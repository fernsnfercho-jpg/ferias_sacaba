<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/**
 * Tabla asignaciones: carpa (posición y medidas) que retiene una reserva dentro
 * de un área. Solo existen filas para carpas ocupadas: las libres no se guardan,
 * se calculan con la cuadrícula del área (UbicacionService).
 */
final class Asignacion extends Modelo
{
    protected string $tabla = 'asignaciones';

    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT x.*, f.nombre AS feria, a.nombre AS area, e.emprendimiento, t.nombre AS carpa
                FROM asignaciones x
                JOIN ferias f ON f.id = x.feria_id
                JOIN areas a ON a.id = x.area_id
                JOIN solicitudes s ON s.id = x.solicitud_id
                JOIN expositores e ON e.id = s.expositor_id
                JOIN tipos_carpa t ON t.id = s.tipo_carpa_id
                WHERE 1 = 1';
        $parametros = [];
        if (!empty($filtros['feria_id'])) {
            $sql .= ' AND x.feria_id = :feria_id';
            $parametros['feria_id'] = (int) $filtros['feria_id'];
        }
        if (!empty($filtros['area_id'])) {
            $sql .= ' AND x.area_id = :area_id';
            $parametros['area_id'] = (int) $filtros['area_id'];
        }
        return $this->consultar($sql . ' ORDER BY f.fecha DESC, a.nombre, x.codigo', $parametros);
    }

    /** Carpas ocupadas de un área (para validar cambios de medidas). */
    public function puestosDeArea(int $areaId, ?int $excluirId = null): array
    {
        $sql = 'SELECT x.id, x.codigo, x.x_m, x.y_m, x.ancho_m, x.largo_m, x.fijada, x.solicitud_id, e.emprendimiento
                FROM asignaciones x
                JOIN solicitudes s ON s.id = x.solicitud_id
                JOIN expositores e ON e.id = s.expositor_id
                WHERE x.area_id = :area_id';
        $parametros = ['area_id' => $areaId];
        if ($excluirId !== null) {
            $sql .= ' AND x.id <> :excluir';
            $parametros['excluir'] = $excluirId;
        }
        return $this->consultar($sql . ' ORDER BY x.y_m, x.x_m', $parametros);
    }

    /** Carpas ocupadas de una feria con el estado de su reserva y los datos del expositor. */
    public function puestosDeFeria(int $feriaId): array
    {
        return $this->consultar(
            'SELECT x.id, x.codigo, x.area_id, x.solicitud_id, x.x_m, x.y_m, x.ancho_m, x.largo_m,
                    ' . Solicitud::ESTADO_SQL . ' AS estado_reserva,
                    s.expositor_id, e.emprendimiento, u.nombre AS titular, c.nombre AS categoria
             FROM asignaciones x
             JOIN solicitudes s ON s.id = x.solicitud_id
             JOIN expositores e ON e.id = s.expositor_id
             JOIN usuarios u ON u.id = e.usuario_id
             JOIN categorias c ON c.id = s.categoria_id
             WHERE x.feria_id = :feria_id
             ORDER BY x.area_id, x.y_m, x.x_m',
            ['feria_id' => $feriaId]
        );
    }

    /** La carpa retenida por una reserva, o null si no tiene. */
    public function deSolicitud(int $solicitudId): ?array
    {
        $filas = $this->consultar(
            'SELECT * FROM asignaciones WHERE solicitud_id = :solicitud_id LIMIT 1',
            ['solicitud_id' => $solicitudId]
        );
        return $filas[0] ?? null;
    }

    public function codigoExiste(int $feriaId, string $codigo, ?int $excluirId): bool
    {
        $sql = 'SELECT COUNT(*) FROM asignaciones WHERE feria_id = :feria_id AND codigo = :codigo';
        $parametros = ['feria_id' => $feriaId, 'codigo' => $codigo];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir';
            $parametros['excluir'] = $excluirId;
        }
        return (int) $this->valor($sql, $parametros) > 0;
    }

    /**
     * Siguiente código libre en la feria para un área (lo usa el módulo de
     * asignaciones manuales, oculto en esta versión). En las reservas el código
     * sale de la posición de la carpa en la cuadrícula.
     */
    public function siguienteCodigo(int $feriaId, string $nombreArea): string
    {
        $prefijo = Area::prefijoCodigo($nombreArea);
        $usados = array_column(
            $this->consultar('SELECT codigo FROM asignaciones WHERE feria_id = :feria_id', ['feria_id' => $feriaId]),
            'codigo'
        );
        $usados = array_map('strtoupper', $usados);
        for ($n = 1; ; $n++) {
            $codigo = sprintf('%s%02d', $prefijo, $n);
            if (!in_array($codigo, $usados, true)) {
                return $codigo;
            }
        }
    }
}
