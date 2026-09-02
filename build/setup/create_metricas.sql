-- ============================================================================
-- TABLA `perfil_vistas` — Registro de vistas a perfiles de empresa
-- ============================================================================
-- Cada vez que un usuario abre el perfil de una empresa, se registra una vista.
-- Permite a los socios ver cuántas personas se han interesado en su empresa.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `perfil_vistas` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `empresa_id`  INT UNSIGNED NOT NULL          COMMENT 'FK → empresas_convenio.id',
    `visitante_id` INT UNSIGNED NULL              COMMENT 'FK → usuarios_perfil.id (NULL si anónimo)',
    `ip_hash`     CHAR(64) NULL                   COMMENT 'SHA-256 de IP para dedup sin guardar IP cruda',
    `creado_en`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_pv_empresa_fecha` (`empresa_id`, `creado_en`),
    KEY `idx_pv_dedup` (`empresa_id`, `ip_hash`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vistas de perfiles de empresa para métricas del socio';
