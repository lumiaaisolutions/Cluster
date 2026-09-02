<?php
/**
 * AuditLogger — Registra acciones críticas del sistema en `audit_log`.
 *
 * USO:
 *   require_once __DIR__ . '/../utils/AuditLogger.php';
 *   AuditLogger::log('empresa.eliminar', 'empresa', $empresaId, ['nombre' => $nombre]);
 *
 * Best-effort: si la tabla no existe o falla la inserción, NO bloquea el flujo
 * principal (solo deja un error_log para diagnóstico).
 */

if (!class_exists('AuditLogger')) {

class AuditLogger
{
    /**
     * Registra una acción en la bitácora.
     *
     * @param string $accion     Ej: 'empresa.eliminar', 'usuario.aprobar'
     * @param string|null $entidad   Ej: 'empresa', 'usuario'
     * @param mixed|null $entidadId  ID del registro afectado (acepta int/string)
     * @param array|string|null $detalle Contexto extra (se serializa como JSON si es array)
     */
    public static function log(
        string $accion,
        ?string $entidad = null,
        $entidadId = null,
        $detalle = null
    ): void {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            $configPath = __DIR__ . '/../config/database.php';
            if (!file_exists($configPath)) {
                error_log('[AuditLogger] database.php no encontrado');
                return;
            }
            require_once $configPath;
            $db = Database::getInstance()->getConnection();

            $usuarioId    = $_SESSION['user_id']    ?? $_SESSION['usuario_id'] ?? null;
            $usuarioEmail = $_SESSION['user_email'] ?? $_SESSION['email']      ?? null;
            $usuarioRol   = $_SESSION['user_rol']   ?? $_SESSION['rol']        ?? $_SESSION['user_role'] ?? null;

            $detalleStr = null;
            if (is_array($detalle) || is_object($detalle)) {
                $detalleStr = json_encode($detalle, JSON_UNESCAPED_UNICODE);
            } elseif (is_string($detalle)) {
                $detalleStr = $detalle;
            }

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? null;
            if ($ip && strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

            $stmt = $db->prepare(
                "INSERT INTO audit_log
                    (usuario_id, usuario_email, usuario_rol, accion, entidad, entidad_id,
                     detalle, ip, user_agent)
                 VALUES
                    (:uid, :uemail, :urol, :accion, :entidad, :eid,
                     :detalle, :ip, :ua)"
            );
            $stmt->execute([
                ':uid'     => $usuarioId !== null ? (int)$usuarioId : null,
                ':uemail'  => $usuarioEmail,
                ':urol'    => $usuarioRol,
                ':accion'  => substr($accion, 0, 80),
                ':entidad' => $entidad !== null ? substr($entidad, 0, 80) : null,
                ':eid'     => $entidadId !== null ? substr((string)$entidadId, 0, 64) : null,
                ':detalle' => $detalleStr,
                ':ip'      => $ip ? substr($ip, 0, 45) : null,
                ':ua'      => $userAgent ?: null,
            ]);
        } catch (Throwable $e) {
            // NUNCA bloquear el flujo principal por un fallo de auditoría
            error_log('[AuditLogger] ' . $e->getMessage());
        }
    }
}

} // if !class_exists
