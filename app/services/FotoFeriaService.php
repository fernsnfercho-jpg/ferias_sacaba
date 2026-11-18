<?php
declare(strict_types=1);

/**
 * Foto del lugar de una feria: validación de tipo, peso y dimensiones,
 * guardado con nombre aleatorio en public/uploads/ferias y borrado seguro.
 */
final class FotoFeriaService
{
    private const PESO_MAXIMO = 8 * 1024 * 1024;
    private const TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const RUTA_VALIDA = '~\Auploads/ferias/[a-f0-9]{32}\.(?:jpg|png|webp)\z~';

    /**
     * Revisa el archivo recibido ($_FILES['foto']). Devuelve los datos para
     * guardar() o null; si hubo un problema lo explica en $error.
     */
    public function validar(array $archivo, ?string &$error): ?array
    {
        $error = null;
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = match ((int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE)) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el límite permitido por el servidor.',
                UPLOAD_ERR_PARTIAL => 'La imagen no se cargó completa. Inténtelo otra vez.',
                UPLOAD_ERR_NO_FILE => null,
                default => 'No se pudo recibir la imagen. Inténtelo otra vez.',
            };
            return null;
        }
        if (!isset($archivo['tmp_name'], $archivo['size'])
            || (int) $archivo['size'] <= 0 || (int) $archivo['size'] > self::PESO_MAXIMO
            || !is_uploaded_file($archivo['tmp_name'])) {
            $error = 'Seleccione una imagen válida de hasta 8 MB.';
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        $dimensiones = @getimagesize($archivo['tmp_name']);
        if (!isset(self::TIPOS[$mime]) || $dimensiones === false
            || $dimensiones[0] < 600 || $dimensiones[1] < 400
            || $dimensiones[0] * $dimensiones[1] > 20000000) {
            $error = 'Use una foto JPG, PNG o WebP de al menos 600 × 400 px y hasta 20 megapíxeles.';
            return null;
        }
        return ['temporal' => $archivo['tmp_name'], 'extension' => self::TIPOS[$mime]];
    }

    /** Guarda la imagen validada y devuelve su ruta dentro de public/. */
    public function guardar(array $imagen): string
    {
        $directorio = RAIZ_PROYECTO . '/public/uploads/ferias';
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear la carpeta para las fotos de las ferias.');
        }
        $nombre = bin2hex(random_bytes(16)) . '.' . $imagen['extension'];
        if (!move_uploaded_file($imagen['temporal'], $directorio . '/' . $nombre)) {
            throw new RuntimeException('No se pudo guardar la foto de la feria.');
        }
        return 'uploads/ferias/' . $nombre;
    }

    /** Solo acepta rutas con la forma que genera guardar(). */
    public function esRutaValida(?string $ruta): bool
    {
        return $ruta !== null && preg_match(self::RUTA_VALIDA, $ruta) === 1;
    }

    public function eliminar(string $ruta): void
    {
        if (!$this->esRutaValida($ruta)) {
            return;
        }
        $archivo = RAIZ_PROYECTO . '/public/' . $ruta;
        if (is_file($archivo) && !unlink($archivo)) {
            error_log('[ferias_sacaba] No se pudo eliminar la foto reemplazada: ' . $ruta);
        }
    }
}
