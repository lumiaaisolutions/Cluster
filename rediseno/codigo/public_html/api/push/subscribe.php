<?php
/**
 * push/subscribe.php — Registra/actualiza una subscripción Web Push.
 * POST body: { endpoint, keys: { p256dh, auth }, user_agent? }
 */
require_once __DIR__ . '/../../config/session-config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/security-headers.php';
require_once __DIR__ . '/../../middleware/csrf-protection.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if (class_exists('CSRFProtection')) CSRFProtection::validate();

try {
    $body = json_decode(file_get_contents('php://input'), true);
    $endpoint = $body['endpoint'] ?? '';
    $p256dh = $body['keys']['p256dh'] ?? '';
    $auth = $body['keys']['auth'] ?? '';
    $ua = substr($body['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

    if (!$endpoint || !$p256dh || !$auth) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subscription incompleta']);
        exit;
    }

    $pdo = (new Database())->getConnection();
    // Reemplazar si ya existe el mismo endpoint para el user
    $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = :u AND endpoint = :e")
        ->execute([':u' => $_SESSION['user_id'], ':e' => $endpoint]);
    $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
        VALUES (:u, :e, :p, :a, :ua)
    ")->execute([
        ':u' => $_SESSION['user_id'],
        ':e' => $endpoint,
        ':p' => $p256dh,
        ':a' => $auth,
        ':ua' => $ua,
    ]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    error_log('[push/subscribe] ' . $e->getMessage());
}
