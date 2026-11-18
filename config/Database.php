<?php
declare(strict_types=1);

/**
 * Conexión única (patrón Singleton) a la base ferias_sacaba mediante PDO.
 * Los datos de acceso se leen de config/config.php.
 */
final class Database
{
    private static ?PDO $conexion = null;

    public static function conectar(): PDO
    {
        if (self::$conexion === null) {
            $c = require __DIR__ . '/config.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $c['db_host'],
                $c['db_port'],
                $c['db_name']
            );
            self::$conexion = new PDO($dsn, $c['db_user'], $c['db_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$conexion;
    }
}
