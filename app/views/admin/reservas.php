<?php
$tituloPagina = 'Reservas';
$moduloActivo = 'admin/reservas';
$subtitulo = 'Listado de solicitudes de reserva';
require __DIR__ . '/../layout/header.php';
?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filtros</h3>
            </div>
            <div class="card-body">
                <form method="get" action="<?= url('admin/reservas') ?>">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Feria</label>
                            <select name="feria_id" class="form-control">
                                <option value="">Todas</option>
                                <?php foreach ($ferias as $f): ?>
                                    <option value="<?= (int) $f['id'] ?>" <?= (isset($filtros['feria_id']) && (int) $filtros['feria_id'] === (int) $f['id']) ? 'selected' : '' ?>><?= e($f['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Estado</label>
                            <select name="estado" class="form-control">
                                <option value="">Todos</option>
                                <?php foreach ($estados as $valor => $texto): ?>
                                    <option value="<?= e($valor) ?>" <?= (isset($filtros['estado']) && $filtros['estado'] === $valor) ? 'selected' : '' ?>><?= e($texto) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 align-self-end">
                            <button type="submit" class="btn btn-primary btn-block">Filtrar</button>
                        </div>
                        <div class="col-md-2 align-self-end">
                            <a href="<?= url('admin/reservas') ?>" class="btn btn-secondary btn-block">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Solicitudes</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover table-striped">
                    <thead>
                    <tr>
                        <th>N.º</th><th>Feria</th><th>Expositor</th><th>Emprendimiento</th><th>Correo</th><th>Rubro</th><th>Código</th><th>Fecha</th><th>Estado</th><th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($solicitudes)): ?>
                        <tr><td colspan="10" class="text-center">No hay solicitudes con esos filtros.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($solicitudes as $s): ?>
                        <?php $estadoReserva = Solicitud::normalizar((string) $s['estado']); ?>
                        <tr>
                            <td><?= (int) $s['id'] ?></td>
                            <td><?= e($s['feria'] ?? '') ?></td>
                            <td><?= e($s['nombre'] ?? $s['titular'] ?? '') ?></td>
                            <td><?= e($s['emprendimiento'] ?? '') ?></td>
                            <td><?= e($s['email'] ?? '') ?></td>
                            <td><?= e($s['categoria'] ?? '') ?></td>
                            <td><?= e($s['codigo'] ?? '—') ?></td>
                            <td><?= e($s['feria_fecha'] ?? $s['creada_en'] ?? '') ?></td>
                            <td><?= etiqueta_estado($estadoReserva) ?></td>
                            <td>
                                <?php if ($estadoReserva === 'pendiente'): ?>
                                    <form method="post" action="<?= e(url('admin/reserva/confirmar')) ?>" class="d-inline">
                                        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button class="btn btn-outline-success btn-sm" type="submit">Confirmar</button>
                                    </form>
                                    <form method="post" action="<?= e(url('admin/reserva/rechazar')) ?>" class="d-flex mt-1">
                                        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <input type="text" name="motivo" class="form-control form-control-sm mr-1" maxlength="255" placeholder="Motivo (opcional)" aria-label="Motivo de rechazo">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Rechazar</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('admin/reserva/reenviar')) ?>" class="d-inline mt-1">
                                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button class="btn btn-outline-info btn-sm" type="submit">Reenviar correo</button>
                                </form>
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