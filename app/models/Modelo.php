<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/Database.php';

/**
 * Clase base de los modelos: operaciones comunes de una tabla
 * (buscar, listar, crear, actualizar, eliminar) con consultas preparadas.
 * Los nombres de columna vienen del código, nunca del usuario.
 */
abstract class Modelo
{
    protected string $tabla = '';
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::conectar();
    }

    public function db(): PDO
    {
        return $this->db;
    }

    public function buscar(int $id): ?array
    {
        $consulta = $this->db->prepare("SELECT * FROM `{$this->tabla}` WHERE id = :id");
        $consulta->execute(['id' => $id]);
        $fila = $consulta->fetch();
        return $fila ?: null;
    }

    /** Las clases hijas lo reemplazan para agregar uniones y filtros. */
    public function listar(array $filtros = []): array
    {
        return $this->consultar("SELECT * FROM `{$this->tabla}` ORDER BY id");
    }

    public function crear(array $datos): int
    {
        $columnas = array_keys($datos);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->tabla,
            implode(', ', array_map(static fn (string $c): string => "`$c`", $columnas)),
            implode(', ', array_map(static fn (string $c): string => ":$c", $columnas))
        );
        $this->db->prepare($sql)->execute($datos);
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): void
    {
        if ($datos === []) {
            return;
        }
        $asignaciones = implode(', ', array_map(
            static fn (string $c): string => "`$c` = :$c",
            array_keys($datos)
        ));
        $datos['id_registro'] = $id;
        $this->db->prepare("UPDATE `{$this->tabla}` SET $asignaciones WHERE id = :id_registro")->execute($datos);
    }

    public function eliminar(int $id): void
    {
        $this->db->prepare("DELETE FROM `{$this->tabla}` WHERE id = :id")->execute(['id' => $id]);
    }

    /** ¿Ya existe ese valor en la columna? (para campos únicos). */
    public function existeValor(string $columna, mixed $valor, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->tabla}` WHERE `$columna` = :valor";
        $parametros = ['valor' => $valor];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir';
            $parametros['excluir'] = $excluirId;
        }
        return (int) $this->valor($sql, $parametros) > 0;
    }

    public function contar(string $condicion = '1 = 1'): int
    {
        return (int) $this->valor("SELECT COUNT(*) FROM `{$this->tabla}` WHERE $condicion");
    }

    /**
     * Opciones id => nombre para listas desplegables. Solo registros activos,
     * más el que ya estaba elegido (aunque ahora esté inactivo).
     */
    public function opcionesActivas(?int $incluir = null): array
    {
        $filas = $this->consultar(
            "SELECT id, nombre, activo FROM `{$this->tabla}` WHERE activo = 1 OR id = :incluir ORDER BY nombre",
            ['incluir' => $incluir ?? 0]
        );
        $opciones = [];
        foreach ($filas as $f) {
            $opciones[$f['id']] = $f['nombre'] . ((int) $f['activo'] === 1 ? '' : ' (inactivo)');
        }
        return $opciones;
    }

    /** Todas las filas id => nombre (para filtros). */
    public function opcionesTodas(): array
    {
        $opciones = [];
        foreach ($this->consultar("SELECT id, nombre FROM `{$this->tabla}` ORDER BY nombre") as $f) {
            $opciones[$f['id']] = $f['nombre'];
        }
        return $opciones;
    }

    protected function consultar(string $sql, array $parametros = []): array
    {
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    protected function valor(string $sql, array $parametros = []): mixed
    {
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchColumn();
    }
}
