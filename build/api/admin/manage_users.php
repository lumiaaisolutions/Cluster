<?php
/**
 * API para gestión de usuarios - Aprobación, rechazo, lista de espera
 */

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en HTTPS
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

/**
 * Función para responder en JSON
 */
function responderJSON($success, $data = null, $message = '', $extra = []) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ];

    foreach ($extra as $key => $value) {
        $response[$key] = $value;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Verificar que el usuario es administrador
 */
function verificarAdmin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
        responderJSON(false, null, 'Acceso denegado. Se requieren permisos de administrador.');
    }
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $db = Database::getInstance();
    $conn = $db->getConnection();

    switch ($method) {
        case 'GET':
            // Listar usuarios pendientes de aprobación
            $status = $_GET['status'] ?? 'pendiente';
            $query = "SELECT up.id, up.nombre, up.apellidos, up.email, up.telefono,
                            up.nombre_empresa, up.rol, up.estado_usuario, up.fecha_registro,
                            up.biografia, up.direccion, up.ciudad, up.estado as estado_geografico,
                            ec.nombre_empresa as empresa_convenio_nombre
                     FROM usuarios_perfil up
                     LEFT JOIN empresas_convenio ec ON up.empresa_id = ec.id";

            if ($status !== 'todos') {
                $query .= " WHERE up.estado_usuario = :status";
            }

            $query .= " ORDER BY up.fecha_registro DESC";

            $stmt = $conn->prepare($query);
            if ($status !== 'todos') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();

            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            responderJSON(true, $usuarios, "Usuarios encontrados: " . count($usuarios));
            break;

        case 'PUT':
            // Actualizar estado del usuario (aprobar/rechazar/lista de espera)
            verificarAdmin();

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                responderJSON(false, null, 'Datos JSON inválidos');
            }

            // ============================================
            // VALIDACIÓN CON API VALIDATOR
            // ============================================
            require_once dirname(dirname(__DIR__)) . '/middleware/api-validator.php';
            
            $validation = ApiValidator::validateAndSanitize($data, [
                'user_id' => 'required|int|min:1',
                'estado_usuario' => 'required|string|in:activo,rechazado,lista_espera,pendiente',
                'comentario' => 'string|max:500'
            ]);
            
            if (!$validation['valid']) {
                ApiValidator::errorResponse($validation['errors']);
            }
            
            $user_id = $validation['data']['user_id'];
            $nuevo_estado = $validation['data']['estado_usuario'];
            $comentario = $validation['data']['comentario'] ?? '';
            // ============================================

            // Actualizar estado del usuario
            $updateQuery = "UPDATE usuarios_perfil
                           SET estado_usuario = :estado_usuario,
                               ultima_actividad = CURRENT_TIMESTAMP
                           WHERE id = :user_id";

            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':estado_usuario', $nuevo_estado);
            $updateStmt->bindParam(':user_id', $user_id);

            if ($updateStmt->execute()) {
                // Obtener datos del usuario para la respuesta
                $userQuery = "SELECT nombre, apellidos, email, estado_usuario FROM usuarios_perfil WHERE id = :user_id";
                $userStmt = $conn->prepare($userQuery);
                $userStmt->bindParam(':user_id', $user_id);
                $userStmt->execute();
                $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

                // Auditoría: registrar cambio de estado del usuario
                $auditPath = dirname(dirname(__DIR__)) . '/utils/AuditLogger.php';
                if (file_exists($auditPath)) {
                    require_once $auditPath;
                    AuditLogger::log('usuario.' . $nuevo_estado, 'usuario', $user_id, [
                        'email'      => $userData['email'] ?? null,
                        'nuevo_estado' => $nuevo_estado,
                        'comentario' => $comentario,
                    ]);
                }

                // Registrar la acción en un log (opcional)
                $admin_id = $_SESSION['user_id'] ?? 0;
                $logQuery = "INSERT INTO notificaciones (titulo, contenido, tipo, origen_id, fecha_creacion)
                            VALUES ('Cambio de estado de usuario',
                                   'Usuario {$userData['email']} cambió de estado a {$nuevo_estado} por admin {$admin_id}. Comentario: {$comentario}',
                                   'admin_action', :user_id, CURRENT_TIMESTAMP)";
                try {
                    $logStmt = $conn->prepare($logQuery);
                    $logStmt->bindParam(':user_id', $user_id);
                    $logStmt->execute();
                } catch (Exception $e) {
                    // Log opcional, no detener el proceso si falla
                    error_log("Error al registrar log de acción: " . $e->getMessage());
                }

                $message = '';
                switch ($nuevo_estado) {
                    case 'activo':
                        $message = "Usuario {$userData['email']} aprobado exitosamente. Ahora puede acceder al sistema.";
                        break;
                    case 'rechazado':
                        $message = "Usuario {$userData['email']} rechazado.";
                        break;
                    case 'lista_espera':
                        $message = "Usuario {$userData['email']} movido a lista de espera.";
                        break;
                    case 'pendiente':
                        $message = "Usuario {$userData['email']} vuelto a estado pendiente.";
                        break;
                }

                responderJSON(true, $userData, $message);
            } else {
                responderJSON(false, null, 'Error al actualizar el estado del usuario');
            }
            break;

        case 'DELETE':
            // Eliminar usuario (solo para casos extremos)
            verificarAdmin();

            $user_id = $_GET['id'] ?? null;
            if (!$user_id) {
                responderJSON(false, null, 'ID de usuario requerido');
            }

            $deleteQuery = "DELETE FROM usuarios_perfil WHERE id = :user_id";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':user_id', $user_id);

            if ($deleteStmt->execute()) {
                responderJSON(true, null, 'Usuario eliminado exitosamente');
            } else {
                responderJSON(false, null, 'Error al eliminar usuario');
            }
            break;

        default:
            responderJSON(false, null, 'Método no permitido');
            break;
    }

} catch (Exception $e) {
    error_log("Error en gestión de usuarios: " . $e->getMessage());
    responderJSON(false, null, 'Error interno del servidor: ' . $e->getMessage());
}
?>