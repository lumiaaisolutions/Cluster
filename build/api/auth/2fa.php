<?php
/**
 * API de 2FA — gestión de Google Authenticator / TOTP
 *
 * Endpoints:
 *   GET  ?action=status               → ¿está activado para mi usuario?
 *   POST { action: 'setup' }          → genera secret + QR URI (no activa aún)
 *   POST { action: 'enable', code }   → activa 2FA con código de verificación
 *   POST { action: 'disable', code }  → desactiva (requiere código actual)
 *   POST { action: 'verify', code }   → valida código (usado en flow de login)
 */

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    define('CLAUT_ACCESS', true);
    require_once dirname(dirname(__DIR__)) . '/config/session-config.php';
    SessionConfig::init();
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../utils/TOTP.php';

    $userId = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? null;
    if (!$userId || !$userEmail) out(['success' => false, 'message' => 'No autenticado'], 401);

    $db = Database::getInstance()->getConnection();
    $action = $_GET['action'] ?? null;
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!$action && isset($body['action'])) $action = $body['action'];

    // === STATUS ===
    if ($action === 'status') {
        $stmt = $db->prepare("SELECT activado FROM usuario_2fa WHERE usuario_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        out(['success' => true, 'data' => ['enabled' => $row ? (bool)$row['activado'] : false]]);
    }

    // === SETUP (generar secret, devolver QR URI) ===
    if ($action === 'setup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $secret = TOTP::generateSecret();
        $stmt = $db->prepare(
            "INSERT INTO usuario_2fa (usuario_id, secret, activado)
             VALUES (:uid, :secret, 0)
             ON DUPLICATE KEY UPDATE secret = :secret, activado = 0"
        );
        $stmt->execute([':uid' => $userId, ':secret' => $secret]);

        $uri = TOTP::buildUri($secret, $userEmail);
        // QR vía servicio externo (no se almacena, solo se renderiza)
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($uri) . '&size=240x240';

        out([
            'success' => true,
            'data' => [
                'secret'   => $secret,
                'uri'      => $uri,
                'qr_url'   => $qrUrl,
            ],
        ]);
    }

    // === ENABLE (confirmar con código) ===
    if ($action === 'enable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim((string)($body['code'] ?? ''));
        $stmt = $db->prepare("SELECT secret FROM usuario_2fa WHERE usuario_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) out(['success' => false, 'message' => 'Primero ejecuta setup'], 400);

        if (!TOTP::verify($row['secret'], $code)) {
            out(['success' => false, 'message' => 'Código inválido. Verifica la hora de tu dispositivo.'], 400);
        }

        $recovery = TOTP::generateRecoveryCodes(10);
        $db->prepare(
            "UPDATE usuario_2fa SET activado = 1, recovery_codes = :rc WHERE usuario_id = :uid"
        )->execute([':uid' => $userId, ':rc' => json_encode($recovery)]);

        // Auditoría
        if (file_exists(__DIR__ . '/../../utils/AuditLogger.php')) {
            require_once __DIR__ . '/../../utils/AuditLogger.php';
            AuditLogger::log('2fa.activado', 'usuario', $userId);
        }

        out([
            'success' => true,
            'message' => '2FA activado correctamente',
            'data' => ['recovery_codes' => $recovery],
        ]);
    }

    // === DISABLE ===
    if ($action === 'disable' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim((string)($body['code'] ?? ''));
        $stmt = $db->prepare("SELECT secret, activado FROM usuario_2fa WHERE usuario_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['activado']) out(['success' => false, 'message' => '2FA no estaba activo'], 400);

        if (!TOTP::verify($row['secret'], $code)) {
            out(['success' => false, 'message' => 'Código inválido. Necesario para desactivar.'], 400);
        }

        $db->prepare("DELETE FROM usuario_2fa WHERE usuario_id = :uid")->execute([':uid' => $userId]);

        if (file_exists(__DIR__ . '/../../utils/AuditLogger.php')) {
            require_once __DIR__ . '/../../utils/AuditLogger.php';
            AuditLogger::log('2fa.desactivado', 'usuario', $userId);
        }

        out(['success' => true, 'message' => '2FA desactivado']);
    }

    // === VERIFY (uso programático desde login) ===
    if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim((string)($body['code'] ?? ''));
        $stmt = $db->prepare("SELECT secret, recovery_codes FROM usuario_2fa WHERE usuario_id = :uid AND activado = 1 LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) out(['success' => false, 'message' => '2FA no configurado'], 400);

        if (TOTP::verify($row['secret'], $code)) {
            $_SESSION['2fa_verified'] = true;
            out(['success' => true]);
        }

        // Recovery code
        $rc = json_decode($row['recovery_codes'] ?? '[]', true) ?: [];
        $idx = array_search(strtoupper($code), array_map('strtoupper', $rc), true);
        if ($idx !== false) {
            unset($rc[$idx]);
            $db->prepare("UPDATE usuario_2fa SET recovery_codes = :rc WHERE usuario_id = :uid")
               ->execute([':uid' => $userId, ':rc' => json_encode(array_values($rc))]);
            $_SESSION['2fa_verified'] = true;
            out(['success' => true, 'data' => ['used_recovery' => true, 'remaining' => count($rc)]]);
        }

        out(['success' => false, 'message' => 'Código inválido'], 400);
    }

    out(['success' => false, 'message' => 'Acción no válida'], 400);

} catch (Throwable $e) {
    error_log('[api/auth/2fa] ' . $e->getMessage());
    out(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
