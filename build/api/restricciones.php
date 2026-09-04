<?php
define('CLAUT_ACCESS', true);
require_once __DIR__ . '/../config/session-config.php';
SessionConfig::init();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

class RestriccionesAPI {
    private $db;
    private $connection;

    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->connection = $this->db->getConnection();
        } catch (Exception $e) {
            $this->sendError('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? $_POST['action'] ?? null;

        // Para requests JSON
        $inputData = json_decode(file_get_contents('php://input'), true);
        if ($inputData && isset($inputData['action'])) {
            $action = $inputData['action'];
        }

        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action, $inputData);
                    break;
                default:
                    $this->sendError('Método no permitido', 405);
            }
        } catch (Exception $e) {
            $this->sendError('Error del servidor: ' . $e->getMessage());
        }
    }

    private function handleGet($action) {
        switch ($action) {
            case 'get':
                $this->getRestricciones();
                break;
            case 'check':
                $this->checkAcceso();
                break;
            default:
                $this->sendError('Acción no válida');
        }
    }

    private function handlePost($action, $data) {
        switch ($action) {
            case 'save':
                $this->saveRestricciones($data);
                break;
            default:
                $this->sendError('Acción no válida');
        }
    }

    private function getRestricciones() {
        $usuarioId = $_GET['usuario_id'] ?? null;

        if (!$usuarioId) {
            $this->sendError('ID de usuario requerido');
            return;
        }

        $sql = "SELECT pagina FROM usuario_restricciones
                WHERE usuario_id = :usuario_id AND restringido = 1";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->execute();

        $restricciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->sendSuccess(['restricciones' => $restricciones]);
    }

    private function checkAcceso() {
        $userEmail = $this->getCurrentUserEmail();
        $pagina = $_GET['pagina'] ?? null;

        if (!$userEmail || !$pagina) {
            $this->sendError('Email de usuario y página requeridos');
            return;
        }

        // Obtener ID del usuario por email
        $sql = "SELECT id FROM usuarios_perfil WHERE email = :email";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':email', $userEmail);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $this->sendError('Usuario no encontrado');
            return;
        }

        // Verificar si tiene restricciones en esta página
        $sql = "SELECT COUNT(*) as count FROM usuario_restricciones
                WHERE usuario_id = :usuario_id AND pagina = :pagina AND restringido = 1";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuario['id']);
        $stmt->bindParam(':pagina', $pagina);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $tieneRestriccion = $result['count'] > 0;

        $this->sendSuccess([
            'usuario_id' => $usuario['id'],
            'pagina' => $pagina,
            'acceso_restringido' => $tieneRestriccion,
            'puede_acceder' => !$tieneRestriccion
        ]);
    }

    private function saveRestricciones($data) {
        $usuarioId = $data['usuario_id'] ?? null;
        $paginas = $data['paginas'] ?? [];

        if (!$usuarioId) {
            $this->sendError('ID de usuario requerido');
            return;
        }

        try {
            $this->connection->beginTransaction();

            // Primero, eliminar todas las restricciones existentes para este usuario
            $sql = "DELETE FROM usuario_restricciones WHERE usuario_id = :usuario_id";
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':usuario_id', $usuarioId);
            $stmt->execute();

            // Luego, insertar las nuevas restricciones
            if (!empty($paginas)) {
                $sql = "INSERT INTO usuario_restricciones (usuario_id, pagina, restringido) VALUES (?, ?, 1)";
                $stmt = $this->connection->prepare($sql);

                foreach ($paginas as $pagina) {
                    $stmt->execute([$usuarioId, $pagina]);
                }
            }

            $this->connection->commit();
            $this->sendSuccess(['message' => 'Restricciones guardadas exitosamente']);

        } catch (Exception $e) {
            $this->connection->rollBack();
            $this->sendError('Error al guardar restricciones: ' . $e->getMessage());
        }
    }

    private function getCurrentUserEmail() {
        // La sesión ya se inició arriba con SessionConfig::init() (mismo
        // patrón/causa que BUG-035 en api/notificaciones.php).
        return $_SESSION['user_email'] ?? null;
    }

    private function sendSuccess($data = null) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Inicializar y manejar la request
$api = new RestriccionesAPI();
$api->handleRequest();
?>