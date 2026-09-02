<?php
/**
 * Script de inicialización de base de datos
 * Ejecuta los archivos SQL necesarios para configurar las empresas en convenio
 */

// Configurar headers
header('Content-Type: application/json; charset=utf-8');

// Definir acceso
define('CLAUT_ACCESS', true);

// Incluir configuración
require_once '../includes/config.php';

try {
    $db = Database::getInstance();
    
    echo json_encode([
        'status' => 'checking',
        'message' => 'Verificando estructura de base de datos...'
    ]);
    
    // Verificar si ya existen datos
    $empresasExistentes = $db->selectOne("SELECT COUNT(*) as total FROM empresas_convenio WHERE estado = 'activa'");
    
    if ($empresasExistentes['total'] == 0) {
        echo json_encode([
            'status' => 'seeding',
            'message' => 'Inicializando datos de empresas en convenio...'
        ]);
        
        // Ejecutar el archivo de semillas
        $seedFile = __DIR__ . '/../database/seeds/empresas_seed.sql';
        
        if (file_exists($seedFile)) {
            $sql = file_get_contents($seedFile);
            
            // Dividir en statements individuales
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            $db->beginTransaction();
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    try {
                        $db->getPdo()->exec($statement);
                    } catch (Exception $e) {
                        // Ignorar errores de IGNORE y duplicados
                        if (!strpos($e->getMessage(), 'Duplicate entry')) {
                            throw $e;
                        }
                    }
                }
            }
            
            $db->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Base de datos inicializada correctamente',
                'data' => [
                    'empresas_creadas' => $db->selectOne("SELECT COUNT(*) as total FROM empresas_convenio WHERE estado = 'activa'")['total'],
                    'usuarios_creados' => $db->selectOne("SELECT COUNT(*) as total FROM usuarios_perfil WHERE estado = 'activo'")['total']
                ]
            ]);
            
        } else {
            throw new Exception('Archivo de semillas no encontrado');
        }
        
    } else {
        echo json_encode([
            'status' => 'already_initialized',
            'message' => 'Base de datos ya contiene datos',
            'data' => [
                'empresas_existentes' => $empresasExistentes['total']
            ]
        ]);
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Error inicializando base de datos',
        'error' => $e->getMessage()
    ]);
}
?>
