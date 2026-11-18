<?php
declare(strict_types=1);

/**
 * Reglas geométricas para ubicar carpas dentro de un área.
 *
 *  - X se mide desde el borde izquierdo, Y desde el frente del área.
 *  - El espacio útil es: ancho del área × (largo − pasillo).
 *    El pasillo es la franja del fondo y nunca se ocupa.
 *  - Dos carpas no pueden superponerse (sí pueden tocarse por el borde).
 *  - Las carpas libres NO se guardan en la base: se calculan con la
 *    cuadrícula del área y la medida de la carpa oficial.
 *
 * Los cálculos se hacen en centímetros enteros para evitar errores de
 * redondeo con decimales (por ejemplo 0,1 + 0,2).
 */
final class UbicacionService
{
    private static function cm(mixed $metros): int
    {
        return (int) round((float) $metros * 100);
    }

    /** Convierte filas de asignaciones (en metros) a rectángulos en cm. */
    private function rectangulos(array $puestos): array
    {
        $rects = [];
        foreach ($puestos as $p) {
            $rects[] = [
                'x' => self::cm($p['x_m']), 'y' => self::cm($p['y_m']),
                'w' => self::cm($p['ancho_m']), 'h' => self::cm($p['largo_m']),
                'codigo' => (string) ($p['codigo'] ?? ''),
            ];
        }
        return $rects;
    }

    private function seSuperponen(int $x, int $y, int $w, int $h, array $r): bool
    {
        return $x < $r['x'] + $r['w'] && $r['x'] < $x + $w
            && $y < $r['y'] + $r['h'] && $r['y'] < $y + $h;
    }

    /**
     * Devuelve null si la carpa cabe en esa posición; si no, explica el motivo.
     */
    public function motivoNoCabe(float $x, float $y, float $ancho, float $largo, float $anchoArea, float $largoUtil, array $puestos): ?string
    {
        $xi = self::cm($x);
        $yi = self::cm($y);
        $wi = self::cm($ancho);
        $hi = self::cm($largo);

        if ($xi < 0 || $yi < 0) {
            return 'La posición X e Y no puede ser negativa.';
        }
        if ($xi + $wi > self::cm($anchoArea)) {
            return sprintf(
                'La carpa se sale por el costado: X (%s) + ancho (%s) = %s m, pero el área mide %s m de ancho.',
                medida($x), medida($ancho), medida($x + $ancho), medida($anchoArea)
            );
        }
        if ($yi + $hi > self::cm($largoUtil)) {
            return sprintf(
                'La carpa invade el pasillo: Y (%s) + largo (%s) = %s m, pero el espacio útil llega hasta %s m.',
                medida($y), medida($largo), medida($y + $largo), medida($largoUtil)
            );
        }
        foreach ($this->rectangulos($puestos) as $r) {
            if ($this->seSuperponen($xi, $yi, $wi, $hi, $r)) {
                return sprintf(
                    'Se superpone con el puesto %s (X %s, Y %s, %s × %s m).',
                    $r['codigo'], medida($r['x'] / 100), medida($r['y'] / 100), medida($r['w'] / 100), medida($r['h'] / 100)
                );
            }
        }
        return null;
    }

    /**
     * Busca el primer espacio libre recorriendo filas de adelante hacia el
     * fondo y, en cada fila, de izquierda a derecha. Devuelve [x, y] en metros
     * o null si la carpa no cabe en ningún lugar del área.
     * (Lo usa el módulo de asignaciones manuales, oculto en esta versión.)
     */
    public function primerEspacioLibre(float $ancho, float $largo, float $anchoArea, float $largoUtil, array $puestos): ?array
    {
        $w = self::cm($ancho);
        $h = self::cm($largo);
        $anchoMax = self::cm($anchoArea);
        $largoMax = self::cm($largoUtil);
        $rects = $this->rectangulos($puestos);

        $xs = [0];
        $ys = [0];
        foreach ($rects as $r) {
            $xs[] = $r['x'] + $r['w'];
            $ys[] = $r['y'] + $r['h'];
        }
        $xs = array_unique($xs);
        $ys = array_unique($ys);
        sort($xs);
        sort($ys);

        foreach ($ys as $y) {
            if ($y + $h > $largoMax) {
                break;
            }
            foreach ($xs as $x) {
                if ($x + $w > $anchoMax) {
                    break;
                }
                $libre = true;
                foreach ($rects as $r) {
                    if ($this->seSuperponen($x, $y, $w, $h, $r)) {
                        $libre = false;
                        break;
                    }
                }
                if ($libre) {
                    return [$x / 100, $y / 100];
                }
            }
        }
        return null;
    }

    // ==========================================================
    // CUADRÍCULA DE CARPAS OFICIALES
    // ==========================================================

    /** Columnas y filas de carpas que caben en el espacio útil de un área. */
    public function columnasYFilas(array $area, array $carpa): array
    {
        $w = self::cm($carpa['ancho_m']);
        $h = self::cm($carpa['largo_m']);
        if ($w <= 0 || $h <= 0) {
            return [0, 0];
        }
        $largoUtil = self::cm($area['largo_m']) - self::cm($area['pasillo_m']);
        return [max(0, intdiv(self::cm($area['ancho_m']), $w)), max(0, intdiv($largoUtil, $h))];
    }

