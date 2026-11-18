<?php
declare(strict_types=1);

final class InstitucionesController extends CrudController
{
    protected string $ruta = 'instituciones';
    protected string $titulo = 'Instituciones';
    protected string $singular = 'institución';
    protected string $textoNuevo = 'Nueva institución';
    protected string $descripcion = 'Asociaciones u organizaciones a las que pertenecen los expositores (es opcional para cada expositor).';

    protected function crearModelo(): Modelo
    {
        return new Institucion();
    }

    protected function campos(?array $fila): array
    {
        return [
            'nombre' => ['etiqueta' => 'Nombre de la institución', 'requerido' => true, 'max' => 120, 'unico' => true,
                         'ayuda' => 'Ej.: Asociación de Productores de Sacaba.'],
            'activo' => ['etiqueta' => 'Activa (aparece en las listas para elegir)', 'tipo' => 'check', 'defecto' => 1],
        ];
    }

    protected function columnas(): array
    {
        return [
            'nombre'      => 'Nombre',
            'expositores' => ['Expositores', 'numero'],
            'activo'      => ['Estado', 'activo'],
        ];
    }
}
