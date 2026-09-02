<?php
/**
 * Script para solucionar el error de índice duplicado 'idx_banner_fechas'
 * y mejorar el sistema de banners
 */

require_once '../config/database.php';

function solucionarIndiceDuplicado() {
    try {
        echo "<h2><i class=\"fas fa-wrench\"></i> Solucionando Error de Índice Duplicado</h2>\n";
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Eliminar índices existentes si existen
        $indices = ['idx_banner_fechas', 'idx_banner_activo', 'idx_banner_posicion'];
        
        foreach ($indices as $indice) {
            try {
                $conn->exec("ALTER TABLE banner_carrusel DROP INDEX $indice");
                echo "<p style='color: green;'><i class=\"fas fa-check\"></i> Índice '$indice' eliminado exitosamente</p>\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Can't DROP") !== false) {
                    echo "<p style='color: orange;'><i class=\"fas fa-triangle-exclamation\"></i> Índice '$indice' no existía</p>\n";
                } else {
                    echo "<p style='color: red;'><i class=\"fas fa-xmark\"></i> Error al eliminar índice '$indice': " . $e->getMessage() . "</p>\n";
                }
            }
        }
        
        // Crear los índices nuevamente
        $nuevosIndices = [
            "CREATE INDEX idx_banner_activo ON banner_carrusel(activo)" => "Estado activo",
            "CREATE INDEX idx_banner_posicion ON banner_carrusel(posicion)" => "Posición del banner", 
            "CREATE INDEX idx_banner_fechas ON banner_carrusel(fecha_inicio, fecha_fin)" => "Fechas de vigencia"
        ];
        
        foreach ($nuevosIndices as $query => $descripcion) {
            try {
                $conn->exec($query);
                echo "<p style='color: green;'><i class=\"fas fa-check\"></i> Índice creado: $descripcion</p>\n";
            } catch (PDOException $e) {
                echo "<p style='color: red;'><i class=\"fas fa-xmark\"></i> Error al crear índice ($descripcion): " . $e->getMessage() . "</p>\n";
            }
        }

        echo "<h3 style='color: green;'><i class=\"fas fa-check\"></i> Problema de índice duplicado solucionado</h3>\n";
        
        // Verificar la estructura actual
        verificarEstructuraBanners($conn);
        
        return true;
        
    } catch (Exception $e) {
        echo "<h3 style='color: red;'><i class=\"fas fa-xmark\"></i> Error al solucionar índices:</h3>\n";
        echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>\n";
        return false;
    }
}

