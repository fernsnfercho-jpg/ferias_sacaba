<?php
// ============================================================
// CONFIGURACIÓN DE LA CONEXIÓN A MySQL
// Cambie estos valores según su instalación.
//
//  - XAMPP: usuario "root" y contraseña vacía (ya viene así).
//  - MySQL instalado aparte (el que usa MySQL Workbench):
//    escriba en 'db_pass' la contraseña que eligió al instalarlo.
//
// También se pueden definir con variables de entorno
// DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASS.
// ============================================================
return [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('DB_PORT') ?: '3306',
    'db_name' => getenv('DB_NAME') ?: 'ferias_sacaba',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
];
