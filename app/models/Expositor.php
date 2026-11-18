<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/** Tabla expositores: emprendimientos, cada uno con una cuenta de usuario titular. */
final class Expositor extends Modelo
{
    protected string $tabla = 'expositores';

    public function listar(array $filtros = []): array
    {
        return $this->consultar(
            "SELECT e.id, e.usuario_id, e.emprendimiento, e.telefono,
                    u.nombre AS titular, u.email, u.activo,
                    (SELECT COUNT(*) FROM solicitudes s WHERE s.expositor_id = e.id) AS solicitudes
             FROM expositores e
             JOIN usuarios u ON u.id = e.usuario_id
             ORDER BY e.emprendimiento"
        );
    }

    /** Opciones "Emprendimiento — Titular". */
    public function opciones(): array
    {
        $filas = $this->consultar(
            'SELECT e.id, e.emprendimiento, u.nombre AS titular, u.activo
             FROM expositores e JOIN usuarios u ON u.id = e.usuario_id
             ORDER BY e.emprendimiento'
        );
        $opciones = [];
        foreach ($filas as $f) {
            $opciones[$f['id']] = $f['emprendimiento'] . ' — ' . $f['titular'] . ((int) $f['activo'] === 1 ? '' : ' (cuenta inactiva)');
        }
        return $opciones;
    }

    public function contarSolicitudes(int $id): int
    {
        return (int) $this->valor('SELECT COUNT(*) FROM solicitudes WHERE expositor_id = :id', ['id' => $id]);
    }

    public function buscarPorUsuario(int $usuarioId): ?array
    {
        $filas = $this->consultar(
            'SELECT e.id, e.usuario_id, e.emprendimiento, e.telefono, u.nombre AS titular, u.email
             FROM expositores e
             JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.usuario_id = :usuario_id LIMIT 1',
            ['usuario_id' => $usuarioId]
        );
        return $filas[0] ?? null;
    }
}
