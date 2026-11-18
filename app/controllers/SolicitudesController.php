<?php
declare(strict_types=1);

final class SolicitudesController extends CrudController
{
    protected string $ruta = 'solicitudes';
    protected string $titulo = 'Solicitudes';
    protected string $singular = 'solicitud';
    protected string $textoNuevo = 'Nueva solicitud';
    protected string $descripcion = 'Pedidos de espacio de los expositores, en orden de inscripción. Un expositor solo puede tener una solicitud por feria.';

    private const ESTADOS = ['pendiente' => 'Pendiente', 'espera' => 'En espera (sin espacio disponible)', 'cancelada' => 'Cancelada'];

    /** Detalle (con puesto) de la solicitud que se está editando. */
    private ?array $detalle = null;

    protected function crearModelo(): Modelo
    {
        return new Solicitud();
    }

    private function detalle(array $fila): ?array
    {
        if ($this->detalle === null || (int) $this->detalle['id'] !== (int) $fila['id']) {
            $this->detalle = (new Solicitud())->buscarDetalle((int) $fila['id']);
        }
        return $this->detalle;
    }

    protected function campos(?array $fila): array
    {
        $estados = self::ESTADOS;
        if ($fila !== null) {
            $det = $this->detalle($fila);
            // "Asignada" solo se muestra si ya tiene puesto: se asigna desde Asignaciones.
            if (!empty($det['asignacion_id']) || $fila['estado'] === 'asignada') {
                $estados = ['asignada' => 'Asignada (tiene puesto)'] + $estados;
            }
        }

        return [
            'feria_id'      => ['etiqueta' => 'Feria', 'tipo' => 'select', 'requerido' => true, 'opciones' => (new Feria())->opciones()],
            'expositor_id'  => ['etiqueta' => 'Expositor', 'tipo' => 'select', 'requerido' => true, 'opciones' => (new Expositor())->opciones()],
            'categoria_id'  => ['etiqueta' => 'Categoría de sus productos', 'tipo' => 'select', 'requerido' => true,
                                'opciones' => (new Categoria())->opcionesActivas($fila !== null ? (int) $fila['categoria_id'] : null)],
            'tipo_carpa_id' => ['etiqueta' => 'Tipo de carpa', 'tipo' => 'select', 'requerido' => true,
                                'opciones' => (new TipoCarpa())->opcionesActivas($fila !== null ? (int) $fila['tipo_carpa_id'] : null)],
            'estado'        => ['etiqueta' => 'Estado', 'tipo' => 'select', 'requerido' => true, 'defecto' => 'pendiente',
                                'opciones' => $estados,
                                'ayuda' => 'El estado «Asignada» se pone solo al registrar el puesto en Asignaciones.'],
        ];
    }

    protected function avisoFormulario(?array $fila): ?string
    {
        if ($fila === null) {
            return 'La fecha y hora de inscripción se registran automáticamente al guardar.';
        }
        $det = $this->detalle($fila);
        $texto = 'Inscrita el ' . fecha_hora((string) $fila['creada_en']) . '.';
        if (!empty($det['asignacion_id'])) {
            $texto .= ' Tiene asignado el puesto ' . $det['codigo'] . '.';
        }
        return $texto;
    }

    protected function columnas(): array
    {
        return [
            'id'             => 'N.º',
            'feria'          => 'Feria',
            'emprendimiento' => 'Expositor',
            'categoria'      => 'Categoría',
            'carpa_texto'    => 'Carpa',
            'creada_en'      => ['Inscripción', 'fechahora'],
            'estado'         => ['Estado', 'estado'],
            'codigo'         => ['Puesto', 'codigo'],
        ];
    }

    protected function filtros(): array
    {
        return [
            'feria_id' => ['etiqueta' => 'Feria', 'todos' => 'Todas las ferias', 'opciones' => (new Feria())->opciones()],
            'estado'   => ['etiqueta' => 'Estado', 'todos' => 'Todos los estados', 'opciones' => [
                'pendiente' => 'Pendiente', 'espera' => 'En espera', 'asignada' => 'Asignada', 'cancelada' => 'Cancelada',
            ]],
        ];
    }

    protected function prepararFilas(array $filas): array
    {
        foreach ($filas as &$f) {
            $f['carpa_texto'] = $f['carpa'] . ' (' . medida($f['carpa_ancho']) . ' × ' . medida($f['carpa_largo']) . ' m)';
        }
        unset($f);
        return $filas;
    }

    protected function accionesExtra(array $fila): array
    {
        if (!empty($fila['asignacion_id'])) {
            return [['texto' => 'Ver plano', 'url' => url('areas/plano', ['id' => $fila['area_id']])]];
        }
        return [];
    }

    protected function nombreRegistro(array $fila): string
    {
        return 'Solicitud N.º ' . $fila['id'];
    }

