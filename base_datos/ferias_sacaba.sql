-- ============================================================
-- FERIAS SACABA · BASE DE DATOS ÚNICA (instalación limpia)
-- ------------------------------------------------------------
-- Este es el ÚNICO archivo SQL que debe importar.
--
-- Cómo importarlo:
--   1. Abra phpMyAdmin (http://localhost/phpmyadmin).
--   2. Pestaña «Importar» (NO hace falta crear la base antes).
--   3. Elija este archivo y pulse «Continuar».
--
-- El script elimina una base «ferias_sacaba» anterior y la crea de
-- nuevo con todas las tablas, columnas, relaciones y datos de
-- demostración. Se puede importar las veces que haga falta y el
-- resultado siempre es el mismo (no quedan tablas viejas ni errores).
--
-- Compatible con MySQL 8 y con MariaDB 10.3 o superior (XAMPP).
-- ============================================================

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS ferias_sacaba;
CREATE DATABASE ferias_sacaba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ferias_sacaba;

-- ------------------------------------------------------------
-- 1. Cuentas del sistema
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('administrador','usuario') NOT NULL DEFAULT 'usuario',
    activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Catálogos (instituciones, rubros y medida oficial de carpa)
-- ------------------------------------------------------------
CREATE TABLE instituciones (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tipos_carpa (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(80) NOT NULL,
    ancho_m DECIMAL(6,2) NOT NULL,
    largo_m DECIMAL(6,2) NOT NULL,
    activo  TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT chk_tipos_carpa_medidas CHECK (ancho_m > 0 AND largo_m > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Expositores (cada uno con su cuenta de usuario)
-- ------------------------------------------------------------
CREATE TABLE expositores (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT NOT NULL UNIQUE,
    institucion_id  INT NULL,
    emprendimiento  VARCHAR(120) NOT NULL,
    telefono        VARCHAR(15) NOT NULL,
    CONSTRAINT fk_expositores_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_expositores_institucion
        FOREIGN KEY (institucion_id) REFERENCES instituciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Ferias (con foto del lugar y estado de inscripción)
-- ------------------------------------------------------------
CREATE TABLE ferias (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(120) NOT NULL,
    fecha     DATE NOT NULL,
    lugar     VARCHAR(180) NOT NULL,
    latitud   DECIMAL(9,6) NULL,
    longitud  DECIMAL(9,6) NULL,
    foto      VARCHAR(255) NULL,
    estado    ENUM('borrador','abierta','cerrada') NOT NULL DEFAULT 'borrador',
    CONSTRAINT chk_ferias_latitud  CHECK (latitud  BETWEEN -90 AND 90),
    CONSTRAINT chk_ferias_longitud CHECK (longitud BETWEEN -180 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Áreas / zonas de cada feria
-- ------------------------------------------------------------
CREATE TABLE areas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    feria_id     INT NOT NULL,
    categoria_id INT NOT NULL,
    nombre       VARCHAR(80) NOT NULL,
    ancho_m      DECIMAL(6,2) NOT NULL,
    largo_m      DECIMAL(6,2) NOT NULL,
    pasillo_m    DECIMAL(6,2) NOT NULL DEFAULT 1.50,
    UNIQUE KEY uq_areas_nombre (feria_id, nombre),
    UNIQUE KEY uq_areas_id_feria (id, feria_id),
    CONSTRAINT fk_areas_feria
        FOREIGN KEY (feria_id) REFERENCES ferias(id),
    CONSTRAINT fk_areas_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    CONSTRAINT chk_areas_medidas
        CHECK (ancho_m > 0 AND largo_m > pasillo_m AND pasillo_m > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Solicitudes / reservas de carpa
-- ------------------------------------------------------------
CREATE TABLE solicitudes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    feria_id        INT NOT NULL,
    expositor_id    INT NOT NULL,
    categoria_id    INT NOT NULL,
    tipo_carpa_id   INT NOT NULL,
    creada_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado          ENUM('pendiente','espera','asignada','cancelada','confirmada','rechazada')
                    NOT NULL DEFAULT 'pendiente',
    confirmada_en   DATETIME NULL,
    motivo_rechazo  VARCHAR(255) NULL,
    codigo_liberado VARCHAR(20) NULL,
    UNIQUE KEY uq_solicitudes_feria_expositor (feria_id, expositor_id),
    UNIQUE KEY uq_solicitudes_id_feria (id, feria_id),
    CONSTRAINT fk_solicitudes_feria
        FOREIGN KEY (feria_id) REFERENCES ferias(id),
    CONSTRAINT fk_solicitudes_expositor
        FOREIGN KEY (expositor_id) REFERENCES expositores(id),
    CONSTRAINT fk_solicitudes_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    CONSTRAINT fk_solicitudes_tipo_carpa
        FOREIGN KEY (tipo_carpa_id) REFERENCES tipos_carpa(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Asignaciones: carpa concreta retenida por cada reserva
-- ------------------------------------------------------------
CREATE TABLE asignaciones (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    feria_id     INT NOT NULL,
    solicitud_id INT NOT NULL UNIQUE,
    area_id      INT NOT NULL,
    codigo       VARCHAR(20) NOT NULL,
    x_m          DECIMAL(6,2) NOT NULL,
    y_m          DECIMAL(6,2) NOT NULL,
    ancho_m      DECIMAL(6,2) NOT NULL,
    largo_m      DECIMAL(6,2) NOT NULL,
    fijada       TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_asignaciones_feria_codigo (feria_id, codigo),
    CONSTRAINT fk_asignaciones_solicitud
        FOREIGN KEY (solicitud_id, feria_id) REFERENCES solicitudes(id, feria_id),
    CONSTRAINT fk_asignaciones_area
        FOREIGN KEY (area_id, feria_id) REFERENCES areas(id, feria_id),
    CONSTRAINT chk_asignaciones_medidas
        CHECK (x_m >= 0 AND y_m >= 0 AND ancho_m > 0 AND largo_m > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. Configuración del espacio de una feria (plano general)
-- ------------------------------------------------------------
CREATE TABLE feria_configuracion (
    feria_id        INT PRIMARY KEY,
    largo_total_m   DECIMAL(6,2) NOT NULL,
    ancho_total_m   DECIMAL(6,2) NOT NULL,
    ancho_pasillo_m DECIMAL(6,2) NOT NULL,
    entrada_m2      DECIMAL(8,2) NOT NULL DEFAULT 0,
    escenario_m2    DECIMAL(8,2) NOT NULL DEFAULT 0,
    tipo_carpa_id   INT NOT NULL,
    imagen_plano    VARCHAR(255) NULL,
    actualizado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feria_configuracion_feria
        FOREIGN KEY (feria_id) REFERENCES ferias(id),
    CONSTRAINT fk_feria_configuracion_tipo
        FOREIGN KEY (tipo_carpa_id) REFERENCES tipos_carpa(id),
    CONSTRAINT chk_feria_configuracion_totales
        CHECK (largo_total_m > 0 AND ancho_total_m > 0 AND ancho_pasillo_m >= 0),
    CONSTRAINT chk_feria_configuracion_areas
        CHECK (entrada_m2 >= 0 AND escenario_m2 >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS DE DEMOSTRACIÓN
-- ============================================================
INSERT INTO categorias (nombre) VALUES
    ('Comida'),
    ('Artesanía'),
    ('Muebles'),
    ('Tecnología'),
    ('Salud'),
    ('Educación');

INSERT INTO instituciones (nombre) VALUES
    ('Asociación de Productores de Sacaba'),
    ('Mujeres Emprendedoras de Sacaba'),
    ('Productores del Valle');

INSERT INTO tipos_carpa (nombre, ancho_m, largo_m, activo) VALUES
    ('Carpa oficial 3×4', 3.00, 4.00, 1);

-- La feria de demostración queda ABIERTA para que la página pública y el
-- mapa muestren contenido desde el primer inicio. Cambie su estado,
-- nombre, fecha y lugar desde el panel cuando use datos reales.
INSERT INTO ferias (nombre, fecha, lugar, latitud, longitud, estado) VALUES
    ('FeriaSacaba · Feria Productiva',
     DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY),
     'Plaza Principal de Sacaba', -17.398000, -66.040000, 'abierta');

-- Zonas de 12 × 9,5 m con pasillo de 1,5 m: caben 8 carpas por zona
-- (4 por fila, 2 filas) y se numeran A01, A02... / C01, C02...
INSERT INTO areas (feria_id, categoria_id, nombre, ancho_m, largo_m, pasillo_m) VALUES
    (1, 1, 'Área Comida',     12.00, 9.50, 1.50),
    (1, 2, 'Área Artesanía',  12.00, 9.50, 1.50),
    (1, 3, 'Área Muebles',    12.00, 9.50, 1.50),
    (1, 4, 'Área Tecnología', 12.00, 9.50, 1.50),
    (1, 5, 'Área Salud',      12.00, 9.50, 1.50),
    (1, 6, 'Área Educación',  12.00, 9.50, 1.50);

-- ============================================================
-- CUENTAS LOCALES DE PRUEBA
--   Administrador: admin@sacaba.bo  /  SacabaAdmin#2026_Local
--   Expositor:     expo@sacaba.bo   /  SacabaExpo#2026_Local
-- Cámbielas antes de publicar el sistema en internet.
-- ============================================================
INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES
    ('Administrador de Sacaba', 'admin@sacaba.bo',
     '$2y$12$aa73xCp2hvZI0C1UO/hSgOhCc5ZYs2iz7pwWKRfT4jMoBB71Sd8fm', 'administrador', 1),
    ('Expositor de prueba', 'expo@sacaba.bo',
     '$2y$12$nqANfy3yIlq9Kc677ndlvOupT1i05jOpOe3nhqCV4VZL4vSgaWjDS', 'usuario', 1);

-- Cuentas históricas del mapa de demostración: se crean desactivadas y con
-- una contraseña aleatoria que nadie conoce. Solo sirven para mostrar
-- emprendimientos ya ubicados en el plano.
INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES
    ('Expositor Demo 01', 'demo01@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 02', 'demo02@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 03', 'demo03@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 04', 'demo04@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 05', 'demo05@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 06', 'demo06@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 07', 'demo07@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 08', 'demo08@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 09', 'demo09@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 10', 'demo10@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 11', 'demo11@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 12', 'demo12@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 13', 'demo13@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0),
    ('Expositor Demo 14', 'demo14@sacaba.local', '$2y$12$zDD1wCh6ROZLsW33vE2FmeGJH/zlJ2GdGJ9zl/n2y2Gk/Ms2Z4BEC', 'usuario', 0);

-- Expositor de prueba (cuenta activa, sin reserva todavía).
INSERT INTO expositores (usuario_id, institucion_id, emprendimiento, telefono)
SELECT id, NULL, 'Emprendimiento de prueba', '70000000'
FROM usuarios WHERE email = 'expo@sacaba.bo';

-- 14 emprendimientos de demostración ya ubicados en el plano.
CREATE TEMPORARY TABLE demo_feria (
    email          VARCHAR(150) PRIMARY KEY,
    emprendimiento VARCHAR(120) NOT NULL,
    institucion    VARCHAR(120) NOT NULL,
    categoria      VARCHAR(80)  NOT NULL,
    area           VARCHAR(80)  NOT NULL,
    codigo         VARCHAR(20)  NOT NULL,
    x_m            DECIMAL(6,2) NOT NULL,
    y_m            DECIMAL(6,2) NOT NULL
) ENGINE=Memory;

INSERT INTO demo_feria VALUES
    ('demo01@sacaba.local', 'Dulce Tradición',    'Mujeres Emprendedoras de Sacaba',   'Comida',     'Área Comida',     'C02', 3, 0),
    ('demo02@sacaba.local', 'Pan de mi Tierra',   'Productores del Valle',             'Comida',     'Área Comida',     'C03', 6, 0),
    ('demo03@sacaba.local', 'Tejidos Qhapaq',     'Asociación de Productores de Sacaba','Artesanía', 'Área Artesanía',  'A01', 0, 0),
    ('demo04@sacaba.local', 'Cerámica Tunari',    'Mujeres Emprendedoras de Sacaba',   'Artesanía',  'Área Artesanía',  'A02', 3, 0),
    ('demo05@sacaba.local', 'Manos de Sacaba',    'Productores del Valle',             'Artesanía',  'Área Artesanía',  'A03', 6, 0),
    ('demo06@sacaba.local', 'Muebles Valle Alto', 'Asociación de Productores de Sacaba','Muebles',   'Área Muebles',    'M01', 0, 0),
    ('demo07@sacaba.local', 'Carpintería Andina', 'Mujeres Emprendedoras de Sacaba',   'Muebles',    'Área Muebles',    'M02', 3, 0),
    ('demo08@sacaba.local', 'Madera Noble',       'Productores del Valle',             'Muebles',    'Área Muebles',    'M03', 6, 0),
    ('demo09@sacaba.local', 'TecnoSoluciones',    'Asociación de Productores de Sacaba','Tecnología','Área Tecnología', 'T01', 0, 0),
    ('demo10@sacaba.local', 'Digital Valle',      'Mujeres Emprendedoras de Sacaba',   'Tecnología', 'Área Tecnología', 'T02', 3, 0),
    ('demo11@sacaba.local', 'Vida Activa',        'Productores del Valle',             'Salud',      'Área Salud',      'S01', 0, 0),
    ('demo12@sacaba.local', 'Salud Natural',      'Asociación de Productores de Sacaba','Salud',     'Área Salud',      'S02', 3, 0),
    ('demo13@sacaba.local', 'Aprende Jugando',    'Mujeres Emprendedoras de Sacaba',   'Educación',  'Área Educación',  'E01', 0, 0),
    ('demo14@sacaba.local', 'Educa Sacaba',       'Productores del Valle',             'Educación',  'Área Educación',  'E02', 3, 0);

INSERT INTO expositores (usuario_id, institucion_id, emprendimiento, telefono)
SELECT u.id, i.id, d.emprendimiento, '70000000'
FROM demo_feria d
JOIN usuarios u      ON u.email  = d.email
JOIN instituciones i ON i.nombre = d.institucion;

INSERT INTO solicitudes (feria_id, expositor_id, categoria_id, tipo_carpa_id, estado, confirmada_en)
SELECT 1, e.id, c.id, 1, 'confirmada', NOW()
FROM demo_feria d
JOIN usuarios u     ON u.email     = d.email
JOIN expositores e  ON e.usuario_id = u.id
JOIN categorias c   ON c.nombre    = d.categoria;

INSERT INTO asignaciones (feria_id, solicitud_id, area_id, codigo, x_m, y_m, ancho_m, largo_m)
SELECT 1, s.id, a.id, d.codigo, d.x_m, d.y_m, 3.00, 4.00
FROM demo_feria d
JOIN usuarios u     ON u.email     = d.email
JOIN expositores e  ON e.usuario_id = u.id
JOIN solicitudes s  ON s.expositor_id = e.id AND s.feria_id = 1
JOIN areas a        ON a.feria_id = 1 AND a.nombre = d.area;

INSERT INTO feria_configuracion
    (feria_id, largo_total_m, ancho_total_m, ancho_pasillo_m, entrada_m2, escenario_m2, tipo_carpa_id)
VALUES (1, 9.50, 12.00, 1.50, 0, 0, 1);

DROP TEMPORARY TABLE demo_feria;

-- ============================================================
-- Resumen de la instalación (debe devolver 10 tablas y datos > 0)
-- ============================================================
SELECT 'Base ferias_sacaba creada correctamente' AS resultado,
       (SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = 'ferias_sacaba') AS tablas,
       (SELECT COUNT(*) FROM usuarios)       AS usuarios,
       (SELECT COUNT(*) FROM expositores)    AS expositores,
       (SELECT COUNT(*) FROM ferias)         AS ferias,
       (SELECT COUNT(*) FROM asignaciones)   AS carpas_ocupadas;
