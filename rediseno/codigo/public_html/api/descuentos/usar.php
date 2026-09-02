<?php
/**
 * descuentos/usar.php — Genera código único para usuario que reclama descuento.
 * POST /api/descuentos/usar.php  body: { descuento_id: X }
 * Retorna: { codigo_unico, validate_url }
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
    echo json_encode(['success' => false, 'message' => 'Inicia sesión para reclamar el descuento']);
    exit;
}

if (class_exists('CSRFProtection')) CSRFProtection::validate();

try {
    $pdo = (new Database())->getConnection();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $descuentoId = (int)($body['descuento_id'] ?? 0);
    $userId = (int)$_SESSION['user_id'];

    if (!$descuentoId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'descuento_id requerido']);
        exit;
    }

    // Validar que el descuento esté activo y vigente
    $stmt = $pdo->prepare("
        SELECT id, titulo, fecha_inicio, fecha_fin, usos_maximos, usos_actuales, estado
        FROM descuentos
        WHERE id = :id
    ");
    $stmt->execute([':id' => $descuentoId]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$d) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Descuento no encontrado']);
        exit;
    }
    if ($d['estado'] !== 'activo') {
        echo json_encode(['success' => false, 'message' => 'Descuento no está activo']);
        exit;
    }
    $hoy = date('Y-m-d');
    if (($d['fecha_fin'] ?? null) && $d['fecha_fin'] < $hoy) {
        echo json_encode(['success' => false, 'message' => 'Descuento expirado']);
        exit;
    }
    if ($d['usos_maximos'] && $d['usos_actuales'] >= $d['usos_maximos']) {
        echo json_encode(['success' => false, 'message' => 'Sin disponibilidad']);
        exit;
    }

    // UUID-like
    $codigo = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("
        INSERT INTO descuentos_usos (descuento_id, usuario_id, codigo_unico, usado, fecha_uso)
        VALUES (:d, :u, :c, 0, NOW())
    ");
    $stmt->execute([':d' => $descuentoId, ':u' => $userId, ':c' => $codigo]);

    $pdo->prepare("UPDATE descuentos SET usos_actuales = usos_actuales + 1 WHERE id = :id")
        ->execute([':id' => $descuentoId]);

    $host = $_SERVER['HTTP_HOST'] ?? 'intranet.clautmetropolitano.mx';
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $validateUrl = "{$proto}://{$host}/validar.html?c={$codigo}";

    echo json_encode([
        'success' => true,
        'codigo_unico' => $codigo,
        'validate_url' => $validateUrl,
        'descuento' => $d['titulo'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    error_log('[descuentos/usar] ' . $e->getMessage());
}
