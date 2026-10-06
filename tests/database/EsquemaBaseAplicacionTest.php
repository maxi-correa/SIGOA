<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database as DatabaseConfig;

/**
 * Guardia de esquema de la base configurada para la aplicación (Fase D.6.1).
 *
 * En D.6 se descubrió que la suite podía estar completamente en verde
 * mientras la base real no tenía las migraciones de D.3/D.4 aplicadas: los
 * tests de `tests/database` construyen su propio esquema SQLite e ignoran
 * el estado de la base configurada en `app/Config/Database.php` y `.env`.
 *
 * Este test es la contraparte de solo lectura de
 * `SincronizarMysqlRealTest`: no crea bases ni escribe datos. Solo comprueba
 * que en la base que la aplicación usa realmente:
 *
 *   1. no quede ninguna migración de `app/Database/Migrations` pendiente;
 *   2. `inspecciones.uuid` y `fotografias.uuid` existan, no admitan nulos y
 *      estén en un índice único;
 *   3. el índice único `obra_id_fecha_inspeccion` ya no exista, de modo que
 *      una obra pueda tener varias inspecciones el mismo día.
 *
 * Si el entorno no tiene una base MySQL/MariaDB disponible, el test se
 * omite en lugar de fallar.
 *
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Group('mysql-real')]
final class EsquemaBaseAplicacionTest extends CIUnitTestCase
{
    /**
     * Conexión de solo lectura a la base configurada para la aplicación.
     */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $config = config(DatabaseConfig::class)->default;

        if (($config['DBDriver'] ?? '') !== 'MySQLi') {
            $this->markTestSkipped('La base configurada para la aplicación no es MySQL: el guardia de esquema no aplica en este entorno.');
        }

        try {
            $this->db = \CodeIgniter\Database\Config::connect($config, false);
            $this->db->connect();
        } catch (\Throwable $e) {
            $this->markTestSkipped('No se pudo conectar a la base configurada para la aplicación: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->close();
        }

        parent::tearDown();
    }

    public function testLaBaseDeLaAplicacionNoTieneMigracionesPendientes(): void
    {
        $aplicadas = [];

        foreach ($this->db->table('migrations')->select('version')->where('group', 'default')->get()->getResultArray() as $fila) {
            $aplicadas[preg_replace('/[^0-9]/', '', (string) $fila['version'])] = true;
        }

        $pendientes = [];

        foreach (glob(APPPATH . 'Database/Migrations/*.php') ?: [] as $archivo) {
            $nombre = basename($archivo, '.php');

            if (preg_match('/\A(\d{4}[_-]?\d{2}[_-]?\d{2}[_-]?\d{6})_\w+\z/', $nombre, $coincidencia) !== 1) {
                continue;
            }

            $version = preg_replace('/[^0-9]/', '', $coincidencia[1]);

            if (! isset($aplicadas[$version])) {
                $pendientes[] = $nombre;
            }
        }

        $this->assertSame(
            [],
            $pendientes,
            'Hay migraciones sin aplicar en la base de la aplicación. Ejecutá `php spark migrate`: ' . implode(', ', $pendientes)
        );
    }

    public function testUuidEsObligatorioYUnicoEnAmbasTablas(): void
    {
        foreach (['inspecciones', 'fotografias'] as $tabla) {
            $columna = $this->db->table('information_schema.COLUMNS')
                ->select('IS_NULLABLE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH')
                ->where('TABLE_SCHEMA', $this->nombreBase())
                ->where('TABLE_NAME', $tabla)
                ->where('COLUMN_NAME', 'uuid')
                ->get()
                ->getRowArray();

            $this->assertIsArray($columna, "La tabla `{$tabla}` no tiene la columna `uuid`: falta aplicar las migraciones de D.3/D.4.");
            $this->assertSame('NO', $columna['IS_NULLABLE'], "`{$tabla}.uuid` no debe admitir nulos.");
            $this->assertSame(36, (int) $columna['CHARACTER_MAXIMUM_LENGTH'], "`{$tabla}.uuid` debe ser CHAR(36).");
            $this->assertGreaterThan(
                0,
                $this->indicesUnicosSobre($tabla, 'uuid'),
                "`{$tabla}.uuid` debe estar en un índice único: es la garantía de idempotencia del servidor."
            );
        }
    }

    public function testNoQuedaElIndiceUnicoObraFechaInspeccion(): void
    {
        $this->assertSame(
            0,
            $this->existeIndice('inspecciones', 'obra_id_fecha_inspeccion') ? 1 : 0,
            'El índice único `obra_id_fecha_inspeccion` impide registrar varias inspecciones de la misma obra el mismo día: debe estar eliminado (migración de D.3/D.4).'
        );
    }

    public function testObrasTieneConfiguracionEconomicaDeCertificacion(): void
    {
        $this->assertColumna('obras', 'presupuesto_oficial', 'decimal(15,3)', true);
        $this->assertColumna('obras', 'monto_contrato', 'decimal(15,3)', true);
        $this->assertColumna('obras', 'monto_contractual_vigente', 'decimal(15,3)', true);
        $this->assertColumnaBooleana('obras', 'tiene_anticipo_financiero', false, '0');
        $this->assertColumna('obras', 'porcentaje_anticipo_financiero', 'decimal(6,3)', true);
        $this->assertColumnaBooleana('obras', 'tiene_fondo_reparo', false, '0');
        $this->assertColumna('obras', 'porcentaje_fondo_reparo', 'decimal(6,3)', true);
        $this->assertColumnaBooleana('obras', 'fondo_reparo_con_poliza', true, null);
    }

    public function testCertificadosTieneEstadoAnticipoYRetencionFondoReparo(): void
    {
        $this->assertColumna('certificados', 'descuento_anticipo', 'decimal(15,3)', true);
        $this->assertColumna('certificados', 'estado_anticipo', 'varchar(30)', true);
        $this->assertColumna('certificados', 'retencion_fondo_reparo', 'decimal(15,3)', true);
        $this->assertColumna('certificados', 'monto_neto', 'decimal(15,3)', true);
        $this->assertNull(
            $this->columna('certificados', 'fondo_reparo'),
            'La columna `certificados.fondo_reparo` debe haberse renombrado a `retencion_fondo_reparo`.'
        );
    }

    private function nombreBase(): string
    {
        return (string) $this->db->getDatabase();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function columna(string $tabla, string $columna): ?array
    {
        $fila = $this->db->table('information_schema.COLUMNS')
            ->select('IS_NULLABLE, COLUMN_TYPE, COLUMN_DEFAULT')
            ->where('TABLE_SCHEMA', $this->nombreBase())
            ->where('TABLE_NAME', $tabla)
            ->where('COLUMN_NAME', $columna)
            ->get()
            ->getRowArray();

        return is_array($fila) ? $fila : null;
    }

    private function assertColumna(string $tabla, string $columna, string $tipo, bool $nullable): void
    {
        $info = $this->columna($tabla, $columna);

        $this->assertIsArray($info, "Falta `{$tabla}.{$columna}`.");
        $this->assertSame($tipo, strtolower((string) $info['COLUMN_TYPE']), "`{$tabla}.{$columna}` debe ser {$tipo}.");
        $this->assertSame($nullable ? 'YES' : 'NO', $info['IS_NULLABLE'], "`{$tabla}.{$columna}` nullability incorrecta.");
    }

    private function assertColumnaBooleana(string $tabla, string $columna, bool $nullable, ?string $default): void
    {
        $info = $this->columna($tabla, $columna);

        $this->assertIsArray($info, "Falta `{$tabla}.{$columna}`.");
        $this->assertSame('tinyint(1)', strtolower((string) $info['COLUMN_TYPE']), "`{$tabla}.{$columna}` debe ser BOOLEAN/TINYINT(1).");
        $this->assertSame($nullable ? 'YES' : 'NO', $info['IS_NULLABLE'], "`{$tabla}.{$columna}` nullability incorrecta.");

        $valorDefault = $info['COLUMN_DEFAULT'];

        if ($default === null) {
            $this->assertTrue($valorDefault === null || $valorDefault === 'NULL', "`{$tabla}.{$columna}` no debe tener default.");
        } else {
            $this->assertSame($default, (string) $valorDefault, "`{$tabla}.{$columna}` default incorrecto.");
        }
    }

    private function indicesUnicosSobre(string $tabla, string $columna): int
    {
        return (int) $this->db->table('information_schema.STATISTICS')
            ->select('INDEX_NAME')
            ->where('TABLE_SCHEMA', $this->nombreBase())
            ->where('TABLE_NAME', $tabla)
            ->where('COLUMN_NAME', $columna)
            ->where('NON_UNIQUE', 0)
            ->groupBy('INDEX_NAME')
            ->countAllResults();
    }

    private function existeIndice(string $tabla, string $indice): bool
    {
        return $this->db->table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $this->nombreBase())
            ->where('TABLE_NAME', $tabla)
            ->where('INDEX_NAME', $indice)
            ->countAllResults() > 0;
    }
}
