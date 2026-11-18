<?php
// Lista genérica de un módulo con búsqueda, filtros y acciones.
$tituloPagina = $titulo;
$moduloActivo = $modulo;
require __DIR__ . '/../layout/header.php';

$hayFiltros = $q !== '' || $filtros !== [];
$retorno = http_build_query(array_filter(['q' => $q] + $filtros, static fn ($v) => $v !== ''));
?>
<div class="encabezado">
    <div>
        <h1><?= e($titulo) ?></h1>
        <?php if ($descripcion !== ''): ?><p class="descripcion"><?= e($descripcion) ?></p><?php endif; ?>
    </div>
    <a class="boton boton-primario" href="<?= e(url($modulo . '/nuevo', $filtros)) ?>">+ <?= e($textoNuevo) ?></a>
</div>

<?php foreach ($requisitos as $r): ?>
    <div class="alerta alerta-aviso"><?= e($r[0]) ?> <a href="<?= e(url($r[1])) ?>"><?= e($r[2]) ?> →</a></div>
<?php endforeach; ?>

<form method="get" action="index.php" class="filtros" role="search">
    <input type="hidden" name="ruta" value="<?= e($modulo) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar en <?= e(minusculas($titulo)) ?>…" aria-label="Buscar">
    <?php foreach ($filtrosDef as $nombre => $f): ?>
        <select name="<?= e($nombre) ?>" aria-label="<?= e($f['etiqueta']) ?>" data-autoenviar>
            <option value=""><?= e($f['todos'] ?? 'Todos') ?></option>
            <?php foreach ($f['opciones'] as $valor => $texto): ?>
                <option value="<?= e($valor) ?>"<?= (string) $valor === (string) ($filtros[$nombre] ?? '') ? ' selected' : '' ?>><?= e($texto) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endforeach; ?>
    <button type="submit" class="boton">Buscar</button>
    <?php if ($hayFiltros): ?><a class="boton-enlace" href="<?= e(url($modulo)) ?>">Quitar filtros</a><?php endif; ?>
</form>

<p class="conteo"><?= count($filas) ?> <?= count($filas) === 1 ? 'registro' : 'registros' ?><?= $hayFiltros ? ' encontrados' : '' ?></p>

<div class="tarjeta tabla-envoltura">
    <?php if ($filas === []): ?>
        <div class="vacio">
            <?php if ($hayFiltros): ?>
                <p>No hay resultados con esos filtros.</p>
            <?php else: ?>
                <p>Todavía no hay registros en «<?= e($titulo) ?>».</p>
                <a class="boton boton-primario" href="<?= e(url($modulo . '/nuevo')) ?>">+ <?= e($textoNuevo) ?></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <?php foreach ($columnas as $col): ?>
                    <th scope="col"><?= e(is_array($col) ? $col[0] : $col) ?></th>
                <?php endforeach; ?>
                <th scope="col" class="acciones">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $fila): ?>
                <tr>
                    <?php foreach ($columnas as $clave => $col): ?>
                        <td><?= formatear_celda($fila[$clave] ?? null, is_array($col) ? ($col[1] ?? 'texto') : 'texto') ?></td>
                    <?php endforeach; ?>
                    <td class="acciones">
                        <?php foreach ($acciones[$fila['id']] ?? [] as $a): ?>
                            <a class="boton boton-chico" href="<?= e($a['url']) ?>"<?= !empty($a['externo']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($a['texto']) ?></a>
                        <?php endforeach; ?>
                        <a class="boton boton-chico" href="<?= e(url($modulo . '/editar', ['id' => $fila['id']])) ?>">Editar</a>
                        <form method="post" action="<?= e(url($modulo . '/eliminar')) ?>"
                              data-confirmar="¿Eliminar «<?= e($nombres[$fila['id']] ?? '') ?>»? Esta acción no se puede deshacer.">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
                            <input type="hidden" name="retorno" value="<?= e($retorno) ?>">
                            <button type="submit" class="boton boton-chico boton-peligro">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
