<?php
/**
 * verify-account.php — Verificación de cuenta por correo
 *
 * GET  ?token=xxx  → activa el flag email_verificado en usuarios_perfil
 *                    → redirige a sign-in con mensaje de éxito
 * POST { email }   → reenvía el correo de verificación al email indicado
 */

define('CLAUT_ACCESS', true);
require_once dirname(dirname(__DIR__)) . '/config/session-config.php';
SessionConfig::init();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/env-loader.php';
require_once __DIR__ . '/../../utils/security-logger.php';
require_once __DIR__ . '/../../services/EmailService.php';

EnvLoader::load();

function jsonResponse(bool $success, string $message, array $extra = []): void {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // ── GET: Activar cuenta por token ────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = trim($_GET['token'] ?? '');

        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            header('Location: ../../pages/sign-in.html?verify=invalid');
            exit();
        }

        // COLLATE forzado: las tablas tienen utf8mb4_unicode_ci vs utf8mb4_uca1400_ai_ci,
        // sin COLLATE el JOIN dispara "Illegal mix of collations" (1267).
        $stmt = $db->prepare(
            "SELECT et.id, et.user_email, et.expires_at, et.usado,
                    up.id AS user_id, up.nombre
             FROM email_tokens et
             JOIN usuarios_perfil up
               ON up.email COLLATE utf8mb4_unicode_ci = et.user_email COLLATE utf8mb4_unicode_ci
              AND up.activo = 1
             WHERE et.token = :token AND et.tipo = 'account_verify'
             LIMIT 1"
        );
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData || $tokenData['usado']) {
            header('Location: ../../pages/sign-in.html?verify=used');
            exit();
        }

        if (strtotime($tokenData['expires_at']) < time()) {
            header('Location: ../../pages/sign-in.html?verify=expired');
            exit();
        }

        // Marcar email como verificado
        $db->prepare(
            "UPDATE usuarios_perfil SET email_verificado = 1 WHERE email = :email AND activo = 1"
        )->execute([':email' => $tokenData['user_email']]);

        // Invalidar token
        $db->prepare("UPDATE email_tokens SET usado = 1 WHERE token = :token")
           ->execute([':token' => $token]);

        SecurityLogger::log('account_email_verified', 'INFO', [
            'email' => $tokenData['user_email']
        ]);

        // Redirigir al login con mensaje de éxito
        header('Location: ../../pages/sign-in.html?verify=success');
        exit();
    }

    // Resto: respuestas JSON
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

    // ── POST: Reenviar correo de verificación ────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = strtolower(trim($input['email'] ?? ''));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Email inválido.');
        }

        // Rate limit: 2 reenvíos por email en la última hora
        $stmtRate = $db->prepare(
            "SELECT COUNT(*) FROM email_tokens
             WHERE user_email = :email AND tipo = 'account_verify'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmtRate->bindParam(':email', $email);
        $stmtRate->execute();

        if ((int)$stmtRate->fetchColumn() >= 2) {
            jsonResponse(true, 'Si el correo está registrado, recibirás el enlace en breve.');
        }

        // Buscar usuario
        $stmtUser = $db->prepare(
            "SELECT id, nombre, apellidos, email_verificado
             FROM usuarios_perfil
             WHERE email = :email AND activo = 1
             LIMIT 1"
        );
        $stmtUser->bindParam(':email', $email);
        $stmtUser->execute();
        $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // Respuesta genérica siempre
        if (!$usuario || $usuario['email_verificado']) {
            jsonResponse(true, 'Si el correo está registrado y sin verificar, recibirás el enlace en breve.');
        }

        // Generar nuevo token
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $nombre    = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? ''));

        // Invalidar tokens previos
        $db->prepare(
            "UPDATE email_tokens SET usado = 1
             WHERE user_email = :email AND tipo = 'account_verify' AND usado = 0"
        )->execute([':email' => $email]);

        $db->prepare(
            "INSERT INTO email_tokens (user_email, token, tipo, expires_at, ip_origen)
             VALUES (:email, :token, 'account_verify', :expires_at, :ip)"
        )->execute([
            ':email'      => $email,
            ':token'      => $token,
            ':expires_at' => $expiresAt,
            ':ip'         => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);

        EmailService::sendAccountVerification($email, $nombre ?: 'Usuario', $token);

        jsonResponse(true, 'Si el correo está registrado y sin verificar, recibirás el enlace en breve.');
    }

    jsonResponse(false, 'Método no permitido.');

} catch (Throwable $e) {
    error_log('[verify-account] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    header('Content-Type: application/json; charset=UTF-8');
    jsonResponse(false, 'Ocurrió un error. Intenta nuevamente.');
}
?>
