<?php
declare(strict_types=1);

final class FeriasController extends CrudController
{
    protected string $ruta = 'ferias';
    protected string $titulo = 'Ferias';
    protected string $singular = 'feria';
    protected string $textoNuevo = 'Nueva feria';
    protected string $descripcion = 'Ferias productivas de Sacaba: fecha, lugar, fotografía y estado de inscripción.';
    private ?array $fotoValidada = null;

    private const ESTADOS = ['borrador' => 'Borrador', 'abierta' => 'Abierta (recibe solicitudes)', 'cerrada' => 'Cerrada'];

    protected function crearModelo(): Modelo
    {
        return new Feria();
    }

    protected function campos(?array $fila): array
    {
        return [
            'nombre'   => ['etiqueta' => 'Nombre de la feria', 'requerido' => true, 'max' => 120,
                           'sugerencias' => [
                               'Feria Productiva de Sacaba',
                               'Feria Gastronómica de Sacaba',
                               'Feria Artesanal y Productiva',
                               'Feria de Productores Agropecuarios',
                               'Feria de Plantas y Flores',
                           ],
                           'ayuda' => 'Ideas de nombre; confirma la denominación oficial, fecha y plaza con la organización antes de publicar.'],
            'fecha'    => ['etiqueta' => 'Fecha', 'tipo' => 'fecha', 'requerido' => true, 'defecto' => date('Y-m-d')],
            'lugar'    => ['etiqueta' => 'Lugar', 'requerido' => true, 'max' => 180,
                           'ayuda' => 'Dirección o referencia. Ej.: Plaza principal 6 de Agosto.'],
            'foto'     => ['etiqueta' => 'Fotografía de la feria', 'tipo' => 'archivo',
                           'ayuda' => $fila !== null && !empty($fila['foto'])
                               ? 'Hay una fotografía guardada. Seleccione otra solo para reemplazarla.'
                               : 'Opcional: JPG, PNG o WebP; mínimo 600 × 400 px y hasta 8 MB.'],
            'estado'   => ['etiqueta' => 'Estado', 'tipo' => 'select', 'requerido' => true, 'defecto' => 'borrador',
                           'opciones' => self::ESTADOS],
        ];
    }

    protected function validarExtra(array &$datos, ?array $fila, array &$errores): void
    {
        $this->fotoValidada = null;
        $archivo = $_FILES['foto'] ?? null;
        if (!is_array($archivo) || (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        $error = null;
        $this->fotoValidada = (new FotoFeriaService())->validar($archivo, $error);
        if ($this->fotoValidada === null) {
            $errores['foto'] = $error ?? 'No se pudo validar la fotografía.';
        }
    }

    protected function guardarDatos(array $datos, ?array $fila): int
    {
        $rutaNueva = null;
        $rutaAnterior = (string) ($fila['foto'] ?? '');
        if ($this->fotoValidada !== null) {
            $rutaNueva = (new FotoFeriaService())->guardar($this->fotoValidada);
            $datos['foto'] = $rutaNueva;
        } else {
            $datos['foto'] = $rutaAnterior !== '' ? $rutaAnterior : null;
        }

        try {
            $id = parent::guardarDatos($datos, $fila);
        } catch (Throwable $e) {
            if ($rutaNueva !== null) {
                (new FotoFeriaService())->borrar($rutaNueva);
            }
            throw $e;
        }
        if ($rutaNueva !== null && $rutaAnterior !== '') {
            (new FotoFeriaService())->borrar($rutaAnterior);
        }
        return $id;
    }

    protected function columnas(): array
    {
        return [
            'nombre'      => 'Feria',
            'fecha'       => ['Fecha', 'fecha'],
            'lugar'       => 'Lugar',
            'estado'      => ['Estado', 'estado'],
            'areas'       => ['Áreas', 'numero'],
            'solicitudes' => ['Solicitudes', 'numero'],
            'asignados'   => ['Puestos', 'numero'],
        ];
    }

    protected function filtros(): array
    {
        return ['estado' => ['etiqueta' => 'Estado', 'todos' => 'Todos los estados',
                             'opciones' => ['borrador' => 'Borrador', 'abierta' => 'Abierta', 'cerrada' => 'Cerrada']]];
    }

    protected function accionesExtra(array $fila): array
    {
        return [
            ['texto' => 'Áreas', 'url' => url('areas', ['feria_id' => $fila['id']])],
            ['texto' => 'Reservas', 'url' => url('admin/reservas', ['feria_id' => $fila['id']])],
            [
                'texto'   => 'Mapa',
                'url'     => enlace_mapa((string) $fila['lugar']),
                'externo' => true,
            ],
        ];
    }

    protected function motivoNoEliminar(array $fila): ?string
    {
        $d = (new Feria())->dependencias((int) $fila['id']);
        if ($d['areas'] > 0 || $d['solicitudes'] > 0) {
            return sprintf(
                'La feria tiene %d área(s) y %d solicitud(es). Elimínelas primero, o cambie su estado a «Cerrada».',
                $d['areas'],
                $d['solicitudes']
            );
        }
        return null;
    }
}
