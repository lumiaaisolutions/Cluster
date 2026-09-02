<?php
/**
 * API Pública de Empresas — ULTRA-defensiva.
 * Diseño:
 *   - Captura errores fatales con register_shutdown_function.
 *   - NO depende de la clase Database (conecta directo con PDO).
 *   - SIEMPRE devuelve JSON con detalle del error.
 *   - Solo expone { id, nombre } — sin datos privados.
 */

// === CAPTURAR TODO TIPO DE ERROR Y CONVERTIR A JSON ===
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'FATAL: ' . $err['message'],
            'file'    => basename($err['file']),
            'line'    => $err['line'],
            'data'    => ['empresas' => [], 'total' => 0],
        ]);
    }
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function ok(array $data): void {
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $code = 500, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => false,
        'message' => $msg,
        'data'    => ['empresas' => [], 'total' => 0],
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // === LEER .env DIRECTAMENTE (sin EnvLoader) ===
    $envPath = dirname(__DIR__) . '/.env';
    if (!file_exists($envPath)) {
        fail('.env no encontrado en ' . dirname(__DIR__));
    }
    $envVars = [];
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $envVars[trim($k)] = trim($v, " \t\"'");
    }

    $dbHost = $envVars['DB_HOST'] ?? 'localhost';
    $dbName = $envVars['DB_NAME'] ?? $envVars['DB_DATABASE'] ?? '';
    $dbUser = $envVars['DB_USER'] ?? $envVars['DB_USERNAME'] ?? '';
    $dbPass = $envVars['DB_PASS'] ?? $envVars['DB_PASSWORD'] ?? '';
    $dbPort = $envVars['DB_PORT'] ?? '3306';

    if (empty($dbName) || empty($dbUser)) {
        fail('Credenciales BD ausentes en .env', 500, [
            'env_keys' => array_keys($envVars),
        ]);
    }

    // === CONECTAR PDO ===
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // === DESCUBRIR COLUMNAS DE 'empresas_convenio' (tabla canónica del directorio) ===
    $cols = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM empresas_convenio");
    foreach ($colStmt->fetchAll() as $row) {
        $cols[] = $row['Field'];
    }

    if (empty($cols) || !in_array('id', $cols, true)) {
        fail('Tabla empresas_convenio sin columna id', 500, ['columns' => $cols]);
    }

    // === ARMAR QUERY DINÁMICA según columnas presentes ===
    $nameParts = [];
    if (in_array('nombre', $cols, true))         $nameParts[] = "NULLIF(nombre, '')";
    if (in_array('nombre_empresa', $cols, true)) $nameParts[] = "NULLIF(nombre_empresa, '')";
    $nameExpr = !empty($nameParts)
        ? 'COALESCE(' . implode(', ', $nameParts) . ", CONCAT('Empresa #', id))"
        : "CONCAT('Empresa #', id)";

    $whereClause = in_array('activo', $cols, true)
        ? 'WHERE (activo = 1 OR activo IS NULL)'
        : '';

    $sql = "SELECT id, $nameExpr AS nombre
            FROM empresas_convenio
            $whereClause
            ORDER BY nombre ASC
            LIMIT 500";

    $stmt = $pdo->query($sql);
    $empresas = $stmt->fetchAll();

    ok(['empresas' => $empresas, 'total' => count($empresas)]);

} catch (Throwable $e) {
    fail($e->getMessage(), 500, [
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ]);
}
