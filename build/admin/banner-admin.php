<?php
/**
 * Panel de Administración de Banners Mejorado
 * Permite gestionar el carrusel de banners del login
 */

// Verificar si existe un sistema de autenticación
session_start();

require_once '../config/database.php';

// Funciones para manejar banners
function obtenerBanners($conn) {
    try {
        $stmt = $conn->query("
            SELECT id, titulo, descripcion, imagen_url, posicion, activo, 
                   fecha_inicio, fecha_fin, fecha_creacion 
            FROM banner_carrusel 
            ORDER BY posicion ASC, fecha_creacion DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function guardarBanner($conn, $datos) {
    try {
        if (isset($datos['id']) && $datos['id']) {
            // Actualizar banner existente
            $stmt = $conn->prepare("
                UPDATE banner_carrusel 
                SET titulo = ?, descripcion = ?, imagen_url = ?, posicion = ?, 
                    activo = ?, fecha_inicio = ?, fecha_fin = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $datos['titulo'],
                $datos['descripcion'],
                $datos['imagen_url'],
                $datos['posicion'],
                $datos['activo'] ? 1 : 0,
                $datos['fecha_inicio'] ?: null,
                $datos['fecha_fin'] ?: null,
                $datos['id']
            ]);
        } else {
            // Crear nuevo banner
            $stmt = $conn->prepare("
                INSERT INTO banner_carrusel (titulo, descripcion, imagen_url, posicion, activo, fecha_inicio, fecha_fin)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $datos['titulo'],
                $datos['descripcion'],
                $datos['imagen_url'],
                $datos['posicion'],
                $datos['activo'] ? 1 : 0,
                $datos['fecha_inicio'] ?: null,
                $datos['fecha_fin'] ?: null
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error al guardar banner: " . $e->getMessage());
        return false;
    }
}

function eliminarBanner($conn, $id) {
    try {
        $stmt = $conn->prepare("DELETE FROM banner_carrusel WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        return false;
    }
}

// Manejar acciones POST
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'guardar':
            $datos = [
                'id' => $_POST['id'] ?? null,
                'titulo' => $_POST['titulo'] ?? '',
                'descripcion' => $_POST['descripcion'] ?? '',
                'imagen_url' => $_POST['imagen_url'] ?? '',
                'posicion' => intval($_POST['posicion'] ?? 1),
                'activo' => isset($_POST['activo']),
                'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
                'fecha_fin' => $_POST['fecha_fin'] ?? ''
            ];
            
            if (guardarBanner($conn, $datos)) {
                $mensaje = $datos['id'] ? 'Banner actualizado correctamente' : 'Banner creado correctamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al guardar el banner';
                $tipo_mensaje = 'error';
            }
            break;
            
        case 'eliminar':
            $id = $_POST['id'] ?? 0;
            if (eliminarBanner($conn, $id)) {
                $mensaje = 'Banner eliminado correctamente';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar el banner';
                $tipo_mensaje = 'error';
            }
            break;
    }
}

// Obtener banners para mostrar
$db = Database::getInstance();
$conn = $db->getConnection();
$banners = obtenerBanners($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Banners Carrusel</title>
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .mensaje {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .mensaje.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .mensaje.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .banner-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .banner-card.inactive {
            opacity: 0.6;
            background: #e9ecef;
        }
        
        .banner-preview {
            width: 100px;
            height: 60px;
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            float: right;
            margin-left: 15px;
        }
        
        .status-badge {
            position: absolute;
            top: 15px;
            right: 120px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .toolbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .banner-preview {
                float: none;
                margin: 10px 0;
            }
            
            .status-badge {
                position: relative;
                top: auto;
                right: auto;
                display: inline-block;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-palette"></i> Panel de Banners</h1>
            <p>Administra el carrusel de banners del login</p>
        </div>
        
        <div class="content">
            <?php if ($mensaje): ?>
                <div class="mensaje <?= $tipo_mensaje ?>">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>
            
            <!-- Estadísticas -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= count($banners) ?></div>
                    <div>Total Banners</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($banners, fn($b) => $b['activo'])) ?></div>
                    <div>Banners Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($banners, fn($b) => !$b['activo'])) ?></div>
                    <div>Banners Inactivos</div>
                </div>
            </div>
            
            <!-- Barra de herramientas -->
            <div class="toolbar">
                <h2>Gestión de Banners</h2>
                <div>
                    <button class="btn btn-success" onclick="abrirModal()">
                        + Nuevo Banner
                    </button>
                    <a href="../pages/sign-in.html" class="btn btn-secondary" target="_blank">
                        <i class="fas fa-eye"></i> Vista Previa
                    </a>
                    <a href="../api/banners.php" class="btn btn-primary" target="_blank">
                        <i class="fas fa-link"></i> API JSON
                    </a>
                </div>
            </div>
            
            <!-- Lista de banners -->
            <div class="banners-list">
                <?php if (empty($banners)): ?>
                    <div class="banner-card" style="text-align: center; padding: 40px;">
                        <h3>No hay banners configurados</h3>
                        <p>Crea tu primer banner para comenzar</p>
                        <button class="btn btn-primary" onclick="abrirModal()">
                            Crear Primer Banner
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($banners as $banner): ?>
                        <div class="banner-card <?= !$banner['activo'] ? 'inactive' : '' ?>">
                            <div class="banner-preview" style="background-image: url('<?= htmlspecialchars($banner['imagen_url']) ?>')"></div>
                            
                            <div class="status-badge <?= $banner['activo'] ? 'active' : 'inactive' ?>">
                                <?= $banner['activo'] ? 'ACTIVO' : 'INACTIVO' ?>
                            </div>
                            
                            <h3><?= htmlspecialchars($banner['titulo']) ?></h3>
                            <p style="color: #666; margin: 10px 0;">
                                <?= htmlspecialchars($banner['descripcion']) ?>
                            </p>
                            
                            <div style="font-size: 14px; color: #888; margin: 10px 0;">
                                <strong>Posición:</strong> <?= $banner['posicion'] ?> | 
                                <strong>Creado:</strong> <?= date('d/m/Y H:i', strtotime($banner['fecha_creacion'])) ?>
                            </div>
                            
                            <div class="actions">
                                <button class="btn btn-primary" onclick="editarBanner(<?= htmlspecialchars(json_encode($banner)) ?>)">
                                    <i class="fas fa-pen"></i> Editar
                                </button>
                                
                                <form method="post" style="display: inline;" 
                                      onsubmit="return confirm('¿Estás seguro de eliminar este banner?')">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= $banner['id'] ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal para crear/editar banner -->
    <div id="bannerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            
            <h2 id="modalTitle">Nuevo Banner</h2>
            
            <form method="post" id="bannerForm">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="bannerId">
                
                <div class="form-group">
                    <label for="titulo">Título del Banner</label>
                    <input type="text" id="titulo" name="titulo" required 
                           placeholder="Ej: Bienvenido a Clúster">
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="3" 
                              placeholder="Descripción del banner que aparecerá en el carrusel"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="imagen_url">URL de la Imagen</label>
                    <input type="url" id="imagen_url" name="imagen_url" required 
                           placeholder="https://ejemplo.com/imagen.jpg"
                           onchange="previsualizarImagen()">
                    <small style="color: #666;">
                        Recomendado: 1920x1080px o superior. Formatos: JPG, PNG, WebP
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Vista Previa de la Imagen</label>
                    <div id="imagenPreview" style="width: 100%; height: 200px; border: 2px dashed #ddd; border-radius: 8px; background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; color: #666;">
                        Sin imagen seleccionada
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="posicion">Posición en el Carrusel</label>
                        <input type="number" id="posicion" name="posicion" min="1" max="10" value="1">
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="activo" name="activo" checked>
                            <label for="activo">Banner Activo</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha de Inicio (Opcional)</label>
                        <input type="datetime-local" id="fecha_inicio" name="fecha_inicio">
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_fin">Fecha de Fin (Opcional)</label>
                        <input type="datetime-local" id="fecha_fin" name="fecha_fin">
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-floppy-disk"></i> Guardar Banner
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                        <i class="fas fa-circle-xmark"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function abrirModal() {
            document.getElementById('bannerModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Nuevo Banner';
            document.getElementById('bannerForm').reset();
            document.getElementById('bannerId').value = '';
            document.getElementById('imagenPreview').style.backgroundImage = '';
            document.getElementById('imagenPreview').textContent = 'Sin imagen seleccionada';
        }
        
        function cerrarModal() {
            document.getElementById('bannerModal').style.display = 'none';
        }
        
        function editarBanner(banner) {
            document.getElementById('bannerModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Editar Banner';
            
            document.getElementById('bannerId').value = banner.id;
            document.getElementById('titulo').value = banner.titulo;
            document.getElementById('descripcion').value = banner.descripcion || '';
            document.getElementById('imagen_url').value = banner.imagen_url;
            document.getElementById('posicion').value = banner.posicion;
            document.getElementById('activo').checked = banner.activo == 1;
            document.getElementById('fecha_inicio').value = banner.fecha_inicio ? banner.fecha_inicio.replace(' ', 'T') : '';
            document.getElementById('fecha_fin').value = banner.fecha_fin ? banner.fecha_fin.replace(' ', 'T') : '';
            
            previsualizarImagen();
        }
        
        function previsualizarImagen() {
            const url = document.getElementById('imagen_url').value;
            const preview = document.getElementById('imagenPreview');
            
            if (url) {
                preview.style.backgroundImage = `url(${url})`;
                preview.textContent = '';
            } else {
                preview.style.backgroundImage = '';
                preview.textContent = 'Sin imagen seleccionada';
            }
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('bannerModal');
            if (event.target == modal) {
                cerrarModal();
            }
        }
        
        // Validación del formulario
        document.getElementById('bannerForm').addEventListener('submit', function(e) {
            const titulo = document.getElementById('titulo').value.trim();
            const imagenUrl = document.getElementById('imagen_url').value.trim();
            
            if (!titulo || !imagenUrl) {
                e.preventDefault();
                alert('Por favor, completa todos los campos requeridos.');
                return;
            }
            
            // Validar que la URL es válida
            try {
                new URL(imagenUrl);
            } catch (error) {
                e.preventDefault();
                alert('Por favor, introduce una URL de imagen válida.');
                return;
            }
        });
        
        // Auto-refresh de la página cada 30 segundos para mostrar cambios
        // setInterval(() => {
        //     if (!document.getElementById('bannerModal').style.display || document.getElementById('bannerModal').style.display === 'none') {
        //         location.reload();
        //     }
        // }, 30000);
    </script>
</body>
</html>