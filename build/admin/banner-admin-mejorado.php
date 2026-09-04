<?php
/**
 * Panel de Administración de Banners Mejorado
 * Con soporte completo para subida de imágenes locales
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';

// Función para subir imagen local
function subirImagenLocal($archivo) {
    $directorioSubida = __DIR__ . '/../uploads/banners/';

    error_log("DEBUG: Directorio de subida: " . $directorioSubida);
    error_log("DEBUG: Archivo recibido: " . print_r($archivo, true));

    if (!file_exists($directorioSubida)) {
        error_log("DEBUG: Directorio no existe, creando...");
        if (!mkdir($directorioSubida, 0755, true)) {
            error_log("DEBUG: Error al crear directorio");
            return ['success' => false, 'message' => 'No se pudo crear el directorio de uploads'];
        }
        error_log("DEBUG: Directorio creado exitosamente");
    } else {
        error_log("DEBUG: Directorio ya existe");
    }

    // Verificar permisos
    if (!is_writable($directorioSubida)) {
        error_log("DEBUG: Directorio no tiene permisos de escritura");
        return ['success' => false, 'message' => 'El directorio no tiene permisos de escritura'];
    }
    
    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $extensionesPermitidas)) {
        return ['success' => false, 'message' => 'Formato de imagen no permitido'];
    }
    
    if ($archivo['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande (máximo 5MB)'];
    }
    
    $nombreArchivo = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
    $rutaCompleta = $directorioSubida . $nombreArchivo;
    
    error_log("DEBUG: Intentando mover archivo de {$archivo['tmp_name']} a $rutaCompleta");

    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        error_log("DEBUG: Archivo movido exitosamente a: $rutaCompleta");
        // Verificar que el archivo realmente existe
        if (file_exists($rutaCompleta)) {
            error_log("DEBUG: Archivo confirmado en: $rutaCompleta");
            return ['success' => true, 'url' => 'uploads/banners/' . $nombreArchivo];
        } else {
            error_log("DEBUG: ERROR - Archivo no existe después de mover");
            return ['success' => false, 'message' => 'Error: el archivo no se creó correctamente'];
        }
    }

    error_log("DEBUG: move_uploaded_file falló. Error: " . error_get_last()['message'] ?? 'desconocido');
    return ['success' => false, 'message' => 'Error al subir la imagen: no se pudo mover el archivo'];
}

// Función para obtener banners
function obtenerBanners($conn) {
    try {
        $stmt = $conn->query("SHOW TABLES LIKE 'banner_carrusel'");
        if ($stmt->rowCount() == 0) {
            crearTablaBanners($conn);
        }
        
        $stmt = $conn->query("
            SELECT id, titulo, descripcion, imagen_url, posicion, activo, 
                   fecha_inicio, fecha_fin, fecha_creacion 
            FROM banner_carrusel 
            ORDER BY posicion ASC, fecha_creacion DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener banners: " . $e->getMessage());
        return [];
    }
}

// Función para crear tabla
function crearTablaBanners($conn) {
    try {
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
        return true;
    } catch (PDOException $e) {
        error_log("Error al crear tabla: " . $e->getMessage());
        return false;
    }
}

// Función para guardar banner
function guardarBanner($conn, $datos) {
    try {
        if (empty($datos['imagen_url'])) {
            throw new Exception("La imagen es requerida");
        }
        
        if (isset($datos['id']) && $datos['id']) {
            $sql = "UPDATE banner_carrusel 
                    SET titulo = :titulo, descripcion = :descripcion, imagen_url = :imagen_url, 
                        posicion = :posicion, activo = :activo, fecha_inicio = :fecha_inicio, fecha_fin = :fecha_fin
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $params = [
                ':titulo' => $datos['titulo'],
                ':descripcion' => $datos['descripcion'],
                ':imagen_url' => $datos['imagen_url'],
                ':posicion' => $datos['posicion'],
                ':activo' => $datos['activo'] ? 1 : 0,
                ':fecha_inicio' => !empty($datos['fecha_inicio']) ? $datos['fecha_inicio'] : null,
                ':fecha_fin' => !empty($datos['fecha_fin']) ? $datos['fecha_fin'] : null,
                ':id' => $datos['id']
            ];
        } else {
            $sql = "INSERT INTO banner_carrusel (titulo, descripcion, imagen_url, posicion, activo, fecha_inicio, fecha_fin)
                    VALUES (:titulo, :descripcion, :imagen_url, :posicion, :activo, :fecha_inicio, :fecha_fin)";
            
            $stmt = $conn->prepare($sql);
            $params = [
                ':titulo' => $datos['titulo'],
                ':descripcion' => $datos['descripcion'],
                ':imagen_url' => $datos['imagen_url'],
                ':posicion' => $datos['posicion'],
                ':activo' => $datos['activo'] ? 1 : 0,
                ':fecha_inicio' => !empty($datos['fecha_inicio']) ? $datos['fecha_inicio'] : null,
                ':fecha_fin' => !empty($datos['fecha_fin']) ? $datos['fecha_fin'] : null
            ];
        }
        
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error al guardar banner: " . $e->getMessage());
        throw $e;
    }
}

// Función para eliminar banner
function eliminarBanner($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT imagen_url FROM banner_carrusel WHERE id = ?");
        $stmt->execute([$id]);
        $banner = $stmt->fetch();
        
        if ($banner && strpos($banner['imagen_url'], 'uploads/banners/') !== false) {
            $rutaImagen = __DIR__ . '/../' . $banner['imagen_url'];
            if (file_exists($rutaImagen)) {
                unlink($rutaImagen);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM banner_carrusel WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Error al eliminar banner: " . $e->getMessage());
        return false;
    }
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $accion = $_POST['accion'] ?? '';
        
        if ($accion === 'guardar') {
            $imagen_url = $_POST['imagen_url_actual'] ?? '';

            // Debug removido - funcionando correctamente

            if (isset($_FILES['imagen_local']) && $_FILES['imagen_local']['error'] === UPLOAD_ERR_OK) {
                error_log("DEBUG: Procesando archivo local");
                $resultadoSubida = subirImagenLocal($_FILES['imagen_local']);
                if ($resultadoSubida['success']) {
                    $imagen_url = $resultadoSubida['url'];
                    error_log("DEBUG: Imagen subida exitosamente: " . $imagen_url);
                } else {
                    error_log("DEBUG: Error en subida: " . $resultadoSubida['message']);
                    throw new Exception($resultadoSubida['message']);
                }
            } elseif (!empty($_POST['imagen_url'])) {
                $imagen_url = $_POST['imagen_url'];
                error_log("DEBUG: Usando URL externa: " . $imagen_url);
            } elseif (isset($_FILES['imagen_local'])) {
                // Diagnosticar error de archivo
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'El archivo supera el tamaño máximo permitido por PHP',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo del formulario',
                    UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                    UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal',
                    UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
                    UPLOAD_ERR_EXTENSION => 'Extensión bloqueada'
                ];
                $error_code = $_FILES['imagen_local']['error'];
                $error_msg = $error_messages[$error_code] ?? "Error desconocido: $error_code";
                error_log("DEBUG: Error de archivo: $error_msg");
                throw new Exception("Error al subir archivo: $error_msg");
            }
            
            if (empty($imagen_url)) {
                throw new Exception("Debe proporcionar una imagen");
            }
            
            $datos = [
                'id' => $_POST['id'] ?? null,
                'titulo' => $_POST['titulo'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'imagen_url' => $imagen_url,
                'posicion' => intval($_POST['posicion'] ?? 1),
                'activo' => isset($_POST['activo']),
                'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
                'fecha_fin' => $_POST['fecha_fin'] ?? ''
            ];
            
            guardarBanner($conn, $datos);
            $mensaje = $datos['id'] ? 'Banner actualizado correctamente' : 'Banner creado correctamente';
            $tipo_mensaje = 'success';
            
        } elseif ($accion === 'eliminar') {
            $id = $_POST['id'] ?? 0;
            if (eliminarBanner($conn, $id)) {
                $mensaje = 'Banner eliminado correctamente';
                $tipo_mensaje = 'success';
            } else {
                throw new Exception("Error al eliminar el banner");
            }
        }
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Obtener banners
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $banners = obtenerBanners($conn);
} catch (Exception $e) {
    $banners = [];
    if (empty($mensaje)) {
        $mensaje = "Error de conexión: " . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrador de Banners - CRUD Completo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/claut-ui.css?v=20260902a">
    <link rel="stylesheet" href="../assets/css/layout/claut-wizard.css?v=20260901a">
    <link rel="stylesheet" href="../assets/css/layout/admin-sidebar.css?v=20260901c">
    <style>
        /* Porsche-inspired Design System */
        :root {
            /* Primary Colors - Inspired by Porsche */
            --porsche-black: #09090b;
            --porsche-charcoal: #2d2d2d;
            --porsche-silver: #8a8a8a;
            --porsche-white: #ffffff;
            --porsche-light-gray: #f5f5f5;
            --porsche-accent: #C7252B;
            /* Typography */
            --porsche-font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --porsche-radius-lg: 12px;
        }

        .modal-backdrop { backdrop-filter: blur(5px); }
        .slide-in { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        .banner-preview {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .drag-drop-area {
            border: 2px dashed #cbd5e1;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .drag-drop-area:hover,
        .drag-drop-area.dragover {
            border-color: #C7252B;
            background-color: #fef2f2;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        header.claut-admin-main { position: relative; z-index: 10; }

        /* Pulido visual del panel — jerarquía y feedback más claros dentro del tema oscuro */
        .claut-stat-card { transition: transform .18s cubic-bezier(0.23,1,0.32,1), box-shadow .18s ease; }
        .claut-stat-card:hover { transform: translateY(-3px); box-shadow: 0 16px 36px -12px rgba(0,0,0,.55); }

        .banner-item {
            position: relative;
            padding-left: 1.5rem !important;
            transition: transform .18s cubic-bezier(0.23,1,0.32,1), box-shadow .18s ease, border-color .18s ease;
        }
        .banner-item::before {
            content: '';
            position: absolute;
            left: 0; top: .6rem; bottom: .6rem;
            width: 4px;
            border-radius: 999px;
            background: #22c55e;
        }
        .banner-item[data-estado="inactivo"]::before { background: #64748b; }
        .banner-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 34px -10px rgba(0,0,0,.55) !important;
            border-color: rgba(199,37,43,.35) !important;
        }
    </style>
</head>
<body class="claut-dark">
    <aside class="claut-admin-sidebar" id="claut-admin-sidebar">
        <div class="claut-admin-sidebar-brand">
            <img src="../assets/img/apple-icon.png" alt="Clúster Metropolitano" class="claut-admin-sidebar-logo">
            <span class="claut-admin-sidebar-title">Clúster Admin</span>
        </div>
        <div class="claut-admin-sidebar-divider"></div>

        <nav class="claut-admin-nav">
            <div class="claut-admin-nav-group">
                <p class="claut-admin-nav-label">General</p>
                <a href="../admin-panel.html?login=success" class="claut-admin-nav-item">
                    <i class="fas fa-shield-halved"></i><span>Panel Admin</span>
                </a>
                <a href="../dashboard.html" class="claut-admin-nav-item">
                    <i class="fas fa-house"></i><span>Dashboard</span>
                </a>
            </div>

            <div class="claut-admin-nav-group">
                <p class="claut-admin-nav-label">Ecosistema de módulos</p>
                <a href="./banner-admin-mejorado.php" class="claut-admin-nav-item active">
                    <i class="fas fa-images"></i><span>Banners</span>
                </a>
                <a href="../demo_boletines.html" class="claut-admin-nav-item">
                    <i class="fas fa-newspaper"></i><span>Boletines</span>
                </a>
                <a href="../demo_documentos.html" class="claut-admin-nav-item">
                    <i class="fas fa-folder-open"></i><span>Documentos</span>
                </a>
                <a href="../demo_descuentos.html" class="claut-admin-nav-item">
                    <i class="fas fa-tags"></i><span>Beneficios</span>
                </a>
                <a href="../demo_comite.html" class="claut-admin-nav-item">
                    <i class="fas fa-people-group"></i><span>Comités</span>
                </a>
                <a href="../calendario.html" class="claut-admin-nav-item">
                    <i class="fas fa-calendar-days"></i><span>Calendario</span>
                </a>
                <a href="../demo_empresas.html" class="claut-admin-nav-item">
                    <i class="fas fa-building"></i><span>Socios</span>
                </a>
                <a href="../demo_evento.html" class="claut-admin-nav-item">
                    <i class="fas fa-calendar-check"></i><span>Eventos</span>
                </a>
                <a href="../gestionar_usuarios.php" class="claut-admin-nav-item">
                    <i class="fas fa-users"></i><span>Usuarios</span>
                </a>
                <a href="../demo_visitante.html" class="claut-admin-nav-item">
                    <i class="fas fa-user-shield"></i><span>Visitantes</span>
                </a>
            </div>

            <div class="claut-admin-nav-group">
                <p class="claut-admin-nav-label">Sistema</p>
                <a href="../profile.html" class="claut-admin-nav-item">
                    <i class="fas fa-gear"></i><span>Configuración</span>
                </a>
            </div>
        </nav>

        <div class="claut-admin-sidebar-foot">
            <button onclick="window.location.href='../pages/sign-in.html'" class="claut-admin-nav-item claut-admin-nav-item--danger">
                <i class="fas fa-sign-out-alt"></i><span>Cerrar sesión</span>
            </button>
        </div>
    </aside>

    <div class="claut-admin-sidebar-overlay" id="claut-admin-sidebar-overlay"></div>

    <!-- Header -->
    <header class="claut-admin-main bg-gradient-to-r from-red-600 to-red-700 text-white shadow-lg" style="background: linear-gradient(135deg, #C7252B 0%, #A01E24 100%);">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <!-- Hamburger Menu Button for All Screens -->
                    <button class="mr-3 p-2 text-white hover:claut-text-secondary focus:outline-none focus:ring-2 focus:ring-white/20 rounded-md"
                            id="claut-header-menu-btn"
                            style="transition: background .2s ease;"
                            aria-label="Abrir menú de navegación">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div>
                        <h1 class="text-3xl font-bold">Administrador de Banners</h1>
                        <p class="text-red-200 mt-1">CRUD Completo - Gestión de Carrusel</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Estadísticas -->
    <div class="claut-admin-main container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="claut-stat-card claut-stat-card--red">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Total Banners</p>
                        <p class="stat-value"><?= count($banners) ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-images"></i></div>
                </div>
            </div>
            <div class="claut-stat-card claut-stat-card--green">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Banners Activos</p>
                        <p class="stat-value"><?= count(array_filter($banners, fn($b) => $b['activo'])) ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="claut-stat-card claut-stat-card--gold">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Banners Inactivos</p>
                        <p class="stat-value"><?= count(array_filter($banners, fn($b) => !$b['activo'])) ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                </div>
            </div>
            <div class="claut-stat-card claut-stat-card--blue">
                <div class="stat-row">
                    <div>
                        <p class="stat-label">Última Actualización</p>
                        <p class="stat-value"><?= !empty($banners) ? date('d/m') : '--' ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                </div>
            </div>
        </div>

        <!-- Controles -->
        <div class="rounded-lg p-4 mb-6" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08);">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <button onclick="abrirModalNuevo()" class="text-white px-4 py-2 rounded-lg hover:opacity-90 transition" style="background: #C7252B;">
                        <i class="fas fa-plus mr-2"></i>Crear Banner
                    </button>
                    <button onclick="location.reload()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                        <i class="fas fa-sync-alt mr-2"></i>Actualizar
                    </button>
                </div>

                <div class="flex items-center space-x-4">
                    <select id="filterEstado" class="claut-select" style="width:auto; padding:0.6rem 1rem;" onchange="filtrarBanners()">
                        <option value="">Todos los estados</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                    </select>
                    <a href="../pages/sign-in.html" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-eye mr-2"></i>Vista Previa
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="mb-6 p-4 rounded-lg" style="<?= $tipo_mensaje === 'success' ? 'background:var(--surface-success); border-left:4px solid var(--state-success); color:var(--state-success);' : 'background:var(--surface-danger); border-left:4px solid var(--state-danger); color:var(--state-danger);' ?>">
                <div class="flex items-center">
                    <i class="fas <?= $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-2"></i>
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <main class=" mx-auto px-4">

            <!-- Lista de Banners -->
            <section class="mb-6">
                <div class="rounded-lg p-4" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08);">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold claut-text-primary">
                            <i class="fas fa-images mr-2" style="color: #C7252B;"></i>
                            Lista de Banners
                        </h2>
                        <div class="text-sm claut-text-secondary">
                            <?= count($banners) ?> banner(es) total
                        </div>
                    </div>

                    <div id="banner-list" class="space-y-4">
                        <?php if (empty($banners)): ?>
                            <div class="text-center py-12">
                                <div class="text-6xl mb-4" style="color:#475569;"><i class="fas fa-images"></i></div>
                                <h3 class="text-xl font-semibold mb-2 claut-text-secondary">No hay banners configurados</h3>
                                <p class="claut-text-muted mb-4">Crea tu primer banner para comenzar</p>
                                <button onclick="abrirModalNuevo()" class="text-white px-6 py-3 rounded-lg hover:opacity-90 transition" style="background: #C7252B;">
                                    <i class="fas fa-plus mr-2"></i>Crear Primer Banner
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($banners as $banner): ?>
                                <?php
                                $imagenUrl = $banner['imagen_url'];
                                if (strpos($imagenUrl, 'http') !== 0) {
                                    // Verificar si el archivo existe físicamente
                                    $rutaFisica = __DIR__ . '/../' . $imagenUrl;
                                    if (file_exists($rutaFisica)) {
                                        // Para el navegador, usar ruta absoluta desde la raíz de Hostinger
                                        $imagenUrl = '/' . ltrim($imagenUrl, '/');
                                    } else {
                                        // Usar imagen por defecto si no existe
                                        $imagenUrl = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlbiBubyBlbmNvbnRyYWRhPC90ZXh0Pjwvc3ZnPg==';
                                    }
                                }
                                $fechaCreacion = date('d/m/Y H:i', strtotime($banner['fecha_creacion']));
                                ?>
                                <div class="banner-item rounded-lg p-4 transition-shadow" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08);" data-estado="<?= $banner['activo'] ? 'activo' : 'inactivo' ?>">
                                    <div class="flex gap-4">
                                        <div class="w-32 h-24 rounded-lg banner-preview flex-shrink-0" style="background-image: url('<?= htmlspecialchars($imagenUrl) ?>')">
                                            <?php if (!$banner['imagen_url']): ?>
                                                <div class="w-full h-full rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,0.06);">
                                                    <i class="fas fa-image claut-text-muted text-2xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex-1">
                                            <div class="flex justify-between items-start mb-2">
                                                <div class="flex-1">
                                                    <h3 class="text-lg font-semibold claut-text-primary mb-1"><?= htmlspecialchars($banner['titulo']) ?></h3>
                                                    <p class="claut-text-secondary text-sm"><?= htmlspecialchars($banner['descripcion'] ?: 'Sin descripción') ?></p>
                                                </div>
                                                <span class="claut-badge <?= $banner['activo'] ? 'claut-badge--success' : 'claut-badge--danger' ?> ml-3">
                                                    <?= $banner['activo'] ? '<i class="fas fa-check-circle"></i> ACTIVO' : '<i class="fas fa-times-circle"></i> INACTIVO' ?>
                                                </span>
                                            </div>

                                            <div class="flex justify-between items-center text-sm claut-text-muted mb-3">
                                                <div class="flex items-center space-x-4">
                                                    <span><i class="fas fa-sort-numeric-up mr-1"></i>Posición <?= $banner['posicion'] ?></span>
                                                    <span><i class="fas fa-calendar mr-1"></i><?= $fechaCreacion ?></span>
                                                </div>
                                                <span class="text-xs">#<?= $banner['id'] ?></span>
                                            </div>

                                            <div class="flex justify-between items-center pt-3 border-t">
                                                <div class="text-sm claut-text-muted">
                                                    <?php if ($banner['fecha_inicio'] || $banner['fecha_fin']): ?>
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?php if ($banner['fecha_inicio']): ?>
                                                            Desde: <?= date('d/m/Y', strtotime($banner['fecha_inicio'])) ?>
                                                        <?php endif; ?>
                                                        <?php if ($banner['fecha_fin']): ?>
                                                            Hasta: <?= date('d/m/Y', strtotime($banner['fecha_fin'])) ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span>Sin límites de fecha</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <button onclick='editarBanner(<?= htmlspecialchars(json_encode($banner)) ?>)'
                                                            class="px-3 py-1 bg-yellow-600 text-white rounded text-xs hover:bg-yellow-700 transition-colors">
                                                        <i class="fas fa-edit mr-1"></i>Editar
                                                    </button>
                                                    <form method="post" class="inline" onsubmit="return confirm('¿Eliminar este banner?\n\nEsta acción no se puede deshacer.')">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id" value="<?= $banner['id'] ?>">
                                                        <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition-colors">
                                                            <i class="fas fa-trash mr-1"></i>Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal Crear/Editar Banner -->
    <div id="modalBanner" class="claut-wizard-backdrop">
        <form method="post" enctype="multipart/form-data" class="claut-wizard" style="max-width:760px;">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" id="bannerId">
            <input type="hidden" name="imagen_url_actual" id="imagenUrlActual">

            <div class="claut-wizard-head">
                <h2 id="modalTitulo">Nuevo Banner</h2>
                <p>Se publica en el carrusel de la landing una vez guardado</p>
                <button type="button" class="claut-modal-close" onclick="cerrarModal()" aria-label="Cerrar">&times;</button>
            </div>

            <div class="claut-wizard-body">
                <nav class="claut-wizard-steps">
                    <button type="button" class="claut-wizard-step-btn active" data-step="1">
                        <span class="claut-wizard-step-num">1</span><span>Información</span>
                    </button>
                    <button type="button" class="claut-wizard-step-btn" data-step="2">
                        <span class="claut-wizard-step-num">2</span><span>Imagen</span>
                    </button>
                    <button type="button" class="claut-wizard-step-btn" data-step="3">
                        <span class="claut-wizard-step-num">3</span><span>Configuración</span>
                    </button>
                </nav>

                <div class="claut-wizard-panels">
                    <!-- Paso 1: Información -->
                    <div class="claut-wizard-panel active" data-step="1">
                        <p class="claut-wizard-panel-eyebrow">Paso 1 de 3</p>
                        <div>
                            <label>Título *</label>
                            <input type="text" name="titulo" id="titulo" required placeholder="Ej. Campaña 2026">
                        </div>
                        <div>
                            <label>Descripción</label>
                            <textarea name="descripcion" id="descripcion" rows="3" placeholder="Descripción del banner (opcional)"></textarea>
                        </div>
                    </div>

                    <!-- Paso 2: Imagen -->
                    <div class="claut-wizard-panel" data-step="2">
                        <p class="claut-wizard-panel-eyebrow">Paso 2 de 3</p>
                        <div>
                            <label>Origen de la imagen *</label>
                            <div class="flex gap-4 mb-2" style="color:#cbd5e1;font-size:.85rem;">
                                <label class="flex items-center" style="width:auto;font-weight:400;">
                                    <input type="radio" name="tipo_imagen" value="local" checked onchange="cambiarTipoImagen('local')" class="mr-2" style="width:auto;">
                                    Subir imagen
                                </label>
                                <label class="flex items-center" style="width:auto;font-weight:400;">
                                    <input type="radio" name="tipo_imagen" value="url" onchange="cambiarTipoImagen('url')" class="mr-2" style="width:auto;">
                                    URL externa
                                </label>
                            </div>

                            <div id="imagenLocal" class="mb-4">
                                <div class="drag-drop-area" id="dropArea">
                                    <input type="file" name="imagen_local" id="imagenFile" accept="image/*" class="hidden" onchange="previewImagen(this)">
                                    <div style="color:#94a3b8;">
                                        <div class="text-4xl mb-2"><i class="fas fa-cloud-upload-alt"></i></div>
                                        <p>Click o arrastra una imagen aquí</p>
                                    </div>
                                </div>
                            </div>

                            <div id="imagenUrl" class="mb-4 hidden">
                                <input type="url" name="imagen_url" id="imagenUrlInput"
                                       placeholder="https://ejemplo.com/imagen.jpg" onchange="previewImagenUrl(this.value)">
                            </div>

                            <div id="previewContainer" class="hidden">
                                <label>Vista previa</label>
                                <div id="imagePreview" style="width:100%;height:12rem;border-radius:10px;border:1px solid rgba(255,255,255,.12);background-color:rgba(255,255,255,.04);background-size:cover;background-position:center;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 3: Configuración -->
                    <div class="claut-wizard-panel" data-step="3">
                        <p class="claut-wizard-panel-eyebrow">Paso 3 de 3</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label>Posición</label>
                                <input type="number" name="posicion" id="posicion" min="1" value="1">
                            </div>
                            <div style="display:flex;align-items:flex-end;padding-bottom:.6rem;">
                                <label class="flex items-center" style="width:auto;font-weight:400;color:#cbd5e1;">
                                    <input type="checkbox" name="activo" id="activo" checked class="mr-2" style="width:auto;">
                                    Banner activo
                                </label>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label>Fecha inicio (opcional)</label>
                                <input type="datetime-local" name="fecha_inicio" id="fecha_inicio">
                            </div>
                            <div>
                                <label>Fecha fin (opcional)</label>
                                <input type="datetime-local" name="fecha_fin" id="fecha_fin">
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
                <button type="submit" id="submitBtn" data-wizard-submit class="porsche-btn" style="display:none;">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </form>
    </div>

    <script>
        function abrirModalNuevo() {
            const modal = document.getElementById('modalBanner');
            modal.classList.add('open');
            const wizardEl = modal.querySelector('.claut-wizard');
            if (wizardEl && wizardEl.__clautWizardReset) wizardEl.__clautWizardReset();
            document.getElementById('modalTitulo').textContent = 'Nuevo Banner';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save mr-2"></i>Crear Banner';
            document.getElementById('bannerId').value = '';
            document.getElementById('titulo').value = '';
            document.getElementById('descripcion').value = '';
            document.getElementById('imagenUrlInput').value = '';
            document.getElementById('imagenUrlActual').value = '';
            document.getElementById('posicion').value = '1';
            document.getElementById('fecha_inicio').value = '';
            document.getElementById('fecha_fin').value = '';
            document.getElementById('activo').checked = true;
            document.getElementById('imagePreview').style.backgroundImage = '';
            document.getElementById('previewContainer').classList.add('hidden');
            document.getElementById('imagenFile').value = '';
            cambiarTipoImagen('local');
        }
        
        function editarBanner(banner) {
            const modal = document.getElementById('modalBanner');
            modal.classList.add('open');
            const wizardEl = modal.querySelector('.claut-wizard');
            if (wizardEl && wizardEl.__clautWizardReset) wizardEl.__clautWizardReset();
            document.getElementById('modalTitulo').textContent = 'Editar Banner';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save mr-2"></i>Actualizar Banner';
            document.getElementById('bannerId').value = banner.id;
            document.getElementById('titulo').value = banner.titulo;
            document.getElementById('descripcion').value = banner.descripcion || '';
            document.getElementById('imagenUrlActual').value = banner.imagen_url;
            document.getElementById('posicion').value = banner.posicion;
            document.getElementById('fecha_inicio').value = banner.fecha_inicio || '';
            document.getElementById('fecha_fin').value = banner.fecha_fin || '';
            document.getElementById('activo').checked = banner.activo == 1;

            if (banner.imagen_url) {
                let imageUrl = banner.imagen_url;
                if (!imageUrl.startsWith('http')) {
                    imageUrl = '/' + imageUrl.replace(/^\//, '');
                }
                document.getElementById('imagePreview').style.backgroundImage = 'url(' + imageUrl + ')';
                document.getElementById('previewContainer').classList.remove('hidden');

                if (banner.imagen_url.startsWith('http')) {
                    cambiarTipoImagen('url');
                    document.getElementById('imagenUrlInput').value = banner.imagen_url;
                    document.querySelector('input[name="tipo_imagen"][value="url"]').checked = true;
                } else {
                    cambiarTipoImagen('local');
                    document.querySelector('input[name="tipo_imagen"][value="local"]').checked = true;
                }
            }
        }

        function cerrarModal() {
            document.getElementById('modalBanner').classList.remove('open');
        }

        // Función para filtrar banners
        function filtrarBanners() {
            const filtro = document.getElementById('filterEstado').value;
            const bannerItems = document.querySelectorAll('.banner-item');

            bannerItems.forEach(item => {
                const estado = item.getAttribute('data-estado');
                if (filtro === '' || estado === filtro) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        function cambiarTipoImagen(tipo) {
            if (tipo === 'local') {
                document.getElementById('imagenLocal').classList.remove('hidden');
                document.getElementById('imagenUrl').classList.add('hidden');
            } else {
                document.getElementById('imagenLocal').classList.add('hidden');
                document.getElementById('imagenUrl').classList.remove('hidden');
            }
        }
        
        function previewImagen(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').style.backgroundImage = 'url(' + e.target.result + ')';
                    document.getElementById('previewContainer').classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function previewImagenUrl(url) {
            if (url) {
                document.getElementById('imagePreview').style.backgroundImage = 'url(' + url + ')';
                document.getElementById('previewContainer').classList.remove('hidden');
            }
        }
        
        // Drag and Drop
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('imagenFile');
        
        if (dropArea && fileInput) {
            dropArea.addEventListener('click', function() {
                fileInput.click();
            });
            
            dropArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                dropArea.classList.add('dragover');
            });
            
            dropArea.addEventListener('dragleave', function() {
                dropArea.classList.remove('dragover');
            });
            
            dropArea.addEventListener('drop', function(e) {
                e.preventDefault();
                dropArea.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    previewImagen(fileInput);
                }
            });
        }
        
        // Cerrar modal al hacer clic fuera o con ESC
        document.getElementById('modalBanner').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('modalBanner').classList.contains('open')) {
                cerrarModal();
            }
        });
    </script>

    <script src="../assets/js/claut-admin-sidebar.js?v=20260901c"></script>
    <script src="../assets/js/claut-wizard.js?v=20260901a"></script>
</body>
</html>