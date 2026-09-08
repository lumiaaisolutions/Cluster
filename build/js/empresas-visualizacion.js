/**
 * Visualización simple de empresas - empresas-convenio.html
 * Solo mostrar, sin funcionalidades de administración
 */

class EmpresasVisualizacionManager {
    constructor() {
        this.empresas = [];
        this.apiUrl = './api/empresas-simple.php';
        this.init();
    }

    init() {
        // console.log('👁️ Inicializando visualización de empresas...');
        this.setupEventListeners();
        this.cargarEmpresas();
        this.crearModalVisualizacion();
    }

    setupEventListeners() {
        // Botón actualizar
        const btnActualizar = document.getElementById('btnActualizarEmpresas');
        if (btnActualizar) {
            btnActualizar.addEventListener('click', () => this.cargarEmpresas());
        }

        // Búsqueda simple
        const busquedaInput = document.getElementById('busquedaEmpresa');
        if (busquedaInput) {
            busquedaInput.addEventListener('input', (e) => this.filtrarEmpresas(e.target.value));
        }

        // Filtro de estado
        const filtroEstado = document.getElementById('filtroEstado');
        if (filtroEstado) {
            filtroEstado.addEventListener('change', () => this.aplicarFiltros());
        }
    }

    async cargarEmpresas() {
        try {
            // console.log('📡 Cargando empresas desde:', this.apiUrl);
            this.mostrarCargando(true);
            
            const response = await fetch(`${this.apiUrl}?action=listar`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            // console.log('📡 Respuesta HTTP:', response.status, response.statusText);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            // console.log('📡 Datos recibidos:', data);

            if (data.success) {
                this.empresas = data.data.empresas;
                // console.log(`✅ ${this.empresas.length} empresas cargadas`);
                this.renderizarEmpresas();
                this.actualizarContador();
            } else {
                throw new Error(data.message || 'Error en respuesta de API');
            }
        } catch (error) {
            console.error('❌ Error completo:', error);
            this.mostrarError(`Error al cargar empresas: ${error.message}`);
        } finally {
            this.mostrarCargando(false);
        }
    }

    renderizarEmpresas(empresasList = null) {
        const container = document.getElementById('empresasContainer');
        if (!container) return;

        const empresas = empresasList || this.empresas;

        if (empresas.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-building text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No hay empresas disponibles</h3>
                    <p class="text-gray-500">No se encontraron empresas que coincidan con los filtros aplicados.</p>
                </div>
            `;
            return;
        }

        const empresasHTML = empresas.map(empresa => this.crearTarjetaEmpresa(empresa)).join('');
        container.innerHTML = empresasHTML;

        // Configurar eventos de click en las imágenes
        this.configurarEventosClick();
    }

    crearTarjetaEmpresa(empresa) {
        const logoUrl = empresa.logo_url || this.generarLogoDefault(empresa.nombre);
        const descripcion = empresa.descripcion_corta || empresa.descripcion || 'Información de la empresa disponible al hacer click en la imagen.';
        const sector = empresa.sector || 'Sin especificar';

        return `
            <div class="empresa-card bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden" 
                 data-empresa-id="${empresa.id}">
                <!-- Imagen clickeable -->
                <div class="relative h-48 bg-gradient-to-br from-blue-50 to-indigo-100 cursor-pointer group empresa-logo-container" 
                     data-empresa-id="${empresa.id}"
                     title="Click para ver información">
                    <img src="${logoUrl}" 
                         alt="Logo de ${empresa.nombre}"
                         class="w-full h-full object-contain p-4 transition-transform duration-300 group-hover:scale-105"
                         onerror="this.src='${this.generarLogoDefault(empresa.nombre)}'">
                    
                    <!-- Overlay con efecto hover -->
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 text-center">
                            <i class="fas fa-eye text-white text-3xl mb-2"></i>
                            <p class="text-white text-sm font-medium">Ver información</p>
                        </div>
                    </div>
                    
                    <!-- Badge de descuento si existe -->
                    ${empresa.descuento_porcentaje ? `
                        <div class="absolute top-3 right-3 bg-green-500 text-white px-2 py-1 rounded-full text-sm font-semibold">
                            -${empresa.descuento_porcentaje}%
                        </div>
                    ` : ''}
                </div>

                <!-- Información básica -->
                <div class="p-6">
                    <div class="text-center">
                        <h3 class="text-xl font-bold text-gray-900 mb-2">${empresa.nombre}</h3>
                        
                        ${sector !== 'Sin especificar' ? `
                            <span class="inline-flex items-center px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full mb-3">
                                <i class="fas fa-industry mr-1"></i>
                                ${sector}
                            </span>
                        ` : ''}
                        
                        <p class="text-gray-600 text-sm leading-relaxed line-clamp-3">${descripcion}</p>
                        
                        <!-- Estado -->
                        <div class="mt-4">
                            <span class="px-3 py-1 text-xs rounded-full ${empresa.estado === 'activa' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                ${empresa.estado === 'activa' ? 'Convenio Activo' : 'Estado: ' + empresa.estado}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    configurarEventosClick() {
        const containers = document.querySelectorAll('.empresa-logo-container');
        containers.forEach(container => {
            container.addEventListener('click', (e) => {
                e.preventDefault();
                const empresaId = container.dataset.empresaId;
                this.abrirModalEmpresa(empresaId);
            });
        });
    }

    crearModalVisualizacion() {
        if (document.getElementById('modalEmpresaInfo')) return;

        const modal = document.createElement('div');
        modal.id = 'modalEmpresaInfo';
        modal.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50';
        modal.style.backdropFilter = 'blur(4px)';
        
        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="flex items-center justify-between p-6 border-b">
                    <h2 id="modalEmpresaNombre" class="text-2xl font-bold text-gray-900"></h2>
                    <button id="btnCerrarModalInfo" class="text-gray-400 hover:text-gray-600 text-2xl transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Contenido -->
                <div id="modalEmpresaContenido" class="p-6">
                    <!-- Se carga dinámicamente -->
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Eventos del modal
        document.getElementById('btnCerrarModalInfo').addEventListener('click', () => this.cerrarModal());
        
        // Cerrar al hacer click fuera
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.cerrarModal();
        });
        
        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                this.cerrarModal();
            }
        });
    }

    async abrirModalEmpresa(empresaId) {
        try {
            const empresa = this.empresas.find(e => e.id == empresaId);
            if (!empresa) {
                throw new Error('Empresa no encontrada');
            }

            // Actualizar contenido del modal
            document.getElementById('modalEmpresaNombre').textContent = empresa.nombre;
            document.getElementById('modalEmpresaContenido').innerHTML = this.generarContenidoModal(empresa);

            // Mostrar modal con animación
            const modal = document.getElementById('modalEmpresaInfo');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

        } catch (error) {
            console.error('❌ Error abriendo modal:', error);
            this.mostrarError('Error al cargar información de la empresa');
        }
    }

    generarContenidoModal(empresa) {
        const logoUrl = empresa.logo_url || this.generarLogoDefault(empresa.nombre);
        
        return `
            <!-- Logo centrado -->
            <div class="text-center mb-6">
                <img src="${logoUrl}" 
                     alt="Logo de ${empresa.nombre}"
                     class="mx-auto h-32 w-auto object-contain rounded-lg shadow-md border border-gray-200"
                     onerror="this.src='${this.generarLogoDefault(empresa.nombre)}'">
            </div>

            <!-- Descripción -->
            ${empresa.descripcion ? `
                <div class="mb-6 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Acerca de la empresa</h3>
                    <p class="text-gray-700 leading-relaxed">${empresa.descripcion}</p>
                </div>
            ` : ''}

            <!-- Información de contacto si está disponible -->
            ${(empresa.email || empresa.telefono || empresa.direccion) ? `
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 text-center">Información de Contacto</h3>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        ${empresa.email ? `
                            <div class="flex items-center justify-center">
                                <i class="fas fa-envelope text-blue-600 w-5 mr-3"></i>
                                <span class="text-gray-700">${empresa.email}</span>
                            </div>
                        ` : ''}
                        ${empresa.telefono ? `
                            <div class="flex items-center justify-center">
                                <i class="fas fa-phone text-blue-600 w-5 mr-3"></i>
                                <span class="text-gray-700">${empresa.telefono}</span>
                            </div>
                        ` : ''}
                        ${empresa.direccion ? `
                            <div class="flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-blue-600 w-5 mr-3"></i>
                                <span class="text-gray-700 text-center">${empresa.direccion}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            ` : ''}

            <!-- Beneficios si están disponibles -->
            ${(empresa.beneficios || empresa.descuento_porcentaje) ? `
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 text-center">Beneficios para Empleados de Clúster</h3>
                    
                    ${empresa.descuento_porcentaje ? `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4 text-center">
                            <div class="flex items-center justify-center mb-2">
                                <i class="fas fa-percentage text-green-600 text-2xl mr-3"></i>
                                <div>
                                    <h4 class="font-semibold text-green-800">Descuento Especial</h4>
                                    <p class="text-green-700 text-lg font-bold">${empresa.descuento_porcentaje}% de descuento</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}

                    ${empresa.beneficios ? `
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <h4 class="font-semibold text-blue-800 mb-2">Beneficios Adicionales</h4>
                            <p class="text-blue-700">${empresa.beneficios}</p>
                        </div>
                    ` : ''}
                </div>
            ` : ''}

            <!-- Términos y condiciones si están disponibles -->
            ${empresa.condiciones ? `
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 text-center">Términos y Condiciones</h3>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-yellow-800 text-sm text-center">${empresa.condiciones}</p>
                    </div>
                </div>
            ` : ''}

            <!-- Botón para visitar sitio web -->
            ${empresa.sitio_web ? `
                <div class="text-center pt-4 border-t border-gray-200">
                    <a href="${empresa.sitio_web}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors">
                        <i class="fas fa-external-link-alt mr-2"></i>
                        Visitar Sitio Web
                    </a>
                </div>
            ` : ''}
        `;
    }

    cerrarModal() {
        const modal = document.getElementById('modalEmpresaInfo');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }

    filtrarEmpresas(termino) {
        if (!termino.trim()) {
            this.renderizarEmpresas();
            return;
        }

        const empresasFiltradas = this.empresas.filter(empresa => 
            empresa.nombre.toLowerCase().includes(termino.toLowerCase()) ||
            (empresa.descripcion && empresa.descripcion.toLowerCase().includes(termino.toLowerCase())) ||
            (empresa.sector && empresa.sector.toLowerCase().includes(termino.toLowerCase()))
        );

        this.renderizarEmpresas(empresasFiltradas);
        this.actualizarContador(empresasFiltradas.length);
    }

    aplicarFiltros() {
        const filtroEstado = document.getElementById('filtroEstado');
        const estado = filtroEstado ? filtroEstado.value : 'todas';
        
        let empresasFiltradas = [...this.empresas];
        
        if (estado !== 'todas') {
            empresasFiltradas = empresasFiltradas.filter(empresa => empresa.estado === estado);
        }

        this.renderizarEmpresas(empresasFiltradas);
        this.actualizarContador(empresasFiltradas.length);
    }

    actualizarContador(cantidad = null) {
        const contador = document.getElementById('contadorEmpresas');
        if (contador) {
            const total = cantidad !== null ? cantidad : this.empresas.length;
            contador.textContent = `${total} empresa${total !== 1 ? 's' : ''} en convenio`;
        }
    }

    mostrarCargando(mostrar) {
        const container = document.getElementById('empresasContainer');
        if (!container) return;

        if (mostrar) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                    <p class="text-gray-600">Cargando empresas...</p>
                </div>
            `;
        }
    }

    mostrarError(mensaje) {
        const container = document.getElementById('empresasContainer');
        if (container) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Error al cargar empresas</h3>
                    <p class="text-gray-500 mb-4">${mensaje}</p>
                    <button onclick="empresasViewer.cargarEmpresas()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Reintentar
                    </button>
                </div>
            `;
        }
        console.error('❌ Error:', mensaje);
    }

    generarLogoDefault(nombre) {
        return `https://ui-avatars.com/api/?name=${encodeURIComponent(nombre)}&background=6366f1&color=ffffff&bold=true&length=2&size=300&font-size=0.33`;
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.empresasViewer = new EmpresasVisualizacionManager();
});