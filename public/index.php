<?php
declare(strict_types=1);

// ============================================================
// FERIAS SACABA — Controlador frontal (todas las páginas pasan por aquí)
// Ejemplos: index.php?ruta=ferias   index.php?ruta=areas/plano&id=1
// ============================================================

ob_start();
require __DIR__ . '/../app/core/bootstrap.php';

$ruta = $_GET['ruta'] ?? 'inicio';
if (!is_string($ruta) || $ruta === '') {
    $ruta = 'login';
}

// ---------- Rutas de la semana 2 (se conservan igual) ----------
if ($ruta === 'mapa') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        exit;
    }
    (new MapaController())->mostrar();
    exit;
}

// ---------- Módulos del panel del administrador ----------
$modulos = [
    'ferias'       => FeriasController::class,
    'areas'        => AreasController::class,
    'expositores'  => ExpositoresController::class,
    'categorias'   => CategoriasController::class,
    'instituciones' => InstitucionesController::class,
    'usuarios'     => UsuariosController::class,
    'tipos_carpa'  => TiposCarpaController::class,
];

try {
    switch ($ruta) {
        // ---------- Página pública ----------
        case 'inicio':
            (new PublicController())->inicio();
            break;

        // ---------- Autenticación ----------
        case 'login':
            (new AuthController())->login();
            break;
        case 'registro':
            (new AuthController())->registro();
            break;
        case 'instalar':
            (new AuthController())->instalar();
            break;
        case 'salir':
            (new AuthController())->salir();
            break;

        // ---------- Panel del expositor ----------
        case 'mi-espacio':
            (new ExpositorPortalController())->index();
            break;
        case 'reservar':
            (new ExpositorPortalController())->reservar();
            break;
        case 'reserva/nueva':
            (new ExpositorPortalController())->solicitar();
            break;
        case 'mis-reservas':
            (new ExpositorPortalController())->misReservas();
            break;
        case 'reserva/cancelar':
            (new ExpositorPortalController())->cancelarReserva();
            break;
        case 'mi-perfil':
            (new ExpositorPortalController())->miPerfil();
            break;

        // ---------- Panel del administrador ----------
        case 'panel':
            Auth::requerirAdmin();
            (new PanelController())->index();
            break;
        case 'admin/reservas':
            Auth::requerirAdmin();
            (new PanelController())->reservas();
            break;
        case 'admin/reserva/confirmar':
            Auth::requerirAdmin();
            (new SolicitudesController())->confirmar();
            break;
        case 'admin/reserva/rechazar':
            Auth::requerirAdmin();
            (new SolicitudesController())->rechazar();
            break;
        case 'admin/reserva/reenviar':
            Auth::requerirAdmin();
            (new SolicitudesController())->reenviarCorreo();
            break;
        case 'admin/mapa':
            Auth::requerirAdmin();
            (new PanelController())->mapaCarpas();
            break;
        case 'admin/expositores':
            Auth::requerirAdmin();
            (new PanelController())->expositores();
            break;
        case 'admin/ferias':
            Auth::requerirAdmin();
            (new PanelController())->ferias();
            break;
        case 'admin/configuracion':
            Auth::requerirAdmin();
            (new PanelController())->configuracion();
            break;

        // ---------- Rutas existentes del panel CRUD ----------
        case 'configurar-feria':
            Auth::requerirAdmin();
            (new FeriaSacabaController())->configurar();
            break;
        case 'mapa-feria':
            $esAdmin = Auth::usuario() !== null && (new Usuario())->buscarAdministradorActivo((int) (Auth::usuario()['id'] ?? 0)) !== null;
            (new FeriaSacabaController())->mapa($esAdmin);
            break;

        default:
            [$modulo, $accion] = array_pad(explode('/', $ruta, 2), 2, 'index');
            if (!isset($modulos[$modulo])) {
                pagina_no_encontrada();
                break;
            }
            Auth::requerirAdmin();
            $controlador = new $modulos[$modulo]();
            if (!in_array($accion, $controlador->acciones(), true)) {
                pagina_no_encontrada();
                break;
            }
            $controlador->{$accion}();
    }
} catch (Throwable $e) {
    error_log('[ferias_sacaba] ' . $e);
    mostrar_error($e);
}
