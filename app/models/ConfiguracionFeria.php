<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

final class ConfiguracionFeria extends Modelo
{
    protected string $tabla = 'feria_configuracion';

    public function paraFeria(int $feriaId): ?array
    {
        $filas = $this->consultar(
            'SELECT * FROM feria_configuracion WHERE feria_id = :feria_id LIMIT 1',
            ['feria_id' => $feriaId]
        );
        return $filas[0] ?? null;
    }

    public function guardar(array $datos): void
    {
        $this->db->prepare(
            'INSERT INTO feria_configuracion
                (feria_id, largo_total_m, ancho_total_m, ancho_pasillo_m, entrada_m2, escenario_m2, tipo_carpa_id, imagen_plano)
             VALUES
                (:feria_id, :largo_total_m, :ancho_total_m, :ancho_pasillo_m, :entrada_m2, :escenario_m2, :tipo_carpa_id, :imagen_plano)
             ON DUPLICATE KEY UPDATE
                largo_total_m = VALUES(largo_total_m),
                ancho_total_m = VALUES(ancho_total_m),
                ancho_pasillo_m = VALUES(ancho_pasillo_m),
                entrada_m2 = VALUES(entrada_m2),
                escenario_m2 = VALUES(escenario_m2),
                tipo_carpa_id = VALUES(tipo_carpa_id),
                imagen_plano = VALUES(imagen_plano)'
        )->execute($datos);
    }
}
