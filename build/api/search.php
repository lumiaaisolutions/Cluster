<?php
/**
 * API de Búsqueda Global — cruza empresas, eventos, documentos, descuentos, boletines.
 *
 * GET /api/search.php?q=texto&limit=30
 *
 * Respuesta:
 *   { success: true, data: { results: [ { type, id, title, subtitle, url }, ... ], total } }
 *
 * Requiere sesión activa (cualquier usuario autenticado puede buscar).
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

    // Requiere sesión
    if (empty($_SESSION['user_email']) && empty($_SESSION['usuario_id'])) {
        out(['success' => false, 'message' => 'No autenticado'], 401);
    }

    $q = trim($_GET['q'] ?? '');
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 30)));

    if (mb_strlen($q) < 2) {
        out(['success' => true, 'data' => ['results' => [], 'total' => 0]]);
    }

    $db = Database::getInstance()->getConnection();
    $like = '%' . $q . '%';
    $results = [];

    // Helper: ejecutar query defensiva (si la tabla/columna no existe, ignora)
    $safeQuery = function (string $sql, array $params) use ($db) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[search] query skipped: ' . $e->getMessage());
            return [];
        }
    };

    // ── EMPRESAS (canónica: empresas_convenio) ───────────────────────────────
    foreach ($safeQuery(
        "SELECT id, COALESCE(NULLIF(nombre,''), nombre_empresa) AS nombre, sector
           FROM empresas_convenio
          WHERE (nombre LIKE :q OR nombre_empresa LIKE :q OR descripcion LIKE :q OR sector LIKE :q)
            AND activo = 1
          LIMIT $limit",
        [':q' => $like]
    ) as $row) {
        $results[] = [
            'type'     => 'empresa',
            'id'       => $row['id'],
            'title'    => $row['nombre'] ?: 'Empresa #' . $row['id'],
            'subtitle' => $row['sector'] ? 'Sector: ' . $row['sector'] : 'Empresa del directorio',
            'url'      => './demo_empresas.html?empresa=' . urlencode($row['id']),
            'icon'     => 'fa-building',
        ];
    }

    // ── EVENTOS ───────────────────────────────────────────────────────────────
    foreach ($safeQuery(
        "SELECT id, titulo, fecha_evento, lugar
           FROM eventos
          WHERE (titulo LIKE :q OR descripcion LIKE :q OR lugar LIKE :q)
          ORDER BY fecha_evento DESC
          LIMIT $limit",
        [':q' => $like]
    ) as $row) {
        $results[] = [
            'type'     => 'evento',
            'id'       => $row['id'],
            'title'    => $row['titulo'],
            'subtitle' => trim(($row['fecha_evento'] ?? '') . ' · ' . ($row['lugar'] ?? ''), ' ·'),
            'url'      => './demo_evento.html?evento=' . urlencode($row['id']),
            'icon'     => 'fa-calendar',
        ];
    }

    // ── DOCUMENTOS ────────────────────────────────────────────────────────────
    foreach ($safeQuery(
        "SELECT id, titulo, descripcion
           FROM documentos
          WHERE (titulo LIKE :q OR descripcion LIKE :q)
          ORDER BY id DESC
          LIMIT $limit",
        [':q' => $like]
    ) as $row) {
        $results[] = [
            'type'     => 'documento',
            'id'       => $row['id'],
            'title'    => $row['titulo'],
            'subtitle' => mb_substr($row['descripcion'] ?? '', 0, 80),
            'url'      => './demo_documentos.html?doc=' . urlencode($row['id']),
            'icon'     => 'fa-file-lines',
        ];
    }

    // ── DESCUENTOS ────────────────────────────────────────────────────────────
    foreach ($safeQuery(
        "SELECT id, titulo, descripcion
           FROM descuentos
          WHERE (titulo LIKE :q OR descripcion LIKE :q)
          ORDER BY id DESC
          LIMIT $limit",
        [':q' => $like]
    ) as $row) {
        $results[] = [
            'type'     => 'descuento',
            'id'       => $row['id'],
            'title'    => $row['titulo'],
            'subtitle' => mb_substr($row['descripcion'] ?? '', 0, 80),
            'url'      => './demo_descuentos.html?d=' . urlencode($row['id']),
            'icon'     => 'fa-tag',
        ];
    }

    // ── BOLETINES ─────────────────────────────────────────────────────────────
    foreach ($safeQuery(
        "SELECT id, titulo, descripcion
           FROM boletines
          WHERE (titulo LIKE :q OR descripcion LIKE :q OR contenido LIKE :q)
          ORDER BY id DESC
          LIMIT $limit",
        [':q' => $like]
    ) as $row) {
        $results[] = [
            'type'     => 'boletin',
            'id'       => $row['id'],
            'title'    => $row['titulo'],
            'subtitle' => mb_substr($row['descripcion'] ?? '', 0, 80),
            'url'      => './demo_boletines.html?b=' . urlencode($row['id']),
            'icon'     => 'fa-newspaper',
        ];
    }

    out([
        'success' => true,
        'data'    => ['results' => $results, 'total' => count($results)],
    ]);

} catch (Throwable $e) {
    error_log('[api/search] ' . $e->getMessage());
    out(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
