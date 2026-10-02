/**
 * SIGOA — Detalle de inspección: estado sin conexión de la galería (Fase E.5)
 * ===================================================================
 * Responsabilidad única: la galería de fotografías es **en línea**. No la
 * guarda, no la descarga y no la replica en el dispositivo; el servidor
 * responde `Cache-Control: private, no-store`, así que sin conexión las
 * miniaturas no llegan a cargarse y quedaría una retícula de imágenes rotas.
 *
 * Lo único que hace esta página, reutilizando el componente de conectividad
 * que ya existe desde D.1 (`SIGOA.conectividad`, también usado por el
 * indicador de la barra superior), es:
 *
 *   1. al detectar que no hay conexión, oculta la retícula y muestra un aviso
 *      con texto e iconografía (RNF §44), de modo que se entienda que la
 *      información no se perdió: no hay fotografías en el dispositivo;
 *   2. al recuperar la conexión, vuelve a la retícula.
 *
 * No se toca IndexedDB, ni la cola, ni la sincronización, ni el CSRF: esta
 * página no realiza peticiones al servidor.
 */
(function () {
    'use strict';

    var CONECTIVIDAD = (window.SIGOA && window.SIGOA.conectividad) ? window.SIGOA.conectividad : null;

    function alIniciar() {
        var galeria = document.getElementById('iidGaleria');
        var aviso   = document.getElementById('iidGaleriaSinConexion');

        if (!galeria || !aviso || !CONECTIVIDAD) {
            return;
        }

        function aplicar(conectado) {
            aviso.hidden = conectado;
            /* Sin conexión las miniaturas no se sirven: se retira la retícula
               en lugar de dejar imágenes rotas. Al volver, el navegador las
               carga de nuevo. */
            galeria.hidden = !conectado;
        }

        CONECTIVIDAD.alCambiar(aplicar);
        aplicar(CONECTIVIDAD.esOnline());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', alIniciar);
    } else {
        alIniciar();
    }
})();
