<?php
declare(strict_types=1);
require_once __DIR__ . '/../models/Feria.php';
require_once __DIR__ . '/../services/MapaService.php';
final class MapaController {
    public function mostrar(): void {
        header('Content-Type: text/html; charset=utf-8');
        try {
            $id = filter_input(INPUT_GET, 'feria_id', FILTER_VALIDATE_INT);
            if (!$id || $id<1) { http_response_code(400); echo 'Identificador inválido'; return; }
            $feria = (new Feria())->buscar($id);
            if (!$feria) { http_response_code(404); echo 'Feria no encontrada'; return; }
            $key = require __DIR__ . '/../../config/maps.php';
            if (!$key) { http_response_code(503); echo 'Mapa pendiente de configurar: agregue la clave en config/maps.php.'; return; }
            $url = (new MapaService())->url((float)$feria['latitud'], (float)$feria['longitud'], $key);
            require __DIR__ . '/../views/mapa.php';
        } catch (Throwable $e) {
            http_response_code(503); echo 'Mapa no disponible. Revise la conexión con MySQL y la configuración.';
        }
    }
}
