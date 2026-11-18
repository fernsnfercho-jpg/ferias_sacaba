<?php
$rolMapa = $administrador ? 'Administrador' : 'Expositor';
$porArea = [];
foreach ($puestos as $puesto) {
    $porArea[(int) $puesto['area_id']][] = $puesto;
}
$totalOcupados = count($puestos);
$totalLibres = max(0, $capacidad - $totalOcupados);
$numero = 0;
$crearLibre = static function (string $areaNombre, int $n) use (&$numero): array {
    $numero++;
    return [
        'id' => 'libre-' . $numero, 'codigo' => sprintf('L%02d', $numero),
        'emprendimiento' => 'Espacio disponible', 'institucion' => '',
        'categoria' => '', 'area' => $areaNombre, 'propio' => false,
        'libre' => true, 'numero' => $n,
    ];
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mapa de carpas · FeriaSacaba</title>
    <link rel="stylesheet" href="css/feria-app.css">
</head>
<body class="feria-body">
<main class="app-shell">
    <header class="app-topbar">
        <a class="feria-logo" href="<?= e($administrador ? url('panel') : url('mi-espacio')) ?>">
            <span class="logo-tent" aria-hidden="true"><i></i><b></b><em></em></span><span>Feria<span>Sacaba</span></span>
        </a>
        <div class="topbar-right"><span>Mapa de carpas · <?= e($rolMapa) ?></span><a class="back-link" href="<?= e($administrador ? url('panel') : url('mi-espacio')) ?>">← Volver</a></div>
    </header>
    <header class="page-head">
        <div><p class="eyebrow">DISTRIBUCIÓN DE PUESTOS</p><h1>Mapa de la feria</h1><p><?= e($feria['nombre']) ?> · <?= e($feria['lugar']) ?> · <?= e(fecha_corta($feria['fecha'])) ?></p></div>
        <?php if (count($ferias) > 1): ?>
            <form method="get" action="index.php">
                <input type="hidden" name="ruta" value="mapa-feria">
                <select class="fair-select" name="feria_id" aria-label="Selecciona una feria" onchange="this.form.submit()">
                    <?php foreach ($ferias as $opcion): ?>
                        <option value="<?= (int) $opcion['id'] ?>"<?= (int) $feria['id'] === (int) $opcion['id'] ? ' selected' : '' ?>><?= e($opcion['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </header>

    <div class="map-stats">
        <div class="map-stat total"><span>Total de puestos estimado</span><strong><?= $capacidad ?></strong></div>
        <div class="map-stat occupied"><span>Ocupados</span><strong><?= $totalOcupados ?></strong></div>
        <div class="map-stat free"><span>Disponibles</span><strong><?= $totalLibres ?></strong></div>
    </div>

    <section class="premium-panel">
        <div class="panel-heading">
            <div><h2>Plano por áreas</h2><p>Ordena los puestos sin cambiar las asignaciones guardadas.</p></div>
        </div>
        <div class="sort-toolbar" role="group" aria-label="Ordenar puestos">
            <button type="button" class="sort-chip" data-order="area" aria-pressed="true">Por Área</button>
            <button type="button" class="sort-chip" data-order="institution" aria-pressed="false">Por Institución</button>
            <button type="button" class="sort-chip" data-order="name" aria-pressed="false">Por Nombre</button>
            <button type="button" class="sort-chip" data-order="category" aria-pressed="false">Por Categoría</button>
        </div>

        <?php if ($areas === []): ?>
            <div class="map-empty">La feria aún no tiene áreas configuradas.</div>
        <?php else: ?>
            <?php foreach ($areas as $area):
                $stands = $porArea[(int) $area['id']] ?? [];
                $capacidadArea = (int) floor((float) $area['ancho_m'] / $carpaAncho)
                    * (int) floor(((float) $area['largo_m'] - (float) $area['pasillo_m']) / $carpaLargo);
                ?>
                <section class="map-area">
                    <header class="map-area-head">
                        <h3><?= e($area['nombre']) ?> <span class="stand-category"><?= e($area['categoria']) ?></span></h3>
                        <span><?= count($stands) ?> ocupado(s) · <?= max(0, $capacidadArea - count($stands)) ?> libre(s) · <?= e(medida($area['ancho_m'])) ?> × <?= e(medida($area['largo_m'])) ?> m</span>
                    </header>
                    <div class="map-stands">
                        <?php foreach ($stands as $puesto):
                            $categoria = normalizar((string) $puesto['categoria']);
                            $categoria = preg_replace('/[^a-z0-9-]/', '', $categoria);
                            ?>
                            <article class="map-stand<?= !empty($puesto['propio']) ? ' mine' : '' ?>"
                                data-area="<?= e($area['nombre']) ?>"
                                data-name="<?= e($puesto['emprendimiento']) ?>"
                                data-institution="<?= e($puesto['institucion'] ?? '') ?>"
                                data-category="<?= e($categoria) ?>">
                                <span class="stand-category"><?= e($puesto['categoria']) ?></span>
                                <strong><?= e($puesto['codigo']) ?></strong>
                                <small><?= e($puesto['emprendimiento']) ?></small>
                                <?php if (!empty($puesto['institucion'])): ?><small><?= e($puesto['institucion']) ?></small><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php for ($i = count($stands); $i < $capacidadArea; $i++):
                            $libre = $crearLibre((string) $area['nombre'], $i + 1);
                            ?>
                            <article class="map-stand free" data-area="<?= e($area['nombre']) ?>" data-name="" data-institution="" data-category="">
                                <span class="stand-category">Disponible</span>
                                <strong><?= e($libre['codigo']) ?></strong>
                                <small>Carpa libre</small>
                            </article>
                        <?php endfor; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="map-legend">
            <span class="legend-item"><i class="legend-dot"></i> Ocupado</span>
            <span class="legend-item"><i class="legend-dot open"></i> Disponible</span>
            <?php if (!$administrador): ?><span class="legend-item"><i class="legend-dot mine"></i> Tu puesto</span><?php endif; ?>
        </div>
    </section>
</main>
<script src="js/feria-app.js"></script>
</body>
</html>
