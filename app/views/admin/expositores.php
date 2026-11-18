<?php
$tituloPagina = 'Expositores';
$moduloActivo = 'admin/expositores';
$subtitulo = 'Listado de expositores registrados';
require __DIR__ . '/../layout/header.php';
?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Buscar expositor</h3>
            </div>
            <div class="card-body">
                <form method="get" action="<?= url('admin/expositores') ?>" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Buscar por nombre, emprendimiento o correo...">
                        <div class="input-group-append">
                            <button class="btn btn-primary">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover">
                    <thead>
                    <tr><th>Emprendimiento</th><th>Titular</th><th>Correo</th><th>Teléfono</th><th>Institución</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($expositores)): ?>
                        <tr><td colspan="5" class="text-center">No se encontraron expositores.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($expositores as $e): ?>
                        <tr>
                            <td><?= e($e['emprendimiento'] ?? '') ?></td>
                            <td><?= e($e['titular'] ?? '') ?></td>
                            <td><?= e($e['email'] ?? '') ?></td>
                            <td><?= e($e['telefono'] ?? '') ?></td>
                            <td><?= e($e['institucion'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php';