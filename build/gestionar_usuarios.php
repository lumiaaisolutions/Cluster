<?php
// gestionar_usuarios.php - Panel para gestionar usuarios registrados
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/config/database.php';
} catch (Exception $e) {
    die('Error cargando database.php: ' . $e->getMessage());
}

// Iniciar sesión básica
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($action === 'crear_admin') {
        // Crear usuario administrador
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Datos predeterminados para el admin
            $admin_email = 'administrador@clúster.com';
            $admin_password = 'admin123';
            
            // Verificar si ya existe un admin con este email
            $stmt = $conn->prepare("SELECT id FROM usuarios_perfil WHERE email = ?");
            $stmt->execute([$admin_email]);
            $existing_admin = $stmt->fetchAll();
            
            if (empty($existing_admin)) {
                // Crear el administrador directamente en la base de datos
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO usuarios_perfil (nombre, apellidos, email, password, telefono, rol) VALUES (?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    'Administrador',
                    'Sistema', 
                    $admin_email,
                    $hashed_password,
                    '0000000000',
                    'admin'
                ]);
                
                $message = $result ? 'Administrador creado exitosamente. Email: administrador@clúster.com, Password: admin123' : 'Error al crear administrador.';
            } else {
                $message = 'Ya existe un administrador con el email administrador@clúster.com';
                $result = false;
            }
        } catch (Exception $e) {
            $message = 'Error al crear administrador: ' . $e->getMessage();
            $result = false;
        }
    } elseif ($user_id > 0) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            switch ($action) {
                case 'activar':
                    $stmt = $conn->prepare("UPDATE usuarios_perfil SET estado_usuario = 'activo', ultima_actividad = NOW() WHERE id = ?");
                    $result = $stmt->execute([$user_id]);
                    $message = $result ? 'Usuario activado exitosamente.' : 'Error al activar usuario.';
                    break;
                    
                case 'desactivar':
                    $stmt = $conn->prepare("UPDATE usuarios_perfil SET estado_usuario = 'inactivo' WHERE id = ?");
                    $result = $stmt->execute([$user_id]);
                    $message = $result ? 'Usuario desactivado exitosamente.' : 'Error al desactivar usuario.';
                    break;
                    
                case 'eliminar':
                    $stmt = $conn->prepare("DELETE FROM usuarios_perfil WHERE id = ?");
                    $result = $stmt->execute([$user_id]);
                    $message = $result ? 'Usuario eliminado exitosamente.' : 'Error al eliminar usuario.';
                    break;
                    
                case 'editar':
                    // Procesar edición de usuario con todos los campos del sign-up
                    $nombre = trim($_POST['nombre'] ?? '');
                    $apellidos = trim($_POST['apellidos'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $telefono = trim($_POST['telefono'] ?? '');
                    $rol = $_POST['rol'] ?? 'empleado';
                    $nueva_password = trim($_POST['nueva_password'] ?? '');
                    $confirmar_password = trim($_POST['confirmar_password'] ?? '');

                    // Campos adicionales del sign-up
                    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
                    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
                    $biografia = trim($_POST['biografia'] ?? '');
                    $direccion = trim($_POST['direccion'] ?? '');
                    $ciudad = trim($_POST['ciudad'] ?? '');
                    $estado = trim($_POST['estado'] ?? '');
                    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
                    $pais = trim($_POST['pais'] ?? 'México');
                    $telefono_emergencia = trim($_POST['telefono_emergencia'] ?? '');
                    $contacto_emergencia = trim($_POST['contacto_emergencia'] ?? '');
                    $empresa_id = !empty($_POST['empresa_id']) ? intval($_POST['empresa_id']) : null;
                    
                    // Validaciones básicas
                    if (empty($nombre) || empty($email)) {
                        $message = 'Nombre y email son obligatorios.';
                        $result = false;
                        break;
                    }
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $message = 'Email no válido.';
                        $result = false;
                        break;
                    }
                    
                    // Verificar si el email ya existe (excepto para el usuario actual)
                    $stmt = $conn->prepare("SELECT id FROM usuarios_perfil WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $message = 'El email ya está en uso por otro usuario.';
                        $result = false;
                        break;
                    }
                    
                    // Inicializar debug_info
                    $debug_info = "";
                    
                    // Validar cambio de contraseña si se proporcionó
                    $password_hash = null;
                    if (!empty($nueva_password)) {
                        if (strlen($nueva_password) < 6) {
                            $message = 'La nueva contraseña debe tener al menos 6 caracteres.';
                            $result = false;
                            break;
                        }
                        
                        if ($nueva_password !== $confirmar_password) {
                            $message = 'Las contraseñas no coinciden.';
                            $result = false;
                            break;
                        }
                        
                        $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                        $debug_info .= " [DEBUG: Contraseña será actualizada]";
                    }
                    
                    // Procesar imagen si se subió
                    $avatar_path = null;
                    
                    // Debug: mostrar información de archivos
                    if (isset($_FILES['avatar'])) {
                        $debug_info .= " [DEBUG: Avatar file - name: " . $_FILES['avatar']['name'] . ", error: " . $_FILES['avatar']['error'] . ", size: " . $_FILES['avatar']['size'] . "]";
                    } else {
                        $debug_info .= " [DEBUG: No se recibió archivo avatar]";
                    }
                    
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = 'uploads/avatars/';
                        
                        // Crear directorio si no existe
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_info = pathinfo($_FILES['avatar']['name']);
                        $extension = strtolower($file_info['extension']);
                        
                        // Validar tipo de archivo
                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                        if (!in_array($extension, $allowed_types)) {
                            $message = 'Solo se permiten imágenes JPG, PNG o GIF.';
                            $result = false;
                            break;
                        }
                        
                        // Validar tamaño (máximo 5MB)
                        if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
                            $message = 'La imagen no puede superar los 5MB.';
                            $result = false;
                            break;
                        }
                        
                        // Generar nombre único
                        $avatar_filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
                        $avatar_path = $upload_dir . $avatar_filename;
                        
                        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
                            $message = 'Error al subir la imagen.';
                            $result = false;
                            break;
                        } else {
                            $debug_info .= " [DEBUG: Imagen guardada en: $avatar_path]";
                        }
                    }
                    
                    // Actualizar usuario con todos los campos
                    if ($avatar_path && $password_hash) {
                        // Con nueva imagen y contraseña
                        $debug_info .= " [DEBUG: Actualizando con avatar y contraseña]";
                        $stmt = $conn->prepare("UPDATE usuarios_perfil SET nombre = ?, apellidos = ?, email = ?, telefono = ?, rol = ?, avatar = ?, password = ?, fecha_nacimiento = ?, nombre_empresa = ?, biografia = ?, direccion = ?, ciudad = ?, estado = ?, codigo_postal = ?, pais = ?, telefono_emergencia = ?, contacto_emergencia = ?, empresa_id = ? WHERE id = ?");
                        $result = $stmt->execute([$nombre, $apellidos, $email, $telefono, $rol, $avatar_path, $password_hash, $fecha_nacimiento, $nombre_empresa, $biografia, $direccion, $ciudad, $estado, $codigo_postal, $pais, $telefono_emergencia, $contacto_emergencia, $empresa_id, $user_id]);
                    } elseif ($avatar_path) {
                        // Solo nueva imagen
                        $debug_info .= " [DEBUG: Actualizando con avatar: $avatar_path]";
                        $stmt = $conn->prepare("UPDATE usuarios_perfil SET nombre = ?, apellidos = ?, email = ?, telefono = ?, rol = ?, avatar = ?, fecha_nacimiento = ?, nombre_empresa = ?, biografia = ?, direccion = ?, ciudad = ?, estado = ?, codigo_postal = ?, pais = ?, telefono_emergencia = ?, contacto_emergencia = ?, empresa_id = ? WHERE id = ?");
                        $result = $stmt->execute([$nombre, $apellidos, $email, $telefono, $rol, $avatar_path, $fecha_nacimiento, $nombre_empresa, $biografia, $direccion, $ciudad, $estado, $codigo_postal, $pais, $telefono_emergencia, $contacto_emergencia, $empresa_id, $user_id]);
                    } elseif ($password_hash) {
                        // Solo nueva contraseña
                        $debug_info .= " [DEBUG: Actualizando con nueva contraseña]";
                        $stmt = $conn->prepare("UPDATE usuarios_perfil SET nombre = ?, apellidos = ?, email = ?, telefono = ?, rol = ?, password = ?, fecha_nacimiento = ?, nombre_empresa = ?, biografia = ?, direccion = ?, ciudad = ?, estado = ?, codigo_postal = ?, pais = ?, telefono_emergencia = ?, contacto_emergencia = ?, empresa_id = ? WHERE id = ?");
                        $result = $stmt->execute([$nombre, $apellidos, $email, $telefono, $rol, $password_hash, $fecha_nacimiento, $nombre_empresa, $biografia, $direccion, $ciudad, $estado, $codigo_postal, $pais, $telefono_emergencia, $contacto_emergencia, $empresa_id, $user_id]);
                    } else {
                        // Sin cambio de imagen ni contraseña
                        $debug_info .= " [DEBUG: Actualizando datos completos sin avatar ni contraseña]";
                        $stmt = $conn->prepare("UPDATE usuarios_perfil SET nombre = ?, apellidos = ?, email = ?, telefono = ?, rol = ?, fecha_nacimiento = ?, nombre_empresa = ?, biografia = ?, direccion = ?, ciudad = ?, estado = ?, codigo_postal = ?, pais = ?, telefono_emergencia = ?, contacto_emergencia = ?, empresa_id = ? WHERE id = ?");
                        $result = $stmt->execute([$nombre, $apellidos, $email, $telefono, $rol, $fecha_nacimiento, $nombre_empresa, $biografia, $direccion, $ciudad, $estado, $codigo_postal, $pais, $telefono_emergencia, $contacto_emergencia, $empresa_id, $user_id]);
                    }
                    
                    $debug_info .= " [DEBUG: Update result: " . ($result ? 'SUCCESS' : 'FAILED') . "]";
                    
                    if ($result) {
                        $message = 'Usuario actualizado exitosamente.' . $debug_info;
                    } else {
                        $message = 'Error al actualizar usuario.' . $debug_info;
                    }
                    break;
                    
                default:
                    $message = 'Acción no válida.';
                    $result = false;
            }
        } catch (Exception $e) {
            $message = 'Error al procesar la acción: ' . $e->getMessage();
            $result = false;
        }
    }
}

