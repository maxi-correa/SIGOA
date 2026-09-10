<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo) ?> — SIGOA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/buttons.css') ?>">
    <style>
        .topbar { background: var(--color-primary); padding: .6rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .topbar .brand { font-weight: 700; font-size: 1.1rem; color: #FFFFFF; }
        .topbar .user-info { font-size: .85rem; color: rgba(255,255,255,.8); }
        .topbar .user-info strong { color: #FFFFFF; }
        .topbar a { color: rgba(255,255,255,.7); text-decoration: none; font-size: .85rem; margin-left: 1rem; }
        .topbar a:hover { color: #FFFFFF; }
        .content { max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; }
        .content h1 { font-size: 1.4rem; margin-bottom: 1rem; color: var(--color-primary); }
        .welcome-box { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; }
        .welcome-box p { line-height: 1.6; color: var(--color-text); }
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
