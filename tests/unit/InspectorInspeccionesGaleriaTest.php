<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Galería de fotografías de una inspección (Fase E.5).
 *
 * Comprueba por estructura las fronteras de la fase, que son requisitos y no
 * preferencias:
 *
 * 1. **No hay endpoint público de imágenes.** Las dos rutas que sirven
 *    fotografías viven en el grupo `inspector`, con `auth` + `role:INSPECTOR`.
 * 2. **La URL es identidad técnica, no una ruta.** El navegador pide por `uuid`
 *    y el servidor resuelve la ruta física desde la base. Un
 *    `path traversal` no tiene por dónde entrar, y el servicio vuelve a
 *    comprobar la contención en disco.
 * 3. **La autorización se resuelve encadenando datos**, fotografía →
 *    inspección → obra → asignación vigente, igual que el detalle de la
 *    inspección: no se acepta un identificador de obra del cliente.
 * 4. **Nada de lo diferido entra en esta fase:** sin descarga, sin caché
 *    histórica (`no-store`), sin IndexedDB para las fotos del servidor, sin
 *    migraciones y sin tocar la sincronización.
 *
 * El comportamiento observable (render y archivos reales) se verifica en
 * `tests/database/InspectorInspeccionesGaleriaRenderTest.php`.
 *
 * @internal
 */
final class InspectorInspeccionesGaleriaTest extends CIUnitTestCase
{
    private function leerApp(string $relativa): string
    {
        $contenido = file_get_contents(APPPATH . ltrim($relativa, '/\\'));

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }

    private function leerPublic(string $relativa): string
    {
        $ruta = rtrim((string) FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativa, '/\\');

        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer el archivo público: {$relativa}");

        return $contenido ?: '';
    }

    /**
     * Cuerpo de un método PHP, desde su firma hasta la llave que lo cierra.
     */
    private function metodo(string $contenido, string $firma): string
    {
        $desde = strpos($contenido, $firma);

        $this->assertIsInt($desde, "No se encontró el método «{$firma}»");

        $nivel  = 0;
        $inicio = strpos($contenido, '{', (int) $desde);

        $this->assertIsInt($inicio, "No se encontró el cuerpo del método «{$firma}»");

        for ($i = (int) $inicio; $i < strlen($contenido); $i++) {
            if ($contenido[$i] === '{') {
                $nivel++;
            } elseif ($contenido[$i] === '}') {
                $nivel--;

                if ($nivel === 0) {
                    return substr($contenido, (int) $inicio, $i - (int) $inicio + 1);
                }
            }
        }

        $this->fail("No se pudo cerrar el método «{$firma}»");

        return '';
    }

    /* ----------------------------------------------------------------
     * Rutas y exposición
     * ---------------------------------------------------------------- */

    public function testLasRutasDeFotografiasExistenYUsanLaIdentidadTecnica(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString(
            "'fotografias/ver/(:segment)'",
            $rutas,
            'El original se pide por uuid.'
        );
        $this->assertStringContainsString(
            "'fotografias/mini/(:segment)'",
            $rutas,
            'La miniatura se pide por uuid.'
        );
    }

    public function testLasRutasDeFotografiasEstanEnElGrupoDelInspector(): void
    {
        $rutas    = $this->leerApp('Config/Routes.php');
        $posicion = strpos($rutas, "'fotografias/ver/(:segment)'");

        $this->assertIsInt($posicion);

        $grupo   = strpos($rutas, "$" . "routes->group('inspector'");
        $filtros = strpos($rutas, "'filter' => ['auth', 'role:INSPECTOR']");

        $this->assertIsInt($grupo);
        $this->assertIsInt($filtros);
        $this->assertGreaterThan($grupo, $filtros, 'El filtro del grupo se declara al abrirlo.');

        foreach (["'fotografias/ver/(:segment)'", "'fotografias/mini/(:segment)'"] as $ruta) {
            $definicion = strpos($rutas, $ruta);

            $this->assertIsInt($definicion);
            $this->assertGreaterThan(
                $grupo,
                $definicion,
                'Las fotografías se sirven dentro del grupo del inspector, no fuera.'
            );
        }
    }

