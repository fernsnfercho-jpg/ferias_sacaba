<?php
$mensajes = tomar_flash();
$usuario = Auth::usuario();
$feriaInicial = (string) ($ferias[0]['id'] ?? '');
$categoriaInicial = '';
foreach ($categorias as $item) {
    if ((string) $item['feria_id'] === $feriaInicial) {
        $categoriaInicial = (string) $item['id'];
        break;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis reservas · Ferias Sacaba</title>
    <link rel="stylesheet" href="css/public.css">
    <link rel="stylesheet" href="css/feria-app.css">
</head>
<body class="publico-body">
<header class="publico-barra">
    <a class="publico-marca" href="<?= e(url('inicio')) ?>"><strong>FERIAS SACABA</strong><span>Municipio de Sacaba · Expositor</span></a>
    <nav class="publico-nav" aria-label="Navegación principal">
        <a href="#reservar">Reservar carpa</a>
        <a href="#mis-reservas">Mis reservas</a>
        <form method="post" action="<?= e(url('salir')) ?>"><?= csrf_campo() ?><button class="nav-logout" type="submit">Salir</button></form>
    </nav>
</header>

<main class="publico-contenido portal-simple">
    <?php foreach ($mensajes as $m): ?>
        <div class="publico-alerta<?= in_array(($m['tipo'] ?? ''), ['error', 'aviso'], true) ? ' publico-alerta-error' : '' ?>" role="status"><?= e($m['texto'] ?? '') ?></div>
    <?php endforeach; ?>

    <section class="portal-saludo">
        <div><p class="publico-kicker">Espacio del expositor</p><h1>Hola, <?= e($expositor['titular']) ?></h1>
            <p><?= e($expositor['emprendimiento']) ?> · <?= e($expositor['email']) ?></p></div>
        <a class="publico-boton" href="#reservar">Reservar mi carpa</a>
    </section>

    <section class="publico-panel portal-reservar" id="reservar">
        <h2>Reservar mi carpa</h2>
        <p class="publico-ayuda">Elija una feria, el rubro de sus productos y toque una carpa libre en el plano.</p>
        <?php if ($ferias === []): ?>
            <div class="publico-vacio">No hay ferias abiertas para reservar en este momento.</div>
        <?php elseif ($categorias === []): ?>
            <div class="publico-vacio">Las ferias abiertas aún no tienen rubros o áreas disponibles.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('reserva/nueva')) ?>" id="reserva-form">
                <?= csrf_campo() ?>
                <div class="portal-selecciones">
                    <div class="campo">
                        <label for="feria_id">1. Feria</label>
                        <select name="feria_id" id="feria_id" required>
                            <?php foreach ($ferias as $feria): ?>
                                <option value="<?= (int) $feria['id'] ?>"<?= (string) $feria['id'] === $feriaInicial ? ' selected' : '' ?>>
                                    <?= e($feria['nombre']) ?> · <?= e(fecha_corta($feria['fecha'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="categoria_id">2. Rubro</label>
                        <select name="categoria_id" id="categoria_id" required>
                            <?php foreach ($categorias as $item): ?>
                                <option value="<?= (int) $item['id'] ?>" data-feria="<?= (int) $item['feria_id'] ?>"
                                    <?= (string) $item['id'] === $categoriaInicial && (string) $item['feria_id'] === $feriaInicial ? ' selected' : '' ?>>
                                    <?= e($item['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <p class="carpa-medida">Carpa oficial: <strong>3 × 4 m (12 m²)</strong> · Pasillo: 1.50 m</p>
                <?php foreach ($categorias as $item): ?>
                    <section class="carpa-plano" data-feria="<?= (int) $item['feria_id'] ?>" data-categoria="<?= (int) $item['id'] ?>" hidden>
                        <?php if ($item['plano']['libres'] === 0): ?>
                            <p class="carpa-sin-libres">No hay carpas disponibles para este rubro.</p>
                        <?php endif; ?>
                        <?php foreach ($item['plano']['zonas'] as $zona): ?>
                            <div class="carpa-zona">
                                <h3><?= e($zona['area']['nombre']) ?></h3>
                                <div class="carpa-grid" style="--columnas:<?= max(1, (int) $zona['columnas']) ?>">
                                    <?php foreach ($zona['celdas'] as $celda): ?>
                                        <?php
                                        $puesto = $celda['puesto'] ?? [];
                                        $ocupante = trim((string) ($puesto['emprendimiento'] ?? '') . ' · ' . (string) ($puesto['titular'] ?? ''), ' ·');
                                        $libre = $celda['estado'] === 'libre';
                                        ?>
                                        <button type="button" class="carpa-celda <?= $libre ? 'carpa-libre' : ($celda['estado'] === 'ocupada' ? 'carpa-ocupada' : 'carpa-bloqueada') ?>"
                                            data-codigo="<?= e($celda['codigo']) ?>" <?= $libre ? '' : 'disabled' ?>
                                            title="<?= $libre ? 'Carpa disponible ' . e($celda['codigo']) : 'Carpa ocupada o no disponible' ?>">
                                            <strong><?= e($celda['codigo']) ?></strong>
                                            <small><?= $libre ? 'Libre' : ($celda['estado'] === 'ocupada' ? 'Ocupada' : 'No disponible') ?></small>
                                            <?php if (!$libre && $ocupante !== ''): ?><span><?= e($ocupante) ?></span><?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                <input type="hidden" name="codigo" id="codigo-carpa" value="">
                <div class="carpa-confirmacion" id="resumen-carpa" aria-live="polite">3. Seleccione una carpa libre en el plano.</div>
                <button class="publico-boton carpa-confirmar" type="submit">Confirmar reserva</button>
                <p class="publico-ayuda">La reserva quedará Pendiente hasta que la administración la apruebe.</p>
            </form>
        <?php endif; ?>
    </section>

    <section class="publico-panel" id="mis-reservas">
        <h2>Mis reservas</h2>
        <?php if ($solicitudes === []): ?>
            <p class="publico-ayuda">Todavía no tiene reservas.</p>
        <?php else: ?>
            <div class="publico-tabla-wrap">
                <table class="publico-tabla">
                    <thead><tr><th>Feria</th><th>Fecha</th><th>Rubro</th><th>Carpa</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <?php $estado = Solicitud::normalizar((string) $solicitud['estado']); ?>
                        <tr>
                            <td><?= e($solicitud['feria']) ?></td>
                            <td><?= e(fecha_corta($solicitud['feria_fecha'])) ?></td>
                            <td><?= e($solicitud['categoria']) ?></td>
                            <td><?= e($solicitud['codigo'] ?? $solicitud['codigo_liberado'] ?? '—') ?></td>
                            <td><?= etiqueta_estado($estado) ?></td>
                            <td>
                                <?php if ($estado === 'pendiente'): ?>
                                    <form method="post" action="<?= e(url('reserva/cancelar')) ?>" onsubmit="return confirm('¿Cancelar esta reserva y liberar la carpa?')">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="id" value="<?= (int) $solicitud['id'] ?>">
                                        <button class="btn-cancelar-reserva" type="submit">Cancelar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer class="publico-pie">Ferias Sacaba · Municipio de Sacaba</footer>
<script>
(() => {
    const feria = document.getElementById('feria_id');
    const rubro = document.getElementById('categoria_id');
    const planos = Array.from(document.querySelectorAll('.carpa-plano'));
    const codigo = document.getElementById('codigo-carpa');
    const resumen = document.getElementById('resumen-carpa');
    const formulario = document.getElementById('reserva-form');
    if (!feria || !rubro || !formulario) return;

    const limpiar = () => {
        codigo.value = '';
        document.querySelectorAll('.carpa-celda.seleccionada').forEach((celda) => celda.classList.remove('seleccionada'));
        resumen.textContent = '3. Seleccione una carpa libre en el plano.';
    };
    const actualizar = (cambioFeria) => {
        const opciones = Array.from(rubro.options);
        opciones.forEach((opcion) => { opcion.hidden = opcion.dataset.feria !== feria.value; });
        if (cambioFeria || !rubro.selectedOptions.length || rubro.selectedOptions[0].hidden) {
            const primera = opciones.find((opcion) => !opcion.hidden);
            rubro.value = primera ? primera.value : '';
        }
        planos.forEach((plano) => {
            plano.hidden = plano.dataset.feria !== feria.value || plano.dataset.categoria !== rubro.value;
        });
        limpiar();
    };
    feria.addEventListener('change', () => actualizar(true));
    rubro.addEventListener('change', () => actualizar(false));
    document.querySelectorAll('.carpa-libre').forEach((celda) => {
        celda.addEventListener('click', () => {
            limpiar();
            codigo.value = celda.dataset.codigo;
            celda.classList.add('seleccionada');
            const feriaElegida = feria.options[feria.selectedIndex].text;
            const rubroElegido = rubro.options[rubro.selectedIndex].text;
            resumen.textContent = feriaElegida + ' · ' + rubroElegido + ' · Carpa ' + codigo.value + ' · 3 × 4 m (12 m²) · Pendiente de aprobación.';
        });
    });
    formulario.addEventListener('submit', (evento) => {
        if (!codigo.value) {
            evento.preventDefault();
            resumen.textContent = 'Seleccione una carpa libre antes de confirmar.';
            resumen.classList.add('carpa-error');
        }
    });
    actualizar(false);
})();
</script>
</body>
</html>
