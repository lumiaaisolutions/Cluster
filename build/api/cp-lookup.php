<?php
/**
 * Búsqueda de ubicación por código postal mexicano (FEATURE-037).
 *
 * GET ?cp=NNNNN → { success, estado, municipio, fuente }
 *
 * Estrategia (en orden):
 *  1. Caché propia en BD (tabla cp_catalogo) — cada CP consultado con éxito
 *     se guarda, así el sistema construye su propio catálogo con el uso y
 *     deja de depender de servicios externos para CPs repetidos.
 *  2. Nominatim/OpenStreetMap (sin token) — da estado + municipio. Su
 *     política pide máx 1 req/s: el volumen de un formulario de registro +
 *     la caché de BD lo respetan de sobra. (COPOMEX se descartó: su token
 *     "pruebas" devuelve datos revueltos a propósito.)
 *  3. Zippopotam (sin token, estable) — da solo estado; municipio queda
 *     vacío y el formulario lo pide manualmente.
 *
 * Si todo falla responde success:false y el formulario cae a captura manual
 * — el registro nunca se bloquea por este servicio.
 */
define('CLAUT_ACCESS', true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$cp = preg_replace('/\D/', '', $_GET['cp'] ?? '');
if (strlen($cp) !== 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CP inválido (5 dígitos)']);
    exit;
}

function httpGetJson(string $url, int $timeout = 4): ?array {
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true,
        'header' => "User-Agent: ClautIntranet/1.0\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

$estado = '';
$municipio = '';
$fuente = '';

// 1) caché propia
try {
    require_once __DIR__ . '/../config/database.php';
    $conn = Database::getInstance()->getConnection();
    $conn->exec("CREATE TABLE IF NOT EXISTS cp_catalogo (
        cp CHAR(5) PRIMARY KEY,
        estado VARCHAR(60) NOT NULL,
        municipio VARCHAR(100) NOT NULL DEFAULT '',
        actualizado DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $conn->prepare("SELECT estado, municipio FROM cp_catalogo WHERE cp = ?");
    $stmt->execute([$cp]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $estado = $row['estado'];
        $municipio = $row['municipio'];
        $fuente = 'cache';
    }
} catch (Exception $e) {
    $conn = null; // sin BD el servicio sigue funcionando, solo sin caché
}

// 2) Nominatim / OpenStreetMap (estado + municipio)
if ($estado === '') {
    $r = httpGetJson("https://nominatim.openstreetmap.org/search?postalcode={$cp}&countrycodes=mx&format=jsonv2&addressdetails=1&limit=1");
    if (!empty($r[0]['address']['state'])) {
        $addr   = $r[0]['address'];
        $estado = $addr['state'];
        $municipio = $addr['county'] ?? $addr['city'] ?? $addr['town'] ?? $addr['municipality'] ?? '';
        // En CDMX el "county" suele repetir el estado; la alcaldía viene como
        // el componente anterior al estado en display_name
        if ($municipio === $estado && !empty($r[0]['display_name'])) {
            $partes = array_map('trim', explode(',', $r[0]['display_name']));
            $idx = array_search($estado, $partes, true);
            if ($idx !== false && $idx >= 1 && $partes[$idx - 1] !== $cp) {
                $municipio = $partes[$idx - 1];
            } else {
                $municipio = '';
            }
        }
        $fuente = 'nominatim';
    }
}

// 3) Zippopotam (solo estado; respaldo)
if ($estado === '') {
    $r = httpGetJson("https://api.zippopotam.us/mx/{$cp}");
    if ($r && !empty($r['places'][0]['state'])) {
        $estado = $r['places'][0]['state'];
        $fuente = 'zippopotam';
    }
}

// normalización de nombres históricos
$mapaEstados = ['Distrito Federal' => 'Ciudad de México', 'Mexico' => 'Estado de México', 'México' => 'Estado de México'];
if (isset($mapaEstados[$estado])) $estado = $mapaEstados[$estado];

if ($estado === '') {
    echo json_encode(['success' => false, 'message' => 'CP no encontrado — captura tu ubicación manualmente']);
    exit;
}

// guardar en caché (solo si vino de un proveedor externo)
if ($conn && $fuente !== 'cache') {
    try {
        $up = $conn->prepare("INSERT INTO cp_catalogo (cp, estado, municipio) VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE estado = VALUES(estado),
                              municipio = IF(VALUES(municipio) <> '', VALUES(municipio), municipio)");
        $up->execute([$cp, $estado, $municipio]);
    } catch (Exception $e) { /* caché best-effort */ }
}

echo json_encode([
    'success'   => true,
    'estado'    => $estado,
    'municipio' => $municipio,
    'fuente'    => $fuente,
]);
