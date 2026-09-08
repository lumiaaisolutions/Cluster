<?php
/**
 * API de empresas
 */

// Definir acceso permitido
define('CLAUT_ACCESS', true);

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

/**
 * Adaptador: este archivo fue escrito contra un config antiguo (includes/config.php,
 * inexistente en producción — mismo patrón de FIX-023) cuyo Database exponía
 * selectOne/select/insert/update. El Database real solo expone getConnection(),
 * así que se puentean aquí esos 4 helpers sobre PDO preparado.
 */
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $code = 200, $message = null) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $code < 400,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}
if (!function_exists('jsonError')) {
    function jsonError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

class EmpresasDbAdapter {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function select($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function selectOne($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
    public function insert($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }
    public function update($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

try {
    $db = new EmpresasDbAdapter(Database::getInstance()->getConnection());
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            jsonError('Método no permitido', 405);
    }
    
} catch (Exception $e) {
    error_log("Error en empresas API: " . $e->getMessage());
    jsonError('Error interno del servidor', 500);
}

function handleGet($db) {
    $id = $_GET['id'] ?? null;
    $limit = $_GET['limit'] ?? null;
    $action = $_GET['action'] ?? null;
    
    if ($id) {
        // Obtener empresa específica
        $empresa = $db->selectOne(
            "SELECT * FROM empresas_convenio WHERE id = ?",
            [$id]
        );
        
        if (!$empresa) {
            jsonError('Empresa no encontrada', 404);
        }
        
        // Obtener miembros de la empresa
        $miembros = $db->select(
            "SELECT u.id, u.nombre, u.apellido, u.email, u.rol, u.fecha_registro 
             FROM usuarios_perfil u 
             WHERE u.empresa_id = ? AND u.estado = 'activo'
             ORDER BY u.nombre",
            [$id]
        );
        
        $empresa['miembros'] = $miembros;
        $empresa['total_miembros'] = count($miembros);
        
        jsonResponse($empresa);
        
    } elseif ($action === 'con_miembros') {
        // Obtener empresas con información de miembros para el dashboard
        $sql = "SELECT e.*, 
                       COUNT(u.id) as total_miembros
                FROM empresas_convenio e
                LEFT JOIN usuarios_perfil u ON e.id = u.empresa_id AND u.estado = 'activo'
                WHERE e.activo = 1
                GROUP BY e.id
                ORDER BY e.nombre";
        
        $empresas = $db->select($sql);
        jsonResponse($empresas);
        
    } else {
        // Obtener todas las empresas
        $sql = "SELECT e.id, e.nombre_empresa, e.nombre, e.descripcion, e.logo_url, e.sitio_web, e.telefono, e.email, e.direccion, e.categoria, e.descuento, e.beneficios, e.fecha_inicio_convenio, e.fecha_fin_convenio, e.activo, e.destacado, e.created_at, e.updated_at, e.fecha_registro, e.sector, e.estado, e.fecha_convenio, e.contacto_nombre, e.contacto_cargo, e.contacto_telefono, e.contacto_email, e.descuento_porcentaje, e.condiciones, e.admin_usuario_id, CONCAT(u.nombre, ' ', u.apellidos) AS admin_nombre FROM empresas_convenio e LEFT JOIN usuarios_perfil u ON e.admin_usuario_id = u.id WHERE e.activo = 1 ORDER BY COALESCE(e.nombre, e.nombre_empresa)";
        
        if ($limit && is_numeric($limit)) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $empresas = $db->select($sql);
        jsonResponse($empresas);
    }
}

function handlePost($db) {
    // Para crear empresas, verificar autenticación
    $headers = getallheaders();
    $isAuthenticated = false;
    
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = verifyToken($token);
            if ($payload) {
                $isAuthenticated = true;
            }
        }
    }
    
    if (!$isAuthenticated) {
        jsonError('Token de autenticación requerido', 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonError('Datos inválidos', 400);
    }
    
    // ============================================
    // VALIDACIÓN CON API VALIDATOR
    // ============================================
    require_once dirname(__DIR__) . '/middleware/api-validator.php';
    
    $validation = ApiValidator::validateAndSanitize($input, [
        'nombre' => 'required|string|min:2|max:255',
        'descripcion' => 'string|max:1000',
        'website' => 'string|max:255',
        'telefono' => 'string|min:10|max:15',
        'email' => 'email|max:255',
        'direccion' => 'string|max:255',
        'logo' => 'string|max:500',
        'contacto_telefono' => 'string|min:10|max:15',
        'contacto_email' => 'email|max:255',
        'admin_usuario_id' => 'int|min:1'
    ]);
    
    if (!$validation['valid']) {
        ApiValidator::errorResponse($validation['errors']);
    }
    
    $datos = [
        'nombre' => $validation['data']['nombre'],
        'descripcion' => $validation['data']['descripcion'] ?? '',
        'sitio_web' => $validation['data']['website'] ?? '',
        'telefono' => $validation['data']['telefono'] ?? '',
        'email' => $validation['data']['email'] ?? '',
        'direccion' => $validation['data']['direccion'] ?? '',
        'logo_url' => $validation['data']['logo'] ?? '',
        'contacto_telefono' => $validation['data']['contacto_telefono'] ?? '',
        'contacto_email' => $validation['data']['contacto_email'] ?? '',
        'admin_usuario_id' => $validation['data']['admin_usuario_id'] ?? null
    ];
    // ============================================
    
    // Insertar empresa
    $sql = "INSERT INTO empresas_convenio (nombre, descripcion, sitio_web, telefono, email, direccion, logo_url, contacto_telefono, contacto_email, admin_usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $empresaId = $db->insert($sql, array_values($datos));
    
    // Obtener la empresa creada
    $empresa = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$empresaId]);
    
    jsonResponse($empresa, 201, 'Empresa creada correctamente');
}

function handlePut($db) {
    // Verificar autenticación
    $headers = getallheaders();
    $isAuthenticated = false;
    
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\\s(\\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = verifyToken($token);
            if ($payload) {
                $isAuthenticated = true;
            }
        }
    }
    
    if (!$isAuthenticated) {
        jsonError('Token de autenticación requerido', 401);
    }
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonError('ID de empresa requerido', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonError('Datos inválidos', 400);
    }
    
    // Verificar que la empresa existe
    $empresa = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$id]);
    
    if (!$empresa) {
        jsonError('Empresa no encontrada', 404);
    }
    
    // ============================================
    // VALIDACIÓN CON API VALIDATOR
    // ============================================
    require_once __DIR__ . '/../middleware/api-validator.php';
    
    $validation = ApiValidator::validateAndSanitize($input, [
        'nombre' => 'string|min:2|max:255',
        'descripcion' => 'string|max:1000',
        'sitio_web' => 'string|max:500',
        'telefono' => 'string|min:10|max:15',
        'email' => 'email|max:255',
        'direccion' => 'string|max:500',
        'logo_url' => 'string|max:500',
        'activo' => 'int|in:0,1',
        'contacto_telefono' => 'string|min:10|max:15',
        'contacto_email' => 'email|max:255',
        'admin_usuario_id' => 'int|min:1'
    ]);
    
    if (!$validation['valid']) {
        ApiValidator::errorResponse($validation['errors']);
    }
    
    $cleanData = $validation['data'];
    // ============================================
    
    // Preparar datos para actualizar
    $campos = [];
    $valores = [];
    
    $camposPermitidos = ['nombre', 'descripcion', 'sitio_web', 'telefono', 'email', 'direccion', 'logo_url', 'activo', 'contacto_telefono', 'contacto_email', 'admin_usuario_id'];
    
    foreach ($camposPermitidos as $campo) {
        if (isset($cleanData[$campo])) {
            $campos[] = "$campo = ?";
            $valores[] = $cleanData[$campo];
        }
    }
    
    if (empty($campos)) {
        jsonError('No hay datos para actualizar', 400);
    }
    
    $valores[] = $id;
    $sql = "UPDATE empresas_convenio SET " . implode(', ', $campos) . " WHERE id = ?";
    
    $db->update($sql, $valores);
    
    // Obtener la empresa actualizada
    $empresaActualizada = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$id]);
    
    jsonResponse($empresaActualizada, 200, 'Empresa actualizada correctamente');
}

function handleDelete($db) {
    // Verificar autenticación
    $headers = getallheaders();
    $isAuthenticated = false;
    
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = verifyToken($token);
            if ($payload) {
                $isAuthenticated = true;
            }
        }
    }
    
    if (!$isAuthenticated) {
        jsonError('Token de autenticación requerido', 401);
    }
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonError('ID de empresa requerido', 400);
    }
    
    // Verificar que la empresa existe
    $empresa = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$id]);
    
    if (!$empresa) {
        jsonError('Empresa no encontrada', 404);
    }
    
    // Marcar como inactiva en lugar de eliminar físicamente
    $db->update("UPDATE empresas_convenio SET activo = 0 WHERE id = ?", [$id]);
    
    jsonResponse([], 200, 'Empresa eliminada correctamente');
}
?>