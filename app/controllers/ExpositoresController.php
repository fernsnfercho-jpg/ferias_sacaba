<?php
declare(strict_types=1);

final class ExpositoresController extends CrudController
{
    protected string $ruta = 'expositores';
    protected string $titulo = 'Expositores';
    protected string $singular = 'expositor';
    protected string $textoNuevo = 'Nuevo expositor';
    protected string $descripcion = 'Emprendimientos que participan en las ferias. Cada uno tiene una cuenta de usuario titular.';

    protected function crearModelo(): Modelo
    {
        return new Expositor();
    }

    protected function campos(?array $fila): array
    {
        $cuentas = (new Usuario())->opcionesSinExpositor($fila !== null ? (int) $fila['usuario_id'] : null);
        if ($fila === null) {
            // Al crear, se puede registrar la cuenta del titular en el mismo formulario.
            $cuentas = ['nuevo' => '➕ Crear una cuenta nueva para el titular'] + $cuentas;
        }
        $institucionActual = $fila !== null && $fila['institucion_id'] !== null ? (int) $fila['institucion_id'] : null;

        return [
            'emprendimiento' => ['etiqueta' => 'Nombre del emprendimiento', 'requerido' => true, 'max' => 120,
                                 'ayuda' => 'Ej.: Sabores del Valle.'],
            'telefono'       => ['etiqueta' => 'Teléfono / WhatsApp', 'tipo' => 'telefono', 'requerido' => true, 'max' => 15,
                                 'ayuda' => 'Ej.: 71234567 o +59171234567. Se usará para avisos por WhatsApp.'],
            'institucion_id' => ['etiqueta' => 'Institución o asociación', 'tipo' => 'select', 'vacio' => '— Sin institución —',
                                 'opciones' => (new Institucion())->opcionesActivas($institucionActual)],
            'usuario_id'     => ['etiqueta' => 'Cuenta del titular', 'tipo' => 'select', 'requerido' => true,
                                 'opciones' => $cuentas, 'defecto' => $fila === null ? 'nuevo' : '',
                                 'ayuda' => 'Solo aparecen cuentas de rol Usuario que aún no tienen expositor.'],
            'nuevo_nombre'   => ['etiqueta' => 'Nombre del titular', 'requerido' => true, 'max' => 100,
                                 'virtual' => true, 'soloCrear' => true, 'mostrarSi' => ['usuario_id', 'nuevo']],
            'nuevo_email'    => ['etiqueta' => 'Correo del titular', 'tipo' => 'email', 'requerido' => true, 'max' => 150,
                                 'virtual' => true, 'soloCrear' => true, 'mostrarSi' => ['usuario_id', 'nuevo']],
            'nuevo_password' => ['etiqueta' => 'Contraseña del titular', 'tipo' => 'password', 'requerido' => true,
                                 'virtual' => true, 'soloCrear' => true, 'mostrarSi' => ['usuario_id', 'nuevo'],
                                 'ayuda' => 'Mínimo 8 caracteres. El expositor la usará para consultar su puesto.'],
        ];
    }

    protected function columnas(): array
    {
        return [
            'emprendimiento' => 'Emprendimiento',
            'titular'        => 'Titular',
            'email'          => 'Correo',
            'telefono'       => 'Teléfono',
            'institucion'    => 'Institución',
            'solicitudes'    => ['Solicitudes', 'numero'],
        ];
    }

    protected function filtros(): array
    {
        return ['institucion_id' => ['etiqueta' => 'Institución', 'todos' => 'Todas las instituciones',
                                     'opciones' => (new Institucion())->opcionesTodas()]];
    }

    protected function nombreRegistro(array $fila): string
    {
        return (string) ($fila['emprendimiento'] ?? ('Expositor N.º ' . $fila['id']));
    }

    protected function validarExtra(array &$datos, ?array $fila, array &$errores): void
    {
        if (($datos['usuario_id'] ?? '') === 'nuevo' && isset($datos['nuevo_email'])
            && (new Usuario())->existeValor('email', $datos['nuevo_email'])) {
            $errores['nuevo_email'] = 'Ya existe una cuenta con este correo. Elíjala en «Cuenta del titular» si todavía no tiene expositor.';
        }
    }

    protected function guardarDatos(array $datos, ?array $fila): int
    {
        $columnas = [
            'emprendimiento' => $datos['emprendimiento'],
            'telefono'       => $datos['telefono'],
            'institucion_id' => $datos['institucion_id'] ?? null,
            'usuario_id'     => $datos['usuario_id'],
        ];

        if ($fila === null && $datos['usuario_id'] === 'nuevo') {
            // Cuenta + expositor en una sola transacción: se guardan los dos o ninguno.
            $db = $this->modelo()->db();
            $db->beginTransaction();
            try {
                $columnas['usuario_id'] = (new Usuario())->crear([
                    'nombre'        => $datos['nuevo_nombre'],
                    'email'         => $datos['nuevo_email'],
                    'password_hash' => password_hash($datos['nuevo_password'], PASSWORD_DEFAULT),
                    'rol'           => 'usuario',
                    'activo'        => 1,
                ]);
                $id = $this->modelo()->crear($columnas);
                $db->commit();
                return $id;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }
        return parent::guardarDatos($columnas, $fila);
    }

    protected function motivoNoEliminar(array $fila): ?string
    {
        $n = (new Expositor())->contarSolicitudes((int) $fila['id']);
        return $n > 0
            ? 'Este expositor tiene ' . $n . ' solicitud(es). Elimínelas primero. La cuenta del titular se conserva en Usuarios.'
            : null;
    }
}
