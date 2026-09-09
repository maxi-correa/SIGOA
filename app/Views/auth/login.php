<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo) ?> — SIGOA</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; background: #f5f6f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .login-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 2rem; width: 100%; max-width: 380px; }
        .login-card h1 { font-size: 1.4rem; margin-bottom: .25rem; color: #212529; }
        .login-card .subtitle { font-size: .85rem; color: #6c757d; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .3rem; color: #495057; }
        .form-group input { width: 100%; padding: .55rem .75rem; font-size: .95rem; border: 1px solid #ced4da; border-radius: 4px; }
        .form-group input:focus { outline: none; border-color: #dd4814; box-shadow: 0 0 0 2px rgba(221,72,20,.25); }
        .btn { display: block; width: 100%; padding: .6rem; font-size: .95rem; font-weight: 600; color: #fff; background: #dd4814; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #c03d11; }
        .error-msg { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; border-radius: 4px; padding: .55rem .75rem; font-size: .85rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>SIGOA</h1>
        <p class="subtitle">Sistema de Inspección de Obras de Arquitectura</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="error-msg"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('/login') ?>">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" id="usuario" name="usuario" value="<?= esc(old('usuario')) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Ingresar</button>
        </form>
    </div>
</body>
</html>
