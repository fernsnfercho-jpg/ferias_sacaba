<?php
$tituloPagina = 'Mapa de carpas';
$moduloActivo = 'admin/mapa';
require __DIR__ . '/../layout/header.php';
?>
<div class="card">
    <div class="card-header"><h3 class="card-title">Seleccionar feria</h3></div>
    <div class="card-body">
        <form method="get" action="index.php" class="form-inline">
            <input type="hidden" name="ruta" value="admin/mapa">
            <label class="mr-2" for="feria_id">Feria</label>
            <select id="feria_id" name="feria_id" class="form-control mr-2" onchange="this.form.submit()">
                <?php foreach ($ferias as $f): ?>
                    <option value="<?= (int) $f['id'] ?>"<?= $feria !== null && (int) $feria['id'] === (int) $f['id'] ? ' selected' : '' ?>><?= e($f['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php if ($feria !== null && $plano !== null): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= e($feria['nombre']) ?> · <?= e(fecha_corta($feria['fecha'])) ?> · <?= e($feria['lugar']) ?></h3>
            <div class="card-tools text-sm">
                <span class="badge badge-success">Libre <?= (int) $plano['libres'] ?></span>
                <span class="badge badge-warning">Pendiente <?= (int) $plano['pendientes'] ?></span>
                <span class="badge badge-primary">Confirmada <?= (int) $plano['confirmadas'] ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($plano['zonas'] === []): ?>
                <p class="text-muted mb-0">No hay áreas configuradas para esta feria.</p>
            <?php endif; ?>
            <?php foreach ($plano['zonas'] as $zona): ?>
                <section class="mb-4">
                    <h4><?= e($zona['area']['nombre']) ?> <small class="text-muted">(<?= e($zona['area']['categoria']) ?>)</small></h4>
                    <p class="text-muted small"><?= (int) $zona['libres'] ?> disponibles · <?= (int) $zona['pendientes'] ?> pendientes · <?= (int) $zona['confirmadas'] ?> confirmadas · Carpa 3 × 4 m · Pasillo <?= e(medida($zona['area']['pasillo_m'])) ?> m</p>
                    <?php if ($zona['celdas'] === []): ?>
                        <div class="alert alert-light">Las medidas del área no permiten ubicar una carpa oficial.</div>
                    <?php else: ?>
                        <div class="mapa-carpa-grid" style="--columnas:<?= max(1, (int) $zona['columnas']) ?>">
                            <?php foreach ($zona['celdas'] as $celda): ?>
                                <?php
                                $puesto = $celda['puesto'] ?? [];
                                $estado = $celda['estado'] === 'libre' ? 'libre' : (string) ($puesto['estado_reserva'] ?? 'bloqueada');
                                $ocupante = trim((string) ($puesto['emprendimiento'] ?? '') . ' · ' . (string) ($puesto['titular'] ?? ''), ' ·');
                                ?>
                                <div class="mapa-carpa mapa-carpa-<?= e($estado) ?>" title="<?= e($ocupante) ?>">
                                    <strong><?= e($celda['codigo']) ?></strong>
                                    <span><?= e($estado === 'libre' ? 'Libre' : nombre_estado($estado)) ?></span>
                                    <?php if ($ocupante !== ''): ?><small><?= e($ocupante) ?></small><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
