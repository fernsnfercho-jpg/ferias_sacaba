<?php
// Encabezado del panel administrativo con AdminLTE 3.2.0
$usuarioActual = Auth::usuario();
$mensajes = tomar_flash();
$moduloActivo = $moduloActivo ?? '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina ?? 'Panel') ?> · Ferias Sacaba</title>
    <link rel="stylesheet" href="vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="vendor/adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <form method="post" action="<?= e(url('salir')) ?>" class="form-inline">
                    <?= csrf_campo() ?>
                    <button class="nav-link btn btn-link" type="submit"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</button>
                </form>
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="<?= e(url('panel')) ?>" class="brand-link">
            <span class="brand-text font-weight-light">Ferias Sacaba</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column">
                    <li class="nav-item">
                        <a href="<?= e(url('panel')) ?>" class="nav-link <?= $moduloActivo === 'panel' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Inicio</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= e(url('admin/reservas')) ?>" class="nav-link <?= str_starts_with($moduloActivo, 'admin/reservas') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Reservas</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= e(url('admin/mapa')) ?>" class="nav-link <?= $moduloActivo === 'admin/mapa' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-map-marked-alt"></i>
                            <p>Mapa de carpas</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= e(url('admin/expositores')) ?>" class="nav-link <?= str_starts_with($moduloActivo, 'admin/expositores') ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Expositores</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= e(url('ferias')) ?>" class="nav-link <?= $moduloActivo === 'ferias' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            <p>Ferias</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= e(url('admin/configuracion')) ?>" class="nav-link <?= $moduloActivo === 'admin/configuracion' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Configuración</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><?= e($tituloPagina ?? '') ?></h1>
                    </div>
                </div>
            </div>
        </section>
        <section class="content">
            <div class="container-fluid">
                <?php foreach ($mensajes as $m): ?>
                    <div class="alert alert-<?= e($m['tipo'] === 'ok' ? 'success' : ($m['tipo'] === 'error' ? 'danger' : 'info')) ?> alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <?= e($m['texto'] ?? '') ?>
                    </div>
                <?php endforeach; ?>