    public function testNoExisteNingunEndpointPublicoDeImagenes(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        /* Fuera del área del inspector no puede haber ninguna ruta de
           fotografías: el que está antes del grupo es autenticación pública. */
        $posiciones = [];
        $offset     = 0;

        while (($posicion = strpos($rutas, 'fotografias/', $offset)) !== false) {
            $posiciones[] = $posicion;
            $offset       = $posicion + 1;
        }

        $this->assertNotEmpty($posiciones);

        $grupo = (int) strpos($rutas, "$" . "routes->group('inspector'");

        foreach ($posiciones as $posicion) {
            if ($posicion < $grupo) {
                $contexto = substr($rutas, max(0, $posicion - 220), 220);

                $this->assertStringContainsString(
                    "'filter'",
                    $contexto,
                    'Toda ruta de fotografías debe estar detrás de un filtro.'
                );
                $this->assertStringNotContainsString('role:INSPECTOR', $contexto);
            }
        }
    }

    /* ----------------------------------------------------------------
     * Autorización
     * ---------------------------------------------------------------- */

    public function testLaAutorizacionSeResuelveEncadenandoLosDatos(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Fotografias.php');
        $metodo    = $this->metodo($contenido, 'private function fotografiaAutorizada(');

        $this->assertStringContainsString(
            'findVisiblePorUuid',
            $metodo,
            'La fotografía se busca por su identidad técnica.'
        );
        $this->assertStringContainsString(
            'findDetalle',
            $metodo,
            'De la fotografía se resuelve su inspección.'
        );
        $this->assertStringContainsString(
            'puedeConsultarObra',
            $metodo,
            'Desde E.6 la imagen se autoriza con la misma consulta que la pantalla que la muestra.'
        );

        foreach (['getPost', 'getQuery', 'getVar', 'ruta_relativa', 'ruta_thumbnail'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $metodo,
                'La autorización no se deduce de lo que envía el navegador, ni de una ruta.'
            );
        }
    }

    public function testUnaFotografiaAnuladaNoSeSirve(): void
    {
        $contenido = $this->leerApp('Models/FotografiaModel.php');

        $this->assertStringContainsString(
            'findVisiblePorUuid',
            $contenido,
            'La búsqueda de servicio ignora las fotografías anuladas.'
        );

        $this->assertMatchesRegularExpression(
            '/findVisiblePorUuid\(string \$uuid\): \?object\s*\{\s*return \$this->where\(\'uuid\', \$uuid\)\s*->where\(\'anulada\', false\)/',
            $contenido,
            'Solo se(localiza fotografía vigente: `anulada = 0` forma parte de la consulta.'
        );
    }

    /* ----------------------------------------------------------------
     * Rutas físicas y path traversal
     * ---------------------------------------------------------------- */

    public function testLaRutaRelativaSeResuelveContraLaRaizDeAlmacenamiento(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Fotografias.php');
        $metodo    = $this->metodo($contenido, 'private function servirImagen(');

        $this->assertStringContainsString(
            'ObraAlmacenamiento',
            $metodo,
            'La resolución de la ruta physical es responsabilidad de la arquitectura existente.'
        );
        $this->assertStringContainsString(
            'absolutoDesdeRelativa',
            $metodo,
            'La ruta relativa se compone con el servicio de almacenamiento, no a mano.'
        );
    }

    public function testLaContencionEnDiscoSeCompruebaTresVeces(): void
    {
        $metodo = $this->metodo(
            $this->leerApp('Controllers/Inspector/Fotografias.php'),
            'private function servirImagen('
        );

        $this->assertStringContainsString(
            'absolutoDesdeRelativa',
            $metodo,
            'Primera barrera: la ruta con `../` o absoluta se descarta.'
        );
        $this->assertStringContainsString(
            'realpath',
            $metodo,
            'Segunda barrera: la contención real se comprueba con realpath.'
        );
        $this->assertStringContainsString(
            'strncmp',
            $metodo,
            'La comparación incluye el separador: una carpeta hermana no puede pasar.'
        );
        $this->assertStringContainsString(
            'DIRECTORY_SEPARATOR',
            $metodo,
            'El prefijo de la raíz termina en separador.'
        );
        $this->assertStringContainsString(
            'finfo',
            $metodo,
            'Tercera barrera: solo se sirven imágenes, según su contenido.'
        );
        $this->assertStringContainsString('image/', $metodo);
        $this->assertStringContainsString('is_readable', $metodo);
        $this->assertStringContainsString('nosniff', $metodo, 'El navegador no debe interpretar el archivo por su extensión.');
    }

