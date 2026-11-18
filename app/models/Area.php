<?php
declare(strict_types=1);

require_once __DIR__ . '/Modelo.php';

/**
 * Tabla areas: zonas de una feria donde se arman las carpas (una por rubro).
 * Convención del plano: X se mide a lo ancho (desde el borde izquierdo),
 * Y a lo largo (desde el frente). El pasillo es la franja final del largo.
 */
final class Area extends Modelo
{
    protected string $tabla = 'areas';

    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT a.*, f.nombre AS feria, c.nombre AS categoria,
                       (SELECT COUNT(*) FROM asignaciones x WHERE x.area_id = a.id) AS puestos,
                       (SELECT COALESCE(SUM(x.ancho_m * x.largo_m), 0) FROM asignaciones x WHERE x.area_id = a.id) AS ocupado
                FROM areas a
                JOIN ferias f ON f.id = a.feria_id
                JOIN categorias c ON c.id = a.categoria_id';
        $parametros = [];
        if (!empty($filtros['feria_id'])) {
            $sql .= ' WHERE a.feria_id = :feria_id';
            $parametros['feria_id'] = (int) $filtros['feria_id'];
        }
        return $this->consultar($sql . ' ORDER BY f.fecha DESC, f.nombre, a.nombre', $parametros);
    }

    /** Área con el nombre de su feria y categoría. */
    public function buscarDetalle(int $id): ?array
    {
        $filas = $this->consultar(
            'SELECT a.*, f.nombre AS feria, c.nombre AS categoria
             FROM areas a
             JOIN ferias f ON f.id = a.feria_id
             JOIN categorias c ON c.id = a.categoria_id
             WHERE a.id = :id',
            ['id' => $id]
        );
        return $filas[0] ?? null;
    }

    /** Filas para la lista desplegable, opcionalmente de una feria y categoría. */
    public function paraSelect(?int $feriaId = null, ?int $categoriaId = null): array
    {
        $sql = 'SELECT a.id, a.nombre, a.feria_id, a.categoria_id, a.ancho_m, a.largo_m, a.pasillo_m,
                       f.nombre AS feria, c.nombre AS categoria
                FROM areas a
                JOIN ferias f ON f.id = a.feria_id
                JOIN categorias c ON c.id = a.categoria_id
                WHERE 1 = 1';
        $parametros = [];
        if ($feriaId !== null) {
            $sql .= ' AND a.feria_id = :feria_id';
            $parametros['feria_id'] = $feriaId;
        }
        if ($categoriaId !== null) {
            $sql .= ' AND a.categoria_id = :categoria_id';
            $parametros['categoria_id'] = $categoriaId;
        }
        return $this->consultar($sql . ' ORDER BY f.fecha DESC, f.nombre, a.nombre', $parametros);
    }

    public function existeNombreEnFeria(int $feriaId, string $nombre, ?int $excluirId): bool
    {
        $sql = 'SELECT COUNT(*) FROM areas WHERE feria_id = :feria_id AND nombre = :nombre';
        $parametros = ['feria_id' => $feriaId, 'nombre' => $nombre];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir';
            $parametros['excluir'] = $excluirId;
        }
        return (int) $this->valor($sql, $parametros) > 0;
    }

    public function contarPuestos(int $id): int
    {
        return (int) $this->valor('SELECT COUNT(*) FROM asignaciones WHERE area_id = :id', ['id' => $id]);
    }

    /**
     * Prefijo de los códigos de carpa según el nombre del área:
     * "Zona A" → A (A01, A02...)   "Zona 2" → 2- (2-01...)   otro → P.
     */
    public static function prefijoCodigo(string $nombre): string
    {
        $ultima = self::ultimaPalabra($nombre);
        if ($ultima !== '' && ctype_digit($ultima) && strlen($ultima) <= 3) {
            return $ultima . '-';
        }
        $letra = strtoupper(substr($ultima, 0, 1));
        return preg_match('/^[A-Z]$/', $letra) === 1 ? $letra : 'P';
    }

    /**
     * Prefijo de cada área de una feria (id → prefijo), sin repetir: si dos zonas
     * empiezan con la misma letra, la segunda usa dos letras ("AR") y, si todavía
     * coincide, un número. Se reparten por orden de creación para que no cambien
     * al agregar zonas nuevas.
     */
    public function prefijos(array $areas): array
    {
        usort($areas, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);
        $usados = [];
        $prefijos = [];
        foreach ($areas as $area) {
            $base = self::prefijoCodigo((string) $area['nombre']);
            $candidatos = [$base];
            $letras = strtoupper((string) preg_replace('/[^A-Za-z]/', '', self::ultimaPalabra((string) $area['nombre'])));
            if (!str_ends_with($base, '-') && strlen($letras) >= 2) {
                $candidatos[] = substr($letras, 0, 2);
            }
            $elegido = null;
            foreach ($candidatos as $candidato) {
                if (!isset($usados[$candidato])) {
                    $elegido = $candidato;
                    break;
                }
            }
            for ($n = 2; $elegido === null; $n++) {
                $candidato = rtrim($base, '-') . $n . '-';
                if (!isset($usados[$candidato])) {
                    $elegido = $candidato;
                }
            }
            $usados[$elegido] = true;
            $prefijos[(int) $area['id']] = $elegido;
        }
        return $prefijos;
    }

    private static function ultimaPalabra(string $nombre): string
    {
        $palabras = preg_split('/\s+/u', trim($nombre)) ?: [];
        return quitar_acentos((string) end($palabras));
    }
}
