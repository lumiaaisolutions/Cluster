<?php
/**
 * catalogos.php — CRUD sobre catálogos editables (sectores, municipios, etc).
 * GET    /api/catalogos.php?tipo=sector            → lista
 * POST   /api/catalogos.php (admin)                → crear
 * PUT    /api/catalogos.php?id=X (admin)           → editar
 * DELETE /api/catalogos.php?id=X (admin)           → eliminar (soft via activo=0)
 */
require_once __DIR__ . '/../config/session-config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/security-headers.php';
require_once __DIR__ . '/../middleware/csrf-protection.php';

header('Content-Type: application/json; charset=utf-8');

function isAdmin() {
    $rol = strtolower($_SESSION['user_rol'] ?? '');
    $super = (int)($_SESSION['superadmin'] ?? 0) === 1;
    return $super || in_array($rol, ['admin', 'administrador', 'root'], true);
}

try {
    $pdo = (new Database())->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $tipo = $_GET['tipo'] ?? null;
        $sql = "SELECT id, tipo, valor, valor_en, parent_id, orden, activo
                FROM catalogos WHERE activo = 1";
        $params = [];
        if ($tipo) {
            $sql .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }
        $sql .= " ORDER BY tipo, orden, valor";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && class_exists('CSRFProtection')) {
        CSRFProtection::validate();
    }

    if ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $stmt = $pdo->prepare("
            INSERT INTO catalogos (tipo, valor, valor_en, parent_id, orden, activo)
            VALUES (:tipo, :valor, :valor_en, :parent_id, :orden, 1)
        ");
        $stmt->execute([
            ':tipo' => $body['tipo'] ?? '',
            ':valor' => trim($body['valor'] ?? ''),
            ':valor_en' => $body['valor_en'] ?? null,
            ':parent_id' => $body['parent_id'] ?? null,
            ':orden' => (int)($body['orden'] ?? 0),
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID requerido']); exit; }
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [':id' => $id];
        foreach (['valor', 'valor_en', 'parent_id', 'orden', 'activo'] as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (!$fields) { echo json_encode(['success' => true, 'message' => 'Sin cambios']); exit; }
        $sql = "UPDATE catalogos SET " . implode(', ', $fields) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID requerido']); exit; }
        $pdo->prepare("UPDATE catalogos SET activo = 0 WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    error_log('[catalogos] ' . $e->getMessage());
}
