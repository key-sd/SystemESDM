-- MySQL 8.0+ / MariaDB 10.4+. Compatible con local (XAMPP) y hosting remoto (InfinityFree, cPanel).
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- Limpieza preventiva para evitar conflictos si se vuelve a ejecutar
DROP TABLE IF EXISTS metas_asignaciones_sector;
DROP TABLE IF EXISTS metas;
DROP TABLE IF EXISTS servicios_iglesia;
DROP TABLE IF EXISTS reportes_sup_detalle;
DROP TABLE IF EXISTS reportes_supervisor;
DROP TABLE IF EXISTS reportes_lider_visitas;
DROP TABLE IF EXISTS reportes_lider_bautismos;
DROP TABLE IF EXISTS reportes_lider_conversiones;
DROP TABLE IF EXISTS reportes_lider;
DROP TABLE IF EXISTS miembros_celula;
DROP TABLE IF EXISTS celulas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS sectores;

CREATE TABLE sectores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    zona VARCHAR(20) NULL,
    distrito VARCHAR(20) NULL,
    supervisor_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sector_nombre (nombre),
    UNIQUE KEY uq_sector_supervisor (supervisor_id),
    CONSTRAINT chk_sector_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(120) NOT NULL,
    usuario VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('lider', 'supervisor', 'admin') NOT NULL,
    sector_id INT UNSIGNED NULL,
    celula_id INT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_login (usuario),
    KEY idx_usuarios_sector (sector_id),
    CONSTRAINT chk_usuario_activo CHECK (activo IN (0, 1)),
    CONSTRAINT fk_usuario_sector FOREIGN KEY (sector_id)
        REFERENCES sectores(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE sectores
    ADD CONSTRAINT fk_sector_supervisor FOREIGN KEY (supervisor_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE celulas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    sector_id INT UNSIGNED NOT NULL,
    lider_id INT UNSIGNED NULL,
    tipo ENUM('adultos', 'jovenes', 'ninos') NOT NULL,
    direccion VARCHAR(255) NULL,
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_celula_nombre_sector (nombre, sector_id),
    UNIQUE KEY uq_celula_lider (lider_id),
    KEY idx_celulas_sector (sector_id),
    CONSTRAINT chk_celula_activa CHECK (activo IN (0, 1)),
    CONSTRAINT chk_latitud CHECK (lat IS NULL OR lat BETWEEN -90 AND 90),
    CONSTRAINT chk_longitud CHECK (lng IS NULL OR lng BETWEEN -180 AND 180),
    CONSTRAINT fk_celula_sector FOREIGN KEY (sector_id)
        REFERENCES sectores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_celula_lider FOREIGN KEY (lider_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuario_celula FOREIGN KEY (celula_id)
        REFERENCES celulas(id) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE miembros_celula (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    celula_id INT UNSIGNED NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    estado_civil ENUM('soltero', 'casado') NOT NULL DEFAULT 'soltero',
    conversion TINYINT(1) NOT NULL DEFAULT 0,
    bautismo_agua TINYINT(1) NOT NULL DEFAULT 0,
    bautismo_espiritu TINYINT(1) NOT NULL DEFAULT 0,
    fecha_nacimiento DATE NULL,
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(30) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_miembros_celula (celula_id),
    CONSTRAINT chk_miembro_activo CHECK (activo IN (0, 1)),
    CONSTRAINT fk_miembros_celula FOREIGN KEY (celula_id)
        REFERENCES celulas(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE reportes_lider (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    celula_id INT UNSIGNED NOT NULL,
    lider_id INT UNSIGNED NOT NULL,
    fecha_reporte DATE NOT NULL,
    anio_semana INT AS (YEARWEEK(fecha_reporte, 1)) STORED,
    adultos_amigos INT UNSIGNED NOT NULL DEFAULT 0,
    adultos_hermanos INT UNSIGNED NOT NULL DEFAULT 0,
    jovenes_amigos INT UNSIGNED NOT NULL DEFAULT 0,
    jovenes_hermanos INT UNSIGNED NOT NULL DEFAULT 0,
    ninos INT UNSIGNED NOT NULL DEFAULT 0,
    total_asistencia INT AS (adultos_amigos + adultos_hermanos + jovenes_amigos + jovenes_hermanos + ninos) STORED,
    ofrenda_iglesia DECIMAL(12,2) NOT NULL DEFAULT 0,
    ofrenda_misiones DECIMAL(12,2) NOT NULL DEFAULT 0,
    ofrenda_bus DECIMAL(12,2) NOT NULL DEFAULT 0,
    ofrenda_total DECIMAL(12,2) AS (ofrenda_iglesia + ofrenda_misiones + ofrenda_bus) STORED,
    conversion INT UNSIGNED NOT NULL DEFAULT 0,
    visita_programada TINYINT(1) NOT NULL DEFAULT 0,
    bautismo_agua TINYINT(1) NOT NULL DEFAULT 0,
    observaciones TEXT NULL,
    enviado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    visto_supervisor_en TIMESTAMP NULL,
    visto_admin_en TIMESTAMP NULL,
    UNIQUE KEY uq_reporte_lider_celula_semana (celula_id, anio_semana),
    KEY idx_reporte_lider_fecha (fecha_reporte),
    CONSTRAINT chk_reporte_lider_ofrendas CHECK (
        ofrenda_iglesia >= 0 AND ofrenda_misiones >= 0 AND ofrenda_bus >= 0
    ),
    CONSTRAINT chk_visita_programada CHECK (visita_programada IN (0, 1)),
    CONSTRAINT chk_bautismo_agua CHECK (bautismo_agua IN (0, 1)),
    CONSTRAINT fk_reporte_lider_celula FOREIGN KEY (celula_id)
        REFERENCES celulas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reporte_lider_usuario FOREIGN KEY (lider_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE reportes_lider_conversiones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporte_lider_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    tipo_decision ENUM('conversion', 'reconcilio') NOT NULL DEFAULT 'conversion',
    estado_civil ENUM('soltero', 'casado') NOT NULL DEFAULT 'soltero',
    bautismo_agua TINYINT(1) NOT NULL DEFAULT 0,
    bautismo_espiritu TINYINT(1) NOT NULL DEFAULT 0,
    fecha_nacimiento DATE NULL,
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(30) NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rep_conv_reporte (reporte_lider_id),
    CONSTRAINT fk_rep_conv_reporte FOREIGN KEY (reporte_lider_id)
        REFERENCES reportes_lider(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reportes_lider_bautismos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporte_lider_id INT UNSIGNED NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    tipo_celula ENUM('adultos', 'infantil', 'juvenil') NOT NULL DEFAULT 'adultos',
    estado_civil ENUM('soltero', 'casado') NOT NULL DEFAULT 'soltero',
    fecha_nacimiento DATE NULL,
    fecha_bautismo DATE NULL,
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(30) NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rep_baut_reporte (reporte_lider_id),
    CONSTRAINT fk_rep_baut_reporte FOREIGN KEY (reporte_lider_id)
        REFERENCES reportes_lider(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reportes_lider_visitas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporte_lider_id INT UNSIGNED NOT NULL,
    nombre_completo VARCHAR(150) NOT NULL,
    tipo_celula ENUM('adultos', 'infantil', 'juvenil') NOT NULL DEFAULT 'adultos',
    estado_civil ENUM('soltero', 'casado') NOT NULL DEFAULT 'soltero',
    bautismo_agua TINYINT(1) NOT NULL DEFAULT 0,
    bautismo_espiritu TINYINT(1) NOT NULL DEFAULT 0,
    fecha_nacimiento DATE NULL,
    numero_visita TINYINT UNSIGNED NOT NULL DEFAULT 1,
    fecha_visita DATE NULL,
    direccion VARCHAR(255) NULL,
    telefono VARCHAR(30) NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rep_vis_reporte (reporte_lider_id),
    CONSTRAINT chk_rep_vis_numero CHECK (numero_visita BETWEEN 1 AND 4),
    CONSTRAINT fk_rep_vis_reporte FOREIGN KEY (reporte_lider_id)
        REFERENCES reportes_lider(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reportes_supervisor (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sector_id INT UNSIGNED NOT NULL,
    supervisor_id INT UNSIGNED NOT NULL,
    fecha_reporte DATE NOT NULL,
    observaciones TEXT NULL,
    enviado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    visto_admin_en TIMESTAMP NULL,
    UNIQUE KEY uq_reporte_supervisor_semana (sector_id, fecha_reporte),
    KEY idx_reporte_supervisor_fecha (fecha_reporte),
    CONSTRAINT chk_reporte_supervisor_sabado CHECK (DAYOFWEEK(fecha_reporte) = 7),
    CONSTRAINT fk_reporte_supervisor_sector FOREIGN KEY (sector_id)
        REFERENCES sectores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reporte_supervisor_usuario FOREIGN KEY (supervisor_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE reportes_sup_detalle (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporte_supervisor_id INT UNSIGNED NOT NULL,
    lider_id INT UNSIGNED NULL,
    celula_id INT UNSIGNED NULL,
    tipo_celula ENUM('adultos', 'jovenes', 'ninos') NOT NULL,
    adultos_amigos INT UNSIGNED NOT NULL DEFAULT 0,
    adultos_hermanos INT UNSIGNED NOT NULL DEFAULT 0,
    jovenes_amigos INT UNSIGNED NOT NULL DEFAULT 0,
    jovenes_hermanos INT UNSIGNED NOT NULL DEFAULT 0,
    ninos INT UNSIGNED NOT NULL DEFAULT 0,
    conversiones INT UNSIGNED NOT NULL DEFAULT 0,
    visitas_programadas INT UNSIGNED NOT NULL DEFAULT 0,
    bautismos_agua INT UNSIGNED NOT NULL DEFAULT 0,
    ofrenda_iglesia DECIMAL(12,2) NOT NULL DEFAULT 0,
    ofrenda_misiones DECIMAL(12,2) NOT NULL DEFAULT 0,
    ofrenda_bus DECIMAL(12,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_detalle_reporte FOREIGN KEY (reporte_supervisor_id)
        REFERENCES reportes_supervisor(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detalle_lider FOREIGN KEY (lider_id)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_detalle_celula FOREIGN KEY (celula_id)
        REFERENCES celulas(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE servicios_iglesia (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    tipo_servicio ENUM('martes', 'jueves', 'domingo') NOT NULL,
    asistencia_adultos INT UNSIGNED NOT NULL DEFAULT 0,
    asistencia_ninos INT UNSIGNED NOT NULL DEFAULT 0,
    asistencia_total INT AS (asistencia_adultos + asistencia_ninos) STORED,
    ofrenda DECIMAL(12,2) NOT NULL DEFAULT 0,
    diezmos DECIMAL(12,2) NOT NULL DEFAULT 0,
    observaciones TEXT NULL,
    registrado_por INT UNSIGNED NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_servicio_fecha (fecha),
    KEY idx_servicio_fecha (fecha),
    CONSTRAINT chk_servicio_dinero CHECK (ofrenda >= 0 AND diezmos >= 0),
    CONSTRAINT chk_servicio_dia CHECK (
        (tipo_servicio = 'martes' AND DAYOFWEEK(fecha) = 3) OR
        (tipo_servicio = 'jueves' AND DAYOFWEEK(fecha) = 5) OR
        (tipo_servicio = 'domingo' AND DAYOFWEEK(fecha) = 1)
    ),
    CONSTRAINT fk_servicio_registrador FOREIGN KEY (registrado_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE metas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    anio SMALLINT UNSIGNED NOT NULL,
    tipo ENUM('celulas', 'asistencia_adultos', 'asistencia_ninos', 'conversiones', 'visitas', 'bautismos') NOT NULL,
    objetivo DECIMAL(12,2) NOT NULL,
    sector_id INT UNSIGNED NULL,
    sector_id_unico INT UNSIGNED NOT NULL DEFAULT 0,
    creado_por INT UNSIGNED NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_meta_anio_tipo_sector (anio, tipo, sector_id_unico),
    CONSTRAINT chk_meta_objetivo CHECK (objetivo > 0),
    CONSTRAINT fk_meta_sector FOREIGN KEY (sector_id)
        REFERENCES sectores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_meta_creador FOREIGN KEY (creado_por)
        REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE metas_asignaciones_sector (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meta_id INT UNSIGNED NOT NULL,
    sector_id INT UNSIGNED NOT NULL,
    objetivo DECIMAL(12,2) NOT NULL,
    base_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_meta_asignacion_sector (meta_id, sector_id),
    CONSTRAINT chk_asignacion_objetivo CHECK (objetivo > 0),
    CONSTRAINT fk_asignacion_meta FOREIGN KEY (meta_id)
        REFERENCES metas(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_asignacion_sector FOREIGN KEY (sector_id)
        REFERENCES sectores(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;


INSERT INTO usuarios (nombre_completo, usuario, password, rol) VALUES
    ('Administrador de prueba', 'admin', '$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i', 'admin'),
    ('Supervisor de prueba', 'supervisor', '$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i', 'supervisor'),
    ('Líder de prueba', 'lider', '$2y$10$VZtHXRRBHJaBbKXcJxKaX.Oi0VunaYnnhwz.QR.xelkTrYXBkAY5i', 'lider');

SET FOREIGN_KEY_CHECKS = 1;
