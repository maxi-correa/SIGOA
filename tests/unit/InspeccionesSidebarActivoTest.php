<?php

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Estado activo del sidebar con el ítem `Inspecciones` (Fase E.6).
 *
 * El sidebar marca la entrada de la pantalla actual. Al añadir `Inspecciones`
 * aparecen rutas que antes no existían (`/inspector/inspecciones`,
 * `/consulta/inspecciones`, `/inspecciones/...`) y el riesgo real es que dos
 * entradas queden activas a la vez, o ninguna.
 *
 * El partial se renderiza directamente con la URI de la pantalla simulada: es
 * más fiable que recorrer páginas reales, porque el estado activo depende de la
 * ruta y cada pantalla debe comprobarse por separado.
 *
 * @internal
 */
final class InspeccionesSidebarActivoTest extends CIUnitTestCase
{
    /**
     * Sidebar renderizado como lo ve el usuario en esa ruta.
     *
     * Se inyecta una petición con la URI de la pantalla, que es lo que decide
     * el estado activo del menú.
     *
     * @param list<string> $roles
     */
    private function sidebar(string $ruta, array $roles): string
    {
        $normalizada = '/' . ltrim($ruta, '/');

        Services::injectMock(
            'request',
            service('request')->withUri(
                service('siteurifactory')->createFromString('http://localhost' . $normalizada)
            )
        );

        return (string) view('layouts/partials/sidebar', ['roles' => $roles], ['saveData' => false]);
    }

    /**
     * Etiqueta de la única entrada activa del menú en esa ruta.
     *
     * @param list<string> $roles
     */
    private function entradaActiva(string $ruta, array $roles): string
    {
        $sidebar = $this->sidebar($ruta, $roles);

        $this->assertSame(
            1,
            preg_match_all('/class="sidebar-link is-active"/', $sidebar),
            "En «{$ruta}» debe quedar exactamente una entrada activa."
        );

        preg_match('/class="sidebar-link is-active".*?<span>(.*?)<\/span>/s', $sidebar, $coincidencia);

        $this->assertArrayHasKey(1, $coincidencia, "No se pudo leer la entrada activa de «{$ruta}».");

        return trim($coincidencia[1]);
    }

    public function testElInspectorVeMisObrasYElListadoDeInspecciones(): void
    {
        $sidebar = $this->sidebar('/inspector/dashboard', ['INSPECTOR']);

        $this->assertStringContainsString('>Mis obras<', $sidebar);
        $this->assertStringContainsString('>Inspecciones<', $sidebar);
        $this->assertStringContainsString('/inspector/inspecciones', $sidebar);
    }

    public function testMisObrasSeDesmarcaEnElListadoDelInspector(): void
    {
        $this->assertSame(
            'Inspecciones',
            $this->entradaActiva('/inspector/inspecciones', ['INSPECTOR'])
        );
    }

    public function testElHistorialDelInspectorPerteneceAlItemInspecciones(): void
    {
        /* `/inspector/inspecciones/ver/{id}` comparte prefijo con el listado: no
           debe reactivar `Mis obras` en el historial. */
        $this->assertSame(
            'Inspecciones',
            $this->entradaActiva('/inspector/inspecciones/ver/12', ['INSPECTOR'])
        );
    }

    public function testElHistorialCompartidoPerteneceAlItemInspecciones(): void
    {
        /* La ruta compartida `/inspecciones/...` se sirve a los roles que
           tienen el ítem de listado, sea cual sea su prefijo de entrada. */
        foreach (['/inspecciones/ver/12', '/inspecciones/detalle/34', '/inspecciones/fotografias/mini/abcd'] as $ruta) {
            foreach ([['INSPECTOR'], ['CONSULTA']] as $roles) {
                $this->assertSame(
                    'Inspecciones',
                    $this->entradaActiva($ruta, $roles),
                    "«{$ruta}» debe activar el ítem del historial."
                );
            }
        }
    }

    public function testElHistorialCompartidoNoActivaEntradasEnLosRolesAdministrativos(): void
    {
        /* Los roles administrativos no tienen listado de inspecciones: llegan
           al historial desde *Ver obra*, así que el menú no marca ninguna
           entrada allí en lugar delies a un ítem al que no pueden volver. */
        foreach (['SUPERADMINISTRADOR', 'ADMINISTRADOR'] as $rol) {
            $this->assertSame(
                0,
                preg_match_all('/class="sidebar-link is-active"/', $this->sidebar('/inspecciones/ver/12', [$rol])),
                "El rol {$rol} no debe marcar entradas en el historial compartido."
            );
        }
    }

    public function testConsultaActivaSuPropioInicio(): void
    {
        $this->assertSame(
            'Inicio',
            $this->entradaActiva('/consulta/dashboard', ['CONSULTA'])
        );
    }

    public function testConsultaActivaInspeccionesEnSuListado(): void
    {
        $this->assertSame(
            'Inspecciones',
            $this->entradaActiva('/consulta/inspecciones', ['CONSULTA'])
        );
    }

    public function testLosRolesAdministrativosNoTienenItemInspecciones(): void
    {
        foreach (['SUPERADMINISTRADOR', 'ADMINISTRADOR'] as $rol) {
            $sidebar = $this->sidebar('/admin/dashboard', [$rol]);

            $this->assertStringNotContainsString(
                '>Inspecciones<',
                $sidebar,
                "El rol {$rol} no navega por listado: entra desde la obra seleccionada."
            );
        }
    }

    public function testSuperadministradorNoAbreElListadoDeInspecciones(): void
    {
        $sidebar = $this->sidebar('/superadmin/dashboard', ['SUPERADMINISTRADOR']);

        $this->assertStringNotContainsString('/inspecciones', $sidebar);
    }
}