<?php
$rol = $usuario['rol'] ?? '';
$enlaceIngreso = $rol === 'administrador' ? url('panel') : ($rol === 'usuario' ? url('mi-espacio') : url('login'));
$fotoPortada = '';
foreach ($ferias as $feriaFoto) {
    if (!empty($feriaFoto['foto'])) {
        $fotoPortada = (string) $feriaFoto['foto'];
        break;
    }
}
if ($fotoPortada === '' && is_file(RAIZ_PROYECTO . '/public/uploads/ferias/cfc43a35e85a58287f97bf5e8e87330d.webp')) {
    $fotoPortada = 'uploads/ferias/cfc43a35e85a58287f97bf5e8e87330d.webp';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ferias Sacaba · Municipio de Sacaba</title>
    <link rel="stylesheet" href="css/public.css">
</head>
<body class="publico-body">
<header class="publico-barra">
    <a class="publico-marca" href="<?= e(url('inicio')) ?>">
        <strong>FERIAS SACABA</strong>
        <span>Municipio de Sacaba</span>
    </a>
    <nav class="publico-nav" aria-label="Navegación principal">
        <a href="#ferias">Ferias disponibles</a>
        <a href="#como-funciona">Cómo funciona</a>
        <a class="publico-nav-cta" href="<?= e($enlaceIngreso) ?>">Ingresar</a>
        <?php if ($rol === ''): ?><a class="publico-nav-cta" href="<?= e(url('registro')) ?>">Crear cuenta</a><?php endif; ?>
    </nav>
</header>

<main class="publico-contenido">
    <section class="publico-hero"<?= $fotoPortada !== '' ? ' style="--foto-feria:url(\'' . e($fotoPortada) . '\')"' : '' ?>>
        <?php if ($fotoPortada !== ''): ?><img class="publico-hero-foto" src="<?= e($fotoPortada) ?>" alt="Feria del Municipio de Sacaba"><?php endif; ?>
        <div class="publico-hero-contenido">
            <p class="publico-kicker">Municipio de Sacaba</p>
            <h1>Reserva tu carpa para la próxima feria de Sacaba</h1>
            <p>Consulta las ferias disponibles, elige una carpa libre y recibe la respuesta de la administración en tu cuenta.</p>
            <div class="publico-acciones">
                <a class="publico-boton" href="<?= e($enlaceIngreso) ?>">Reservar mi carpa</a>
                <a class="publico-boton publico-boton-claro" href="#ferias">Ver ferias</a>
            </div>
        </div>
    </section>

    <section class="publico-aviso-carpa" aria-label="Medida oficial de carpa">
        <strong>Carpa oficial 3 × 4 m (12 m²)</strong>
        <span>Medida de armado de la Alcaldía de Sacaba · pasillo de 1.50 m</span>
    </section>

    <section class="publico-seccion" id="ferias">
        <div class="publico-seccion-cabecera">
            <div><h2>Ferias disponibles</h2><p>Elige una feria con inscripciones abiertas.</p></div>
        </div>
        <?php if ($ferias === []): ?>
            <div class="publico-vacio"><strong>No hay ferias abiertas por el momento.</strong><div>Vuelve pronto para consultar las próximas fechas.</div></div>
        <?php else: ?>
            <div class="publico-ferias">
                <?php foreach ($ferias as $feria): ?>
                    <article class="publico-feria">
                        <?php if (!empty($feria['foto'])): ?><img class="publico-feria-foto" src="<?= e($feria['foto']) ?>" alt="Lugar de <?= e($feria['nombre']) ?>"><?php endif; ?>
                        <span class="publico-feria-estado">Inscripciones abiertas</span>
                        <h3><?= e($feria['nombre']) ?></h3>
                        <p><strong>Fecha:</strong> <?= e(fecha_corta($feria['fecha'])) ?></p>
                        <p><strong>Lugar:</strong> <?= e($feria['lugar']) ?></p>
                        <div class="publico-feria-acciones">
                            <a class="publico-boton" href="<?= e($rol === 'usuario' ? url('mi-espacio') : ($rol === 'administrador' ? url('panel') : url('login'))) ?>">Reservar mi carpa</a>
                            <a class="publico-enlace-mapa" href="<?= e(enlace_mapa((string) $feria['lugar'])) ?>" target="_blank" rel="noopener noreferrer">Ver ubicación</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="publico-seccion" id="como-funciona">
        <div class="publico-seccion-cabecera"><div><h2>Cómo funciona</h2><p>Reservar toma solo unos pasos.</p></div></div>
        <div class="publico-pasos">
            <article class="publico-paso"><strong>1. Crea tu cuenta</strong><p>Registra tu nombre, emprendimiento y datos de contacto.</p></article>
            <article class="publico-paso"><strong>2. Elige tu carpa</strong><p>Selecciona una feria, el rubro y una ubicación libre en el plano.</p></article>
            <article class="publico-paso"><strong>3. Recibe la respuesta</strong><p>Consulta tus reservas y espera la aprobación de la administración.</p></article>
        </div>
    </section>
</main>
<footer class="publico-pie">
    Ferias Sacaba · Municipio de Sacaba · Plataforma de reservas de carpas
    <?php if ($contacto !== ''): ?><br>Contacto: <a href="mailto:<?= e($contacto) ?>"><?= e($contacto) ?></a><?php endif; ?>
</footer>
</body>
</html>
