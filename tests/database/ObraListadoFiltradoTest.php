<?php

use App\Models\ObraModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Filtrado server-side del listado paginado de obras (Buscador y filtro por
 * estado del dashboard).
 *
 * Construye su propio esquema SQLite, por lo que no depende de que las
 * migraciones de MySQL estén aplicadas. La insensibilidad a acentos es una
 * propiedad de la collation `utf8mb4_general_ci` de MySQL y no se reproduce
 * aquí (LIKE de SQLite solo es insensible a mayúsculas en ASCII); estos
 * casos cubren el filtrado, el escape de comodines y la interacción con la
 * paginación.
 *
 * @internal
 */
final class ObraListadoFiltradoTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        foreach (['estados_obra', 'barrios', 'empresas', 'tipos_licitacion', 'obras'] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        $this->conn->query('CREATE TABLE ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo TEXT NOT NULL,
            expediente_municipal TEXT NOT NULL,
            numero_licitacion TEXT NULL,
            nombre TEXT NOT NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            tipo_licitacion_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL,
            created_at DATETIME NULL
        )');

        $this->sembrar();
    }

    protected function tearDown(): void
    {
        foreach (['obras', 'tipos_licitacion', 'empresas', 'barrios', 'estados_obra'] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        parent::tearDown();
    }

    public function testSinFiltrosDevuelveTodasLasObras(): void
    {
        $modelo = new ObraModel();

        $this->assertCount(4, $modelo->listarPaginado(10));
    }

    public function testBuscaPorNombreSinDistinguirMayusculas(): void
    {
        $modelo = new ObraModel();

        $resultados = $modelo->listarPaginado(10, 'plaza');

        $this->assertCount(2, $resultados);
        foreach ($resultados as $obra) {
            $this->assertStringContainsString('PLAZA', $obra->nombre);
        }
    }

    public function testBuscaPorBarrioExpedienteEmpresaYNumeroDeLicitacion(): void
    {
        $modelo = new ObraModel();

        /* Barrio: "ALMAGRO" (obras 2, 3 y 4). */
        $porBarrio = $modelo->listarPaginado(10, 'almagro');
        $this->assertCount(3, $porBarrio);

        /* Expediente municipal. */
        $porExpediente = $modelo->listarPaginado(10, '4356');
        $this->assertCount(1, $porExpediente);
        $this->assertSame('4356-M-2026', $porExpediente[0]->expediente_municipal);

        /* Razón social de la empresa (obras 1 y 4 usan "CONSTRUCTORA DEL SUR"). */
        $porEmpresa = $modelo->listarPaginado(10, 'del sur');
        $this->assertCount(2, $porEmpresa);
        foreach ($porEmpresa as $obra) {
            $this->assertStringContainsString('DEL SUR', $obra->empresa_razon_social);
        }

        /* Número de licitación. */
        $porLicitacion = $modelo->listarPaginado(10, '34/2026');
        $this->assertCount(1, $porLicitacion);
        $this->assertSame('4356-M-2026', $porLicitacion[0]->expediente_municipal);
    }

    public function testRecortaLosEspaciosDelTermino(): void
    {
        $modelo = new ObraModel();

        $this->assertSame(
            count($modelo->listarPaginado(10, 'plaza')),
            count($modelo->listarPaginado(10, '   plaza   '))
        );
    }

    public function testLosComodinesDelTextoSeTratanComoLiterales(): void
    {
        $modelo = new ObraModel();

        /* Sin escape, '%' devolvería todas las obras. */
        $this->assertCount(0, $modelo->listarPaginado(10, '%'));
    }

    public function testSinCoincidenciasDevuelveListaVacia(): void
    {
        $modelo = new ObraModel();

        $this->assertSame([], $modelo->listarPaginado(10, 'zzzzzz'));
    }

    public function testFiltraPorEstado(): void
    {
        $modelo = new ObraModel();

        $resultados = $modelo->listarPaginado(10, '', 3);

        $this->assertCount(1, $resultados);
        $this->assertSame(3, (int) $resultados[0]->estado_obra_id);
    }

    public function testCombinaBusquedaYEstado(): void
    {
        $modelo = new ObraModel();

        /* "PLAZA" aparece en dos obras, pero solo una está FINALIZADA (id 5). */
        $resultados = $modelo->listarPaginado(10, 'plaza', 5);

        $this->assertCount(1, $resultados);
        $this->assertSame('PLAZA DEL SUR', $resultados[0]->nombre);
        $this->assertSame(5, (int) $resultados[0]->estado_obra_id);
    }

    public function testLaPaginacionOperaSobreElConjuntoFiltrado(): void
    {
        $modelo = new ObraModel();

        $primera = $modelo->listarPaginado(1, 'plaza');

        $this->assertCount(1, $primera);
        $this->assertSame(2, $modelo->pager->getTotal(), 'El total paginado debe reflejar solo las coincidencias');
        $this->assertSame(2, $modelo->pager->getPageCount('default'));
    }

    /**
     * Cuatro obras con combinaciones que permiten aislar cada campo de
     * búsqueda y cada estado.
     */
    private function sembrar(): void
    {
        $filas = [
            [$this->tabla('estados_obra'), [
                ['id' => 1, 'estado' => 'PREVIO INICIO', 'activo' => 1],
                ['id' => 2, 'estado' => 'EN EJECUCION', 'activo' => 1],
                ['id' => 3, 'estado' => 'NEUTRALIZADA', 'activo' => 1],
                ['id' => 5, 'estado' => 'FINALIZADA', 'activo' => 1],
            ]],
            [$this->tabla('barrios'), [
                ['id' => 1, 'nombre' => 'PUEYRREDON', 'activo' => 1],
                ['id' => 2, 'nombre' => 'ALMAGRO', 'activo' => 1],
            ]],
            [$this->tabla('empresas'), [
                ['id' => 1, 'razon_social' => 'CONSTRUCTORA DEL SUR S.A.', 'activo' => 1],
                ['id' => 2, 'razon_social' => 'EMPRESA NORTE SRL', 'activo' => 1],
            ]],
            [$this->tabla('tipos_licitacion'), [
                ['id' => 1, 'tipo_licitacion' => 'PUBLICA', 'activo' => 1],
            ]],
            [$this->tabla('obras'), [
                [
                    'id' => 1, 'codigo' => 'OBR-000001', 'expediente_municipal' => '4356-M-2026',
                    'numero_licitacion' => '34/2026', 'nombre' => 'PLAZA PUEYRREDON',
                    'barrio_id' => 1, 'empresa_id' => 1, 'tipo_licitacion_id' => 1,
                    'estado_obra_id' => 2, 'created_at' => '2026-01-01 10:00:00',
                ],
                [
                    'id' => 2, 'codigo' => 'OBR-000002', 'expediente_municipal' => '5000-M-2026',
                    'numero_licitacion' => '35/2026', 'nombre' => 'RECUENTO BARRIO ALMAGRO',
                    'barrio_id' => 2, 'empresa_id' => 2, 'tipo_licitacion_id' => 1,
                    'estado_obra_id' => 3, 'created_at' => '2026-01-02 10:00:00',
                ],
                [
                    'id' => 3, 'codigo' => 'OBR-000003', 'expediente_municipal' => '6000-M-2026',
                    'numero_licitacion' => '36/2026', 'nombre' => 'VEREDAS GENERALES',
                    'barrio_id' => 2, 'empresa_id' => 2, 'tipo_licitacion_id' => 1,
                    'estado_obra_id' => 1, 'created_at' => '2026-01-03 10:00:00',
                ],
                [
                    'id' => 4, 'codigo' => 'OBR-000004', 'expediente_municipal' => '7000-M-2026',
                    'numero_licitacion' => '37/2026', 'nombre' => 'PLAZA DEL SUR',
                    'barrio_id' => 2, 'empresa_id' => 1, 'tipo_licitacion_id' => 1,
                    'estado_obra_id' => 5, 'created_at' => '2026-01-04 10:00:00',
                ],
            ]],
        ];

        foreach ($filas as [$tabla, $registros]) {
            foreach ($registros as $registro) {
                $this->conn->table($tabla)->insert($registro);
            }
        }
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
