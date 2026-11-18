<?php
// Página de error simple (no depende de la base de datos). Variables: $titulo, $mensaje, $detalle
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · Ferias Sacaba</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="pagina-acceso">
<main class="acceso acceso-ancho">
    <div class="acceso-marca">
        <strong>FERIAS SACABA</strong>
        <span>Panel del administrador</span>
    </div>
    <div class="tarjeta">
        <h1><?= e($titulo) ?></h1>
        <div class="alerta alerta-error"><?= e($mensaje) ?></div>
        <?php if (!empty($detalle)): ?>
            <details>
                <summary>Detalle técnico</summary>
                <pre class="detalle"><?= e($detalle) ?></pre>
            </details>
        <?php endif; ?>
        <p><a class="boton" href="index.php">Volver al inicio</a></p>
    </div>
</main>
</body>
</html>
