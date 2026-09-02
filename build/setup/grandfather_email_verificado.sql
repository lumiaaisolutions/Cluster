-- ============================================================================
-- MIGRACIÓN "GRANDFATHER": marcar usuarios LEGACY como ya verificados
-- ============================================================================
-- CONTEXTO: A partir del 2026-05-12 el login exige `email_verificado = 1`.
-- Los usuarios registrados ANTES tienen ese campo en 0 (default del schema),
-- aunque hayan estado usando el sistema durante meses.
--
-- ESTE SCRIPT: marca como verificados a todos los usuarios que YA estaban
-- en estado `activo` antes de la nueva validación. Es una migración única.
--
-- ⚠️ EJECUTAR ANTES de subir el nuevo login-compatible.php, o los usuarios
-- existentes serán bloqueados.
-- ============================================================================

-- 1. Diagnóstico previo: ¿cuántos usuarios serían afectados?
--    (ejecutar y revisar antes de aplicar el UPDATE de abajo)
SELECT
    COUNT(*) AS total_legacy_sin_verificar,
    SUM(CASE WHEN estado_usuario = 'activo' THEN 1 ELSE 0 END) AS activos_que_se_bloquearian
FROM usuarios_perfil
WHERE email_verificado = 0;

-- 2. Migración: marcar como verificados a TODOS los usuarios actualmente activos.
--    Si ya estaban en 'activo', es porque el admin los aprobó, y para aprobarlos
--    el admin razonablemente vio que su email funciona. Es seguro asumir que el
--    email es real.
UPDATE usuarios_perfil
   SET email_verificado = 1
 WHERE estado_usuario = 'activo'
   AND email_verificado = 0;

-- 3. Verificación final
SELECT
    estado_usuario,
    email_verificado,
    COUNT(*) AS total
FROM usuarios_perfil
GROUP BY estado_usuario, email_verificado
ORDER BY estado_usuario, email_verificado;
-- Resultado esperado:
--   activo + email_verificado=1 → todos los socios y staff funcionando
--   pendiente + email_verificado=0 → nuevos registros que no han verificado
--   pendiente + email_verificado=1 → verificaron email, esperando aprobación admin
