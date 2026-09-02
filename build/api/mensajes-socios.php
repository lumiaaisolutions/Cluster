<?php
/**
 * API de Mensajería Socio↔Socio.
 *
 * GET  ?action=inbox                    → bandeja de entrada
 * GET  ?action=sent                     → enviados
 * GET  ?action=conversation&with=USER_ID → hilo con un usuario
 * GET  ?action=unread_count             → cuántos no leídos
 * GET  ?action=contacts                 → lista de socios disponibles (opt-in)
 * POST { action: 'send', destinatario_id, asunto, contenido }
 * POST { action: 'mark_read', id }
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

    $userId = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
    if (!$userId) out(['success' => false, 'message' => 'No autenticado'], 401);

    $db = Database::getInstance()->getConnection();
    $action = $_GET['action'] ?? null;
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!$action && isset($body['action'])) $action = $body['action'];

    // === INBOX ===
    if ($action === 'inbox') {
        $stmt = $db->prepare(
            "SELECT m.id, m.remitente_id, m.asunto, m.contenido, m.leido, m.creado_en,
                    CONCAT(u.nombre, ' ', u.apellidos) AS remitente_nombre,
                    u.email AS remitente_email,
                    u.nombre_empresa AS remitente_empresa
               FROM mensajes_socios m
               JOIN usuarios_perfil u ON u.id = m.remitente_id
              WHERE m.destinatario_id = :uid
                AND m.archivado_por_destinatario = 0
              ORDER BY m.creado_en DESC
              LIMIT 100"
        );
        $stmt->execute([':uid' => $userId]);
        out(['success' => true, 'data' => ['mensajes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    // === SENT ===
    if ($action === 'sent') {
        $stmt = $db->prepare(
            "SELECT m.id, m.destinatario_id, m.asunto, m.contenido, m.leido, m.creado_en,
                    CONCAT(u.nombre, ' ', u.apellidos) AS destinatario_nombre,
                    u.email AS destinatario_email
               FROM mensajes_socios m
               JOIN usuarios_perfil u ON u.id = m.destinatario_id
              WHERE m.remitente_id = :uid
                AND m.archivado_por_remitente = 0
              ORDER BY m.creado_en DESC
              LIMIT 100"
        );
        $stmt->execute([':uid' => $userId]);
        out(['success' => true, 'data' => ['mensajes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    // === CONVERSATION ===
    if ($action === 'conversation') {
        $otherId = (int)($_GET['with'] ?? 0);
        if (!$otherId) out(['success' => false, 'message' => 'with requerido'], 400);

        $stmt = $db->prepare(
            "SELECT id, remitente_id, destinatario_id, asunto, contenido, leido, creado_en
               FROM mensajes_socios
              WHERE (remitente_id = :uid AND destinatario_id = :oid)
                 OR (remitente_id = :oid AND destinatario_id = :uid)
              ORDER BY creado_en ASC
              LIMIT 200"
        );
        $stmt->execute([':uid' => $userId, ':oid' => $otherId]);

        // Marcar como leídos los que llegaron a mí
        $db->prepare(
            "UPDATE mensajes_socios SET leido = 1, leido_en = NOW()
              WHERE destinatario_id = :uid AND remitente_id = :oid AND leido = 0"
        )->execute([':uid' => $userId, ':oid' => $otherId]);

        out(['success' => true, 'data' => ['mensajes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    // === UNREAD COUNT ===
    if ($action === 'unread_count') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM mensajes_socios WHERE destinatario_id = :uid AND leido = 0 AND archivado_por_destinatario = 0");
        $stmt->execute([':uid' => $userId]);
        out(['success' => true, 'data' => ['count' => (int)$stmt->fetchColumn()]]);
    }

    // === CONTACTS (socios disponibles para escribirles) ===
    if ($action === 'contacts') {
        $stmt = $db->prepare(
            "SELECT id,
                    CONCAT(nombre, ' ', apellidos) AS nombre_completo,
                    email,
                    nombre_empresa
               FROM usuarios_perfil
              WHERE estado_usuario = 'activo'
                AND id != :uid
                AND activo = 1
              ORDER BY nombre ASC
              LIMIT 500"
        );
        $stmt->execute([':uid' => $userId]);
        out(['success' => true, 'data' => ['contactos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    // === SEND ===
    if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $destId = (int)($body['destinatario_id'] ?? 0);
        $asunto = trim((string)($body['asunto'] ?? ''));
        $contenido = trim((string)($body['contenido'] ?? ''));

        if (!$destId)       out(['success' => false, 'message' => 'destinatario_id requerido'], 400);
        if (!$contenido)    out(['success' => false, 'message' => 'contenido requerido'], 400);
        if (strlen($contenido) > 5000) out(['success' => false, 'message' => 'contenido demasiado largo'], 400);
        if ($destId === (int)$userId) out(['success' => false, 'message' => 'No puedes enviarte mensajes a ti mismo'], 400);

        // Validar que el destinatario existe y está activo
        $stmt = $db->prepare("SELECT id FROM usuarios_perfil WHERE id = :id AND estado_usuario = 'activo' AND activo = 1");
        $stmt->execute([':id' => $destId]);
        if (!$stmt->fetchColumn()) out(['success' => false, 'message' => 'Destinatario no válido'], 400);

        $db->prepare(
            "INSERT INTO mensajes_socios (remitente_id, destinatario_id, asunto, contenido)
             VALUES (:r, :d, :a, :c)"
        )->execute([
            ':r' => $userId,
            ':d' => $destId,
            ':a' => $asunto ?: null,
            ':c' => $contenido,
        ]);

        $newId = $db->lastInsertId();

        // Notificación in-app al destinatario
        try {
            $db->prepare(
                "INSERT INTO notificaciones (titulo, contenido, tipo, dirigido_a, dirigido_email, fecha_creacion, activo)
                 SELECT
                    CONCAT('Mensaje de ', nombre, ' ', apellidos),
                    :contenido,
                    'mensaje_socio',
                    'destinatario',
                    (SELECT email FROM usuarios_perfil WHERE id = :destId),
                    NOW(),
                    1
                   FROM usuarios_perfil
                  WHERE id = :remitenteId"
            )->execute([
                ':contenido'   => mb_substr($asunto ?: $contenido, 0, 200),
                ':destId'      => $destId,
                ':remitenteId' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('[mensajes-socios] notif: ' . $e->getMessage());
        }

        out(['success' => true, 'data' => ['id' => $newId]]);
    }

    // === MARK READ ===
    if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success' => false, 'message' => 'id requerido'], 400);

        $db->prepare(
            "UPDATE mensajes_socios SET leido = 1, leido_en = NOW()
              WHERE id = :id AND destinatario_id = :uid AND leido = 0"
        )->execute([':id' => $id, ':uid' => $userId]);

        out(['success' => true]);
    }

    out(['success' => false, 'message' => 'Acción no válida'], 400);

} catch (Throwable $e) {
    error_log('[api/mensajes-socios] ' . $e->getMessage());
    out(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