    protected function requisitos(): array
    {
        $faltan = [];
        if ((new Feria())->contar() === 0) {
            $faltan[] = ['Primero registre una feria.', 'ferias/nuevo', 'Nueva feria'];
        }
        if ((new Expositor())->contar() === 0) {
            $faltan[] = ['Todavía no hay expositores.', 'expositores/nuevo', 'Nuevo expositor'];
        }
        if ((new TipoCarpa())->contar('activo = 1') === 0) {
            $faltan[] = ['No hay tipos de carpa activos.', 'tipos_carpa/nuevo', 'Nuevo tipo de carpa'];
        }
        if ((new Categoria())->contar('activo = 1') === 0) {
            $faltan[] = ['No hay categorías activas.', 'categorias/nuevo', 'Nueva categoría'];
        }
        return $faltan;
    }

    protected function validarExtra(array &$datos, ?array $fila, array &$errores): void
    {
        $excluir = $fila !== null ? (int) $fila['id'] : null;
        if ((new Solicitud())->existeEnFeria((int) $datos['feria_id'], (int) $datos['expositor_id'], $excluir)) {
            $errores['expositor_id'] = 'Este expositor ya tiene una solicitud en esa feria.';
        }
        if ($fila === null) {
            return;
        }

        $det = $this->detalle($fila);
        if (!empty($det['asignacion_id'])) {
            $bloqueados = ['feria_id' => 'la feria', 'categoria_id' => 'la categoría', 'tipo_carpa_id' => 'el tipo de carpa'];
            foreach ($bloqueados as $campo => $texto) {
                if ((int) $datos[$campo] !== (int) $fila[$campo]) {
                    $errores[$campo] = 'Tiene asignado el puesto ' . $det['codigo'] . ': elimine la asignación antes de cambiar ' . $texto . '.';
                }
            }
            if ($datos['estado'] !== 'asignada') {
                $errores['estado'] = 'Tiene asignado el puesto ' . $det['codigo'] . '. Para cambiar el estado, elimine primero la asignación.';
            }
        } elseif ($datos['estado'] === 'asignada') {
            $errores['estado'] = 'Esta solicitud no tiene puesto. Regístrelo en Asignaciones y el estado cambiará solo.';
        }
    }

    protected function motivoNoEliminar(array $fila): ?string
    {
        $det = $this->detalle($fila);
        return !empty($det['asignacion_id'])
            ? 'Tiene asignado el puesto ' . $det['codigo'] . '. Elimine primero la asignación.'
            : null;
    }

    public function confirmar(): void
    {
        Auth::requerirAdmin();
        exigir_post();
        verificar_csrf('admin/reservas');
        $id = pedir_id(pedir_post('id'));
        if ($id === null) {
            flash('error', 'ID de solicitud inválido.');
            redirigir('admin/reservas');
        }
        try {
            $resultado = (new SolicitudService())->confirmar($id);
            $enviado = (new NotificacionService())->reservaConfirmada($resultado['datos']);
            flash($enviado ? 'ok' : 'aviso', $enviado
                ? 'Reserva confirmada y expositor notificado.'
                : 'Reserva confirmada. No se pudo enviar el correo; revise logs/correo.log.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('admin/reservas');
    }

    public function rechazar(): void
    {
        Auth::requerirAdmin();
        exigir_post();
        verificar_csrf('admin/reservas');
        $id = pedir_id(pedir_post('id'));
        $motivo = pedir_post('motivo');
        if ($id === null) {
            flash('error', 'ID de solicitud inválido.');
            redirigir('admin/reservas');
        }
        try {
            $resultado = (new SolicitudService())->rechazar($id, $motivo);
            $enviado = (new NotificacionService())->reservaRechazada($resultado['datos']);
            flash($enviado ? 'ok' : 'aviso', $enviado
                ? 'Reserva rechazada, carpa liberada y expositor notificado.'
                : 'Reserva rechazada y carpa liberada. No se pudo enviar el correo; revise logs/correo.log.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('admin/reservas');
    }

    public function reenviarCorreo(): void
    {
        Auth::requerirAdmin();
        exigir_post();
        verificar_csrf('admin/reservas');
        $id = pedir_id(pedir_post('id'));
        if ($id === null) {
            flash('error', 'ID de solicitud inválido.');
            redirigir('admin/reservas');
        }
        try {
            $solicitud = (new Solicitud())->buscarDetalle($id);
            if ($solicitud === null) {
                throw new DomainException('La solicitud no existe.');
            }
            $enviado = (new NotificacionService())->reenviar($solicitud);
            flash($enviado ? 'ok' : 'aviso', $enviado
                ? 'Correo reenviado al expositor.'
                : 'No se pudo reenviar el correo. Revise logs/correo.log.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('admin/reservas');
    }
}
