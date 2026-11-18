<?php
declare(strict_types=1);

/**
 * Correos de las reservas. Recibe la reserva completa (Solicitud::buscarDetalle)
 * y arma cada mensaje en HTML sencillo con su versión de texto.
 *
 *   A la administración: su correo es el de cada cuenta de administrador activa.
 *   Al expositor: el correo con el que se registró.
 *
 * Ninguna dirección está escrita en el código. Cada método devuelve true si
 * el correo salió (o se anotó en modo log) y false si falló; la reserva ya
 * está guardada antes de llamar a este servicio.
 */
final class NotificacionService
{
    private CorreoService $correo;

    public function __construct(?CorreoService $correo = null)
    {
        $this->correo = $correo ?? new CorreoService();
    }

    public function correo(): CorreoService
    {
        return $this->correo;
    }

    /** Reserva nueva: aviso a la administración y acuse al expositor (misma conexión). */
    public function reservaCreada(array $r): bool
    {
        $aviso = $this->avisarAdministracion(
            $r,
            'Nueva reserva · ' . $r['feria'] . ' · Carpa ' . $r['codigo'],
            'Nueva reserva de carpa',
            'Un expositor reservó una carpa y espera su confirmación.',
            ['Ingrese al panel de Ferias Sacaba y abra «Reservas».', 'Confirme o rechace la reserva: el expositor recibirá un correo con la respuesta.']
        );
        return $this->reservaRecibida($r) && $aviso;
    }

    /** Al expositor: «Recibimos tu reserva». */
    public function reservaRecibida(array $r): bool
    {
        return $this->alExpositor(
            $r,
            'Recibimos tu reserva · ' . $r['feria'],
            'Recibimos tu reserva',
            'Hola, ' . $r['titular'] . '. Tu carpa quedó retenida a tu nombre mientras la administración revisa la reserva.',
            $this->datosParaExpositor($r, (string) $r['codigo']),
            [
                'Espera el correo de confirmación de la administración.',
                'Puedes ver el estado de tu reserva en «Mis reservas».',
                'Si ya no participarás, cancela la reserva desde «Mis reservas» para liberar la carpa.',
            ]
        );
    }

    /** Al expositor: «Reserva confirmada». */
    public function reservaConfirmada(array $r): bool
    {
        return $this->alExpositor(
            $r,
            'Reserva confirmada · ' . $r['feria'] . ' · Carpa ' . $r['codigo'],
            'Tu reserva está confirmada',
            'Hola, ' . $r['titular'] . '. La administración confirmó tu carpa para la feria.',
            $this->datosParaExpositor($r, (string) $r['codigo']),
            [
                'Guarda tu código de carpa: ' . $r['codigo'] . '.',
                'El día de la feria preséntate en el lugar indicado y ubica la carpa con ese código.',
                'Si no podrás asistir, avisa a la administración para que otra persona pueda usar la carpa.',
            ]
        );
    }

    /** Al expositor: «Reserva no disponible» (rechazo del administrador). */
    public function reservaRechazada(array $r): bool
    {
        $datos = $this->datosParaExpositor($r, (string) ($r['codigo_liberado'] ?? ''));
        if (!empty($r['motivo_rechazo'])) {
            $datos['Motivo'] = (string) $r['motivo_rechazo'];
        }
        return $this->alExpositor(
            $r,
            'Reserva no disponible · ' . $r['feria'],
            'Tu reserva no pudo confirmarse',
            'Hola, ' . $r['titular'] . '. La administración no pudo confirmar tu reserva y la carpa quedó liberada.',
            $datos,
            [
                'Si todavía hay carpas libres, puedes ingresar y elegir otra.',
                'Si tienes dudas, comunícate con la administración de la feria.',
            ]
        );
    }

    /** A la administración: el expositor canceló y la carpa volvió a estar libre. */
    public function reservaCancelada(array $r): bool
    {
        $codigo = (string) ($r['codigo_liberado'] ?? '');
        return $this->avisarAdministracion(
            $r,
            'Reserva cancelada · ' . $r['feria'] . ($codigo !== '' ? ' · Carpa ' . $codigo : ''),
            'Reserva cancelada por el expositor',
            'El expositor canceló su reserva. La carpa volvió a estar libre.',
            ['No necesita hacer nada: la carpa ya aparece disponible en el plano.']
        );
    }

    /** Vuelve a enviar al expositor el correo que corresponde al estado actual. */
    public function reenviar(array $r): bool
    {
        switch ($r['estado_reserva']) {
            case 'pendiente':
                return $this->reservaRecibida($r);
            case 'confirmada':
                return $this->reservaConfirmada($r);
            case 'rechazada':
                return $this->reservaRechazada($r);
            default:
                return false;
        }
    }

    /** Correo de prueba desde Configuración. */
    public function prueba(array $destinatario): bool
    {
        [$html, $texto] = $this->plantilla(
            'Prueba de correo',
            'Si está leyendo este mensaje, el envío de correos de Ferias Sacaba funciona.',
            ['Enviado' => fecha_hora(date('Y-m-d H:i:s'))],
            ['A este correo llegarán los avisos de cada reserva nueva.']
        );
        return $this->correo->enviar([$destinatario], 'Prueba de correo · Ferias Sacaba', $html, $texto);
    }

    // ----------------------------------------------------------

