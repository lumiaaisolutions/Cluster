<?php
/**
 * theme-config.php — Configuración global del tema (theme_config table).
 * GET  → cualquiera (datos públicos del branding)
 * PUT  → solo superadmin
 */
require_once __DIR__ . '/../config/session-config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/security-headers.php';
require_once __DIR__ . '/../middleware/csrf-protection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = (new Database())->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $rows = $pdo->query("SELECT clave, valor FROM theme_config")->fetchAll(PDO::FETCH_KEY_PAIR);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // Mutaciones requieren superadmin
    $superadmin = (int)($_SESSION['superadmin'] ?? 0);
    if ($superadmin !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo superadmin puede modificar el tema']);
        exit;
    }

    if ($method === 'PUT') {
        if (class_exists('CSRFProtection')) CSRFProtection::validate();
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Body inválido']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE theme_config SET valor = :v, updated_by = :u WHERE clave = :k");
        $count = 0;
        foreach ($body as $k => $v) {
            $stmt->execute([
                ':k' => $k,
                ':v' => is_string($v) ? $v : json_encode($v),
                ':u' => $_SESSION['user_id'] ?? null,
            ]);
            $count += $stmt->rowCount();
        }
        echo json_encode(['success' => true, 'updated' => $count]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    error_log('[theme-config] ' . $e->getMessage());
}
