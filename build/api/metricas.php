<?php
/**
 * API de Métricas — para socios y admins.
 *
 * POST { action: 'registrar_vista', empresa_id }  → registrar visualización (con dedup 1h por IP)
 * GET  ?action=mi_empresa                          → métricas de la empresa del usuario actual
 * GET  ?action=empresa&id=X                        → métricas de una empresa específica
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
    require_once dirname(__DIR__) . '/config/session-config.php';
    SessionConfig::init();
    require_once __DIR__ . '/../config/database.php';

    $db = Database::getInstance()->getConnection();
    $action = $_GET['action'] ?? null;
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!$action && isset($body['action'])) $action = $body['action'];

    $userId    = $_SESSION['user_id']    ?? $_SESSION['usuario_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? null;

    // === REGISTRAR VISTA ===
    if ($action === 'registrar_vista' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $empresaId = (int)($body['empresa_id'] ?? 0);
        if (!$empresaId) out(['success' => false, 'message' => 'empresa_id requerido'], 400);

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
        $ipHash = hash('sha256', $ip);

        // Dedup: no contar la misma IP dos veces en 1 hora
        $dedup = $db->prepare(
            "SELECT 1 FROM perfil_vistas
              WHERE empresa_id = :eid AND ip_hash = :h
                AND creado_en >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
              LIMIT 1"
        );
        $dedup->execute([':eid' => $empresaId, ':h' => $ipHash]);
        if ($dedup->fetchColumn()) {
            out(['success' => true, 'deduped' => true]);
        }

        $db->prepare(
            "INSERT INTO perfil_vistas (empresa_id, visitante_id, ip_hash)
             VALUES (:eid, :vid, :h)"
        )->execute([
            ':eid' => $empresaId,
            ':vid' => $userId ?: null,
            ':h'   => $ipHash,
        ]);

        out(['success' => true]);
    }

    // === MÉTRICAS DE MI EMPRESA ===
    if ($action === 'mi_empresa') {
        if (!$userId) out(['success' => false, 'message' => 'No autenticado'], 401);

        // Resolver empresa del usuario
        $stmt = $db->prepare("SELECT empresa_id FROM usuarios_perfil WHERE id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $empresaId = $stmt->fetchColumn();
        if (!$empresaId) out(['success' => false, 'message' => 'No tienes empresa asignada'], 404);

        out(['success' => true, 'data' => getMetricas($db, (int)$empresaId)]);
    }

    // === MÉTRICAS DE UNA EMPRESA ===
    if ($action === 'empresa') {
        $empresaId = (int)($_GET['id'] ?? 0);
        if (!$empresaId) out(['success' => false, 'message' => 'id requerido'], 400);

        // Solo dueño o admin pueden ver
        $rol = strtolower($_SESSION['user_rol'] ?? '');
        $esAdmin = in_array($rol, ['admin', 'administrador', 'root'], true);
        if (!$esAdmin) {
            $stmt = $db->prepare("SELECT empresa_id FROM usuarios_perfil WHERE id = :uid");
            $stmt->execute([':uid' => $userId]);
            if ((int)$stmt->fetchColumn() !== $empresaId) {
                out(['success' => false, 'message' => 'No autorizado'], 403);
            }
        }

        out(['success' => true, 'data' => getMetricas($db, $empresaId)]);
    }

    out(['success' => false, 'message' => 'Acción no válida'], 400);

} catch (Throwable $e) {
    error_log('[api/metricas] ' . $e->getMessage());
    out(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}

function getMetricas(PDO $db, int $empresaId): array {
    // Vistas totales
    $tot = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE empresa_id = :e");
    $tot->execute([':e' => $empresaId]);
    $vistasTotal = (int)$tot->fetchColumn();

    // Vistas últimos 30 días
    $mes = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE empresa_id = :e AND creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $mes->execute([':e' => $empresaId]);
    $vistas30d = (int)$mes->fetchColumn();

    // Vistas últimos 7 días
    $sem = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE empresa_id = :e AND creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $sem->execute([':e' => $empresaId]);
    $vistas7d = (int)$sem->fetchColumn();

    // Vistas únicas (por IP hash)
    $uniq = $db->prepare("SELECT COUNT(DISTINCT ip_hash) FROM perfil_vistas WHERE empresa_id = :e AND creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $uniq->execute([':e' => $empresaId]);
    $vistasUnicas30d = (int)$uniq->fetchColumn();

    // Serie diaria últimos 30 días
    $serie = $db->prepare(
        "SELECT DATE(creado_en) AS dia, COUNT(*) AS vistas
           FROM perfil_vistas
          WHERE empresa_id = :e AND creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          GROUP BY DATE(creado_en)
          ORDER BY dia ASC"
    );
    $serie->execute([':e' => $empresaId]);
    $serieDiaria = $serie->fetchAll(PDO::FETCH_ASSOC);

    return [
        'vistas_total'      => $vistasTotal,
        'vistas_30d'        => $vistas30d,
        'vistas_7d'         => $vistas7d,
        'vistas_unicas_30d' => $vistasUnicas30d,
        'serie_diaria'      => $serieDiaria,
    ];
}