// Obtener usuarios
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT u.id, u.nombre, u.apellidos, u.email, u.telefono, u.rol, 
               u.estado_usuario,
               CASE WHEN u.ultima_actividad IS NOT NULL THEN 1 ELSE 0 END as online_status, 
               COALESCE(u.fecha_ingreso, u.created_at) as fecha_registro, u.user_id, e.nombre_empresa,
               u.departamento
        FROM usuarios_perfil u
        LEFT JOIN empresas_convenio e ON u.user_id = e.id
        ORDER BY u.fecha_ingreso DESC
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error al cargar usuarios: " . $e->getMessage();
    $usuarios = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Clúster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./css/claut-ui.css?v=20260902a">
    <link rel="stylesheet" href="./assets/css/layout/admin-sidebar.css?v=20260901c">
    <link rel="stylesheet" href="./assets/css/layout/claut-wizard.css?v=20260901a">
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }

        .modal-backdrop { backdrop-filter: blur(5px); }
        .slide-in { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0;
        }
        
        .content-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }
        
        .header-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .users-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .users-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-activo {
            background: #d1f2eb;
            color: #0c5460;
        }
        
        .status-pendiente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-inactivo {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .search-box {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 300px;
        }
        
        @media (max-width: 768px) {
            .users-table {
                font-size: 0.9rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .toolbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .search-box {
                width: 100%;
            }
        }

        .container {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }

        /* Desktop adjustments */
        @media (min-width: 1280px) {
            .container {
                padding-left: 2rem !important;
                padding-right: 2rem !important;
            }
        }

        /* ============================================================
           RESPONSIVE MASTER & PREMIUM DARK UI
        ============================================================ */
        body { background-color: #0f1115 !important; color: #f1f5f9 !important; }
        .glass-card {
            background: rgba(20, 20, 22, 0.65) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .glass-card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
        }
        .header-gradient-premium {
            background: radial-gradient(circle at top right, rgba(139, 92, 246, 0.1) 0%, rgba(15, 15, 15, 0.85) 60%),
                        linear-gradient(180deg, rgba(20, 20, 22, 0.95) 0%, rgba(10, 10, 15, 0.98) 100%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6) !important;
        }
        
        /* Stats Cards Update */
        .bg-white.rounded-lg.shadow.p-4, .bg-white.rounded-lg.shadow.p-6, .content-container {
            background: rgba(25, 25, 28, 0.6) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 16px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            color: #f1f5f9 !important;
        }
        .claut-text-primary { color: #f8f9fa !important; font-weight: 700 !important; }
        .claut-text-muted, .claut-text-secondary { color: #9ca3af !important; }
        
        /* Table Styles */
        .users-table th { 
            background-color: rgba(0, 0, 0, 0.35) !important; 
            color: #f8f9fa !important; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important; 
            font-weight: 600 !important;
        }
        .users-table td { 
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important; 
            color: #cbd5e1 !important; 
        }
        .users-table tr:hover { background-color: rgba(255, 255, 255, 0.04) !important; }
        
        /* Action Buttons Premium */
        .btn-primary, .btn-success, .btn.bg-green-600 {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            border: 1px solid rgba(255,255,255,0.2) !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important;
            transition: all 0.3s ease !important;
        }
        .btn-primary:focus, .btn-success:hover { filter: brightness(1.1); transform: translateY(-1px); }

        /* Responsive Fixes */
        .main-content {
            padding-left: clamp(0.75rem, 3vw, 3rem) !important;
            padding-right: clamp(0.75rem, 3vw, 3rem) !important;
            overflow-x: hidden !important;
        }
        html, body {
            overflow-x: hidden !important;
            max-width: 100vw !important;
        }
        @media (max-width: 767px) {
            .container { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
        }
    </style>
<link rel="stylesheet" href="./css/claut-admin-elite.css?v=20260909a">
</head>
<body class="claut-dark">
    <aside class="claut-admin-sidebar" id="claut-admin-sidebar">
        <div class="claut-admin-sidebar-brand">
            <img src="./assets/img/apple-icon.png" alt="Clúster Metropolitano" class="claut-admin-sidebar-logo">
            <span class="claut-admin-sidebar-title">Clúster Admin</span>
        </div>
        <div class="claut-admin-sidebar-divider"></div>

        <nav class="claut-admin-nav">
            <div class="claut-admin-nav-group">
                <p class="claut-admin-nav-label">General</p>
                <a href="admin-panel.html?login=success" class="claut-admin-nav-item">
                    <i class="fas fa-shield-halved"></i><span>Panel Admin</span>
                </a>
                <a href="dashboard.html" class="claut-admin-nav-item">
                    <i class="fas fa-house"></i><span>Dashboard</span>
                </a>
            </div>

            <div class="claut-admin-nav-group">
                <p class="claut-admin-nav-label">Ecosistema de módulos</p>
                <a href="admin/banner-admin-mejorado.php" class="claut-admin-nav-item">
                    <i class="fas fa-images"></i><span>Banners</span>
                </a>
                <a href="demo_boletines.html" class="claut-admin-nav-item">
                    <i class="fas fa-newspaper"></i><span>Boletines</span>
                </a>
                <a href="demo_documentos.html" class="claut-admin-nav-item">
                    <i class="fas fa-folder-open"></i><span>Documentos</span>
                </a>
                <a href="demo_descuentos.html" class="claut-admin-nav-item">
                    <i class="fas fa-tags"></i><span>Beneficios</span>
                </a>
                <a href="demo_comite.html" class="claut-admin-nav-item">
                    <i class="fas fa-people-group"></i><span>Comités</span>
                </a>
                <a href="calendario.html" class="claut-admin-nav-item">
                    <i class="fas fa-calendar-days"></i><span>Calendario</span>
                </a>
                <a href="demo_empresas.html" class="claut-admin-nav-item">
                    <i class="fas fa-building"></i><span>Socios</span>
                </a>
                <a href="demo_evento.html" class="claut-admin-nav-item">
                    <i class="fas fa-calendar-check"></i><span>Eventos</span>
                </a>
                <a href="gestionar_usuarios.php" class="claut-admin-nav-item active">
                    <i class="fas fa-users"></i><span>Usuarios</span>
                </a>
                <a href="demo_visitante.html" class="claut-admin-nav-item">
                    <i class="fas fa-user-shield"></i><span>Visitantes</span>
                </a>
            </div>

            <div class="claut-admin-nav-group">
                <p class="claut-admin-nav-label">Sistema</p>
                <a href="profile.html" class="claut-admin-nav-item">
                    <i class="fas fa-gear"></i><span>Configuración</span>
                </a>
            </div>
        </nav>

        <div class="claut-admin-sidebar-foot">
            <button onclick="window.location.href='pages/sign-in.html'" class="claut-admin-nav-item claut-admin-nav-item--danger">
                <i class="fas fa-sign-out-alt"></i><span>Cerrar sesión</span>
            </button>
        </div>
    </aside>

    <div class="claut-admin-sidebar-overlay" id="claut-admin-sidebar-overlay"></div>

    <div class="main-content">
    <!-- Header - Premium Redesign -->
    <header class="claut-admin-main header-gradient-premium text-white shadow-lg sticky top-0 z-40">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <button class="mr-5 p-3 bg-white/5 hover:bg-white/10 rounded-xl backdrop-blur-md border border-white/10 text-white/90"
                            id="claut-header-menu-btn"
                            style="transition: background .2s ease;"
                            aria-label="Abrir menú de navegación">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight flex items-center">
                            <span class="bg-gradient-to-r from-red-500 to-red-600 bg-clip-text text-transparent mr-3">
                                <i class="fas fa-users-cog"></i>
                            </span>
                            Gestión de Usuarios
                        </h1>
                        <div class="flex items-center mt-1">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse mr-2"></span>
                            <p class="claut-text-muted text-xs font-medium uppercase tracking-widest">Panel Administrativo Premium</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="location.href='dashboard.html'" class="group px-5 py-2.5 bg-white/5 hover:bg-white/10 text-white rounded-xl transition-all duration-300 border border-white/10 flex items-center backdrop-blur-md">
                        <i class="fas fa-th-large mr-2 text-red-500 group-hover:scale-110 transition-transform"></i>
                        <span class="text-sm font-semibold">Dashboard</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="claut-admin-main container mx-auto px-6 py-8">
        <!-- Stats Cards Redesign -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <?php
            $stats = [
                'total' => count($usuarios),
                'activos' => count(array_filter($usuarios, fn($u) => ($u['estado_usuario'] ?? '') === 'activo')),
                'inactivos' => count(array_filter($usuarios, fn($u) => ($u['estado_usuario'] ?? '') !== 'activo')),
                'administradores' => count(array_filter($usuarios, fn($u) => $u['rol'] === 'admin'))
            ];
            ?>
            <!-- Total Card -->
            <div class="claut-stat-card claut-stat-card--red">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Total Usuarios</p>
                        <p class="stat-value"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
            </div>

            <!-- Activos Card -->
            <div class="claut-stat-card claut-stat-card--green">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Usuarios Activos</p>
                        <p class="stat-value"><?php echo $stats['activos']; ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                </div>
            </div>

            <!-- Inactivos Card -->
            <div class="claut-stat-card claut-stat-card--gold">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Inactivos / Pendientes</p>
                        <p class="stat-value"><?php echo $stats['inactivos']; ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                </div>
            </div>

            <!-- Admins Card -->
            <div class="claut-stat-card claut-stat-card--blue">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Administradores</p>
                        <p class="stat-value"><?php echo $stats['administradores']; ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                </div>
            </div>
        </div>

    <div class="container mx-auto px-4 max-w-7xl">
        
        <?php if (isset($message)): ?>
            <div class="px-4 py-3 rounded mb-4" style="background: <?php echo $result ? 'var(--surface-success)' : 'var(--surface-danger)'; ?>; border: 1px solid <?php echo $result ? 'rgba(34,197,94,0.3)' : 'rgba(239,68,68,0.3)'; ?>; color: <?php echo $result ? 'var(--state-success)' : 'var(--state-danger)'; ?>;">
                <i class="fas fa-<?php echo $result ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="px-4 py-3 rounded mb-4" style="background: var(--surface-danger); border: 1px solid rgba(239,68,68,0.3); color: var(--state-danger);">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Controles principales -->
        <div class="flex justify-center space-x-4 mb-6">
            <a href="pages/sign-up.html" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition flex items-center">
                <i class="fas fa-plus mr-2"></i>Nuevo Usuario
            </a>
            <button onclick="abrirModalMensajeria()" class="text-white px-6 py-2 rounded-lg hover:opacity-90 transition flex items-center" style="background: linear-gradient(135deg, #C7252B 0%, #A01E24 100%);">
                <i class="fas fa-envelope mr-2"></i>Enviar Mensaje
            </button>
        </div>

        <!-- Premium Section: Notifications -->
        <div class="glass-card mb-10 overflow-hidden">
            <div class="px-6 py-5 border-b border-white/5 bg-white/5 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-xl bg-red-600/10 flex items-center justify-center mr-4 border border-red-600/20">
                        <i class="fas fa-bell text-red-500"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white tracking-tight">Solicitudes de Cambios de Perfil</h2>
                        <p class="claut-text-muted text-xs">Revisiones pendientes de datos de usuario</p>
                    </div>
                </div>
                <button onclick="loadProfileNotifications()" class="flex items-center space-x-2 px-4 py-2 bg-white/5 hover:bg-white/10 text-white text-xs font-bold rounded-lg border border-white/10 transition-all">
                    <i class="fas fa-sync-alt animate-hover"></i>
                    <span>Actualizar</span>
                </button>
            </div>
            <div id="profileNotificationsContainer" class="p-0">
                <div class="flex flex-col items-center justify-center py-16 claut-text-muted">
                    <i class="fas fa-circle-notch fa-spin text-3xl mb-4 text-red-500/50"></i>
                    <p class="text-sm font-medium tracking-wide">Sincronizando notificaciones...</p>
                </div>
            </div>
        </div>

        <!-- Premium Section: User List -->
        <div class="glass-card">
            <!-- Toolbar Redesign -->
            <div class="px-6 py-6 border-b border-white/5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center mr-4 border border-white/5 shadow-2xl">
                        <i class="fas fa-users-viewfinder text-red-500 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-black text-white tracking-tighter">Directorio de Usuarios</h2>
                        <p class="claut-text-muted text-xs font-medium tracking-wide uppercase">Gestión de Accesos y Roles</p>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative group">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 claut-text-muted group-focus-within:text-red-500 transition-colors"></i>
                        <input type="text" id="searchBox" 
                               class="w-full sm:w-80 pl-11 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition-all placeholder:claut-text-secondary" 
                               placeholder="Filtrar por nombre, email o rol..." 
                               onkeyup="filterUsers()">
                    </div>
                    <button onclick="location.href='pages/sign-up.html'" class="px-6 py-2.5 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 text-white font-bold rounded-xl shadow-lg shadow-red-900/40 border border-white/10 transition-all flex items-center justify-center whitespace-nowrap">
                        <i class="fas fa-plus-circle mr-2"></i>
                        <span>Nuevo Usuario</span>
                    </button>
                </div>
            </div>
        
            <!-- Directorio en tarjetas (elite v2) + filtros por rol -->
            <div class="elite-filter-chips mb-5" id="rolChips">
                <button type="button" class="on" data-rol="" onclick="setRolFilter(this)">Todos</button>
                <button type="button" data-rol="admin" onclick="setRolFilter(this)">Administradores</button>
                <button type="button" data-rol="empresa" onclick="setRolFilter(this)">Empresas</button>
                <button type="button" data-rol="empleado" onclick="setRolFilter(this)">Empleados</button>
            </div>
            <div>
                <div class="elite-grid" id="usersTable">
                <?php if (empty($usuarios)): ?>
                    <div class="py-12 text-center" style="grid-column:1/-1;">
                        <i class="fas fa-users-slash text-4xl text-white/10 mb-4 block"></i>
                        <span class="claut-text-muted font-medium tracking-wide">No se encontraron usuarios en la base de datos.</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($usuarios as $usuario): ?>
                        <?php 
                        // Usar el estado real de la base de datos
                        $estado_real = $usuario['estado_usuario'] ?? 'pendiente';
                        $estado_class = 'status-pendiente'; // Default
                        
                        // Determinar clase basada en el estado
                        if ($estado_real === 'activo') {
                            $estado_class = 'status-activo';
                        } elseif ($estado_real === 'inactivo' || $estado_real === 'rechazado') {
                            $estado_class = 'status-inactivo';
                        }
                        ?>
                        <?php
                        $rol_map = ['admin' => 'Administrador', 'empresa' => 'Empresa', 'empleado' => 'Empleado'];
                        $rol_colores = ['admin' => 'linear-gradient(135deg,#e23238,#7f1d1d)', 'empresa' => 'linear-gradient(135deg,#3b82f6,#1e3a8a)', 'empleado' => 'linear-gradient(135deg,#475569,#1e293b)'];
                        $iniciales = strtoupper(mb_substr($usuario['nombre'] ?? '?', 0, 1) . mb_substr($usuario['apellidos'] ?? '', 0, 1));
                        $badge_estado = $estado_real === 'activo' ? 'claut-badge--success' : ($estado_real === 'pendiente' ? 'claut-badge--warning' : 'claut-badge--neutral');
                        $search_blob = strtolower(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellidos'] ?? '') . ' ' . ($usuario['email'] ?? '') . ' ' . ($rol_map[$usuario['rol']] ?? $usuario['rol']) . ' ' . ($usuario['nombre_empresa'] ?? ''));
                        ?>
                        <div class="elite-card" data-rol="<?php echo htmlspecialchars($usuario['rol']); ?>" data-search="<?php echo htmlspecialchars($search_blob); ?>">
                            <div class="elite-card-body">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div style="width:44px;height:44px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;background:<?php echo $rol_colores[$usuario['rol']] ?? $rol_colores['empleado']; ?>;"><?php echo htmlspecialchars($iniciales); ?></div>
                                    <div style="min-width:0;flex:1;">
                                        <h4 style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . ($usuario['apellidos'] ?? '')); ?></h4>
                                        <div class="elite-card-desc" style="-webkit-line-clamp:1;"><?php echo htmlspecialchars($usuario['email']); ?></div>
                                    </div>
                                    <span class="claut-badge <?php echo $badge_estado; ?>"><?php echo ucfirst($estado_real); ?></span>
                                </div>
                                <div class="elite-card-meta">
                                    <div class="row"><i class="fas fa-id-badge"></i><?php echo $rol_map[$usuario['rol']] ?? htmlspecialchars($usuario['rol']); ?><?php if (!empty($usuario['cargo'])): ?> · <?php echo htmlspecialchars($usuario['cargo']); ?><?php endif; ?></div>
                                    <?php if (!empty($usuario['nombre_empresa'])): ?><div class="row"><i class="fas fa-building"></i><?php echo htmlspecialchars($usuario['nombre_empresa']); ?></div><?php endif; ?>
                                    <?php if (!empty($usuario['telefono'])): ?><div class="row"><i class="fas fa-phone"></i><?php echo htmlspecialchars($usuario['telefono']); ?></div><?php endif; ?>
                                    <div class="row"><i class="fas fa-calendar-check"></i><?php echo ($usuario['fecha_registro'] && $usuario['fecha_registro'] != '0000-00-00 00:00:00') ? date('d/m/Y', strtotime($usuario['fecha_registro'])) : 'Sin fecha'; ?> · ID <?php echo $usuario['id']; ?></div>
                                </div>
                                <div class="elite-card-foot">
                                    <div class="elite-actions">
                                        <button type="button" class="elite-btn elite-btn--edit" onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($usuario)); ?>)"><i class="fas fa-edit"></i>Editar</button>
                                        <button type="button" class="elite-btn elite-btn--view" onclick="abrirModalRestricciones(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?>')"><i class="fas fa-lock"></i>Accesos</button>
                                    </div>
                                    <div class="elite-actions">
                                        <?php if ($estado_real !== 'activo'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="activar">
                                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                            <button type="submit" class="elite-btn" style="background:rgba(52,211,153,.16);color:#34d399;" onclick="return confirm('¿Activar este usuario?')"><i class="fas fa-circle-check"></i>Activar</button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="desactivar">
                                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                            <button type="submit" class="elite-btn" style="background:rgba(255,255,255,.09);color:#a2a8b3;" onclick="return confirm('¿Desactivar este usuario?')"><i class="fas fa-circle-xmark"></i>Pausar</button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="eliminar">
                                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                            <button type="submit" class="elite-btn elite-btn--del" onclick="return confirm('¿ELIMINAR permanentemente este usuario? Esta acción no se puede deshacer.')"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
        
        
    </div>
    
    <script>
        let rolActivo = '';
        function setRolFilter(btn) {
            rolActivo = btn.dataset.rol || '';
            document.querySelectorAll('#rolChips button').forEach(b => b.classList.toggle('on', b === btn));
            filterUsers();
        }
        function filterUsers() {
            const searchTerm = (document.getElementById('searchBox').value || '').toLowerCase();
            document.querySelectorAll('#usersTable .elite-card').forEach(card => {
                const matchTexto = !searchTerm || (card.dataset.search || '').includes(searchTerm);
                const matchRol = !rolActivo || card.dataset.rol === rolActivo;
                card.style.display = (matchTexto && matchRol) ? '' : 'none';
            });
        }
        
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
        
        // Funciones para el modal de edición
        function abrirModalEditar(usuario) {
            console.log('Abriendo modal para usuario:', usuario);
            
            // Llenar los campos del formulario
            document.getElementById('edit_user_id').value = usuario.id;
            document.getElementById('edit_nombre').value = usuario.nombre || '';
            document.getElementById('edit_apellidos').value = usuario.apellidos || '';
            document.getElementById('edit_email').value = usuario.email || '';
            document.getElementById('edit_telefono').value = usuario.telefono || '';
            document.getElementById('edit_rol').value = usuario.rol || 'empleado';
            document.getElementById('edit_fecha_nacimiento').value = usuario.fecha_nacimiento || '';
            document.getElementById('edit_nombre_empresa').value = usuario.nombre_empresa || '';
            document.getElementById('edit_direccion').value = usuario.direccion || '';
            document.getElementById('edit_ciudad').value = usuario.ciudad || '';
            document.getElementById('edit_estado').value = usuario.estado || '';
            document.getElementById('edit_codigo_postal').value = usuario.codigo_postal || '';
            document.getElementById('edit_pais').value = usuario.pais || 'México';
            document.getElementById('edit_telefono_emergencia').value = usuario.telefono_emergencia || '';
            document.getElementById('edit_contacto_emergencia').value = usuario.contacto_emergencia || '';
            document.getElementById('edit_biografia').value = usuario.biografia || '';
            document.getElementById('edit_empresa_id').value = usuario.empresa_id || '';
            
            // Cambio dinámico de etiqueta en el modal
            const labelFecha = document.querySelector('label[for="edit_fecha_nacimiento"]') || 
                               document.getElementById('edit_fecha_nacimiento').previousElementSibling;
            if (labelFecha) {
                labelFecha.textContent = usuario.rol === 'empresa' ? 'Fecha de Fundación de la Empresa' : 'Fecha de Nacimiento';
            }
            
            // Limpiar campos de contraseña
            document.getElementById('nueva_password').value = '';
            document.getElementById('confirmar_password').value = '';
            document.getElementById('mostrar_passwords').checked = false;
            
            // Mostrar imagen actual si existe
            const currentImage = document.getElementById('current_avatar');
            const imagePreview = document.getElementById('avatar_preview');
            
            if (usuario.avatar && usuario.avatar.trim() !== '') {
                currentImage.src = usuario.avatar;
                currentImage.style.display = 'block';
                imagePreview.style.display = 'block';
            } else {
                currentImage.src = '';
                currentImage.style.display = 'none';
                imagePreview.style.display = 'none';
            }
            
            // Cargar opciones de empresas
            cargarEmpresasSelect();

            // Mostrar el modal
            const editModal = document.getElementById('editModal');
            editModal.classList.add('open');
            const editWizardEl = editModal.querySelector('.claut-wizard');
            if (editWizardEl && editWizardEl.__clautWizardReset) editWizardEl.__clautWizardReset();
        }

        function cerrarModal() {
            document.getElementById('editModal').classList.remove('open');
            // Limpiar campos de contraseña al cerrar
            if (document.getElementById('nueva_password')) {
                document.getElementById('nueva_password').value = '';
                document.getElementById('confirmar_password').value = '';
                document.getElementById('mostrar_passwords').checked = false;
            }
        }
        
        function togglePasswordVisibility() {
            const checkbox = document.getElementById('mostrar_passwords');
            const password1 = document.getElementById('nueva_password');
            const password2 = document.getElementById('confirmar_password');
            
            if (checkbox.checked) {
                password1.type = 'text';
                password2.type = 'text';
            } else {
                password1.type = 'password';
                password2.type = 'password';
            }
        }
        
        function previewAvatar(input) {
            const preview = document.getElementById('current_avatar');
            const previewContainer = document.getElementById('avatar_preview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                cerrarModal();
            }
        }

        // Funciones para gestión de notificaciones de perfil
        async function loadProfileNotifications() {
            try {
                const response = await fetch('./api_notificaciones_perfil.php?action=listar');
                const result = await response.json();

                if (result.success) {
                    renderProfileNotifications(result.notificaciones);
                } else {
                    document.getElementById('profileNotificationsContainer').innerHTML =
                        '<div style="text-align: center; padding: 20px; color: #e74c3c;"><i class="fas fa-circle-xmark"></i> Error al cargar notificaciones</div>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('profileNotificationsContainer').innerHTML =
                    '<div style="text-align: center; padding: 20px; color: #e74c3c;"><i class="fas fa-circle-xmark"></i> Error de conexión</div>';
            }
        }

        function renderProfileNotifications(notificaciones) {
            const container = document.getElementById('profileNotificationsContainer');

            if (notificaciones.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-green-500/10 mb-4 border border-green-500/20">
                            <i class="fas fa-check text-3xl text-green-500"></i>
                        </div>
                        <h4 class="text-white font-bold text-lg">Sin solicitudes pendientes</h4>
                        <p class="claut-text-muted text-sm mt-1">El sistema está actualizado. No hay cambios de perfil por revisar.</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead>
                            <tr class="border-b border-white/5 bg-white/5">
                                <th class="px-6 py-4 text-xs font-bold claut-text-muted uppercase tracking-widest">Usuario</th>
                                <th class="px-6 py-4 text-xs font-bold claut-text-muted uppercase tracking-widest">Campo</th>
                                <th class="px-6 py-4 text-xs font-bold claut-text-muted uppercase tracking-widest">Cambio Propuesto</th>
                                <th class="px-6 py-4 text-xs font-bold claut-text-muted uppercase tracking-widest text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
            `;

            notificaciones.forEach(notif => {
                const fieldName = getFieldDisplayName(notif.campo_modificado);
                const fecha = new Date(notif.fecha_solicitud).toLocaleDateString('es-ES', {
                    month: 'short', day: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });

                html += `
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-red-600 to-red-800 flex items-center justify-center text-white font-bold mr-3 border border-white/10 shadow-lg">
                                    ${notif.nombre.charAt(0)}
                                </div>
                                <div>
                                    <div class="text-white font-bold text-sm">${notif.nombre} ${notif.apellidos || ''}</div>
                                    <div class="claut-text-muted text-xs">${notif.email}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">
                                ${fieldName}
                            </span>
                            <div class="text-[10px] claut-text-muted mt-1 font-bold">Solicitado: ${fecha}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center space-x-3">
                                <span class="text-xs claut-text-muted italic line-through max-w-[100px] truncate">${notif.valor_anterior || 'Vacío'}</span>
                                <i class="fas fa-arrow-right text-[10px] text-red-500"></i>
                                <span class="text-sm text-white font-bold max-w-[150px] truncate">${notif.valor_nuevo || 'Vacío'}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button onclick="approveProfileChange(${notif.id})"
                                        class="p-2.5 bg-green-500/10 hover:bg-green-500/20 text-green-500 rounded-lg transition-all border border-green-500/20 group"
                                        title="Aprobar Cambio">
                                    <i class="fas fa-check group-hover:scale-110"></i>
                                </button>
                                <button onclick="rejectProfileChange(${notif.id})"
                                        class="p-2.5 bg-red-500/10 hover:bg-red-500/20 text-red-500 rounded-lg transition-all border border-red-500/20 group"
                                        title="Rechazar Cambio">
                                    <i class="fas fa-times group-hover:scale-110"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            container.innerHTML = html;
        }

        function getFieldDisplayName(field) {
            const fieldNames = {
                'phone': 'Teléfono',
                'birthDate': 'Fecha de Fundación / Nacimiento',
                'department': 'Departamento',
                'position': 'Cargo',
                'bio': 'Biografía',
                'address': 'Dirección',
                'city': 'Ciudad',
                'state': 'Estado',
                'zipCode': 'Código Postal',
                'country': 'País',
                'emergencyPhone': 'Teléfono Emergencia',
                'emergencyContact': 'Contacto Emergencia'
            };
            return fieldNames[field] || field;
        }

        async function approveProfileChange(notificationId) {
            if (!confirm('¿Está seguro de que desea aprobar este cambio?\\n\\nEsta acción aplicará el cambio en la base de datos.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('notificacion_id', notificationId);
                formData.append('revisado_por', 1);

                const response = await fetch('./api_notificaciones_perfil.php?action=aprobar', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Cambio aprobado exitosamente');
                    loadProfileNotifications();
                } else {
                    alert('Error: ' + (result.message || 'Error al aprobar cambio'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        }

        async function rejectProfileChange(notificationId) {
            const comentarios = prompt('Comentarios para el rechazo (opcional):');
            if (comentarios === null) return; // Usuario canceló

            try {
                const formData = new FormData();
                formData.append('notificacion_id', notificationId);
                formData.append('revisado_por', 1);
                formData.append('comentarios', comentarios);

                const response = await fetch('./api_notificaciones_perfil.php?action=rechazar', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Cambio rechazado');
                    loadProfileNotifications();
                } else {
                    alert('Error: ' + (result.message || 'Error al rechazar cambio'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        }

        // Cargar notificaciones al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            loadProfileNotifications();
        });
    </script>

    <!-- Modal de Edición -->
    <div id="editModal" class="claut-wizard-backdrop">
        <form method="POST" enctype="multipart/form-data" class="claut-wizard" style="max-width:760px;">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" id="edit_user_id" name="user_id" value="">

            <div class="claut-wizard-head">
                <h2><i class="fas fa-edit mr-2" style="color: #C7252B;"></i>Editar Usuario</h2>
                <p>Actualiza los datos, la empresa asociada y las credenciales del usuario</p>
                <button type="button" class="claut-modal-close" onclick="cerrarModal()" aria-label="Cerrar">&times;</button>
            </div>

            <div class="claut-wizard-body">
                <nav class="claut-wizard-steps">
                    <button type="button" class="claut-wizard-step-btn active" data-step="1">
                        <span class="claut-wizard-step-num">1</span><span>Datos personales</span>
                    </button>
                    <button type="button" class="claut-wizard-step-btn" data-step="2">
                        <span class="claut-wizard-step-num">2</span><span>Empresa y ubicación</span>
                    </button>
                    <button type="button" class="claut-wizard-step-btn" data-step="3">
                        <span class="claut-wizard-step-num">3</span><span>Biografía y seguridad</span>
                    </button>
                    <button type="button" class="claut-wizard-step-btn" data-step="4">
                        <span class="claut-wizard-step-num">4</span><span>Foto de perfil</span>
                    </button>
                </nav>

                <div class="claut-wizard-panels">
                    <!-- Paso 1: Datos personales -->
                    <div class="claut-wizard-panel active" data-step="1">
                        <p class="claut-wizard-panel-eyebrow">Paso 1 de 4</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label>Nombre *</label>
                                <input type="text" id="edit_nombre" name="nombre" required>
                            </div>
                            <div>
                                <label>Apellidos</label>
                                <input type="text" id="edit_apellidos" name="apellidos">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label>Email *</label>
                                <input type="email" id="edit_email" name="email" required>
                            </div>
                            <div>
                                <label>Teléfono</label>
                                <input type="tel" id="edit_telefono" name="telefono">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label id="lbl_fecha_edit">Fecha de Nacimiento</label>
                                <input type="date" id="edit_fecha_nacimiento" name="fecha_nacimiento">
                            </div>
                            <div>
                                <label>Rol</label>
                                <select id="edit_rol" name="rol">
                                    <option value="empleado">Empleado</option>
                                    <option value="empresa">Empresa</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2: Empresa y ubicación -->
                    <div class="claut-wizard-panel" data-step="2">
                        <p class="claut-wizard-panel-eyebrow">Paso 2 de 4</p>
                        <div>
                            <label>Nombre de Empresa</label>
                            <input type="text" id="edit_nombre_empresa" name="nombre_empresa">
                        </div>
                        <div>
                            <label>Empresa Asociada</label>
                            <select id="edit_empresa_id" name="empresa_id">
                                <option value="">Seleccionar empresa</option>
                                <!-- Se cargarán dinámicamente -->
                            </select>
                        </div>
                        <div>
                            <label>Dirección</label>
                            <input type="text" id="edit_direccion" name="direccion">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label>Ciudad</label>
                                <input type="text" id="edit_ciudad" name="ciudad">
                            </div>
                            <div>
                                <label>Estado</label>
                                <input type="text" id="edit_estado" name="estado">
                            </div>
                            <div>
                                <label>Código Postal</label>
                                <input type="text" id="edit_codigo_postal" name="codigo_postal">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label>País</label>
                                <input type="text" id="edit_pais" name="pais" value="México">
                            </div>
                            <div>
                                <label>Teléfono Emergencia</label>
                                <input type="tel" id="edit_telefono_emergencia" name="telefono_emergencia">
                            </div>
                        </div>
                        <div>
                            <label>Contacto Emergencia</label>
                            <input type="text" id="edit_contacto_emergencia" name="contacto_emergencia">
                        </div>
                    </div>

                    <!-- Paso 3: Biografía y seguridad -->
                    <div class="claut-wizard-panel" data-step="3">
                        <p class="claut-wizard-panel-eyebrow">Paso 3 de 4</p>
                        <div>
                            <label>Biografía</label>
                            <textarea id="edit_biografia" name="biografia" rows="3"></textarea>
                        </div>

                        <div style="background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); border-radius: 10px; padding: 1rem;">
                            <h4 style="margin: 0 0 .5rem; color: #f8fafc; font-size: .9rem; font-weight:700;"><i class="fas fa-lock"></i> Cambiar Contraseña (Opcional)</h4>
                            <p style="margin: 0 0 .75rem; color: #94a3b8; font-size: .8rem;">Deja en blanco si no deseas cambiar la contraseña</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label>Nueva Contraseña</label>
                                    <input type="password" name="nueva_password" id="nueva_password" placeholder="Mínimo 6 caracteres">
                                </div>
                                <div>
                                    <label>Confirmar Contraseña</label>
                                    <input type="password" name="confirmar_password" id="confirmar_password" placeholder="Repetir contraseña">
                                </div>
                            </div>

                            <div style="margin-top: .65rem;">
                                <label class="flex items-center" style="width:auto;font-weight:400;color:#94a3b8;">
                                    <input type="checkbox" id="mostrar_passwords" onchange="togglePasswordVisibility()" class="mr-2" style="width:auto;">
                                    Mostrar contraseñas
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 4: Foto de perfil -->
                    <div class="claut-wizard-panel" data-step="4">
                        <p class="claut-wizard-panel-eyebrow">Paso 4 de 4</p>
                        <div>
                            <label>Foto de Perfil</label>
                            <input type="file" name="avatar" accept="image/*" onchange="previewAvatar(this)">
                            <small style="color: #64748b; font-size: 12px;">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB.</small>

                            <div id="avatar_preview" style="margin-top: 10px; display: none;">
                                <p style="margin: 10px 0 5px 0; font-weight: bold; color:#94a3b8; font-size:.8rem;">Vista previa:</p>
                                <img id="current_avatar" src="" alt="Vista previa" style="max-width: 150px; max-height: 150px; border: 2px solid rgba(255,255,255,.12); border-radius: 8px; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="claut-wizard-foot">
                <button type="button" onclick="cerrarModal()" class="porsche-btn porsche-btn--ghost">Cancelar</button>
                <button type="button" data-wizard-prev class="porsche-btn porsche-btn--ghost">
                    <i class="fas fa-arrow-left"></i> Atrás
                </button>
                <button type="button" data-wizard-next class="porsche-btn">
                    Siguiente <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" data-wizard-submit class="porsche-btn" style="display:none;">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>

    <script>
        // Función para cargar empresas en el select
        async function cargarEmpresasSelect() {
            try {
                const response = await fetch('./api/empresas.php');
                const result = await response.json();

                const select = document.getElementById('edit_empresa_id');

                if (result.success && result.data) {
                    // Limpiar opciones existentes excepto la primera
                    select.innerHTML = '<option value="">Seleccionar empresa</option>';

                    // Agregar opciones de empresas
                    result.data.forEach(empresa => {
                        const option = document.createElement('option');
                        option.value = empresa.id;
                        option.textContent = empresa.nombre;
                        select.appendChild(option);
                    });
                } else {
                    console.warn('No se pudieron cargar las empresas:', result.message);
                }
            } catch (error) {
                console.error('Error al cargar empresas:', error);
                // Fallback: Cargar empresas desde la tabla de convenios
                try {
                    const fallbackResponse = await fetch('./api/empresas-convenio.php');
                    const fallbackResult = await fallbackResponse.json();

                    const select = document.getElementById('edit_empresa_id');

                    if (fallbackResult.success && fallbackResult.data) {
                        select.innerHTML = '<option value="">Seleccionar empresa</option>';

                        fallbackResult.data.forEach(empresa => {
                            const option = document.createElement('option');
                            option.value = empresa.id;
                            option.textContent = empresa.nombre || empresa.razon_social;
                            select.appendChild(option);
                        });
                    }
                } catch (fallbackError) {
                    console.error('Error en fallback de empresas:', fallbackError);
                }
            }
        }
    </script>

    <!-- Modal de Restricciones de Acceso -->
    <div id="modalRestricciones" class="claut-wizard-backdrop">
        <form id="formRestricciones" class="claut-wizard" style="max-width:640px;">
            <input type="hidden" id="usuarioIdRestricciones" name="usuario_id">

            <div class="claut-wizard-head">
                <h2><i class="fas fa-lock mr-2"></i>Gestionar Restricciones de Acceso</h2>
                <p>Usuario: <span id="nombreUsuarioRestricciones"></span></p>
                <button type="button" class="claut-modal-close" onclick="cerrarModalRestricciones()" aria-label="Cerrar">&times;</button>
            </div>

            <div class="claut-wizard-body">
                <nav class="claut-wizard-steps">
                    <button type="button" class="claut-wizard-step-btn active" data-step="1">
                        <span class="claut-wizard-step-num">1</span><span>Información</span>
                    </button>
                    <button type="button" class="claut-wizard-step-btn" data-step="2">
                        <span class="claut-wizard-step-num">2</span><span>Páginas</span>
                    </button>
                </nav>

                <div class="claut-wizard-panels">
                    <!-- Paso 1: Información -->
                    <div class="claut-wizard-panel active" data-step="1">
                        <p class="claut-wizard-panel-eyebrow">Paso 1 de 2</p>
                        <div style="background: rgba(234,179,8,.1); border-left: 3px solid #eab308; padding: 1rem; border-radius: 8px;">
                            <div class="flex">
                                <i class="fas fa-exclamation-triangle mr-3 mt-1" style="color:#eab308;"></i>
                                <div>
                                    <h4 style="color:#fde68a; font-size:.85rem; font-weight:700; margin:0 0 .35rem;">Información sobre las restricciones</h4>
                                    <p style="color:#cbd5e1; font-size:.8rem; margin:0;">
                                        Las páginas marcadas como restringidas mostrarán una advertencia al usuario y lo redirigirán automáticamente al dashboard.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2: Páginas a restringir -->
                    <div class="claut-wizard-panel" data-step="2">
                        <p class="claut-wizard-panel-eyebrow">Paso 2 de 2</p>
                        <h4 style="color:#f8fafc; font-size:.85rem; font-weight:700;">Selecciona las páginas a restringir:</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="eventos" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Eventos</div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="documentacion" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Documentación</div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="boletines" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Boletines</div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="comites" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Comités</div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="contacto" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Contacto</div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="empresas-convenio" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Empresas en Convenio</div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="descuentos" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Descuentos</div>
                                </div>
                            </label>

                            <label class="flex items-center p-3 border rounded-lg cursor-pointer" style="border-color: rgba(255,255,255,.1);">
                                <input type="checkbox" name="paginas[]" value="profile" class="mr-3" style="width:auto;">
                                <div>
                                    <div style="color:#f8fafc; font-weight:600; font-size:.85rem;">Perfil</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="claut-wizard-foot">
                <button type="button" onclick="cerrarModalRestricciones()" class="porsche-btn porsche-btn--ghost">Cancelar</button>
                <button type="button" data-wizard-prev class="porsche-btn porsche-btn--ghost">
                    <i class="fas fa-arrow-left"></i> Atrás
                </button>
                <button type="button" data-wizard-next class="porsche-btn">
                    Siguiente <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" data-wizard-submit class="porsche-btn" style="display:none;">
                    <i class="fas fa-save"></i> Guardar Restricciones
                </button>
            </div>
        </form>
    </div>

    <script>
        // Función para abrir el modal de restricciones
        function abrirModalRestricciones(usuarioId, nombreUsuario) {
            document.getElementById('usuarioIdRestricciones').value = usuarioId;
            document.getElementById('nombreUsuarioRestricciones').textContent = nombreUsuario;
            const restriccionesModal = document.getElementById('modalRestricciones');
            restriccionesModal.classList.add('open');
            const restriccionesWizardEl = restriccionesModal.querySelector('.claut-wizard');
            if (restriccionesWizardEl && restriccionesWizardEl.__clautWizardReset) restriccionesWizardEl.__clautWizardReset();

            // Cargar restricciones actuales del usuario
            cargarRestriccionesUsuario(usuarioId);
        }

        // Función para cerrar el modal de restricciones
        function cerrarModalRestricciones() {
            document.getElementById('modalRestricciones').classList.remove('open');
        }

        // Función para cargar las restricciones actuales del usuario
        function cargarRestriccionesUsuario(usuarioId) {
            fetch('./api/restricciones.php?action=get&usuario_id=' + usuarioId)
                .then(response => response.json())
                .then(data => {
                    // Limpiar checkboxes
                    const checkboxes = document.querySelectorAll('input[name="paginas[]"]');
                    checkboxes.forEach(checkbox => checkbox.checked = false);

                    if (data.success && data.restricciones) {
                        // Marcar las páginas restringidas
                        data.restricciones.forEach(pagina => {
                            const checkbox = document.querySelector(`input[value="${pagina}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                })
                .catch(error => console.error('Error cargando restricciones:', error));
        }

        // Manejar el envío del formulario de restricciones
        document.getElementById('formRestricciones').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const usuarioId = formData.get('usuario_id');
            const paginasRestringidas = formData.getAll('paginas[]');

            // Enviar datos al API
            fetch('./api/restricciones.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save',
                    usuario_id: usuarioId,
                    paginas: paginasRestringidas
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Restricciones guardadas exitosamente');
                    cerrarModalRestricciones();
                } else {
                    alert('Error al guardar restricciones: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar las restricciones');
            });
        });

        // Cerrar modal al hacer clic fuera de él
        document.getElementById('modalRestricciones').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalRestricciones();
            }
        });
    </script>

    </div>

    <!-- Modal de Mensajería para Usuarios -->
    <div id="modalMensajeria" class="claut-wizard-backdrop">
        <form id="mensajeriaForm" class="claut-wizard" style="max-width:760px;">
            <div class="claut-wizard-head">
                <h2><i class="fas fa-envelope-open mr-2"></i>Enviar Mensaje a Usuarios</h2>
                <p>Sistema de mensajería para administradores</p>
                <button type="button" class="claut-modal-close" onclick="cerrarModalMensajeria()" aria-label="Cerrar">&times;</button>
            </div>

            <div class="claut-wizard-body">
                <nav class="claut-wizard-steps">
                    <button type="button" class="claut-wizard-step-btn active" data-step="1">
                        <span class="claut-wizard-step-num">1</span><span>Destinatarios</span>
                    </button>
                    <button type="button" class="claut-wizard-step-btn" data-step="2">
                        <span class="claut-wizard-step-num">2</span><span>Mensaje</span>
                    </button>
                </nav>

                <div class="claut-wizard-panels">
                    <!-- Paso 1: Destinatarios -->
                    <div class="claut-wizard-panel active" data-step="1">
                        <p class="claut-wizard-panel-eyebrow">Paso 1 de 2</p>
                        <div>
                            <label for="destinatarioSelect">
                                <i class="fas fa-users mr-1" style="color: #C7252B;"></i>
                                Destinatarios *
                            </label>
                            <select id="destinatarioSelect" name="destinatario" required>
                                <option value="">Selecciona destinatarios...</option>
                                <option value="todos">Todos los usuarios activos</option>
                                <optgroup label="Usuarios específicos" id="usuariosEspecificos">
                                    <!-- Se llenará dinámicamente -->
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label for="tipoMensajeSelect">
                                <i class="fas fa-tag mr-1" style="color: #C7252B;"></i>
                                Tipo de Mensaje
                            </label>
                            <select id="tipoMensajeSelect" name="tipo_mensaje" onchange="cambiarTipoMensaje()">
                                <option value="texto">Mensaje de Texto</option>
                                <option value="link">Enlace/URL</option>
                                <option value="imagen">Imagen</option>
                                <option value="documento">Documento</option>
                            </select>
                        </div>
                    </div>

                    <!-- Paso 2: Mensaje -->
                    <div class="claut-wizard-panel" data-step="2">
                        <p class="claut-wizard-panel-eyebrow">Paso 2 de 2</p>

                        <!-- Asunto -->
                        <div>
                            <label for="asuntoMensaje">
                                <i class="fas fa-heading mr-1" style="color: #C7252B;"></i>
                                Asunto del Mensaje *
                            </label>
                            <input type="text" id="asuntoMensaje" name="asunto" required
                                   placeholder="Escriba el asunto del mensaje...">
                        </div>

                        <!-- Contenido Dinámico según Tipo de Mensaje -->
                        <div id="contenidoMensaje">
                            <!-- Contenido de Texto (por defecto) -->
                            <div id="contenidoTexto" class="mensaje-content">
                                <label for="textoMensaje">
                                    <i class="fas fa-edit mr-1" style="color: #C7252B;"></i>
                                    Contenido del Mensaje *
                                </label>
                                <textarea id="textoMensaje" name="contenido_texto" rows="6" required
                                          placeholder="Escriba aquí el contenido del mensaje..."></textarea>
                            </div>

                            <!-- Contenido de Enlace -->
                            <div id="contenidoLink" class="mensaje-content hidden">
                                <div>
                                    <label for="linkUrl">
                                        <i class="fas fa-link mr-1" style="color: #C7252B;"></i>
                                        URL del Enlace *
                                    </label>
                                    <input type="url" id="linkUrl" name="link_url"
                                           placeholder="https://ejemplo.com">
                                </div>
                                <div>
                                    <label for="linkTexto">
                                        Texto del Enlace
                                    </label>
                                    <input type="text" id="linkTexto" name="link_texto"
                                           placeholder="Texto que aparecerá como enlace">
                                </div>
                                <div>
                                    <label for="linkDescripcion">
                                        Descripción
                                    </label>
                                    <textarea id="linkDescripcion" name="link_descripcion" rows="3"
                                              placeholder="Descripción opcional del enlace"></textarea>
                                </div>
                            </div>

                            <!-- Contenido de Imagen -->
                            <div id="contenidoImagen" class="mensaje-content hidden">
                                <div>
                                    <label for="imagenArchivo">
                                        <i class="fas fa-image mr-1" style="color: #C7252B;"></i>
                                        Seleccionar Imagen *
                                    </label>
                                    <input type="file" id="imagenArchivo" name="imagen_archivo"
                                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                    <p style="color:#64748b; font-size:.75rem; margin-top:.35rem;">Formatos permitidos: JPG, PNG, GIF, WebP. Máximo 5MB.</p>
                                </div>
                                <div>
                                    <label for="imagenDescripcion">
                                        Descripción de la Imagen
                                    </label>
                                    <textarea id="imagenDescripcion" name="imagen_descripcion" rows="3"
                                              placeholder="Descripción o contexto de la imagen"></textarea>
                                </div>
                            </div>

                            <!-- Contenido de Documento -->
                            <div id="contenidoDocumento" class="mensaje-content hidden">
                                <div>
                                    <label for="documentoArchivo">
                                        <i class="fas fa-file-alt mr-1" style="color: #C7252B;"></i>
                                        Seleccionar Documento *
                                    </label>
                                    <input type="file" id="documentoArchivo" name="documento_archivo"
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                    <p style="color:#64748b; font-size:.75rem; margin-top:.35rem;">Formatos permitidos: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT. Máximo 10MB.</p>
                                </div>
                                <div>
                                    <label for="documentoDescripcion">
                                        Descripción del Documento
                                    </label>
                                    <textarea id="documentoDescripcion" name="documento_descripcion" rows="3"
                                              placeholder="Descripción o contexto del documento"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="claut-wizard-foot">
                <button type="button" onclick="cerrarModalMensajeria()" class="porsche-btn porsche-btn--ghost">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </button>
                <button type="button" data-wizard-prev class="porsche-btn porsche-btn--ghost">
                    <i class="fas fa-arrow-left"></i> Atrás
                </button>
                <button type="button" data-wizard-next class="porsche-btn">
                    Siguiente <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" id="enviarMensajeBtn" data-wizard-submit
                        class="porsche-btn" style="display:none;">
                    <span id="mensajeSpinner" class="hidden animate-spin mr-2">
                        <i class="fas fa-spinner"></i>
                    </span>
                    <i id="mensajeIcon" class="fas fa-paper-plane mr-2"></i>
                    <span id="mensajeText">Enviar Mensaje</span>
                </button>
            </div>
        </form>
    </div>

    <script>
        // Funciones para el sistema de mensajería
        let usuariosDisponibles = [];

        // Función para abrir el modal de mensajería
        function abrirModalMensajeria() {
            console.log('🔔 Abriendo modal de mensajería...');

            // Cargar usuarios disponibles
            cargarUsuariosDisponibles();

            // Mostrar modal
            const modal = document.getElementById('modalMensajeria');
            modal.classList.add('open');
            const mensajeriaWizardEl = modal.querySelector('.claut-wizard');
            if (mensajeriaWizardEl && mensajeriaWizardEl.__clautWizardReset) mensajeriaWizardEl.__clautWizardReset();

            // Reset del formulario
            const form = document.getElementById('mensajeriaForm');
            if (form) {
                form.reset();
                // Ocultar contenidos específicos
                document.querySelectorAll('.mensaje-content').forEach(content => {
                    content.classList.add('hidden');
                });
                // Mostrar contenido de texto por defecto
                document.getElementById('contenidoTexto').classList.remove('hidden');
            }
        }

        // Función para cerrar el modal de mensajería
        function cerrarModalMensajeria() {
            console.log('🚪 Cerrando modal de mensajería...');
            document.getElementById('modalMensajeria').classList.remove('open');

            // Reset del formulario
            const form = document.getElementById('mensajeriaForm');
            if (form) {
                form.reset();
            }
        }

        // Función para cargar usuarios disponibles
        async function cargarUsuariosDisponibles() {
            try {
                console.log('👥 Cargando usuarios disponibles...');
                const response = await fetch('./api/usuarios_mensajes.php?action=obtener_usuarios');
                const data = await response.json();

                if (data.success && data.data) {
                    usuariosDisponibles = data.data;
                    llenarSelectUsuarios(data.data);
                } else {
                    console.error('Error cargando usuarios:', data.message);
                }
            } catch (error) {
                console.error('Error en la petición de usuarios:', error);
            }
        }

        // Función para llenar el select de usuarios
        function llenarSelectUsuarios(usuarios) {
            const optgroup = document.getElementById('usuariosEspecificos');
            if (!optgroup) return;

            // Limpiar opciones anteriores
            optgroup.innerHTML = '';

            // Agregar cada usuario
            usuarios.forEach(usuario => {
                const option = document.createElement('option');
                option.value = usuario.email;
                option.textContent = `${usuario.nombre} ${usuario.apellido} (${usuario.email})`;
                optgroup.appendChild(option);
            });

            console.log(`✅ ${usuarios.length} usuarios cargados en el selector`);
        }

        // Función para cambiar tipo de mensaje
        function cambiarTipoMensaje() {
            const tipoSelect = document.getElementById('tipoMensajeSelect');
            const selectedType = tipoSelect.value;

            console.log('📝 Cambiando tipo de mensaje a:', selectedType);

            // Ocultar todos los contenidos
            document.querySelectorAll('.mensaje-content').forEach(content => {
                content.classList.add('hidden');
                // Limpiar required de campos no visibles
                content.querySelectorAll('[required]').forEach(field => {
                    if (!content.classList.contains('hidden')) return;
                    field.removeAttribute('required');
                });
            });

            // Mostrar contenido correspondiente y establecer required
            let targetContent = null;
            switch (selectedType) {
                case 'texto':
                    targetContent = document.getElementById('contenidoTexto');
                    document.getElementById('textoMensaje').setAttribute('required', 'required');
                    break;
                case 'link':
                    targetContent = document.getElementById('contenidoLink');
                    document.getElementById('linkUrl').setAttribute('required', 'required');
                    break;
                case 'imagen':
                    targetContent = document.getElementById('contenidoImagen');
                    document.getElementById('imagenArchivo').setAttribute('required', 'required');
                    break;
                case 'documento':
                    targetContent = document.getElementById('contenidoDocumento');
                    document.getElementById('documentoArchivo').setAttribute('required', 'required');
                    break;
            }

            if (targetContent) {
                targetContent.classList.remove('hidden');
            }
        }

        // Función para enviar mensaje
        async function enviarMensajeUsuarios(e) {
            e.preventDefault();
            console.log('📤 Iniciando envío de mensaje...');

            const submitBtn = document.getElementById('enviarMensajeBtn');
            const spinner = document.getElementById('mensajeSpinner');
            const icon = document.getElementById('mensajeIcon');
            const text = document.getElementById('mensajeText');

            // Cambiar estado del botón
            submitBtn.disabled = true;
            spinner.classList.remove('hidden');
            icon.classList.add('hidden');
            text.textContent = 'Enviando...';

            try {
                const formData = new FormData(e.target);
                console.log('📋 Datos del formulario:', Object.fromEntries(formData));

                const response = await fetch('./api/usuarios_mensajes.php?action=enviar_mensaje', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('📨 Respuesta del servidor:', result);

                if (result.success) {
                    // Mostrar mensaje de éxito
                    alert(`Mensaje enviado correctamente a ${result.data.emails_enviados} usuario(s)`);

                    // Cerrar modal y resetear
                    cerrarModalMensajeria();

                    console.log('✅ Mensaje enviado exitosamente');
                } else {
                    throw new Error(result.message || 'Error desconocido');
                }

            } catch (error) {
                console.error('❌ Error enviando mensaje:', error);
                alert('Error al enviar el mensaje: ' + error.message);
            } finally {
                // Restaurar estado del botón
                submitBtn.disabled = false;
                spinner.classList.add('hidden');
                icon.classList.remove('hidden');
                text.textContent = 'Enviar Mensaje';
            }
        }

        // Event listeners al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 Inicializando sistema de mensajería...');

            // Listener para el formulario
            const mensajeriaForm = document.getElementById('mensajeriaForm');
            if (mensajeriaForm) {
                mensajeriaForm.addEventListener('submit', enviarMensajeUsuarios);
            }

            // Cerrar modal con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('modalMensajeria');
                    if (modal && modal.classList.contains('open')) {
                        cerrarModalMensajeria();
                    }
                }
            });

            // Cerrar modal al hacer clic fuera
            document.getElementById('modalMensajeria').addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarModalMensajeria();
                }
            });

            console.log('✅ Sistema de mensajería inicializado');
        });
    </script>

    <script src="./assets/js/claut-admin-sidebar.js?v=20260901c"></script>
    <script src="./assets/js/claut-wizard.js?v=20260901a"></script>
<script src="./js/claut-admin-elite.js?v=20260909a"></script>
</body>
</html>