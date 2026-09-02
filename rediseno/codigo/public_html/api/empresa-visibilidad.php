<?php
/**
 * empresa-visibilidad.php — Toggle granular de campos en directorio.
 * Solo el dueño (admin_usuario_id) o admin pueden editar.
 *
 * GET ?empresa_id=X  → array {campo: visible}
 * PUT ?empresa_id=X  → body {campo1: 1, campo2: 0, ...}
 */
require_once __DIR__ . '/../config/session-config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/security-headers.php';
require_once __DIR__ . '/../middleware/csrf-protection.php';

header('Content-Type: application/json; charset=utf-8');

const ALLOWED_FIELDS = [
    'nombre', 'sector', 'entidad_federativa', 'municipio', 'descripcion',
    'certificaciones', 'exporta', 'redes_fb', 'redes_x', 'redes_linkedin',
    'redes_instagram', 'logo', 'contacto_persona', 'contacto_cargo',
    'contacto_telefono', 'contacto_email', 'sitio_web',
];

function canEditEmpresa($pdo, $empresaId) {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $rol = strtolower($_SESSION['user_rol'] ?? '');
    $super = (int)($_SESSION['superadmin'] ?? 0) === 1;
    if ($super || in_array($rol, ['admin', 'administrador', 'root'], true)) return true;

    $stmt = $pdo->prepare("SELECT admin_usuario_id FROM empresas_convenio WHERE id = :id");
    $stmt->execute([':id' => $empresaId]);
    $owner = (int)$stmt->fetchColumn();
    return $owner === $userId;
}

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }

    $pdo = (new Database())->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $empresaId = (int)($_GET['empresa_id'] ?? 0);
    if (!$empresaId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'empresa_id requerido']);
        exit;
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT campo, visible FROM empresa_visibilidad_campo WHERE empresa_id = :id");
        $stmt->execute([':id' => $empresaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // Default: todos visibles si no hay registro
        $data = [];
        foreach (ALLOWED_FIELDS as $f) {
            $data[$f] = array_key_exists($f, $rows) ? (int)$rows[$f] : 1;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    if (!canEditEmpresa($pdo, $empresaId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado para editar esta empresa']);
        exit;
    }

    if ($method === 'PUT') {
        if (class_exists('CSRFProtection')) CSRFProtection::validate();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $stmt = $pdo->prepare("
            INSERT INTO empresa_visibilidad_campo (empresa_id, campo, visible)
            VALUES (:e, :c, :v)
            ON DUPLICATE KEY UPDATE visible = VALUES(visible)
        ");
        $count = 0;
        foreach ($body as $campo => $visible) {
            if (!in_array($campo, ALLOWED_FIELDS, true)) continue;
            $stmt->execute([
                ':e' => $empresaId,
                ':c' => $campo,
                ':v' => (int)!!$visible,
            ]);
            $count++;
        }
        echo json_encode(['success' => true, 'updated' => $count]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    error_log('[empresa-visibilidad] ' . $e->getMessage());
}
