<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/** Tabla categorias: rubros de productos (Alimentos, Artesanía, Muebles...). */
final class Categoria extends Modelo
{
    protected string $tabla = 'categorias';

    public function listar(array $filtros = []): array
    {
        return $this->consultar(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM areas a WHERE a.categoria_id = c.id) AS areas,
                    (SELECT COUNT(*) FROM solicitudes s WHERE s.categoria_id = c.id) AS solicitudes
             FROM categorias c
             ORDER BY c.nombre'
        );
    }

    /** Categorías activas que tienen un área habilitada en la feria indicada. */
    public function deFeria(int $feriaId): array
    {
        return $this->consultar(
            'SELECT DISTINCT c.id, c.nombre
             FROM categorias c
             JOIN areas a ON a.categoria_id = c.id
             WHERE c.activo = 1 AND a.feria_id = :feria_id
             ORDER BY c.nombre',
            ['feria_id' => $feriaId]
        );
    }

    public function estaDisponibleEnFeria(int $categoriaId, int $feriaId): bool
    {
        return (int) $this->valor(
            'SELECT COUNT(*) FROM categorias c
             JOIN areas a ON a.categoria_id = c.id
             WHERE c.id = :categoria_id AND c.activo = 1 AND a.feria_id = :feria_id',
            ['categoria_id' => $categoriaId, 'feria_id' => $feriaId]
        ) > 0;
    }
}
