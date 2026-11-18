<?php
declare(strict_types=1);

final class AreasController extends CrudController
{
    protected string $ruta = 'areas';
    protected string $titulo = 'Áreas';
    protected string $singular = 'área';
    protected string $textoNuevo = 'Nueva área';
    protected string $descripcion = 'Zonas de cada feria donde se ubican las carpas. El pasillo es una franja al fondo del área que no se ocupa.';

    protected function crearModelo(): Modelo
    {
        return new Area();
    }

    public function acciones(): array
    {
        return [...parent::acciones(), 'plano'];
    }

    protected function campos(?array $fila): array
    {
        return [
            'feria_id'     => ['etiqueta' => 'Feria', 'tipo' => 'select', 'requerido' => true, 'opciones' => (new Feria())->opciones()],
            'categoria_id' => ['etiqueta' => 'Categoría de productos', 'tipo' => 'select', 'requerido' => true,
                               'opciones' => (new Categoria())->opcionesActivas($fila !== null ? (int) $fila['categoria_id'] : null)],
            'nombre'       => ['etiqueta' => 'Nombre del área', 'requerido' => true, 'max' => 80,
                               'ayuda' => 'Ej.: Zona A. La letra final se usa para los códigos de puesto (A01, A02...).'],
            'ancho_m'      => ['etiqueta' => 'Ancho (m)', 'tipo' => 'decimal', 'requerido' => true, 'mayorQue' => 0, 'maximo' => 9999.99,
                               'ayuda' => 'Medida de izquierda a derecha (eje X).'],
            'largo_m'      => ['etiqueta' => 'Largo (m)', 'tipo' => 'decimal', 'requerido' => true, 'mayorQue' => 0, 'maximo' => 9999.99,
                               'ayuda' => 'Medida de adelante hacia el fondo (eje Y), incluido el pasillo.'],
            'pasillo_m'    => ['etiqueta' => 'Pasillo (m)', 'tipo' => 'decimal', 'requerido' => true, 'mayorQue' => 0, 'maximo' => 9999.99,
                               'defecto' => (string) Configuracion::leer('feria')['pasillo_defecto_m'], 'ayuda' => 'Franja de circulación al fondo del área. Debe ser menor que el largo.'],
        ];
    }

    protected function columnas(): array
    {
        return [
            'nombre'    => 'Área',
            'feria'     => 'Feria',
            'categoria' => 'Categoría',
            'medidas'   => 'Medidas',
            'pasillo_m' => ['Pasillo', 'medida'],
            'util'      => ['Espacio útil', 'm2'],
            'puestos'   => ['Puestos', 'numero'],
            'ocupacion' => ['Ocupación', 'porcentaje'],
        ];
    }

    protected function filtros(): array
    {
        return ['feria_id' => ['etiqueta' => 'Feria', 'todos' => 'Todas las ferias', 'opciones' => (new Feria())->opciones()]];
    }

    protected function prepararFilas(array $filas): array
    {
        foreach ($filas as &$f) {
            $util = (float) $f['ancho_m'] * ((float) $f['largo_m'] - (float) $f['pasillo_m']);
            $f['medidas'] = medida($f['ancho_m']) . ' × ' . medida($f['largo_m']) . ' m';
            $f['util'] = $util;
            $f['ocupacion'] = $util > 0 ? (float) $f['ocupado'] / $util * 100 : 0;
        }
        unset($f);
        return $filas;
    }

    protected function accionesExtra(array $fila): array
    {
        return [['texto' => 'Plano', 'url' => url('areas/plano', ['id' => $fila['id']])]];
    }

    protected function requisitos(): array
    {
        $faltan = [];
        if ((new Feria())->contar() === 0) {
            $faltan[] = ['Primero registre una feria.', 'ferias/nuevo', 'Nueva feria'];
        }
        if ((new Categoria())->contar('activo = 1') === 0) {
            $faltan[] = ['No hay categorías activas.', 'categorias/nuevo', 'Nueva categoría'];
        }
        return $faltan;
    }

