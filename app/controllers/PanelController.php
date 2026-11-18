<?php
declare(strict_types=1);

final class PanelController
{
    public function index(): void
    {
        $resumen = [
            'carpas_totales' => 0,
            'carpas_disponibles' => 0,
            'reservas_pendientes' => (new Solicitud())->contarPendientes(),
            'expositores' => (new Expositor())->contar(),
        ];
        $ubicacion = new UbicacionService();
        foreach ((new Feria())->listar() as $feria) {
            $plano = $ubicacion->planoDeFeria((int) $feria['id']);
            $resumen['carpas_totales'] += $plano['total'];
            $resumen['carpas_disponibles'] += $plano['libres'];
        }

        vista('admin/inicio', [
            'c' => $resumen,
            'ultimas_reservas' => array_slice((new Solicitud())->listar(), 0, 8),
        ]);
    }

    public function reservas(): void
    {
        $filtros = [];
        $feriaId = pedir_id(pedir_get('feria_id'));
        if ($feriaId !== null) {
            $filtros['feria_id'] = $feriaId;
        }
        $estado = pedir_get('estado');
        if (array_key_exists($estado, Solicitud::ESTADOS)) {
            $filtros['estado'] = $estado;
        }

        vista('admin/reservas', [
            'solicitudes' => (new Solicitud())->listar($filtros),
            'ferias' => (new Feria())->listar(),
            'estados' => Solicitud::ESTADOS,
            'filtros' => $filtros,
        ]);
    }

    public function mapaCarpas(): void
    {
        $ferias = (new Feria())->listar();
        $feriaId = pedir_id(pedir_get('feria_id'));
        $feria = null;
        foreach ($ferias as $item) {
            if ($feriaId === (int) $item['id']) {
                $feria = $item;
                break;
            }
        }
        if ($feria === null && $ferias !== []) {
            $feria = $ferias[0];
            $feriaId = (int) $feria['id'];
        }

        $plano = $feriaId !== null ? (new UbicacionService())->planoDeFeria($feriaId) : null;
        vista('admin/mapa', ['ferias' => $ferias, 'feria' => $feria, 'plano' => $plano]);
    }

    public function expositores(): void
    {
        $q = pedir_get('q');
        $expositores = (new Expositor())->listar();
        if ($q !== '') {
            $expositores = array_values(array_filter($expositores, static function (array $e) use ($q): bool {
                return stripos((string) $e['emprendimiento'], $q) !== false
                    || stripos((string) $e['titular'], $q) !== false
                    || stripos((string) $e['email'], $q) !== false;
            }));
        }
        vista('admin/expositores', ['expositores' => $expositores, 'q' => $q]);
    }

    public function ferias(): void
    {
        vista('admin/ferias', ['ferias' => (new Feria())->listar()]);
    }

    public function configuracion(): void
    {
        $errores = [];
        $config = Configuracion::leer('correo');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verificar_csrf('admin/configuracion');
            if (pedir_post('guardar_correo') === '1') {
                $driver = pedir_post('driver');
                $host = pedir_post('host');
                $puerto = filter_var(pedir_post('puerto'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
                $seguridad = pedir_post('seguridad');
                $usuario = pedir_post('usuario');
                $clave = pedir_post_crudo('clave');
                if (!in_array($driver, ['log', 'smtp'], true)) {
                    $errores[] = 'Seleccione un modo de correo válido.';
                }
                if ($driver === 'smtp' && ($host === '' || $puerto === false || !in_array($seguridad, ['tls', 'ssl', 'ninguna'], true))) {
                    $errores[] = 'Complete el servidor, puerto y seguridad SMTP.';
                }
                if ($errores === []) {
                    $cambios = [
                        'driver' => $driver,
                        'host' => $host,
                        'puerto' => $puerto === false ? (int) ($config['puerto'] ?? 587) : $puerto,
                        'seguridad' => $seguridad,
                        'usuario' => $usuario,
                    ];
                    if ($clave !== '') {
                        $cambios['clave'] = $clave;
                    }
                    if (!Configuracion::guardar('correo', $cambios)) {
                        $errores[] = 'No se pudo guardar la configuración. Revise permisos de config/.';
                    } else {
                        flash('ok', 'Configuración de correo guardada.');
                        redirigir('admin/configuracion');
                    }
                }
                $config = array_replace($config, [
                    'driver' => $driver,
                    'host' => $host,
                    'puerto' => $puerto === false ? '' : $puerto,
                    'seguridad' => $seguridad,
                    'usuario' => $usuario,
                ]);
            } elseif (pedir_post('guardar_pasillo') === '1') {
                $pasillo = filter_var(str_replace(',', '.', pedir_post('pasillo_defecto_m')), FILTER_VALIDATE_FLOAT);
                if ($pasillo === false || $pasillo <= 0 || $pasillo > 20) {
                    $errores[] = 'El pasillo debe ser mayor que 0 y no superar 20 metros.';
                } elseif (!Configuracion::guardar('feria', ['pasillo_defecto_m' => $pasillo])) {
                    $errores[] = 'No se pudo guardar la medida. Revise permisos de config/.';
                } else {
                    flash('ok', 'Pasillo por defecto actualizado para las nuevas áreas.');
                    redirigir('admin/configuracion');
                }
            } elseif (pedir_post('probar_correo') === '1') {
                $email = pedir_post('email_prueba');
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errores[] = 'Escriba un correo válido para la prueba.';
                } elseif ((new NotificacionService())->prueba(['email' => $email, 'nombre' => 'Prueba'])) {
                    flash('ok', 'Prueba registrada o enviada correctamente.');
                    redirigir('admin/configuracion');
                } else {
                    $errores[] = 'No se pudo enviar la prueba. Revise logs/correo.log.';
                }
            }
        }

        $carpa = (new TipoCarpa())->oficial();
        $administradores = (new Usuario())->administradoresActivos();
        $configFeria = Configuracion::leer('feria');
        vista('admin/configuracion', [
            'config' => $config,
            'carpa' => $carpa,
            'pasillo_default' => (float) ($configFeria['pasillo_defecto_m'] ?? 1.50),
            'administradores' => $administradores,
            'errores' => $errores,
        ]);
    }
}
