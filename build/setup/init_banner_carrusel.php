<?php
/**
 * Script de Inicialización del Sistema de Banner Carrusel
 * Corrige el problema de la tabla faltante y unifica los nombres de columnas
 */

// Incluir la configuración de base de datos
$config_paths = [
    '../config/database.php',
    '../config/database.php',
    '../api/config.php'
];

$database_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $database_loaded = true;
        break;
    }
}

if (!$database_loaded) {
    die("Error: No se pudo encontrar el archivo de configuración de base de datos.");
}

class BannerCarruselSetup {
    private $conn;
    
    public function __construct() {
        try {
            // Intentar diferentes maneras de conectar
            if (class_exists('Database')) {
                $db = Database::getInstance();
                $this->conn = $db->getConnection();
            } else {
                // Conectar directamente
                $host = 'localhost';
                $dbname = 'claut_intranet';
                $username = 'root';
                $password = '12345678';
                
                $this->conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
        } catch (Exception $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public function crearTablaBannerCarrusel() {
        echo "<h3><i class=\"fas fa-hammer\"></i> Creando tabla banner_carrusel...</h3>\n";
        
        try {
            // Primero, verificar si la tabla existe
            $stmt = $this->conn->prepare("SHOW TABLES LIKE 'banner_carrusel'");
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: orange;'><i class=\"fas fa-triangle-exclamation\"></i> La tabla banner_carrusel ya existe. Verificando estructura...</p>\n";
                $this->verificarEstructuraTabla();
            } else {
                // Crear la tabla
                $sql = "CREATE TABLE banner_carrusel (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    titulo VARCHAR(255) NOT NULL,
                    descripcion TEXT,
                    imagen_url VARCHAR(500) NOT NULL,
                    orden INT DEFAULT 1,
                    posicion INT DEFAULT 1,
                    activo BOOLEAN DEFAULT TRUE,
                    fecha_inicio DATETIME NULL,
                    fecha_fin DATETIME NULL,
                    creado_por INT NULL,
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_banner_activo (activo),
                    INDEX idx_banner_orden (orden),
                    INDEX idx_banner_posicion (posicion),
                    INDEX idx_banner_fechas (fecha_inicio, fecha_fin)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $this->conn->exec($sql);
                echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Tabla banner_carrusel creada exitosamente</p>\n";
            }

        } catch (Exception $e) {
            echo "<p style='color: red;'><i class=\"fas fa-circle-xmark\"></i> Error al crear tabla: " . htmlspecialchars($e->getMessage()) . "</p>\n";
            return false;
        }
        
        return true;
    }
    
    public function verificarEstructuraTabla() {
        try {
            $stmt = $this->conn->prepare("DESCRIBE banner_carrusel");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $columnNames = array_column($columns, 'Field');
            
            // Verificar si falta la columna 'orden'
            if (!in_array('orden', $columnNames)) {
                echo "<p style='color: blue;'><i class=\"fas fa-wrench\"></i> Agregando columna 'orden'...</p>\n";
                $this->conn->exec("ALTER TABLE banner_carrusel ADD COLUMN orden INT DEFAULT 1 AFTER imagen_url");
                echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Columna 'orden' agregada</p>\n";
            }
            
            // Verificar si falta la columna 'posicion'
            if (!in_array('posicion', $columnNames)) {
                echo "<p style='color: blue;'><i class=\"fas fa-wrench\"></i> Agregando columna 'posicion'...</p>\n";
                $this->conn->exec("ALTER TABLE banner_carrusel ADD COLUMN posicion INT DEFAULT 1 AFTER orden");
                echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Columna 'posicion' agregada</p>\n";
            }

            // Crear índices si no existen
            $this->crearIndices();

        } catch (Exception $e) {
            echo "<p style='color: red;'><i class=\"fas fa-circle-xmark\"></i> Error al verificar estructura: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    public function crearIndices() {
        $indices = [
            'idx_banner_activo' => 'CREATE INDEX idx_banner_activo ON banner_carrusel(activo)',
            'idx_banner_orden' => 'CREATE INDEX idx_banner_orden ON banner_carrusel(orden)',
            'idx_banner_posicion' => 'CREATE INDEX idx_banner_posicion ON banner_carrusel(posicion)',
            'idx_banner_fechas' => 'CREATE INDEX idx_banner_fechas ON banner_carrusel(fecha_inicio, fecha_fin)'
        ];
        
        foreach ($indices as $nombre => $sql) {
            try {
                $this->conn->exec($sql);
                echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Índice $nombre creado</p>\n";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    echo "<p style='color: orange;'><i class=\"fas fa-triangle-exclamation\"></i> Índice $nombre ya existe</p>\n";
                }
            }
        }
    }
    
    public function insertarBannersEjemplo() {
        echo "<h3><i class=\"fas fa-camera\"></i> Insertando banners de ejemplo...</h3>\n";
        
        try {
            // Verificar si ya hay banners
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM banner_carrusel");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                echo "<p style='color: orange;'><i class=\"fas fa-triangle-exclamation\"></i> Ya existen {$result['total']} banners en la base de datos</p>\n";
                return;
            }
            
            $banners = [
                [
                    'titulo' => 'Bienvenido a Clúster Intranet',
                    'descripcion' => 'Conecta, colabora y crece con el clúster automotriz líder de México',
                    'imagen_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2340&q=80',
                    'orden' => 1,
                    'posicion' => 1,
                    'activo' => 1
                ],
                [
                    'titulo' => 'Innovación Automotriz',
                    'descripcion' => 'Impulsamos la transformación digital en la industria automotriz mexicana',
                    'imagen_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2340&q=80',
                    'orden' => 2,
                    'posicion' => 2,
                    'activo' => 1
                ],
                [
                    'titulo' => 'Colaboración Empresarial',
                    'descripcion' => 'Fortalecemos las alianzas estratégicas entre empresas del sector automotriz',
                    'imagen_url' => 'https://images.unsplash.com/photo-1560472355-109703aa3edc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2126&q=80',
                    'orden' => 3,
                    'posicion' => 3,
                    'activo' => 1
                ],
                [
                    'titulo' => 'Desarrollo Sustentable',
                    'descripcion' => 'Comprometidos con un futuro automotriz más limpio y sostenible',
                    'imagen_url' => 'https://images.unsplash.com/photo-1593941707874-ef2d9b5c1e6e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2242&q=80',
                    'orden' => 4,
                    'posicion' => 4,
                    'activo' => 1
                ]
            ];
            
            $sql = "INSERT INTO banner_carrusel (titulo, descripcion, imagen_url, orden, posicion, activo, fecha_creacion) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($banners as $banner) {
                $stmt->execute([
                    $banner['titulo'],
                    $banner['descripcion'],
                    $banner['imagen_url'],
                    $banner['orden'],
                    $banner['posicion'],
                    $banner['activo']
                ]);
                
                echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Banner '{$banner['titulo']}' insertado</p>\n";
            }

            echo "<p style='color: green;'><strong><i class=\"fas fa-circle-check\"></i> " . count($banners) . " banners de ejemplo insertados exitosamente</strong></p>\n";

        } catch (Exception $e) {
            echo "<p style='color: red;'><i class=\"fas fa-circle-xmark\"></i> Error al insertar banners: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    public function crearDirectorioUploads() {
        echo "<h3><i class=\"fas fa-folder\"></i> Verificando directorio de uploads...</h3>\n";
        
        $uploadPaths = [
            '../uploads/banners',
            '../../uploads/banners'
        ];
        
        foreach ($uploadPaths as $path) {
            if (!file_exists($path)) {
                if (mkdir($path, 0755, true)) {
                    echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Directorio creado: $path</p>\n";
                } else {
                    echo "<p style='color: red;'><i class=\"fas fa-circle-xmark\"></i> No se pudo crear el directorio: $path</p>\n";
                }
            } else {
                echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Directorio ya existe: $path</p>\n";
            }
        }
        
        // Crear archivo .htaccess para seguridad
        $htaccessContent = "Options -Indexes\n<Files *.php>\n    Deny from all\n</Files>";
        foreach ($uploadPaths as $path) {
            if (file_exists($path)) {
                file_put_contents($path . '/.htaccess', $htaccessContent);
            }
        }
    }
    
    public function verificarSistema() {
        echo "<h3><i class=\"fas fa-magnifying-glass\"></i> Verificando sistema...</h3>\n";
        
        try {
            // Contar banners
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN activo = 1 THEN 1 END) as activos FROM banner_carrusel");
            $stmt->execute();
            $stats = $stmt->fetch();
            
            echo "<p><strong><i class=\"fas fa-chart-column\"></i> Estadísticas:</strong></p>\n";
            echo "<ul>\n";
            echo "<li>Total de banners: {$stats['total']}</li>\n";
            echo "<li>Banners activos: {$stats['activos']}</li>\n";
            echo "</ul>\n";
            
            // Mostrar banners activos
            if ($stats['activos'] > 0) {
                echo "<p><strong><i class=\"fas fa-bullseye\"></i> Banners activos:</strong></p>\n";
                $stmt = $this->conn->prepare("SELECT id, titulo, orden, posicion FROM banner_carrusel WHERE activo = 1 ORDER BY orden ASC");
                $stmt->execute();
                $banners = $stmt->fetchAll();
                
                echo "<ol>\n";
                foreach ($banners as $banner) {
                    echo "<li>ID {$banner['id']}: {$banner['titulo']} (Orden: {$banner['orden']}, Posición: {$banner['posicion']})</li>\n";
                }
                echo "</ol>\n";
            }
            
            // Probar API
            echo "<p><strong><i class=\"fas fa-plug\"></i> Probando API...</strong></p>\n";
            $apiUrl = '../api/banners.php';
            if (file_exists($apiUrl)) {
                echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> Archivo API encontrado</p>\n";
                
                // Hacer una petición de prueba interna
                ob_start();
                $_SERVER['REQUEST_METHOD'] = 'GET';
                include $apiUrl;
                $apiResponse = ob_get_clean();
                
                if (!empty($apiResponse)) {
                    echo "<p style='color: green;'><i class=\"fas fa-circle-check\"></i> API respondiendo correctamente</p>\n";
                } else {
                    echo "<p style='color: orange;'><i class=\"fas fa-triangle-exclamation\"></i> API no está respondiendo como se esperaba</p>\n";
                }
            } else {
                echo "<p style='color: red;'><i class=\"fas fa-circle-xmark\"></i> Archivo API no encontrado</p>\n";
            }

        } catch (Exception $e) {
            echo "<p style='color: red;'><i class=\"fas fa-circle-xmark\"></i> Error en verificación: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    public function ejecutarConfiguracionCompleta() {
        echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; border-left: 4px solid #2196f3; margin: 20px 0;'>\n";
        echo "<h2><i class=\"fas fa-rocket\"></i> Iniciando configuración del Sistema de Banner Carrusel</h2>\n";
        echo "</div>\n";
        
        $pasos = [
            'crearTablaBannerCarrusel' => '1. Crear/verificar tabla de base de datos',
            'crearDirectorioUploads' => '2. Crear directorios de subida de archivos',
            'insertarBannersEjemplo' => '3. Insertar banners de ejemplo',
            'verificarSistema' => '4. Verificar funcionamiento del sistema'
        ];
        
        foreach ($pasos as $metodo => $descripcion) {
            echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;'>\n";
            echo "<h4>$descripcion</h4>\n";
            $this->$metodo();
            echo "</div>\n";
        }
        
        echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; border-left: 4px solid #4caf50; margin: 20px 0;'>\n";
        echo "<h2><i class=\"fas fa-champagne-glasses\"></i> ¡Configuración completada!</h2>\n";
        echo "<p>El sistema de banner carrusel está listo para usarse.</p>\n";
        echo "</div>\n";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicialización Banner Carrusel - Clúster</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
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
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        
        .content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 8px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .feature-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        
        .status-check {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        
        .status-check span {
            margin-right: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-palette"></i> Clúster Intranet</h1>
        <p>Sistema de Banner Carrusel - Inicialización</p>
    </div>
    
    <div class="content">
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['init'])) {
            $setup = new BannerCarruselSetup();
            $setup->ejecutarConfiguracionCompleta();
            
            echo "<hr style='margin: 30px 0;'>\n";
            echo "<h3><i class=\"fas fa-link\"></i> Enlaces de Acceso</h3>\n";
            echo "<div style='text-align: center;'>\n";
            echo "<a href='../admin/banner-admin.php' class='btn btn-success'><i class=\"fas fa-chart-column\"></i> Panel de Administración</a>\n";
            echo "<a href='../pages/sign-in.html' class='btn'><i class=\"fas fa-lock\"></i> Ver Página de Login</a>\n";
            echo "<a href='../api/banners.php' class='btn'><i class=\"fas fa-plug\"></i> Probar API</a>\n";
            echo "</div>\n";
            
        } else {
        ?>
            <h2><i class="fas fa-bullseye"></i> Sistema de Banner Carrusel</h2>
            <p>Este script configurará completamente el sistema de banners para la página de login de Clúster Intranet.</p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <h4><i class="fas fa-database"></i> Base de Datos</h4>
                    <ul>
                        <li>Creación de tabla <code>banner_carrusel</code></li>
                        <li>Índices optimizados</li>
                        <li>Compatibilidad con campos existentes</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h4><i class="fas fa-camera"></i> Contenido</h4>
                    <ul>
                        <li>Banners de ejemplo listos</li>
                        <li>Imágenes de alta calidad</li>
                        <li>Configuración automática</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h4><i class="fas fa-wrench"></i> Configuración</h4>
                    <ul>
                        <li>Directorios de subida</li>
                        <li>Permisos de archivos</li>
                        <li>Validación del sistema</li>
                    </ul>
                </div>
                
                <div class="feature-card">
                    <h4><i class="fas fa-rocket"></i> API Ready</h4>
                    <ul>
                        <li>Endpoints funcionales</li>
                        <li>Operaciones CRUD</li>
                        <li>Respuestas JSON</li>
                    </ul>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <strong><i class="fas fa-triangle-exclamation"></i> Requisitos previos:</strong>
                <ul style="margin: 10px 0;">
                    <li>Base de datos <code>claut_intranet</code> debe existir</li>
                    <li>Usuario MySQL con permisos de CREATE y INSERT</li>
                    <li>PHP con extensión PDO habilitada</li>
                    <li>Directorio <code>uploads</code> con permisos de escritura</li>
                </ul>
            </div>
            
            <div class="alert alert-info">
                <strong><i class="fas fa-circle-info"></i> Lo que hará este script:</strong>
                <ul style="margin: 10px 0;">
                    <li><i class="fas fa-circle-check"></i> Crear tabla <code>banner_carrusel</code> con estructura optimizada</li>
                    <li><i class="fas fa-circle-check"></i> Insertar 4 banners de ejemplo con imágenes de calidad</li>
                    <li><i class="fas fa-circle-check"></i> Crear directorios necesarios con permisos adecuados</li>
                    <li><i class="fas fa-circle-check"></i> Verificar que el API funcione correctamente</li>
                    <li><i class="fas fa-circle-check"></i> Generar estadísticas del sistema</li>
                </ul>
            </div>
            
            <form method="post" style="text-align: center; margin: 40px 0;">
                <button type="submit" class="btn btn-success" style="font-size: 20px; padding: 20px 40px;">
                    <i class="fas fa-rocket"></i> Inicializar Sistema Completo
                </button>
            </form>
            
            <div style="text-align: center; margin: 20px 0;">
                <a href="?init=1" class="btn">Inicializar vía GET</a>
                <a href="../admin/banner-admin.php" class="btn">Panel Admin (si ya está configurado)</a>
            </div>
        <?php
        }
        ?>
    </div>
    
    <footer style="text-align: center; margin-top: 40px; color: #6c757d; padding: 20px;">
        <p>&copy; <?= date('Y') ?> Clúster Intranet - Sistema de Gestión de Banners</p>
        <p style="font-size: 14px;">Desarrollado para el clúster automotriz líder de México</p>
    </footer>
</body>
</html>