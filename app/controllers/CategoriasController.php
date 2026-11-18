<?php
declare(strict_types=1);

final class CategoriasController extends CrudController
{
    protected string $ruta = 'categorias';
    protected string $titulo = 'Categorías';
    protected string $singular = 'categoría';
    protected string $textoNuevo = 'Nueva categoría';
    protected string $descripcion = 'Rubros de productos. Cada área de la feria se destina a una categoría y cada solicitud indica la suya.';

    protected function crearModelo(): Modelo
    {
        return new Categoria();
    }

    protected function campos(?array $fila): array
    {
        return [
            'nombre' => ['etiqueta' => 'Nombre de la categoría', 'requerido' => true, 'max' => 80, 'unico' => true,
                         'ayuda' => 'Ej.: Alimentos, Artesanía, Textiles.'],
            'activo' => ['etiqueta' => 'Activa (aparece en las listas para elegir)', 'tipo' => 'check', 'defecto' => 1],
        ];
    }

    protected function columnas(): array
    {
        return [
            'nombre'      => 'Nombre',
            'areas'       => ['Áreas', 'numero'],
            'solicitudes' => ['Solicitudes', 'numero'],
            'activo'      => ['Estado', 'activo'],
        ];
    }
}
