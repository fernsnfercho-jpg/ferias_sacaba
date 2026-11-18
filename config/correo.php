<?php
// ============================================================
// CONFIGURACIÓN DEL CORREO
//
// Este archivo es el ejemplo: NO escriba aquí contraseñas reales.
// Los datos reales se guardan en config/correo.local.php, que el sistema
// crea al guardar en el panel: Configuración → Envío de correos.
// (También puede crearlo a mano copiando este archivo con ese nombre;
//  solo necesita las claves que quiera cambiar.)
//
//  driver = 'log'   Desarrollo sin internet: los correos no salen, se
//                   anotan en logs/correo.log y la reserva funciona igual.
//  driver = 'smtp'  Envío real. Con Gmail use una «contraseña de
//                   aplicación» (16 letras), no la contraseña normal.
//                   Los pasos están en LEEME.txt.
// ============================================================
return [
    'driver'           => 'log',             // 'log' o 'smtp'
    'host'             => 'smtp.gmail.com',  // servidor de salida
    'puerto'           => 587,               // 587 con 'tls', 465 con 'ssl'
    'usuario'          => '',                // cuenta completa con la que se inicia sesión en el servidor
    'clave'            => '',                // contraseña de aplicación (nunca la suba a un repositorio)
    'seguridad'        => 'tls',             // 'tls', 'ssl' o 'ninguna'
    'remitente_email'  => '',                // correo que aparece como remitente (con Gmail, la misma cuenta)
    'remitente_nombre' => 'Ferias Sacaba',   // nombre que aparece como remitente
    'responder_a'      => '',                // correo que recibe las respuestas (opcional)
    'tiempo_limite'    => 10,                // segundos de espera al servidor: un solo intento
];
