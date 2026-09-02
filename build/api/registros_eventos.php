<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

class RegistrosEventosAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($method) {
                case 'GET':
                    $this->getRegistros();
                    break;
                    
                case 'POST':
                    $this->createRegistro();
                    break;
                    
                case 'PUT':
                    $this->updateRegistro();
                    break;
                    
                case 'DELETE':
                    $this->deleteRegistro();
                    break;
                    
                default:
                    throw new Exception('Método no permitido');
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, $e->getMessage());
        }
    }

    private function getRegistros() {
        $evento_id = isset($_GET['evento_id']) ? $_GET['evento_id'] : null;
        $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
        
        $sql = "SELECT r.*, e.titulo as evento_titulo, e.fecha_inicio as evento_fecha 
                FROM registros_eventos r 
                JOIN eventos e ON r.evento_id = e.id 
                WHERE 1=1";
        $params = [];

        if ($evento_id) {
            $sql .= " AND r.evento_id = ?";
            $params[] = $evento_id;
        }

        if ($user_id) {
            $sql .= " AND r.user_id = ?";
            $params[] = $user_id;
        }

        $sql .= " ORDER BY r.fecha_registro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->sendResponse(true, $registros);
    }

    private function createRegistro() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Si es una petición POST con FormData
        if (empty($input)) {
            $input = $_POST;
        }

        // Validar datos requeridos
        $required = ['evento_id', 'nombre', 'email'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("El campo '$field' es requerido");
            }
        }

        // Verificar que el evento existe y está disponible
        $stmt = $this->db->prepare("
            SELECT id, capacidad_maxima, estado,
                   (SELECT COUNT(*) FROM registros_eventos WHERE evento_id = ?) as registrados_actuales
            FROM eventos WHERE id = ?
        ");
        $stmt->execute([$input['evento_id'], $input['evento_id']]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$evento) {
            throw new Exception('El evento no existe');
        }

        if ($evento['estado'] === 'cancelado') {
            throw new Exception('No es posible registrarse a un evento cancelado');
        }

        if ($evento['estado'] === 'finalizado') {
            throw new Exception('No es posible registrarse a un evento finalizado');
        }

        if ($evento['registrados_actuales'] >= $evento['capacidad_maxima']) {
            throw new Exception('El evento ha alcanzado su capacidad máxima');
        }

        // Verificar que el usuario no esté ya registrado
        $stmt = $this->db->prepare("SELECT id FROM registros_eventos WHERE evento_id = ? AND email = ?");
        $stmt->execute([$input['evento_id'], $input['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Ya estás registrado a este evento');
        }

        // Generar código QR único
        $codigo_qr = uniqid('QR_') . '_' . $input['evento_id'];

        // Crear el registro
        $stmt = $this->db->prepare("
            INSERT INTO registros_eventos (evento_id, user_id, nombre, apellido, email, telefono, 
                                         empresa, cargo, notas_especiales, codigo_qr, estado_registro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
        ");

        $result = $stmt->execute([
            $input['evento_id'],
            $input['user_id'] ?? null,
            $input['nombre'],
            $input['apellido'] ?? '',
            $input['email'],
            $input['telefono'] ?? '',
            $input['empresa'] ?? '',
            $input['cargo'] ?? '',
            $input['notas_especiales'] ?? '',
            $codigo_qr
        ]);

        if ($result) {
            $registroId = $this->db->lastInsertId();
            
            // Obtener información completa del registro creado
            $stmt = $this->db->prepare("
                SELECT r.*, e.titulo as evento_titulo, e.fecha_inicio as evento_fecha
                FROM registros_eventos r
                JOIN eventos e ON r.evento_id = e.id
                WHERE r.id = ?
            ");
            $stmt->execute([$registroId]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->sendResponse(true, $registro, 'Registro exitoso al evento');
        } else {
            throw new Exception('Error al crear el registro');
        }
    }

    private function updateRegistro() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            throw new Exception('ID del registro es requerido');
        }

        // Verificar que el registro existe
        $stmt = $this->db->prepare("SELECT id FROM registros_eventos WHERE id = ?");
        $stmt->execute([$input['id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Registro no encontrado');
        }

        $updateFields = [];
        $params = [];

        $allowedFields = ['nombre', 'apellido', 'telefono', 'empresa', 'cargo', 'notas_especiales', 'estado_registro'];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
            }
        }

        if (empty($updateFields)) {
            throw new Exception('No hay campos para actualizar');
        }

        $sql = "UPDATE registros_eventos SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $params[] = $input['id'];

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            $this->sendResponse(true, null, 'Registro actualizado exitosamente');
        } else {
            throw new Exception('Error al actualizar el registro');
        }
    }

    private function deleteRegistro() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            throw new Exception('ID del registro es requerido');
        }

        // Verificar que el registro existe
        $stmt = $this->db->prepare("SELECT id FROM registros_eventos WHERE id = ?");
        $stmt->execute([$input['id']]);
        if (!$stmt->fetch()) {
            throw new Exception('Registro no encontrado');
        }

        $stmt = $this->db->prepare("DELETE FROM registros_eventos WHERE id = ?");
        $result = $stmt->execute([$input['id']]);

        if ($result) {
            $this->sendResponse(true, null, 'Registro eliminado exitosamente');
        } else {
            throw new Exception('Error al eliminar el registro');
        }
    }

    private function sendResponse($success, $data = null, $message = '') {
        http_response_code($success ? 200 : 400);
        echo json_encode([
            'success' => $success,
            'data' => $data,
            'message' => $message
        ]);
        exit;
    }
}

// Instanciar y manejar la petición
$api = new RegistrosEventosAPI();
$api->handleRequest();
?>