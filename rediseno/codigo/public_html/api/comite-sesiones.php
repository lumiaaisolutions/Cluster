<?php
/**
 * comite-sesiones.php — CRUD de sesiones programadas por comité.
 * GET    ?comite_id=X    → próximas + pasadas
 * GET    ?proximas=1     → todas las sesiones próximas (todos los comités)
 * POST   (admin)         → crear sesión
 * PUT    ?id=X (admin)
 * DELETE ?id=X (admin)
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
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }

    $pdo = (new Database())->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (!empty($_GET['proximas'])) {
            $stmt = $pdo->prepare("
                SELECT s.*, c.nombre AS comite_nombre
                FROM comite_sesiones s
                JOIN comites c ON c.id = s.comite_id
                WHERE s.fecha >= CURDATE() AND s.estado IN ('programada', 'en_curso')
                ORDER BY s.fecha ASC, s.hora ASC
                LIMIT 50
            ");
            $stmt->execute();
        } else {
            $comiteId = (int)($_GET['comite_id'] ?? 0);
            if (!$comiteId) { echo json_encode(['success' => true, 'data' => []]); exit; }
            $stmt = $pdo->prepare("
                SELECT s.*, c.nombre AS comite_nombre
                FROM comite_sesiones s
                JOIN comites c ON c.id = s.comite_id
                WHERE s.comite_id = :c
                ORDER BY s.fecha DESC, s.hora DESC
            ");
            $stmt->execute([':c' => $comiteId]);
        }
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo administradores']);
        exit;
    }

    if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && class_exists('CSRFProtection')) {
        CSRFProtection::validate();
    }

    if ($method === 'POST') {
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        $stmt = $pdo->prepare("
            INSERT INTO comite_sesiones (comite_id, fecha, hora, lugar, agenda, link_meet, estado)
            VALUES (:c, :f, :h, :l, :a, :m, COALESCE(:e, 'programada'))
        ");
        $stmt->execute([
            ':c' => (int)($b['comite_id'] ?? 0),
            ':f' => $b['fecha'] ?? null,
            ':h' => $b['hora'] ?? null,
            ':l' => $b['lugar'] ?? null,
            ':a' => $b['agenda'] ?? null,
            ':m' => $b['link_meet'] ?? null,
            ':e' => $b['estado'] ?? null,
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID requerido']); exit; }
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [':id' => $id];
        foreach (['fecha', 'hora', 'lugar', 'agenda', 'link_meet', 'estado'] as $f) {
            if (array_key_exists($f, $b)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $b[$f];
            }
        }
        if (!$fields) { echo json_encode(['success' => true]); exit; }
        $pdo->prepare("UPDATE comite_sesiones SET " . implode(', ', $fields) . " WHERE id = :id")
            ->execute($params);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID requerido']); exit; }
        $pdo->prepare("UPDATE comite_sesiones SET estado = 'cancelada' WHERE id = :id")
            ->execute([':id' => $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
    error_log('[comite-sesiones] ' . $e->getMessage());
}
