-- ============================================================
-- Migraciones para soportar el rediseño Clúster Intranet 2026
-- Organización: Clúster Automotriz Metropolitano A.C. (CLAUTMET)
-- Cobertura: Estado de México · CDMX · Hidalgo
-- Fecha: 2026-05-04
-- Patrón: idempotente (IF NOT EXISTS) — seguro de re-correr
-- Plataforma: MySQL/MariaDB en Hostinger Shared
-- Cómo correr: phpMyAdmin → SQL → pegar este archivo → Continuar
-- ============================================================

-- 1) Rol superadmin
ALTER TABLE `usuarios_perfil`
  ADD COLUMN IF NOT EXISTS `superadmin` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1=Acceso a panel de configuración del sistema'
    AFTER `rol`;

-- 2) Distinción socio / no-socio en directorio
ALTER TABLE `empresas_convenio`
  ADD COLUMN IF NOT EXISTS `es_socio` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1=Socio CLAUTMET activo, 0=Empresa solo en directorio'
    AFTER `autoriza_directorio`;

-- 3) Toggle independiente para aparecer en /descuentos
ALTER TABLE `empresas_convenio`
  ADD COLUMN IF NOT EXISTS `tiene_convenio_activo` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1=Empresa publica descuento Clúster activo'
    AFTER `es_socio`;

-- 4) Términos del convenio (texto libre)
ALTER TABLE `empresas_convenio`
  ADD COLUMN IF NOT EXISTS `terminos_convenio` TEXT NULL DEFAULT NULL
    COMMENT 'Términos y condiciones del convenio CLAUTMET'
    AFTER `vigencia_fin`;

-- 5) Vínculo dueño del registro empresa
ALTER TABLE `empresas_convenio`
  ADD COLUMN IF NOT EXISTS `admin_usuario_id` INT NULL DEFAULT NULL
    COMMENT 'FK→usuarios_perfil.id — usuario empresa_socio dueño del registro'
    AFTER `creado_por`;

-- 6) Visibilidad granular por campo del directorio
CREATE TABLE IF NOT EXISTS `empresa_visibilidad_campo` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `empresa_id` INT NOT NULL,
  `campo` VARCHAR(64) NOT NULL COMMENT 'nombre, sector, municipio, certificaciones, etc',
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_empresa_campo` (`empresa_id`, `campo`),
  INDEX `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Toggle por campo cuando el socio quiere granularidad';

-- 7) Comités: WhatsApp + Google Form
ALTER TABLE `comites`
  ADD COLUMN IF NOT EXISTS `link_whatsapp` VARCHAR(500) NULL DEFAULT NULL
    COMMENT 'Link de invitación al grupo WhatsApp'
    AFTER `link_registro`,
  ADD COLUMN IF NOT EXISTS `link_google_form` VARCHAR(500) NULL DEFAULT NULL
    COMMENT 'Google Form externo de registro'
    AFTER `link_whatsapp`;

