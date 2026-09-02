<?php
/**
 * API para manejar archivos adjuntos de boletines
 */

header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/api-response.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    $boletin_id = isset($_GET['boletin_id']) ? intval($_GET['boletin_id']) : null;
    
    if (!$boletin_id) {
        ApiResponse::error('ID del boletín es requerido');
    }
    
    // Obtener información del archivo adjunto del boletín
    $stmt = $pdo->prepare("SELECT id, titulo, archivo_adjunto FROM boletines WHERE id = ?");
    $stmt->execute([$boletin_id]);
    $boletin = $stmt->fetch();
    
    if (!$boletin) {
        ApiResponse::error('Boletín no encontrado');
    }
    
    $archivos = [];
    
    // Si hay archivo adjunto, agregar a la lista
    if ($boletin['archivo_adjunto']) {
        $filePath = '../uploads/' . $boletin['archivo_adjunto'];
        $fileExists = file_exists($filePath);
        
        // Obtener información del archivo
        $fileInfo = pathinfo($boletin['archivo_adjunto']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        // Determinar el tipo MIME
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg'
        ];
        
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        
        // Determinar el ícono
        $iconos = [
            'pdf' => '<i class="fas fa-file-pdf"></i>',
            'doc' => '<i class="fas fa-file-word"></i>', 'docx' => '<i class="fas fa-file-word"></i>',
            'xls' => '<i class="fas fa-file-excel"></i>', 'xlsx' => '<i class="fas fa-file-excel"></i>',
            'ppt' => '<i class="fas fa-file-powerpoint"></i>', 'pptx' => '<i class="fas fa-file-powerpoint"></i>',
            'txt' => '<i class="fas fa-file-lines"></i>', 'csv' => '<i class="fas fa-file-excel"></i>',
            'jpg' => '<i class="fas fa-file-image"></i>', 'jpeg' => '<i class="fas fa-file-image"></i>', 'png' => '<i class="fas fa-file-image"></i>', 'gif' => '<i class="fas fa-file-image"></i>',
            'mp4' => '<i class="fas fa-file-video"></i>',
            'mp3' => '<i class="fas fa-file-audio"></i>'
        ];

        $icono = $iconos[$extension] ?? '<i class="fas fa-paperclip"></i>';
        
        // Obtener tamaño del archivo si existe
        $fileSize = 0;
        $sizeFormatted = 'N/A';
        if ($fileExists) {
            $fileSize = filesize($filePath);
            
            // Formatear tamaño
            if ($fileSize < 1024) {
                $sizeFormatted = $fileSize . ' B';
            } elseif ($fileSize < 1048576) {
                $sizeFormatted = round($fileSize / 1024, 2) . ' KB';
            } else {
                $sizeFormatted = round($fileSize / 1048576, 2) . ' MB';
            }
        }
        
        // Determinar si es visualizable
        $isViewable = in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'csv']);
        
        $archivos[] = [
            'id' => $boletin['id'],
            'nombre_original' => $boletin['archivo_adjunto'],
            'nombre_archivo' => $boletin['archivo_adjunto'],
            'tipo_mime' => $mimeType,
            'extension' => $extension,
            'tamaño' => $fileSize,
            'tamaño_formateado' => $sizeFormatted,
            'icono' => $icono,
            'es_pdf' => $extension === 'pdf',
            'es_imagen' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif']),
            'es_visualizable' => $isViewable,
            'existe' => $fileExists,
            'url_vista' => './uploads/' . $boletin['archivo_adjunto'],
            'url_descarga' => './uploads/' . $boletin['archivo_adjunto']
        ];
    }
    
    ApiResponse::success($archivos, [
        'total' => count($archivos)
    ]);
    
} catch (PDOException $e) {
    error_log("Error en archivos API: " . $e->getMessage());
    ApiResponse::error('Error de base de datos: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    ApiResponse::error('Error del servidor: ' . $e->getMessage());
}
?>
