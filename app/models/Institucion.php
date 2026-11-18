<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/** Tabla instituciones: asociaciones a las que pertenecen los expositores. */
final class Institucion extends Modelo
{
    protected string $tabla = 'instituciones';

    public function listar(array $filtros = []): array
    {
        return $this->consultar(
            'SELECT i.*, (SELECT COUNT(*) FROM expositores e WHERE e.institucion_id = i.id) AS expositores
             FROM instituciones i
             ORDER BY i.nombre'
        );
    }
}
