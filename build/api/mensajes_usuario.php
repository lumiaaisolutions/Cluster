<?php
/**
 * API: User Messages Management
 * File: api/mensajes_usuario.php
 * Purpose: Handle user messages and notifications
 */

// Disable error display to prevent breaking JSON responses
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../utils/api-response.php';
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Start session — antes session_start() a secas usaba el nombre de
// cookie por defecto de PHP en vez de "CLAUT_SESSION" (mismo bug que
// BUG-035). Nota: el propio "<?php" de este archivo venía escapado como
// texto literal "&lt;?php" — PHP nunca ejecutaba nada de este archivo,
// solo emitía el código fuente como texto plano. Cero referencias a este
// archivo en el resto del sitio (verificado) — sin impacto en producción
// hoy, se deja corregido por consistencia.
define('CLAUT_ACCESS', true);
require_once __DIR__ . '/../config/session-config.php';
SessionConfig::init();

// Check authentication
if (!isset($_SESSION['user_id'])) {
        ApiResponse::error('No autenticado', 401);
}

$user_id = intval($_SESSION['user_id']);
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            // Get user messages with pagination
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $leido = isset($_GET['leido']) ? intval($_GET['leido']) : null;
            $offset = ($page - 1) * $limit;
            
            // Build query
            $where = "WHERE usuario_id = ?";
            $params = [$user_id];
            
            if ($leido !== null) {
                $where .= " AND leido = ?";
                $params[] = $leido;
            }
            
            // Get total count
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM mensajes_usuario $where");
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get messages
            $stmt = $conn->prepare("
                SELECT * FROM mensajes_usuario 
                $where 
                ORDER BY fecha_creacion DESC 
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiResponse::success([
                'mensajes' => $mensajes,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => intval($total),
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'POST':
            // Create message (system use - could be restricted)
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['titulo']) || !isset($data['contenido'])) {
                ApiResponse::error('Datos inválidos', 400);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO mensajes_usuario 
                (usuario_id, tipo, titulo, contenido, relacionado_tipo, relacionado_id, icono, color) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $user_id,
                $data['tipo'] ?? 'mensaje',
                $data['titulo'],
                $data['contenido'],
                $data['relacionado_tipo'] ?? null,
                $data['relacionado_id'] ?? null,
                $data['icono'] ?? 'fa-envelope',
                $data['color'] ?? 'info'
            ]);
            
            if ($result) {
                ApiResponse::success(['mensaje_id' => $conn->lastInsertId()], 'Mensaje creado correctamente');
            } else {
                ApiResponse::error('Error al crear el mensaje', 500);
            }
            break;
            
        case 'PUT':
            // Mark message as read/unread
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['id'])) {
                ApiResponse::error('ID de mensaje requerido', 400);
            }
            
            $mensaje_id = intval($data['id']);
            $leido = isset($data['leido']) ? intval($data['leido']) : 1;
            
            // Verify ownership
            $stmt = $conn->prepare("SELECT id FROM mensajes_usuario WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$mensaje_id, $user_id]);
            
            if (!$stmt->fetch()) {
                ApiResponse::error('Mensaje no encontrado', 404);
            }
            
            $stmt = $conn->prepare("UPDATE mensajes_usuario SET leido = ? WHERE id = ?");
            $result = $stmt->execute([$leido, $mensaje_id]);
            
            if ($result) {
                ApiResponse::success(null, 'Mensaje actualizado correctamente');
            } else {
                ApiResponse::error('Error al actualizar el mensaje', 500);
            }
            break;
            
        case 'DELETE':
            // Delete message
            $mensaje_id = intval($_GET['id'] ?? 0);
            
            if (!$mensaje_id) {
                ApiResponse::error('ID de mensaje requerido', 400);
            }
            
            // Verify ownership
            $stmt = $conn->prepare("SELECT id FROM mensajes_usuario WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$mensaje_id, $user_id]);
            
            if (!$stmt->fetch()) {
                ApiResponse::error('Mensaje no encontrado', 404);
            }
            
            $stmt = $conn->prepare("DELETE FROM mensajes_usuario WHERE id = ?");
            $result = $stmt->execute([$mensaje_id]);
            
            if ($result) {
                ApiResponse::success(null, 'Mensaje eliminado correctamente');
            } else {
                ApiResponse::error('Error al eliminar el mensaje', 500);
            }
            break;
            
        default:
            ApiResponse::error('Método no permitido', 405);
    }
    
} catch (Exception $e) {
    ApiResponse::error('Error del servidor', 500, ['error_info' => $e->getMessage()]);
}
