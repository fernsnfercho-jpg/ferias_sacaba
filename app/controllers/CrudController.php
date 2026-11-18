<?php
declare(strict_types=1);

/**
 * Controlador base para Listar, Crear, Editar y Eliminar (CRUD).
 *
 * Cada módulo (Ferias, Áreas, Solicitudes...) hereda de esta clase y solo
 * define sus campos, columnas y reglas propias. Así todas las pantallas
 * funcionan igual y las validaciones se hacen siempre en el servidor.
 *
 * Definición de un campo (método campos()):
 *   'nombre' => [
 *     'etiqueta'  => 'Nombre',                // texto visible
 *     'tipo'      => 'texto',                 // texto|email|password|telefono|decimal|entero|fecha|select|check
 *     'requerido' => true,
 *     'max'       => 100,                     // largo máximo de texto
 *     'decimales' => 2, 'minimo' => 0, 'maximo' => 9999.99, 'mayorQue' => 0,   // números
 *     'opciones'  => [valor => texto],        // select
 *     'vacio'     => '— Sin institución —',   // select: opción vacía que se guarda como NULL
 *     'defecto'   => valor inicial,
 *     'ayuda'     => 'texto de ayuda',
 *     'unico'     => true,                    // no puede repetirse en la tabla
 *     'virtual'   => true,                    // no es una columna de la tabla
 *     'soloCrear' => true,                    // solo aparece al crear
 *     'mostrarSi' => ['otro_campo', 'valor'], // solo aparece si otro campo tiene ese valor
 *   ]
 */
abstract class CrudController
{
    protected string $ruta = '';        // ej. 'categorias'
    protected string $titulo = '';      // ej. 'Categorías'
    protected string $singular = '';    // ej. 'categoría'
    protected string $textoNuevo = '';  // ej. 'Nueva categoría'
    protected string $descripcion = '';

    private ?Modelo $modeloCache = null;
    protected array $camposActuales = [];

    // ---------- Lo que cada módulo define ----------

    abstract protected function crearModelo(): Modelo;

    abstract protected function campos(?array $fila): array;

    /** Columnas de la tabla: clave => 'Título' o [Título, formato]. */
    abstract protected function columnas(): array;

    // ---------- Puntos de extensión (opcionales) ----------

    /** Filtros de la lista: nombre => ['etiqueta' => ..., 'opciones' => [...]]. */
    protected function filtros(): array
    {
        return [];
    }

    /** Enlaces extra por fila: [['texto' => ..., 'url' => ..., 'externo' => bool]]. */
    protected function accionesExtra(array $fila): array
    {
        return [];
    }

    /** Datos previos que faltan: [['Mensaje', 'ruta', 'Texto del enlace']]. */
    protected function requisitos(): array
    {
        return [];
    }

    /** Texto informativo encima del formulario. */
    protected function avisoFormulario(?array $fila): ?string
    {
        return null;
    }

    /** Agrega columnas calculadas a las filas de la lista. */
    protected function prepararFilas(array $filas): array
    {
        return $filas;
    }

    /** Reglas propias del módulo. Puede modificar $datos y agregar $errores. */
    protected function validarExtra(array &$datos, ?array $fila, array &$errores): void
    {
    }

    /** Devuelve un mensaje si no se debe eliminar, o null. */
    protected function motivoNoEliminar(array $fila): ?string
    {
        return null;
    }

    protected function nombreRegistro(array $fila): string
    {
        return (string) ($fila['nombre'] ?? ('N.º ' . $fila['id']));
    }

    /** Acciones que el enrutador puede llamar. */
    public function acciones(): array
    {
        return ['index', 'nuevo', 'editar', 'guardar', 'eliminar'];
    }

    protected function modelo(): Modelo
    {
        return $this->modeloCache ??= $this->crearModelo();
    }

    // ==========================================================
    // ACCIONES
    // ==========================================================

