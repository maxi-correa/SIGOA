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
})();
