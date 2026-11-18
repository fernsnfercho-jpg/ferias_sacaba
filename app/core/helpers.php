<?php
declare(strict_types=1);

// ============================================================
// Funciones de ayuda usadas por controladores y vistas.
// ============================================================

/** Escapa un valor para mostrarlo en HTML (evita inyección de código). */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Arma un enlace interno: url('ferias/editar', ['id' => 3]). */
function url(string $ruta, array $parametros = []): string
{
    return 'index.php?' . http_build_query(['ruta' => $ruta] + $parametros);
}

/** Ruta de un archivo de public/ con su fecha, para que el navegador no use una copia vieja. */
function recurso(string $ruta): string
{
    $archivo = RAIZ_PROYECTO . '/public/' . $ruta;
    return $ruta . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}

/** Redirige a otra ruta y termina la petición. */
function redirigir(string $ruta, array $parametros = []): void
{
    header('Location: ' . url($ruta, $parametros));
    exit;
}

/** Carga una vista de app/views con las variables indicadas. */
function vista(string $nombre, array $datos = []): void
{
    extract($datos, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . $nombre . '.php';
}

// ---------- Mensajes de una sola vez (flash) ----------

/** Tipos: 'ok', 'error', 'aviso' e 'info'. */
function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'texto' => $texto];
}

function tomar_flash(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($mensajes) ? $mensajes : [];
}

// ---------- Lectura segura de formularios ----------

/** Devuelve un dato de $_POST como texto sin espacios a los lados. */
function pedir_post(string $nombre): string
{
    $valor = $_POST[$nombre] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

/** Igual que pedir_post pero sin recortar (para contraseñas). */
function pedir_post_crudo(string $nombre): string
{
    $valor = $_POST[$nombre] ?? '';
    return is_string($valor) ? $valor : '';
}

function pedir_get(string $nombre): string
{
    $valor = $_GET[$nombre] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

/** Lee un identificador (entero mayor que cero) o devuelve null. */
function pedir_id(string $valor): ?int
{
    $numero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $numero === false ? null : $numero;
}

// ---------- Protección CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Comprueba que el formulario salió de este sistema. */
function verificar_csrf(string $rutaDestino = 'panel'): void
{
    if (!hash_equals(csrf_token(), pedir_post_crudo('csrf'))) {
        flash('error', 'El formulario expiró. Vuelve a intentarlo.');
        redirigir($rutaDestino);
    }
}

/** Termina con error 405 si la petición no es POST. */
function exigir_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Método no permitido';
        exit;
    }
}

// ---------- Texto ----------

function minusculas(string $texto): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

function longitud(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

function quitar_acentos(string $texto): string
{
    return strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
    ]);
}

/** Texto en minúsculas y sin acentos, para buscar. */
function normalizar(string $texto): string
{
    return minusculas(quitar_acentos($texto));
}

// ---------- Validaciones que se repiten en varios formularios ----------

/** Nombre de persona: letras, espacios, punto, apóstrofo y guion (2 a 100 caracteres). */
function es_nombre_valido(string $valor): bool
{
    return longitud($valor) >= 2 && longitud($valor) <= 100
        && preg_match('/^\p{L}[\p{L}\s.\'\-]*$/u', $valor) === 1;
}

/** Nombre de emprendimiento: 2 a 120 caracteres, con al menos una letra o número. */
function es_emprendimiento_valido(string $valor): bool
{
    return longitud($valor) >= 2 && longitud($valor) <= 120
        && preg_match('/[\p{L}\p{N}]/u', $valor) === 1
        && preg_match('/[\x00-\x1F\x7F<>]/', $valor) !== 1;
}

/** Devuelve el teléfono sin espacios ni guiones (7 a 14 dígitos, puede empezar con +) o null. */
function limpiar_telefono(string $valor): ?string
{
    $limpio = (string) preg_replace('/[\s\-().]/', '', $valor);
    return preg_match('/^\+?\d{7,14}$/', $limpio) === 1 ? $limpio : null;
}

/** Correo válido de hasta 150 caracteres. */
function es_correo_valido(string $valor): bool
{
    return longitud($valor) <= 150 && filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
}

// ---------- Formatos de número y fecha ----------

/** 3.00 → "3"   1.50 → "1,5"   12.25 → "12,25" (formato boliviano). */
function medida(mixed $valor): string
{
    $texto = number_format((float) $valor, 2, ',', '.');
    return rtrim(rtrim($texto, '0'), ',');
}

/** Medida de una carpa: "3 × 4 m" (frente × fondo). */
function medida_carpa(array $carpa): string
{
    return medida($carpa['ancho_m']) . ' × ' . medida($carpa['largo_m']) . ' m';
}

/** Superficie de una carpa: "12 m²". */
function superficie_carpa(array $carpa): string
{
    return medida((float) $carpa['ancho_m'] * (float) $carpa['largo_m']) . ' m²';
}

function fecha_corta(?string $fecha): string
{
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $t = strtotime($fecha);
    return $t === false ? $fecha : date('d/m/Y', $t);
}

function fecha_hora(?string $fecha): string
{
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $t = strtotime($fecha);
    return $t === false ? $fecha : date('d/m/Y H:i', $t);
}