-- 8) Tabla de sesiones de comité (para alertas próximas)
CREATE TABLE IF NOT EXISTS `comite_sesiones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `comite_id` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `hora` TIME NULL,
  `lugar` VARCHAR(255) NULL,
  `agenda` TEXT NULL,
  `link_meet` VARCHAR(500) NULL COMMENT 'Google Meet/Zoom para sesión virtual',
  `estado` ENUM('programada','en_curso','finalizada','cancelada') DEFAULT 'programada',
  `recordatorio_enviado` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_comite_fecha` (`comite_id`, `fecha`),
  INDEX `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9) Beneficio Clúster en eventos + recordatorios
ALTER TABLE `eventos`
  ADD COLUMN IF NOT EXISTS `beneficio_cluster` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1=Evento ofrece beneficio para socios CLAUTMET'
    AFTER `cupo_maximo`,
  ADD COLUMN IF NOT EXISTS `beneficio_descripcion` TEXT NULL DEFAULT NULL
    AFTER `beneficio_cluster`,
  ADD COLUMN IF NOT EXISTS `beneficio_contacto_nombre` VARCHAR(255) NULL DEFAULT NULL
    AFTER `beneficio_descripcion`,
  ADD COLUMN IF NOT EXISTS `beneficio_contacto_email` VARCHAR(255) NULL DEFAULT NULL
    AFTER `beneficio_contacto_nombre`,
  ADD COLUMN IF NOT EXISTS `beneficio_contacto_telefono` VARCHAR(20) NULL DEFAULT NULL
    AFTER `beneficio_contacto_email`,
  ADD COLUMN IF NOT EXISTS `recordatorio_24h_enviado` TINYINT(1) DEFAULT 0
    AFTER `beneficio_contacto_telefono`,
  ADD COLUMN IF NOT EXISTS `recordatorio_1h_enviado` TINYINT(1) DEFAULT 0
    AFTER `recordatorio_24h_enviado`;

-- 10) Tracking de descuentos con código único + validación
ALTER TABLE `descuentos_usos`
  ADD COLUMN IF NOT EXISTS `codigo_unico` VARCHAR(64) NULL DEFAULT NULL
    COMMENT 'UUID generado al hacer click en descuento'
    AFTER `usuario_id`,
  ADD COLUMN IF NOT EXISTS `usado` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1=Comerciante validó el código'
    AFTER `codigo_unico`,
  ADD COLUMN IF NOT EXISTS `fecha_validacion` DATETIME NULL DEFAULT NULL
    AFTER `usado`,
  ADD UNIQUE INDEX IF NOT EXISTS `uq_codigo_unico` (`codigo_unico`);

-- 11) Push notifications (opcional)
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `endpoint` TEXT NOT NULL,
  `p256dh` VARCHAR(255) NOT NULL,
  `auth` VARCHAR(255) NOT NULL,
  `user_agent` VARCHAR(500) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12) Configuración global del tema (superadmin)
CREATE TABLE IF NOT EXISTS `theme_config` (
  `clave` VARCHAR(100) PRIMARY KEY,
  `valor` TEXT NOT NULL,
  `tipo` ENUM('color','texto','numero','imagen','json','bool') NOT NULL DEFAULT 'texto',
  `descripcion` VARCHAR(255) NULL,
  `updated_by` INT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds del tema CLAUTMET
INSERT IGNORE INTO `theme_config` (`clave`, `valor`, `tipo`, `descripcion`) VALUES
  ('brand_name',            'Clúster Automotriz Metropolitano', 'texto', 'Nombre completo'),
  ('brand_short',           'CLAUTMET', 'texto', 'Nombre corto/acrónimo'),
  ('brand_color_primary',   '#C7252B', 'color', 'Rojo Clúster (acento)'),
  ('brand_color_dark',      '#0A0A0B', 'color', 'Negro profundo dark mode'),
  ('brand_color_light',     '#FFFFFF', 'color', 'Blanco puro light mode'),
  ('brand_color_carbon',    '#1D1D1F', 'color', 'Carbón superficie'),
  ('brand_logo_light_url',  '/assets/img/logo/logo-light.png', 'imagen', 'Logo en fondo claro'),
  ('brand_logo_dark_url',   '/assets/img/logo/logo-dark.png',  'imagen', 'Logo en fondo oscuro'),
  ('font_family',           'InterVariable', 'texto', 'Tipografía principal'),
  ('site_title',            'Clúster Automotriz Metropolitano', 'texto', 'Título global'),
  ('site_tagline_es',       'Motor de vinculación de la industria automotriz', 'texto', 'Tagline ES'),
  ('site_tagline_en',       'Engine of connection for the automotive industry', 'texto', 'Tagline EN'),
  ('site_description_es',   'Conectamos empresas, talento y tecnología en la zona metropolitana del Valle de México, CDMX e Hidalgo.', 'texto', 'Descripción ES'),
  ('site_description_en',   'We connect companies, talent and technology in the Mexico Valley metropolitan zone, Mexico City and Hidalgo.', 'texto', 'Descripción EN'),
  ('default_language',      'es', 'texto', 'Idioma por defecto'),
  ('hero_sequence_enabled', '1', 'bool', 'Activar image sequence scrubbing en hero'),
  ('hero_sequence_frames',  '60', 'numero', 'Cantidad de frames'),
  ('hero_sequence_path',    '/assets/img/sequences/hero', 'texto', 'Ruta base de los frames'),
  ('hero_sequence_ext',     'webp', 'texto', 'Extensión de los frames'),
  ('hero_fallback_lottie',  '/assets/lottie/hero-placeholder.json', 'imagen', 'Fallback Lottie'),
  ('color_invert_on_scroll','1', 'bool', 'Invertir colores al scroll en hero (light only)'),
  ('push_enabled',          '0', 'bool', 'Activar push notifications'),
  ('vapid_public_key',      '', 'texto', 'Public key VAPID (generar con web-push)'),
  ('vapid_private_key',     '', 'texto', 'Private key VAPID'),
  ('email_smtp_from',       'auxsistemas@clautmetropolitano.mx', 'texto', 'Remitente SMTP existente'),
  ('email_contact',         'atencion@clautmetropolitano.mx', 'texto', 'Email contacto público'),
  ('superadmin_root_email', '', 'texto', 'Email raíz superadmin'),
  ('captcha_enabled',       '1', 'bool', 'Activar math captcha en formularios públicos'),
  ('push_onboarding_visits','3', 'numero', 'Visitas antes de pedir permiso push');

-- 13) Catálogo configurable
CREATE TABLE IF NOT EXISTS `catalogos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipo` ENUM('sector','municipio','entidad','categoria_descuento','tipo_evento','tipo_comite','departamento','cargo','certificacion') NOT NULL,
  `valor` VARCHAR(255) NOT NULL,
  `valor_en` VARCHAR(255) NULL COMMENT 'Traducción inglés opcional',
  `parent_id` INT NULL COMMENT 'Para municipio→entidad',
  `orden` INT DEFAULT 0,
  `activo` TINYINT(1) DEFAULT 1,
  UNIQUE KEY `uq_tipo_valor` (`tipo`, `valor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogos editables desde panel superadmin';

