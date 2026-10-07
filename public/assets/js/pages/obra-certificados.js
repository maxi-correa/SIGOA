/**
 * SIGOA — Certificados de obra
 *
 * Alterna campos de anticipo y fondo, calcula el anticipo total
 * para la vista previa y abre los modales de confirmación y carga.
 */
(function () {
    'use strict';

    var pagina = document.querySelector('.cert-page');
    if (!pagina) return;

    var puedeEditar = pagina.getAttribute('data-puede-editar') === '1';

    function parseImporte(valor) {
        var texto = String(valor || '').replace(/\$/g, '').replace(/%/g, '').replace(/\s/g, '');
        if (!texto) return null;

        if (texto.indexOf(',') !== -1 && texto.indexOf('.') !== -1) {
            texto = texto.replace(/\./g, '').replace(',', '.');
        } else if (texto.indexOf(',') !== -1) {
            texto = texto.replace(',', '.');
        } else if (/^\d{1,3}(\.\d{3})+$/.test(texto)) {
            texto = texto.replace(/\./g, '');
        }

        var numero = parseFloat(texto);
        return isNaN(numero) ? null : numero;
    }

    function formatearImporte(numero) {
        if (numero === null || isNaN(numero)) return '—';

        return '$ ' + numero.toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatearPorcentaje(numero) {
        if (numero === null || isNaN(numero)) return '—';

        return numero.toLocaleString('es-AR', {
            minimumFractionDigits: 3,
            maximumFractionDigits: 3
        }) + ' %';
    }

    function radioSeleccionado(nombre) {
        var marcado = document.querySelector('input[name="' + nombre + '"]:checked');
        return marcado ? marcado.value : '';
    }

    function configurarModal(opciones) {
        var overlay = document.getElementById(opciones.overlay);
        if (!overlay) return;

        var btnAbrir    = opciones.abrir ? document.getElementById(opciones.abrir) : null;
        var btnCancelar = opciones.cancelar ? document.getElementById(opciones.cancelar) : null;
        var campoFoco   = opciones.foco ? document.getElementById(opciones.foco) : null;

        function abrir(evento) {
            if (evento) evento.preventDefault();
            if (typeof opciones.antesDeAbrir === 'function' && !opciones.antesDeAbrir()) {
                return;
            }

            overlay.removeAttribute('hidden');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            if (campoFoco) {
                campoFoco.focus();
            }
        }

        function cerrar() {
            overlay.setAttribute('hidden', '');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        if (btnAbrir) {
            btnAbrir.addEventListener('click', abrir);
        }

        if (btnCancelar) {
            btnCancelar.addEventListener('click', cerrar);
        }

        overlay.addEventListener('click', function (evento) {
            if (evento.target === overlay) {
                cerrar();
            }
        });

        document.addEventListener('keydown', function (evento) {
            if ((evento.key === 'Escape' || evento.keyCode === 27) && !overlay.hasAttribute('hidden')) {
                cerrar();
            }
        });

        return { abrir: abrir, cerrar: cerrar };
    }

    function actualizarAnticipo() {
        var campos = document.querySelector('.cert-anticipo-campos');
        var texto  = document.getElementById('anticipoTotalTexto');
        var hayAnticipo = radioSeleccionado('tiene_anticipo_financiero') === '1';

        if (campos) {
            if (hayAnticipo) {
                campos.removeAttribute('hidden');
            } else {
                campos.setAttribute('hidden', '');
            }
        }

        if (!texto) return;

        if (!hayAnticipo) {
            texto.textContent = '—';
            return;
        }

        var presupuesto = parseImporte((document.getElementById('presupuesto_oficial') || {}).value);
        var porcentaje  = parseImporte((document.getElementById('porcentaje_anticipo_financiero') || {}).value);

        if (presupuesto === null || porcentaje === null) {
            texto.textContent = '—';
            return;
        }

        texto.textContent = formatearImporte(presupuesto * porcentaje / 100);
    }

    function actualizarFondo() {
        var campos = document.querySelector('.cert-fondo-campos');
        var hayFondo = radioSeleccionado('tiene_fondo_reparo') === '1';

        if (!campos) return;

        if (hayFondo) {
            campos.removeAttribute('hidden');
        } else {
            campos.setAttribute('hidden', '');
        }
    }

    function textoSiNo(valor) {
        return valor === '1' ? 'Sí' : 'No';
    }

    function armarResumen() {
        var lista = document.getElementById('resumenConfigEconomica');
        if (!lista) return false;

        var presupuesto = parseImporte((document.getElementById('presupuesto_oficial') || {}).value);
        var contrato    = parseImporte((document.getElementById('monto_contrato') || {}).value);
        var anticipo    = radioSeleccionado('tiene_anticipo_financiero');
        var fondo       = radioSeleccionado('tiene_fondo_reparo');
        var filas       = [];

        filas.push(['Presupuesto oficial', formatearImporte(presupuesto)]);
        filas.push(['Monto de contrato original', formatearImporte(contrato)]);
        filas.push(['Anticipo', textoSiNo(anticipo)]);

        if (anticipo === '1') {
            var porcentajeAnticipo = parseImporte((document.getElementById('porcentaje_anticipo_financiero') || {}).value);
            filas.push(['Porcentaje de anticipo', formatearPorcentaje(porcentajeAnticipo)]);
            filas.push([
                'Monto total de anticipo',
                (presupuesto === null || porcentajeAnticipo === null)
                    ? '—'
                    : formatearImporte(presupuesto * porcentajeAnticipo / 100)
            ]);
        }

        filas.push(['Fondo de reparo', textoSiNo(fondo)]);

        if (fondo === '1') {
            var porcentajeFondo = parseImporte((document.getElementById('porcentaje_fondo_reparo') || {}).value);
            var poliza = radioSeleccionado('fondo_reparo_con_poliza');
            filas.push(['Porcentaje de fondo', formatearPorcentaje(porcentajeFondo)]);
            filas.push(['Póliza', poliza === '' ? '—' : textoSiNo(poliza)]);
        }

        lista.textContent = '';

        filas.forEach(function (fila) {
            var dt = document.createElement('dt');
            var dd = document.createElement('dd');
            dt.textContent = fila[0];
            dd.textContent = fila[1];
            lista.appendChild(dt);
            lista.appendChild(dd);
        });

        return true;
    }

    if (puedeEditar) {
        document.querySelectorAll('input[name="tiene_anticipo_financiero"]').forEach(function (radio) {
            radio.addEventListener('change', actualizarAnticipo);
        });

        var inputPresupuesto = document.getElementById('presupuesto_oficial');
        var inputPorcAnticipo = document.getElementById('porcentaje_anticipo_financiero');

        if (inputPresupuesto) {
            inputPresupuesto.addEventListener('input', actualizarAnticipo);
        }

        if (inputPorcAnticipo) {
            inputPorcAnticipo.addEventListener('input', actualizarAnticipo);
        }

        document.querySelectorAll('input[name="tiene_fondo_reparo"]').forEach(function (radio) {
            radio.addEventListener('change', actualizarFondo);
        });

        actualizarAnticipo();
        actualizarFondo();

        configurarModal({
            overlay: 'modalConfirmacionConfig',
            abrir: 'btnAbrirConfirmacionConfig',
            cancelar: 'btnCancelarConfirmacionConfig',
            antesDeAbrir: armarResumen
        });
    }

    configurarModal({
        overlay: 'modalCertificado',
        abrir: 'btnCargarCertificado',
        cancelar: 'btnCancelarCertificado',
        foco: 'mes'
    });
})();
