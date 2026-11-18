<!doctype html><html lang="es"><meta charset="utf-8"><title>Ubicación de la feria</title>
<style>body{font:16px Arial;color:#203040}iframe{width:100%;height:420px;border:0}</style>
<h2><?= htmlspecialchars($feria['nombre'], ENT_QUOTES, 'UTF-8') ?></h2>
<p><?= htmlspecialchars($feria['lugar'], ENT_QUOTES, 'UTF-8') ?></p>
<iframe title="Ubicación de la feria en Google Maps" loading="lazy" allowfullscreen
 referrerpolicy="strict-origin-when-cross-origin"
 src="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"></iframe>
<p>Si Google no carga el mapa, compruebe internet, la clave y sus restricciones.</p></html>
