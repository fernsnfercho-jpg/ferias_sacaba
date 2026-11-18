<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/** Tabla usuarios: cuentas de administradores y de titulares de expositores. */
final class Usuario extends Modelo
{
    protected string $tabla = 'usuarios';

    public function listar(array $filtros = []): array
    {
        // Nunca se lee password_hash en los listados.
        $sql = 'SELECT u.id, u.nombre, u.email, u.rol, u.activo, e.emprendimiento
                FROM usuarios u
                LEFT JOIN expositores e ON e.usuario_id = u.id';
        $parametros = [];
        if (!empty($filtros['rol'])) {
            $sql .= ' WHERE u.rol = :rol';
            $parametros['rol'] = $filtros['rol'];
        }
        return $this->consultar($sql . ' ORDER BY u.rol, u.nombre', $parametros);
    }

    public function buscarPorEmail(string $email): ?array
    {
        $filas = $this->consultar('SELECT * FROM usuarios WHERE email = :email LIMIT 1', ['email' => $email]);
        return $filas[0] ?? null;
    }

    public function buscarAdministradorActivo(int $id): ?array
    {
        $filas = $this->consultar(
            "SELECT id, nombre, email FROM usuarios WHERE id = :id AND rol = 'administrador' AND activo = 1",
            ['id' => $id]
        );
        return $filas[0] ?? null;
    }

    public function buscarUsuarioActivo(int $id): ?array
    {
        $filas = $this->consultar(
            "SELECT id, nombre, email FROM usuarios WHERE id = :id AND rol = 'usuario' AND activo = 1",
            ['id' => $id]
        );
        return $filas[0] ?? null;
    }

    public function contarAdministradoresActivos(): int
    {
        return $this->contar("rol = 'administrador' AND activo = 1");
    }

    /** Administradores que reciben los avisos de reservas: su correo es el de su cuenta. */
    public function administradoresActivos(): array
    {
        return $this->consultar(
            "SELECT id, nombre, email FROM usuarios WHERE rol = 'administrador' AND activo = 1 ORDER BY id"
        );
    }

    /** Emprendimiento del que esta cuenta es titular, o null. */
    public function expositorDe(int $usuarioId): ?string
    {
        $valor = $this->valor('SELECT emprendimiento FROM expositores WHERE usuario_id = :id', ['id' => $usuarioId]);
        return $valor === false ? null : (string) $valor;
    }

    /** Cuentas de rol "usuario" que todavía no tienen expositor (más la actual). */
    public function opcionesSinExpositor(?int $incluir = null): array
    {
        $filas = $this->consultar(
            "SELECT u.id, u.nombre, u.email, u.activo
             FROM usuarios u
             LEFT JOIN expositores e ON e.usuario_id = u.id
             WHERE u.rol = 'usuario' AND (e.id IS NULL OR u.id = :incluir)
             ORDER BY u.nombre",
            ['incluir' => $incluir ?? 0]
        );
        $opciones = [];
        foreach ($filas as $f) {
            $opciones[$f['id']] = $f['nombre'] . ' <' . $f['email'] . '>' . ((int) $f['activo'] === 1 ? '' : ' (inactiva)');
        }
        return $opciones;
    }
}
