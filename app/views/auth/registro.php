<?php
$mensajes = tomar_flash();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta de expositor · Ferias Sacaba</title>
    <link rel="stylesheet" href="css/public.css">
</head>
<body class="publico-body">
<header class="publico-barra">
    <a class="publico-marca" href="<?= e(url('inicio')) ?>"><strong>FERIAS SACABA</strong><span>Registro de expositores</span></a>
    <nav class="publico-nav" aria-label="Navegación principal">
        <a href="<?= e(url('inicio')) ?>">Inicio</a>
        <a href="<?= e(url('login')) ?>">Ingresar</a>
    </nav>
</header>
<main class="publico-cuenta">
    <div class="publico-cuenta-cabecera">
        <div><p class="publico-kicker">Participa en las ferias</p><h1>Crea tu cuenta de expositor</h1></div>
        <a href="<?= e(url('inicio')) ?>">← Volver al inicio</a>
    </div>
    <section class="publico-panel" style="max-width:720px">
        <p class="publico-ayuda">Registre sus datos para elegir una feria y reservar una carpa.</p>
        <?php foreach ($mensajes as $m): ?>
            <div class="publico-alerta<?= ($m['tipo'] ?? '') === 'error' ? ' publico-alerta-error' : '' ?>" role="status"><?= e($m['texto'] ?? '') ?></div>
        <?php endforeach; ?>
        <?php if ($errores !== []): ?>
            <div class="alerta alerta-error" role="alert">Revise los datos indicados y vuelva a intentarlo.</div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('registro')) ?>">
            <?= csrf_campo() ?>
            <?php foreach ([
                ['nombre', 'Nombre completo', 'text', 'name'],
                ['emprendimiento', 'Nombre del emprendimiento', 'text', 'organization'],
                ['telefono', 'Teléfono / WhatsApp', 'tel', 'tel'],
                ['email', 'Correo electrónico', 'email', 'email'],
            ] as [$campo, $etiqueta, $tipo, $autocomplete]): ?>
                <div class="campo">
                    <label for="<?= e($campo) ?>"><?= e($etiqueta) ?></label>
                    <input id="<?= e($campo) ?>" name="<?= e($campo) ?>" type="<?= e($tipo) ?>" value="<?= e($valores[$campo]) ?>"
                        autocomplete="<?= e($autocomplete) ?>" required maxlength="<?= $campo === 'email' ? 150 : ($campo === 'nombre' ? 100 : ($campo === 'telefono' ? 15 : 120)) ?>">
                    <?php if (isset($errores[$campo])): ?><small class="publico-error"><?= e($errores[$campo]) ?></small><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="campo">
                <label for="password">Contraseña (mínimo 8 caracteres)</label>
                <input id="password" name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password">
                <?php if (isset($errores['password'])): ?><small class="publico-error"><?= e($errores['password']) ?></small><?php endif; ?>
            </div>
            <button type="submit" class="publico-boton">Crear cuenta y continuar</button>
        </form>
    </section>
</main>
</body>
</html>
