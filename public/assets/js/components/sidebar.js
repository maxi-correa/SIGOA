(function () {
    'use strict';

    var toggleBtn = document.getElementById('sidebarToggle');
    var closeBtn  = document.getElementById('sidebarClose');
    var backdrop  = document.getElementById('sidebarBackdrop');
    var sidebar   = document.getElementById('sidebar');
    var mobileQuery = window.matchMedia('(max-width: 768px)');

    if (!toggleBtn || !sidebar) {
        return;
    }

    function setOpen(open) {
        if (open) {
            document.body.classList.add('nav-open');
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.setAttribute('aria-label', 'Cerrar menú de navegación');
        } else {
            document.body.classList.remove('nav-open');
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute('aria-label', 'Abrir menú de navegación');
        }
    }

    function open() {
        setOpen(true);
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function close() {
        if (!document.body.classList.contains('nav-open')) {
            return;
        }
        setOpen(false);
        toggleBtn.focus();
    }

    toggleBtn.addEventListener('click', function () {
        if (document.body.classList.contains('nav-open')) {
            close();
        } else {
            open();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', close);
    }

    if (backdrop) {
        backdrop.addEventListener('click', close);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('nav-open')) {
            close();
        }
    });

    sidebar.addEventListener('click', function (event) {
        var link = event.target.closest('.sidebar-link');
        if (link && mobileQuery.matches) {
            close();
        }
    });

    mobileQuery.addEventListener('change', function () {
        if (!mobileQuery.matches && document.body.classList.contains('nav-open')) {
            setOpen(false);
        }
    });

    /* ------------------------------------------------------------------
       Cierre de sesión: se borra el perfil local de la sesión antes de
       salir, de modo que la documentación descargada en el dispositivo
       quede asociada a ninguna cuenta y no pueda consultarse desde el
       shell sin conexión.

       La navegación se espera al borrado porque una transacción IndexedDB
       se aborta si la página se descarga antes de que termine.
       ------------------------------------------------------------------ */
    var enlaceLogout = document.querySelector('.sidebar-link-logout');

    if (enlaceLogout) {
        enlaceLogout.addEventListener('click', function (evento) {
            var almacen = window.SIGOA ? window.SIGOA.almacenamiento : null;

            if (!almacen) {
                return;
            }

            evento.preventDefault();

            Promise.resolve()
                .then(function () {
                    return almacen.eliminar(almacen.ALMACENES.perfiles, 'activo');
                })
                .catch(function () {
                    /* Si no se puede borrar, no se bloquea la salida. */
                })
                .then(function () {
                    window.location.href = enlaceLogout.getAttribute('href');
                });
        });
    }
})();
