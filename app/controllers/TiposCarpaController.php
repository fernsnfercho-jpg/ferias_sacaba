<?php
declare(strict_types=1);

final class TiposCarpaController extends CrudController
{
    protected string $ruta = 'tipos_carpa';
    protected string $titulo = 'Tipos de carpa';
    protected string $singular = 'tipo de carpa';
    protected string $textoNuevo = 'Nuevo tipo de carpa';
    protected string $descripcion = 'Tamaños de carpa que puede pedir un expositor. Las medidas se usan para ubicar el puesto en el área.';

    protected function crearModelo(): Modelo
    {
        return new TipoCarpa();
    }

    protected function campos(?array $fila): array
    {
        return [
            'nombre'  => ['etiqueta' => 'Nombre', 'requerido' => true, 'max' => 80, 'ayuda' => 'Ej.: Pequeña, Mediana, Grande.'],
            'ancho_m' => ['etiqueta' => 'Ancho (m)', 'tipo' => 'decimal', 'requerido' => true, 'mayorQue' => 0, 'maximo' => 9999.99,
                          'ayuda' => 'Frente de la carpa, en metros. Ej.: 3 o 2,5.'],
            'largo_m' => ['etiqueta' => 'Largo (m)', 'tipo' => 'decimal', 'requerido' => true, 'mayorQue' => 0, 'maximo' => 9999.99,
                          'ayuda' => 'Fondo de la carpa, en metros.'],
            'activo'  => ['etiqueta' => 'Activo (aparece en las listas para elegir)', 'tipo' => 'check', 'defecto' => 1],
        ];
    }

    protected function avisoFormulario(?array $fila): ?string
    {
        return $fila === null ? null
            : 'Cambiar las medidas no modifica los puestos ya asignados: cada asignación guarda sus propias medidas.';
    }

    protected function columnas(): array
    {
        return [
            'nombre'      => 'Nombre',
            'medidas'     => 'Medidas',
            'superficie'  => ['Superficie', 'm2'],
            'solicitudes' => ['Solicitudes', 'numero'],
            'activo'      => ['Estado', 'activo'],
        ];
    }

    protected function prepararFilas(array $filas): array
    {
        foreach ($filas as &$f) {
            $f['medidas'] = medida($f['ancho_m']) . ' × ' . medida($f['largo_m']) . ' m';
        }
        unset($f);
        return $filas;
    }
}
