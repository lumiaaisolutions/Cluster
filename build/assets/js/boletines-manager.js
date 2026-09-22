/**
 * Administrador de Boletines - Conecta con la base de datos real
 * Versión: 2.0
 * Autor: Fernando Torres
 */

class BoletinesManager {
    constructor() {
        this.config = window.BoletinesConfig || {};
        this.apiBase = this.config.apiEndpoints?.boletines || './api/boletines_simple.php';
        this.currentBoletin = null;
        this.boletines = [];
        this.cache = new Map();
        this.init();
    }

    async init() {
        try {
            await this.cargarBoletines();
            this.renderizarBoletines();
            this.setupEventListeners();
        } catch (error) {
            console.error('Error inicializando BoletinesManager:', error);
            this.mostrarError('Error al cargar los boletines');
        }
    }

    async cargarBoletines() {
        try {
            const response = await fetch(`${this.apiBase}?estado=publicado&limit=50&orderBy=fecha_publicacion&order=DESC`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.boletines = data.data || [];
                console.log('Boletines cargados:', this.boletines.length);
            } else {
                throw new Error(data.message || 'Error al obtener boletines');
            }
        } catch (error) {
            console.error('Error cargando boletines:', error);
            // Mostrar mensaje de error en lugar de datos de ejemplo
            this.boletines = [];
            throw error;
        }
    }

    renderizarBoletines() {
        const container = document.getElementById('boletines-grid');
        
        if (!container) {
            console.error('Contenedor de boletines no encontrado');
            return;
        }

        if (this.boletines.length === 0) {
            container.innerHTML = this.renderizarEstadoVacio();
            return;
        }

        container.innerHTML = this.boletines.map(boletin => this.renderizarTarjetaBoletin(boletin)).join('');
    }

