/**
 * JavaScript para el Panel de Administración de Banners
 * Maneja la interfaz de usuario y las operaciones CRUD
 */

let editandoBanner = false;
let bannerEditId = null;

// Inicializar la aplicación
document.addEventListener('DOMContentLoaded', function() {
    cargarBanners();
    configurarDragDrop();
    configurarFormulario();
});

// Cargar banners desde la API
async function cargarBanners() {
    try {
        mostrarCarga(true);
        const response = await fetch('../api/banners.php?action=all');
        const result = await response.json();

        if (result.success) {
            mostrarBanners(result.data);
        } else {
            mostrarAlerta('Error al cargar banners: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('Error de conexión al cargar banners', 'error');
    } finally {
        mostrarCarga(false);
    }
}

// Mostrar banners en la interfaz
function mostrarBanners(banners) {
    const grid = document.getElementById('bannersGrid');
    const noMessage = document.getElementById('noBannersMessage');

    if (banners.length === 0) {
        grid.classList.add('hidden');
        noMessage.classList.remove('hidden');
        return;
    }

    grid.classList.remove('hidden');
    noMessage.classList.add('hidden');

    grid.innerHTML = banners.map(banner => `
        <div class="banner-card bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="relative">
                <img src="${banner.imagen_url}" alt="${banner.titulo}" 
                     class="w-full h-48 object-cover" onerror="this.src='../assets/img/placeholder.svg';">
                <div class="absolute top-2 right-2">
                    <span class="status-badge ${banner.activo == 1 ? 'status-active' : 'status-inactive'}">
                        ${banner.activo == 1 ? 'Activo' : 'Inactivo'}
                    </span>
                </div>
                <div class="absolute top-2 left-2 bg-blue-500 text-white px-2 py-1 rounded text-sm font-semibold">
                    #${banner.posicion}
                </div>
            </div>
            <div class="p-4">
                <h3 class="font-semibold text-lg text-gray-900 mb-2">${banner.titulo}</h3>
                <p class="text-gray-600 text-sm mb-4 line-clamp-2">${banner.descripcion || 'Sin descripción'}</p>
                <div class="flex justify-between items-center">
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-user mr-1"></i>
                        ${banner.creador_nombre || 'Sistema'}
                    </div>
                    <div class="flex space-x-2">
                        <button class="btn btn-warning btn-sm" onclick="editarBanner(${banner.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="eliminarBanner(${banner.id}, '${banner.titulo.replace(/'/g, "\\'")}'))" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Configurar drag and drop para imágenes
function configurarDragDrop() {
    const dragDropArea = document.getElementById('dragDropArea');
    const fileInput = document.getElementById('imagenFile');

    dragDropArea.addEventListener('click', () => fileInput.click());

    dragDropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dragDropArea.classList.add('dragover');
    });

    dragDropArea.addEventListener('dragleave', () => {
        dragDropArea.classList.remove('dragover');
    });

    dragDropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dragDropArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            manejarArchivoSeleccionado(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            manejarArchivoSeleccionado(e.target.files[0]);
        }
    });
}

// Manejar archivo de imagen seleccionado
function manejarArchivoSeleccionado(archivo) {
    // Validar tipo de archivo
    const tiposPermitidos = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!tiposPermitidos.includes(archivo.type)) {
        mostrarAlerta('Tipo de archivo no válido. Solo se permiten: JPG, PNG, WEBP', 'error');
        return;
    }

    // Validar tamaño (5MB)
    if (archivo.size > 5 * 1024 * 1024) {
        mostrarAlerta('El archivo es muy grande. Tamaño máximo: 5MB', 'error');
        return;
    }

    // Mostrar vista previa
    const reader = new FileReader();
    reader.onload = (e) => {
        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        
        previewImg.src = e.target.result;
        imagePreview.classList.remove('hidden');
    };
    reader.readAsDataURL(archivo);

    // Subir archivo
    subirImagen(archivo);
}

// Subir imagen al servidor
async function subirImagen(archivo) {
    try {
        mostrarCarga(true);
        
        const formData = new FormData();
        formData.append('imagen', archivo);

        const response = await fetch('../api/banners.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            document.getElementById('imagen_url').value = result.url;
            mostrarAlerta('Imagen subida exitosamente', 'success');
        } else {
            mostrarAlerta('Error al subir imagen: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('Error al subir imagen', 'error');
    } finally {
        mostrarCarga(false);
    }
}

// Configurar formulario
function configurarFormulario() {
    const form = document.getElementById('bannerForm');
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await guardarBanner();
    });
}

// Guardar banner (crear o actualizar)
async function guardarBanner() {
    try {
        mostrarCargaBoton(true);

        const formData = new FormData(document.getElementById('bannerForm'));
        const data = Object.fromEntries(formData.entries());

        // Validar campos requeridos
        if (!data.titulo || !data.imagen_url) {
            mostrarAlerta('Título e imagen son requeridos', 'error');
            return;
        }

        const url = editandoBanner 
            ? `../api/banners.php?id=${bannerEditId}`
            : '../api/banners.php';
        
        const method = editandoBanner ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            mostrarAlerta(
                editandoBanner ? 'Banner actualizado exitosamente' : 'Banner creado exitosamente', 
                'success'
            );
            cerrarModal();
            cargarBanners();
        } else {
            mostrarAlerta('Error: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('Error al guardar banner', 'error');
    } finally {
        mostrarCargaBoton(false);
    }
}

// Editar banner
async function editarBanner(id) {
    try {
        mostrarCarga(true);
        
        const response = await fetch(`../api/banners.php?action=all`);
        const result = await response.json();

        if (result.success) {
            const banner = result.data.find(b => b.id == id);
            if (banner) {
                llenarFormularioEditar(banner);
                abrirModalEditar();
            }
        } else {
            mostrarAlerta('Error al cargar banner: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('Error al cargar banner', 'error');
    } finally {
        mostrarCarga(false);
    }
}

// Llenar formulario con datos del banner a editar
function llenarFormularioEditar(banner) {
    document.getElementById('titulo').value = banner.titulo;
    document.getElementById('descripcion').value = banner.descripcion || '';
    document.getElementById('imagen_url').value = banner.imagen_url;
    document.getElementById('posicion').value = banner.posicion;
    document.getElementById('activo').value = banner.activo;
    
    if (banner.fecha_inicio) {
        document.getElementById('fecha_inicio').value = new Date(banner.fecha_inicio).toISOString().slice(0, 16);
    }
    if (banner.fecha_fin) {
        document.getElementById('fecha_fin').value = new Date(banner.fecha_fin).toISOString().slice(0, 16);
    }

    // Mostrar vista previa de la imagen
    const previewImg = document.getElementById('previewImg');
    const imagePreview = document.getElementById('imagePreview');
    previewImg.src = banner.imagen_url;
    imagePreview.classList.remove('hidden');

    bannerEditId = banner.id;
}

// Eliminar banner
async function eliminarBanner(id, titulo) {
    if (!confirm(`¿Estás seguro de que deseas eliminar el banner "${titulo}"?`)) {
        return;
    }

    try {
        mostrarCarga(true);
        
        const response = await fetch(`../api/banners.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            mostrarAlerta('Banner eliminado exitosamente', 'success');
            cargarBanners();
        } else {
            mostrarAlerta('Error al eliminar banner: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarAlerta('Error al eliminar banner', 'error');
    } finally {
        mostrarCarga(false);
    }
}

// Funciones para manejar modales
function abrirModalCrear() {
    editandoBanner = false;
    bannerEditId = null;
    document.getElementById('modalTitle').textContent = 'Nuevo Banner';
    document.getElementById('submitText').textContent = 'Crear Banner';
    limpiarFormulario();
    document.getElementById('bannerModal').style.display = 'block';
}

function abrirModalEditar() {
    editandoBanner = true;
    document.getElementById('modalTitle').textContent = 'Editar Banner';
    document.getElementById('submitText').textContent = 'Actualizar Banner';
    document.getElementById('bannerModal').style.display = 'block';
}

function cerrarModal() {
    document.getElementById('bannerModal').style.display = 'none';
    limpiarFormulario();
}

function limpiarFormulario() {
    document.getElementById('bannerForm').reset();
    document.getElementById('imagen_url').value = '';
    document.getElementById('imagePreview').classList.add('hidden');
    editandoBanner = false;
    bannerEditId = null;
}

// Funciones de utilidad
function mostrarAlerta(mensaje, tipo) {
    const alertContainer = document.getElementById('alertContainer');
    const alertClass = tipo === 'error' ? 'alert-error' : 'alert-success';
    
    alertContainer.innerHTML = `
        <div class="alert ${alertClass}">
            <i class="fas fa-${tipo === 'error' ? 'exclamation-triangle' : 'check-circle'} mr-2"></i>
            ${mensaje}
        </div>
    `;

    // Ocultar alerta después de 5 segundos
    setTimeout(() => {
        alertContainer.innerHTML = '';
    }, 5000);
}

function mostrarCarga(mostrar) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = mostrar ? 'block' : 'none';
}

function mostrarCargaBoton(mostrar) {
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitSpinner = document.getElementById('submitSpinner');

    if (mostrar) {
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        submitSpinner.classList.remove('hidden');
    } else {
        submitBtn.disabled = false;
        submitText.classList.remove('hidden');
        submitSpinner.classList.add('hidden');
    }
}

// Cerrar modal al hacer clic fuera de él
window.onclick = function(event) {
    const modal = document.getElementById('bannerModal');
    if (event.target === modal) {
        cerrarModal();
    }
}