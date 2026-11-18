<?php
// ============================================================
// DATOS GENERALES DE LAS FERIAS
//
// La medida de la carpa oficial NO está aquí: es el único tipo activo de
// la tabla tipos_carpa (3 m de frente × 4 m de fondo).
//
// Estos valores se cambian desde el panel (Configuración → Carpa y pasillo);
// el sistema guarda los cambios en config/feria.local.php.
// ============================================================
return [
    // Pasillo que se propone al crear una zona, en metros. Cada zona guarda
    // el suyo en la tabla areas; si la Alcaldía confirma otra medida, se
    // cambia aquí y en las zonas que correspondan.
    'pasillo_defecto_m' => 1.50,

    // Correo público de contacto para el pie de la página. Vacío = no se muestra.
    // No use el correo de la cuenta del administrador.
    'contacto_email'    => '',
];
