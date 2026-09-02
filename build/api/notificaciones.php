<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

class NotificacionesAPI {
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
            case 'list':
                $this->listNotifications();
                break;
            case 'unread_count':
                $this->getUnreadCount();
                break;
            default:
                $this->sendError('Acción no válida');
        }
    }

    private function handlePost($action, $data) {
        switch ($action) {
            case 'create':
                $this->createNotification($data);
                break;
            case 'mark_read':
                $this->markAsRead($data['id'] ?? null);
                break;
            case 'mark_all_read':
                $this->markAllAsRead();
                break;
            case 'auto_generate':
                $this->autoGenerateNotifications($data);
                break;
            default:
                $this->sendError('Acción no válida');
        }
    }

    private function listNotifications() {
        $userEmail = $this->getCurrentUserEmail();
        $userRole = $this->getCurrentUserRole();

        if (!$userEmail) {
            $this->sendError('Usuario no autenticado', 401);
            return;
        }

        $sql = "SELECT * FROM notificaciones
                WHERE activo = 1
                AND (dirigido_a = 'todos'
                     OR destinatario_email = :email
                     OR destinatario_rol = :role)
                ORDER BY fecha_creacion DESC
                LIMIT 50";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':email', $userEmail);
        $stmt->bindParam(':role', $userRole);
        $stmt->execute();

        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->sendSuccess($notifications);
    }

    private function getUnreadCount() {
        $userEmail = $this->getCurrentUserEmail();
        $userRole = $this->getCurrentUserRole();

        if (!$userEmail) {
            $this->sendError('Usuario no autenticado', 401);
            return;
        }

        $sql = "SELECT COUNT(*) as count FROM notificaciones
                WHERE activo = 1 AND leido = 0
                AND (dirigido_a = 'todos'
                     OR destinatario_email = :email
                     OR destinatario_rol = :role)";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':email', $userEmail);
        $stmt->bindParam(':role', $userRole);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->sendSuccess(['count' => (int)$result['count']]);
    }

    private function createNotification($data) {
        // OPCIONAL: Validación adicional (no altera funcionamiento)
        if (file_exists(dirname(__DIR__) . '/middleware/api-validator.php')) {
            require_once dirname(__DIR__) . '/middleware/api-validator.php';
            
            $rules = [
                'titulo' => 'required|string|min:3|max:255',
                'contenido' => 'required|string|min:10',
                'tipo' => 'required|in:boletin,evento,documento,comite,general,sistema',
                'destinatario_email' => 'email',
                'destinatario_rol' => 'in:admin,empleado,usuario,miembro_comite'
            ];
            
            $validation = ApiValidator::validateAndSanitize($data, $rules);
            
            if (!$validation['valid']) {
                $this->sendError('Errores de validación: ' . json_encode($validation['errors']));
                return;
            }
            
            // Usar datos sanitizados
            $data = $validation['data'];
        }
        
        // LÓGICA ORIGINAL
        $required = ['titulo', 'contenido', 'tipo'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendError("Campo requerido: $field");
                return;
            }
        }

        $sql = "INSERT INTO notificaciones (
                    titulo, contenido, tipo, origen_id, origen_tabla,
                    destinatario_email, destinatario_rol, dirigido_a,
                    importante, metadata
                ) VALUES (
                    :titulo, :contenido, :tipo, :origen_id, :origen_tabla,
                    :destinatario_email, :destinatario_rol, :dirigido_a,
                    :importante, :metadata
                )";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':titulo', $data['titulo']);
        $stmt->bindParam(':contenido', $data['contenido']);
        $stmt->bindParam(':tipo', $data['tipo']);
        $stmt->bindParam(':origen_id', $data['origen_id'] ?? null);
        $stmt->bindParam(':origen_tabla', $data['origen_tabla'] ?? null);
        $stmt->bindParam(':destinatario_email', $data['destinatario_email'] ?? null);
        $stmt->bindParam(':destinatario_rol', $data['destinatario_rol'] ?? null);
        $stmt->bindParam(':dirigido_a', $data['dirigido_a'] ?? 'todos');
        $stmt->bindParam(':importante', $data['importante'] ?? false, PDO::PARAM_BOOL);
        $stmt->bindParam(':metadata', json_encode($data['metadata'] ?? []));

        if ($stmt->execute()) {
            $newId = $this->connection->lastInsertId();

            // ── Hook de correo: Mensaje en Buzón (best-effort) ────────────
            try {
                require_once dirname(__DIR__) . '/utils/NotificationMailer.php';

                $emailDestino = $data['destinatario_email'] ?? null;
                $tituloNot    = $data['titulo'] ?? 'Nueva notificación';
                $contenidoNot = $data['contenido'] ?? '';

                NotificationMailer::dispatch(
                    'mensaje_buzon',
                    "$tituloNot",
                    "$contenidoNot\n\n" .
                    "Puedes ver todas tus notificaciones ingresando a la Intranet del Clúster.",
                    $this->connection,
                    $emailDestino  // null → no aplica para 'destinatario', lo ignora
                );
            } catch (Exception $emailEx) {
                error_log('⚠️ [notificaciones] Hook correo falló: ' . $emailEx->getMessage());
            }
            // ──────────────────────────────────────────────────────────────

            $this->sendSuccess(['id' => $newId]);
        } else {
            $this->sendError('Error al crear la notificación');
        }

    }

    private function markAsRead($id) {
        // OPCIONAL: Validación adicional (no altera funcionamiento)
        if (file_exists(dirname(__DIR__) . '/middleware/api-validator.php')) {
            require_once dirname(__DIR__) . '/middleware/api-validator.php';
            
            $validation = ApiValidator::validateField($id, 'required|int|min:1', 'id');
            
            if (!$validation['valid']) {
                $this->sendError($validation['error']);
                return;
            }
        }
        
        // LÓGICA ORIGINAL
        if (!$id) {
            $this->sendError('ID requerido');
            return;
        }

        $sql = "UPDATE notificaciones
                SET leido = 1, fecha_leido = CURRENT_TIMESTAMP
                WHERE id = :id";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            $this->sendSuccess(['message' => 'Notificación marcada como leída']);
        } else {
            $this->sendError('Error al marcar como leída');
        }
    }

    private function markAllAsRead() {
        $userEmail = $this->getCurrentUserEmail();
        $userRole = $this->getCurrentUserRole();

        if (!$userEmail) {
            $this->sendError('Usuario no autenticado', 401);
            return;
        }

        $sql = "UPDATE notificaciones
                SET leido = 1, fecha_leido = CURRENT_TIMESTAMP
                WHERE leido = 0 AND activo = 1
                AND (dirigido_a = 'todos'
                     OR destinatario_email = :email
                     OR destinatario_rol = :role)";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':email', $userEmail);
        $stmt->bindParam(':role', $userRole);

        if ($stmt->execute()) {
            $this->sendSuccess(['message' => 'Todas las notificaciones marcadas como leídas']);
        } else {
            $this->sendError('Error al marcar todas como leídas');
        }
    }

    public function autoGenerateNotifications($data) {
        $tipo = $data['tipo'] ?? null;
        $origenId = $data['origen_id'] ?? null;
        $origenTabla = $data['origen_tabla'] ?? null;

        switch ($tipo) {
            case 'nuevo_boletin':
                $this->generateBoletinNotification($origenId);
                break;
            case 'nuevo_evento':
                $this->generateEventoNotification($origenId);
                break;
            case 'nuevo_documento':
                $this->generateDocumentoNotification($origenId);
                break;
            case 'comite_sesion':
                $this->generateComiteNotification($origenId, $data);
                break;
            case 'nuevo_banner':
                $this->generateBannerNotification($origenId);
                break;
            default:
                $this->sendError('Tipo de notificación no válido');
                return;
        }

        $this->sendSuccess(['message' => 'Notificación automática generada']);
    }

    private function generateBoletinNotification($boletinId) {
        // Simular datos del boletín (normalmente vendría de la BD)
        $notification = [
            'titulo' => 'Nuevo Boletín Disponible',
            'contenido' => 'Se ha publicado un nuevo boletín informativo. Revisa las últimas noticias y actualizaciones.',
            'tipo' => 'boletin',
            'origen_id' => $boletinId,
            'origen_tabla' => 'boletines',
            'dirigido_a' => 'todos',
            'importante' => false
        ];

        $this->createNotification($notification);
    }

    private function generateEventoNotification($eventoId) {
        // Obtener información del evento de la BD
        $sql = "SELECT titulo, fecha_inicio, modalidad FROM eventos WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $eventoId);
        $stmt->execute();
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($evento) {
            $fechaEvento = date('d/m/Y H:i', strtotime($evento['fecha_inicio']));
            $notification = [
                'titulo' => 'Nuevo Evento: ' . $evento['titulo'],
                'contenido' => "Se ha programado un nuevo evento para el {$fechaEvento}. Modalidad: {$evento['modalidad']}. ¡Regístrate ahora!",
                'tipo' => 'evento',
                'origen_id' => $eventoId,
                'origen_tabla' => 'eventos',
                'dirigido_a' => 'todos',
                'importante' => true
            ];

            $this->createNotification($notification);
        }
    }

    private function generateDocumentoNotification($documentoId) {
        $notification = [
            'titulo' => 'Nuevo Documento Compartido',
            'contenido' => 'Se ha añadido un nuevo documento a la biblioteca. Consulta la sección de documentación para más detalles.',
            'tipo' => 'documento',
            'origen_id' => $documentoId,
            'origen_tabla' => 'documentos',
            'dirigido_a' => 'todos',
            'importante' => false
        ];

        $this->createNotification($notification);
    }

    private function generateComiteNotification($comiteId, $data) {
        // Obtener información del comité
        $sql = "SELECT nombre FROM comites WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $comiteId);
        $stmt->execute();
        $comite = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($comite) {
            $fechaSesion = $data['fecha_sesion'] ?? 'próximamente';
            $notification = [
                'titulo' => 'Sesión Virtual - ' . $comite['nombre'],
                'contenido' => "El comité {$comite['nombre']} ha programado una sesión virtual para {$fechaSesion}. Los miembros registrados recibirán el enlace de acceso.",
                'tipo' => 'comite',
                'origen_id' => $comiteId,
                'origen_tabla' => 'comites',
                'destinatario_rol' => 'miembro_comite',
                'dirigido_a' => 'rol',
                'importante' => true
            ];

            $this->createNotification($notification);
        }
    }

    private function generateBannerNotification($bannerId) {
        $notification = [
            'titulo' => 'Nuevo Anuncio Importante',
            'contenido' => 'Se ha publicado un nuevo banner con información relevante. Consulta el panel principal para más detalles.',
            'tipo' => 'general',
            'origen_id' => $bannerId,
            'origen_tabla' => 'banner_carrusel',
            'dirigido_a' => 'todos',
            'importante' => true
        ];

        $this->createNotification($notification);
    }

    private function getCurrentUserEmail() {
        session_start();
        return $_SESSION['user_email'] ?? null;
    }

    private function getCurrentUserRole() {
        session_start();
        return $_SESSION['user_rol'] ?? 'user';
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
$api = new NotificacionesAPI();
$api->handleRequest();
?>