<?php
/**
 * API para manejo de eventos - Base de datos real
 */

// Headers CORS y JSON
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// RATE LIMITING - Protección contra abuso de API
// ============================================
try {
    require_once dirname(__DIR__) . '/middleware/rate-limiter.php';
    
    $rateLimiter = new RateLimiter();
    $clientIP = getRateLimitIdentifier();
    
    // Verificar límite (100 requests / minuto para APIs públicas)
    $rateLimiter->protect(
        $clientIP,
        RateLimitConfig::API_PUBLIC['max'],
        RateLimitConfig::API_PUBLIC['window'],
        RateLimitConfig::API_PUBLIC['action']
    );
    
} catch (Exception $e) {
    // Si hay error en rate limiter, continuar sin bloquear
    error_log("Error en rate limiter (eventos): " . $e->getMessage());
}
// ============================================

require_once __DIR__ . '/../config/database.php';

// Función para construir URL de imagen
function buildImageUrl($imagen) {
    if (empty($imagen)) {
        return '';
    }

    // Si ya es una URL completa, devolverla tal como está
    if (filter_var($imagen, FILTER_VALIDATE_URL)) {
        return $imagen;
    }

    // Si es un archivo local, construir la URL del API
    if (strpos($imagen, 'evento_') === 0) {
        // Es un archivo subido, usar el endpoint de imagen
        return "./api/eventos.php?action=imagen&file=" . urlencode($imagen);
    }

    // Para cualquier otro caso, devolver como está
    return $imagen;
}

