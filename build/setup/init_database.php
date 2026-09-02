<?php
/**
 * Script de inicialización de la base de datos
 * Ejecuta la instalación inicial de la base de datos
 */

// Definir acceso permitido
define('CLAUT_ACCESS', true);

require_once '../includes/config.php';

echo "<h1>Inicialización de Base de Datos - Clúster Intranet</h1>";

try {
    // Obtener configuración de la base de datos
    $config = DatabaseConfig::getConfig();
    
    echo "<p>Conectando a la base de datos...</p>";
    
    // Crear conexión sin especificar base de datos para poder crearla
    $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p><i class=\"fas fa-check\"></i> Conexión establecida correctamente</p>";
    
    // Leer y ejecutar el script SQL
    $sqlFile = __DIR__ . '/install_database.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo SQL no encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    $statements = explode(';', $sql);
    
    echo "<p>Ejecutando script de base de datos...</p>";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignorar errores de "ya existe" pero mostrar otros
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "<p style='color: orange;'>Advertencia: " . $e->getMessage() . "</p>";
                }
            }
        }
    }
    
    echo "<p><i class=\"fas fa-check\"></i> Script SQL ejecutado correctamente</p>";
    
    // Verificar que las tablas se crearon correctamente
    $pdo->exec("USE {$config['database']}");
    
    $tables = ['usuarios', 'empresas', 'comites', 'eventos', 'boletines', 'descuentos'];
    
    echo "<h3>Verificando tablas:</h3>";
    echo "<ul>";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            // Contar registros
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $countStmt->fetch()['count'];
            echo "<li><i class=\"fas fa-check\"></i> Tabla <strong>$table</strong>: $count registros</li>";
        } else {
            echo "<li><i class=\"fas fa-xmark\"></i> Tabla <strong>$table</strong>: NO ENCONTRADA</li>";
        }
    }
    
    echo "</ul>";
    
    // Verificar usuario administrador
    $adminStmt = $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'admin'");
    $adminCount = $adminStmt->fetch()['count'];
    
    if ($adminCount > 0) {
        echo "<p><i class=\"fas fa-check\"></i> Usuario administrador creado correctamente</p>";
        echo "<p><strong>Credenciales de administrador:</strong></p>";
        echo "<ul>";
        echo "<li>Email: admin@clúster.com</li>";
        echo "<li>Contraseña: password</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'><i class=\"fas fa-xmark\"></i> No se encontró usuario administrador</p>";
    }

    echo "<hr>";
    echo "<h3><i class=\"fas fa-circle-check\"></i> Base de datos inicializada correctamente</h3>";
    echo "<p>La aplicación está lista para usar.</p>";
    echo "<p><a href='../index.php' style='background: #3B82F6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a></p>";
    echo "<p><a href='../pages/sign-in.html' style='background: #10B981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Iniciar Sesión</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><i class=\"fas fa-circle-xmark\"></i> Error: " . $e->getMessage() . "</p>";
    echo "<p>Por favor, verifica la configuración de la base de datos en includes/config.php</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inicialización - Clúster Intranet</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        h1 { color: #333; }
        h3 { color: #666; }
        p { margin: 10px 0; }
        ul { margin: 10px 0; }
        li { margin: 5px 0; }
        hr { margin: 30px 0; }
    </style>
</head>
<body>
</body>
</html>