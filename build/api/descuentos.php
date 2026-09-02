<?php
/**
 * API de descuentos - Gestión completa de descuentos empresariales
 */

// Headers CORS
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Usar configuración de base de datos remota únicamente
require_once '../config/database.php';

// Función para respuesta JSON
function sendJsonResponse($data, $success = true) {
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($success) {
        $response = array_merge($response, $data);
    } else {
        $response['message'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Función para sanitizar input
function clean($input) {
    if (is_array($input)) {
        return array_map('clean', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

try {
    // Conectar a la base de datos usando singleton
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Manejar tabla descuentos y corregir foreign keys si es necesario
    try {
        // Primero verificar si la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'descuentos'");
        $tableExists = $stmt->fetch();

        if ($tableExists) {
            // Si existe, verificar y corregir foreign keys
            try {
                // Intentar eliminar constraint incorrecta si existe
                $pdo->exec("ALTER TABLE descuentos DROP FOREIGN KEY descuentos_ibfk_1");
            } catch (PDOException $e) {
                // Ignorar error si la constraint no existe
            }

            // Verificar qué tabla de empresas usar
            $stmt = $pdo->query("SHOW TABLES LIKE 'empresas_convenio'");
            $empresasConvenioExists = $stmt->fetch();

            $stmt = $pdo->query("SHOW TABLES LIKE 'empresas'");
            $empresasExists = $stmt->fetch();

            if ($empresasConvenioExists) {
                // Usar empresas_convenio
                try {
                    $pdo->exec("ALTER TABLE descuentos ADD CONSTRAINT fk_descuentos_empresa_convenio
                               FOREIGN KEY (empresa_oferente_id) REFERENCES empresas_convenio(id) ON DELETE CASCADE");
                } catch (PDOException $e) {
                    // Constraint ya existe o error, continuar
                }
            } elseif ($empresasExists) {
                // Usar empresas
                try {
                    $pdo->exec("ALTER TABLE descuentos ADD CONSTRAINT fk_descuentos_empresa
                               FOREIGN KEY (empresa_oferente_id) REFERENCES empresas(id) ON DELETE CASCADE");
                } catch (PDOException $e) {
                    // Constraint ya existe o error, continuar
                }
            }
        } else {
            // Crear tabla nueva
            // Verificar qué tabla de empresas usar
            $stmt = $pdo->query("SHOW TABLES LIKE 'empresas_convenio'");
            $empresasConvenioExists = $stmt->fetch();

            $foreignKeyRef = $empresasConvenioExists ? 'empresas_convenio' : 'empresas';

            $createTableSQL = "
            CREATE TABLE `descuentos` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `titulo` varchar(255) NOT NULL,
                `descripcion` text,
                `empresa_oferente_id` int(11) NOT NULL,
                `codigo_descuento` varchar(50) DEFAULT NULL,
                `porcentaje_descuento` decimal(5,2) DEFAULT NULL,
                `monto_descuento` decimal(10,2) DEFAULT NULL,
                `fecha_inicio` date NOT NULL,
                `fecha_fin` date NOT NULL,
                `usos_maximos` int(11) DEFAULT NULL,
                `usos_actuales` int(11) DEFAULT 0,
                `estado` enum('activo','inactivo','expirado') DEFAULT 'activo',
                `accion_tipo` enum('link','telefono','email','whatsapp','mapa','ninguno') DEFAULT 'ninguno',
                `accion_valor` varchar(500) DEFAULT NULL,
                `accion_etiqueta` varchar(100) DEFAULT NULL,
                `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_empresa_oferente` (`empresa_oferente_id`),
                KEY `idx_estado` (`estado`),
                KEY `idx_fecha_fin` (`fecha_fin`),
                CONSTRAINT `fk_descuentos_empresa` FOREIGN KEY (`empresa_oferente_id`) REFERENCES `{$foreignKeyRef}` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            $pdo->exec($createTableSQL);
        }

        // Auto-migración: agregar columnas de acción si no existen
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM descuentos LIKE 'accion_tipo'")->fetch();
            if (!$cols) {
                $pdo->exec("ALTER TABLE descuentos
                    ADD COLUMN accion_tipo ENUM('link','telefono','email','whatsapp','mapa','ninguno') DEFAULT 'ninguno',
                    ADD COLUMN accion_valor VARCHAR(500) DEFAULT NULL,
                    ADD COLUMN accion_etiqueta VARCHAR(100) DEFAULT NULL");
                error_log('✅ Columnas accion_* agregadas a tabla descuentos');
            }
        } catch (PDOException $e) {
            error_log('⚠️ Auto-migración accion fields: ' . $e->getMessage());
        }
    } catch (PDOException $e) {
        error_log("Error configurando tabla descuentos: " . $e->getMessage());
        // Continuar sin foreign keys si hay problemas
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Obtener descuentos
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;

            if ($id) {
                // Determinar qué tabla de empresas usar
                $stmt = $pdo->query("SHOW TABLES LIKE 'empresas_convenio'");
                $empresasConvenioExists = $stmt->fetch();
                $empresaTable = $empresasConvenioExists ? 'empresas_convenio' : 'empresas';

                // Obtener un descuento específico
                $stmt = $pdo->prepare("SELECT * FROM descuentos WHERE id = ?");
                $stmt->execute([$id]);
                $descuento = $stmt->fetch();

                if ($descuento) {
                    // Si es una empresa en convenio, obtener datos adicionales
                    if ($descuento['tipo_empresa'] === 'convenio' && $descuento['empresa_oferente_id']) {
                        $empresaStmt = $pdo->prepare("
                            SELECT nombre as empresa_nombre_tabla, logo_url, sector, telefono, email
                            FROM {$empresaTable}
                            WHERE id = ?
                        ");
                        $empresaStmt->execute([$descuento['empresa_oferente_id']]);
                        $empresaData = $empresaStmt->fetch();

                        if ($empresaData) {
                            // Combinar datos
                            $descuento = array_merge($descuento, $empresaData);
                            // Usar el nombre de la tabla si no hay empresa_nombre en descuentos
                            if (empty($descuento['empresa_nombre'])) {
                                $descuento['empresa_nombre'] = $empresaData['empresa_nombre_tabla'];
                            }
                        }
                    } else {
                        // Para empresas externas, agregar campos faltantes
                        $descuento['logo_url'] = null;
                        $descuento['sector'] = 'Externo';
                        $descuento['telefono'] = null;
                        $descuento['email'] = null;
                    }
                }

                if (!$descuento) {
                    sendJsonResponse('Descuento no encontrado', false);
                }

                sendJsonResponse(['data' => $descuento]);

            } else {
                // Determinar qué tabla de empresas usar
                $stmt = $pdo->query("SHOW TABLES LIKE 'empresas_convenio'");
                $empresasConvenioExists = $stmt->fetch();
                $empresaTable = $empresasConvenioExists ? 'empresas_convenio' : 'empresas';

                // Obtener todos los descuentos con información de empresa
                $estado = isset($_GET['estado']) ? clean($_GET['estado']) : 'activo';
                $empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : null;
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
                $orderBy = isset($_GET['orderBy']) ? clean($_GET['orderBy']) : 'fecha_creacion';
                $order = isset($_GET['order']) ? strtoupper(clean($_GET['order'])) : 'DESC';
                $incluir_expirados = isset($_GET['incluir_expirados']) ? $_GET['incluir_expirados'] === '1' : false;

                // Validar orderBy
                $validOrderBy = ['fecha_creacion', 'fecha_fin', 'titulo', 'porcentaje_descuento', 'usos_actuales'];
                if (!in_array($orderBy, $validOrderBy)) {
                    $orderBy = 'fecha_creacion';
                }

                // Validar order
                if (!in_array($order, ['ASC', 'DESC'])) {
                    $order = 'DESC';
                }

                $sql = "
                    SELECT d.*,
                           COALESCE(d.empresa_nombre, e.nombre) as empresa_nombre,
                           e.logo_url,
                           COALESCE(e.sector, 'Externo') as sector,
                           e.telefono,
                           e.email,
                           CASE
                               WHEN d.fecha_fin < CURDATE() THEN 'expirado'
                               WHEN d.usos_maximos IS NOT NULL AND d.usos_actuales >= d.usos_maximos THEN 'agotado'
                               ELSE d.estado
                           END as estado_calculado
                    FROM descuentos d
                    LEFT JOIN {$empresaTable} e ON d.empresa_oferente_id = e.id AND d.tipo_empresa = 'convenio'
                    WHERE 1=1
                ";
                $params = [];

                // Filtro por estado (temporalmente simplificado para debug)
                if ($estado !== 'todos') {
                    if ($estado === 'vigente') {
                        // Solo verificar que esté activo, ignorar fechas y usos por ahora
                        $sql .= " AND d.estado = 'activo'";
                        // Comentado temporalmente para debug:
                        // $sql .= " AND d.fecha_fin >= CURDATE()";
                        // if (!$incluir_expirados) {
                        //     $sql .= " AND (d.usos_maximos IS NULL OR d.usos_actuales < d.usos_maximos)";
                        // }
                    } else {
                        $sql .= " AND d.estado = ?";
                        $params[] = $estado;
                    }
                }

                // Filtro por empresa
                if ($empresa_id) {
                    $sql .= " AND d.empresa_oferente_id = ?";
                    $params[] = $empresa_id;
                }

                $sql .= " ORDER BY $orderBy $order";

                if ($limit > 0) {
                    $sql .= " LIMIT " . $limit;
                }

                // Debug: log de la consulta SQL
                error_log("SQL Query: " . $sql);
                error_log("SQL Params: " . json_encode($params));

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $descuentos = $stmt->fetchAll();

                // Debug: log de resultados
                error_log("Descuentos encontrados: " . count($descuentos));

                // Procesar datos
                foreach ($descuentos as &$descuento) {
                    $descuento['id'] = intval($descuento['id']);
                    $descuento['empresa_oferente_id'] = intval($descuento['empresa_oferente_id']);
                    $descuento['porcentaje_descuento'] = floatval($descuento['porcentaje_descuento']);
                    $descuento['monto_descuento'] = floatval($descuento['monto_descuento']);
                    $descuento['usos_maximos'] = $descuento['usos_maximos'] ? intval($descuento['usos_maximos']) : null;
                    $descuento['usos_actuales'] = intval($descuento['usos_actuales']);

                    // Calcular porcentaje de uso
                    if ($descuento['usos_maximos']) {
                        $descuento['porcentaje_uso'] = round(($descuento['usos_actuales'] / $descuento['usos_maximos']) * 100, 1);
                    } else {
                        $descuento['porcentaje_uso'] = null;
                    }

                    // Verificar vigencia
                    $hoy = date('Y-m-d');
                    $descuento['vigente'] = ($descuento['fecha_fin'] >= $hoy && $descuento['estado'] === 'activo');
                    $descuento['dias_restantes'] = max(0, (strtotime($descuento['fecha_fin']) - strtotime($hoy)) / 86400);
                }

                // Obtener estadísticas
                $statsStmt = $pdo->query("
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN estado = 'activo' AND fecha_fin >= CURDATE() THEN 1 ELSE 0 END) as activos,
                        SUM(CASE WHEN fecha_fin < CURDATE() THEN 1 ELSE 0 END) as expirados,
                        COUNT(DISTINCT empresa_oferente_id) as empresas_participantes
                    FROM descuentos
                ");
                $stats = $statsStmt->fetch();

                sendJsonResponse([
                    'data' => $descuentos,
                    'total' => count($descuentos),
                    'stats' => $stats
                ]);
            }
            break;

        case 'POST':
            // Verificar si es una actualización (PUT disfrazado)
            if (isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
                goto handle_put;
            }

            // Verificar si es uso de descuento
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input && isset($input['action']) && $input['action'] === 'usar_descuento') {
                // Usar descuento
                $descuento_id = intval($input['descuento_id'] ?? 0);
                $usuario_id = intval($input['usuario_id'] ?? 0);

                if (!$descuento_id || !$usuario_id) {
                    sendJsonResponse('ID de descuento y usuario son requeridos', false);
                }

                // Verificar que el descuento existe y está vigente
                $stmt = $pdo->prepare("
                    SELECT d.*, e.nombre as empresa_nombre
                    FROM descuentos d
                    INNER JOIN empresas_convenio e ON d.empresa_oferente_id = e.id
                    WHERE d.id = ? AND d.estado = 'activo' AND d.fecha_fin >= CURDATE()
                ");
                $stmt->execute([$descuento_id]);
                $descuento = $stmt->fetch();

                if (!$descuento) {
                    sendJsonResponse('Descuento no encontrado o no vigente', false);
                }

                // Verificar límites de uso total
                if ($descuento['usos_maximos'] && $descuento['usos_actuales'] >= $descuento['usos_maximos']) {
                    sendJsonResponse('Este descuento ya alcanzó el límite máximo de usos', false);
                }

                // Crear tabla de usos si no existe
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS descuentos_usos (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        descuento_id INT NOT NULL,
                        usuario_id INT NOT NULL,
                        fecha_uso DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_descuento_usuario (descuento_id, usuario_id),
                        FOREIGN KEY (descuento_id) REFERENCES descuentos(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                } catch (PDOException $e) {
                    // Tabla ya existe, continuar
                }

                // Verificar si el usuario ya usó este descuento
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM descuentos_usos WHERE descuento_id = ? AND usuario_id = ?");
                $stmt->execute([$descuento_id, $usuario_id]);
                $yaUsado = $stmt->fetchColumn();

                if ($yaUsado > 0) {
                    sendJsonResponse('Ya usaste este código de descuento anteriormente', false);
                }

                // Registrar el uso
                $pdo->beginTransaction();
                try {
                    // Insertar registro de uso
                    $stmt = $pdo->prepare("INSERT INTO descuentos_usos (descuento_id, usuario_id) VALUES (?, ?)");
                    $stmt->execute([$descuento_id, $usuario_id]);

                    // Actualizar contador de usos
                    $stmt = $pdo->prepare("UPDATE descuentos SET usos_actuales = usos_actuales + 1 WHERE id = ?");
                    $stmt->execute([$descuento_id]);

                    $pdo->commit();

                    sendJsonResponse([
                        'message' => '¡Descuento registrado exitosamente!',
                        'data' => [
                            'descuento' => $descuento,
                            'codigo' => $descuento['codigo_descuento'],
                            'empresa' => $descuento['empresa_nombre']
                        ]
                    ]);

                } catch (Exception $e) {
                    $pdo->rollback();
                    sendJsonResponse('Error al registrar el uso del descuento', false);
                }

                break;
            }

            // Crear nuevo descuento
            $titulo = clean($_POST['titulo'] ?? '');
            $descripcion = clean($_POST['descripcion'] ?? '');
            $empresa_oferente_id = intval($_POST['empresa_oferente_id'] ?? 0);
            $codigo_descuento = clean($_POST['codigo_descuento'] ?? null);
            $porcentaje_descuento = isset($_POST['porcentaje_descuento']) ? floatval($_POST['porcentaje_descuento']) : null;
            $monto_descuento = isset($_POST['monto_descuento']) ? floatval($_POST['monto_descuento']) : null;
            $fecha_inicio = clean($_POST['fecha_inicio'] ?? '');
            $fecha_fin = clean($_POST['fecha_fin'] ?? '');
            $usos_maximos = isset($_POST['usos_maximos']) && $_POST['usos_maximos'] !== '' ? intval($_POST['usos_maximos']) : null;
            $estado = clean($_POST['estado'] ?? 'activo');

            // Campos del botón de acción
            $accionTiposValidos = ['link','telefono','email','whatsapp','mapa','ninguno'];
            $accion_tipo = clean($_POST['accion_tipo'] ?? 'ninguno');
            if (!in_array($accion_tipo, $accionTiposValidos)) $accion_tipo = 'ninguno';
            $accion_valor = $accion_tipo !== 'ninguno' ? clean($_POST['accion_valor'] ?? '') : null;
            $accion_valor = ($accion_valor === '') ? null : $accion_valor;
            $accion_etiqueta = $accion_tipo !== 'ninguno' ? clean($_POST['accion_etiqueta'] ?? '') : null;
            $accion_etiqueta = ($accion_etiqueta === '') ? null : $accion_etiqueta;

            // Obtener datos adicionales para validación
            $empresa_nombre = clean($_POST['empresa_nombre'] ?? '');
            $tipo_empresa = clean($_POST['tipo_empresa'] ?? 'convenio');


            // Validaciones ajustadas para soportar empresas externas
            if (empty($titulo) || empty($fecha_inicio) || empty($fecha_fin)) {
                sendJsonResponse('Título, fecha de inicio y fecha de fin son requeridos', false);
            }

            // Validar empresa según el tipo
            if ($tipo_empresa === 'convenio') {
                if ($empresa_oferente_id <= 0) {
                    sendJsonResponse('Debe seleccionar una empresa en convenio válida', false);
                }
            } else {
                // Para empresas externas
                if (empty($empresa_nombre)) {
                    sendJsonResponse('Debe especificar el nombre de la empresa', false);
                }
                // Establecer empresa_oferente_id como NULL para empresas externas
                $empresa_oferente_id = null;
            }

            if (!$porcentaje_descuento && !$monto_descuento) {
                sendJsonResponse('Debe especificar porcentaje de descuento o monto de descuento', false);
            }

            // Verificar que la empresa existe solo si es del tipo convenio
            if ($tipo_empresa === 'convenio' && $empresa_oferente_id) {
                $stmt = $pdo->query("SHOW TABLES LIKE 'empresas_convenio'");
                $empresasConvenioExists = $stmt->fetch();
                $empresaTable = $empresasConvenioExists ? 'empresas_convenio' : 'empresas';

                $empresaStmt = $pdo->prepare("SELECT id FROM {$empresaTable} WHERE id = ?");
                $empresaStmt->execute([$empresa_oferente_id]);
                if (!$empresaStmt->fetch()) {
                    sendJsonResponse('La empresa en convenio seleccionada no existe', false);
                }
            }

            $sql = "INSERT INTO descuentos (titulo, descripcion, empresa_oferente_id, empresa_nombre, tipo_empresa, codigo_descuento, porcentaje_descuento, monto_descuento, fecha_inicio, fecha_fin, usos_maximos, estado, accion_tipo, accion_valor, accion_etiqueta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$titulo, $descripcion, $empresa_oferente_id, $empresa_nombre, $tipo_empresa, $codigo_descuento, $porcentaje_descuento, $monto_descuento, $fecha_inicio, $fecha_fin, $usos_maximos, $estado, $accion_tipo, $accion_valor, $accion_etiqueta])) {
                $id = $pdo->lastInsertId();

                // Obtener el descuento recién creado con información de empresa
                if ($tipo_empresa === 'convenio' && $empresa_oferente_id) {
                    // Para empresas en convenio, obtener datos de la tabla de empresas
                    $getStmt = $pdo->prepare("
                        SELECT d.*, e.nombre as empresa_nombre_tabla, e.logo_url, e.sector
                        FROM descuentos d
                        INNER JOIN {$empresaTable} e ON d.empresa_oferente_id = e.id
                        WHERE d.id = ?
                    ");
                    $getStmt->execute([$id]);
                    $nuevoDescuento = $getStmt->fetch();

                    // Usar el nombre de la tabla si no hay empresa_nombre en descuentos
                    if (empty($nuevoDescuento['empresa_nombre'])) {
                        $nuevoDescuento['empresa_nombre'] = $nuevoDescuento['empresa_nombre_tabla'];
                    }
                } else {
                    // Para empresas externas, obtener solo datos del descuento
                    $getStmt = $pdo->prepare("
                        SELECT d.*, d.empresa_nombre
                        FROM descuentos d
                        WHERE d.id = ?
                    ");
                    $getStmt->execute([$id]);
                    $nuevoDescuento = $getStmt->fetch();

                    // Agregar campos faltantes para mantener compatibilidad
                    $nuevoDescuento['logo_url'] = null;
                    $nuevoDescuento['sector'] = 'Externo';
                }

                // ── Hook de correo: Nuevo Descuento (best-effort) ──────────
                try {
                    require_once __DIR__ . '/../utils/NotificationMailer.php';
                    $descNombre  = $nuevoDescuento['titulo'] ?? $titulo;
                    $empNombre   = $nuevoDescuento['empresa_nombre'] ?? $empresa_nombre ?? 'Empresa';
                    $porcentaje  = $nuevoDescuento['porcentaje_descuento'] ?? $porcentaje_descuento ?? '';
                    $fechaFin    = isset($nuevoDescuento['fecha_fin'])
                        ? date('d/m/Y', strtotime($nuevoDescuento['fecha_fin']))
                        : $fecha_fin;

                    NotificationMailer::dispatch(
                        'nuevo_descuento',
                        "Nuevo descuento disponible: $descNombre",
                        "La empresa «$empNombre» ha publicado un nuevo descuento.\n\n" .
                        "Descuento: $descNombre\n" .
                        ($porcentaje ? "Beneficio: $porcentaje% de descuento\n" : '') .
                        "Válido hasta: $fechaFin\n\n" .
                        "Ingresa a la intranet para ver todos los detalles y obtener tu código.",
                        $pdo
                    );
                } catch (Exception $emailEx) {
                    error_log('⚠️ [descuentos] Hook correo falló: ' . $emailEx->getMessage());
                }
                // ───────────────────────────────────────────────────────────

                sendJsonResponse([
                    'message' => 'Descuento creado exitosamente',
                    'data' => $nuevoDescuento
                ]);

            } else {
                sendJsonResponse('Error al crear el descuento', false);
            }
            break;

        case 'PUT':
        handle_put:
            // Actualizar descuento existente
            $id = intval($_POST['id'] ?? 0);
            $titulo = clean($_POST['titulo'] ?? '');
            $descripcion = clean($_POST['descripcion'] ?? '');
            $empresa_oferente_id = intval($_POST['empresa_oferente_id'] ?? 0);
            $codigo_descuento = clean($_POST['codigo_descuento'] ?? null);
            $porcentaje_descuento = isset($_POST['porcentaje_descuento']) ? floatval($_POST['porcentaje_descuento']) : null;
            $monto_descuento = isset($_POST['monto_descuento']) ? floatval($_POST['monto_descuento']) : null;
            $fecha_inicio = clean($_POST['fecha_inicio'] ?? '');
            $fecha_fin = clean($_POST['fecha_fin'] ?? '');
            $usos_maximos = isset($_POST['usos_maximos']) && $_POST['usos_maximos'] !== '' ? intval($_POST['usos_maximos']) : null;
            $estado = clean($_POST['estado'] ?? 'activo');

            // Campos del botón de acción
            $accionTiposValidos = ['link','telefono','email','whatsapp','mapa','ninguno'];
            $accion_tipo = clean($_POST['accion_tipo'] ?? 'ninguno');
            if (!in_array($accion_tipo, $accionTiposValidos)) $accion_tipo = 'ninguno';
            $accion_valor = $accion_tipo !== 'ninguno' ? clean($_POST['accion_valor'] ?? '') : null;
            $accion_valor = ($accion_valor === '') ? null : $accion_valor;
            $accion_etiqueta = $accion_tipo !== 'ninguno' ? clean($_POST['accion_etiqueta'] ?? '') : null;
            $accion_etiqueta = ($accion_etiqueta === '') ? null : $accion_etiqueta;

            // Obtener datos adicionales para validación
            $empresa_nombre = clean($_POST['empresa_nombre'] ?? '');
            $tipo_empresa = clean($_POST['tipo_empresa'] ?? 'convenio');

            if ($id <= 0 || empty($titulo)) {
                sendJsonResponse('ID y título son requeridos', false);
            }

            // Validar empresa según el tipo
            if ($tipo_empresa === 'convenio') {
                if ($empresa_oferente_id <= 0) {
                    sendJsonResponse('Debe seleccionar una empresa en convenio válida', false);
                }
            } else {
                // Para empresas externas
                if (empty($empresa_nombre)) {
                    sendJsonResponse('Debe especificar el nombre de la empresa', false);
                }
                // Establecer empresa_oferente_id como NULL para empresas externas
                $empresa_oferente_id = null;
            }

            // Verificar que el descuento existe
            $checkStmt = $pdo->prepare("SELECT id FROM descuentos WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                sendJsonResponse('Descuento no encontrado', false);
            }

            $sql = "UPDATE descuentos SET titulo = ?, descripcion = ?, empresa_oferente_id = ?, empresa_nombre = ?, tipo_empresa = ?, codigo_descuento = ?, porcentaje_descuento = ?, monto_descuento = ?, fecha_inicio = ?, fecha_fin = ?, usos_maximos = ?, estado = ?, accion_tipo = ?, accion_valor = ?, accion_etiqueta = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$titulo, $descripcion, $empresa_oferente_id, $empresa_nombre, $tipo_empresa, $codigo_descuento, $porcentaje_descuento, $monto_descuento, $fecha_inicio, $fecha_fin, $usos_maximos, $estado, $accion_tipo, $accion_valor, $accion_etiqueta, $id])) {
                // Obtener descuento actualizado según el tipo
                if ($tipo_empresa === 'convenio' && $empresa_oferente_id) {
                    // Determinar qué tabla de empresas usar
                    $stmt = $pdo->query("SHOW TABLES LIKE 'empresas_convenio'");
                    $empresasConvenioExists = $stmt->fetch();
                    $empresaTable = $empresasConvenioExists ? 'empresas_convenio' : 'empresas';

                    // Para empresas en convenio, obtener datos de la tabla de empresas
                    $getStmt = $pdo->prepare("
                        SELECT d.*, e.nombre as empresa_nombre_tabla, e.logo_url, e.sector
                        FROM descuentos d
                        INNER JOIN {$empresaTable} e ON d.empresa_oferente_id = e.id
                        WHERE d.id = ?
                    ");
                    $getStmt->execute([$id]);
                    $descuentoActualizado = $getStmt->fetch();

                    // Usar el nombre de la tabla si no hay empresa_nombre en descuentos
                    if (empty($descuentoActualizado['empresa_nombre'])) {
                        $descuentoActualizado['empresa_nombre'] = $descuentoActualizado['empresa_nombre_tabla'];
                    }
                } else {
                    // Para empresas externas, obtener solo datos del descuento
                    $getStmt = $pdo->prepare("
                        SELECT d.*, d.empresa_nombre
                        FROM descuentos d
                        WHERE d.id = ?
                    ");
                    $getStmt->execute([$id]);
                    $descuentoActualizado = $getStmt->fetch();

                    // Agregar campos faltantes para mantener compatibilidad
                    $descuentoActualizado['logo_url'] = null;
                    $descuentoActualizado['sector'] = 'Externo';
                }

                sendJsonResponse([
                    'message' => 'Descuento actualizado exitosamente',
                    'data' => $descuentoActualizado
                ]);
            } else {
                sendJsonResponse('Error al actualizar el descuento', false);
            }
            break;

        case 'DELETE':
            // Eliminar descuento
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if ($id <= 0) {
                sendJsonResponse('ID es requerido', false);
            }

            // Verificar que el descuento existe
            $checkStmt = $pdo->prepare("SELECT id FROM descuentos WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                sendJsonResponse('Descuento no encontrado', false);
            }

            // Eliminar de la base de datos
            $deleteStmt = $pdo->prepare("DELETE FROM descuentos WHERE id = ?");

            if ($deleteStmt->execute([$id])) {
                sendJsonResponse(['message' => 'Descuento eliminado exitosamente']);
            } else {
                sendJsonResponse('Error al eliminar el descuento', false);
            }
            break;

        default:
            sendJsonResponse('Método no permitido: ' . $method, false);
    }

} catch (PDOException $e) {
    error_log("Error en descuentos API (PDO): " . $e->getMessage());
    sendJsonResponse('Error de base de datos: ' . $e->getMessage(), false);

} catch (Exception $e) {
    error_log("Error general en descuentos API: " . $e->getMessage());
    sendJsonResponse('Error del servidor: ' . $e->getMessage(), false);
}
?>