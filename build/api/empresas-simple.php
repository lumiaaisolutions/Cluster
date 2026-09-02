<?php
/**
 * API Simple para Empresas - Compatible con empresas-convenio.html
 * Este archivo actúa como un wrapper simplificado para la API principal
 */

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
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
    error_log("Error en rate limiter (empresas-simple): " . $e->getMessage());
}
// ============================================

// Definir acceso
define('CLAUT_ACCESS', true);

// Incluir configuración de base de datos
require_once __DIR__ . '/../config/database.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'listar';
    $db = Database::getInstance();
    
    switch ($action) {
        case 'listar':
            listarEmpresas($db);
            break;
            
        case 'obtener':
            obtenerEmpresa($db);
            break;
            
        case 'crear':
            crearEmpresa($db);
            break;
            
        case 'actualizar':
            actualizarEmpresa($db);
            break;
            
        case 'eliminar':
            eliminarEmpresa($db);
            break;
            
        default:
            sendResponse(false, 'Acción no válida', null, 400);
    }
    
} catch (Exception $e) {
    error_log("Error en empresas-simple.php: " . $e->getMessage());
    sendResponse(false, 'Error interno del servidor', null, 500);
}

/**
 * Listar empresas — administradores ven TODAS, otros solo ven activas.
 * FIX (2026-04-13): Se eliminó WHERE e.activo = 1 que bloqueaba
 * la visibilidad de registros en el panel administrativo.
 */
