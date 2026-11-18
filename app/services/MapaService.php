<?php
declare(strict_types=1);
final class MapaService {
    public function url(float $lat, float $lng, string $key): string {
        if (!is_finite($lat) || !is_finite($lng) || abs($lat)>90 || abs($lng)>180) {
            throw new InvalidArgumentException('Coordenadas inválidas');
        }
        return 'https://www.google.com/maps/embed/v1/place?' . http_build_query([
            'key'=>$key, 'q'=>$lat . ',' . $lng, 'zoom'=>16, 'language'=>'es']);
    }
}
