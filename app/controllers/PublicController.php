<?php
declare(strict_types=1);

final class PublicController
{
    public function inicio(): void
    {
        $contacto = trim((string) Configuracion::leer('feria')['contacto_email']);
        if ($contacto === '') {
            $administradores = (new Usuario())->administradoresActivos();
            $contacto = (string) ($administradores[0]['email'] ?? '');
        }
        vista('publico/inicio', [
            'ferias' => (new Feria())->abiertasParaInscripcion(),
            'usuario' => Auth::usuario(),
            'contacto' => $contacto,
        ]);
    }
}
