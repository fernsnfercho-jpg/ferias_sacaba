<?php
declare(strict_types=1);

/**
 * Envío de correos según config/correo.php:
 *
 *   driver = 'log'  → no sale nada: cada correo se anota en logs/correo.log
 *                     (desarrollo sin internet).
 *   driver = 'smtp' → envío real con PHPMailer (app/vendor/PHPMailer).
 *
 * enviar() nunca lanza excepciones ni detiene la reserva: hace un solo
 * intento con tiempo límite, devuelve true o false y anota el resultado
 * en logs/correo.log. La contraseña nunca se escribe en el registro.
 */
final class CorreoService
{
    private array $config;
    private ?string $ultimoError = null;

    /** PHPMailer con la conexión abierta, para mandar varios correos seguidos. */
    private ?object $smtp = null;

    /** Si el servidor no respondió, no se vuelve a esperar en la misma petición. */
    private static bool $servidorCaido = false;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? Configuracion::leer('correo');
    }

    public function __destruct()
    {
        if ($this->smtp !== null) {
            try {
                $this->smtp->smtpClose();
            } catch (Throwable $e) {
                // Cerrar la conexión es solo cortesía con el servidor.
            }
        }
    }

    public function esSmtp(): bool
    {
        return ($this->config['driver'] ?? 'log') === 'smtp';
    }

    /** Motivo del último fallo, en una línea, o null si salió bien. */
    public function ultimoError(): ?string
    {
        return $this->ultimoError;
    }

    /**
     * Envía un correo HTML con texto alternativo, en UTF-8.
     * $destinatarios: [['email' => 'a@b.c', 'nombre' => 'Ana'], ...]
     */
    public function enviar(array $destinatarios, string $asunto, string $html, string $texto): bool
    {
        $this->ultimoError = null;
        $validos = [];
        foreach ($destinatarios as $d) {
            $email = trim((string) ($d['email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $validos[] = ['email' => $email, 'nombre' => self::unaLinea((string) ($d['nombre'] ?? ''))];
            }
        }
        if ($validos === []) {
            return $this->fallar($destinatarios, $asunto, 'No hay un destinatario con correo válido.');
        }

        if (!$this->esSmtp()) {
            $this->anotar('REGISTRADO (modo log, no se envió)', $validos, $asunto, $texto);
            return true;
        }
        if (self::$servidorCaido) {
            return $this->fallar($validos, $asunto, 'No se intentó: el servidor de correo no respondió hace un momento.');
        }

        try {
            $mail = $this->smtp ?? $this->conectar();
            $mail->clearAllRecipients();
            foreach ($validos as $d) {
                $mail->addAddress($d['email'], $d['nombre']);
            }
            $mail->Subject = self::unaLinea($asunto);
            $mail->Body = $html;
            $mail->AltBody = $texto;
            $mail->send();
            $this->anotar('ENVIADO', $validos, $asunto);
            return true;
        } catch (Throwable $e) {
            // Sin conexión (servidor apagado, clave rechazada...): no insistir en esta petición.
            try {
                $conectado = $this->smtp !== null && $this->smtp->getSMTPInstance()->connected();
            } catch (Throwable $otro) {
                $conectado = false;
            }
            if (!$conectado) {
                self::$servidorCaido = true;
                $this->smtp = null;
            }
            return $this->fallar($validos, $asunto, $e->getMessage());
        }
    }

    /** Prepara PHPMailer con los datos de config/correo.php. */
    private function conectar(): object
    {
        $c = $this->config;
        $remitente = trim((string) ($c['remitente_email'] ?? ''));
        if ($remitente === '') {
            $remitente = trim((string) ($c['usuario'] ?? ''));
        }
        if (trim((string) ($c['host'] ?? '')) === '' || filter_var($remitente, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Falta completar el servidor o el correo remitente en Configuración → Envío de correos.');
        }

        $carpeta = RAIZ_PROYECTO . '/app/vendor/PHPMailer/src/';
        require_once $carpeta . 'Exception.php';
        require_once $carpeta . 'PHPMailer.php';
        require_once $carpeta . 'SMTP.php';

        $limite = max(3, min(30, (int) ($c['tiempo_limite'] ?? 10)));
        $seguridad = (string) ($c['seguridad'] ?? 'tls');

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->setLanguage('es');
        $mail->isSMTP();
        $mail->Host = trim((string) $c['host']);
        $mail->Port = (int) ($c['puerto'] ?? 587);
        $mail->SMTPSecure = $seguridad === 'ssl' ? 'ssl' : ($seguridad === 'tls' ? 'tls' : '');
        $mail->SMTPAutoTLS = $seguridad !== 'ninguna';
        $mail->SMTPAuth = (string) ($c['usuario'] ?? '') !== '';
        $mail->Username = (string) ($c['usuario'] ?? '');
        $mail->Password = (string) ($c['clave'] ?? '');
        $mail->Timeout = $limite;                       // un solo intento con tiempo límite
        $mail->getSMTPInstance()->Timelimit = $limite;
        $mail->SMTPKeepAlive = true;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);
        $mail->setFrom($remitente, self::unaLinea((string) ($c['remitente_nombre'] ?? '')));
        $responder = trim((string) ($c['responder_a'] ?? ''));
        if (filter_var($responder, FILTER_VALIDATE_EMAIL) !== false) {
            $mail->addReplyTo($responder);
        }
        return $this->smtp = $mail;
    }

    private function fallar(array $destinatarios, string $asunto, string $motivo): bool
    {
        $this->ultimoError = function_exists('mb_substr')
            ? mb_substr(self::unaLinea(strip_tags($motivo)), 0, 300, 'UTF-8')
            : substr(self::unaLinea(strip_tags($motivo)), 0, 300);
        $this->anotar('ERROR', $destinatarios, $asunto, 'Motivo: ' . $this->ultimoError);
        return false;
    }

    /** Agrega una entrada a logs/correo.log. */
    private function anotar(string $resultado, array $destinatarios, string $asunto, string $detalle = ''): void
    {
        $para = [];
        foreach ($destinatarios as $d) {
            $email = self::unaLinea((string) ($d['email'] ?? ''));
            $nombre = self::unaLinea((string) ($d['nombre'] ?? ''));
            $para[] = $nombre !== '' ? $nombre . ' <' . $email . '>' : $email;
        }
        $entrada = sprintf(
            '[%s] %s | Para: %s | Asunto: %s',
            date('Y-m-d H:i:s'),
            $resultado,
            implode(', ', $para),
            self::unaLinea($asunto)
        );
        if ($detalle !== '') {
            $entrada .= PHP_EOL . (string) preg_replace('/^/m', '    ', trim($detalle));
        }
        @file_put_contents(RAIZ_PROYECTO . '/logs/correo.log', $entrada . PHP_EOL . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** Quita saltos de línea (evita que un dato del usuario invente cabeceras o entradas del registro). */
    private static function unaLinea(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }
}