    private function alExpositor(array $r, string $asunto, string $titulo, string $introduccion, array $datos, array $pasos): bool
    {
        [$html, $texto] = $this->plantilla($titulo, $introduccion, $datos, $pasos);
        return $this->correo->enviar(
            [['email' => (string) $r['email'], 'nombre' => (string) $r['titular']]],
            $asunto,
            $html,
            $texto
        );
    }

    /** Correo con todos los datos del expositor y de la carpa para cada administrador activo. */
    private function avisarAdministracion(array $r, string $asunto, string $titulo, string $introduccion, array $pasos): bool
    {
        $destinatarios = [];
        foreach ((new Usuario())->administradoresActivos() as $admin) {
            $destinatarios[] = ['email' => (string) $admin['email'], 'nombre' => (string) $admin['nombre']];
        }
        $codigo = (string) ($r['codigo'] ?? '') !== '' ? (string) $r['codigo'] : (string) ($r['codigo_liberado'] ?? '');
        [$html, $texto] = $this->plantilla($titulo, $introduccion, [
            'Expositor'      => (string) $r['titular'],
            'Emprendimiento' => (string) $r['emprendimiento'],
            'Correo'         => (string) $r['email'],
            'Teléfono'       => (string) $r['telefono'],
            'Feria'          => (string) $r['feria'],
            'Fecha'          => fecha_larga((string) $r['feria_fecha']),
            'Lugar'          => (string) $r['feria_lugar'],
            'Rubro'          => (string) $r['categoria'],
            'Carpa'          => $codigo,
            'Medida'         => $this->medida($r),
            'Estado'         => nombre_estado((string) $r['estado_reserva']),
        ], $pasos);
        return $this->correo->enviar($destinatarios, $asunto, $html, $texto);
    }

    private function datosParaExpositor(array $r, string $codigo): array
    {
        return [
            'Código de carpa' => $codigo,
            'Feria'           => (string) $r['feria'],
            'Fecha'           => fecha_larga((string) $r['feria_fecha']),
            'Lugar'           => (string) $r['feria_lugar'],
            'Rubro'           => (string) $r['categoria'],
            'Medida'          => $this->medida($r),
            'Estado'          => nombre_estado((string) $r['estado_reserva']),
        ];
    }

    /** "3 × 4 m (12 m²)" con la medida de la carpa de la reserva. */
    private function medida(array $r): string
    {
        $carpa = ['ancho_m' => $r['carpa_ancho'], 'largo_m' => $r['carpa_largo']];
        return medida_carpa($carpa) . ' (' . superficie_carpa($carpa) . ')';
    }

    /** Devuelve [HTML, texto] con el mismo contenido. Los datos vacíos se omiten. */
    private function plantilla(string $titulo, string $introduccion, array $datos, array $pasos): array
    {
        $filasHtml = '';
        $filasTexto = '';
        foreach ($datos as $etiqueta => $valor) {
            if ((string) $valor === '') {
                continue;
            }
            $filasHtml .= '<tr>'
                . '<td style="padding:6px 16px 6px 0;color:#5f6b7a;vertical-align:top;white-space:nowrap">' . e($etiqueta) . '</td>'
                . '<td style="padding:6px 0;color:#1b2733;font-weight:bold">' . e($valor) . '</td>'
                . '</tr>';
            $filasTexto .= $etiqueta . ': ' . $valor . "\n";
        }
        $pasosHtml = '';
        $pasosTexto = '';
        foreach ($pasos as $paso) {
            $pasosHtml .= '<li style="margin:0 0 6px">' . e($paso) . '</li>';
            $pasosTexto .= '- ' . $paso . "\n";
        }

        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . e($titulo) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f6f9;font-family:Segoe UI,Arial,sans-serif;font-size:15px;color:#1b2733">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #dfe4ea">'
            . '<tr><td style="background:#0b2a4a;color:#ffffff;padding:16px 24px"><strong>Ferias Sacaba</strong> · Municipio de Sacaba</td></tr>'
            . '<tr><td style="padding:24px">'
            . '<h1 style="margin:0 0 12px;font-size:20px;color:#0b2a4a">' . e($titulo) . '</h1>'
            . '<p style="margin:0 0 16px;line-height:1.5">' . e($introduccion) . '</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;margin:0 0 16px">' . $filasHtml . '</table>'
            . ($pasosHtml !== '' ? '<p style="margin:0 0 8px;font-weight:bold;color:#0b2a4a">Qué sigue</p><ul style="margin:0;padding-left:20px;line-height:1.5">' . $pasosHtml . '</ul>' : '')
            . '</td></tr>'
            . '<tr><td style="padding:14px 24px;border-top:1px solid #dfe4ea;color:#5f6b7a;font-size:12px">'
            . 'Mensaje automático de Ferias Sacaba, la plataforma de reservas de carpas del Municipio de Sacaba.'
            . '</td></tr></table></td></tr></table></body></html>';

        $texto = "FERIAS SACABA · Municipio de Sacaba\n\n"
            . $titulo . "\n\n"
            . $introduccion . "\n\n"
            . $filasTexto
            . ($pasosTexto !== '' ? "\nQué sigue:\n" . $pasosTexto : '')
            . "\nMensaje automático de Ferias Sacaba, la plataforma de reservas de carpas del Municipio de Sacaba.\n";

        return [$html, $texto];
    }
}
