<?php
declare(strict_types=1);

/**
 * Ingreso, salida, registro de expositores y creación del primer administrador.
 * La base no trae una cuenta con contraseña predeterminada: mientras no
 * exista un administrador activo, el sistema muestra «Crear administrador».
 *
 * Un solo formulario de ingreso: correo + contraseña. El sistema detecta
 * automáticamente si la cuenta es administrador o expositor.
 */
final class AuthController
{
    private const MAX_INTENTOS = 5;
    private const SEGUNDOS_BLOQUEO = 60;

    public function login(): void
    {
        $usuarios = new Usuario();
        if ($usuarios->contarAdministradoresActivos() === 0) {
            redirigir('instalar');
        }
        if (Auth::usuario() !== null) {
            $actual = Auth::usuario();
            if ($usuarios->buscarAdministradorActivo((int) $actual['id']) !== null) {
                redirigir('panel');
            }
            if ($usuarios->buscarUsuarioActivo((int) $actual['id']) !== null
                && (new Expositor())->buscarPorUsuario((int) $actual['id']) !== null) {
                redirigir('mi-espacio');
            }
            Auth::cerrar();
        }

        $error = null;
        $email = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verificar_csrf('login');
            $email = minusculas(pedir_post('email'));
            $clave = pedir_post_crudo('password');
            $bloqueo = (int) ($_SESSION['bloqueo_hasta'] ?? 0);

            if ($bloqueo > time()) {
                $error = 'Demasiados intentos fallidos. Espere ' . ($bloqueo - time()) . ' segundos e inténtelo de nuevo.';
            } else {
                $u = $email !== '' ? $usuarios->buscarPorEmail($email) : null;
                $valido = $u !== null
                    && (int) $u['activo'] === 1
                    && in_array($u['rol'], ['administrador', 'usuario'], true)
                    && password_verify($clave, (string) $u['password_hash']);
                if ($valido && $u['rol'] === 'usuario') {
                    $valido = (new Expositor())->buscarPorUsuario((int) $u['id']) !== null;
                }

                if ($valido) {
                    if (password_needs_rehash((string) $u['password_hash'], PASSWORD_DEFAULT)) {
                        $usuarios->actualizar((int) $u['id'], ['password_hash' => password_hash($clave, PASSWORD_DEFAULT)]);
                    }
                    Auth::iniciar($u);
                    flash('ok', 'Bienvenido/a, ' . $u['nombre'] . '.');
                    redirigir($u['rol'] === 'administrador' ? 'panel' : 'mi-espacio');
                }

                $intentos = (int) ($_SESSION['intentos'] ?? 0) + 1;
                $_SESSION['intentos'] = $intentos;
                if ($intentos >= self::MAX_INTENTOS) {
                    $_SESSION['intentos'] = 0;
                    $_SESSION['bloqueo_hasta'] = time() + self::SEGUNDOS_BLOQUEO;
                }
                $error = 'Correo o contraseña incorrectos.';
            }
        }
        vista('auth/login', ['error' => $error, 'email' => $email]);
    }

    public function registro(): void
    {
        $usuarios = new Usuario();
        if ($usuarios->contarAdministradoresActivos() === 0) {
            redirigir('instalar');
        }
        if (Auth::usuario() !== null) {
            $actual = Auth::usuario();
            redirigir($usuarios->buscarAdministradorActivo((int) $actual['id']) !== null ? 'panel' : 'mi-espacio');
        }

        $errores = [];
        $valores = ['nombre' => '', 'email' => '', 'emprendimiento' => '', 'telefono' => ''];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verificar_csrf('registro');
            foreach ($valores as $campo => $_) {
                $valores[$campo] = pedir_post($campo);
            }
            $clave = pedir_post_crudo('password');

            if ($valores['nombre'] === '' || longitud($valores['nombre']) > 100) {
                $errores['nombre'] = 'Escriba su nombre (máximo 100 caracteres).';
            }
            if (filter_var($valores['email'], FILTER_VALIDATE_EMAIL) === false || longitud($valores['email']) > 150) {
                $errores['email'] = 'Escriba un correo válido.';
            } elseif ($usuarios->existeValor('email', minusculas($valores['email']))) {
                $errores['email'] = 'Ese correo ya tiene una cuenta. Ingrese con sus datos.';
            }
            if ($valores['emprendimiento'] === '' || longitud($valores['emprendimiento']) > 120) {
                $errores['emprendimiento'] = 'Escriba el nombre del emprendimiento (máximo 120 caracteres).';
            }
            if ($valores['telefono'] === '' || longitud($valores['telefono']) > 15
                || preg_match('/^[0-9+()\-\s]+$/', $valores['telefono']) !== 1) {
                $errores['telefono'] = 'Escriba un teléfono válido (máximo 15 caracteres).';
            }
            if (strlen($clave) < 8 || strlen($clave) > 72) {
                $errores['password'] = 'La contraseña debe tener entre 8 y 72 caracteres.';
            }

            if ($errores === []) {
                $db = Database::conectar();
                $db->beginTransaction();
                try {
                    $usuarioId = $usuarios->crear([
                        'nombre' => $valores['nombre'],
                        'email' => minusculas($valores['email']),
                        'password_hash' => password_hash($clave, PASSWORD_DEFAULT),
                        'rol' => 'usuario',
                        'activo' => 1,
                    ]);
                    (new Expositor())->crear([
                        'usuario_id' => $usuarioId,
                        'institucion_id' => null,
                        'emprendimiento' => $valores['emprendimiento'],
                        'telefono' => $valores['telefono'],
                    ]);
                    $db->commit();
                    Auth::iniciar([
                        'id' => $usuarioId,
                        'nombre' => $valores['nombre'],
                        'email' => minusculas($valores['email']),
                        'rol' => 'usuario',
                    ]);
                    flash('ok', 'Cuenta creada. Ya puede solicitar un puesto para su emprendimiento.');
                    redirigir('mi-espacio');
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $e;
                }
            }
        }
        vista('auth/registro', ['errores' => $errores, 'valores' => $valores]);
    }

    public function instalar(): void
    {
        $usuarios = new Usuario();
        if ($usuarios->contarAdministradoresActivos() > 0) {
            redirigir('inicio');
        }

        $errores = [];
        $valores = ['nombre' => '', 'email' => ''];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verificar_csrf('instalar');
            $valores['nombre'] = pedir_post('nombre');
            $valores['email'] = minusculas(pedir_post('email'));
            $clave = pedir_post_crudo('password');
            $confirmacion = pedir_post_crudo('password2');

            if ($valores['nombre'] === '') {
                $errores['nombre'] = 'Escriba su nombre.';
            } elseif (longitud($valores['nombre']) > 100) {
                $errores['nombre'] = 'Máximo 100 caracteres.';
            }
            if (filter_var($valores['email'], FILTER_VALIDATE_EMAIL) === false || longitud($valores['email']) > 150) {
                $errores['email'] = 'Escriba un correo válido.';
            } elseif ($usuarios->existeValor('email', $valores['email'])) {
                $errores['email'] = 'Ese correo ya está registrado. Use otro.';
            }
            if (strlen($clave) < 8 || strlen($clave) > 72) {
                $errores['password'] = 'La contraseña debe tener entre 8 y 72 caracteres.';
            } elseif ($clave !== $confirmacion) {
                $errores['password2'] = 'Las contraseñas no coinciden.';
            }

            if ($errores === []) {
                $id = $usuarios->crear([
                    'nombre'        => $valores['nombre'],
                    'email'         => $valores['email'],
                    'password_hash' => password_hash($clave, PASSWORD_DEFAULT),
                    'rol'           => 'administrador',
                    'activo'        => 1,
                ]);
                Auth::iniciar(['id' => $id, 'nombre' => $valores['nombre'], 'email' => $valores['email']]);
                flash('ok', 'Cuenta de administrador creada. Ya puede cargar los datos del sistema.');
                redirigir('panel');
            }
        }
        vista('auth/instalar', ['errores' => $errores, 'valores' => $valores]);
    }

    public function salir(): void
    {
        exigir_post();
        verificar_csrf('inicio');
        Auth::cerrar();
        flash('ok', 'Sesión cerrada.');
        redirigir('login');
    }
}
