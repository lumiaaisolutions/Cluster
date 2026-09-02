-- ============================================================================
-- TABLA `usuario_2fa` — Secret TOTP por usuario
-- ============================================================================
-- Almacena el secret de Google Authenticator / Authy / 1Password para cada
-- usuario que activa 2FA. Solo se exige a admins, opcional para otros.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `usuario_2fa` (
    `usuario_id`     INT UNSIGNED NOT NULL PRIMARY KEY,
    `secret`         VARCHAR(64)  NOT NULL          COMMENT 'Base32 secret TOTP',
    `activado`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=activo, 0=secret generado pero no confirmado',
    `recovery_codes` TEXT NULL                       COMMENT 'JSON array de códigos de recuperación (10 códigos)',
    `creado_en`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuración 2FA por usuario';
