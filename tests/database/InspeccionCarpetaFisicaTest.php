<?php

use App\Libraries\Uuid;
use App\Models\InspeccionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fase E.2 — nombre físico de la carpeta de una inspección.
 *
 * Verifica la regla que desambigua el nombre físico cuando varias
 * inspecciones de una obra comparten fecha y hora: dentro de la combinación
 * `obra_id + fecha_inspeccion + hora_inspeccion`, la asignación de sufijos
 * sigue el orden `inspecciones.id ASC`.
 *
 * Usa la conexión `tests` (SQLite en memoria compartida con el grupo) y un
 * esquema mínimo equivalente al de la aplicación. No toca ninguna raíz de
 * almacenamiento real: solo comprueba el nombre que compone el modelo.
 *
 * El esquema se declara idéntico al que usan los demás tests del grupo
 * (`obras`, `usuarios` e `inspecciones`): la base en memoria es compartida y
 * la crea el primer test que pase, de modo que una definición más pobre
 * rompería las claves foráneas que otros verifican.
 *
 * @internal
 */
final class InspeccionCarpetaFisicaTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    private int $obraId;

    private int $otraObraId;

    private int $inspectorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            numero_licitacion VARCHAR(30) NULL,
            tipo_licitacion_id INTEGER NULL,
            nombre VARCHAR(200) NOT NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL,
            created_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('usuarios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(100) NULL,
            apellido VARCHAR(100) NULL,
            usuario VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(150) NULL,
            activo TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspecciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            obra_id INTEGER NOT NULL,
            inspector_id INTEGER NOT NULL,
            fecha_inspeccion DATE NOT NULL,
            hora_inspeccion TIME NULL,
            observacion TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (obra_id) REFERENCES ' . $this->tabla('obras') . ' (id),
            FOREIGN KEY (inspector_id) REFERENCES ' . $this->tabla('usuarios') . ' (id)
        )');

        $this->conn->query('DELETE FROM ' . $this->tabla('inspecciones'));
        $this->conn->query('DELETE FROM ' . $this->tabla('obras'));
        $this->conn->query('DELETE FROM ' . $this->tabla('usuarios'));

        $this->obraId      = $this->crearObra('OBR-000001');
        $this->otraObraId  = $this->crearObra('OBR-000002');
        $this->inspectorId = $this->crearUsuario();
    }

    /* =================================================================
       Inspección con hora normal
       ================================================================= */

    public function testInspeccionConHoraNormalRecibeElNombreBase(): void
    {
        $id = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');

        $modelo = new InspeccionModel();

        $this->assertSame(1, $modelo->sufijoCarpeta($this->obraId, '2026-09-30', '11:14:00', $id));
        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $id)
        );
    }

    /* =================================================================
       Misma obra + misma fecha + misma hora
       ================================================================= */

    public function testDosInspeccionesConMismaFechaYHoraRecibenBaseYSufijo(): void
    {
        $primera = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');
        $segunda = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');

        $modelo = new InspeccionModel();

        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $primera)
        );
        $this->assertSame(
            '11-14-00-2',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $segunda)
        );
    }

    public function testTresInspeccionesConMismaFechaYHoraRecibenBaseYDosSufijos(): void
    {
        $primera = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');
        $segunda = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');
        $tercera = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');

        $modelo = new InspeccionModel();

        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $primera)
        );
        $this->assertSame(
            '11-14-00-2',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $segunda)
        );
        $this->assertSame(
            '11-14-00-3',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $tercera)
        );
    }

    /**
     * El desempate depende del `id`, no del orden de llegada ni del contenido
     * de la fila: al pedir el nombre en orden inverso los sufijos no cambian.
     */
    public function testSufijosLosDeterminaElIdAscYNoElOrdenDeConsulta(): void
    {
        $ids = [
            $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00'),
            $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00'),
            $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00'),
        ];

        $modelo = new InspeccionModel();

        $this->assertSame([1, 2, 3], array_map(
            fn (int $id): int => $modelo->sufijoCarpeta($this->obraId, '2026-09-30', '11:14:00', $id),
            $ids
        ));

        $this->assertSame([3, 2, 1], array_map(
            fn (int $id): int => $modelo->sufijoCarpeta($this->obraId, '2026-09-30', '11:14:00', $id),
            array_reverse($ids)
        ));
    }

    /**
     * Añadir una inspección posterior no altera el nombre ya asignado a las
     * anteriores: la regla es estable mientras no se eliminen inspecciones.
     */
    public function testInspeccionPosteriorNoCambiaLosNombresYaAsignados(): void
    {
        $primera = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');
        $segunda = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');

        $modelo = new InspeccionModel();

        $antes = [
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $primera),
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $segunda),
        ];

        $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');

        $despues = [
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $primera),
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $segunda),
        ];

        $this->assertSame($antes, $despues);
        $this->assertSame(['11-14-00', '11-14-00-2'], $despues);
    }

    /* =================================================================
       hora_inspeccion NULL
       ================================================================= */

    public function testInspeccionSinHoraUsaSinHora(): void
    {
        $id = $this->crearInspeccion($this->obraId, '2026-09-30', null);

        $modelo = new InspeccionModel();

        $this->assertSame(1, $modelo->sufijoCarpeta($this->obraId, '2026-09-30', null, $id));
        $this->assertSame(
            'SIN-HORA',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', null, $id)
        );
    }

    public function testVariasInspeccionesSinHoraRecibenSufijosConsecutivos(): void
    {
        $ids = [
            $this->crearInspeccion($this->obraId, '2026-09-30', null),
            $this->crearInspeccion($this->obraId, '2026-09-30', null),
            $this->crearInspeccion($this->obraId, '2026-09-30', null),
        ];

        $modelo = new InspeccionModel();

        $this->assertSame(
            ['SIN-HORA', 'SIN-HORA-2', 'SIN-HORA-3'],
            array_map(
                fn (int $id): string => (string) $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', null, $id),
                $ids
            )
        );
    }

    /**
     * `00:00:00` no es la ausencia de hora: una inspección con medianoche y
     * otra sin hora no comparten carpeta.
     */
    public function testMedianocheYSinHoraNoSeConfunden(): void
    {
        $medianoche = $this->crearInspeccion($this->obraId, '2026-09-30', '00:00:00');
        $sinHora    = $this->crearInspeccion($this->obraId, '2026-09-30', null);

        $modelo = new InspeccionModel();

        $this->assertSame(
            '00-00-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '00:00:00', $medianoche)
        );
        $this->assertSame(
            'SIN-HORA',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', null, $sinHora)
        );

        $this->assertNotSame(
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '00:00:00', $medianoche),
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', null, $sinHora)
        );
    }

    /* =================================================================
       Ausencia de colisiones
       ================================================================= */

    public function testMismaHoraEnFechasDistintasNoGeneraColision(): void
    {
        $primera = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');
        $segunda = $this->crearInspeccion($this->obraId, '2026-10-01', '11:14:00');
        $tercera = $this->crearInspeccion($this->obraId, '2026-10-01', '11:14:00');

        $modelo = new InspeccionModel();

        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $primera)
        );
        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-10-01', '11:14:00', $segunda)
        );
        $this->assertSame(
            '11-14-00-2',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-10-01', '11:14:00', $tercera)
        );
    }

    public function testMismaFechaYHoraEnObrasDistintasNoGeneraColision(): void
    {
        $primera = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');
        $segunda = $this->crearInspeccion($this->otraObraId, '2026-09-30', '11:14:00');

        $modelo = new InspeccionModel();

        $this->assertSame(1, $modelo->sufijoCarpeta($this->obraId, '2026-09-30', '11:14:00', $primera));
        $this->assertSame(1, $modelo->sufijoCarpeta($this->otraObraId, '2026-09-30', '11:14:00', $segunda));

        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $primera)
        );
        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->otraObraId, '2026-09-30', '11:14:00', $segunda)
        );
    }

    public function testHorasDistintasDeLaMismaFechaNoGeneranColision(): void
    {
        $primera = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');
        $segunda = $this->crearInspeccion($this->obraId, '2026-09-30', '11:15:00');

        $modelo = new InspeccionModel();

        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $primera)
        );
        $this->assertSame(
            '11-15-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:15:00', $segunda)
        );
    }

    /**
     * Ningún nombre compuesto contiene el UUID de la inspección: el UUID es
     * identidad técnica y de sincronización, no nombre de carpeta.
     */
    public function testNombreCompuestoNuncaContieneElUuid(): void
    {
        $uuid = Uuid::v4();

        $this->conn->table('inspecciones')->insert([
            'uuid'             => $uuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-09-30',
            'hora_inspeccion'  => '11:14:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $id     = (int) $this->conn->insertID();
        $nombre = (new InspeccionModel())->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00', $id);

        $this->assertNotNull($nombre);
        $this->assertStringNotContainsString($uuid, $nombre);
        $this->assertDoesNotMatchRegularExpression(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            $nombre
        );
    }

    /* =================================================================
       Normalización de la hora leída del motor
       ================================================================= */

    public function testHoraSeComparaNormalizada(): void
    {
        $id = $this->crearInspeccion($this->obraId, '2026-09-30', '11:14:00');

        $modelo = new InspeccionModel();

        $this->assertSame('11:14:00', $modelo->normalizarHora('11:14:00'));
        $this->assertSame('11:14:00', $modelo->normalizarHora(' 11:14:00 '));
        $this->assertSame('00:00:00', $modelo->normalizarHora('00:00:00'));
        $this->assertSame('11:14:00', $modelo->normalizarHora('11:14'));
        $this->assertSame('11:14:00', $modelo->normalizarHora('11:14:00.000000'));

        $this->assertNull($modelo->normalizarHora('no-es-una-hora'));
        $this->assertNull($modelo->normalizarHora('24:00:00'));
        $this->assertNull($modelo->normalizarHora('11:60:00'));

        /* La normalización es responsabilidad del punto de entrada: aunque la
           hora llegue con otra forma equivalente desde el motor, el nombre y el
           sufijo son los mismos. */
        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14:00.000000', $id)
        );
        $this->assertSame(
            '11-14-00',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:14', $id)
        );
    }

    /* =================================================================
       Hora ilegible
       ================================================================= */

    /**
     * Una hora presente pero ilegible no produce carpeta y, sobre todo, no se
     * convierte en `SIN-HORA`: esa carpeta pertenece a las inspecciones que no
     * tienen hora, y mezclarlas ocultaría un dato corrupto detrás de un nombre
     * aparentemente válido.
     */
    public function testHoraIlegibleNoSeConfundeConLaAusenciaDeHora(): void
    {
        $sinHora = $this->crearInspeccion($this->obraId, '2026-09-30', null);

        $modelo = new InspeccionModel();

        $this->assertNull(
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', 'no-es-una-hora', $sinHora)
        );
        $this->assertNull(
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '24:00:00', $sinHora)
        );
        $this->assertNull(
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', '11:60:00', $sinHora)
        );

        /* La inspección sin hora sigue recibiendo su carpeta propia. */
        $this->assertSame(
            'SIN-HORA',
            $modelo->nombreCarpetaInspeccion($this->obraId, '2026-09-30', null, $sinHora)
        );
    }

    /* =================================================================
       Utilidades
       ================================================================= */

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function crearObra(string $codigo): int
    {
        $this->conn->table('obras')->insert([
            'codigo'         => $codigo,
            'nombre'         => 'OBRA PRUEBA E2',
            'estado_obra_id' => 1,
        ]);

        return (int) $this->conn->insertID();
    }

    private function crearUsuario(): int
    {
        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_E2_' . bin2hex(random_bytes(3)),
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);

        return (int) $this->conn->insertID();
    }

    private function crearInspeccion(int $obraId, string $fecha, ?string $hora): int
    {
        $this->conn->table('inspecciones')->insert([
            'uuid'             => Uuid::v4(),
            'obra_id'          => $obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => $fecha,
            'hora_inspeccion'  => $hora,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->conn->insertID();
    }
}