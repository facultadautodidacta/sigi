-- =====================================================================
-- SIGI - Esquema de base de datos
-- Sistema de Gestion de Inventario Informatico
-- Motor: MariaDB 10.11 / MySQL 5.7+   Charset: utf8mb4
-- Ejecutar:  mysql -u<admin> -p < database/schema.sql
-- =====================================================================

USE `sigi`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- No se fija time_zone a proposito: el esquema debe instalarse en la zona
-- del servidor (CST, UTC-6), igual que PHP (America/Mexico_City). Fijarla
-- en UTC dejaba los TIMESTAMP desalineados una hora con la aplicacion.

-- ---------------------------------------------------------------------
-- Tabla: roles
-- Catalogo de roles del sistema. 3 roles base del proyecto.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: users
--
-- password         : hash generado con password_hash() (bcrypt). Es la
--                   columna que usa el modulo correcto de autenticacion.
-- password_legacy  : hash MD5 del sistema anterior a la migracion a
--                   bcrypt. Se conserva porque el modulo de autenticacion
--                   viejo (login.php) sigue comparando contra esta columna.
--                   No debe usarse en ningun flujo nuevo.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(100) NOT NULL,
  `apellido`   VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `username`   VARCHAR(50) NOT NULL,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt via password_hash()',
  `password_legacy` CHAR(32) DEFAULT NULL COMMENT 'MD5 heredado. Solo lo usa el modulo legacy de login.php',
  `role_id`    INT(11) NOT NULL,
  `estado`     ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_estado` (`estado`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: login_attempts
-- Registro de intentos de autenticacion. LAB-09: brute force / flood.
-- Indice compuesto (ip, created_at) para consultas rapidas por IP.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(100) NOT NULL,
  `ip`         VARCHAR(45) NOT NULL COMMENT 'soporta IPv6',
  `success`    TINYINT(1) NOT NULL DEFAULT 0,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_ip_time` (`ip`, `created_at`),
  KEY `idx_attempts_user_time` (`username`, `created_at`),
  KEY `idx_attempts_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: audit_log
-- Auditoria de acciones. Se escribe desde config/audit.php
-- Acciones: LOGIN CREATE UPDATE DELETE ASSIGN RETURN MAINTENANCE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario`     VARCHAR(100) DEFAULT NULL COMMENT 'username o SISTEMA',
  `accion`      VARCHAR(30) NOT NULL,
  `modulo`      VARCHAR(50) NOT NULL,
  `ip`          VARCHAR(45) NOT NULL,
  `descripcion` VARCHAR(500) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`usuario`),
  KEY `idx_audit_modulo` (`modulo`),
  KEY `idx_audit_fecha` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: employees
-- Empleados que pueden recibir equipos.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `numero_empleado` VARCHAR(20) NOT NULL,
  `nombre`          VARCHAR(100) NOT NULL,
  `apellido`        VARCHAR(100) NOT NULL,
  `email`           VARCHAR(150) NOT NULL,
  `departamento`    VARCHAR(50) NOT NULL,
  `puesto`          VARCHAR(100) DEFAULT NULL,
  `telefono`        VARCHAR(30) DEFAULT NULL COMMENT 'LAB-03: dato personal restringido',
  `notas_confidenciales` TEXT DEFAULT NULL COMMENT 'LAB-03: notas internas de RRHH, restringidas a Administrador',
  `estado`          ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emp_numero` (`numero_empleado`),
  UNIQUE KEY `uq_emp_email` (`email`),
  KEY `idx_emp_departamento` (`departamento`),
  KEY `idx_emp_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: brands  (Marcas)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(80) NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `estado`      ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brands_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: categories  (Categorias de equipo)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(80) NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `estado`      ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: equipment  (modulo principal)
-- inventory_number y serial_number son UNIQUE (regla de negocio).
-- status: Disponible | Asignado | Mantenimiento | Baja
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `inventory_number` VARCHAR(30) NOT NULL COMMENT 'INV-000001',
  `serial_number`    VARCHAR(80) NOT NULL,
  `brand_id`         INT(11) NOT NULL,
  `category_id`      INT(11) NOT NULL,
  `model`            VARCHAR(120) NOT NULL,
  `processor`        VARCHAR(120) DEFAULT NULL,
  `ram`              VARCHAR(50) DEFAULT NULL,
  `storage`          VARCHAR(120) DEFAULT NULL,
  `operating_system` VARCHAR(120) DEFAULT NULL,
  `purchase_date`    DATE DEFAULT NULL,
  `price`            DECIMAL(12,2) DEFAULT NULL,
  `location`         VARCHAR(150) DEFAULT NULL,
  `status`           ENUM('Disponible','Asignado','Mantenimiento','Baja') NOT NULL DEFAULT 'Disponible',
  `description`      TEXT DEFAULT NULL COMMENT 'LAB-04: campo de Stored XSS controlado',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_equip_invnum` (`inventory_number`),
  UNIQUE KEY `uq_equip_serial` (`serial_number`),
  KEY `idx_equip_brand` (`brand_id`),
  KEY `idx_equip_category` (`category_id`),
  KEY `idx_equip_status` (`status`),
  KEY `idx_equip_model` (`model`),
  KEY `idx_equip_location` (`location`),
  CONSTRAINT `fk_equip_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_equip_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: assignments  (asignaciones de equipos a empleados)
-- Regla: un equipo solo puede tener UNA asignacion activa.
--   Activa  = fecha_devolucion IS NULL
--   Unicidad garantizada en logica de aplicacion (Fase 7) + indice de apoyo.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `assignments`;
CREATE TABLE `assignments` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `equipment_id`     INT(11) NOT NULL,
  `employee_id`      INT(11) NOT NULL,
  `fecha_asignacion` DATE NOT NULL,
  `fecha_devolucion` DATE DEFAULT NULL COMMENT 'NULL = asignacion activa',
  `observaciones`    TEXT DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asg_equip` (`equipment_id`),
  KEY `idx_asg_employee` (`employee_id`),
  KEY `idx_asg_activa` (`fecha_devolucion`),
  CONSTRAINT `fk_asg_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asg_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: maintenance
-- Un equipo puede tener multiples mantenimientos.
-- type: Preventivo | Correctivo | Diagnostico
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `maintenance`;
CREATE TABLE `maintenance` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `equipment_id`    INT(11) NOT NULL,
  `technician_id`   INT(11) DEFAULT NULL COMMENT 'users.id (rol Tecnico o Administrador)',
  `type`            ENUM('Preventivo','Correctivo','Diagnostico') NOT NULL DEFAULT 'Preventivo',
  `description`     TEXT DEFAULT NULL,
  `cost`            DECIMAL(12,2) DEFAULT NULL,
  `maintenance_date` DATE NOT NULL,
  `status`          ENUM('Pendiente','En Proceso','Completado') NOT NULL DEFAULT 'Pendiente',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mnt_equipment` (`equipment_id`),
  KEY `idx_mnt_technician` (`technician_id`),
  KEY `idx_mnt_status` (`status`),
  KEY `idx_mnt_fecha` (`maintenance_date`),
  CONSTRAINT `fk_mnt_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mnt_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- lab_documentos - Catalogo del visor de contratos (laboratorio)
-- LAB-05: la pagina concatena el campo `archivo` a la ruta base.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `lab_documentos`;
CREATE TABLE `lab_documentos` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `archivo`     VARCHAR(120) NOT NULL COMMENT 'Nombre relativo dentro de lab/contratos/',
  `descripcion` VARCHAR(200) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_doc_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Fin schema.sql
-- =====================================================================
