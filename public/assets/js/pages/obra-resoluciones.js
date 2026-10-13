/* ===================================================================
   SIGOA — Página: Resoluciones de obra
   ===================================================================
   Gestiona el alta/edición de resoluciones, la confirmación y la
   eliminación. Los campos de monto y días se muestran u ocultan según
   las reglas que la vista adjunta a cada tipo (data-monto / data-dias),
   de modo que la decisión del backend no se duplica aquí.
   =================================================================== */

(function () {
    'use strict';

    var page = document.querySelector('.resoluciones-page');

    if (!page || page.dataset.puedeEditar !== '1') {
        return;
    }

    var form       = document.getElementById('formResolucion');
    var urlCrear   = page.dataset.urlCrear;
    var urlEditar  = page.dataset.urlActualizar;

    var select    = document.getElementById('tipo_resolucion_id');
    var grupoMonto = document.getElementById('grupo-monto');
    var grupoDias  = document.getElementById('grupo-dias');
    var inputMonto = document.getElementById('monto');
    var inputDias  = document.getElementById('dias');

    var modal        = document.getElementById('modalResolucion');
    var tituloModal  = document.getElementById('modalResolucionTituloTexto');
    var inputId      = document.getElementById('resolucion_id');

    function abrir(overlay) {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
    }

    function cerrar(overlay) {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
    }

    var marcadorObservaciones = document.getElementById('observaciones-requerido');
    var inputObservaciones    = document.getElementById('observaciones');

    function reglasDelTipo() {
        var opcion = select.options[select.selectedIndex];

        return {
            monto: opcion ? opcion.dataset.monto : '',
            dias: opcion ? opcion.dataset.dias : '',
            observaciones: opcion ? opcion.dataset.observaciones : ''
        };
    }

    function aplicarReglas() {
        var reglas = reglasDelTipo();

        grupoMonto.hidden = reglas.monto === 'oculto';
        grupoDias.hidden = reglas.dias === 'oculto';

        inputMonto.disabled = reglas.monto === 'oculto';
        inputDias.disabled = reglas.dias === 'oculto';

        if (inputMonto.disabled) {
            inputMonto.value = '';
        }

        if (inputDias.disabled) {
            inputDias.value = '';
        }

        if (marcadorObservaciones) {
            var requiereObservaciones = reglas.observaciones === 'requerido';
            marcadorObservaciones.hidden = !requiereObservaciones;
            inputObservaciones.setAttribute('aria-required', requiereObservaciones ? 'true' : 'false');
        }
    }

    var botones = page.querySelectorAll('[data-accion]');

    botones.forEach(function (boton) {
        boton.addEventListener('click', function () {
            var accion = boton.dataset.accion;

            if (accion === 'editar') {
                inputId.value = boton.dataset.id || '';
                select.value = boton.dataset.tipo || '';
                document.getElementById('numero').value = boton.dataset.numero || '';
                document.getElementById('anio').value = boton.dataset.anio || '';
                document.getElementById('fecha_resolucion').value = boton.dataset.fecha || '';
                inputMonto.value = boton.dataset.monto || '';
                inputDias.value = boton.dataset.dias || '';
                document.getElementById('observaciones').value = boton.dataset.observaciones || '';

                form.action = urlEditar;
                tituloModal.textContent = 'Modificar resolución';

                aplicarReglas();
                abrir(modal);
                select.focus();
                return;
            }

            if (accion === 'confirmar') {
                document.getElementById('resolucion_id_confirmar').value = boton.dataset.id || '';
                abrir(document.getElementById('modalConfirmarResolucion'));
                return;
            }

            if (accion === 'eliminar') {
                document.getElementById('resolucion_id_eliminar').value = boton.dataset.id || '';
                abrir(document.getElementById('modalEliminarResolucion'));
            }
        });
    });

    document.getElementById('btnNuevaResolucion').addEventListener('click', function () {
        form.reset();
        inputId.value = '';
        form.action = urlCrear;
        tituloModal.textContent = 'Registrar resolución';

        aplicarReglas();
        abrir(modal);
        select.focus();
    });

    document.getElementById('btnCancelarResolucion').addEventListener('click', function () {
        cerrar(modal);
    });

    document.getElementById('btnCancelarConfirmarResolucion').addEventListener('click', function () {
        cerrar(document.getElementById('modalConfirmarResolucion'));
    });

    document.getElementById('btnCancelarEliminarResolucion').addEventListener('click', function () {
        cerrar(document.getElementById('modalEliminarResolucion'));
    });

    [modal,
        document.getElementById('modalConfirmarResolucion'),
        document.getElementById('modalEliminarResolucion')].forEach(function (overlay) {
        overlay.addEventListener('click', function (evento) {
            if (evento.target === overlay) {
                cerrar(overlay);
            }
        });
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') {
            return;
        }

        [modal,
            document.getElementById('modalConfirmarResolucion'),
            document.getElementById('modalEliminarResolucion')].forEach(function (overlay) {
            if (! overlay.hidden) {
                cerrar(overlay);
            }
        });
    });

    select.addEventListener('change', aplicarReglas);

    if (! modal.hidden) {
        aplicarReglas();
    }
})();