try {
    $action = $_GET['action'] ?? 'listar';
    
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    switch ($action) {
        case 'imagen':
            // Servir imagen de evento
            $evento_id = $_GET['id'] ?? null;
            $file = $_GET['file'] ?? null;

            $imagenData = null;

            if ($file) {
                // Servir imagen por nombre de archivo
                $imagenData = $file;
            } elseif ($evento_id) {
                // Servir imagen por ID de evento
                $stmt = $conn->prepare("SELECT imagen FROM eventos WHERE id = ?");
                $stmt->execute([$evento_id]);
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$resultado || !$resultado['imagen']) {
                    $defaultImage = "https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80";
                    header('Location: ' . $defaultImage);
                    exit;
                }

                $imagenData = $resultado['imagen'];
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID de evento o nombre de archivo requerido']);
                exit;
            }

            // Si es una URL, redirigir
            if (filter_var($imagenData, FILTER_VALIDATE_URL)) {
                header('Location: ' . $imagenData);
                exit;
            }

            // Si es data en base64, servir directamente
            if (strpos($imagenData, 'data:image/') === 0) {
                $parts = explode(',', $imagenData);
                if (count($parts) === 2) {
                    $mimeType = explode(';', explode(':', $parts[0])[1])[0];
                    $imageContent = base64_decode($parts[1]);

                    header('Content-Type: ' . $mimeType);
                    header('Content-Length: ' . strlen($imageContent));
                    header('Cache-Control: public, max-age=3600');
                    echo $imageContent;
                    exit;
                }
            }

            // Si es un archivo local
            $uploadDir = __DIR__ . '/../uploads/eventos/';
            $filePath = $uploadDir . basename($imagenData);

            if (file_exists($filePath)) {
                $mimeType = mime_content_type($filePath);
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: public, max-age=3600');
                readfile($filePath);
                exit;
            }

            // Si el archivo no existe o hubo error, servir una imagen por defecto para evitar 404s en consola
            $defaultImage = "https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80";
            header('Location: ' . $defaultImage);
            exit;

        case 'listar':
            // Consultar TODOS los eventos de la tabla eventos sin filtro de estado
            $stmt = $conn->prepare("SELECT * FROM eventos ORDER BY fecha_inicio DESC");
            $stmt->execute();
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug: Mostrar cuántos eventos se encontraron
            error_log("Eventos encontrados en BD: " . count($eventos));
            if (count($eventos) > 0) {
                error_log("Primer evento: " . json_encode($eventos[0]));
            }
            
            // Formatear datos para la vista
            $eventosFormateados = [];
            foreach ($eventos as $evento) {
                $eventosFormateados[] = [
                    'id' => $evento['id'],
                    'titulo' => $evento['titulo'],
                    'descripcion' => $evento['descripcion'] ?? 'Sin descripción',
                    'fecha_inicio' => $evento['fecha_inicio'],
                    'fecha_fin' => $evento['fecha_fin'],
                    'ubicacion' => $evento['ubicacion'] ?? 'Por definir',
                    'tipo' => $evento['tipo'] ?? 'Evento',
                    'modalidad' => $evento['modalidad'] ?? 'Presencial',
                    'estado' => $evento['estado'],
                    'cupo_maximo' => $evento['capacidad_maxima'] ?? 100,
                    'capacidad_maxima' => $evento['capacidad_maxima'] ?? 100,
                    'capacidad_actual' => $evento['capacidad_actual'] ?? 0,
                    'registrados' => $evento['capacidad_actual'] ?? 0,
                    'precio' => $evento['precio'] ?? 0,
                    'organizador_id' => $evento['organizador_id'],
                    'comite_id' => $evento['comite_id'],
                    'imagen' => $evento['imagen'],
                    'imagen_url' => buildImageUrl($evento['imagen']), // Construir URL completa
                    'tiene_imagen' => !empty($evento['imagen']),
                    'created_at' => $evento['fecha_creacion'],
                    'updated_at' => $evento['fecha_actualizacion']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'eventos' => $eventosFormateados,
                'total' => count($eventosFormateados),
                'message' => count($eventosFormateados) > 0 ? 'Eventos cargados exitosamente' : 'No hay eventos disponibles'
            ]);
            break;

        case 'obtener':
            // Obtener un evento específico por ID
            $evento_id = $_GET['id'] ?? null;

            // OPCIONAL: Validación adicional (no altera funcionamiento)
            if (file_exists(dirname(__DIR__) . '/middleware/api-validator.php')) {
                require_once dirname(__DIR__) . '/middleware/api-validator.php';
                
                $validation = ApiValidator::validateField($evento_id, 'required|int|min:1', 'id');
                
                if (!$validation['valid']) {
                    echo json_encode([
                        'success' => false,
                        'message' => $validation['error']
                    ]);
                    exit;
                }
            }

            // LÓGICA ORIGINAL
            if (!$evento_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de evento requerido'
                ]);
                break;
            }

            $stmt = $conn->prepare("SELECT * FROM eventos WHERE id = ? LIMIT 1");
            $stmt->execute([$evento_id]);
            $evento = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($evento) {
                // Formatear el evento igual que en listar
                $eventoFormateado = [
                    'id' => $evento['id'],
                    'titulo' => $evento['titulo'],
                    'descripcion' => $evento['descripcion'] ?? 'Sin descripción',
                    'fecha_inicio' => $evento['fecha_inicio'],
                    'fecha_fin' => $evento['fecha_fin'],
                    'ubicacion' => $evento['ubicacion'] ?? 'Por definir',
                    'tipo' => $evento['tipo'] ?? 'Evento',
                    'modalidad' => $evento['modalidad'] ?? 'Presencial',
                    'estado' => $evento['estado'],
                    'cupo_maximo' => $evento['capacidad_maxima'] ?? 100,
                    'capacidad_maxima' => $evento['capacidad_maxima'] ?? 100,
                    'capacidad_actual' => $evento['capacidad_actual'] ?? 0,
                    'registrados' => $evento['capacidad_actual'] ?? 0,
                    'precio' => $evento['precio'] ?? 0,
                    'organizador_id' => $evento['organizador_id'],
                    'comite_id' => $evento['comite_id'],
                    'imagen' => $evento['imagen'],
                    'imagen_url' => buildImageUrl($evento['imagen']), // Construir URL completa
                    'tiene_imagen' => !empty($evento['imagen']),
                    'created_at' => $evento['fecha_creacion'],
                    'updated_at' => $evento['fecha_actualizacion']
                ];

                echo json_encode([
                    'success' => true,
                    'evento' => $eventoFormateado,
                    'message' => 'Evento obtenido correctamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Evento no encontrado'
                ]);
            }
            break;
            
        case 'registrar':
            // Registrar empresa/usuario a un evento
            $evento_id = $_POST['evento_id'] ?? null;
            $empresa_id = $_POST['empresa_id'] ?? null;
            $usuario_id = $_POST['usuario_id'] ?? null;
            $nombre_empresa = $_POST['nombre_empresa'] ?? '';
            $nombre_usuario = $_POST['nombre_usuario'] ?? '';
            $email_contacto = $_POST['email_contacto'] ?? '';
            $telefono_contacto = $_POST['telefono_contacto'] ?? '';
            $comentarios = $_POST['comentarios'] ?? '';
            
            if (!$evento_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de evento requerido'
                ]);
                break;
            }
            
            try {
                // Verificar si la tabla existe y tiene las columnas correctas
                $tableExists = false;
                $hasCorrectColumns = false;
                
                try {
                    $stmt = $conn->prepare("DESCRIBE evento_registros");
                    $stmt->execute();
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $tableExists = true;
                    
                    // Verificar si tiene la columna email_contacto
                    $columnNames = array_column($columns, 'Field');
                    $hasCorrectColumns = in_array('email_contacto', $columnNames);
                } catch (Exception $e) {
                    // La tabla no existe
                    $tableExists = false;
                }
                
                // Si la tabla no existe o no tiene las columnas correctas, recrearla
                if (!$tableExists || !$hasCorrectColumns) {
                    if ($tableExists) {
                        // Eliminar tabla existente con estructura incorrecta
                        $stmt = $conn->prepare("DROP TABLE IF EXISTS evento_registros");
                        $stmt->execute();
                    }
                    
                    // Crear tabla nueva con estructura correcta
                    $stmt = $conn->prepare("
                        CREATE TABLE evento_registros (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            evento_id INT NOT NULL,
                            empresa_id INT,
                            usuario_id INT,
                            nombre_empresa VARCHAR(255),
                            nombre_usuario VARCHAR(255),
                            email_contacto VARCHAR(255),
                            telefono_contacto VARCHAR(20),
                            comentarios TEXT,
                            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
                            estado_registro VARCHAR(50) DEFAULT 'pendiente',
                            INDEX idx_evento_id (evento_id),
                            INDEX idx_email (email_contacto)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $stmt->execute();
                }
                
                // Verificar si ya está registrado
                $stmt = $conn->prepare("SELECT id FROM evento_registros WHERE evento_id = ? AND email_contacto = ?");
                $stmt->execute([$evento_id, $email_contacto]);
                
                if ($stmt->fetch()) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Ya estás registrado a este evento'
                    ]);
                    break;
                }
                
                // Insertar registro
                $stmt = $conn->prepare("
                    INSERT INTO evento_registros (evento_id, nombre_empresa, nombre_usuario, email_contacto, telefono_contacto, comentarios, estado_registro)
                    VALUES (?, ?, ?, ?, ?, ?, 'pendiente')
                ");
                
                $result = $stmt->execute([
                    $evento_id,
                    $nombre_empresa,
                    $nombre_usuario,
                    $email_contacto,
                    $telefono_contacto,
                    $comentarios
                ]);
                
                if ($result) {
                    // Actualizar capacidad actual del evento
                    $stmt = $conn->prepare("UPDATE eventos SET capacidad_actual = capacidad_actual + 1 WHERE id = ?");
                    $stmt->execute([$evento_id]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Registro exitoso al evento',
                        'registro_id' => $conn->lastInsertId()
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al registrar al evento'
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Error en registro de evento: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al procesar registro: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'registros':
            // Obtener registros de un evento específico
            $evento_id = $_GET['evento_id'] ?? null;
            
            if (!$evento_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de evento requerido'
                ]);
                break;
            }
            
            $stmt = $conn->prepare("
                SELECT r.*, e.titulo as evento_titulo
                FROM evento_registros r
                JOIN eventos e ON r.evento_id = e.id
                WHERE r.evento_id = ?
                ORDER BY r.fecha_registro DESC
            ");
            
            $stmt->execute([$evento_id]);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'registros' => $registros,
                'total' => count($registros)
            ]);
            break;
            
        case 'registros_all':
            // Obtener todos los registros con información del evento
            try {
                // Verificar si la tabla existe y tiene las columnas correctas
                $tableExists = false;
                $hasCorrectColumns = false;
                
                try {
                    $stmt = $conn->prepare("DESCRIBE evento_registros");
                    $stmt->execute();
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $tableExists = true;
                    
                    // Verificar si tiene la columna email_contacto
                    $columnNames = array_column($columns, 'Field');
                    $hasCorrectColumns = in_array('email_contacto', $columnNames);
                } catch (Exception $e) {
                    $tableExists = false;
                }
                
                // Si la tabla no existe o no tiene las columnas correctas, recrearla
                if (!$tableExists || !$hasCorrectColumns) {
                    if ($tableExists) {
                        $stmt = $conn->prepare("DROP TABLE IF EXISTS evento_registros");
                        $stmt->execute();
                    }
                    
                    $stmt = $conn->prepare("
                        CREATE TABLE evento_registros (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            evento_id INT NOT NULL,
                            empresa_id INT,
                            usuario_id INT,
                            nombre_empresa VARCHAR(255),
                            nombre_usuario VARCHAR(255),
                            email_contacto VARCHAR(255),
                            telefono_contacto VARCHAR(20),
                            comentarios TEXT,
                            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
                            estado_registro VARCHAR(50) DEFAULT 'pendiente',
                            INDEX idx_evento_id (evento_id),
                            INDEX idx_email (email_contacto)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $stmt->execute();
                }
                
                // Ahora obtener los registros
                $stmt = $conn->prepare("
                    SELECT r.*, e.titulo as evento_titulo
                    FROM evento_registros r
                    LEFT JOIN eventos e ON r.evento_id = e.id
                    ORDER BY r.fecha_registro DESC
                ");
                
                $stmt->execute();
                $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'registros' => $registros,
                    'total' => count($registros)
                ]);
                
            } catch (Exception $e) {
                error_log("Error en registros_all: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al cargar registros: ' . $e->getMessage(),
                    'registros' => [],
                    'total' => 0
                ]);
            }
            break;
            
        case 'debug_tabla':
            // Debug: Verificar estructura de tabla evento_registros
            try {
                $actions = [];
                
                // Verificar si la tabla existe y tiene las columnas correctas
                $tableExists = false;
                $hasCorrectColumns = false;
                
                try {
                    $stmt = $conn->prepare("DESCRIBE evento_registros");
                    $stmt->execute();
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $tableExists = true;
                    $actions[] = "Tabla existe";
                    
                    // Verificar si tiene la columna email_contacto
                    $columnNames = array_column($columns, 'Field');
                    $hasCorrectColumns = in_array('email_contacto', $columnNames);
                    $actions[] = $hasCorrectColumns ? "Estructura correcta" : "Estructura incorrecta";
                } catch (Exception $e) {
                    $tableExists = false;
                    $actions[] = "Tabla no existe";
                }
                
                // Si la tabla no existe o no tiene las columnas correctas, recrearla
                if (!$tableExists || !$hasCorrectColumns) {
                    if ($tableExists) {
                        $stmt = $conn->prepare("DROP TABLE IF EXISTS evento_registros");
                        $stmt->execute();
                        $actions[] = "Tabla eliminada";
                    }
                    
                    $stmt = $conn->prepare("
                        CREATE TABLE evento_registros (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            evento_id INT NOT NULL,
                            empresa_id INT,
                            usuario_id INT,
                            nombre_empresa VARCHAR(255),
                            nombre_usuario VARCHAR(255),
                            email_contacto VARCHAR(255),
                            telefono_contacto VARCHAR(20),
                            comentarios TEXT,
                            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
                            estado_registro VARCHAR(50) DEFAULT 'pendiente',
                            INDEX idx_evento_id (evento_id),
                            INDEX idx_email (email_contacto)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    $stmt->execute();
                    $actions[] = "Tabla creada con estructura correcta";
                }
                
                // Verificar la estructura final
                $stmt = $conn->prepare("DESCRIBE evento_registros");
                $stmt->execute();
                $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Verificar cuántos registros existen
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM evento_registros");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'columns' => $finalColumns,
                    'total_registros' => $count['total'],
                    'actions_taken' => $actions,
                    'message' => 'Tabla evento_registros verificada: ' . implode(', ', $actions)
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error con tabla: ' . $e->getMessage(),
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'eliminar_registro':
            // Eliminar un registro específico
            $registro_id = $_GET['registro_id'] ?? null;
            
            if (!$registro_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de registro requerido'
                ]);
                break;
            }
            
            // Obtener información del registro antes de eliminarlo para actualizar capacidad
            $stmt = $conn->prepare("SELECT evento_id FROM evento_registros WHERE id = ?");
            $stmt->execute([$registro_id]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registro) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ]);
                break;
            }
            
            // Eliminar el registro
            $stmt = $conn->prepare("DELETE FROM evento_registros WHERE id = ?");
            $result = $stmt->execute([$registro_id]);
            
            if ($result) {
                // Actualizar capacidad actual del evento
                $stmt = $conn->prepare("UPDATE eventos SET capacidad_actual = capacidad_actual - 1 WHERE id = ? AND capacidad_actual > 0");
                $stmt->execute([$registro['evento_id']]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Registro eliminado exitosamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al eliminar el registro'
                ]);
            }
            break;
            
        case 'crear':
            // Crear un nuevo evento
            $titulo = $_POST['titulo'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            $fecha_inicio = $_POST['fecha_inicio'] ?? '';
            $fecha_fin = $_POST['fecha_fin'] ?? '';
            $ubicacion = $_POST['ubicacion'] ?? '';
            $tipo = $_POST['tipo'] ?? 'Evento';
            $modalidad = $_POST['modalidad'] ?? 'Presencial';
            $capacidad_maxima = $_POST['capacidad_maxima'] ?? 100;
            $precio = $_POST['precio'] ?? 0;

            // === NUEVOS CAMPOS ===
            $link_evento = $_POST['link_evento'] ?? '';
            $link_mapa = $_POST['link_mapa'] ?? '';
            $tiene_beneficio = $_POST['tiene_beneficio'] ?? 0;

            // Manejo de imagen mejorado
            $imagen = '';
            $imagen_method = $_POST['imagen_method'] ?? 'upload';
            $imagen_url_final = $_POST['imagen_url_final'] ?? '';

            // Debug: Log de imagen
            error_log("=== CREAR EVENTO - IMAGEN DEBUG ===");
            error_log("imagen_method: " . $imagen_method);
            error_log("imagen_url_final: " . $imagen_url_final);
            error_log("FILES imagenFile: " . print_r($_FILES['imagenFile'] ?? 'NO_FILE', true));

            if ($imagen_method === 'url' && !empty($imagen_url_final)) {
                // URL externa
                $imagen = $imagen_url_final;
            } elseif ($imagen_method === 'upload' && isset($_FILES['imagenFile']) && $_FILES['imagenFile']['error'] === UPLOAD_ERR_OK) {
                // Upload de archivo
                $uploadDir = __DIR__ . '/../uploads/eventos/';

                // Crear directorio si no existe
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $file = $_FILES['imagenFile'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                if (!in_array($file['type'], $allowedTypes)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Tipo de archivo no válido. Solo se permiten: JPG, PNG, GIF, WEBP'
                    ]);
                    exit;
                }

                if ($file['size'] > $maxSize) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'El archivo es demasiado grande. Tamaño máximo: 5MB'
                    ]);
                    exit;
                }

                // Generar nombre único
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'evento_' . uniqid() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $imagen = $filename; // Guardar solo el nombre del archivo
                    error_log("✅ Imagen guardada exitosamente: " . $uploadPath);
                    error_log("✅ Nombre de archivo a guardar en BD: " . $imagen);
                } else {
                    error_log("❌ Error al mover archivo desde: " . $file['tmp_name'] . " a: " . $uploadPath);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al subir la imagen'
                    ]);
                    exit;
                }
            } elseif (!empty($_POST['imagen'])) {
                // Compatibilidad con el sistema anterior
                $imagen = $_POST['imagen'];
            }
            
            if (!$titulo || !$fecha_inicio) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Título y fecha de inicio son requeridos'
                ]);
                break;
            }
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, tipo, modalidad, capacidad_maxima, capacidad_actual, precio, imagen, estado, fecha_creacion, link_evento, link_mapa, tiene_beneficio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'activo', NOW(), ?, ?, ?)
                ");

                $result = $stmt->execute([
                    $titulo,
                    $descripcion,
                    $fecha_inicio,
                    $fecha_fin,
                    $ubicacion,
                    $tipo,
                    $modalidad,
                    $capacidad_maxima,
                    $precio,
                    $imagen,
                    $link_evento,
                    $link_mapa,
                    $tiene_beneficio
                ]);
                
                if ($result) {
                    $nuevoEventoId = $conn->lastInsertId();
                    
                    // Preparamos la respuesta
                    $responseData = [
                        'success' => true,
                        'message' => 'Evento creado exitosamente',
                        'evento_id' => $nuevoEventoId
                    ];

                    // Responder al cliente
                    echo json_encode($responseData);
                    
                    // Forzar el envío del buffer si es posible
                    if (ob_get_level() > 0) ob_end_flush();
                    flush();
                    
                    if (function_exists('fastcgi_finish_request')) {
                        fastcgi_finish_request();
                    }

                    // A partir de aquí, el script sigue corriendo pero el cliente ya recibió el JSON
                    // ── Hook de correo: Nuevo Evento (best-effort) ──────────
                    try {
                        require_once __DIR__ . '/../utils/NotificationMailer.php';
                        $fechaFormateada = $fecha_inicio
                            ? date('d/m/Y \a \l\a\s H:i', strtotime($fecha_inicio))
                            : 'próximamente';
                        NotificationMailer::dispatch(
                            'nuevo_evento',
                            "Nuevo evento: $titulo",
                            "Se ha programado un nuevo evento en la Intranet del Clúster.\n\n" .
                            "Evento: $titulo\n" .
                            "Fecha: $fechaFormateada\n" .
                            ($ubicacion ? "Lugar: $ubicacion\n" : '') .
                            "Modalidad: $modalidad\n\n" .
                            "Ingresa a la intranet para ver los detalles y registrarte.",
                            $conn
                        );
                    } catch (Exception $emailEx) {
                        error_log('⚠️ [eventos] Hook correo falló: ' . $emailEx->getMessage());
                    }
                    // ─────────────────────────────────────────────────────────

                    exit;

                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al crear el evento'
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Error al crear evento: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al crear evento: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'editar':
            // Editar un evento existente
            $evento_id = $_POST['id'] ?? null;
            $titulo = $_POST['titulo'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            $fecha_inicio = $_POST['fecha_inicio'] ?? '';
            $fecha_fin = $_POST['fecha_fin'] ?? '';
            $ubicacion = $_POST['ubicacion'] ?? '';
            $tipo = $_POST['tipo'] ?? 'Evento';
            $modalidad = $_POST['modalidad'] ?? 'Presencial';
            $capacidad_maxima = $_POST['capacidad_maxima'] ?? 100;
            $precio = $_POST['precio'] ?? 0;

            // === NUEVOS CAMPOS ===
            $link_evento = $_POST['link_evento'] ?? '';
            $link_mapa = $_POST['link_mapa'] ?? '';
            $tiene_beneficio = $_POST['tiene_beneficio'] ?? 0;

            // Debug: Log de datos recibidos
            error_log("=== EDITAR EVENTO DEBUG ===");
            error_log("evento_id recibido: " . ($evento_id ?? 'NULL'));
            error_log("titulo recibido: " . $titulo);
            error_log("action recibido: " . ($_GET['action'] ?? 'NULL'));
            error_log("Datos POST completos: " . print_r($_POST, true));
            error_log("Datos FILES completos: " . print_r($_FILES, true));

            // Obtener imagen actual del evento
            $stmt_current = $conn->prepare("SELECT imagen FROM eventos WHERE id = ?");
            $stmt_current->execute([$evento_id]);
            $current_event = $stmt_current->fetch(PDO::FETCH_ASSOC);
            $imagen = $current_event['imagen'] ?? ''; // Mantener imagen actual por defecto

            // Manejo de imagen mejorado (igual que en crear)
            $imagen_method = $_POST['imagen_method'] ?? 'upload';
            $imagen_url_final = $_POST['imagen_url_final'] ?? '';

            if ($imagen_method === 'url' && !empty($imagen_url_final)) {
                // URL externa
                $imagen = $imagen_url_final;
            } elseif ($imagen_method === 'upload' && isset($_FILES['imagenFile']) && $_FILES['imagenFile']['error'] === UPLOAD_ERR_OK) {
                // Upload de archivo nuevo
                $uploadDir = __DIR__ . '/../uploads/eventos/';

                // Crear directorio si no existe
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $file = $_FILES['imagenFile'];
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                if (!in_array($file['type'], $allowedTypes)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Tipo de archivo no válido. Solo se permiten: JPG, PNG, GIF, WEBP'
                    ]);
                    exit;
                }

                if ($file['size'] > $maxSize) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'El archivo es demasiado grande. Tamaño máximo: 5MB'
                    ]);
                    exit;
                }

                // Eliminar imagen anterior si existe y es un archivo local
                if (!empty($current_event['imagen']) && !filter_var($current_event['imagen'], FILTER_VALIDATE_URL)) {
                    $oldImagePath = $uploadDir . basename($current_event['imagen']);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                // Generar nombre único
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'evento_' . uniqid() . '.' . $extension;
                $uploadPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $imagen = $filename; // Guardar solo el nombre del archivo
                    error_log("✅ Imagen guardada exitosamente: " . $uploadPath);
                    error_log("✅ Nombre de archivo a guardar en BD: " . $imagen);
                } else {
                    error_log("❌ Error al mover archivo desde: " . $file['tmp_name'] . " a: " . $uploadPath);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al subir la imagen'
                    ]);
                    exit;
                }
            } elseif (!empty($_POST['imagen'])) {
                // Compatibilidad con el sistema anterior
                $imagen = $_POST['imagen'];
            }

            if (!$evento_id || !$titulo || !$fecha_inicio) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del evento, título y fecha de inicio son requeridos'
                ]);
                break;
            }
            
            try {
                $stmt = $conn->prepare("
                    UPDATE eventos
                    SET titulo = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, ubicacion = ?,
                        tipo = ?, modalidad = ?, capacidad_maxima = ?, precio = ?, imagen = ?,
                        link_evento = ?, link_mapa = ?, tiene_beneficio = ?,
                        fecha_actualizacion = NOW()
                    WHERE id = ?
                ");

                $result = $stmt->execute([
                    $titulo,
                    $descripcion,
                    $fecha_inicio,
                    $fecha_fin,
                    $ubicacion,
                    $tipo,
                    $modalidad,
                    $capacidad_maxima,
                    $precio,
                    $imagen,
                    $link_evento,
                    $link_mapa,
                    $tiene_beneficio,
                    $evento_id
                ]);
                
                if ($result) {
                    error_log("✅ Evento editado exitosamente - ID: " . $evento_id);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Evento actualizado exitosamente'
                    ]);
                } else {
                    error_log("❌ Error al ejecutar UPDATE - evento_id: " . $evento_id);
                    error_log("❌ SQL Error: " . print_r($stmt->errorInfo(), true));
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar el evento'
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Error al editar evento: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al editar evento: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'eliminar':
            // Eliminar un evento
            $evento_id = $_GET['id'] ?? null;
            
            if (!$evento_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del evento requerido'
                ]);
                break;
            }
            
            try {
                // Primero eliminar todos los registros del evento
                $stmt = $conn->prepare("DELETE FROM evento_registros WHERE evento_id = ?");
                $stmt->execute([$evento_id]);
                
                // Luego eliminar el evento
                $stmt = $conn->prepare("DELETE FROM eventos WHERE id = ?");
                $result = $stmt->execute([$evento_id]);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Evento eliminado exitosamente'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al eliminar el evento'
                    ]);
                }
                
            } catch (Exception $e) {
                error_log("Error al eliminar evento: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al eliminar evento: ' . $e->getMessage()
                ]);
            }
            break;

        case 'cambiar_estado_registro':
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                $registro_id = intval($input['registro_id'] ?? 0);
                $nuevo_estado = $input['estado'] ?? '';

                if ($registro_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID de registro inválido']);
                    break;
                }

                // Validar estados permitidos
                $estados_permitidos = ['pendiente', 'confirmado', 'cancelado'];
                if (!in_array($nuevo_estado, $estados_permitidos)) {
                    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
                    break;
                }

                // Actualizar estado del registro
                $stmt = $conn->prepare("UPDATE evento_registros SET estado_registro = ? WHERE id = ?");
                $result = $stmt->execute([$nuevo_estado, $registro_id]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => "Registro actualizado a: $nuevo_estado"
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar registro']);
                }

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error en API eventos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>