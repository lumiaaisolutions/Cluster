-- ============================================================================
-- TABLA `mensajes_socios` — Mensajería directa entre socios
-- ============================================================================
-- Separada de `usuarios_mensajes` (que es para mensajes administrativos broadcast)
-- para no contaminar lógica existente.
--
-- Cada mensaje es de 1 socio a otro. Soporta hilos via thread_id (group por par).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `mensajes_socios` (
    `id`              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `remitente_id`    INT UNSIGNED NOT NULL          COMMENT 'FK → usuarios_perfil.id',
    `destinatario_id` INT UNSIGNED NOT NULL          COMMENT 'FK → usuarios_perfil.id',
    `asunto`          VARCHAR(200) NULL,
    `contenido`       TEXT NOT NULL,
    `leido`           TINYINT(1)   NOT NULL DEFAULT 0,
    `leido_en`        TIMESTAMP NULL,
    `archivado_por_remitente`    TINYINT(1) NOT NULL DEFAULT 0,
    `archivado_por_destinatario` TINYINT(1) NOT NULL DEFAULT 0,
    `creado_en`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ms_destinatario_leido` (`destinatario_id`, `leido`, `creado_en`),
    KEY `idx_ms_remitente`          (`remitente_id`, `creado_en`),
    KEY `idx_ms_par`                (`remitente_id`, `destinatario_id`, `creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mensajería directa socio-socio (privada)';
