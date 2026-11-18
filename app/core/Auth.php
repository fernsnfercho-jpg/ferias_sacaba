<?php
declare(strict_types=1);

/**
 * Manejo de sesiones para administradores y expositores.
 */
final class Auth
{
    /** Datos de la cuenta conectada o null si no hay sesión. */
    public static function usuario(): ?array
    {
        $u = $_SESSION['usuario'] ?? null;
        return is_array($u) ? $u : null;
    }

    public static function id(): int
    {
        return (int) (self::usuario()['id'] ?? 0);
    }

    /** Inicia la sesión con un nuevo identificador (evita robo de sesión). */
    public static function iniciar(array $usuario): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'     => (int) $usuario['id'],
            'nombre' => (string) $usuario['nombre'],
            'email'  => (string) $usuario['email'],
            'rol'    => (string) ($usuario['rol'] ?? 'administrador'),
        ];
        unset($_SESSION['intentos'], $_SESSION['bloqueo_hasta']);
    }

    public static function cerrar(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /**
     * Exige un administrador activo. Se vuelve a consultar la base en cada
     * página: si la cuenta fue desactivada o cambió de rol, se cierra la sesión.
     */
    public static function requerirAdmin(): void
    {
        $u = self::usuario();
        if ($u === null) {
            redirigir('login');
        }
        $vigente = (new Usuario())->buscarAdministradorActivo((int) $u['id']);
        if ($vigente === null) {
            if ((new Usuario())->buscarUsuarioActivo((int) $u['id']) !== null
                && (new Expositor())->buscarPorUsuario((int) $u['id']) !== null) {
                redirigir('mi-espacio');
            }
            self::cerrar();
            flash('error', 'Su sesión terminó porque la cuenta ya no es un administrador activo.');
            redirigir('login');
        }
        $_SESSION['usuario']['nombre'] = (string) $vigente['nombre'];
        $_SESSION['usuario']['email'] = (string) $vigente['email'];
        $_SESSION['usuario']['rol'] = 'administrador';
    }

    /** Exige una cuenta activa asociada a un perfil de expositor. */
    public static function requerirExpositor(): array
    {
        $u = self::usuario();
        if ($u === null) {
            redirigir('login');
        }
        if ((new Usuario())->buscarAdministradorActivo((int) $u['id']) !== null) {
            redirigir('panel');
        }
        $vigente = (new Usuario())->buscarUsuarioActivo((int) $u['id']);
        $expositor = $vigente !== null ? (new Expositor())->buscarPorUsuario((int) $u['id']) : null;
        if ($vigente === null || $expositor === null) {
            self::cerrar();
            flash('error', 'La cuenta no tiene un perfil de expositor activo. Contacte al administrador.');
            redirigir('login');
        }
        $_SESSION['usuario']['nombre'] = (string) $vigente['nombre'];
        $_SESSION['usuario']['email'] = (string) $vigente['email'];
        $_SESSION['usuario']['rol'] = 'usuario';
        return $expositor;
    }
}
