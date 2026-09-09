<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo) ?> — SIGOA</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; background: #f5f6f8; color: #212529; }
        .topbar { background: #fff; border-bottom: 1px solid #dee2e6; padding: .6rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .topbar .brand { font-weight: 700; font-size: 1.1rem; color: #dd4814; }
        .topbar .user-info { font-size: .85rem; color: #495057; }
        .topbar .user-info strong { color: #212529; }
        .topbar a { color: #6c757d; text-decoration: none; font-size: .85rem; margin-left: 1rem; }
        .topbar a:hover { color: #dd4814; }
        .content { max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; }
        .content h1 { font-size: 1.4rem; margin-bottom: 1rem; }
        .welcome-box { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.5rem; }
        .welcome-box p { line-height: 1.6; color: #495057; }
    </style>
</head>
<body>
    <div class="topbar">
        <span class="brand">SIGOA</span>
        <div>
            <span class="user-info">
                Sesión: <strong><?= esc($user_name) ?></strong> (<?= esc($username) ?>)
            </span>
            <a href="<?= site_url('/logout') ?>">Cerrar sesión</a>
        </div>
    </div>

    <div class="content">
        <h1><?= esc($titulo) ?></h1>
        <div class="welcome-box">
            <p>
                Bienvenido/a, <strong><?= esc($user_name) ?></strong>.<br>
                Ha iniciado sesión correctamente en SIGOA.
            </p>
            <p style="margin-top:1rem;">
                Esta es una página de verificación para comprobar que el mecanismo de autenticación
                funciona correctamente. El acceso a esta página requiere sesión activa.
            </p>
        </div>
    </div>
</body>
</html>
