<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Función para subir imagen local (igual que banner-admin-mejorado.php)
function subirImagenComite($archivo) {
    $directorioSubida = __DIR__ . '/../uploads/comites/';

    if (!file_exists($directorioSubida)) {
        if (!mkdir($directorioSubida, 0755, true)) {
            return ['success' => false, 'message' => 'No se pudo crear el directorio de uploads'];
        }
    }

    if (!is_writable($directorioSubida)) {
        return ['success' => false, 'message' => 'El directorio no tiene permisos de escritura'];
    }

    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas)) {
        return ['success' => false, 'message' => 'Formato de imagen no permitido'];
    }

    if ($archivo['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande (máximo 5MB)'];
    }

    $nombreArchivo = 'comite_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $rutaCompleta = $directorioSubida . $nombreArchivo;

    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        $rutaRelativa = 'uploads/comites/' . $nombreArchivo;
        return ['success' => true, 'ruta' => $rutaRelativa, 'nombre' => $nombreArchivo];
    } else {
        return ['success' => false, 'message' => 'Error al mover el archivo'];
    }
}

// Función para subir archivos de mensajería (imágenes y documentos)
function subirArchivoMensaje($archivo, $tipo) {
    $directorioBase = __DIR__ . '/../uploads/mensajes/';
    $directorioSubida = $directorioBase . $tipo . '/';

    // Crear directorios si no existen
    if (!file_exists($directorioBase)) {
        if (!mkdir($directorioBase, 0755, true)) {
            return ['success' => false, 'message' => 'No se pudo crear el directorio base'];
        }
    }

    if (!file_exists($directorioSubida)) {
        if (!mkdir($directorioSubida, 0755, true)) {
            return ['success' => false, 'message' => 'No se pudo crear el directorio de ' . $tipo];
        }
    }

    if (!is_writable($directorioSubida)) {
        return ['success' => false, 'message' => 'El directorio no tiene permisos de escritura'];
    }

    // Validar tipos de archivo según el tipo
    $extensionesPermitidas = [];
    $tamanoMaximo = 5 * 1024 * 1024; // 5MB por defecto

    if ($tipo === 'imagenes') {
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $tamanoMaximo = 5 * 1024 * 1024; // 5MB para imágenes
    } elseif ($tipo === 'documentos') {
        $extensionesPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
        $tamanoMaximo = 10 * 1024 * 1024; // 10MB para documentos
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $extensionesPermitidas)) {
        return ['success' => false, 'message' => 'Formato de archivo no permitido para ' . $tipo];
    }

    if ($archivo['size'] > $tamanoMaximo) {
        $maxMB = $tamanoMaximo / (1024 * 1024);
        return ['success' => false, 'message' => "El archivo es demasiado grande (máximo {$maxMB}MB)"];
    }

    $nombreArchivo = 'mensaje_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $rutaCompleta = $directorioSubida . $nombreArchivo;

    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        $rutaRelativa = 'uploads/mensajes/' . $tipo . '/' . $nombreArchivo;
        return ['success' => true, 'ruta' => $rutaRelativa, 'nombre' => $nombreArchivo];
    } else {
        return ['success' => false, 'message' => 'Error al mover el archivo'];
    }
}