    protected function validarExtra(array &$datos, ?array $fila, array &$errores): void
    {
        $ancho = (float) $datos['ancho_m'];
        $largo = (float) $datos['largo_m'];
        $pasillo = (float) $datos['pasillo_m'];
        $excluir = $fila !== null ? (int) $fila['id'] : null;

        // Regla de la base: largo > pasillo.
        if ($largo <= $pasillo) {
            $errores['largo_m'] = 'El largo debe ser mayor que el pasillo (' . medida($pasillo) . ' m).';
        }
        if ((new Area())->existeNombreEnFeria((int) $datos['feria_id'], (string) $datos['nombre'], $excluir)) {
            $errores['nombre'] = 'Ya existe un área con este nombre en la feria elegida.';
        }

        if ($fila === null || $errores !== []) {
            return;
        }

        // Si el área ya tiene puestos, los cambios no deben dejarlos inválidos.
        $puestos = (new Asignacion())->puestosDeArea((int) $fila['id']);
        if ($puestos === []) {
            return;
        }
        if ((int) $datos['feria_id'] !== (int) $fila['feria_id']) {
            $errores['feria_id'] = 'El área ya tiene ' . count($puestos) . ' puesto(s) asignado(s); no se puede pasar a otra feria.';
        }
        if ((int) $datos['categoria_id'] !== (int) $fila['categoria_id']) {
            $errores['categoria_id'] = 'El área tiene puestos de la categoría actual. Elimine esas asignaciones antes de cambiarla.';
        }
        $servicio = new UbicacionService();
        $fuera = [];
        foreach ($puestos as $p) {
            $motivo = $servicio->motivoNoCabe(
                (float) $p['x_m'], (float) $p['y_m'], (float) $p['ancho_m'], (float) $p['largo_m'],
                $ancho, $largo - $pasillo, []
            );
            if ($motivo !== null) {
                $fuera[] = $p['codigo'];
            }
        }
        if ($fuera !== []) {
            $errores['ancho_m'] = 'Con estas medidas quedarían fuera del espacio útil los puestos: '
                . implode(', ', $fuera) . '. Muévalos o elimínelos antes de reducir el área.';
        }
    }

    protected function motivoNoEliminar(array $fila): ?string
    {
        $n = (new Area())->contarPuestos((int) $fila['id']);
        return $n > 0 ? 'El área tiene ' . $n . ' puesto(s) asignado(s). Elimine primero esas asignaciones.' : null;
    }

    /** Dibujo a escala del área con sus puestos y las solicitudes que faltan ubicar. */
    public function plano(): void
    {
        $id = filter_var(pedir_get('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $area = $id === false ? null : (new Area())->buscarDetalle($id);
        if ($area === null) {
            flash('error', 'El área no existe.');
            redirigir('areas');
        }

        $puestos = (new Asignacion())->puestosDeArea((int) $area['id']);
        $pendientes = (new Solicitud())->sinPuesto((int) $area['feria_id'], (int) $area['categoria_id']);

        $ancho = (float) $area['ancho_m'];
        $largo = (float) $area['largo_m'];
        $pasillo = (float) $area['pasillo_m'];
        $util = $ancho * ($largo - $pasillo);
        $ocupado = 0.0;
        foreach ($puestos as $p) {
            $ocupado += (float) $p['ancho_m'] * (float) $p['largo_m'];
        }

        vista('areas/plano', [
            'area'       => $area,
            'puestos'    => $puestos,
            'pendientes' => $pendientes,
            'ancho'      => $ancho,
            'largo'      => $largo,
            'pasillo'    => $pasillo,
            'util'       => $util,
            'ocupado'    => $ocupado,
            'porcentaje' => $util > 0 ? $ocupado / $util * 100 : 0,
        ]);
    }
}
