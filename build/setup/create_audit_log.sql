-- ============================================================================
-- TABLA `audit_log` — Bitácora de acciones críticas del sistema
-- ============================================================================
-- Registra qué usuario hizo qué acción en qué entidad, cuándo y desde qué IP.
-- Visible solo para administradores en /admin/auditoria.html.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id`   INT UNSIGNED NULL              COMMENT 'FK lógica → usuarios_perfil.id',
    `usuario_email` VARCHAR(255) NULL              COMMENT 'Email del actor (redundante para trazabilidad si se borra usuario)',
    `usuario_rol`   VARCHAR(50)  NULL              COMMENT 'Rol al momento del evento',
    `accion`       VARCHAR(80)  NOT NULL          COMMENT 'Ej: empresa.eliminar, usuario.aprobar, descuento.crear',
    `entidad`      VARCHAR(80)  NULL              COMMENT 'Ej: empresa, usuario, evento',
    `entidad_id`   VARCHAR(64)  NULL              COMMENT 'ID del registro afectado',
    `detalle`      TEXT NULL                       COMMENT 'JSON o texto: cambios antes/después, contexto',
    `ip`           VARCHAR(45)  NULL,
    `user_agent`   VARCHAR(255) NULL,
    `creado_en`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_audit_creado` (`creado_en`),
    KEY `idx_audit_usuario` (`usuario_id`),
    KEY `idx_audit_accion`  (`accion`),
    KEY `idx_audit_entidad` (`entidad`, `entidad_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bitácora de acciones críticas — para administradores';
