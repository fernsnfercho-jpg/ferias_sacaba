<?php
$mensajes = tomar_flash();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Ferias Sacaba</title>
    <link rel="stylesheet" href="css/feria-app.css">
</head>
<body class="feria-body login-body">
<main class="login-layout">
    <section class="login-brand">
        <a class="feria-logo" href="<?= e(url('inicio')) ?>" aria-label="FeriaSacaba">
            <span class="logo-tent" aria-hidden="true"><i></i><b></b><em></em></span>
            <span>Feria<span>Sacaba</span></span>
        </a>
        <div class="login-copy">
            <p class="eyebrow">MUNICIPIO DE SACABA · COCHABAMBA</p>
            <h1>FERIAS<br><span>DE SACABA</span></h1>
            <p>Asigna tu carpa de forma automática, rápida y ordenada.</p>
        </div>
        <div class="login-landscape" aria-hidden="true">
            <span class="mountain mountain-one"></span><span class="mountain mountain-two"></span>
            <span class="plaza-light"></span><span class="tent tent-one"></span>
            <span class="tent tent-two"></span><span class="tent tent-three"></span>
        </div>
        <span class="login-edition">FERIAS PRODUCTIVAS · SACABA</span>
    </section>

    <section class="login-side">
        <a class="mobile-logo feria-logo" href="<?= e(url('inicio')) ?>" aria-label="FeriaSacaba">
            <span class="logo-tent" aria-hidden="true"><i></i><b></b><em></em></span><span>Feria<span>Sacaba</span></span>
        </a>
        <div class="login-card">
            <p class="eyebrow">ACCESO A LA PLATAFORMA</p>
            <h2>Qué bueno tenerte aquí.</h2>
            <p class="login-intro">Ingresa tus datos y elige tu espacio de trabajo.</p>
            <?php foreach ($mensajes as $m): ?>
                <div class="app-alert app-alert-<?= e($m['tipo'] ?? 'info') ?>" role="status"><?= e($m['texto'] ?? '') ?></div>
            <?php endforeach; ?>
            <?php if ($error !== null): ?>
                <div class="app-alert app-alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('login')) ?>" class="login-form">
                <?= csrf_campo() ?>
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="correo@ejemplo.com" required autofocus autocomplete="username">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                <button type="submit" class="boton-ingresar">Ingresar</button>
            </form>
            <p class="login-hint">El sistema detecta automáticamente si es administrador o expositor.</p>
            <div class="login-bottom">
                <a href="<?= e(url('registro')) ?>">¿Nuevo en la feria? Crear cuenta de expositor</a>
                <span>Acceso protegido · FeriaSacaba</span>
            </div>
        </div>
    </section>
</main>
</body>
</html>