    renderizarTarjetaBoletin(boletin) {
        const fechaFormateada = this.formatearFecha(boletin.fecha_publicacion);
        const esNuevo = this.esBoletinNuevo(boletin.fecha_publicacion);
        const resumen = boletin.resumen || this.extraerResumen(boletin.contenido);
        
        return `
            <div class="bg-white rounded-3xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden group cursor-pointer"
                 onclick="boletinesManager.abrirBoletin(${boletin.id})"
                 data-boletin-id="${boletin.id}">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="px-3 py-1 rounded-full text-sm font-medium ${this.getEstiloBadge(boletin, esNuevo)}">
                            ${this.getBadgeTexto(boletin, esNuevo)}
                        </span>
                        <span class="text-gray-500 text-sm">
                            ${fechaFormateada}
                        </span>
                    </div>
                    
                    <h3 class="text-xl font-semibold text-cluster-dark mb-3 group-hover:text-cluster-red transition-colors line-clamp-2">
                        ${this.escapeHtml(boletin.titulo)}
                    </h3>
                    
                    <p class="text-gray-600 mb-4 line-clamp-3">
                        ${this.escapeHtml(resumen)}
                    </p>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-gray-500 text-sm">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            ${boletin.visualizaciones || 0} vistas
                        </div>
                        <div class="text-cluster-red font-medium text-sm">
                            Ver más →
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderizarEstadoVacio() {
        return `
            <div class="col-span-full text-center py-16">
                <div class="max-w-md mx-auto">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No hay boletines disponibles</h3>
                    <p class="text-gray-500">Los boletines aparecerán aquí una vez que sean publicados.</p>
                    <button onclick="boletinesManager.cargarBoletines().then(() => boletinesManager.renderizarBoletines())" 
                            class="mt-4 bg-cluster-red text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Actualizar
                    </button>
                </div>
            </div>
        `;
    }

    async abrirBoletin(boletinId) {
        try {
            // Cargar datos completos del boletín
            const response = await fetch(`${this.apiBase}?id=${boletinId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Error al cargar el boletín');
            }

            this.currentBoletin = data.data;
            
            // Cargar archivos adjuntos si existen
            await this.cargarArchivosAdjuntos(boletinId);
            
            this.mostrarModal();
            
        } catch (error) {
            console.error('Error cargando boletín:', error);
            this.mostrarError('Error al cargar el boletín');
        }
    }

    async cargarArchivosAdjuntos(boletinId) {
        try {
            // Si el boletín ya tiene archivo_adjunto, usarlo directamente
            if (this.currentBoletin && this.currentBoletin.archivo_adjunto) {
                const archivo = this.currentBoletin.archivo_adjunto;
                const extension = archivo.split('.').pop().toLowerCase();
                
                // Crear objeto de archivo para mantener compatibilidad
                // Intentamos detectar si el archivo está en la carpeta específica de boletines
                const isBoletinSubfolder = archivo.startsWith('boletin_');
                const baseUrl = isBoletinSubfolder ? './uploads/boletines/' : './uploads/';

                this.currentBoletin.archivos = [{
                    id: this.currentBoletin.id,
                    nombre_original: archivo,
                    extension: extension,
                    icono: this.getFileIcon(extension),
                    es_pdf: extension === 'pdf',
                    es_imagen: ['jpg', 'jpeg', 'png', 'gif'].includes(extension),
                    tamaño_formateado: 'N/A',
                    url_vista: `${baseUrl}${archivo}`,
                    url_descarga: `${baseUrl}${archivo}`
                }];
                
                console.log('Archivo adjunto encontrado:', this.currentBoletin.archivos);
            } else {
                // Intentar cargar desde API
                const response = await fetch(`./api/boletines_archivos.php?boletin_id=${boletinId}`);
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.data) {
                        this.currentBoletin.archivos = data.data;
                    } else {
                        this.currentBoletin.archivos = [];
                    }
                } else {
                    this.currentBoletin.archivos = [];
                }
            }
        } catch (error) {
            console.log('Error cargando archivos adjuntos:', error);
            this.currentBoletin.archivos = [];
        }
    }

    getFileIcon(extension) {
        const icons = {
            'pdf': '📄',
            'doc': '📝', 'docx': '📝',
            'xls': '📊', 'xlsx': '📊',
            'ppt': '📽️', 'pptx': '📽️',
            'txt': '📄', 'csv': '📊',
            'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️',
            'mp4': '🎥', 'mp3': '🎵'
        };
        return icons[extension] || '📎';
    }

    mostrarModal() {
        if (!this.currentBoletin) return;

        const modal = document.getElementById('bulletinModal');
        const boletin = this.currentBoletin;

        // Usar las nuevas funciones de visualización de documentos
        if (typeof updateDocumentInfo === 'function') {
            updateDocumentInfo(boletin);
        } else {
            // Fallback a la funcionalidad original
            document.getElementById('modalTitle').textContent = boletin.titulo;
            document.getElementById('modalDate').textContent = this.formatearFecha(boletin.fecha_publicacion);
            document.getElementById('modalDescription').textContent = boletin.resumen || this.extraerResumen(boletin.contenido);
            this.actualizarInfoDocumento(boletin);
        }

        // Usar la nueva función de visualización de documentos
        if (typeof viewDocument === 'function' && boletin.archivo_adjunto) {
            viewDocument(boletin.archivo_adjunto, boletin.titulo);
        } else {
            // Fallback a la funcionalidad original
            this.mostrarContenidoBoletin(boletin);
        }
        
        // Mostrar modal con animación
        if (modal) {
            modal.classList.remove('opacity-0', 'invisible');
            const modalContent = modal.querySelector('.bg-white') || modal.querySelector('.bg-gray-900') || modal.querySelector('div > div');
            if (modalContent) {
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }
            document.body.style.overflow = 'hidden';
        }
    }

    actualizarInfoDocumento(boletin) {
        // Actualizar información básica
        const infoContainer = document.querySelector('.bg-cluster-gray .space-y-2');
        if (!infoContainer) return;

        const fechaCreacion = new Date(boletin.fecha_publicacion || boletin.fecha_creacion);
        const autor = boletin.autor || 'Cluster Intranet';
        const tieneArchivoAdjunto = boletin.archivo_adjunto && boletin.archivo_adjunto.length > 0;
        const tieneArchivos = (boletin.archivos && boletin.archivos.length > 0) || tieneArchivoAdjunto;
        
        let infoHTML = `
            <div class="flex justify-between">
                <span class="text-gray-600">Tipo:</span>
                <span class="font-medium">Boletín HTML</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Autor:</span>
                <span class="font-medium">${this.escapeHtml(autor)}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Fecha:</span>
                <span class="font-medium">${this.formatearFecha(boletin.fecha_publicacion)}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Vistas:</span>
                <span class="font-medium">${boletin.visualizaciones || 0}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Estado:</span>
                <span class="font-medium capitalize">${boletin.estado}</span>
            </div>
        `;
        
        if (tieneArchivos) {
            const numArchivos = boletin.archivos ? boletin.archivos.length : 1;
            infoHTML += `
            <div class="flex justify-between">
                <span class="text-gray-600">Archivos adjuntos:</span>
                <span class="font-medium">${numArchivos}</span>
            </div>
            `;
        }
        
        infoContainer.innerHTML = infoHTML;
        
        // Mostrar archivos adjuntos si existen
        this.mostrarArchivosAdjuntos(boletin.archivos || []);
    }

    mostrarContenidoBoletin(boletin) {
        const viewer = document.getElementById('documentFrame');
        const placeholder = document.getElementById('documentPlaceholder');
        
        // Crear HTML del contenido del boletín
        const contenidoHTML = this.generarHTMLBoletin(boletin);
        
        // Usar el visualizador universal si está disponible
        if (window.documentViewer && viewer) {
            const viewerContainer = viewer.parentElement;
            if (viewerContainer) {
                // Limpiar visualizadores anteriores
                const existingViewer = viewerContainer.querySelector('.custom-document-viewer');
                if (existingViewer) {
                    existingViewer.remove();
                }
                
                // Crear blob para el contenido HTML
                const blob = new Blob([contenidoHTML], { type: 'text/html' });
                const url = URL.createObjectURL(blob);
                
                // Usar el visualizador para mostrar el HTML
                const viewerHTML = window.documentViewer.generateViewerHTML(
                    url, 
                    'text/html', 
                    `${boletin.titulo}.html`
                );
                
                // Crear contenedor para el visualizador
                const customViewer = document.createElement('div');
                customViewer.className = 'custom-document-viewer w-full h-full';
                customViewer.innerHTML = viewerHTML;
                
                viewerContainer.appendChild(customViewer);
                
                // Ocultar iframe original y placeholder
                viewer.style.display = 'none';
                if (placeholder) placeholder.classList.add('hidden');
                
                // Limpiar URL después de un tiempo para evitar memory leaks
                setTimeout(() => {
                    URL.revokeObjectURL(url);
                }, 30000);
                
                return;
            }
        }
        
        // Fallback al método original si no hay visualizador universal
        const blob = new Blob([contenidoHTML], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        
        viewer.src = url;
        
        // Mostrar iframe y ocultar placeholder
        setTimeout(() => {
            if (placeholder) placeholder.classList.add('hidden');
            if (viewer) viewer.classList.remove('hidden');
        }, 300);

        // Limpiar URL después de un tiempo para evitar memory leaks
        setTimeout(() => {
            URL.revokeObjectURL(url);
        }, 30000);
    }

    generarHTMLBoletin(boletin) {
        return `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${this.escapeHtml(boletin.titulo)}</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            line-height: 1.6;
            color: #1D1D1F;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: #f8f9fa;
        }
        .boletin-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }
        .boletin-header {
            border-bottom: 3px solid #C7252B;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
        }
        .boletin-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1D1D1F;
            margin: 0 0 0.5rem 0;
        }
        .boletin-meta {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .boletin-content {
            font-size: 1rem;
            line-height: 1.7;
        }
        .boletin-content h1,
        .boletin-content h2,
        .boletin-content h3 {
            color: #C7252B;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .boletin-content p {
            margin-bottom: 1rem;
        }
        .boletin-content ul,
        .boletin-content ol {
            margin-bottom: 1rem;
            padding-left: 1.5rem;
        }
        .boletin-footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 0.8rem;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .boletin-container {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="boletin-container">
        <header class="boletin-header">
            <h1 class="boletin-title">${this.escapeHtml(boletin.titulo)}</h1>
            <div class="boletin-meta">
                <span>📅 ${this.formatearFecha(boletin.fecha_publicacion)}</span>
                <span>👤 ${this.escapeHtml(boletin.autor || 'Cluster Intranet')}</span>
                <span>👀 ${boletin.visualizaciones || 0} visualizaciones</span>
            </div>
        </header>
        
        <main class="boletin-content">
            ${this.formatearContenido(boletin.contenido)}
        </main>
        
        <footer class="boletin-footer">
            <p>Cluster Intranet © ${new Date().getFullYear()} - Todos los derechos reservados</p>
        </footer>
    </div>
</body>
</html>
        `;
    }

    formatearContenido(contenido) {
        if (!contenido) return '<p>No hay contenido disponible.</p>';
        
        // Convertir saltos de línea a párrafos
        return contenido
            .replace(/\n\s*\n/g, '</p><p>')
            .replace(/^/, '<p>')
            .replace(/$/, '</p>')
            .replace(/<p><\/p>/g, '');
    }

    cerrarModal() {
        const modal = document.getElementById('bulletinModal');
        const viewer = document.getElementById('documentFrame');
        const placeholder = document.getElementById('documentPlaceholder');
        
        if (modal) {
            modal.classList.add('opacity-0', 'invisible');
            const modalContent = modal.querySelector('.bg-white') || modal.querySelector('.bg-gray-900') || modal.querySelector('div > div');
            if (modalContent) {
                modalContent.classList.add('scale-95');
                modalContent.classList.remove('scale-100');
            }
        }
        
        // Reset iframe
        if (viewer) {
            viewer.src = '';
            viewer.classList.add('hidden');
        }
        if (placeholder) {
            placeholder.classList.remove('hidden');
        }
        
        // Limpiar archivos adjuntos
        const archivosContainer = document.getElementById('archivos-adjuntos');
        if (archivosContainer) {
            archivosContainer.remove();
        }
        
        // Limpiar estado de ruta en ventana global (opcional)
        window.currentBulletinPath = '';
        
        document.body.style.overflow = 'auto';
        this.currentBoletin = null;
    }

    setupEventListeners() {
        // Cerrar modal con botón
        const closeBtn = document.querySelector('[onclick="closeBulletin()"]');
        if (closeBtn) {
            closeBtn.onclick = () => this.cerrarModal();
        }

        // Cerrar modal al hacer click fuera
        const modal = document.getElementById('bulletinModal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.cerrarModal();
                }
            });
        }

        // Cerrar modal con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.cerrarModal();
            }
        });

        // Botón de descarga
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn) {
            downloadBtn.onclick = () => this.descargarBoletin();
        }
    }

    descargarBoletin() {
        if (!this.currentBoletin) return;
        
        const contenidoHTML = this.generarHTMLBoletin(this.currentBoletin);
        const blob = new Blob([contenidoHTML], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = `${this.currentBoletin.titulo.replace(/[^a-zA-Z0-9]/g, '_')}.html`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Utilidades
    formatearFecha(fechaString) {
        if (!fechaString) return 'Sin fecha';
        
        try {
            const fecha = new Date(fechaString);
            return fecha.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (error) {
            return fechaString;
        }
    }

    esBoletinNuevo(fechaPublicacion) {
        if (!fechaPublicacion) return false;
        
        const fecha = new Date(fechaPublicacion);
        const ahora = new Date();
        const diferenciaDias = (ahora - fecha) / (1000 * 60 * 60 * 24);
        
        return diferenciaDias <= 7; // Nuevo si tiene menos de 7 días
    }

    getEstiloBadge(boletin, esNuevo) {
        if (esNuevo) return 'bg-cluster-red text-white';
        if (boletin.destacado) return 'bg-cluster-red/20 text-cluster-red';
        return 'bg-gray-100 text-gray-600';
    }

    getBadgeTexto(boletin, esNuevo) {
        if (esNuevo) return 'Nuevo';
        if (boletin.destacado) return 'Destacado';
        return 'Archivo';
    }

    extraerResumen(contenido, maxLength = 150) {
        if (!contenido) return 'Sin descripción disponible.';
        
        const textoLimpio = contenido.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        
        if (textoLimpio.length <= maxLength) return textoLimpio;
        
        return textoLimpio.substring(0, maxLength).replace(/\s+\w*$/, '') + '...';
    }

    escapeHtml(text) {
        if (!text) return '';
        
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    mostrarError(mensaje) {
        console.error('BoletinesManager Error:', mensaje);
        
        // Mostrar notificación de error (implementar según el sistema de notificaciones existente)
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-red-500 text-white p-4 rounded-lg z-50';
        notification.textContent = mensaje;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }

    // Método público para recargar boletines
    async recargar() {
        try {
            await this.cargarBoletines();
            this.renderizarBoletines();
        } catch (error) {
            this.mostrarError('Error al recargar boletines');
        }
    }

    mostrarArchivosAdjuntos(archivos) {
        if (!archivos || archivos.length === 0) {
            console.log('No hay archivos adjuntos para mostrar');
            return;
        }
        
        console.log('Mostrando archivos adjuntos:', archivos);
        
        // Buscar el contenedor de descripción para agregar archivos después
        const descripcionContainer = document.querySelector('.bg-cluster-gray:last-of-type');
        if (!descripcionContainer) {
            console.error('No se encontró el contenedor de descripción');
            return;
        }
        
        // Crear contenedor de archivos si no existe
        let archivosContainer = document.getElementById('archivos-adjuntos');
        if (!archivosContainer) {
            archivosContainer = document.createElement('div');
            archivosContainer.id = 'archivos-adjuntos';
            archivosContainer.className = 'bg-cluster-gray rounded-2xl p-4 mt-4';
            descripcionContainer.parentNode.insertBefore(archivosContainer, descripcionContainer.nextSibling);
        }
        
        archivosContainer.innerHTML = `
            <h3 class="font-semibold text-cluster-dark mb-3">Archivos Adjuntos (${archivos.length})</h3>
            <div class="space-y-2">
                ${archivos.map(archivo => `
                    <div class="flex items-center justify-between p-2 bg-white rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center space-x-2 flex-1 min-w-0">
                            <span class="text-lg">${archivo.icono}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" title="${this.escapeHtml(archivo.nombre_original)}">
                                    ${this.escapeHtml(archivo.nombre_original)}
                                </p>
                                <p class="text-xs text-gray-500">
                                    ${archivo.tamaño_formateado}
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-1">
                            ${archivo.es_pdf || archivo.es_imagen ? `
                                <button onclick="boletinesManager.verArchivo(${archivo.id})" 
                                        class="p-1 text-blue-600 hover:bg-blue-50 rounded transition-colors" 
                                        title="Ver archivo">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            ` : ''}
                            <button onclick="boletinesManager.descargarArchivo(${archivo.id})" 
                                    class="p-1 text-green-600 hover:bg-green-50 rounded transition-colors" 
                                    title="Descargar archivo">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    verArchivo(archivoId) {
        if (!this.currentBoletin || !this.currentBoletin.archivos) {
            console.error('No hay archivos disponibles');
            return;
        }
        
        const archivo = this.currentBoletin.archivos.find(a => a.id == archivoId);
        if (!archivo) {
            console.error('Archivo no encontrado:', archivoId);
            return;
        }
        
        console.log('Visualizando archivo:', archivo);
        
        // Abrir en nueva pestaña si es PDF o imagen
        if (archivo.es_pdf || archivo.es_imagen) {
            window.open(archivo.url_vista, '_blank');
        } else {
            // Para otros archivos, descargar directamente
            this.descargarArchivo(archivoId);
        }
    }

    descargarArchivo(archivoId) {
        if (!this.currentBoletin || !this.currentBoletin.archivos) return;
        
        const archivo = this.currentBoletin.archivos.find(a => a.id == archivoId);
        if (!archivo) return;
        
        // Crear enlace temporal para descarga
        const a = document.createElement('a');
        a.href = archivo.url_descarga;
        a.download = archivo.nombre_original;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

// Inicializar cuando el DOM esté listo
let boletinesManager;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        boletinesManager = new BoletinesManager();
    });
} else {
    boletinesManager = new BoletinesManager();
}

// Funciones globales para compatibilidad con el HTML existente
function openBulletin(boletinId) {
    if (boletinesManager) {
        boletinesManager.abrirBoletin(boletinId);
    }
}

function closeBulletin() {
    if (boletinesManager) {
        boletinesManager.cerrarModal();
    }
}
