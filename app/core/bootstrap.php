<?php
declare(strict_types=1);

// ------------------------------------------------------------
// Arranque de la aplicación: registro de errores, carga automática
// de clases, funciones de ayuda, zona horaria y sesión segura.
// ------------------------------------------------------------

define('RAIZ_PROYECTO', dirname(__DIR__, 2));

// Los errores y avisos de PHP nunca se muestran en pantalla:
// se registran en logs/errores.log.
if (!is_dir(RAIZ_PROYECTO . '/logs')) {
    @mkdir(RAIZ_PROYECTO . '/logs', 0755, true);
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', RAIZ_PROYECTO . '/logs/errores.log');

// Carga automática: busca el archivo con el mismo nombre de la clase.
spl_autoload_register(static function (string $clase): void {
    $carpetas = ['/app/core/', '/app/controllers/', '/app/models/', '/app/services/', '/config/'];
    foreach ($carpetas as $carpeta) {
        $archivo = RAIZ_PROYECTO . $carpeta . $clase . '.php';
        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});

require_once __DIR__ . '/helpers.php';

date_default_timezone_set('America/La_Paz');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('ferias_sacaba');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