    /** Lista con búsqueda y filtros. */
    public function index(): void
    {
        $filtrosDef = $this->filtros();
        $filtros = $this->filtrosElegidos($filtrosDef, $_GET);
        $q = pedir_get('q');

        $filas = $this->prepararFilas($this->modelo()->listar($filtros));
        if ($q !== '') {
            $buscado = normalizar($q);
            $filas = array_values(array_filter($filas, static function (array $fila) use ($buscado): bool {
                $texto = implode(' ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $fila));
                return str_contains(normalizar($texto), $buscado);
            }));
        }

        $acciones = [];
        $nombres = [];
        foreach ($filas as $fila) {
            $acciones[$fila['id']] = $this->accionesExtra($fila);
            $nombres[$fila['id']] = $this->nombreRegistro($fila);
        }

        vista('crud/lista', [
            'titulo'      => $this->titulo,
            'descripcion' => $this->descripcion,
            'modulo'      => $this->ruta,
            'textoNuevo'  => $this->textoNuevo,
            'singular'    => $this->singular,
            'columnas'    => $this->columnas(),
            'filas'       => $filas,
            'acciones'    => $acciones,
            'nombres'     => $nombres,
            'filtrosDef'  => $filtrosDef,
            'filtros'     => $filtros,
            'q'           => $q,
            'requisitos'  => $this->requisitos(),
        ]);
    }

    /** Formulario vacío. Acepta valores iniciales por la URL (?feria_id=1). */
    public function nuevo(): void
    {
        $campos = $this->campos(null);
        $valores = [];
        foreach ($campos as $nombre => $c) {
            $desdeUrl = pedir_get($nombre);
            $valores[$nombre] = $desdeUrl !== '' ? $desdeUrl : ($c['defecto'] ?? '');
        }
        $this->mostrarFormulario(null, $campos, $valores, []);
    }

    /** Formulario con los datos actuales. */
    public function editar(): void
    {
        $fila = $this->cargarFila(pedir_get('id'));
        $campos = $this->campos($fila);
        $valores = [];
        foreach ($campos as $nombre => $c) {
            $valores[$nombre] = ($c['tipo'] ?? '') === 'password' ? '' : ($fila[$nombre] ?? ($c['defecto'] ?? ''));
        }
        $this->mostrarFormulario($fila, $campos, $valores, []);
    }

    /** Recibe el formulario, valida y guarda (crear o actualizar). */
    public function guardar(): void
    {
        exigir_post();
        verificar_csrf();

        $fila = pedir_post('id') !== '' ? $this->cargarFila(pedir_post('id')) : null;
        $campos = $this->campos($fila);
        $this->camposActuales = $campos;

        $errores = [];
        $datos = $this->leerFormulario($campos, $fila === null, $errores);
        if ($errores === []) {
            $this->validarUnicos($campos, $datos, $fila, $errores);
        }
        if ($errores === []) {
            $this->validarExtra($datos, $fila, $errores);
        }
        if ($errores !== []) {
            $this->mostrarFormulario($fila, $campos, $this->valoresEnviados($campos), $errores);
            return;
        }

        try {
            $id = $this->guardarDatos($datos, $fila);
        } catch (PDOException $ex) {
            error_log('[ferias_sacaba] ' . $ex->getMessage());
            $errores['_general'] = $this->mensajeErrorBD($ex);
            $this->mostrarFormulario($fila, $campos, $this->valoresEnviados($campos), $errores);
            return;
        } catch (RuntimeException $ex) {
            error_log('[ferias_sacaba] ' . $ex->getMessage());
            $errores['_general'] = 'No se pudo guardar el archivo. Revise los permisos de la carpeta de fotos y vuelva a intentarlo.';
            $this->mostrarFormulario($fila, $campos, $this->valoresEnviados($campos), $errores);
            return;
        }

        $guardado = $this->modelo()->buscar($id) ?? ($fila ?? ['id' => $id]);
        flash('ok', '«' . $this->nombreRegistro($guardado) . '» '
            . ($fila === null ? 'se registró correctamente.' : 'se actualizó correctamente.'));
        [$ruta, $parametros] = $this->destinoTrasGuardar($datos, $id);
        redirigir($ruta, $parametros);
    }

    /** Elimina un registro (solo por POST y con confirmación). */
    public function eliminar(): void
    {
        exigir_post();
        verificar_csrf();
        $fila = $this->cargarFila(pedir_post('id'));
        $volver = $this->parametrosDeRetorno();

        $motivo = $this->motivoNoEliminar($fila);
        if ($motivo !== null) {
            flash('error', $motivo);
            redirigir($this->ruta, $volver);
        }
        try {
            $this->eliminarRegistro($fila);
            flash('ok', '«' . $this->nombreRegistro($fila) . '» se eliminó.');
        } catch (PDOException $ex) {
            error_log('[ferias_sacaba] ' . $ex->getMessage());
            flash('error', $this->mensajeErrorBD($ex));
        }
        redirigir($this->ruta, $volver);
    }

    // ==========================================================
    // GUARDADO (los módulos pueden reemplazarlo)
    // ==========================================================

    protected function guardarDatos(array $datos, ?array $fila): int
    {
        $columnas = $this->soloColumnas($datos);
        if ($fila !== null) {
            $this->modelo()->actualizar((int) $fila['id'], $columnas);
            return (int) $fila['id'];
        }
        return $this->modelo()->crear($columnas);
    }

    protected function eliminarRegistro(array $fila): void
    {
        $this->modelo()->eliminar((int) $fila['id']);
    }

    /** Quita los campos virtuales (los que no son columnas de la tabla). */
    protected function soloColumnas(array $datos): array
    {
        foreach ($this->camposActuales as $nombre => $c) {
            if (!empty($c['virtual'])) {
                unset($datos[$nombre]);
            }
        }
        return $datos;
    }

    /** A dónde volver después de guardar: la lista, filtrada por feria si corresponde. */
    protected function destinoTrasGuardar(array $datos, int $id): array
    {
        $parametros = [];
        if (isset($datos['feria_id']) && array_key_exists('feria_id', $this->filtros())) {
            $parametros['feria_id'] = $datos['feria_id'];
        }
        return [$this->ruta, $parametros];
    }

    // ==========================================================
    // FORMULARIO
    // ==========================================================

    protected function mostrarFormulario(?array $fila, array $campos, array $valores, array $errores): void
    {
        $esNuevo = $fila === null;
        $visibles = array_filter($campos, fn (array $c): bool => $this->campoVisible($c, $esNuevo));
        vista('crud/formulario', [
            'titulo'       => $esNuevo ? $this->textoNuevo : 'Editar ' . $this->singular,
            'subtitulo'    => $esNuevo ? '' : $this->nombreRegistro($fila),
            'modulo'       => $this->ruta,
            'tituloModulo' => $this->titulo,
            'fila'         => $fila,
            'campos'       => $visibles,
            'valores'      => $valores,
            'errores'      => $errores,
            'aviso'        => $this->avisoFormulario($fila),
            'requisitos'   => $esNuevo ? $this->requisitos() : [],
        ]);
    }

    private function campoVisible(array $c, bool $esNuevo): bool
    {
        return empty($c['soloCrear']) || $esNuevo;
    }

    /** Lee, limpia y valida cada campo según su tipo. */
    protected function leerFormulario(array $campos, bool $esNuevo, array &$errores): array
    {
        $datos = [];
        foreach ($campos as $nombre => $c) {
            if (!$this->campoVisible($c, $esNuevo)) {
                continue;
            }
            if (isset($c['mostrarSi']) && pedir_post($c['mostrarSi'][0]) !== (string) $c['mostrarSi'][1]) {
                continue;
            }

            $tipo = $c['tipo'] ?? 'texto';
            if ($tipo === 'check') {
                $datos[$nombre] = isset($_POST[$nombre]) ? 1 : 0;
                continue;
            }
            if ($tipo === 'archivo') {
                $datos[$nombre] = null;
                continue;
            }

            $valor = $tipo === 'password' ? pedir_post_crudo($nombre) : pedir_post($nombre);
            if ($valor === '') {
                if (!empty($c['requerido'])) {
                    $errores[$nombre] = 'Este dato es obligatorio.';
                }
                $datos[$nombre] = null;
                continue;
            }

            $error = null;
            switch ($tipo) {
                case 'email':
                    $valor = minusculas($valor);
                    if (filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
                        $error = 'Escriba un correo válido, por ejemplo nombre@correo.com.';
                    }
                    break;

                case 'password':
                    if (strlen($valor) < 8) {
                        $error = 'La contraseña debe tener al menos 8 caracteres.';
                    } elseif (strlen($valor) > 72) {
                        $error = 'La contraseña no puede tener más de 72 caracteres.';
                    }
                    break;

                case 'telefono':
                    $valor = (string) preg_replace('/[\s\-().]/', '', $valor);
                    if (preg_match('/^\+?\d{7,14}$/', $valor) !== 1) {
                        $error = 'Escriba solo números (7 a 14 dígitos). Puede empezar con +, por ejemplo +59171234567.';
                    }
                    break;

                case 'entero':
                    if (filter_var($valor, FILTER_VALIDATE_INT) === false) {
                        $error = 'Escriba un número entero.';
                    }
                    break;

                case 'decimal':
                    $normal = str_replace(',', '.', $valor);
                    if (preg_match('/^-?\d+(\.\d+)?$/', $normal) !== 1) {
                        $error = 'Escriba un número. Use coma o punto para los decimales (ej.: 2,5).';
                        break;
                    }
                    $decimales = (int) ($c['decimales'] ?? 2);
                    $numero = round((float) $normal, $decimales);
                    if (isset($c['mayorQue']) && $numero <= $c['mayorQue']) {
                        $error = 'Debe ser mayor que ' . medida($c['mayorQue']) . '.';
                    } elseif (isset($c['minimo']) && $numero < $c['minimo']) {
                        $error = 'No puede ser menor que ' . $c['minimo'] . '.';
                    } elseif (isset($c['maximo']) && $numero > $c['maximo']) {
                        $error = 'No puede ser mayor que ' . $c['maximo'] . '.';
                    }
                    $valor = number_format($numero, $decimales, '.', '');
                    break;

                case 'fecha':
                    $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
                    if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
                        $error = 'Elija una fecha válida.';
                    }
                    break;

                case 'select':
                    if (!array_key_exists($valor, $c['opciones'] ?? [])) {
                        $error = 'Elija una opción de la lista.';
                    }
                    break;
            }

            if ($error === null && isset($c['max']) && longitud($valor) > (int) $c['max']) {
                $error = 'Máximo ' . $c['max'] . ' caracteres.';
            }
            if ($error !== null) {
                $errores[$nombre] = $error;
            }
            $datos[$nombre] = $valor;
        }
        return $datos;
    }