-- ============================================================
-- SEEDS — Catálogos iniciales CLAUTMET
-- ============================================================

-- Entidades federativas (3 que cubre el clúster)
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `valor_en`, `orden`) VALUES
  ('entidad', 'Estado de México', 'State of Mexico', 1),
  ('entidad', 'Ciudad de México',  'Mexico City',     2),
  ('entidad', 'Hidalgo',           'Hidalgo',         3);

-- Sectores enfocados en industria automotriz/manufactura
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `valor_en`, `orden`) VALUES
  ('sector', 'Manufactura automotriz',          'Automotive manufacturing',     1),
  ('sector', 'Autopartes y componentes',        'Auto parts and components',    2),
  ('sector', 'Semiconductores y electrónica',   'Semiconductors and electronics', 3),
  ('sector', 'Movilidad eléctrica',              'Electric mobility',            4),
  ('sector', 'Tratamientos térmicos',            'Heat treatments',              5),
  ('sector', 'Mecatrónica y automatización',    'Mechatronics and automation',  6),
  ('sector', 'Software industrial',              'Industrial software',          7),
  ('sector', 'Logística automotriz',             'Automotive logistics',         8),
  ('sector', 'Calidad y procesos',               'Quality and processes',        9),
  ('sector', 'Capacitación y desarrollo',        'Training and development',    10),
  ('sector', 'Ingeniería y diseño',              'Engineering and design',      11),
  ('sector', 'Servicios especializados',         'Specialized services',        12);

