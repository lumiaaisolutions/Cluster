<?php
/**
 * Script para crear todas las tablas necesarias del sistema
 */

require_once '../config/database.php';

echo '<script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>';
echo "<h1><i class='fas fa-screwdriver-wrench'></i> Configuración de Base de Datos - Clúster</h1>";
echo "<style>body{font-family:Arial;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";

try {
    $db = Database::getInstance();
    
    echo "<h2><i class='fas fa-clipboard-list'></i> Creando Tablas del Sistema</h2>";
    
    // 1. Tabla de usuarios (verificar si existe)
    echo "<h3><i class='fas fa-users'></i> Tabla: usuarios</h3>";
    try {
        $result = $db->selectOne("SHOW TABLES LIKE 'usuarios'");
        if ($result) {
            echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'usuarios' ya existe</span><br>";
        } else {
            throw new Exception("Tabla usuarios no existe");
        }
    } catch (Exception $e) {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Creando tabla 'usuarios'...</span><br>";
        
        $sql = "CREATE TABLE `usuarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(100) NOT NULL,
            `apellido` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL UNIQUE,
            `password` varchar(255) NOT NULL,
            `telefono` varchar(20) DEFAULT NULL,
            `empresa_id` int(11) DEFAULT NULL,
            `rol` enum('admin','empleado','moderador') DEFAULT 'empleado',
            `estado` enum('activo','pendiente','inactivo') DEFAULT 'pendiente',
            `avatar` varchar(255) DEFAULT NULL,
            `fecha_registro` timestamp DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_email` (`email`),
            KEY `idx_rol` (`rol`),
            KEY `idx_estado` (`estado`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($sql);
        echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'usuarios' creada exitosamente</span><br>";
    }
    
    // 2. Tabla de banners
    echo "<h3><i class='fas fa-image'></i> Tabla: banners</h3>";
    try {
        $result = $db->selectOne("SHOW TABLES LIKE 'banners'");
        if ($result) {
            echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'banners' ya existe</span><br>";
        } else {
            throw new Exception("Tabla banners no existe");
        }
    } catch (Exception $e) {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Creando tabla 'banners'...</span><br>";
        
        $sql = "CREATE TABLE `banners` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titulo` varchar(255) NOT NULL,
            `descripcion` text,
            `imagen_url` varchar(500) NOT NULL,
            `enlace` varchar(500) DEFAULT NULL,
            `orden` int(11) DEFAULT 0,
            `estado` enum('activo','inactivo') DEFAULT 'activo',
            `fecha_inicio` datetime DEFAULT CURRENT_TIMESTAMP,
            `fecha_fin` datetime DEFAULT NULL,
            `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_estado` (`estado`),
            KEY `idx_orden` (`orden`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($sql);
        echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'banners' creada exitosamente</span><br>";
    }
    
    // 3. Tabla de boletines
    echo "<h3><i class='fas fa-newspaper'></i> Tabla: boletines</h3>";
    try {
        $result = $db->selectOne("SHOW TABLES LIKE 'boletines'");
        if ($result) {
            echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'boletines' ya existe</span><br>";
        } else {
            throw new Exception("Tabla boletines no existe");
        }
    } catch (Exception $e) {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Creando tabla 'boletines'...</span><br>";
        
        $sql = "CREATE TABLE `boletines` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titulo` varchar(255) NOT NULL,
            `descripcion` text,
            `archivo_url` varchar(500) NOT NULL,
            `fecha_publicacion` date NOT NULL,
            `estado` enum('borrador','publicado','archivado') DEFAULT 'borrador',
            `descargas` int(11) DEFAULT 0,
            `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_estado` (`estado`),
            KEY `idx_fecha_publicacion` (`fecha_publicacion`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($sql);
        echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'boletines' creada exitosamente</span><br>";
    }
    
    // 4. Tabla de eventos
    echo "<h3><i class='fas fa-calendar-days'></i> Tabla: eventos</h3>";
    try {
        $result = $db->selectOne("SHOW TABLES LIKE 'eventos'");
        if ($result) {
            echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'eventos' ya existe</span><br>";
        } else {
            throw new Exception("Tabla eventos no existe");
        }
    } catch (Exception $e) {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Creando tabla 'eventos'...</span><br>";
        
        $sql = "CREATE TABLE `eventos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titulo` varchar(255) NOT NULL,
            `descripcion` text,
            `fecha_inicio` datetime NOT NULL,
            `fecha_fin` datetime NOT NULL,
            `ubicacion` varchar(255) DEFAULT NULL,
            `tipo` varchar(100) DEFAULT NULL,
            `estado` enum('programado','en_curso','finalizado','cancelado') DEFAULT 'programado',
            `cupo_maximo` int(11) DEFAULT NULL,
            `registrados` int(11) DEFAULT 0,
            `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_estado` (`estado`),
            KEY `idx_fecha_inicio` (`fecha_inicio`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($sql);
        echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'eventos' creada exitosamente</span><br>";
    }
    
    // 5. Tabla de empresas convenio
    echo "<h3><i class='fas fa-building'></i> Tabla: empresas_convenio</h3>";
    try {
        $result = $db->selectOne("SHOW TABLES LIKE 'empresas_convenio'");
        if ($result) {
            echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'empresas_convenio' ya existe</span><br>";
        } else {
            throw new Exception("Tabla empresas_convenio no existe");
        }
    } catch (Exception $e) {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Creando tabla 'empresas_convenio'...</span><br>";
        
        $sql = "CREATE TABLE `empresas_convenio` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nombre` varchar(255) NOT NULL,
            `descripcion` text,
            `logo_url` varchar(500) DEFAULT NULL,
            `sitio_web` varchar(255) DEFAULT NULL,
            `descuento` varchar(100) DEFAULT NULL,
            `categoria` varchar(100) DEFAULT NULL,
            `estado` enum('activo','inactivo','pausado') DEFAULT 'activo',
            `fecha_convenio` date DEFAULT NULL,
            `fecha_vencimiento` date DEFAULT NULL,
            `contacto_email` varchar(255) DEFAULT NULL,
            `contacto_telefono` varchar(20) DEFAULT NULL,
            `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
            `fecha_actualizacion` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_estado` (`estado`),
            KEY `idx_categoria` (`categoria`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->query($sql);
        echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla 'empresas_convenio' creada exitosamente</span><br>";
    }
    
    // 6. Verificar/Crear usuario administrador
    echo "<h2><i class='fas fa-crown'></i> Verificación de Usuario Administrador</h2>";
    
    $usuario = new Usuario();
    $adminExistente = $usuario->obtenerPorEmail('admin@clúster.com');
    
    if ($adminExistente) {
        echo "<span class='success'><i class='fas fa-circle-check'></i> Usuario administrador ya existe</span><br>";
        echo "<span class='info'><i class=\"fas fa-envelope\'></i> Email: {$adminExistente['email']}</span><br>";
        echo "<span class='info'><i class='fas fa-user'></i> Rol: {$adminExistente['rol']}</span><br>";
        echo "<span class='info'><i class='fas fa-chart-column'></i> Estado: {$adminExistente['estado']}</span><br>";
        
        // Asegurar que está activo
        if ($adminExistente['estado'] !== 'activo') {
            $usuario->cambiarEstado($adminExistente['id'], 'activo');
            echo "<span class='success'><i class='fas fa-circle-check'></i> Usuario administrador activado</span><br>";
        }
    } else {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Creando usuario administrador...</span><br>";
        
        $datosAdmin = [
            'nombre' => 'Administrador',
            'apellido' => 'Sistema',
            'email' => 'admin@clúster.com',
            'password' => 'admin123',
            'telefono' => '0000000000',
            'rol' => 'admin',
            'estado' => 'activo'
        ];
        
        $adminId = $usuario->crear($datosAdmin);
        
        if ($adminId) {
            echo "<span class='success'><i class='fas fa-circle-check'></i> Usuario administrador creado con ID: $adminId</span><br>";
            echo "<span class='info'><i class=\"fas fa-envelope\'></i> Email: admin@clúster.com</span><br>";
            echo "<span class='info'><i class=\"fas fa-key\'></i> Password: admin123</span><br>";
        } else {
            echo "<span class='error'><i class='fas fa-circle-xmark'></i> Error al crear usuario administrador</span><br>";
        }
    }
    
    // 7. Insertar datos de ejemplo
    echo "<h2><i class='fas fa-chart-column'></i> Datos de Ejemplo</h2>";
    
    // Banner de ejemplo
    $bannerExistente = $db->selectOne("SELECT COUNT(*) as total FROM banners")['total'];
    if ($bannerExistente == 0) {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Insertando banner de ejemplo...</span><br>";
        
        $db->query("INSERT INTO banners (titulo, descripcion, imagen_url, estado) VALUES (?, ?, ?, ?)", [
            'Bienvenido a Clúster',
            'Sistema de administración de contenido automotriz',
            'https://via.placeholder.com/1200x400/667eea/ffffff?text=Bienvenido+a+Clúster',
            'activo'
        ]);
        
        echo "<span class='success'><i class='fas fa-circle-check'></i> Banner de ejemplo creado</span><br>";
    } else {
        echo "<span class='success'><i class='fas fa-circle-check'></i> Ya existen banners en el sistema</span><br>";
    }
    
    // Empresa de ejemplo
    $empresaExistente = $db->selectOne("SELECT COUNT(*) as total FROM empresas_convenio")['total'];
    if ($empresaExistente == 0) {
        echo "<span class='info'><i class='fas fa-pen-to-square'></i> Insertando empresa de ejemplo...</span><br>";
        
        $db->query("INSERT INTO empresas_convenio (nombre, descripcion, descuento, categoria, estado) VALUES (?, ?, ?, ?, ?)", [
            'Empresa Ejemplo',
            'Empresa con convenio especial para miembros de Clúster',
            '15% descuento',
            'Tecnología',
            'activo'
        ]);
        
        echo "<span class='success'><i class='fas fa-circle-check'></i> Empresa de ejemplo creada</span><br>";
    } else {
        echo "<span class='success'><i class='fas fa-circle-check'></i> Ya existen empresas en el sistema</span><br>";
    }
    
    // 8. Verificación final
    echo "<h2><i class='fas fa-magnifying-glass'></i> Verificación Final</h2>";
    
    $tablas = ['usuarios', 'banners', 'boletines', 'eventos', 'empresas_convenio'];
    $todasExisten = true;
    
    foreach ($tablas as $tabla) {
        $resultado = $db->selectOne("SHOW TABLES LIKE '$tabla'");
        if ($resultado) {
            $count = $db->selectOne("SELECT COUNT(*) as total FROM $tabla")['total'];
            echo "<span class='success'><i class='fas fa-circle-check'></i> Tabla '$tabla': $count registros</span><br>";
        } else {
            echo "<span class='error'><i class='fas fa-circle-xmark'></i> Tabla '$tabla': No existe</span><br>";
            $todasExisten = false;
        }
    }
    
    if ($todasExisten) {
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #c3e6cb;'>";
        echo "<h3 style='color: #155724; margin: 0;'><i class='fas fa-champagne-glasses'></i> ¡Configuración Completada!</h3>";
        echo "<p style='color: #155724; margin: 10px 0 0 0;'>Todas las tablas han sido creadas exitosamente. El sistema está listo para usar.</p>";
        echo "</div>";
        
        echo "<h3><i class='fas fa-link'></i> Próximos Pasos</h3>";
        echo "<p><a href='../pages/sign-in.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'><i class='fas fa-lock'></i> Ir al Login</a></p>";
        echo "<p><strong>Credenciales:</strong> admin@clúster.com / admin123</p>";
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #f5c6cb;'>";
        echo "<h3 style='color: #721c24; margin: 0;'><i class='fas fa-circle-xmark'></i> Error en la Configuración</h3>";
        echo "<p style='color: #721c24; margin: 10px 0 0 0;'>Algunas tablas no se pudieron crear. Verifica los permisos de la base de datos.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #f5c6cb;'>";
    echo "<h3 style='color: #721c24; margin: 0;'><i class='fas fa-bolt'></i> Error Fatal</h3>";
    echo "<p style='color: #721c24; margin: 10px 0 0 0;'>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Configuración ejecutada el " . date('d/m/Y H:i:s') . "</small></p>";
?>