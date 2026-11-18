<?php
$tituloPagina = 'Inicio';
$moduloActivo = 'panel';
$subtitulo = 'Resumen del sistema';
require __DIR__ . '/../layout/header.php';
?>
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= (int) $c['carpas_totales'] ?></h3>
                <p>Carpas totales</p>
            </div>
            <div class="icon">
                <i class="fas fa-cube"></i>
            </div>
            <a href="<?= url('admin/mapa') ?>" class="small-box-footer">Ver mapa <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= (int) $c['carpas_disponibles'] ?></h3>
                <p>Carpas disponibles</p>
            </div>
            <div class="icon">
                <i class="fas fa-door-open"></i>
            </div>
            <a href="<?= url('admin/reservas') ?>" class="small-box-footer">Gestionar reservas <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= (int) $c['reservas_pendientes'] ?></h3>
                <p>Reservas pendientes</p>
            </div>
            <div class="icon">
                <i class="fas fa-clock"></i>
            </div>
            <a href="<?= e(url('admin/reservas', ['estado' => 'pendiente'])) ?>" class="small-box-footer">Revisar pendientes <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= (int) $c['expositores'] ?></h3>
                <p>Expositores registrados</p>
            </div>
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <a href="<?= url('admin/expositores') ?>" class="small-box-footer">Ver expositores <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Últimas reservas</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>N.º</th><th>Expositor</th><th>Emprendimiento</th><th>Feria</th><th>Estado</th><th>Código</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ultimas_reservas)): ?>
                        <tr><td colspan="6" class="text-center">No hay reservas registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ultimas_reservas as $r): ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= e($r['nombre'] ?? $r['titular'] ?? '') ?></td>
                            <td><?= e($r['emprendimiento'] ?? '') ?></td>
                            <td><?= e($r['feria'] ?? '') ?></td>
                            <td>
                                <span class="badge badge-<?= $r['estado'] === 'confirmada' ? 'confirmada' : ($r['estado'] === 'pendiente' ? 'pendiente' : ($r['estado'] === 'rechazada' ? 'rechazada' : 'cancelada')) ?>">
                                    <?= ucfirst(e($r['estado'])) ?>
                                </span>
                            </td>
                            <td><?= e($r['codigo'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php';