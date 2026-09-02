<?php
/**
 * Acceso directo al panel de admin (sin middleware)
 * Solo para debugging y acceso de emergencia
 */

require_once '../config/database.php';

// No usar middleware automático
define('ADMIN_MIDDLEWARE_SKIP_AUTO', true);

// Verificar si viene de un login exitoso con token
if (isset($_GET['from_login']) && $_GET['from_login'] === 'true') {
    // Si viene del login principal, verificar token en localStorage via JavaScript
    // y establecer sesión automáticamente
    echo '<script>
        const token = localStorage.getItem("clúster_token");
        const user = localStorage.getItem("clúster_user");
        
        if (token && user) {
            const userData = JSON.parse(user);
            if (userData.rol === "admin") {
                console.log("🔑 Token encontrado, estableciendo sesión admin...");
                // Enviar datos al servidor para establecer sesión
                fetch("establecer-sesion.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        token: token,
                        user: userData
                    })
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log("✅ Sesión establecida, redirigiendo...");
                        window.location.href = "admin-dashboard.php";
                    } else {
                        console.error("❌ Error estableciendo sesión:", data.message);
                    }
                });
            }
        }
    </script>';
}

// Intentar obtener usuario admin directamente
try {
    $db = Database::getInstance();
    $usuario = new Usuario();
    
    // Si hay datos de login en POST, procesarlos
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
        $userData = $usuario->login($_POST['email'], $_POST['password']);
        
        if ($userData && $userData['rol'] === 'admin') {
            // Login exitoso, establecer sesión
            iniciarSesion();
            $_SESSION['usuario_id'] = $userData['id'];
            $_SESSION['admin_directo'] = true;
            
            header('Location: admin-dashboard.php');
            exit;
        } else {
            $error = 'Credenciales inválidas o no es administrador';
        }
    }
    
    // Verificar si ya hay sesión
    iniciarSesion();
    if (isset($_SESSION['admin_directo']) && isset($_SESSION['usuario_id'])) {
        $userData = $usuario->obtenerPorId($_SESSION['usuario_id']);
        if ($userData && $userData['rol'] === 'admin') {
            header('Location: admin-dashboard.php');
            exit;
        }
    }
    
} catch (Exception $e) {
    $error = 'Error de conexión: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Directo Admin - Clúster</title>
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 90%;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center; color: #333;"> Acceso Admin</h1>
        
        <div class="info">
            <strong></strong><br>
            Por tu seguridad Vuelve a ingresar tus Credenciales
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><i class="fas fa-circle-xmark"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email de Administrador:</label>
                <input type="email" id="email" name="email" value="admin@clúster.com" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-key"></i> Contraseña:</label>
                <input type="password" id="password" name="password" value="admin123" required>
            </div>
            
            <button type="submit" class="btn">Acceder</button>
        </form>
    </div>
</body>
</html>