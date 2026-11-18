<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/**
 * Tabla ferias: nombre, fecha, lugar, foto del lugar y estado
 * (borrador, abierta o cerrada). Solo las abiertas se ven en la página pública.
 */
final class Feria extends Modelo
{
    protected string $tabla = 'ferias';

    /** Condición común: feria publicada, con fecha vigente y con zonas y carpa oficial listas. */
    private const ABIERTA_SQL = "f.estado = 'abierta' AND f.fecha >= CURRENT_DATE
               AND EXISTS (
                    SELECT 1 FROM areas a
                    JOIN categorias c ON c.id = a.categoria_id AND c.activo = 1
                    WHERE a.feria_id = f.id
               )
               AND EXISTS (SELECT 1 FROM tipos_carpa t WHERE t.activo = 1)";

    public function listar(array $filtros = []): array
    {
        $sql = "SELECT f.*,
                       (SELECT COUNT(*) FROM areas a WHERE a.feria_id = f.id) AS zonas,
                       (SELECT COUNT(*) FROM solicitudes s WHERE s.feria_id = f.id
                            AND s.estado IN ('pendiente', 'espera', 'confirmada', 'asignada')) AS reservas
                FROM ferias f";
        $parametros = [];
        if (!empty($filtros['estado'])) {
            $sql .= ' WHERE f.estado = :estado';
            $parametros['estado'] = $filtros['estado'];
        }
        return $this->consultar($sql . ' ORDER BY f.fecha DESC, f.id DESC', $parametros);
    }

    /** Opciones "Nombre · 15/11/2026 · Abierta". */
    public function opciones(): array
    {
        $opciones = [];
        foreach ($this->consultar('SELECT id, nombre, fecha, estado FROM ferias ORDER BY fecha DESC, nombre') as $f) {
            $opciones[$f['id']] = $f['nombre'] . ' · ' . fecha_corta($f['fecha']) . ' · ' . nombre_estado($f['estado']);
        }
        return $opciones;
    }

    /** Ferias que reciben reservas, de la más próxima a la más lejana. */
    public function abiertasParaInscripcion(): array
    {
        return $this->consultar(
            'SELECT f.id, f.nombre, f.fecha, f.lugar, f.foto
             FROM ferias f
             WHERE ' . self::ABIERTA_SQL . '
             ORDER BY f.fecha, f.nombre'
        );
    }

    public function estaAbiertaParaInscripcion(int $id): bool
    {
        return (int) $this->valor(
            'SELECT COUNT(*) FROM ferias f WHERE f.id = :id AND ' . self::ABIERTA_SQL,
            ['id' => $id]
        ) > 0;
    }

    /** Ferias en estado «abierta» (para los contadores del inicio del panel). */
    public function abiertas(): array
    {
        return $this->consultar("SELECT id, nombre, fecha FROM ferias WHERE estado = 'abierta' ORDER BY fecha, nombre");
    }

    /** Todas las ferias para el selector del mapa: primero las abiertas. */
    public function paraMapa(): array
    {
        return $this->consultar("SELECT * FROM ferias ORDER BY (estado = 'abierta') DESC, fecha DESC, id DESC");
    }

    /** Cuántas zonas y reservas dependen de la feria. */
    public function dependencias(int $id): array
    {
        $consulta = $this->db->prepare(
            'SELECT (SELECT COUNT(*) FROM areas WHERE feria_id = :a) AS areas,
                    (SELECT COUNT(*) FROM solicitudes WHERE feria_id = :s) AS solicitudes'
        );
        $consulta->execute(['a' => $id, 's' => $id]);
        $fila = $consulta->fetch();
        return ['areas' => (int) $fila['areas'], 'solicitudes' => (int) $fila['solicitudes']];
    }
}
