<?php
$mensajes = tomar_flash();
$datosGuardados = $areas !== [] ? (new ConfiguracionFeria())->paraFeria((int) $feria['id']) : null;
$solicitudesFeria = (int) Database::conectar()->query(
    'SELECT COUNT(*) FROM solicitudes WHERE feria_id = ' . (int) $feria['id'] . ' AND estado <> \'cancelada\''
)->fetchColumn();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurar feria · FeriaSacaba</title>
    <link rel="stylesheet" href="css/feria-app.css">
</head>
<body class="feria-body">
<main class="app-shell">
    <header class="app-topbar">
        <a class="feria-logo" href="<?= e(url('panel')) ?>"><span class="logo-tent" aria-hidden="true"><i></i><b></b><em></em></span><span>Feria<span>Sacaba</span></span></a>
        <div class="topbar-right"><span>Configuración · Administrador</span><a class="back-link" href="<?= e(url('panel')) ?>">← Panel</a></div>
    </header>
    <?php foreach ($mensajes as $m): ?>
        <div class="app-alert app-alert-<?= e($m['tipo'] ?? 'info') ?>" role="status"><?= e($m['texto'] ?? '') ?></div>
    <?php endforeach; ?>

    <header class="page-head">
        <div><p class="eyebrow">DISEÑO DEL ESPACIO</p><h1>Configurar Feria</h1><p>Planifica el terreno y revisa la capacidad antes de asignar las carpas.</p></div>
        <form method="get" action="index.php">
            <input type="hidden" name="ruta" value="configurar-feria">
            <select class="fair-select" name="feria_id" aria-label="Selecciona una feria" onchange="this.form.submit()">
                <?php foreach ($ferias as $opcion): ?>
                    <option value="<?= (int) $opcion['id'] ?>"<?= (int) $feria['id'] === (int) $opcion['id'] ? ' selected' : '' ?>><?= e($opcion['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </header>

    <?php if ($errores !== []): ?>
        <div class="app-alert app-alert-error" role="alert">
            <?php foreach ($errores as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="premium-panel">
        <div class="panel-heading">
            <div><h2><?= e($feria['nombre']) ?></h2><p><?= e(fecha_corta($feria['fecha'])) ?> · <?= e(ucfirst($feria['estado'])) ?></p></div>
            <span class="map-stat total" style="padding:9px 13px"><span>Inscripciones</span><strong style="font-size:1.35rem"><?= $solicitudesFeria ?></strong></span>
        </div>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('configurar-feria', ['feria_id' => $feria['id']])) ?>" id="config-form">
            <?= csrf_campo() ?>
            <div class="config-grid">
                <?php foreach ([
                    ['largo_total_m', 'Largo del terreno', 'm', '1', '250', '0.5'],
                    ['ancho_total_m', 'Ancho del terreno', 'm', '1', '250', '0.5'],
                    ['ancho_pasillo_m', 'Ancho de pasillos', 'm', '0', '50', '0.1'],
                    ['entrada_m2', 'Entrada reservada', 'm²', '0', '10000', '1'],
                    ['escenario_m2', 'Escenario reservado', 'm²', '0', '10000', '1'],
                ] as [$campo, $etiqueta, $unidad, $minimo, $maximo, $paso]): ?>
                    <div class="config-field">
                        <label for="<?= e($campo) ?>"><?= e($etiqueta) ?> <span class="tenue">(<?= e($unidad) ?>)</span></label>
                        <input type="number" id="<?= e($campo) ?>" name="<?= e($campo) ?>"
                               min="<?= e($minimo) ?>" max="<?= e($maximo) ?>" step="<?= e($paso) ?>"
                               value="<?= e($valores[$campo] ?? '') ?>" required>
                        <?php if (isset($errores[$campo])): ?><small class="field-error"><?= e($errores[$campo]) ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="config-field">
                    <label>Carpa oficial</label>
                    <strong>3 m de frente × 4 m de fondo · 12 m²</strong>
                </div>
            </div>

            <section class="fair-photo-settings" aria-labelledby="fair-photo-title">
                <div>
                    <p class="eyebrow">REFERENCIA VISUAL DEL LUGAR</p>
                    <h3 id="fair-photo-title">Foto real de la plaza o espacio</h3>
                    <p>Publica una foto propia del lugar de esta feria. Una toma desde arriba ayuda a que el expositor reconozca las zonas; el plano superpuesto respeta las medidas que configures.</p>
                    <label class="photo-upload-label" for="imagen_plano">Subir o reemplazar foto (JPG, PNG o WebP · máximo 8 MB)</label>
                    <input type="file" id="imagen_plano" name="imagen_plano" accept="image/jpeg,image/png,image/webp">
                    <?php if ($imagenPlanoActual !== null): ?>
                        <label class="remove-photo-option"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la foto publicada</label>
                    <?php endif; ?>
                    <?php if (isset($errores['imagen_plano'])): ?><small class="field-error"><?= e($errores['imagen_plano']) ?></small><?php endif; ?>
                </div>
                <div class="admin-photo-preview">
                    <?php if ($imagenPlanoActual !== null): ?>
                        <img src="<?= e($imagenPlanoActual) ?>" alt="Foto actualmente publicada para <?= e($feria['nombre']) ?>">
                    <?php else: ?>
                        <div class="admin-photo-empty"><span aria-hidden="true">📷</span><strong>Aún no hay una foto publicada</strong></div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="calc-grid" aria-live="polite">
                <div class="calc-card"><span>Espacio útil disponible</span><strong id="calc-useful">0 m²</strong></div>
                <div class="calc-card mint"><span>Puestos que caben</span><strong id="calc-capacity">0</strong></div>
                <div class="calc-card"><span>Demanda registrada</span><strong><?= $solicitudesFeria ?> expositores</strong></div>
            </div>
            <div id="calc-alert" class="capacity-alert" hidden></div>
            <?php if ($datosGuardados !== null): ?>
                <p class="publico-ayuda">Última configuración guardada <?= e(fecha_hora($datosGuardados['actualizado_en'] ?? null)) ?>. Las medidas se aplican a las áreas de esta feria; el sistema no reduce espacios que ya tengan puestos asignados.</p>
            <?php endif; ?>
            <button type="submit" class="app-submit">Guardar configuración del espacio</button>
        </form>
    </section>

    <section class="premium-panel" style="margin-top:17px">
        <div class="panel-heading"><div><h2>Distribución por zonas</h2><p><?= count($areas) ?> área(s) vinculada(s) a esta feria.</p></div></div>
        <?php if ($areas === []): ?>
            <p class="publico-ayuda">Antes de guardar el plano, crea las áreas y asígnales sus categorías desde la administración de áreas.</p>
            <a class="app-submit" style="display:inline-flex;text-decoration:none" href="<?= e(url('areas', ['feria_id' => $feria['id']])) ?>">Abrir áreas</a>
        <?php else: ?>
            <div class="table-wrap">
                <table class="publico-tabla">
                    <thead><tr><th>Área</th><th>Categoría</th><th>Medidas asignadas</th><th>Puestos</th></tr></thead>
                    <tbody><?php foreach ($areas as $area): ?>
                        <tr><td><?= e($area['nombre']) ?></td><td><?= e($area['categoria']) ?></td>
                            <td><?= e(medida($area['ancho_m'])) ?> × <?= e(medida($area['largo_m'])) ?> m</td>
                            <td><?= (int) $area['puestos'] ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('config-form');
    const fields = ['largo_total_m', 'ancho_total_m', 'ancho_pasillo_m', 'entrada_m2', 'escenario_m2']
        .reduce((all, id) => (all[id] = document.getElementById(id), all), {});
    function update() {
        const values = Object.fromEntries(Object.entries(fields).map(([key, input]) => [key, Number(input.value) || 0]));
        const tentArea = 12;
        const total = values.ancho_total_m * values.largo_total_m;
        const usable = Math.max(0, total - values.ancho_total_m * values.ancho_pasillo_m - values.entrada_m2 - values.escenario_m2);
        const capacity = tentArea > 0 ? Math.floor(usable / tentArea) : 0;
        document.getElementById('calc-useful').textContent = `${usable.toLocaleString('es-BO', {maximumFractionDigits: 1})} m²`;
        document.getElementById('calc-capacity').textContent = capacity.toLocaleString('es-BO');
        const demand = <?= $solicitudesFeria ?>;
        const alert = document.getElementById('calc-alert');
        alert.hidden = false;
        alert.classList.toggle('capacity-good', capacity >= demand);
        alert.textContent = capacity < demand
            ? `Faltan ${demand - capacity} puestos para cubrir ${demand} solicitudes registradas.`
            : `Capacidad suficiente · sobran ${(usable - demand * tentArea).toLocaleString('es-BO', {maximumFractionDigits: 1})} m² de espacio útil.`;
    }
    form.addEventListener('input', update);
    update();

    const imageInput = document.getElementById('imagen_plano');
    const preview = document.querySelector('.admin-photo-preview');
    const removeImage = document.querySelector('input[name="quitar_imagen"]');
    let previewUrl = null;
    imageInput.addEventListener('change', () => {
        const file = imageInput.files[0];
        if (!file) return;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        let image = preview.querySelector('img');
        if (!image) {
            preview.replaceChildren();
            image = document.createElement('img');
            image.alt = 'Vista previa de la nueva foto de la feria';
            preview.appendChild(image);
        }
        image.src = previewUrl;
        if (removeImage) removeImage.checked = false;
    });
})();
</script>
<script src="js/admin.js"></script>
</body>
</html>