// Usar la clase Database centralizada
try {
    require_once __DIR__ . '/../config/database.php';
    $conn = Database::getInstance()->getConnection();

    $action = $_GET['action'] ?? 'listar';

    switch ($action) {
        case 'listar':
            // Listar comités activos
            $stmt = $conn->prepare("
                SELECT c.id, c.nombre, c.descripcion, c.objetivo, c.fecha_creacion, c.estado, c.coordinador_id,
                       c.periodicidad, c.miembros_activos, c.organizacion, c.imagen,
                       c.tipo_registro, c.link_registro,
                       CASE WHEN c.imagen IS NOT NULL THEN 1 ELSE 0 END as tiene_imagen,
                       u.nombre as coordinador_nombre,
                       COUNT(cr.id) as total_registros,
                       COUNT(CASE WHEN cr.estado_registro = 'aprobado' THEN 1 END) as registros_aprobados,
                       COUNT(CASE WHEN cr.estado_registro = 'pendiente' THEN 1 END) as registros_pendientes
                FROM comites c
                LEFT JOIN usuarios_perfil u ON c.coordinador_id = u.id
                LEFT JOIN comite_registros cr ON c.id = cr.comite_id
                WHERE c.estado = 'activo'
                GROUP BY c.id, c.nombre, c.descripcion, c.objetivo, c.fecha_creacion, c.estado, c.coordinador_id,
                         c.periodicidad, c.miembros_activos, c.organizacion, c.imagen, c.tipo_registro, c.link_registro,
                         u.nombre
                ORDER BY c.fecha_creacion DESC
            ");
            $stmt->execute();
            $comites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'comites' => $comites
            ]);
            break;

        case 'registrar':
            // Registrar empresa a un comité
            $comite_id = $_POST['comite_id'] ?? null;
            $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
            $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
            $email_contacto = trim($_POST['email_contacto'] ?? '');
            $telefono_contacto = trim($_POST['telefono_contacto'] ?? '');
            $cargo = trim($_POST['cargo'] ?? '');
            $departamento = trim($_POST['departamento'] ?? '');
            $comentarios = trim($_POST['comentarios'] ?? '');

            // Información del usuario loggeado que envía el formulario
            $usuario_loggeado_id = trim($_POST['usuario_loggeado_id'] ?? '');
            $usuario_loggeado_nombre = trim($_POST['usuario_loggeado_nombre'] ?? '');
            $usuario_loggeado_email = trim($_POST['usuario_loggeado_email'] ?? '');
            $usuario_loggeado_empresa = trim($_POST['usuario_loggeado_empresa'] ?? '');
            $session_info = trim($_POST['session_info'] ?? '');

            // Capturar IP del usuario
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
                         $_SERVER['HTTP_X_REAL_IP'] ??
                         $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // Validación básica
            if (!$comite_id || !$nombre_empresa || !$email_contacto) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Datos requeridos: comité, empresa y email'
                ]);
                break;
            }

            // Validar formato de email
            if (!filter_var($email_contacto, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'El formato del email no es válido'
                ]);
                break;
            }

            // Verificar si ya está registrado
            $stmt = $conn->prepare("SELECT id FROM comite_registros WHERE comite_id = ? AND email_contacto = ?");
            $stmt->execute([$comite_id, $email_contacto]);

            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Esta empresa ya está registrada en este comité'
                ]);
                break;
            }

            // Insertar registro
            try {
                $stmt = $conn->prepare("
                    INSERT INTO comite_registros
                    (comite_id, nombre_empresa, nombre_usuario, email_contacto, telefono_contacto, cargo, departamento,
                     comentarios, estado_registro, usuario_loggeado_id, usuario_loggeado_nombre, usuario_loggeado_email,
                     usuario_loggeado_empresa, session_info, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?, ?, ?)
                ");

                $result = $stmt->execute([
                    $comite_id, $nombre_empresa, $nombre_usuario, $email_contacto,
                    $telefono_contacto, $cargo, $departamento, $comentarios,
                    $usuario_loggeado_id, $usuario_loggeado_nombre, $usuario_loggeado_email,
                    $usuario_loggeado_empresa, $session_info, $ip_address
                ]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Solicitud de registro enviada exitosamente. Está pendiente de aprobación.',
                        'registro_id' => $conn->lastInsertId()
                    ]);
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Error inserting comite registro: " . print_r($errorInfo, true));
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al enviar la solicitud: ' . ($errorInfo[2] ?? 'Error desconocido')
                    ]);
                }
            } catch (Exception $e) {
                error_log("Exception in comite registrar: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
                ]);
            }
            break;

        case 'registros':
            // Obtener registros de un comité específico
            $comite_id = $_GET['comite_id'] ?? null;

            if (!$comite_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del comité requerido'
                ]);
                break;
            }

            $stmt = $conn->prepare("
                SELECT cr.*, c.nombre as comite_nombre,
                       u_aprobado.nombre as aprobado_por_nombre
                FROM comite_registros cr
                LEFT JOIN comites c ON cr.comite_id = c.id
                LEFT JOIN usuarios_perfil u_aprobado ON cr.aprobado_por = u_aprobado.id
                WHERE cr.comite_id = ?
                ORDER BY cr.fecha_registro DESC
            ");
            $stmt->execute([$comite_id]);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'registros' => $registros
            ]);
            break;

        case 'registros_all':
            // Obtener todos los registros de comités con información del comité
            $stmt = $conn->prepare("
                SELECT cr.*, c.nombre as comite_nombre, c.descripcion as comite_descripcion,
                       u_aprobado.nombre as aprobado_por_nombre
                FROM comite_registros cr
                LEFT JOIN comites c ON cr.comite_id = c.id
                LEFT JOIN usuarios_perfil u_aprobado ON cr.aprobado_por = u_aprobado.id
                ORDER BY cr.fecha_registro DESC
            ");
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'registros' => $registros
            ]);
            break;

        case 'aprobar_registro':
            // Aprobar un registro de comité
            $registro_id = $_POST['registro_id'] ?? null;
            $aprobado_por = $_POST['aprobado_por'] ?? 1; // ID del usuario que aprueba

            if (!$registro_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del registro requerido'
                ]);
                break;
            }

            $stmt = $conn->prepare("
                UPDATE comite_registros
                SET estado_registro = 'aprobado',
                    fecha_aprobacion = NOW(),
                    aprobado_por = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$aprobado_por, $registro_id]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Registro aprobado exitosamente'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al aprobar el registro'
                ]);
            }
            break;

        case 'rechazar_registro':
            // Rechazar un registro de comité
            $registro_id = $_POST['registro_id'] ?? null;
            $aprobado_por = $_POST['aprobado_por'] ?? 1; // ID del usuario que rechaza

            if (!$registro_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del registro requerido'
                ]);
                break;
            }

            $stmt = $conn->prepare("
                UPDATE comite_registros
                SET estado_registro = 'rechazado',
                    fecha_aprobacion = NOW(),
                    aprobado_por = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$aprobado_por, $registro_id]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Registro rechazado'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al rechazar el registro'
                ]);
            }
            break;

        case 'eliminar_registro':
            // Eliminar definitivamente una inscripción del historial de admisiones
            $registro_id = $_POST['registro_id'] ?? null;

            if (!$registro_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del registro requerido'
                ]);
                break;
            }

            $stmt = $conn->prepare("DELETE FROM comite_registros WHERE id = ?");
            $result = $stmt->execute([$registro_id]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Registro eliminado'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al eliminar el registro'
                ]);
            }
            break;

        case 'listar_registros_pendientes':
            // Listar registros pendientes de comités
            $stmt = $conn->prepare("
                SELECT cr.id, cr.comite_id, cr.nombre_empresa, cr.nombre_usuario,
                       cr.email_contacto, cr.telefono_contacto, cr.cargo, cr.departamento,
                       cr.comentarios, cr.fecha_registro, cr.estado_registro,
                       c.nombre as comite_nombre
                FROM comite_registros cr
                LEFT JOIN comites c ON cr.comite_id = c.id
                WHERE cr.estado_registro = 'pendiente'
                ORDER BY cr.fecha_registro DESC
            ");
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'registros' => $registros
            ]);
            break;

        case 'crear':
            // Crear un nuevo comité
            $nombre = $_POST['nombre'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            $objetivo = $_POST['objetivo'] ?? '';
            $periodicidad = $_POST['periodicidad'] ?? 'Mensual';
            $miembros_activos = intval($_POST['miembros_activos'] ?? 0);
            $organizacion = $_POST['organizacion'] ?? null;
            $coordinador_id = (!empty($_POST['coordinador_id']) && $_POST['coordinador_id'] !== '') ? intval($_POST['coordinador_id']) : null;
            $estado = $_POST['estado'] ?? 'activo';
            $tipo_registro = $_POST['tipo_registro'] ?? 'formulario';
            $link_registro = $_POST['link_registro'] ?? null;

            // Procesar imagen subida o URL
            $imagen = null;

            // Prioridad 1: Archivo subido
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $resultadoImagen = subirImagenComite($_FILES['imagen']);
                if ($resultadoImagen['success']) {
                    $imagen = $resultadoImagen['ruta'];
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al subir imagen: ' . $resultadoImagen['message']
                    ]);
                    break;
                }
            }
            // Prioridad 2: URL de imagen
            else if (isset($_POST['imagen_url']) && !empty(trim($_POST['imagen_url']))) {
                $imagen_url = trim($_POST['imagen_url']);
                // Validar que sea una URL válida
                if (filter_var($imagen_url, FILTER_VALIDATE_URL)) {
                    $imagen = $imagen_url;
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'URL de imagen no válida'
                    ]);
                    break;
                }
            }

            if (!$nombre || !$descripcion || !$objetivo) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Nombre, descripción y objetivo son requeridos'
                ]);
                break;
            }

            try {
                $stmt = $conn->prepare("
                    INSERT INTO comites (nombre, descripcion, objetivo, imagen, periodicidad, miembros_activos, organizacion, coordinador_id, estado, tipo_registro, link_registro, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $result = $stmt->execute([$nombre, $descripcion, $objetivo, $imagen, $periodicidad, $miembros_activos, $organizacion, $coordinador_id, $estado, $tipo_registro, $link_registro]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Comité creado exitosamente',
                        'comite_id' => $conn->lastInsertId()
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al crear el comité'
                    ]);
                }

            } catch (Exception $e) {
                error_log("Error al crear comité: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al crear comité: ' . $e->getMessage()
                ]);
            }
            break;

        case 'editar':
            // Editar un comité existente
            $comite_id = $_POST['id'] ?? null;
            $nombre = $_POST['nombre'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            $objetivo = $_POST['objetivo'] ?? '';
            $periodicidad = $_POST['periodicidad'] ?? 'Mensual';
            $miembros_activos = intval($_POST['miembros_activos'] ?? 0);
            $organizacion = $_POST['organizacion'] ?? null;
            $coordinador_id = $_POST['coordinador_id'] ?? null;
            $estado = $_POST['estado'] ?? 'activo';
            $tipo_registro = $_POST['tipo_registro'] ?? 'formulario';
            $link_registro = $_POST['link_registro'] ?? null;

            // Procesar imagen subida (solo si se sube una nueva)
            $imagen = null;
            $updateImagen = false;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $resultadoImagen = subirImagenComite($_FILES['imagen']);
                if ($resultadoImagen['success']) {
                    $imagen = $resultadoImagen['ruta'];
                    $updateImagen = true;
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al subir imagen: ' . $resultadoImagen['message']
                    ]);
                    break;
                }
            }

            if (!$comite_id || !$nombre || !$descripcion || !$objetivo) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del comité, nombre, descripción y objetivo son requeridos'
                ]);
                break;
            }

            try {
                if ($updateImagen) {
                    // Actualizar con nueva imagen
                    $stmt = $conn->prepare("
                        UPDATE comites
                        SET nombre = ?, descripcion = ?, objetivo = ?, imagen = ?, periodicidad = ?, miembros_activos = ?, organizacion = ?, coordinador_id = ?, estado = ?, tipo_registro = ?, link_registro = ?
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([$nombre, $descripcion, $objetivo, $imagen, $periodicidad, $miembros_activos, $organizacion, $coordinador_id, $estado, $tipo_registro, $link_registro, $comite_id]);
                } else {
                    // Actualizar sin cambiar imagen
                    $stmt = $conn->prepare("
                        UPDATE comites
                        SET nombre = ?, descripcion = ?, objetivo = ?, periodicidad = ?, miembros_activos = ?, organizacion = ?, coordinador_id = ?, estado = ?, tipo_registro = ?, link_registro = ?
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([$nombre, $descripcion, $objetivo, $periodicidad, $miembros_activos, $organizacion, $coordinador_id, $estado, $tipo_registro, $link_registro, $comite_id]);
                }

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Comité actualizado exitosamente'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar el comité'
                    ]);
                }

            } catch (Exception $e) {
                error_log("Error al editar comité: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al editar comité: ' . $e->getMessage()
                ]);
            }
            break;

        case 'eliminar':
            // Eliminar un comité
            $comite_id = $_POST['id'] ?? null;

            if (!$comite_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del comité requerido'
                ]);
                break;
            }

            try {
                // Primero eliminar todos los registros del comité
                $stmt = $conn->prepare("DELETE FROM comite_registros WHERE comite_id = ?");
                $stmt->execute([$comite_id]);

                // Luego eliminar el comité
                $stmt = $conn->prepare("DELETE FROM comites WHERE id = ?");
                $result = $stmt->execute([$comite_id]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Comité eliminado exitosamente'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al eliminar el comité'
                    ]);
                }

            } catch (Exception $e) {
                error_log("Error al eliminar comité: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al eliminar comité: ' . $e->getMessage()
                ]);
            }
            break;

        case 'usuario_comites':
            // Obtener comités en los que está registrada una empresa
            $email = $_GET['email'] ?? null;

            if (!$email) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email requerido'
                ]);
                break;
            }

            $stmt = $conn->prepare("
                SELECT cr.*, c.nombre as comite_nombre, c.descripcion as comite_descripcion,
                       c.objetivo as comite_objetivo
                FROM comite_registros cr
                LEFT JOIN comites c ON cr.comite_id = c.id
                WHERE cr.email_contacto = ? AND cr.estado_registro = 'aprobado'
                ORDER BY cr.fecha_aprobacion DESC
            ");
            $stmt->execute([$email]);
            $comites = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'comites' => $comites
            ]);
            break;

        case 'imagen':
            // Servir imagen de comité desde uploads
            $comite_id = $_GET['id'] ?? null;

            if (!$comite_id) {
                header('HTTP/1.1 404 Not Found');
                header('Content-Type: text/plain');
                echo 'ID de comité requerido';
                exit;
            }

            try {
                $stmt = $conn->prepare("SELECT imagen FROM comites WHERE id = ?");
                $stmt->execute([$comite_id]);
                $result = $stmt->fetch();

                if ($result && $result['imagen']) {
                    // Verificar si es una ruta de archivo o datos binarios
                    if (strpos($result['imagen'], 'uploads/') === 0) {
                        // Nueva lógica: archivo en uploads
                        $rutaImagen = __DIR__ . '/../' . $result['imagen'];

                        if (file_exists($rutaImagen)) {
                        $extension = strtolower(pathinfo($rutaImagen, PATHINFO_EXTENSION));

                        switch ($extension) {
                            case 'jpg':
                            case 'jpeg':
                                $content_type = 'image/jpeg';
                                break;
                            case 'png':
                                $content_type = 'image/png';
                                break;
                            case 'gif':
                                $content_type = 'image/gif';
                                break;
                            case 'webp':
                                $content_type = 'image/webp';
                                break;
                            default:
                                $content_type = 'application/octet-stream';
                        }

                        header("Content-Type: $content_type");
                        header('Content-Length: ' . filesize($rutaImagen));
                        header('Accept-Ranges: bytes');
                        header('Cache-Control: public, max-age=3600');
                        header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
                        header('Access-Control-Allow-Headers: *');

                            // Output the file
                            readfile($rutaImagen);
                        } else {
                            header('HTTP/1.1 404 Not Found');
                            header('Content-Type: text/plain');
                            echo 'Archivo de imagen no encontrado';
                        }
                    } else {
                        // Lógica antigua: datos binarios (BLOB) para compatibilidad
                        $image_data = $result['imagen'];

                        // Detectar tipo de imagen
                        $first_bytes = substr($image_data, 0, 10);
                        $hex_bytes = bin2hex($first_bytes);

                        if (substr($hex_bytes, 0, 4) === 'ffd8') {
                            $content_type = 'image/jpeg';
                        } elseif (substr($hex_bytes, 0, 8) === '89504e47') {
                            $content_type = 'image/png';
                        } elseif (substr($hex_bytes, 0, 6) === '474946') {
                            $content_type = 'image/gif';
                        } else {
                            $content_type = 'image/png'; // default
                        }

                        header("Content-Type: $content_type");
                        header('Content-Length: ' . strlen($image_data));
                        header('Accept-Ranges: bytes');
                        header('Cache-Control: public, max-age=3600');
                        header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
                        header('Access-Control-Allow-Headers: *');

                        echo $image_data;
                    }
                } else {
                    header('HTTP/1.1 404 Not Found');
                    header('Content-Type: text/plain');
                    echo 'Imagen no encontrada para el comité especificado';
                }
            } catch (Exception $e) {
                header('HTTP/1.1 500 Internal Server Error');
                header('Content-Type: text/plain');
                echo 'Error al servir imagen: ' . $e->getMessage();
                error_log("Error serving image for comite $comite_id: " . $e->getMessage());
            }
            exit;

        case 'obtener_miembros':
            // Obtener miembros aprobados de un comité específico
            $comite_id = $_GET['comite_id'] ?? null;

            if (!$comite_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del comité requerido'
                ]);
                break;
            }

            try {
                $stmt = $conn->prepare("
                    SELECT cr.nombre_empresa, cr.nombre_usuario, cr.email_contacto,
                           cr.telefono_contacto, cr.cargo, cr.fecha_aprobacion
                    FROM comite_registros cr
                    WHERE cr.comite_id = ? AND cr.estado_registro = 'aprobado'
                    ORDER BY cr.nombre_empresa, cr.nombre_usuario
                ");
                $stmt->execute([$comite_id]);
                $miembros = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'miembros' => $miembros
                ]);
            } catch (Exception $e) {
                error_log("Error obteniendo miembros del comité: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener miembros del comité'
                ]);
            }
            break;

        case 'enviar_mensaje':
            // Enviar mensaje a miembros de un comité
            $comite_id = $_POST['comite_id'] ?? null;
            $destinatario = $_POST['destinatario'] ?? null;
            $tipo_mensaje = $_POST['tipo_mensaje'] ?? 'texto';
            $asunto = trim($_POST['asunto'] ?? '');

            if (!$comite_id || !$destinatario || !$asunto) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Comité, destinatario y asunto son requeridos'
                ]);
                break;
            }

            try {
                // Obtener destinatarios
                $destinatarios = [];
                if ($destinatario === 'todos') {
                    // Obtener todos los miembros aprobados del comité
                    $stmt = $conn->prepare("
                        SELECT DISTINCT email_contacto, nombre_usuario, nombre_empresa
                        FROM comite_registros
                        WHERE comite_id = ? AND estado_registro = 'aprobado'
                    ");
                    $stmt->execute([$comite_id]);
                    $destinatarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Destinatario específico
                    $stmt = $conn->prepare("
                        SELECT email_contacto, nombre_usuario, nombre_empresa
                        FROM comite_registros
                        WHERE comite_id = ? AND email_contacto = ? AND estado_registro = 'aprobado'
                    ");
                    $stmt->execute([$comite_id, $destinatario]);
                    $miembro = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($miembro) {
                        $destinatarios = [$miembro];
                    }
                }

                if (empty($destinatarios)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se encontraron destinatarios válidos'
                    ]);
                    break;
                }

                // Preparar contenido del mensaje según tipo
                $contenido_mensaje = '';
                $archivo_adjunto = null;

                switch ($tipo_mensaje) {
                    case 'texto':
                        $contenido_mensaje = trim($_POST['contenido_texto'] ?? '');
                        break;

                    case 'link':
                        $link_url = trim($_POST['link_url'] ?? '');
                        $link_texto = trim($_POST['link_texto'] ?? '');
                        $link_descripcion = trim($_POST['link_descripcion'] ?? '');

                        if (!$link_url) {
                            echo json_encode([
                                'success' => false,
                                'message' => 'URL del enlace es requerida'
                            ]);
                            break 2;
                        }

                        $contenido_mensaje = json_encode([
                            'url' => $link_url,
                            'texto' => $link_texto ?: 'Enlace',
                            'descripcion' => $link_descripcion
                        ]);
                        break;

                    case 'imagen':
                        $imagen_descripcion = trim($_POST['imagen_descripcion'] ?? '');

                        if (isset($_FILES['imagen_archivo']) && $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_OK) {
                            $archivo_adjunto = subirArchivoMensaje($_FILES['imagen_archivo'], 'imagenes');
                            if (!$archivo_adjunto['success']) {
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'Error al subir imagen: ' . $archivo_adjunto['message']
                                ]);
                                break 2;
                            }
                            $contenido_mensaje = json_encode([
                                'archivo' => $archivo_adjunto['ruta'],
                                'descripcion' => $imagen_descripcion
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Imagen es requerida'
                            ]);
                            break 2;
                        }
                        break;

                    case 'documento':
                        $documento_descripcion = trim($_POST['documento_descripcion'] ?? '');

                        if (isset($_FILES['documento_archivo']) && $_FILES['documento_archivo']['error'] === UPLOAD_ERR_OK) {
                            $archivo_adjunto = subirArchivoMensaje($_FILES['documento_archivo'], 'documentos');
                            if (!$archivo_adjunto['success']) {
                                echo json_encode([
                                    'success' => false,
                                    'message' => 'Error al subir documento: ' . $archivo_adjunto['message']
                                ]);
                                break 2;
                            }
                            $contenido_mensaje = json_encode([
                                'archivo' => $archivo_adjunto['ruta'],
                                'nombre_original' => $_FILES['documento_archivo']['name'],
                                'descripcion' => $documento_descripcion
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'message' => 'Documento es requerido'
                            ]);
                            break 2;
                        }
                        break;
                }

                if (empty($contenido_mensaje)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Contenido del mensaje es requerido'
                    ]);
                    break;
                }

                // Insertar mensajes para cada destinatario
                $mensajes_enviados = 0;
                $stmt_mensaje = $conn->prepare("
                    INSERT INTO mensajes_comites
                    (comite_id, destinatario_email, tipo_mensaje, asunto, contenido, fecha_envio, estado)
                    VALUES (?, ?, ?, ?, ?, NOW(), 'no_leido')
                ");

                foreach ($destinatarios as $destinatario_data) {
                    $result = $stmt_mensaje->execute([
                        $comite_id,
                        $destinatario_data['email_contacto'],
                        $tipo_mensaje,
                        $asunto,
                        $contenido_mensaje
                    ]);

                    if ($result) {
                        $mensajes_enviados++;
                    }
                }

                if ($mensajes_enviados > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => "Mensaje enviado exitosamente a {$mensajes_enviados} destinatario(s)",
                        'destinatarios_count' => $mensajes_enviados
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No se pudo enviar el mensaje a ningún destinatario'
                    ]);
                }

            } catch (Exception $e) {
                error_log("Error enviando mensaje: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al enviar mensaje: ' . $e->getMessage()
                ]);
            }
            break;

        case 'obtener_mensajes':
            // Obtener mensajes de comités para un usuario específico
            $email_usuario = $_GET['email'] ?? null;

            if (!$email_usuario) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Email del usuario requerido'
                ]);
                break;
            }

            try {
                $stmt = $conn->prepare("
                    SELECT mc.id, mc.comite_id, mc.tipo_mensaje, mc.asunto, mc.contenido,
                           mc.fecha_envio, mc.estado, c.nombre as comite_nombre
                    FROM mensajes_comites mc
                    LEFT JOIN comites c ON mc.comite_id = c.id
                    WHERE mc.destinatario_email = ?
                    ORDER BY mc.fecha_envio DESC
                ");
                $stmt->execute([$email_usuario]);
                $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'mensajes' => $mensajes
                ]);
            } catch (Exception $e) {
                error_log("Error obteniendo mensajes: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener mensajes'
                ]);
            }
            break;

        case 'marcar_mensaje_leido':
            // Marcar un mensaje como leído
            $mensaje_id = $_POST['mensaje_id'] ?? null;

            if (!$mensaje_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del mensaje requerido'
                ]);
                break;
            }

            try {
                $stmt = $conn->prepare("UPDATE mensajes_comites SET estado = 'leido' WHERE id = ?");
                $result = $stmt->execute([$mensaje_id]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Mensaje marcado como leído'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al marcar mensaje como leído'
                    ]);
                }
            } catch (Exception $e) {
                error_log("Error marcando mensaje como leído: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al marcar mensaje como leído'
                ]);
            }
            break;

        case 'obtener_todos_registros':
            // Obtener todos los registros de usuarios en comités
            try {
                $stmt = $conn->prepare("
                    SELECT cr.*, c.nombre as comite_nombre,
                           CASE
                               WHEN cr.usuario_loggeado_nombre IS NOT NULL
                               THEN CONCAT('Enviado por: ', cr.usuario_loggeado_nombre, ' (', cr.usuario_loggeado_email, ')')
                               ELSE 'Sin información del remitente'
                           END as info_usuario_loggeado
                    FROM comite_registros cr
                    LEFT JOIN comites c ON cr.comite_id = c.id
                    ORDER BY cr.fecha_registro DESC
                ");
                $stmt->execute();
                $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'registros' => $registros
                ]);
            } catch (Exception $e) {
                error_log("Error obteniendo todos los registros: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener registros'
                ]);
            }
            break;

        case 'cambiar_estado_registro':
            // Cambiar estado de un registro de usuario
            $input = json_decode(file_get_contents('php://input'), true);
            $registro_id = $input['registro_id'] ?? null;
            $nuevo_estado = $input['nuevo_estado'] ?? null;

            if (!$registro_id || !$nuevo_estado) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del registro y nuevo estado requeridos'
                ]);
                break;
            }

            if (!in_array($nuevo_estado, ['pendiente', 'aprobado', 'rechazado'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Estado no válido'
                ]);
                break;
            }

            try {
                $stmt = $conn->prepare("
                    UPDATE comite_registros
                    SET estado_registro = ?, fecha_aprobacion = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $result = $stmt->execute([$nuevo_estado, $registro_id]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => "Registro {$nuevo_estado} exitosamente"
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al cambiar estado del registro'
                    ]);
                }
            } catch (Exception $e) {
                error_log("Error cambiando estado del registro: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al cambiar estado del registro'
                ]);
            }
            break;

        case 'obtener_mensajes_enviados':
            // Obtener mensajes enviados por el sistema desde los comités
            try {
                // Primero verificar si la tabla existe y tiene datos
                $check_table = $conn->query("SHOW TABLES LIKE 'mensajes_comites'");
                if ($check_table->rowCount() == 0) {
                    // La tabla no existe, retornar array vacío
                    echo json_encode([
                        'success' => true,
                        'mensajes' => []
                    ]);
                    break;
                }

                // Consulta simplificada inicialmente
                $stmt = $conn->prepare("
                    SELECT mc.id, mc.comite_id, mc.tipo_mensaje, mc.asunto, mc.contenido,
                           mc.fecha_envio, mc.estado, mc.destinatario_email,
                           c.nombre as comite_nombre
                    FROM mensajes_comites mc
                    LEFT JOIN comites c ON mc.comite_id = c.id
                    ORDER BY mc.fecha_envio DESC
                ");
                $stmt->execute();
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Agrupar mensajes por asunto, fecha y comité
                $mensajes_agrupados = [];
                foreach ($resultados as $row) {
                    $key = $row['asunto'] . '_' . $row['fecha_envio'] . '_' . $row['comite_id'];

                    if (!isset($mensajes_agrupados[$key])) {
                        $mensajes_agrupados[$key] = [
                            'id' => $row['id'],
                            'comite_id' => $row['comite_id'],
                            'tipo_mensaje' => $row['tipo_mensaje'],
                            'asunto' => $row['asunto'],
                            'contenido' => $row['contenido'],
                            'fecha_envio' => $row['fecha_envio'],
                            'estado' => $row['estado'],
                            'comite_nombre' => $row['comite_nombre'],
                            'destinatarios_emails' => [],
                            'total_destinatarios' => 0,
                            'leidos' => 0,
                            'no_leidos' => 0
                        ];
                    }

                    // Agregar destinatario
                    if (!in_array($row['destinatario_email'], $mensajes_agrupados[$key]['destinatarios_emails'])) {
                        $mensajes_agrupados[$key]['destinatarios_emails'][] = $row['destinatario_email'];
                        $mensajes_agrupados[$key]['total_destinatarios']++;

                        if ($row['estado'] === 'leido') {
                            $mensajes_agrupados[$key]['leidos']++;
                        } else {
                            $mensajes_agrupados[$key]['no_leidos']++;
                        }
                    }
                }

                $mensajes = array_values($mensajes_agrupados);

                // Enriquecer cada mensaje con información adicional de destinatarios
                foreach ($mensajes as &$mensaje) {
                    // Obtener nombres de empresas para los destinatarios
                    if (!empty($mensaje['destinatarios_emails']) && count($mensaje['destinatarios_emails']) > 0) {
                        $emails = $mensaje['destinatarios_emails'];
                        $placeholders = implode(',', array_fill(0, count($emails), '?'));

                        try {
                            $stmt_nombres = $conn->prepare("
                                SELECT DISTINCT cr.email_contacto, cr.nombre_empresa, cr.nombre_usuario
                                FROM comite_registros cr
                                WHERE cr.email_contacto IN ($placeholders)
                            ");
                            $stmt_nombres->execute($emails);
                            $nombres_destinatarios = $stmt_nombres->fetchAll(PDO::FETCH_ASSOC);

                            // Crear array de destinatarios con nombres
                            $destinatarios_info = [];
                            foreach ($nombres_destinatarios as $dest) {
                                $destinatarios_info[] = [
                                    'email' => $dest['email_contacto'],
                                    'empresa' => $dest['nombre_empresa'],
                                    'usuario' => $dest['nombre_usuario']
                                ];
                            }

                            $mensaje['destinatarios_detalle'] = $destinatarios_info;
                            $mensaje['destinatarios_nombres'] = array_map(function($d) {
                                return $d['empresa'] . ($d['usuario'] ? ' (' . $d['usuario'] . ')' : '');
                            }, $destinatarios_info);
                        } catch (Exception $e) {
                            // Si hay error obteniendo nombres, usar solo emails
                            error_log("Error obteniendo nombres de destinatarios: " . $e->getMessage());
                            $mensaje['destinatarios_detalle'] = [];
                            $mensaje['destinatarios_nombres'] = $emails;
                        }
                    } else {
                        $mensaje['destinatarios_detalle'] = [];
                        $mensaje['destinatarios_nombres'] = [];
                    }

                    // Limpiar el array temporal de emails
                    unset($mensaje['destinatarios_emails']);
                }

                echo json_encode([
                    'success' => true,
                    'mensajes' => $mensajes
                ]);
            } catch (Exception $e) {
                error_log("Error obteniendo mensajes enviados: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al obtener mensajes enviados'
                ]);
            }
            break;

        case 'actualizar_mensaje':
            // Actualizar un mensaje existente
            $input = json_decode(file_get_contents('php://input'), true);
            $mensaje_id = $input['mensaje_id'] ?? null;
            $nuevo_asunto = trim($input['asunto'] ?? '');
            $nuevo_contenido = trim($input['contenido'] ?? '');

            if (!$mensaje_id || !$nuevo_asunto) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del mensaje y asunto son requeridos'
                ]);
                break;
            }

            try {
                // Actualizar todos los mensajes con el mismo asunto y fecha
                $stmt = $conn->prepare("
                    UPDATE mensajes_comites
                    SET asunto = ?, contenido = ?
                    WHERE id IN (
                        SELECT DISTINCT m2.id FROM
                        (SELECT id, asunto, fecha_envio, comite_id FROM mensajes_comites WHERE id = ?) m1
                        JOIN mensajes_comites m2 ON m1.asunto = m2.asunto
                        AND m1.fecha_envio = m2.fecha_envio
                        AND m1.comite_id = m2.comite_id
                    )
                ");
                $result = $stmt->execute([$nuevo_asunto, $nuevo_contenido, $mensaje_id]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Mensaje actualizado exitosamente'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al actualizar el mensaje'
                    ]);
                }
            } catch (Exception $e) {
                error_log("Error actualizando mensaje: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al actualizar mensaje: ' . $e->getMessage()
                ]);
            }
            break;

        case 'eliminar_mensaje':
            // Eliminar un mensaje y todos sus destinatarios relacionados
            $input = json_decode(file_get_contents('php://input'), true);
            $mensaje_id = $input['mensaje_id'] ?? null;

            if (!$mensaje_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID del mensaje requerido'
                ]);
                break;
            }

            try {
                // Eliminar todos los mensajes con el mismo asunto y fecha
                $stmt = $conn->prepare("
                    DELETE FROM mensajes_comites
                    WHERE id IN (
                        SELECT DISTINCT m2.id FROM
                        (SELECT id, asunto, fecha_envio, comite_id FROM mensajes_comites WHERE id = ?) m1
                        JOIN mensajes_comites m2 ON m1.asunto = m2.asunto
                        AND m1.fecha_envio = m2.fecha_envio
                        AND m1.comite_id = m2.comite_id
                    )
                ");
                $result = $stmt->execute([$mensaje_id]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Mensaje eliminado exitosamente'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al eliminar el mensaje'
                    ]);
                }
            } catch (Exception $e) {
                error_log("Error eliminando mensaje: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al eliminar mensaje: ' . $e->getMessage()
                ]);
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
    error_log("Error en comites.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>