    /** Revisa los campos marcados como 'unico'. */
    private function validarUnicos(array $campos, array $datos, ?array $fila, array &$errores): void
    {
        foreach ($campos as $nombre => $c) {
            if (!empty($c['unico']) && isset($datos[$nombre])
                && $this->modelo()->existeValor($nombre, $datos[$nombre], $fila ? (int) $fila['id'] : null)) {
                $errores[$nombre] = 'Ya existe un registro con este dato. Debe ser único.';
            }
        }
    }

    /** Lo que el usuario escribió, para volver a mostrarlo si hubo errores. */
    private function valoresEnviados(array $campos): array
    {
        $valores = [];
        foreach ($campos as $nombre => $c) {
            $tipo = $c['tipo'] ?? 'texto';
            if ($tipo === 'password' || $tipo === 'archivo') {
                $valores[$nombre] = '';
            } elseif ($tipo === 'check') {
                $valores[$nombre] = isset($_POST[$nombre]) ? 1 : 0;
            } else {
                $valores[$nombre] = pedir_post($nombre);
            }
        }
        return $valores;
    }

    // ==========================================================
    // UTILIDADES
    // ==========================================================

    /** Busca el registro por id o vuelve a la lista con un aviso. */
    protected function cargarFila(string $id): array
    {
        $numero = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $fila = $numero === false ? null : $this->modelo()->buscar($numero);
        if ($fila === null) {
            flash('error', 'El registro no existe o ya fue eliminado.');
            redirigir($this->ruta);
        }
        return $fila;
    }