function listarEmpresas($db) {
    try {
        // Verificar si el usuario es administrador para aplicar o no el filtro
        // Iniciar sesión segura usando la configuración centralizada del sistema
        if (!defined('CLAUT_ACCESS')) define('CLAUT_ACCESS', true);
        require_once dirname(__DIR__) . '/config/session-config.php';
        SessionConfig::init();
        // Detección omnidireccional de rol (cubre todas las variantes del sistema)
        $rolUsuario = strtolower(
            $_SESSION['user_rol'] ?? 
            $_SESSION['usuario_rol'] ?? 
            $_SESSION['rol'] ?? 
            $_SESSION['user_role'] ?? 
            $_SESSION['usuario_tipo'] ?? 
            ''
        );
        $esAdmin = in_array($rolUsuario, ['admin', 'administrador'], true);

        // Construir cláusula WHERE: admins ven todo, otros solo activos Y autorizados (incluyendo legados NULL)
        $whereClause = $esAdmin ? '' : 'WHERE e.activo = 1 AND (e.autoriza_directorio = 1 OR e.autoriza_directorio IS NULL)';

        // Query para obtener empresas con todos los campos necesarios
        // FIX (2026-04-13): El campo 'direccion' se omite para no-admins por privacidad
        $direccionSelect = $esAdmin ? 'e.direccion,' : "'' AS direccion,";

        $sql = "SELECT 
                    e.id,
                    COALESCE(e.nombre, e.nombre_empresa) as nombre,
                    e.nombre_empresa,
                    e.descripcion,
                    e.logo_url,
                    e.sitio_web,
                    e.telefono,
                    e.email,
                    $direccionSelect
                    COALESCE(e.categoria, e.sector) as sector,
                    e.estado,
                    e.descuento,
                    e.descuento_porcentaje,
                    e.fecha_convenio,
                    e.beneficios,
                    e.condiciones,
                    e.contacto_nombre,
                    e.contacto_cargo,
                    e.contacto_telefono,
                    e.contacto_email,
                    e.activo,
                    e.destacado,
                    e.fecha_inicio_convenio,
                    e.fecha_fin_convenio,
                    e.created_at,
                    e.updated_at,
                    e.admin_usuario_id,
                    CONCAT(u.nombre, ' ', u.apellidos) AS admin_nombre,
                    e.municipio,
                    e.entidad_federativa,
                    e.certificaciones,
                    e.exporta,
                    e.redes_fb,
                    e.redes_x,
                    e.redes_linkedin,
                    e.redes_instagram,
                    e.usuario_registro_nombre,
                    e.usuario_registro_apellido,
                    e.departamento,
                    e.cargo,
                    e.logo_archivo,
                    e.convenio_descripcion,
                    e.vigencia_inicio,
                    e.vigencia_fin,
                    e.contacto_movil,
                    e.autoriza_directorio
                FROM empresas_convenio e
                LEFT JOIN usuarios_perfil u ON e.admin_usuario_id = u.id
                {$whereClause}
                ORDER BY e.destacado DESC, COALESCE(e.nombre, e.nombre_empresa) ASC";
        
        error_log("DEBUG ELITE: WHERE clause = '$whereClause'");
        error_log("DEBUG ELITE: SQL Query = " . substr($sql, 0, 200) . "...");
        
        $empresas = $db->select($sql);
        
        // Formatear datos para el frontend
        $empresasFormateadas = array_map(function($empresa) {
            return [
                'id' => $empresa['id'],
                'nombre' => $empresa['nombre'] ?: $empresa['nombre_empresa'],
                'nombre_empresa' => $empresa['nombre_empresa'],
                'descripcion' => $empresa['descripcion'] ?: '',
                'logo_url' => $empresa['logo_url'] ?: '',
                'sitio_web' => $empresa['sitio_web'] ?: '',
                'telefono' => $empresa['telefono'] ?: '',
                'email' => $empresa['email'] ?: '',
                'direccion' => $empresa['direccion'] ?: '',
                'sector' => $empresa['sector'] ?: 'General',
                'categoria' => $empresa['sector'] ?: 'General',
                'estado' => $empresa['estado'] ?: 'activa',
                'descuento_porcentaje' => $empresa['descuento_porcentaje'] ?? $empresa['descuento'] ?? 0,
                'fecha_convenio' => $empresa['fecha_convenio'] ?: '',
                'beneficios' => $empresa['beneficios'] ?: '',
                'condiciones' => $empresa['condiciones'] ?: '',
                'contacto_nombre' => $empresa['contacto_nombre'] ?: '',
                'contacto_persona' => $empresa['contacto_nombre'] ?: '',
                'contacto_cargo' => $empresa['contacto_cargo'] ?: '',
                'contacto_telefono' => $empresa['contacto_telefono'] ?: '',
                'contacto_email' => $empresa['contacto_email'] ?: '',
                'admin_usuario_id' => $empresa['admin_usuario_id'],
                'admin_nombre' => $empresa['admin_nombre'],
                'activo' => (bool)$empresa['activo'],
                'destacado' => (bool)$empresa['destacado'],
                'fecha_inicio_convenio' => $empresa['fecha_inicio_convenio'],
                'fecha_fin_convenio' => $empresa['fecha_fin_convenio'],
                'municipio' => $empresa['municipio'] ?: '',
                'entidad_federativa' => $empresa['entidad_federativa'] ?: '',
                'certificaciones' => $empresa['certificaciones'] ?: '',
                'exporta' => (bool)($empresa['exporta'] ?? false),
                'redes_fb' => $empresa['redes_fb'] ?: '',
                'redes_x' => $empresa['redes_x'] ?: '',
                'redes_linkedin' => $empresa['redes_linkedin'] ?: '',
                'redes_instagram' => $empresa['redes_instagram'] ?: '',
                'autoriza_directorio' => ($empresa['autoriza_directorio'] === null || $empresa['autoriza_directorio'] == 1),
                'usuario_registro_nombre' => $empresa['usuario_registro_nombre'] ?: '',
                'usuario_registro_apellido' => $empresa['usuario_registro_apellido'] ?: '',
                'departamento' => $empresa['departamento'] ?: '',
                'cargo' => $empresa['cargo'] ?: '',
                'logo_archivo' => $empresa['logo_archivo'] ?: '',
                // Convenios Clúster
                'convenio_descripcion' => $empresa['convenio_descripcion'] ?: '',
                'vigencia_inicio' => $empresa['vigencia_inicio'] ?: '',
                'vigencia_fin' => $empresa['vigencia_fin'] ?: '',
                'contacto_movil' => $empresa['contacto_movil'] ?: ''
            ];
        }, $empresas);
        
        sendResponse(true, 'Empresas obtenidas correctamente', [
            'empresas' => $empresasFormateadas,
            'total' => count($empresasFormateadas)
        ]);
        
    } catch (Exception $e) {
        error_log("Error listando empresas: " . $e->getMessage());
        sendResponse(false, 'Error al obtener empresas: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Obtener una empresa específica
 */
function obtenerEmpresa($db) {
    try {
        $id = $_GET['id'] ?? null;
        
        // OPCIONAL: Validación adicional (no altera funcionamiento)
        if (file_exists(dirname(__DIR__) . '/middleware/api-validator.php')) {
            require_once dirname(__DIR__) . '/middleware/api-validator.php';
            
            $validation = ApiValidator::validateField($id, 'required|int|min:1', 'id');
            
            if (!$validation['valid']) {
                sendResponse(false, $validation['error'], null, 400);
            }
        }
        
        // LÓGICA ORIGINAL
        if (!$id) {
            sendResponse(false, 'ID de empresa requerido', null, 400);
        }
        
        $sql = "SELECT 
                    e.id,
                    COALESCE(e.nombre, e.nombre_empresa) as nombre,
                    e.nombre_empresa,
                    e.descripcion,
                    e.logo_url,
                    e.sitio_web,
                    e.telefono,
                    e.email,
                    e.direccion,
                    COALESCE(e.categoria, e.sector) as sector,
                    e.descuento,
                    e.descuento_porcentaje,
                    e.beneficios,
                    e.contacto_nombre,
                    e.contacto_cargo,
                    e.contacto_telefono,
                    e.contacto_email,
                    e.activo,
                    e.destacado,
                    e.estado,
                    e.fecha_convenio,
                    e.condiciones,
                    e.fecha_inicio_convenio,
                    e.fecha_fin_convenio,
                    e.admin_usuario_id,
                    CONCAT(u.nombre, ' ', u.apellidos) AS admin_nombre,
                    e.municipio,
                    e.certificaciones,
                    e.exporta,
                    e.redes_fb,
                    e.redes_x,
                    e.redes_linkedin,
                    e.redes_instagram,
                    e.usuario_registro_nombre,
                    e.usuario_registro_apellido,
                    e.departamento,
                    e.cargo,
                    e.logo_archivo,
                    e.convenio_descripcion,
                    e.vigencia_inicio,
                    e.vigencia_fin,
                    e.contacto_movil,
                    e.autoriza_directorio
                FROM empresas_convenio e
                LEFT JOIN usuarios_perfil u ON e.admin_usuario_id = u.id
                WHERE e.id = ? AND e.activo = 1";
        
        $empresa = $db->selectOne($sql, [$id]);
        
        if (!$empresa) {
            sendResponse(false, 'Empresa no encontrada', null, 404);
        }
        
        // Formatear datos
        $empresaFormateada = [
            'id' => $empresa['id'],
            'nombre' => $empresa['nombre'] ?: $empresa['nombre_empresa'],
            'nombre_empresa' => $empresa['nombre_empresa'],
            'descripcion' => $empresa['descripcion'] ?: 'Sin descripción disponible',
            'logo_url' => $empresa['logo_url'] ?: '',
            'sitio_web' => $empresa['sitio_web'] ?: '#',
            'telefono' => $empresa['telefono'] ?: 'No disponible',
            'email' => $empresa['email'] ?: 'No disponible',
            'direccion' => $empresa['direccion'] ?: '',
            'sector' => $empresa['sector'] ?: 'General',
            'descuento' => $empresa['descuento'] ?: $empresa['descuento_porcentaje'] ?: 0,
            'beneficios' => $empresa['beneficios'] ?: '',
            'contacto_nombre' => $empresa['contacto_nombre'] ?: 'No especificado',
            'contacto_cargo' => $empresa['contacto_cargo'] ?: '',
            'contacto_telefono' => $empresa['contacto_telefono'] ?: 'No disponible',
            'contacto_email' => $empresa['contacto_email'] ?: 'No disponible',
            'admin_usuario_id' => $empresa['admin_usuario_id'],
            'admin_nombre' => $empresa['admin_nombre'],
            'activo' => (bool)$empresa['activo'],
            'destacado' => (bool)$empresa['destacado'],
            'estado' => $empresa['estado'] ?: 'activa',
            'fecha_convenio' => $empresa['fecha_convenio'] ?: '',
            'condiciones' => $empresa['condiciones'] ?: '',
            'municipio' => $empresa['municipio'] ?: '',
            'certificaciones' => $empresa['certificaciones'] ?: '',
            'exporta' => (bool)($empresa['exporta'] ?? false),
            'redes_fb' => $empresa['redes_fb'] ?: '',
            'redes_x' => $empresa['redes_x'] ?: '',
            'redes_linkedin' => $empresa['redes_linkedin'] ?: '',
            'redes_instagram' => $empresa['redes_instagram'] ?: '',
            'usuario_registro_nombre' => $empresa['usuario_registro_nombre'] ?: '',
            'usuario_registro_apellido' => $empresa['usuario_registro_apellido'] ?: '',
            // NUEVOS CAMPOS DEL DIRECTORIO AVANZADO
            'departamento' => $empresa['departamento'] ?: '',
            'cargo' => $empresa['cargo'] ?: '',
            'logo_archivo' => $empresa['logo_archivo'] ?: '',
            'convenio_descripcion' => $empresa['convenio_descripcion'] ?: '',
            'vigencia_inicio' => $empresa['vigencia_inicio'] ?: '',
            'vigencia_fin' => $empresa['vigencia_fin'] ?: '',
            'contacto_movil' => $empresa['contacto_movil'] ?: '',
            'autoriza_directorio' => (bool)($empresa['autoriza_directorio'] ?? false)
        ];
        
        sendResponse(true, 'Empresa encontrada', $empresaFormateada);
        
    } catch (Exception $e) {
        error_log("Error obteniendo empresa: " . $e->getMessage());
        sendResponse(false, 'Error al obtener empresa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Crear nueva empresa
 */
function crearEmpresa($db) {
    try {
        // Recopilar y sanear datos del POST
        $nombre = trim($_POST['nombre'] ?? '');
        $sector = trim($_POST['sector'] ?? 'General');
        $estado = trim($_POST['estado'] ?? 'activa');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $sitio_web = trim($_POST['sitio_web'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descuento_porcentaje = isset($_POST['descuento_porcentaje']) && $_POST['descuento_porcentaje'] !== ''
            ? floatval($_POST['descuento_porcentaje']) : 0;
        $fecha_convenio_raw = trim($_POST['fecha_convenio'] ?? '');
        $fecha_convenio = ($fecha_convenio_raw !== '' && strtotime($fecha_convenio_raw)) ? $fecha_convenio_raw : null;
        $beneficios = trim($_POST['beneficios'] ?? '');
        $condiciones = trim($_POST['condiciones'] ?? '');
        $contacto_nombre = trim($_POST['contacto_persona'] ?? '');
        $contacto_telefono = trim($_POST['contacto_telefono'] ?? '');
        $contacto_email = trim($_POST['contacto_email'] ?? '');
        $logo_url = trim($_POST['logo_url'] ?? '');
        $admin_usuario_id = !empty($_POST['admin_usuario_id']) ? intval($_POST['admin_usuario_id']) : null;
        $municipio = trim($_POST['municipio'] ?? '');
        $entidad_federativa = trim($_POST['entidad_federativa'] ?? '');
        $certificaciones = trim($_POST['certificaciones'] ?? '');
        $exporta = isset($_POST['exporta']) ? (int)(bool)$_POST['exporta'] : 0;
        $redes_fb = trim($_POST['redes_fb'] ?? '');
        $redes_x = trim($_POST['redes_x'] ?? '');
        $redes_linkedin = trim($_POST['redes_linkedin'] ?? '');
        $redes_instagram = trim($_POST['redes_instagram'] ?? '');
        $usuario_registro_nombre = trim($_POST['usuario_registro_nombre'] ?? '');
        $usuario_registro_apellido = trim($_POST['usuario_registro_apellido'] ?? '');
        $departamento = trim($_POST['departamento'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $logo_archivo = trim($_POST['logo_archivo'] ?? '');
        $autoriza_directorio = isset($_POST['autoriza_directorio']) ? (int)(bool)$_POST['autoriza_directorio'] : 0;
        // Convenios Clúster
        $convenio_descripcion = trim($_POST['convenio_descripcion'] ?? '');
        $vigencia_inicio_raw = trim($_POST['vigencia_inicio'] ?? '');
        $vigencia_inicio = ($vigencia_inicio_raw !== '' && strtotime($vigencia_inicio_raw)) ? $vigencia_inicio_raw : null;
        $vigencia_fin_raw = trim($_POST['vigencia_fin'] ?? '');
        $vigencia_fin = ($vigencia_fin_raw !== '' && strtotime($vigencia_fin_raw)) ? $vigencia_fin_raw : null;
        $contacto_movil = trim($_POST['contacto_movil'] ?? '');
        $contacto_cargo = trim($_POST['contacto_cargo'] ?? '');

        // Validar campos requeridos
        if (empty($nombre)) {
            sendResponse(false, 'El nombre de la empresa es requerido', null, 400);
        }
        
        // Preparar SQL de inserción
        $sql = "INSERT INTO empresas_convenio (
                    nombre, sector, estado, email, telefono, sitio_web, direccion,
                    descripcion, descuento_porcentaje, fecha_convenio, beneficios,
                    condiciones, contacto_nombre, contacto_cargo, contacto_telefono, contacto_email,
                    logo_url, admin_usuario_id, entidad_federativa, municipio, certificaciones, exporta,
                    redes_fb, redes_x, redes_linkedin, redes_instagram,
                    autoriza_directorio,
                    usuario_registro_nombre, usuario_registro_apellido, departamento, cargo, logo_archivo,
                    convenio_descripcion, vigencia_inicio, vigencia_fin, contacto_movil,
                    activo, fecha_registro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        
        $params = [
            $nombre, $sector, $estado, $email, $telefono, $sitio_web, $direccion,
            $descripcion, $descuento_porcentaje, $fecha_convenio, $beneficios,
            $condiciones, $contacto_nombre, $contacto_cargo, $contacto_telefono, $contacto_email,
            $logo_url, $admin_usuario_id, $entidad_federativa, $municipio, $certificaciones, $exporta,
            $redes_fb, $redes_x, $redes_linkedin, $redes_instagram,
            $autoriza_directorio,
            $usuario_registro_nombre, $usuario_registro_apellido, $departamento, $cargo, $logo_archivo,
            $convenio_descripcion, $vigencia_inicio, $vigencia_fin, $contacto_movil
        ];
        
        $empresaId = $db->insert($sql, $params);
        
        if ($empresaId) {
            // ── Hook de correo: Nueva Empresa Registrada (best-effort) ──
            try {
                require_once dirname(__DIR__) . '/utils/NotificationMailer.php';
                $db2 = Database::getInstance()->getConnection();
                NotificationMailer::dispatch(
                    'nueva_empresa',
                    "Nueva empresa registrada: $nombre",
                    "Se ha registrado una nueva empresa en el sistema y requiere revisión.\n\n" .
                    "Empresa: $nombre\n" .
                    "Sector: $sector\n" .
                    ($email ? "Email: $email\n" : '') .
                    ($telefono ? "Teléfono: $telefono\n" : '') .
                    "\nAccede al Panel de Administración para revisar y activar la empresa.",
                    $db2
                );
            } catch (Exception $emailEx) {
                error_log('⚠️ [empresas-simple] Hook correo falló: ' . $emailEx->getMessage());
            }
            // ───────────────────────────────────────────────────────────

            // Recargar lista de empresas
            listarEmpresas($db);

        } else {
            sendResponse(false, 'Error al crear la empresa', null, 500);
        }
        
    } catch (Exception $e) {
        error_log("Error creando empresa: " . $e->getMessage());
        sendResponse(false, 'Error al crear empresa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Actualizar empresa existente
 */
function actualizarEmpresa($db) {
    try {
        // Recopilar datos del POST
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            sendResponse(false, 'ID de empresa requerido', null, 400);
        }
        
        $nombre = trim($_POST['nombre'] ?? '');
        $sector = trim($_POST['sector'] ?? 'General');
        $estado = trim($_POST['estado'] ?? 'activa');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $sitio_web = trim($_POST['sitio_web'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $descuento_porcentaje = isset($_POST['descuento_porcentaje']) && $_POST['descuento_porcentaje'] !== ''
            ? floatval($_POST['descuento_porcentaje']) : 0;
        $fecha_convenio_raw = trim($_POST['fecha_convenio'] ?? '');
        $fecha_convenio = ($fecha_convenio_raw !== '' && strtotime($fecha_convenio_raw)) ? $fecha_convenio_raw : null;
        $beneficios = trim($_POST['beneficios'] ?? '');
        $condiciones = trim($_POST['condiciones'] ?? '');
        $contacto_nombre = trim($_POST['contacto_persona'] ?? '');
        $contacto_telefono = trim($_POST['contacto_telefono'] ?? '');
        $contacto_email = trim($_POST['contacto_email'] ?? '');
        $logo_url = trim($_POST['logo_url'] ?? '');
        $admin_usuario_id = !empty($_POST['admin_usuario_id']) ? intval($_POST['admin_usuario_id']) : null;
        $municipio = trim($_POST['municipio'] ?? '');
        $entidad_federativa = trim($_POST['entidad_federativa'] ?? '');
        $certificaciones = trim($_POST['certificaciones'] ?? '');
        $exporta = isset($_POST['exporta']) ? (int)(bool)$_POST['exporta'] : 0;
        $redes_fb = trim($_POST['redes_fb'] ?? '');
        $redes_x = trim($_POST['redes_x'] ?? '');
        $redes_linkedin = trim($_POST['redes_linkedin'] ?? '');
        $redes_instagram = trim($_POST['redes_instagram'] ?? '');
        $usuario_registro_nombre = trim($_POST['usuario_registro_nombre'] ?? '');
        $usuario_registro_apellido = trim($_POST['usuario_registro_apellido'] ?? '');
        $departamento = trim($_POST['departamento'] ?? '');
        $cargo = trim($_POST['cargo'] ?? '');
        $logo_archivo = trim($_POST['logo_archivo'] ?? '');
        // Convenios Clúster
        $convenio_descripcion = trim($_POST['convenio_descripcion'] ?? '');
        $vigencia_inicio_raw = trim($_POST['vigencia_inicio'] ?? '');
        $vigencia_inicio = ($vigencia_inicio_raw !== '' && strtotime($vigencia_inicio_raw)) ? $vigencia_inicio_raw : null;
        $vigencia_fin_raw = trim($_POST['vigencia_fin'] ?? '');
        $vigencia_fin = ($vigencia_fin_raw !== '' && strtotime($vigencia_fin_raw)) ? $vigencia_fin_raw : null;
        $contacto_movil = trim($_POST['contacto_movil'] ?? '');
        $contacto_cargo = trim($_POST['contacto_cargo'] ?? '');
        $autoriza_directorio = isset($_POST['autoriza_directorio']) ? (int)(bool)$_POST['autoriza_directorio'] : 0;

        // Validar que la empresa existe
        $empresaExistente = $db->selectOne("SELECT id FROM empresas_convenio WHERE id = ?", [$id]);
        if (!$empresaExistente) {
            sendResponse(false, 'Empresa no encontrada', null, 404);
        }
        
        // Preparar SQL de actualización
        $sql = "UPDATE empresas_convenio SET 
                    nombre = ?,
                    sector = ?,
                    estado = ?,
                    email = ?,
                    telefono = ?,
                    sitio_web = ?,
                    direccion = ?,
                    descripcion = ?,
                    descuento_porcentaje = ?,
                    fecha_convenio = ?,
                    beneficios = ?,
                    condiciones = ?,
                    contacto_nombre = ?,
                    contacto_cargo = ?,
                    contacto_telefono = ?,
                    contacto_email = ?,
                    logo_url = ?,
                    admin_usuario_id = ?,
                    entidad_federativa = ?,
                    municipio = ?,
                    certificaciones = ?,
                    exporta = ?,
                    redes_fb = ?,
                    redes_x = ?,
                    redes_linkedin = ?,
                    redes_instagram = ?,
                    autoriza_directorio = ?,
                    usuario_registro_nombre = ?,
                    usuario_registro_apellido = ?,
                    departamento = ?,
                    cargo = ?,
                    logo_archivo = ?,
                    convenio_descripcion = ?,
                    vigencia_inicio = ?,
                    vigencia_fin = ?,
                    contacto_movil = ?,
                    updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $nombre, $sector, $estado, $email, $telefono, $sitio_web, $direccion,
            $descripcion, $descuento_porcentaje, $fecha_convenio, $beneficios,
            $condiciones, $contacto_nombre, $contacto_cargo, $contacto_telefono, $contacto_email,
            $logo_url, $admin_usuario_id, $entidad_federativa, $municipio, $certificaciones, $exporta,
            $redes_fb, $redes_x, $redes_linkedin, $redes_instagram,
            $autoriza_directorio,
            $usuario_registro_nombre, $usuario_registro_apellido, $departamento, $cargo, $logo_archivo,
            $convenio_descripcion, $vigencia_inicio, $vigencia_fin, $contacto_movil,
            $id
        ];
        
        $rowsAffected = $db->update($sql, $params);
        
        if ($rowsAffected !== false) {
            // Recargar lista de empresas
            listarEmpresas($db);
        } else {
            sendResponse(false, 'Error al actualizar la empresa', null, 500);
        }
        
    } catch (Exception $e) {
        error_log("Error actualizando empresa: " . $e->getMessage());
        sendResponse(false, 'Error al actualizar empresa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Eliminar empresa (soft delete - marca activo = 0)
 */
function eliminarEmpresa($db) {
    try {
        $id = $_POST['id'] ?? null;

        if (!$id) {
            sendResponse(false, 'ID de empresa requerido', null, 400);
        }

        $id = intval($id);

        $empresaExistente = $db->selectOne("SELECT id, nombre FROM empresas_convenio WHERE id = ?", [$id]);
        if (!$empresaExistente) {
            sendResponse(false, 'Empresa no encontrada', null, 404);
        }

        $rowsAffected = $db->delete(
            "DELETE FROM empresas_convenio WHERE id = ?",
            [$id]
        );

        if ($rowsAffected !== false) {
            // Auditoría
            if (file_exists(__DIR__ . '/../utils/AuditLogger.php')) {
                require_once __DIR__ . '/../utils/AuditLogger.php';
                AuditLogger::log('empresa.eliminar', 'empresa', $id, [
                    'nombre' => $empresaExistente['nombre'] ?? null,
                ]);
            }
            sendResponse(true, 'Empresa eliminada físicamente de la base de datos', ['id' => $id]);
        } else {
            sendResponse(false, 'Error al eliminar la empresa', null, 500);
        }

    } catch (Exception $e) {
        error_log("Error eliminando empresa: " . $e->getMessage());
        sendResponse(false, 'Error al eliminar empresa: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Enviar respuesta JSON
 */
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
?>
