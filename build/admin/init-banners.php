<?php
/**
 * Script de inicialización rápida del sistema de banners
 * Ejecutar este archivo para crear la tabla y configurar el sistema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

$mensajes = [];

try {
    // Conectar a la base de datos
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $mensajes[] = "<i class=\"fas fa-circle-check\"></i> Conexión a base de datos exitosa";
    
    // Crear tabla si no existe
    $sql = "CREATE TABLE IF NOT EXISTS banner_carrusel (
        id INT PRIMARY KEY AUTO_INCREMENT,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT,
        imagen_url VARCHAR(500) NOT NULL,
        posicion INT DEFAULT 1,
        activo BOOLEAN DEFAULT TRUE,
        fecha_inicio DATETIME DEFAULT NULL,
        fecha_fin DATETIME DEFAULT NULL,
        creado_por INT DEFAULT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql);
    $mensajes[] = "<i class=\"fas fa-circle-check\"></i> Tabla banner_carrusel creada/verificada";
    
    // Verificar si hay banners
    $stmt = $conn->query("SELECT COUNT(*) as total FROM banner_carrusel");
    $count = $stmt->fetch();
    
    if ($count['total'] == 0) {
        // Insertar banners de ejemplo
        $banners_ejemplo = [
            [
                'titulo' => 'Bienvenido a Clúster Intranet',
                'descripcion' => 'Conecta, colabora y crece con el clúster automotriz líder de México',
                'imagen_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'posicion' => 1
            ],
            [
                'titulo' => 'Innovación Automotriz',
                'descripcion' => 'Impulsamos la transformación digital en la industria automotriz mexicana',
                'imagen_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                'posicion' => 2
            ],
            [
                'titulo' => 'Colaboración Empresarial',
                'descripcion' => 'Fortalecemos las alianzas estratégicas entre empresas del sector automotriz',
                'imagen_url' => 'https://images.unsplash.com/photo-1560472355-109703aa3edc?ixlib=rb-4.0.3&auto=format&fit=crop&w=2126&q=80',
                'posicion' => 3
            ]
        ];
        
        $stmt = $conn->prepare("INSERT INTO banner_carrusel (titulo, descripcion, imagen_url, posicion, activo) VALUES (?, ?, ?, ?, 1)");
        
        foreach ($banners_ejemplo as $banner) {
            $stmt->execute([
                $banner['titulo'],
                $banner['descripcion'],
                $banner['imagen_url'],
                $banner['posicion']
            ]);
        }
        
        $mensajes[] = "<i class=\"fas fa-circle-check\"></i> Banners de ejemplo insertados";
    } else {
        $mensajes[] = "<i class=\"fas fa-circle-info\"></i> Ya existen {$count['total']} banners en el sistema";
    }
    
    // Crear directorio de uploads
    $directorioUploads = '../uploads/banners/';
    if (!file_exists($directorioUploads)) {
        if (mkdir($directorioUploads, 0755, true)) {
            $mensajes[] = "<i class=\"fas fa-circle-check\"></i> Directorio de uploads creado";
        } else {
            $mensajes[] = "<i class=\"fas fa-triangle-exclamation\"></i> No se pudo crear el directorio de uploads";
        }
    } else {
        $mensajes[] = "<i class=\"fas fa-circle-check\"></i> Directorio de uploads ya existe";
    }
    
    // Verificar permisos
    if (is_writable($directorioUploads)) {
        $mensajes[] = "<i class=\"fas fa-circle-check\"></i> Directorio de uploads tiene permisos de escritura";
    } else {
        $mensajes[] = "<i class=\"fas fa-triangle-exclamation\"></i> El directorio de uploads NO tiene permisos de escritura";
    }
    
} catch (Exception $e) {
    $mensajes[] = "<i class=\"fas fa-circle-xmark\"></i> Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicialización del Sistema de Banners</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .mensaje {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f0f0f0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 10px 10px 0;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-secondary {
            background: #2196F3;
        }
        .btn-secondary:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-rocket"></i> Inicialización del Sistema de Banners</h1>
        
        <?php foreach ($mensajes as $mensaje): ?>
            <div class="mensaje"><?= $mensaje ?></div>
        <?php endforeach; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h2>Enlaces útiles:</h2>
            <a href="banner-admin-mejorado.php" class="btn">Panel de Administración</a>
            <a href="diagnostico-banners.php" class="btn btn-secondary">Diagnóstico Completo</a>
            <a href="../pages/sign-in.html" class="btn btn-secondary" target="_blank">Ver Login</a>
        </div>
    </div>
</body>
</html>
