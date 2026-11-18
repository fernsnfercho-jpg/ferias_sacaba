<?php
// Plano a escala de un área. X = ancho (izquierda → derecha), Y = largo (frente → fondo).
$tituloPagina = 'Plano de ' . $area['nombre'];
$moduloActivo = 'areas';
require __DIR__ . '/../layout/header.php';

$pct = static fn (float $valor, float $total): string => sprintf('%.4F', $total > 0 ? $valor / $total * 100 : 0);
$largoUtil = $largo - $pasillo;
// Que el dibujo no pase de ~520 px de alto, manteniendo la proporción real.
$anchoMaximo = $largo > 0 ? (int) round(520 * $ancho / $largo) : 520;
$estiloPlano = sprintf(
    'aspect-ratio: %.2F / %.2F; max-width: %dpx; background-size: calc(100%% / %.2F) calc(100%% / %.2F);',
    $ancho, $largo, max($anchoMaximo, 240), $ancho, $largo
);
?>
<div class="encabezado">
    <div>
        <p class="migas"><a href="<?= e(url('areas', ['feria_id' => $area['feria_id']])) ?>">Áreas</a> / Plano</p>
        <h1>Plano · <?= e($area['nombre']) ?></h1>
        <p class="descripcion">
            <?= e($area['feria']) ?> · Categoría <?= e($area['categoria']) ?> ·
            <?= e(medida($ancho)) ?> × <?= e(medida($largo)) ?> m con pasillo de <?= e(medida($pasillo)) ?> m
        </p>
    </div>
    <div class="botones">
        <a class="boton" href="<?= e(url('areas/editar', ['id' => $area['id']])) ?>">Editar área</a>
    </div>
</div>

<div class="tarjetas">
    <div class="tarjeta dato"><span>Superficie total</span><strong class="numero"><?= e(medida($ancho * $largo)) ?> m²</strong>
        <small><?= e(medida($ancho)) ?> m de ancho × <?= e(medida($largo)) ?> m de largo</small></div>
    <div class="tarjeta dato"><span>Espacio útil</span><strong class="numero"><?= e(medida($util)) ?> m²</strong>
        <small>Sin el pasillo: <?= e(medida($ancho)) ?> × <?= e(medida($largoUtil)) ?> m</small></div>
    <div class="tarjeta dato"><span>Puestos asignados</span><strong class="numero"><?= count($puestos) ?></strong>
        <small><?= e(medida($ocupado)) ?> m² ocupados</small></div>
    <div class="tarjeta dato"><span>Ocupación</span><strong class="numero"><?= (int) round($porcentaje) ?> %</strong>
        <small>del espacio útil</small></div>
</div>

<section class="tarjeta">
    <div class="plano-marco">
        <div class="plano" style="<?= e($estiloPlano) ?>" role="img"
             aria-label="Plano del área <?= e($area['nombre']) ?> con <?= count($puestos) ?> puestos">
            <?php foreach ($puestos as $p):
                $estilo = sprintf(
                    'left:%s%%;top:%s%%;width:%s%%;height:%s%%;',
                    $pct((float) $p['x_m'], $ancho), $pct((float) $p['y_m'], $largo),
                    $pct((float) $p['ancho_m'], $ancho), $pct((float) $p['largo_m'], $largo)
                );
                $info = sprintf(
                    '%s · %s · X %s m, Y %s m · %s × %s m%s',
                    $p['codigo'], $p['emprendimiento'], medida($p['x_m']), medida($p['y_m']),
                    medida($p['ancho_m']), medida($p['largo_m']), (int) $p['fijada'] === 1 ? ' · fijada' : ''
                );
                ?>
                <div class="puesto<?= (int) $p['fijada'] === 1 ? ' puesto-fijo' : '' ?>" style="<?= e($estilo) ?>"
                   title="<?= e($info) ?>">
                    <strong><?= e($p['codigo']) ?></strong>
                    <small><?= e($p['emprendimiento']) ?></small>
                </div>
            <?php endforeach; ?>
            <div class="pasillo" style="top:<?= $pct($largoUtil, $largo) ?>%;height:<?= $pct($pasillo, $largo) ?>%;">
                Pasillo · <?= e(medida($ancho)) ?> × <?= e(medida($pasillo)) ?> m
            </div>
        </div>
    </div>
    <p class="leyenda">
        Cada cuadro de la cuadrícula = 1 m. X se mide desde el borde izquierdo y Y desde el frente.
        Borde grueso = posición fijada. Las carpas se reservan desde el portal del expositor.
    </p>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
