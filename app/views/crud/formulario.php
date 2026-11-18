<?php
// Formulario genérico (crear / editar) construido a partir de la definición de campos.
$tituloPagina = $titulo;
$moduloActivo = $modulo;
require __DIR__ . '/../layout/header.php';

$tiposInput = ['texto' => 'text', 'email' => 'email', 'password' => 'password', 'telefono' => 'tel',
               'decimal' => 'text', 'entero' => 'number', 'fecha' => 'date', 'archivo' => 'file'];
$requiereArchivo = count(array_filter($campos, static fn (array $c): bool => ($c['tipo'] ?? '') === 'archivo')) > 0;
?>
<div class="encabezado">
    <div>
        <p class="migas"><a href="<?= e(url($modulo)) ?>"><?= e($tituloModulo) ?></a> / <?= $fila === null ? 'Nuevo registro' : 'Editar' ?></p>
        <h1><?= e($titulo) ?></h1>
        <?php if ($subtitulo !== ''): ?><p class="descripcion"><?= e($subtitulo) ?></p><?php endif; ?>
    </div>
</div>

<?php foreach ($requisitos as $r): ?>
    <div class="alerta alerta-aviso"><?= e($r[0]) ?> <a href="<?= e(url($r[1])) ?>"><?= e($r[2]) ?> →</a></div>
<?php endforeach; ?>
<?php if ($aviso !== null): ?>
    <div class="alerta alerta-info"><?= e($aviso) ?></div>
<?php endif; ?>
<?php if ($errores !== []): ?>
    <div class="alerta alerta-error" role="alert"><?= e($errores['_general'] ?? 'No se guardó. Revise los datos marcados en rojo.') ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url($modulo . '/guardar')) ?>" class="tarjeta formulario"<?= $requiereArchivo ? ' enctype="multipart/form-data"' : '' ?>>
    <?= csrf_campo() ?>
    <?php if ($fila !== null): ?>
        <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
    <?php endif; ?>

    <div class="rejilla-form">
        <?php foreach ($campos as $nombre => $c):
            $tipo = $c['tipo'] ?? 'texto';
            $valor = $valores[$nombre] ?? '';
            $error = $errores[$nombre] ?? null;
            $idCampo = 'campo_' . $nombre;
            $condicion = $c['mostrarSi'] ?? null;
            $requerido = !empty($c['requerido']);
            // Los campos condicionales reciben "required" desde admin.js solo cuando se ven.
            $attrRequerido = $requerido && $condicion === null ? ' required' : '';
            $idAyuda = !empty($c['ayuda']) ? ' aria-describedby="ayuda_' . e($nombre) . '"' : '';
            ?>
            <div class="campo<?= $tipo === 'check' ? ' campo-check' : '' ?><?= $error !== null ? ' con-error' : '' ?>"
                <?php if ($condicion !== null): ?>
                    data-si-campo="<?= e($condicion[0]) ?>" data-si-valor="<?= e($condicion[1]) ?>" data-requerido="<?= $requerido ? '1' : '0' ?>"
                <?php endif; ?>>

                <?php if ($tipo === 'check'): ?>
                    <label>
                        <input type="checkbox" id="<?= e($idCampo) ?>" name="<?= e($nombre) ?>" value="1"<?= (int) $valor === 1 ? ' checked' : '' ?>>
                        <?= e($c['etiqueta']) ?>
                    </label>

                <?php elseif ($tipo === 'archivo'): ?>
                    <input type="file" id="<?= e($idCampo) ?>" name="<?= e($nombre) ?>" accept="image/jpeg,image/png,image/webp">
                <?php else: ?>
                    <label for="<?= e($idCampo) ?>"><?= e($c['etiqueta']) ?><?= $requerido ? ' <span class="obligatorio" title="Obligatorio">*</span>' : '' ?></label>

                    <?php if ($tipo === 'select'): ?>
                        <select id="<?= e($idCampo) ?>" name="<?= e($nombre) ?>"<?= $attrRequerido ?><?= $idAyuda ?>
                            <?= !empty($c['filtrarCon']) ? ' data-filtrar-con="' . e($c['filtrarCon']) . '"' : '' ?>>
                            <option value=""><?= e($c['vacio'] ?? '— Elija una opción —') ?></option>
                            <?php foreach ($c['opciones'] as $clave => $texto): ?>
                                <option value="<?= e($clave) ?>"<?= (string) $clave === (string) $valor ? ' selected' : '' ?><?php
                                    foreach ($c['datosOpciones'][$clave] ?? [] as $k => $v): ?> data-<?= e($k) ?>="<?= e($v) ?>"<?php endforeach;
                                ?>><?= e($texto) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($c['filtrarCon'])): ?>
                            <small class="aviso-filtro" id="aviso_<?= e($nombre) ?>" hidden></small>
                        <?php endif; ?>

                    <?php else: ?>
                        <input type="<?= $tiposInput[$tipo] ?? 'text' ?>" id="<?= e($idCampo) ?>" name="<?= e($nombre) ?>"
                               value="<?= $tipo === 'password' ? '' : e($valor) ?>"<?= $attrRequerido ?><?= $idAyuda ?>
                            <?= isset($c['max']) ? ' maxlength="' . (int) $c['max'] . '"' : '' ?>
                            <?= !empty($c['sugerencias']) ? ' list="sugerencias_' . e($nombre) . '"' : '' ?>
                            <?= $tipo === 'decimal' ? ' inputmode="decimal"' : '' ?>
                            <?= $tipo === 'password' ? ' autocomplete="new-password" minlength="8" maxlength="72"' : '' ?>
                            <?= $tipo === 'email' ? ' autocomplete="off"' : '' ?>>
                        <?php if (!empty($c['sugerencias'])): ?>
                            <datalist id="sugerencias_<?= e($nombre) ?>">
                                <?php foreach ($c['sugerencias'] as $sugerencia): ?><option value="<?= e($sugerencia) ?>"><?php endforeach; ?>
                            </datalist>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($c['ayuda'])): ?>
                    <small class="ayuda" id="ayuda_<?= e($nombre) ?>"><?= e($c['ayuda']) ?></small>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <small class="error-campo"><?= e($error) ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="acciones-form">
        <button type="submit" class="boton boton-primario">Guardar</button>
        <a class="boton" href="<?= e(url($modulo)) ?>">Cancelar</a>
        <span class="nota-obligatorio"><span class="obligatorio">*</span> Dato obligatorio</span>
    </div>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
