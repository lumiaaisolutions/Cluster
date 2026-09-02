<?php
/**
 * API de Auditoría — solo accesible para administradores.
 *
 * GET /api/auditoria.php
 *   ?limit=50     (default 50, max 500)
 *   &offset=0
 *   &accion=empresa.eliminar    (opcional, filtro)
 *   &entidad=empresa            (opcional, filtro)
 *   &usuario=email@x.com        (opcional, filtro)
 *   &desde=2026-05-01           (opcional)
 *   &hasta=2026-05-31           (opcional)
 *
 * Respuesta: { success, data: { logs: [...], total }, page: {...} }
 */

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    define('CLAUT_ACCESS', true);
    require_once dirname(__DIR__) . '/config/session-config.php';
    SessionConfig::init();
    require_once __DIR__ . '/../config/database.php';

    // Solo admins
    $rol = strtolower($_SESSION['user_rol'] ?? $_SESSION['rol'] ?? '');
    if (!in_array($rol, ['admin', 'administrador', 'root'], true)) {
        out(['success' => false, 'message' => 'No autorizado'], 403);
    }

    $db = Database::getInstance()->getConnection();

    $limit  = max(1, min(500, (int)($_GET['limit']  ?? 50)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $where  = [];
    $params = [];

    if (!empty($_GET['accion']))  { $where[] = 'accion LIKE :accion';   $params[':accion']  = $_GET['accion'] . '%'; }
    if (!empty($_GET['entidad'])) { $where[] = 'entidad = :entidad';    $params[':entidad'] = $_GET['entidad']; }
    if (!empty($_GET['usuario'])) { $where[] = 'usuario_email LIKE :usuario'; $params[':usuario'] = '%' . $_GET['usuario'] . '%'; }
    if (!empty($_GET['desde']))   { $where[] = 'creado_en >= :desde';   $params[':desde']   = $_GET['desde'] . ' 00:00:00'; }
    if (!empty($_GET['hasta']))   { $where[] = 'creado_en <= :hasta';   $params[':hasta']   = $_GET['hasta'] . ' 23:59:59'; }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $stmtTot = $db->prepare("SELECT COUNT(*) AS total FROM audit_log $whereSQL");
    $stmtTot->execute($params);
    $total = (int)$stmtTot->fetchColumn();

    // Listado
    $stmt = $db->prepare(
        "SELECT id, usuario_id, usuario_email, usuario_rol, accion, entidad, entidad_id,
                detalle, ip, creado_en
           FROM audit_log
           $whereSQL
          ORDER BY creado_en DESC
          LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    out([
        'success' => true,
        'data' => [
            'logs'  => $logs,
            'total' => $total,
        ],
        'page' => [
            'limit'  => $limit,
            'offset' => $offset,
            'hasMore' => ($offset + count($logs)) < $total,
        ],
    ]);

} catch (Throwable $e) {
    error_log('[api/auditoria] ' . $e->getMessage());
    out(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
