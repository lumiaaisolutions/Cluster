-- ============================================================================
-- FIX: Normalizar collations de toda la BD a utf8mb4_unicode_ci
-- ============================================================================
-- PROBLEMA: Algunas tablas se crearon con utf8mb4_uca1400_ai_ci (default de
-- MariaDB 10.10+) y otras con utf8mb4_unicode_ci. JOINs entre columnas con
-- collations distintas disparan error 1267 "Illegal mix of collations".
--
-- SOLUCIÓN: Convertir todas las tablas al mismo collation.
--
-- INSTRUCCIONES:
-- 1. Hacer BACKUP de la BD ANTES de ejecutar este script (Hostinger →
--    Bases de datos → Tu BD → Backup).
-- 2. Entrar a phpMyAdmin → seleccionar la BD u695712029_claut_intranet.
-- 3. Pestaña SQL → pegar este script completo → Ejecutar.
-- 4. Verificar que termina sin errores (verá filas afectadas en cada tabla).
-- 5. Probar login + registro + verificación de email para confirmar que todo
--    sigue funcionando.
-- ============================================================================

-- Forzar set/collation para conexiones siguientes
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Diagnóstico previo (ejecutar manualmente para ver el estado actual)
-- SELECT TABLE_NAME, TABLE_COLLATION
--   FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = DATABASE()
--  ORDER BY TABLE_COLLATION, TABLE_NAME;

-- ============================================================================
-- CONVERTIR TODAS LAS TABLAS al mismo collation
-- ============================================================================
-- NOTA: Esta operación bloquea cada tabla por unos segundos. En total ~30-60
-- segundos para 35 tablas. Hacer en horario de baja actividad.
-- ============================================================================

ALTER TABLE banners                            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE banner_carrusel                    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE boletines                          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE comites                            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE comite_registros                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE configuraciones                    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE contactos                          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE descuentos                         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE descuentos_usos                    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE descuento_usos                     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE documentos                         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE email_notification_config          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE email_tokens                       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE empresas_convenio                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE empresa_comite                     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE estadisticas_config                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE eventos                            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE evento_agenda                      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE evento_asistentes                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE evento_registros                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mensajes_comites                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE mensajes_usuario                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notificaciones                     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notificaciones_backup              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notificaciones_cambios_perfil      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE registros_eventos                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE solicitudes_empresa                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios                           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios_mensajes                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios_mensajes_individuales     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuarios_perfil                    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE usuario_restricciones              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- NOTA: la tabla 'empresas' será eliminada después (ver delete_tabla_empresas.sql)

-- ============================================================================
-- Verificación final — ejecutar después de los ALTER
-- ============================================================================
-- Debe mostrar todas con utf8mb4_unicode_ci:
-- SELECT TABLE_NAME, TABLE_COLLATION
--   FROM information_schema.TABLES
--  WHERE TABLE_SCHEMA = DATABASE()
--  ORDER BY TABLE_NAME;
