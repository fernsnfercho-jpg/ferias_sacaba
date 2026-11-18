<?php
declare(strict_types=1);

/**
 * Lee y guarda los archivos de configuración editables desde el panel.
 *
 *   config/correo.php  → valores de ejemplo (sin contraseñas)
 *   config/correo.local.php → valores reales de esta instalación
 *
 * Al leer, lo que haya en el archivo .local reemplaza al ejemplo.
 * Al guardar, solo se escribe el archivo .local.
 */
final class Configuracion
{
    private const ARCHIVOS = ['correo', 'feria'];

    private static array $cache = [];

    public static function leer(string $nombre): array
    {
        if (!isset(self::$cache[$nombre])) {
            $valores = self::cargar(self::ruta($nombre));
            // Solo se aceptan las claves que existen en el archivo de ejemplo.
            $propios = array_intersect_key(self::cargar(self::ruta($nombre, true)), $valores);
            self::$cache[$nombre] = array_replace($valores, $propios);
        }
        return self::$cache[$nombre];
    }

    /** Guarda los cambios en config/<nombre>.local.php. Devuelve false si no se pudo escribir. */
    public static function guardar(string $nombre, array $cambios): bool
    {
        $nuevos = array_replace(self::leer($nombre), array_intersect_key($cambios, self::leer($nombre)));
        $contenido = "<?php\n"
            . "// Archivo creado desde «Configuración» del panel de Ferias Sacaba.\n"
            . "// Contiene datos propios de esta instalación: no lo suba a un repositorio.\n"
            . 'return ' . var_export($nuevos, true) . ";\n";

        $destino = self::ruta($nombre, true);
        $temporal = $destino . '.tmp';
        if (@file_put_contents($temporal, $contenido, LOCK_EX) === false || !@rename($temporal, $destino)) {
            @unlink($temporal);
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($destino, true);
        }
        unset(self::$cache[$nombre]);
        return true;
    }

    private static function ruta(string $nombre, bool $local = false): string
    {
        if (!in_array($nombre, self::ARCHIVOS, true)) {
            throw new InvalidArgumentException('Configuración desconocida: ' . $nombre);
        }
        return RAIZ_PROYECTO . '/config/' . $nombre . ($local ? '.local' : '') . '.php';
    }

    private static function cargar(string $archivo): array
    {
        if (!is_file($archivo)) {
            return [];
        }
        $valores = require $archivo;
        return is_array($valores) ? $valores : [];
    }
}