-- Municipios Estado de México (top automotriz)
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `parent_id`, `orden`) VALUES
  ('municipio', 'Toluca',              (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 1),
  ('municipio', 'Lerma',               (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 2),
  ('municipio', 'Cuautitlán Izcalli',  (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 3),
  ('municipio', 'Tlalnepantla',        (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 4),
  ('municipio', 'Naucalpan',           (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 5),
  ('municipio', 'Ecatepec',            (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 6),
  ('municipio', 'Atizapán',            (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 7),
  ('municipio', 'Tultitlán',           (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 8),
  ('municipio', 'Coacalco',            (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 9),
  ('municipio', 'Metepec',             (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Estado de México') AS x), 10);

-- Alcaldías CDMX
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `parent_id`, `orden`) VALUES
  ('municipio', 'Azcapotzalco',     (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Ciudad de México') AS x), 1),
  ('municipio', 'Iztapalapa',       (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Ciudad de México') AS x), 2),
  ('municipio', 'Gustavo A. Madero',(SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Ciudad de México') AS x), 3),
  ('municipio', 'Tlalpan',          (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Ciudad de México') AS x), 4),
  ('municipio', 'Miguel Hidalgo',   (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Ciudad de México') AS x), 5);

-- Municipios Hidalgo (industriales)
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `parent_id`, `orden`) VALUES
  ('municipio', 'Pachuca de Soto',  (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Hidalgo') AS x), 1),
  ('municipio', 'Tepeji del Río',   (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Hidalgo') AS x), 2),
  ('municipio', 'Tula de Allende',  (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Hidalgo') AS x), 3),
  ('municipio', 'Tizayuca',         (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Hidalgo') AS x), 4),
  ('municipio', 'Tepetitlán',       (SELECT id FROM (SELECT id FROM catalogos WHERE tipo='entidad' AND valor='Hidalgo') AS x), 5);

-- Tipos de evento
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `valor_en`, `orden`) VALUES
  ('tipo_evento', 'Asamblea',                'Assembly',          1),
  ('tipo_evento', 'Capacitación',            'Training',          2),
  ('tipo_evento', 'Networking',              'Networking',        3),
  ('tipo_evento', 'Foro técnico',            'Technical forum',   4),
  ('tipo_evento', 'Misión comercial',        'Trade mission',     5),
  ('tipo_evento', 'Visita industrial',       'Industrial visit',  6),
  ('tipo_evento', 'Congreso',                'Congress',          7);

-- Tipos de comité (basados en estructura real CLAUTMET)
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `valor_en`, `orden`) VALUES
  ('tipo_comite', 'Manufactura',             'Manufacturing',     1),
  ('tipo_comite', 'Desarrollo Tecnológico',  'Technology Development', 2),
  ('tipo_comite', 'Capital Humano',          'Human Capital',     3),
  ('tipo_comite', 'Cooperación Internacional','International Cooperation', 4),
  ('tipo_comite', 'Calidad y Mejora',        'Quality and Improvement', 5),
  ('tipo_comite', 'Cadena de Suministro',    'Supply Chain',      6);

-- Categorías de descuento
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `valor_en`, `orden`) VALUES
  ('categoria_descuento', 'Capacitación',           'Training',          1),
  ('categoria_descuento', 'Servicios profesionales','Professional services', 2),
  ('categoria_descuento', 'Equipo y herramientas',  'Equipment and tools', 3),
  ('categoria_descuento', 'Consultoría',            'Consulting',        4),
  ('categoria_descuento', 'Software',                'Software',          5),
  ('categoria_descuento', 'Insumos industriales',    'Industrial supplies', 6);

-- Certificaciones automotrices comunes
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `orden`) VALUES
  ('certificacion', 'IATF 16949',           1),
  ('certificacion', 'ISO 9001',             2),
  ('certificacion', 'ISO 14001',            3),
  ('certificacion', 'ISO 45001',            4),
  ('certificacion', 'ISO/IEC 27001',        5),
  ('certificacion', 'VDA 6.3',              6),
  ('certificacion', 'AS9100',               7),
  ('certificacion', 'OEA (Operador Económico Autorizado)', 8),
  ('certificacion', 'IMMEX',                9),
  ('certificacion', 'C-TPAT',              10);

-- Departamentos típicos
INSERT IGNORE INTO `catalogos` (`tipo`, `valor`, `valor_en`, `orden`) VALUES
  ('departamento', 'Dirección General',     'General Management',  1),
  ('departamento', 'Ingeniería',            'Engineering',         2),
  ('departamento', 'Producción',            'Production',          3),
  ('departamento', 'Calidad',               'Quality',             4),
  ('departamento', 'Recursos Humanos',      'Human Resources',     5),
  ('departamento', 'Compras',               'Procurement',         6),
  ('departamento', 'Ventas',                'Sales',               7),
  ('departamento', 'Logística',             'Logistics',           8),
  ('departamento', 'Tecnologías de Información','IT',              9),
  ('departamento', 'Finanzas',              'Finance',            10);

-- ============================================================
-- Verificación post-ejecución
-- ============================================================
-- En phpMyAdmin: pestaña Structure de cada tabla. Confirmar columnas/tablas nuevas.
-- En Hostinger Shared, INFORMATION_SCHEMA puede dar Error #1044 — esperado, ignorar.
