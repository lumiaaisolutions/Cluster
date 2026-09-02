<?php
/**
 * i18n.php — Editor de traducciones (lee/escribe /i18n/{lang}.json).
 * GET  /api/i18n.php?lang=es     → devuelve JSON
 * PUT  /api/i18n.php?lang=es     → reemplaza JSON (solo superadmin)
 */
require_once __DIR__ . '/../config/session-config.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/security-headers.php';
require_once __DIR__ . '/../middleware/csrf-protection.php';

header('Content-Type: application/json; charset=utf-8');

$lang = $_GET['lang'] ?? 'es';
if (!in_array($lang, ['es', 'en'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Idioma no soportado']);
    exit;
}

$file = __DIR__ . "/../i18n/{$lang}.json";
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (!is_readable($file)) {
            echo json_encode(['success' => true, 'data' => new stdClass()]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'data' => json_decode(file_get_contents($file), true) ?? new stdClass(),
        ]);
        exit;
    }

    if ($method === 'PUT') {
        $superadmin = (int)($_SESSION['superadmin'] ?? 0);
        if ($superadmin !== 1) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo superadmin']);
            exit;
        }
        if (class_exists('CSRFProtection')) CSRFProtection::validate();

        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            exit;
        }
        // Backup antes de sobrescribir
        if (is_file($file)) {
            @copy($file, $file . '.bak');
        }
        file_put_contents($file, json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    error_log('[i18n] ' . $e->getMessage());
}
