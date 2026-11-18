<?php
declare(strict_types=1);

final class ExpositorPortalController
{
    public function index(): void
    {
        $expositor = Auth::requerirExpositor();
        $ferias = (new Feria())->abiertasParaInscripcion();
        $categorias = [];
        $plano = new UbicacionService();

        foreach ($ferias as $feria) {
            foreach ((new Categoria())->deFeria((int) $feria['id']) as $categoria) {
                $categorias[] = [
                    'feria_id' => (int) $feria['id'],
                    'feria' => $feria,
                    'id' => (int) $categoria['id'],
                    'nombre' => (string) $categoria['nombre'],
                    'plano' => $plano->planoDeFeria((int) $feria['id'], (int) $categoria['id']),
                ];
            }
        }

        vista('publico/mi_espacio', [
            'expositor' => $expositor,
            'ferias' => $ferias,
            'categorias' => $categorias,
            'solicitudes' => (new Solicitud())->deExpositor((int) $expositor['id']),
            'errores' => [],
        ]);
    }

    public function reservar(): void
    {
        Auth::requerirExpositor();
        redirigir('mi-espacio');
    }

    public function solicitar(): void
    {
        $expositor = Auth::requerirExpositor();
        exigir_post();
        verificar_csrf('mi-espacio');

        $feriaId = pedir_id(pedir_post('feria_id'));
        $categoriaId = pedir_id(pedir_post('categoria_id'));
        $codigo = strtoupper(pedir_post('codigo'));
        if ($feriaId === null || $categoriaId === null || $codigo === '') {
            flash('error', 'Elija una feria, un rubro y una carpa disponible.');
            redirigir('mi-espacio');
        }

        try {
            $resultado = (new SolicitudService())->reservarCarpa(
                $feriaId,
                (int) $expositor['id'],
                $categoriaId,
                $codigo
            );
            $reserva = (new Solicitud())->buscarDetalle((int) $resultado['solicitud_id']);
            if ($reserva === null) {
                throw new RuntimeException('No se pudo recuperar la reserva registrada.');
            }

            if (!(new NotificacionService())->reservaCreada($reserva)) {
                flash('aviso', 'La reserva quedó pendiente. No se pudo enviar uno de los correos; la administración puede revisarla desde el panel.');
            } else {
                flash('ok', 'Reserva recibida. La carpa ' . $resultado['codigo'] . ' quedó pendiente de aprobación.');
            }
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('mi-espacio');
    }

    public function misReservas(): void
    {
        Auth::requerirExpositor();
        redirigir('mi-espacio');
    }

    public function cancelarReserva(): void
    {
        $expositor = Auth::requerirExpositor();
        exigir_post();
        verificar_csrf('mi-espacio');
        $id = pedir_id(pedir_post('id'));
        if ($id === null) {
            flash('error', 'La reserva indicada no es válida.');
            redirigir('mi-espacio');
        }

        try {
            $resultado = (new SolicitudService())->cancelar($id, (int) $expositor['id']);
            $datos = $resultado['datos'];
            (new NotificacionService())->reservaCancelada($datos);
            flash('ok', 'Reserva cancelada. La carpa ' . ($datos['codigo_liberado'] ?? '') . ' volvió a estar disponible.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('mi-espacio');
    }

    public function miPerfil(): void
    {
        Auth::requerirExpositor();
        redirigir('mi-espacio');
    }
}