    /** Solo acepta filtros cuyo valor está entre las opciones. */
    private function filtrosElegidos(array $definicion, array $origen): array
    {
        $elegidos = [];
        foreach ($definicion as $nombre => $f) {
            $valor = $origen[$nombre] ?? '';
            if (is_string($valor) && $valor !== '' && array_key_exists($valor, $f['opciones'])) {
                $elegidos[$nombre] = $valor;
            }
        }
        return $elegidos;
    }

    /** Filtros y búsqueda que tenía la lista antes de eliminar. */
    private function parametrosDeRetorno(): array
    {
        parse_str(pedir_post('retorno'), $anterior);
        $parametros = $this->filtrosElegidos($this->filtros(), $anterior);
        if (isset($anterior['q']) && is_string($anterior['q']) && $anterior['q'] !== '') {
            $parametros['q'] = $anterior['q'];
        }
        return $parametros;
    }

    /** Traduce los errores de MySQL a mensajes comprensibles. */
    protected function mensajeErrorBD(PDOException $ex): string
    {
        $codigo = (int) ($ex->errorInfo[1] ?? 0);
        return match ($codigo) {
            1062    => 'Ya existe un registro con esos mismos datos (por ejemplo, el mismo nombre, correo o código).',
            1451    => 'No se puede eliminar porque otros registros dependen de este. Elimine primero esos registros, o desactívelo para que deje de aparecer en las listas.',
            1452    => 'Uno de los registros elegidos ya no existe. Recargue la página e inténtelo de nuevo.',
            3819    => 'Los valores no cumplen las reglas de la base de datos (revise medidas y coordenadas).',
            1406    => 'Uno de los textos es demasiado largo.',
            default => 'No se pudo completar la operación en la base de datos (código ' . $codigo . ').',
        };
    }
}
