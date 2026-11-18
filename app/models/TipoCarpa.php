<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/**
 * Tabla tipos_carpa. En esta versión hay un solo tipo activo: la carpa oficial
 * de 3 m de frente × 4 m de fondo. Los demás tamaños se conservan inactivos.
 * Todas las medidas del sistema se leen de aquí, no están escritas en el código.
 */
final class TipoCarpa extends Modelo
{
    protected string $tabla = 'tipos_carpa';

    /** La carpa oficial: el único tipo activo. */
    public function oficial(): array
    {
        $filas = $this->consultar(
            'SELECT * FROM tipos_carpa WHERE activo = 1 AND ancho_m = 3.00 AND largo_m = 4.00 ORDER BY id LIMIT 1'
        );
        if ($filas === []) {
            throw new RuntimeException('No hay una carpa oficial activa (3 × 4 m). Importe base_datos/ferias_sacaba.sql.');
        }
        return $filas[0];
    }

    public function listar(array $filtros = []): array
    {
        return $this->consultar(
            'SELECT t.*, t.ancho_m * t.largo_m AS superficie,
                    (SELECT COUNT(*) FROM solicitudes s WHERE s.tipo_carpa_id = t.id) AS solicitudes
             FROM tipos_carpa t
             ORDER BY t.ancho_m * t.largo_m, t.nombre'
        );
    }

    /** Opciones con medidas: "Carpa oficial 3×4 (3 × 4 m)". */
    public function opcionesActivas(?int $incluir = null): array
    {
        $filas = $this->consultar(
            'SELECT id, nombre, ancho_m, largo_m, activo FROM tipos_carpa
             WHERE activo = 1 OR id = :incluir
             ORDER BY ancho_m * largo_m, nombre',
            ['incluir' => $incluir ?? 0]
        );
        $opciones = [];
        foreach ($filas as $f) {
            $opciones[$f['id']] = sprintf(
                '%s (%s)%s',
                $f['nombre'],
                medida_carpa($f),
                (int) $f['activo'] === 1 ? '' : ' (inactivo)'
            );
        }
        return $opciones;
    }
}
