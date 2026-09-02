-- ============================================================================
-- ELIMINAR TABLA OBSOLETA `empresas`
-- ============================================================================
-- CONTEXTO: La BD tiene dos tablas paralelas: `empresas` (legacy) y
-- `empresas_convenio` (canónica, en uso). Todos los archivos PHP del sistema
-- ya fueron migrados a apuntar a `empresas_convenio`.
--
-- ESTE SCRIPT: elimina la tabla `empresas` definitivamente.
--
-- ⚠️ ANTES DE EJECUTAR:
-- 1. Ejecutar primero `fix_collations.sql` (normaliza collations).
-- 2. Hacer BACKUP completo de la BD (Hostinger → Bases de datos → Backup).
-- 3. Confirmar que el sitio funciona correctamente con el código nuevo
--    (apuntando a empresas_convenio) — probar:
--      - Listado de empresas en /demo_empresas.html
--      - Registro de socio nuevo en /pages/sign-up.html (dropdown empresas)
--      - Panel admin con empresas
-- 4. Solo cuando todo lo anterior funcione bien, ejecutar este script.
-- ============================================================================

-- Diagnóstico previo: ¿cuántos registros tiene cada tabla?
-- SELECT 'empresas' AS tabla, COUNT(*) AS total FROM empresas
-- UNION ALL
-- SELECT 'empresas_convenio', COUNT(*) FROM empresas_convenio;

-- ============================================================================
-- BACKUP defensivo: copiar empresas → empresas_BACKUP_pre_drop
-- (por si después se necesita rescatar algo)
-- ============================================================================
DROP TABLE IF EXISTS empresas_BACKUP_pre_drop;
CREATE TABLE empresas_BACKUP_pre_drop AS SELECT * FROM empresas;

-- ============================================================================
-- ELIMINAR la tabla legacy
-- ============================================================================
DROP TABLE IF EXISTS empresas;

-- ============================================================================
-- Verificación: la tabla ya no debe existir
-- ============================================================================
-- SHOW TABLES LIKE 'empresas';   -- debe estar vacío
-- SHOW TABLES LIKE 'empresas_%'; -- debe mostrar empresas_convenio y empresas_BACKUP_pre_drop
--
-- NOTA: empresas_BACKUP_pre_drop puede borrarse después de 1-2 semanas si todo
-- sigue funcionando bien:
--   DROP TABLE empresas_BACKUP_pre_drop;