/** "jueves 17 de diciembre de 2026". */
function fecha_larga(?string $fecha): string
{
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $t = strtotime($fecha);
    if ($t === false) {
        return $fecha;
    }
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int) date('w', $t)] . ' ' . (int) date('j', $t) . ' de '
        . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
}

/** Enlace a Google Maps con el texto del lugar (no necesita clave de API). */
function enlace_mapa(string $lugar): string
{
    $consulta = str_contains(normalizar($lugar), 'sacaba') ? $lugar : $lugar . ', Sacaba';
    return 'https://www.google.com/maps/search/?' . http_build_query(['api' => 1, 'query' => $consulta . ', Bolivia']);
}

// ---------- Estados ----------

/** Texto y color de cada estado (reservas, ferias y cuentas). */
function datos_estado(string $estado): array
{
    $estados = [
        // Reservas
        'pendiente'     => ['Pendiente', 'warning'],
        'confirmada'    => ['Confirmada', 'success'],
        'rechazada'     => ['Rechazada', 'danger'],
        'cancelada'     => ['Cancelada', 'secondary'],
        // Valores de versiones anteriores, que se conservan en la base
        'asignada'      => ['Confirmada', 'success'],
        'espera'        => ['Pendiente', 'warning'],
        // Ferias
        'borrador'      => ['Borrador', 'secondary'],
        'abierta'       => ['Abierta', 'success'],
        'cerrada'       => ['Cerrada', 'dark'],
        // Cuentas
        'administrador' => ['Administrador', 'primary'],
        'usuario'       => ['Expositor', 'info'],
    ];
    return $estados[$estado] ?? [$estado, 'secondary'];
}

function nombre_estado(string $estado): string
{
    return datos_estado($estado)[0];
}

/** Etiqueta de color: pendiente amarillo, confirmada verde, rechazada rojo, cancelada gris. */
function etiqueta_estado(string $estado): string
{
    [$texto, $color] = datos_estado($estado);
    return '<span class="badge badge-' . $color . '">' . e($texto) . '</span>';
}

/** Da formato HTML (ya escapado) a una celda de las tablas. */
function formatear_celda(mixed $valor, string $formato): string
{
    if ($valor === null || $valor === '') {
        return $formato === 'numero' ? '0' : '<span class="text-muted">—</span>';
    }
    switch ($formato) {
        case 'activo':
            return (int) $valor === 1
                ? '<span class="badge badge-success">Activo</span>'
                : '<span class="badge badge-secondary">Inactivo</span>';
        case 'si_no':
            return (int) $valor === 1 ? '<span class="badge badge-info">Sí</span>' : 'No';
        case 'estado':
            return etiqueta_estado((string) $valor);
        case 'medida':
            return e(medida($valor) . ' m');
        case 'm2':
            return e(medida($valor) . ' m²');
        case 'porcentaje':
            return e((string) (int) round((float) $valor) . ' %');
        case 'fecha':
            return e(fecha_corta((string) $valor));
        case 'fechahora':
            return e(fecha_hora((string) $valor));
        case 'codigo':
            return '<strong>' . e($valor) . '</strong>';
        default:
            return e($valor);
    }
}

// ---------- Páginas de error ----------

/** Explica en palabras simples por qué falló la base de datos. */
function diagnostico_bd(Throwable $e): string
{
    $mensaje = $e->getMessage();
    $codigo = (string) $e->getCode();
    if ($e instanceof PDOException && isset($e->errorInfo[1])) {
        $codigo = (string) $e->errorInfo[1];
    }
    $es = static fn (string $num): bool => $codigo === $num || str_contains($mensaje, '[' . $num . ']');

    if ($es('1049')) {
        return 'La base de datos «ferias_sacaba» no existe. Importe base_datos/ferias_sacaba.sql en phpMyAdmin o en MySQL Workbench.';
    }
    if ($es('1045')) {
        return 'MySQL rechazó el usuario o la contraseña. Corrija db_user y db_pass en config/config.php.';
    }
    if ($es('2002') || str_contains($mensaje, 'Connection refused')) {
        return 'No se pudo conectar con MySQL. Verifique que el servidor esté encendido (XAMPP → MySQL → Start) y que el puerto de config/config.php sea el correcto.';
    }
    if ($es('1146') || $codigo === '42S02') {
        return 'Faltan tablas en la base. Importe ferias_sacaba.sql completo en una base nueva.';
    }
    if ($es('1054') || $codigo === '42S22') {
        return 'La base de datos es de una versión anterior. Importe base_datos/actualizar_v3.sql (no borra datos) y vuelva a intentarlo.';
    }
    if ($e instanceof PDOException) {
        return 'Ocurrió un error en la base de datos.';
    }
    return 'Ocurrió un error inesperado.';
}

/** Muestra una explicación sencilla; el detalle técnico queda solo en logs/errores.log. */
function mostrar_error(Throwable $e): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    vista('error', [
        'titulo'  => 'No se pudo completar la operación',
        'mensaje' => diagnostico_bd($e),
    ]);
}

function pagina_no_encontrada(): void
{
    http_response_code(404);
    vista('error', [
        'titulo'  => 'Página no encontrada',
        'mensaje' => 'La dirección no corresponde a ninguna pantalla del sistema.',
    ]);
}
