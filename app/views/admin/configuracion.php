<?php
$tituloPagina = 'Configuración';
$moduloActivo = 'admin/configuracion';
require __DIR__ . '/../layout/header.php';
?>
<?php foreach ($errores as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Correo de administración</h3></div>
            <div class="card-body">
                <p class="text-muted">Los avisos de reserva se envían a las cuentas de administrador activas. La configuración SMTP se guarda fuera de la carpeta pública, en <code>config/correo.local.php</code>.</p>
                <?php if ($administradores === []): ?>
                    <p class="text-danger">No hay una cuenta de administrador activa.</p>
                <?php else: ?>
                    <ul class="mb-3">
                        <?php foreach ($administradores as $administrador): ?>
                            <li><?= e($administrador['nombre']) ?> · <?= e($administrador['email']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" action="<?= e(url('admin/configuracion')) ?>">
                    <?= csrf_campo() ?><input type="hidden" name="guardar_correo" value="1">
                    <div class="form-group">
                        <label for="driver">Modo de envío</label>
                        <select id="driver" name="driver" class="form-control">
                            <option value="log"<?= ($config['driver'] ?? 'log') === 'log' ? ' selected' : '' ?>>Prueba local (log)</option>
                            <option value="smtp"<?= ($config['driver'] ?? '') === 'smtp' ? ' selected' : '' ?>>SMTP</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="host">Servidor SMTP</label>
                            <input id="host" name="host" class="form-control" value="<?= e($config['host'] ?? '') ?>" maxlength="180">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="puerto">Puerto</label>
                            <input id="puerto" name="puerto" type="number" min="1" max="65535" class="form-control" value="<?= e($config['puerto'] ?? 587) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="seguridad">Seguridad</label>
                        <select id="seguridad" name="seguridad" class="form-control">
                            <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'ninguna' => 'Sin cifrado'] as $valor => $nombre): ?>
                                <option value="<?= e($valor) ?>"<?= ($config['seguridad'] ?? 'tls') === $valor ? ' selected' : '' ?>><?= e($nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="usuario">Cuenta SMTP</label>
                        <input id="usuario" name="usuario" type="email" class="form-control" value="<?= e($config['usuario'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="clave">Contraseña SMTP o contraseña de aplicación</label>
                        <input id="clave" name="clave" type="password" class="form-control" autocomplete="new-password" placeholder="En blanco conserva la contraseña guardada">
                    </div>
                    <button class="btn btn-primary" type="submit">Guardar configuración</button>
                </form>
                <hr>
                <form method="post" action="<?= e(url('admin/configuracion')) ?>">
                    <?= csrf_campo() ?><input type="hidden" name="probar_correo" value="1">
                    <div class="form-group">
                        <label for="email_prueba">Enviar un correo de prueba a</label>
                        <input id="email_prueba" name="email_prueba" type="email" class="form-control" required>
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Probar correo</button>
                    <small class="form-text text-muted">En modo log, el mensaje se registra en <code>logs/correo.log</code>.</small>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Medidas oficiales</h3></div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>Carpa</th><td><?= e($carpa['nombre']) ?></td></tr>
                    <tr><th>Frente</th><td><?= e(medida($carpa['ancho_m'])) ?> m</td></tr>
                    <tr><th>Fondo</th><td><?= e(medida($carpa['largo_m'])) ?> m</td></tr>
                    <tr><th>Superficie</th><td><?= e(superficie_carpa($carpa)) ?></td></tr>
                </table>
                <form method="post" action="<?= e(url('admin/configuracion')) ?>">
                    <?= csrf_campo() ?><input type="hidden" name="guardar_pasillo" value="1">
                    <div class="form-group">
                        <label for="pasillo_defecto_m">Pasillo por defecto de nuevas áreas (m)</label>
                        <input id="pasillo_defecto_m" name="pasillo_defecto_m" type="number" min="0.01" max="20" step="0.01" class="form-control" value="<?= e(number_format($pasillo_default, 2, '.', '')) ?>" required>
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Guardar pasillo</button>
                </form>
                <p class="text-muted mt-3 mb-0">La carpa 3 × 4 m es fija. El pasillo se aplica a nuevas áreas; los valores existentes se conservan.</p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
