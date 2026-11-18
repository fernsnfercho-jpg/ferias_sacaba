<?php
declare(strict_types=1);

final class FeriaSacabaController
{
    public function configurar(): void
    {
        Auth::requerirAdmin();
        $db = Database::conectar();
        $ferias = $db->query('SELECT id, nombre, fecha, estado FROM ferias ORDER BY fecha DESC, id DESC')->fetchAll();
        $feriaId = filter_var(pedir_get('feria_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($feriaId === false || $feriaId === null) {
            $feriaId = isset($ferias[0]) ? (int) $ferias[0]['id'] : 0;
        }
        $feria = null;
        foreach ($ferias as $item) {
            if ((int) $item['id'] === $feriaId) {
                $feria = $item;
                break;
            }
        }
        if ($feria === null) {
            flash('error', 'Registra una feria antes de configurar su espacio.');
            redirigir('ferias/nuevo');
        }

        $areas = (new Area())->listar(['feria_id' => $feriaId]);
        $guardado = null;
        foreach ($areas as $area) {
            $guardado = (new ConfiguracionFeria())->paraFeria($feriaId);
            if ($guardado !== null) {
                break;
            }
        }
        if ($guardado === null && $areas !== []) {
            $primera = $areas[0];
            $guardado = [
                'largo_total_m' => $primera['largo_m'],
                'ancho_total_m' => $primera['ancho_m'],
                'ancho_pasillo_m' => $primera['pasillo_m'],
                'entrada_m2' => 0,
                'escenario_m2' => 0,
                'tipo_carpa_id' => $this->tipoCarpaBase(),
            ];
        }
        $errores = [];
        $valores = $guardado ?? [
            'largo_total_m' => '',
            'ancho_total_m' => '',
            'ancho_pasillo_m' => '',
            'entrada_m2' => '0',
            'escenario_m2' => '0',
            'tipo_carpa_id' => $this->tipoCarpaBase(),
        ];
        $imagenPlanoActual = $guardado['imagen_plano'] ?? null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verificar_csrf('configurar-feria');
            $imagenSubida = null;
            $quitarImagen = pedir_post('quitar_imagen') === '1';
            $valores = [
                'largo_total_m' => pedir_post('largo_total_m'),
                'ancho_total_m' => pedir_post('ancho_total_m'),
                'ancho_pasillo_m' => pedir_post('ancho_pasillo_m'),
                'entrada_m2' => pedir_post('entrada_m2'),
                'escenario_m2' => pedir_post('escenario_m2'),
            ];
            $numeros = [];
            foreach (['largo_total_m', 'ancho_total_m', 'ancho_pasillo_m', 'entrada_m2', 'escenario_m2'] as $campo) {
                $numero = filter_var(str_replace(',', '.', $valores[$campo]), FILTER_VALIDATE_FLOAT);
                if ($numero === false || !is_finite((float) $numero) || (float) $numero < 0
                    || (in_array($campo, ['largo_total_m', 'ancho_total_m'], true) && (float) $numero <= 0)) {
                    $errores[$campo] = 'Ingresa una medida válida mayor o igual a cero.';
                } else {
                    $numeros[$campo] = (float) $numero;
                }
            }
            $tipoId = $this->tipoCarpaBase();
            if ($quitarImagen && (int) ($_FILES['imagen_plano']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $errores['imagen_plano'] = 'Elige una imagen nueva o marca quitar la imagen, pero no ambas opciones.';
            } elseif ((int) ($_FILES['imagen_plano']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $errorImagen = null;
                $imagenSubida = $this->validarImagenSubida($_FILES['imagen_plano'], $errorImagen);
                if ($errorImagen !== null) {
                    $errores['imagen_plano'] = $errorImagen;
                }
            }
            if ($areas === []) {
                $errores['espacio'] = 'Crea primero al menos un área para la feria desde Ferias → Áreas.';
            }
            if ($numeros !== []) {
                $ancho = $numeros['ancho_total_m'] ?? 0;
                $largo = $numeros['largo_total_m'] ?? 0;
                $reservado = ($numeros['entrada_m2'] ?? 0) + ($numeros['escenario_m2'] ?? 0);
                if ($ancho <= 0 || $largo <= 0 || $numeros['ancho_pasillo_m'] >= $largo) {
                    $errores['ancho_pasillo_m'] = 'El pasillo debe ser menor que el largo del terreno.';
                } elseif ($reservado + ($ancho * $numeros['ancho_pasillo_m']) >= $ancho * $largo) {
                    $errores['entrada_m2'] = 'El pasillo, la entrada y el escenario deben dejar espacio útil para los puestos.';
                }
            }

            if ($errores === []) {
                $largoReservado = ($numeros['entrada_m2'] + $numeros['escenario_m2']) / $numeros['ancho_total_m'];
                $largoArea = $numeros['largo_total_m'] - $largoReservado;
                $servicio = new UbicacionService();
                $incompatibles = [];
                foreach ($areas as $area) {
                    foreach ((new Asignacion())->puestosDeArea((int) $area['id']) as $puesto) {
                        $motivo = $servicio->motivoNoCabe(
                            (float) $puesto['x_m'],
                            (float) $puesto['y_m'],
                            (float) $puesto['ancho_m'],
                            (float) $puesto['largo_m'],
                            $numeros['ancho_total_m'],
                            $largoArea - $numeros['ancho_pasillo_m'],
                            []
                        );
                        if ($motivo !== null) {
                            $incompatibles[] = $puesto['codigo'];
                        }
                    }
                }
                if ($incompatibles !== []) {
                    $errores['espacio'] = 'No se guardó porque las nuevas medidas sacarían del área estos puestos: '
                        . implode(', ', $incompatibles) . '. Amplía el espacio o reubícalos primero.';
                } else {
                    $rutaImagenNueva = null;
                    try {
                        if ($imagenSubida !== null) {
                            $rutaImagenNueva = $this->guardarImagen($imagenSubida);
                        }
                        $imagenPlano = $rutaImagenNueva
                            ?? ($quitarImagen ? null : $imagenPlanoActual);
                        $db->beginTransaction();
                        foreach ($areas as $area) {
                            (new Area())->actualizar((int) $area['id'], [
                                'ancho_m' => number_format($numeros['ancho_total_m'], 2, '.', ''),
                                'largo_m' => number_format($largoArea, 2, '.', ''),
                                'pasillo_m' => number_format($numeros['ancho_pasillo_m'], 2, '.', ''),
                            ]);
                        }
                        (new ConfiguracionFeria())->guardar([
                            'feria_id' => $feriaId,
                            'largo_total_m' => number_format($numeros['largo_total_m'], 2, '.', ''),
                            'ancho_total_m' => number_format($numeros['ancho_total_m'], 2, '.', ''),
                            'ancho_pasillo_m' => number_format($numeros['ancho_pasillo_m'], 2, '.', ''),
                            'entrada_m2' => number_format($numeros['entrada_m2'], 2, '.', ''),
                            'escenario_m2' => number_format($numeros['escenario_m2'], 2, '.', ''),
                            'tipo_carpa_id' => $tipoId,
                            'imagen_plano' => $imagenPlano,
                        ]);
                        $db->commit();
                        if (($rutaImagenNueva !== null || $quitarImagen) && $imagenPlanoActual !== null) {
                            $this->eliminarImagen($imagenPlanoActual);
                        }
                        flash('ok', 'Configuración guardada. La foto publicada y el plano de puestos ya usan estas medidas.');
                        redirigir('configurar-feria', ['feria_id' => $feriaId]);
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        if ($rutaImagenNueva !== null) {
                            $this->eliminarImagen($rutaImagenNueva);
                        }
                        throw $e;
                    }
                }
            }
        }

        vista('publico/configurar_feria', [
            'ferias' => $ferias,
            'feria' => $feria,
            'areas' => $areas,
            'errores' => $errores,
            'valores' => $valores,
            'imagenPlanoActual' => $imagenPlanoActual,
        ]);
    }

    public function mapa(): void
    {
        $usuario = Auth::usuario();
        if ($usuario === null) {
            redirigir('login');
        }
        $administrador = (new Usuario())->buscarAdministradorActivo((int) $usuario['id']);
        if ($administrador === null) {
            Auth::requerirExpositor();
        } else {
            Auth::requerirAdmin();
        }

        $ferias = Database::conectar()->query('SELECT * FROM ferias ORDER BY (estado = \'abierta\') DESC, fecha DESC, id DESC')->fetchAll();
        $feriaId = filter_var(pedir_get('feria_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $feria = null;
        foreach ($ferias as $item) {
            if ($feriaId !== false && $feriaId !== null && (int) $item['id'] === $feriaId) {
                $feria = $item;
                break;
            }
        }
        $feria ??= $ferias[0] ?? null;
        if ($feria === null) {
            flash('aviso', 'Todavía no hay ferias registradas.');
            redirigir($administrador !== null ? 'panel' : 'mi-espacio');
        }

        $areas = (new Area())->listar(['feria_id' => (int) $feria['id']]);
        $puestos = (new Asignacion())->puestosDeFeria((int) $feria['id']);
        $configuracion = (new ConfiguracionFeria())->paraFeria((int) $feria['id']);
        $feria['imagen_plano'] = $configuracion['imagen_plano'] ?? null;
        $tipoCarpa = (new TipoCarpa())->oficial();
        $carpaAncho = (float) $tipoCarpa['ancho_m'];
        $carpaLargo = (float) $tipoCarpa['largo_m'];
        $capacidad = 0;
        foreach ($areas as $area) {
            $capacidad += (int) floor((float) $area['ancho_m'] / $carpaAncho)
                * (int) floor(((float) $area['largo_m'] - (float) $area['pasillo_m']) / $carpaLargo);
        }
        if ($administrador === null) {
            $perfil = Auth::requerirExpositor();
            $miIds = [];
            foreach ((new Solicitud())->deExpositor((int) $perfil['id']) as $solicitud) {
                if ($solicitud['asignacion_id'] !== null) {
                    $miIds[(int) $solicitud['asignacion_id']] = true;
                }
            }
        } else {
            $miIds = [];
        }
        foreach ($puestos as &$puesto) {
            $puesto['propio'] = isset($miIds[(int) $puesto['id']]);
        }
        unset($puesto);
        vista('publico/mapa_feria', [
            'ferias' => $ferias,
            'feria' => $feria,
            'areas' => $areas,
            'puestos' => $puestos,
            'capacidad' => $capacidad,
            'carpaAncho' => $carpaAncho,
            'carpaLargo' => $carpaLargo,
            'administrador' => $administrador !== null,
        ]);
    }

    private function tipoCarpaBase(): int
    {
        return (int) (new TipoCarpa())->oficial()['id'];
    }

    private function validarImagenSubida(array $archivo, ?string &$error): ?array
    {
        $error = null;
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = match ((int) $archivo['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el límite permitido por el servidor.',
                UPLOAD_ERR_PARTIAL => 'La imagen no se cargó completa. Inténtalo otra vez.',
                UPLOAD_ERR_NO_FILE => null,
                default => 'No se pudo recibir la imagen. Inténtalo otra vez.',
            };
            return null;
        }
        if (!isset($archivo['tmp_name'], $archivo['size'])
            || (int) $archivo['size'] <= 0 || (int) $archivo['size'] > 8 * 1024 * 1024
            || !is_uploaded_file($archivo['tmp_name'])) {
            $error = 'Selecciona una imagen válida de hasta 8 MB.';
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        $tipos = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $dimensiones = @getimagesize($archivo['tmp_name']);
        if (!isset($tipos[$mime]) || $dimensiones === false
            || $dimensiones[0] < 600 || $dimensiones[1] < 400
            || $dimensiones[0] * $dimensiones[1] > 20000000) {
            $error = 'Usa una foto JPG, PNG o WebP de al menos 600 × 400 px y hasta 20 megapíxeles.';
            return null;
        }
        return ['temporal' => $archivo['tmp_name'], 'extension' => $tipos[$mime]];
    }

    private function guardarImagen(array $imagen): string
    {
        $directorio = dirname(__DIR__, 2) . '/public/uploads/ferias';
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio para las fotos de las ferias.');
        }
        $nombre = bin2hex(random_bytes(16)) . '.' . $imagen['extension'];
        if (!move_uploaded_file($imagen['temporal'], $directorio . '/' . $nombre)) {
            throw new RuntimeException('No se pudo guardar la foto de la feria.');
        }
        return 'uploads/ferias/' . $nombre;
    }

    private function eliminarImagen(string $ruta): void
    {
        if (preg_match('~\Auploads/ferias/[a-f0-9]{32}\.(?:jpg|png|webp)\z~', $ruta) !== 1) {
            return;
        }
        $archivo = dirname(__DIR__, 2) . '/public/' . $ruta;
        if (is_file($archivo) && !unlink($archivo)) {
            error_log('[ferias_sacaba] No se pudo eliminar la foto reemplazada: ' . $ruta);
        }
    }
}
