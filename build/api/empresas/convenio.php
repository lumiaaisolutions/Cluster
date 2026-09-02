<?php
/**
 * API Endpoint - Empresas en Convenio
 * Maneja operaciones específicas para empresas en convenio
 */

define('CLAUT_ACCESS', true);
require_once '../includes/config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    
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
    error_log("Error en API empresas/convenio: " . $e->getMessage());
    jsonError('Error interno del servidor: ' . $e->getMessage(), 500);
}

function handleGet($db) {
    $id = $_GET['id'] ?? null;
    $limit = $_GET['limit'] ?? null;
    $includeStats = isset($_GET['include_stats']) && $_GET['include_stats'] === 'true';
    
    if ($id) {
        // Obtener empresa específica con estadísticas
        $sql = "SELECT * FROM vista_empresas_convenio WHERE id = ?";
        $empresa = $db->selectOne($sql, [$id]);
        
        if (!$empresa) {
            jsonError('Empresa no encontrada', 404);
        }
        
        jsonResponse($empresa);
        
    } else {
        // Obtener todas las empresas en convenio
        try {
            // Intentar usar la vista optimizada
            $sql = "SELECT * FROM vista_empresas_convenio ORDER BY nombre";
            
            if ($limit) {
                $sql .= " LIMIT " . (int)$limit;
            }
            
            $empresas = $db->select($sql);
            
            if ($includeStats) {
                // Incluir estadísticas generales
                $stats = [
                    'total_empresas' => count($empresas),
                    'total_empleados' => array_sum(array_column($empresas, 'total_empleados')),
                    'total_comites' => $db->selectOne("SELECT COUNT(*) as count FROM comites WHERE estado = 'activo'")['count'],
                    'empresas_activas' => count(array_filter($empresas, function($e) { return $e['estado'] === 'activa'; }))
                ];
                
                jsonResponse([
                    'empresas' => $empresas,
                    'estadisticas' => $stats
                ]);
            } else {
                jsonResponse($empresas);
            }
            
        } catch (Exception $e) {
            // Si falla la vista, usar consulta básica
            $sql = "SELECT 
                        e.*, 
                        COUNT(DISTINCT u.id) as total_empleados,
                        COUNT(DISTINCT cm.comite_id) as comites_participando
                    FROM empresas_convenio e
                    LEFT JOIN usuarios u ON e.id = u.empresa_id AND u.estado = 'activo'
                    LEFT JOIN comite_miembros cm ON u.id = cm.usuario_id AND cm.estado = 'activo'
                    WHERE e.estado = 'activa'
                    GROUP BY e.id
                    ORDER BY e.nombre";
            
            if ($limit) {
                $sql .= " LIMIT " . (int)$limit;
            }
            
            $empresas = $db->select($sql);
            jsonResponse($empresas);
        }
    }
}

function handlePost($db) {
    // Verificar autenticación
    $userPayload = requireAuth();
    
    // Obtener datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        jsonError('Datos JSON inválidos', 400);
    }
    
    // Validar campos requeridos
    $nombre = sanitizeInput($data['nombre'] ?? '');
    $descripcion = sanitizeInput($data['descripcion'] ?? '');
    $website = sanitizeInput($data['website'] ?? '');
    $telefono = sanitizeInput($data['telefono'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $direccion = sanitizeInput($data['direccion'] ?? '');
    $logo = sanitizeInput($data['logo'] ?? '');
    
    if (empty($nombre)) {
        jsonError('El nombre de la empresa es requerido', 400);
    }
    
    // Insertar empresa
    $sql = "INSERT INTO empresas_convenio (nombre, descripcion, website, telefono, email, direccion, logo, estado, fecha_registro) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'activa', NOW())";
    
    $empresaId = $db->insert($sql, [
        $nombre, $descripcion, $website, $telefono, $email, $direccion, $logo
    ]);
    
    if ($empresaId) {
        $empresa = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$empresaId]);
        jsonResponse($empresa, 201, 'Empresa creada exitosamente');
    } else {
        jsonError('Error al crear empresa', 500);
    }
}

function handlePut($db) {
    // Verificar autenticación
    $userPayload = requireAuth();
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonError('ID de empresa requerido', 400);
    }
    
    // Obtener datos JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        jsonError('Datos JSON inválidos', 400);
    }
    
    // Verificar que la empresa existe
    $empresa = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$id]);
    if (!$empresa) {
        jsonError('Empresa no encontrada', 404);
    }
    
    // Preparar datos para actualizar
    $updateFields = [];
    $params = [];
    
    $allowedFields = ['nombre', 'descripcion', 'website', 'telefono', 'email', 'direccion', 'logo', 'estado'];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = sanitizeInput($data[$field]);
        }
    }
    
    if (empty($updateFields)) {
        jsonError('No hay campos para actualizar', 400);
    }
    
    $params[] = $id;
    $sql = "UPDATE empresas_convenio SET " . implode(', ', $updateFields) . ", fecha_actualizacion = NOW() WHERE id = ?";
    
    $updated = $db->update($sql, $params);
    
    if ($updated) {
        $empresa = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$id]);
        jsonResponse($empresa, 200, 'Empresa actualizada exitosamente');
    } else {
        jsonError('Error al actualizar empresa', 500);
    }
}

function handleDelete($db) {
    // Verificar autenticación
    $userPayload = requireAuth();
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        jsonError('ID de empresa requerido', 400);
    }
    
    // Verificar que la empresa exists
    $empresa = $db->selectOne("SELECT * FROM empresas_convenio WHERE id = ?", [$id]);
    if (!$empresa) {
        jsonError('Empresa no encontrada', 404);
    }
    
    // Soft delete - cambiar estado
    $deleted = $db->update("UPDATE empresas_convenio SET estado = 'inactiva', fecha_actualizacion = NOW() WHERE id = ?", [$id]);
    
    if ($deleted) {
        jsonResponse(null, 200, 'Empresa marcada como inactiva exitosamente');
    } else {
        jsonError('Error al desactivar empresa', 500);
    }
}
?>