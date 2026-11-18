<?php
// Creación del primer administrador. Variables: $errores, $valores
$mensajes = tomar_flash();
$campo = static function (string $nombre, string $etiqueta, string $tipo, string $valor, string $autocompletar, array $errores): string {
    $html = '<div class="campo' . (isset($errores[$nombre]) ? ' con-error' : '') . '">';
    $html .= '<label for="' . e($nombre) . '">' . e($etiqueta) . '</label>';
    $html .= '<input type="' . e($tipo) . '" id="' . e($nombre) . '" name="' . e($nombre) . '" value="' . e($valor) . '" required autocomplete="' . e($autocompletar) . '"'
        . ($tipo === 'password' ? ' minlength="8" maxlength="72"' : '') . '>';
    if (isset($errores[$nombre])) {
        $html .= '<small class="error-campo">' . e($errores[$nombre]) . '</small>';
    }
    return $html . '</div>';
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear administrador · Ferias Sacaba</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="pagina-acceso">
<main class="acceso">
    <div class="acceso-marca">
        <strong>FERIAS SACABA</strong>
        <span>Configuración inicial</span>
    </div>
    <div class="tarjeta">
        <h1>Crear la cuenta del administrador</h1>
        <p class="descripcion">
            La base de datos no trae contraseñas predeterminadas. Esta pantalla solo aparece
            mientras no exista ningún administrador activo.
        </p>
        <?php foreach ($mensajes as $m): ?>
            <div class="alerta alerta-<?= e($m['tipo'] ?? 'info') ?>"><?= e($m['texto'] ?? '') ?></div>
        <?php endforeach; ?>
        <?php if ($errores !== []): ?>
            <div class="alerta alerta-error" role="alert">Revise los datos marcados en rojo.</div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('instalar')) ?>" class="formulario">
            <?= csrf_campo() ?>
            <?= $campo('nombre', 'Nombre completo', 'text', $valores['nombre'], 'name', $errores) ?>
            <?= $campo('email', 'Correo electrónico', 'email', $valores['email'], 'username', $errores) ?>
            <?= $campo('password', 'Contraseña (mínimo 8 caracteres)', 'password', '', 'new-password', $errores) ?>
            <?= $campo('password2', 'Repita la contraseña', 'password', '', 'new-password', $errores) ?>
            <button type="submit" class="boton boton-primario boton-ancho">Crear administrador e ingresar</button>
        </form>
    </div>
</main>
</body>
</html>
