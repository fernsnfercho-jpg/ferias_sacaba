<?php
$tituloPagina = 'Ferias';
$moduloActivo = 'ferias';
$subtitulo = 'Administrar ferias';
require __DIR__ . '/../layout/header.php';
?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ferias registradas</h3>
                <a href="<?= url('ferias/nuevo') ?>" class="btn btn-primary btn-sm float-right">Nueva feria</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover">
                    <thead>
                    <tr><th>Nombre</th><th>Fecha</th><th>Lugar</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ferias)): ?>
                        <tr><td colspan="5" class="text-center">No hay ferias registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ferias as $f): ?>
                        <tr>
                            <td><?= e($f['nombre']) ?></td>
                            <td><?= e(fecha_corta($f['fecha'])) ?></td>
                            <td><?= e($f['lugar']) ?></td>
                            <td>
                                <span class="badge badge-<?= $f['estado'] === 'abierta' ? 'success' : ($f['estado'] === 'cerrada' ? 'secondary' : 'warning') ?>">
                                    <?= ucfirst(e($f['estado'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= url('ferias/editar', ['id' => $f['id']]) ?>" class="btn btn-outline-primary btn-sm">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php';