function verificarEstructuraBanners($conn) {
    try {
        echo "<h3><i class=\"fas fa-chart-column\"></i> Verificando Estructura de la Tabla</h3>\n";
        
        // Verificar que la tabla existe
        $stmt = $conn->query("SHOW TABLES LIKE 'banner_carrusel'");
        if ($stmt->rowCount() == 0) {
            echo "<p style='color: red;'><i class=\"fas fa-triangle-exclamation\"></i> La tabla banner_carrusel no existe</p>\n";
            return;
        }
        
        // Mostrar estructura de la tabla
        $stmt = $conn->query("DESCRIBE banner_carrusel");
        $columns = $stmt->fetchAll();
        
        echo "<h4>Columnas de la tabla:</h4>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Defecto</th></tr>\n";
        
        foreach ($columns as $column) {
            echo "<tr>\n";
            echo "<td>{$column['Field']}</td>\n";
            echo "<td>{$column['Type']}</td>\n";
            echo "<td>{$column['Null']}</td>\n";
            echo "<td>{$column['Key']}</td>\n";
            echo "<td>{$column['Default']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Mostrar índices
        $stmt = $conn->query("SHOW INDEX FROM banner_carrusel");
        $indexes = $stmt->fetchAll();
        
        echo "<h4>Índices de la tabla:</h4>\n";
        if (count($indexes) > 0) {
            echo "<ul>\n";
            $indexNames = [];
            foreach ($indexes as $index) {
                if (!in_array($index['Key_name'], $indexNames)) {
                    echo "<li><strong>{$index['Key_name']}</strong> en columna(s): ";
                    $indexNames[] = $index['Key_name'];
                }
            }
            
            foreach ($indexNames as $indexName) {
                $columns = [];
                foreach ($indexes as $index) {
                    if ($index['Key_name'] === $indexName) {
                        $columns[] = $index['Column_name'];
                    }
                }
                echo implode(', ', $columns) . "</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo "<p>No hay índices personalizados.</p>\n";
        }
        
        // Contar banners
        $stmt = $conn->query("SELECT COUNT(*) as total FROM banner_carrusel");
        $result = $stmt->fetch();
        echo "<p><strong>Total de banners:</strong> {$result['total']}</p>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error al verificar estructura: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
}

function inicializarBannersCompleto() {
    try {
        echo "<h2><i class=\"fas fa-palette\"></i> Inicialización Completa del Sistema de Banners</h2>\n";
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Verificar si la tabla existe, si no, crearla
        $stmt = $conn->query("SHOW TABLES LIKE 'banner_carrusel'");
        if ($stmt->rowCount() == 0) {
            echo "<p>Creando tabla banner_carrusel...</p>\n";
            
            $createTable = "
            CREATE TABLE banner_carrusel (
                id INT PRIMARY KEY AUTO_INCREMENT,
                titulo VARCHAR(255) NOT NULL,
                descripcion TEXT,
                imagen_url VARCHAR(500) NOT NULL,
                posicion INT DEFAULT 1,
                activo BOOLEAN DEFAULT TRUE,
                fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_fin DATETIME NULL,
                creado_por INT,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            $conn->exec($createTable);
            echo "<p style='color: green;'><i class=\"fas fa-check\"></i> Tabla banner_carrusel creada</p>\n";
        }
        
        // Verificar si hay banners, si no, insertar algunos de ejemplo
        $stmt = $conn->query("SELECT COUNT(*) as total FROM banner_carrusel");
        $result = $stmt->fetch();
        
        if ($result['total'] == 0) {
            echo "<p>Insertando banners de ejemplo...</p>\n";
            
            $banners = [
                [
                    'titulo' => 'Banner Principal',
                    'descripcion' => 'Bienvenido a Clúster Intranet - Portal del clúster automotriz de México',
                    'imagen_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                    'posicion' => 1
                ],
                [
                    'titulo' => 'Promoción Especial',
                    'descripcion' => 'Innovación y tecnología al servicio de la industria automotriz',
                    'imagen_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&auto=format&fit=crop&w=2340&q=80',
                    'posicion' => 2
                ],
                [
                    'titulo' => 'Nuevo Producto',
                    'descripcion' => 'Conectando empresas y profesionales del sector automotriz mexicano',
                    'imagen_url' => 'https://images.unsplash.com/photo-1560472355-109703aa3edc?ixlib=rb-4.0.3&auto=format&fit=crop&w=2126&q=80',
                    'posicion' => 3
                ]
            ];
            
            foreach ($banners as $banner) {
                $stmt = $conn->prepare("
                    INSERT INTO banner_carrusel (titulo, descripcion, imagen_url, posicion, activo) 
                    VALUES (?, ?, ?, ?, TRUE)
                ");
                
                $stmt->execute([
                    $banner['titulo'],
                    $banner['descripcion'],
                    $banner['imagen_url'],
                    $banner['posicion']
                ]);
            }
            
            echo "<p style='color: green;'><i class=\"fas fa-check\"></i> Banners de ejemplo insertados</p>\n";
        }
        
        // Ahora solucionar el problema de índices
        solucionarIndiceDuplicado();
        
        return true;
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error en inicialización completa: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solución de Índice Duplicado - Sistema de Banners</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            background-color: #f8f9fa;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .status-success { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-screwdriver-wrench"></i> Reparación Sistema de Banners</h1>
        <p>Solución para el error de índice duplicado 'idx_banner_fechas'</p>
    </div>
    
    <div class="content">
        <?php
        $action = $_GET['action'] ?? '';
        
        if ($action === 'fix') {
            solucionarIndiceDuplicado();
        } elseif ($action === 'init') {
            inicializarBannersCompleto();
        } else {
        ?>
            <h2><i class="fas fa-triangle-exclamation"></i> Error Detectado: Índice Duplicado</h2>
            
            <div class="alert alert-warning">
                <strong>Error:</strong> SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'idx_banner_fechas'
                <br><br>
                <strong>Causa:</strong> El índice 'idx_banner_fechas' ya existe en la tabla banner_carrusel.
            </div>
            
            <div class="alert alert-info">
                <strong><i class="fas fa-lightbulb"></i> Solución:</strong> Este script eliminará los índices duplicados y los recreará correctamente.
            </div>
            
            <h3>Opciones de Reparación:</h3>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="?action=fix" class="btn btn-success">
                    <i class="fas fa-wrench"></i> Solucionar Solo Índices
                </a>

                <a href="?action=init" class="btn btn-warning">
                    <i class="fas fa-palette"></i> Inicialización Completa
                </a>
            </div>
            
            <h4>¿Qué hace cada opción?</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                <div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; background: #f8f9fa;">
                    <h5><i class="fas fa-wrench"></i> Solucionar Solo Índices</h5>
                    <ul>
                        <li>Elimina índices duplicados</li>
                        <li>Recrea los índices necesarios</li>
                        <li>Mantiene los datos existentes</li>
                        <li>Rápido y seguro</li>
                    </ul>
                </div>
                
                <div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; background: #f8f9fa;">
                    <h5><i class="fas fa-palette"></i> Inicialización Completa</h5>
                    <ul>
                        <li>Crea la tabla si no existe</li>
                        <li>Inserta banners de ejemplo</li>
                        <li>Soluciona problemas de índices</li>
                        <li>Configuración completa</li>
                    </ul>
                </div>
            </div>
            
            <hr>
            
            <h3>Enlaces Útiles:</h3>
            <div style="text-align: center;">
                <a href="../admin/banner-admin.php" class="btn"><i class="fas fa-chart-column"></i> Panel de Admin</a>
                <a href="../pages/sign-in.html" class="btn"><i class="fas fa-lock"></i> Ver Login</a>
                <a href="../api/banners.php" class="btn"><i class="fas fa-link"></i> API Banners</a>
            </div>
        <?php
        }
        ?>
    </div>
    
    <footer style="text-align: center; margin-top: 40px; color: #6c757d;">
        <p>&copy; <?= date('Y') ?> Clúster Intranet - Sistema de Gestión de Banners v2.0</p>
    </footer>
</body>
</html>