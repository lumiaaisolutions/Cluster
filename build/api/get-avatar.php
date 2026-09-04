<?php
/**
 * API para obtener avatares de usuarios desde la base de datos
 * Endpoint: /api/get-avatar.php?user_id=123 o /api/get-avatar.php (usuario actual)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Obtener avatar de la base de datos
 */
function obtenerAvatar($userId) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $query = "SELECT avatar, avatar_mime_type, avatar_filename 
                  FROM usuarios_perfil 
                  WHERE id = :user_id AND activo = 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['avatar']) {
            return [
                'data' => $result['avatar'],
                'mime_type' => $result['avatar_mime_type'],
                'filename' => $result['avatar_filename']
            ];
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error obteniendo avatar: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener avatar por defecto
 */
function obtenerAvatarPorDefecto() {
    // Crear un avatar simple por defecto (icono de usuario)
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="150" height="150" viewBox="0 0 150 150" xmlns="http://www.w3.org/2000/svg">
        <rect width="150" height="150" fill="#e5e7eb"/>
        <circle cx="75" cy="60" r="25" fill="#9ca3af"/>
        <path d="M75 100 C50 100, 30 115, 30 135 L120 135 C120 115, 100 100, 75 100 Z" fill="#9ca3af"/>
    </svg>';
    
    return [
        'data' => $svg,
        'mime_type' => 'image/svg+xml',
        'filename' => 'default-avatar.svg'
    ];
}

// Configuración de sesión — antes session_start() a secas usaba el
// nombre de cookie por defecto de PHP en vez de "CLAUT_SESSION" (mismo
// bug que BUG-035). Nota: este archivo no está referenciado por ninguna
// página del sitio (verificado, cero resultados) — sin impacto en
// producción hoy, se deja corregido por consistencia.
define('CLAUT_ACCESS', true);
require_once __DIR__ . '/../config/session-config.php';
SessionConfig::init();

// Determinar qué usuario
$userId = null;

if (isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
} elseif (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
}

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de usuario requerido']);
    exit();
}

// Obtener avatar
$avatar = obtenerAvatar($userId);

// Si no hay avatar, usar por defecto
if (!$avatar) {
    $avatar = obtenerAvatarPorDefecto();
}

// Configurar headers apropiados
header('Content-Type: ' . $avatar['mime_type']);
header('Content-Length: ' . strlen($avatar['data']));
header('Cache-Control: public, max-age=3600'); // Cache por 1 hora
header('Content-Disposition: inline; filename="' . $avatar['filename'] . '"');

// Enviar imagen
echo $avatar['data'];
?>