    /** Cuántas carpas oficiales caben en un área. */
    public function capacidad(array $area, array $carpa): int
    {
        [$columnas, $filas] = $this->columnasYFilas($area, $carpa);
        return $columnas * $filas;
    }

    /**
     * Cuadrícula de un área: una celda por cada carpa oficial que cabe, numerada
     * de izquierda a derecha y del frente hacia el fondo (A01, A02...).
     *
     * Estado de cada celda:
     *   libre     → se puede reservar
     *   ocupada   → tiene una reserva; 'puesto' trae sus datos
     *   bloqueada → la pisa un puesto de otra medida (datos de versiones
     *               anteriores) o su código ya se usa en otra parte de la feria
     *
     * 'sueltos' son los puestos guardados que no coinciden con ninguna celda.
     */
    public function cuadricula(array $area, array $carpa, array $puestos, string $prefijo, array $codigosEnUso = []): array
    {
        $puestos = array_values($puestos);
        $rects = $this->rectangulos($puestos);
        [$columnas, $filas] = $this->columnasYFilas($area, $carpa);
        $w = self::cm($carpa['ancho_m']);
        $h = self::cm($carpa['largo_m']);

        $celdas = [];
        $enCelda = [];
        $numero = 0;
        for ($fila = 0; $fila < $filas; $fila++) {
            for ($columna = 0; $columna < $columnas; $columna++) {
                $numero++;
                $x = $columna * $w;
                $y = $fila * $h;
                $codigo = sprintf('%s%02d', $prefijo, $numero);
                $estado = 'libre';
                $puesto = null;

                foreach ($rects as $i => $r) {
                    if ($r['x'] === $x && $r['y'] === $y && $r['w'] === $w && $r['h'] === $h) {
                        $estado = 'ocupada';
                        $puesto = $puestos[$i];
                        $codigo = (string) $puesto['codigo'];
                        $enCelda[$i] = true;
                        break;
                    }
                }
                if ($puesto === null) {
                    foreach ($rects as $r) {
                        if ($this->seSuperponen($x, $y, $w, $h, $r)) {
                            $estado = 'bloqueada';
                            break;
                        }
                    }
                    if ($estado === 'libre' && in_array(strtoupper($codigo), $codigosEnUso, true)) {
                        $estado = 'bloqueada';
                    }
                }

                $celdas[] = [
                    'codigo'  => $codigo,
                    'fila'    => $fila + 1,
                    'columna' => $columna + 1,
                    'x_m'     => $x / 100,
                    'y_m'     => $y / 100,
                    'estado'  => $estado,
                    'puesto'  => $puesto,
                ];
            }
        }

        $sueltos = [];
        foreach ($puestos as $i => $p) {
            if (!isset($enCelda[$i])) {
                $sueltos[] = $p;
            }
        }
        return ['columnas' => $columnas, 'filas' => $filas, 'celdas' => $celdas, 'sueltos' => $sueltos];
    }

    /**
     * Plano de una feria: sus zonas (todas o las de un rubro) con la cuadrícula
     * de carpas y los totales. Es la única fuente para dibujar el plano, contar
     * carpas libres y comprobar que una carpa sigue disponible al reservar.
     */
    public function planoDeFeria(int $feriaId, ?int $categoriaId = null): array
    {
        $carpa = (new TipoCarpa())->oficial();
        $modeloAreas = new Area();
        $areas = $modeloAreas->listar(['feria_id' => $feriaId]);
        $prefijos = $modeloAreas->prefijos($areas);

        $porArea = [];
        $codigosEnUso = [];
        foreach ((new Asignacion())->puestosDeFeria($feriaId) as $p) {
            $porArea[(int) $p['area_id']][] = $p;
            $codigosEnUso[] = strtoupper((string) $p['codigo']);
        }

        $plano = ['carpa' => $carpa, 'zonas' => [], 'total' => 0, 'libres' => 0, 'pendientes' => 0, 'confirmadas' => 0];
        foreach ($areas as $area) {
            if ($categoriaId !== null && (int) $area['categoria_id'] !== $categoriaId) {
                continue;
            }
            $puestos = $porArea[(int) $area['id']] ?? [];
            $zona = ['area' => $area]
                + $this->cuadricula($area, $carpa, $puestos, $prefijos[(int) $area['id']], $codigosEnUso)
                + ['total' => 0, 'libres' => 0, 'pendientes' => 0, 'confirmadas' => 0];

            $zona['total'] = count($zona['celdas']);
            foreach ($zona['celdas'] as $celda) {
                if ($celda['estado'] === 'libre') {
                    $zona['libres']++;
                }
            }
            foreach ($puestos as $p) {
                $zona[$p['estado_reserva'] === 'confirmada' ? 'confirmadas' : 'pendientes']++;
            }
            foreach (['total', 'libres', 'pendientes', 'confirmadas'] as $clave) {
                $plano[$clave] += $zona[$clave];
            }
            $plano['zonas'][] = $zona;
        }
        return $plano;
    }
}
