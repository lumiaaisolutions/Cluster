<?php
/**
 * reset-password.php — Restablecer contraseña con token
 *
 * GET ?token=xxx         → valida el token (para verificar si es válido antes de mostrar form)
 * POST { token, password, password_confirm }
 *   → valida token (existe, no usado, no expirado)
 *   → valida nueva contraseña
 *   → actualiza hash en usuarios_perfil
 *   → invalida el token
 *   → envía correo de confirmación
 */

define('CLAUT_ACCESS', true);
require_once dirname(dirname(__DIR__)) . '/config/session-config.php';
SessionConfig::init();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env-loader.php';
require_once __DIR__ . '/../../utils/security-logger.php';
require_once __DIR__ . '/../../services/EmailService.php';

EnvLoader::load();

function jsonResponse(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

function validarPassword(string $pass): bool {
    return strlen($pass) >= 8
        && preg_match('/[A-Z]/', $pass)
        && preg_match('/[a-z]/', $pass)
        && preg_match('/[0-9]/', $pass);
}

/**
 * Busca y valida el token en la BD
 */
function buscarToken(PDO $db, string $token): ?array {
    // COLLATE forzado: email_tokens y usuarios_perfil tienen collations distintas
    // (utf8mb4_unicode_ci vs utf8mb4_uca1400_ai_ci) — sin esto el JOIN dispara error 1267.
    $stmt = $db->prepare(
        "SELECT et.id, et.user_email, et.expires_at, et.usado,
                up.id AS user_id, up.nombre, up.apellidos
         FROM email_tokens et
         JOIN usuarios_perfil up
           ON up.email COLLATE utf8mb4_unicode_ci = et.user_email COLLATE utf8mb4_unicode_ci
          AND up.activo = 1
         WHERE et.token = :token AND et.tipo = 'password_reset'
         LIMIT 1"
    );
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    $db = Database::getInstance()->getConnection();

    // ── GET: Verificar si el token es válido ─────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = trim($_GET['token'] ?? '');

        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            jsonResponse(false, 'Token inválido.');
        }

        $tokenData = buscarToken($db, $token);

        if (!$tokenData) {
            jsonResponse(false, 'El enlace de recuperación no es válido.');
        }
        if ($tokenData['usado']) {
            jsonResponse(false, 'Este enlace ya fue utilizado. Solicita uno nuevo.');
        }
        if (strtotime($tokenData['expires_at']) < time()) {
            jsonResponse(false, 'El enlace ha expirado. Solicita uno nuevo.');
        }

        jsonResponse(true, 'Token válido.', ['email' => $tokenData['user_email']]);
    }

    // ── POST: Resetear contraseña ─────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input    = json_decode(file_get_contents('php://input'), true);
        $token    = trim($input['token'] ?? '');
        $password = $input['password'] ?? '';
        $confirm  = $input['password_confirm'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Validaciones
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            jsonResponse(false, 'Token inválido.');
        }
        if ($password !== $confirm) {
            jsonResponse(false, 'Las contraseñas no coinciden.');
        }
        if (!validarPassword($password)) {
            jsonResponse(false, 'La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas y números.');
        }

        $tokenData = buscarToken($db, $token);

        if (!$tokenData) {
            jsonResponse(false, 'El enlace de recuperación no es válido.');
        }
        if ($tokenData['usado']) {
            jsonResponse(false, 'Este enlace ya fue utilizado. Solicita uno nuevo.');
        }
        if (strtotime($tokenData['expires_at']) < time()) {
            jsonResponse(false, 'El enlace ha expirado. Solicita uno nuevo.');
        }

        // Actualizar contraseña
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmtUpd = $db->prepare(
            "UPDATE usuarios_perfil SET password = :hash WHERE email = :email AND activo = 1"
        );
        $stmtUpd->execute([':hash' => $newHash, ':email' => $tokenData['user_email']]);

        if ($stmtUpd->rowCount() === 0) {
            jsonResponse(false, 'No se pudo actualizar la contraseña. Intenta nuevamente.');
        }

        // Invalidar token
        $db->prepare("UPDATE email_tokens SET usado = 1 WHERE token = :token")
           ->execute([':token' => $token]);

        // Enviar correo de confirmación
        $nombre = trim(($tokenData['nombre'] ?? '') . ' ' . ($tokenData['apellidos'] ?? ''));
        EmailService::sendNotification(
            $tokenData['user_email'],
            $nombre ?: 'Usuario',
            'Contraseña actualizada correctamente',
            'Tu contraseña en la Intranet del Clúster Automotriz Metropolitano fue cambiada exitosamente.
             Si no realizaste este cambio, contacta al administrador de inmediato.'
        );

        SecurityLogger::log('password_reset_completed', 'INFO', [
            'email' => $tokenData['user_email'],
            'ip'    => $ip
        ]);

        jsonResponse(true, '¡Contraseña actualizada exitosamente! Ya puedes iniciar sesión.');
    }

    jsonResponse(false, 'Método no permitido.');

} catch (Exception $e) {
    error_log('[reset-password] Error: ' . $e->getMessage());
    jsonResponse(false, 'Ocurrió un error. Intenta nuevamente.');
}
?>