    public function testLaRutaRelativaNoSeComponeEnElServidor(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Fotografias.php');

        foreach ([
            'nombreCarpetaInspeccion',
            'rutaRelativaImagen',
            'rutaRelativaThumbnail',
            'ensureEstructura',
            'asegurarEstructura',
            'mkdir',
            'unlink',
        ] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $contenido,
                'Servir fotografías no compone ni escribe la estructura física: esa es la sincronización.'
            );
        }
    }

    public function testLaRespuestaNoInvitaACachearLaFotografia(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Fotografias.php');

        $this->assertStringContainsString('Cache-Control', $contenido);
        $this->assertStringContainsString('no-store', $contenido, 'La caché histórica de fotografías es una fase posterior.');
        $this->assertStringContainsString('Content-Type', $contenido);
        $this->assertStringContainsString('Content-Length', $contenido);
    }

    public function testLoNoServibleRespondeAlwaysNotFound(): void
    {
        $metodo = $this->metodo(
            $this->leerApp('Controllers/Inspector/Fotografias.php'),
            'private function noDisponible('
        );

        $this->assertStringContainsString('404', $metodo);
        $this->assertStringNotContainsString(
            '403',
            $metodo,
            'Un mismo 404 para todo lo no servible evita revelar qué fotografías existen.'
        );
    }

    /* ----------------------------------------------------------------
     * Lo que esta fase NO hace
     * ---------------------------------------------------------------- */

    public function testElServicioNoEscribeDatosNiEnDisco(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Fotografias.php');

        foreach (['insert(', 'update(', 'delete(', 'save(', 'file_put_contents'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $contenido,
                'Servir una fotografía es de solo lectura.'
            );
        }
    }

    public function testLaGaleriaNoSeGuardaEnElDispositivoNiSeSincroniza(): void
    {
        $script = $this->leerPublic('assets/js/pages/inspeccion-detalle.js');

        $this->assertStringNotContainsString('SIGOA.almacenamiento', $script, 'La galería no lee ni escribe IndexedDB.');
        $this->assertStringNotContainsString('SIGOA.sincronizacion', $script, 'La galería no toca la cola.');
        $this->assertStringNotContainsString('SIGOA.csrf', $script, 'La galería no hace peticiones al servidor.');
        $this->assertStringNotContainsString('caches', $script, 'La galería no cachea en el dispositivo.');
        $this->assertStringNotContainsString('fetch(', $script);

        /* Lo único que hace es mostrar el estado sin conexión. */
        $this->assertStringContainsString('SIGOA.conectividad', $script, 'Reutiliza el componente de conectividad de D.1.');
    }

    public function testLaPaginaNoSeSirveDesdeUnRecursoEstaticoDelShell(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringNotContainsString(
            'fotografias',
            $sw,
            'Las fotografías no se precachean: la galería en línea no se guarda en el dispositivo.'
        );
    }

    public function testNoSeAgregoNingunaMigracionNiSeTocoLaSincronizacion(): void
    {
        $migraciones = glob(APPPATH . 'Database/Migrations/*.php') ?: [];

        $nombres = array_map(static fn (string $ruta): string => basename($ruta), $migraciones);
        sort($nombres);

        $this->assertSame(
            '2026-09-23-122000_DropUniqueObraFechaInspeccion.php',
            end($nombres),
            'E.5 no introduce cambios de esquema.'
        );

        $sincronizador = $this->leerApp('Controllers/Inspector/Sincronizar.php');

        foreach (['listarPorInspeccion', 'findVisiblePorUuid', 'Fotografias::'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $sincronizador,
                'La sincronización no se modifica: su idempotencia sigue usando findByUuid().'
            );
        }
    }

    public function testElOrdenDeLasFotografiasEsElDelRegistro(): void
    {
        $contenido = $this->leerApp('Models/FotografiaModel.php');

        $this->assertMatchesRegularExpression(
            '/listarPorInspeccion\(int \$inspeccionId\): array.*?orderBy\(\'id\', \'ASC\'\).*?findAll\(\);/s',
            $contenido,
            'Orden estable: el orden en que el servidor registró cada fotografía.'
        );
    }
}
