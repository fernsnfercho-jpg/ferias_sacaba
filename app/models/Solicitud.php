<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/**
 * Tabla solicitudes: reserva de un expositor para una feria.
 *
 * Estados de la versión 3: pendiente → confirmada, rechazada o cancelada.
 * La carpa retenida está en la tabla asignaciones; al rechazar o cancelar se
 * elimina esa fila (la carpa queda libre) y el código se guarda en codigo_liberado.
 */
final class Solicitud extends Modelo
{
    protected string $tabla = 'solicitudes';

    public const ESTADOS = [
        'pendiente'  => 'Pendiente',
        'confirmada' => 'Confirmada',
        'rechazada'  => 'Rechazada',
        'cancelada'  => 'Cancelada',
    ];

    /**
     * Los valores de versiones anteriores se leen como su equivalente actual,
     * sin modificar los datos guardados: «asignada» = confirmada, «espera» = pendiente.
     */
    public const ESTADO_SQL = "CASE s.estado WHEN 'asignada' THEN 'confirmada' WHEN 'espera' THEN 'pendiente' ELSE s.estado END";

    private const CONSULTA_DETALLE = 'SELECT s.*, ' . self::ESTADO_SQL . ' AS estado_reserva,
                       f.nombre AS feria, f.fecha AS feria_fecha, f.lugar AS feria_lugar, f.estado AS feria_estado,
                       e.emprendimiento, e.telefono, u.nombre AS titular, u.email,
                       c.nombre AS categoria,
                       t.nombre AS carpa, t.ancho_m AS carpa_ancho, t.largo_m AS carpa_largo,
                       x.id AS asignacion_id, x.codigo, x.area_id, a.nombre AS area
                FROM solicitudes s
                JOIN ferias f ON f.id = s.feria_id
                JOIN expositores e ON e.id = s.expositor_id
                JOIN usuarios u ON u.id = e.usuario_id
                JOIN categorias c ON c.id = s.categoria_id
                JOIN tipos_carpa t ON t.id = s.tipo_carpa_id
                LEFT JOIN asignaciones x ON x.solicitud_id = s.id
                LEFT JOIN areas a ON a.id = x.area_id';

    /** Lo mismo que ESTADO_SQL, para un valor que ya está en PHP. */
    public static function normalizar(string $estado): string
    {
        return ['asignada' => 'confirmada', 'espera' => 'pendiente'][$estado] ?? $estado;
    }

    /** Reservas con sus datos completos, de la más reciente a la más antigua. */
    public function listar(array $filtros = []): array
    {
        $sql = self::CONSULTA_DETALLE . ' WHERE 1 = 1';
        $parametros = [];
        if (!empty($filtros['feria_id'])) {
            $sql .= ' AND s.feria_id = :feria_id';
            $parametros['feria_id'] = (int) $filtros['feria_id'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= ' AND ' . self::ESTADO_SQL . ' = :estado';
            $parametros['estado'] = $filtros['estado'];
        }
        return $this->consultar($sql . ' ORDER BY s.creada_en DESC, s.id DESC', $parametros);
    }

    /** Las últimas reservas registradas (para el inicio del panel). */
    public function ultimas(int $cantidad): array
    {
        return $this->consultar(
            self::CONSULTA_DETALLE . ' ORDER BY s.creada_en DESC, s.id DESC LIMIT ' . max(1, $cantidad)
        );
    }

    /** Reserva con feria, expositor, carpa oficial y su carpa retenida (si la tiene). */
    public function buscarDetalle(int $id): ?array
    {
        $filas = $this->consultar(self::CONSULTA_DETALLE . ' WHERE s.id = :id', ['id' => $id]);
        return $filas[0] ?? null;
    }

    /** Reservas del expositor conectado, sin exponer reservas ajenas. */
    public function deExpositor(int $expositorId): array
    {
        return $this->consultar(
            self::CONSULTA_DETALLE . ' WHERE s.expositor_id = :expositor_id ORDER BY f.fecha DESC, s.creada_en DESC',
            ['expositor_id' => $expositorId]
        );
    }

    /** La reserva del expositor en una feria (en cualquier estado) o null. */
    public function deFeriaYExpositor(int $feriaId, int $expositorId): ?array
    {
        $filas = $this->consultar(
            self::CONSULTA_DETALLE . ' WHERE s.feria_id = :feria_id AND s.expositor_id = :expositor_id',
            ['feria_id' => $feriaId, 'expositor_id' => $expositorId]
        );
        return $filas[0] ?? null;
    }

    /** Reservas que esperan la confirmación del administrador. */
    public function contarPendientes(): int
    {
        return $this->contar("estado IN ('pendiente', 'espera')");
    }

    /**
     * Carpas que quedaron libres por un rechazo o una cancelación en la feria:
     * código → última reserva que la tuvo (para marcarlas en el mapa).
     */
    public function liberadasDeFeria(int $feriaId): array
    {
        $filas = $this->consultar(
            "SELECT s.id, s.codigo_liberado, s.estado, e.emprendimiento, u.nombre AS titular
             FROM solicitudes s
             JOIN expositores e ON e.id = s.expositor_id
             JOIN usuarios u ON u.id = e.usuario_id
             WHERE s.feria_id = :feria_id AND s.estado IN ('rechazada', 'cancelada')
               AND s.codigo_liberado IS NOT NULL
             ORDER BY s.id",
            ['feria_id' => $feriaId]
        );
        $porCodigo = [];
        foreach ($filas as $fila) {
            $porCodigo[strtoupper((string) $fila['codigo_liberado'])] = $fila;
        }
        return $porCodigo;
    }

    public function existeEnFeria(int $feriaId, int $expositorId, ?int $excluirId): bool
    {
        $sql = 'SELECT COUNT(*) FROM solicitudes WHERE feria_id = :feria_id AND expositor_id = :expositor_id';
        $parametros = ['feria_id' => $feriaId, 'expositor_id' => $expositorId];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir';
            $parametros['excluir'] = $excluirId;
        }
        return (int) $this->valor($sql, $parametros) > 0;
    }

    /** Solicitudes sin carpa (lo usa el módulo de asignaciones manuales, oculto en esta versión). */
    public function sinPuesto(?int $feriaId = null, ?int $categoriaId = null): array
    {
        $sql = self::CONSULTA_DETALLE . " WHERE x.id IS NULL AND s.estado IN ('pendiente', 'espera')";
        $parametros = [];
        if ($feriaId !== null) {
            $sql .= ' AND s.feria_id = :feria_id';
            $parametros['feria_id'] = $feriaId;
        }
        if ($categoriaId !== null) {
            $sql .= ' AND s.categoria_id = :categoria_id';
            $parametros['categoria_id'] = $categoriaId;
        }
        return $this->consultar($sql . ' ORDER BY s.creada_en, s.id', $parametros);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->db->prepare('UPDATE solicitudes SET estado = :estado WHERE id = :id')
            ->execute(['estado' => $estado, 'id' => $id]);
    }
}
