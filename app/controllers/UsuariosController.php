<?php
declare(strict_types=1);

final class UsuariosController extends CrudController
{
    protected string $ruta = 'usuarios';
    protected string $titulo = 'Usuarios';
    protected string $singular = 'usuario';
    protected string $textoNuevo = 'Nuevo usuario';
    protected string $descripcion = 'Cuentas del sistema. Los administradores entran a este panel; las cuentas de rol Usuario son titulares de un expositor.';

    protected function crearModelo(): Modelo
    {
        return new Usuario();
    }

    protected function campos(?array $fila): array
    {
        return [
            'nombre'   => ['etiqueta' => 'Nombre completo', 'requerido' => true, 'max' => 100],
            'email'    => ['etiqueta' => 'Correo electrónico', 'tipo' => 'email', 'requerido' => true, 'max' => 150, 'unico' => true],
            'password' => [
                'etiqueta'  => $fila === null ? 'Contraseña' : 'Nueva contraseña',
                'tipo'      => 'password',
                'requerido' => $fila === null,
                'virtual'   => true,
                'ayuda'     => $fila === null ? 'Mínimo 8 caracteres. Se guarda cifrada.' : 'Déjela vacía para mantener la contraseña actual.',
            ],
            'rol'      => ['etiqueta' => 'Rol', 'tipo' => 'select', 'requerido' => true, 'defecto' => 'usuario',
                           'opciones' => ['usuario' => 'Usuario (titular de un expositor)', 'administrador' => 'Administrador (entra a este panel)']],
            'activo'   => ['etiqueta' => 'Cuenta activa', 'tipo' => 'check', 'defecto' => 1],
        ];
    }

    protected function columnas(): array
    {
        return [
            'nombre'         => 'Nombre',
            'email'          => 'Correo',
            'rol'            => ['Rol', 'estado'],
            'emprendimiento' => 'Expositor',
            'activo'         => ['Estado', 'activo'],
        ];
    }

    protected function filtros(): array
    {
        return ['rol' => ['etiqueta' => 'Rol', 'todos' => 'Todos los roles',
                          'opciones' => ['administrador' => 'Administradores', 'usuario' => 'Usuarios']]];
    }

    protected function validarExtra(array &$datos, ?array $fila, array &$errores): void
    {
        if ($fila !== null) {
            $id = (int) $fila['id'];
            // Protecciones para no quedarse sin acceso al panel.
            if ($id === Auth::id()) {
                if ($datos['rol'] !== 'administrador') {
                    $errores['rol'] = 'No puede quitarse a sí mismo el rol de administrador.';
                }
                if ((int) $datos['activo'] !== 1) {
                    $errores['activo'] = 'No puede desactivar su propia cuenta.';
                }
            }
            // El titular de un expositor debe seguir siendo de rol Usuario.
            $expositor = (new Usuario())->expositorDe($id);
            if ($expositor !== null && $datos['rol'] === 'administrador') {
                $errores['rol'] = 'Esta cuenta es titular del expositor «' . $expositor . '»; debe tener rol Usuario.';
            }
        }

        // La contraseña se guarda cifrada en password_hash (nunca en texto plano).
        if (!empty($datos['password'])) {
            $datos['password_hash'] = password_hash($datos['password'], PASSWORD_DEFAULT);
        }
        unset($datos['password']);
    }

    protected function motivoNoEliminar(array $fila): ?string
    {
        if ((int) $fila['id'] === Auth::id()) {
            return 'No puede eliminar su propia cuenta.';
        }
        $expositor = (new Usuario())->expositorDe((int) $fila['id']);
        if ($expositor !== null) {
            return 'Esta cuenta es titular del expositor «' . $expositor . '». Elimine primero el expositor, o desactive la cuenta.';
        }
        return null;
    }
}
