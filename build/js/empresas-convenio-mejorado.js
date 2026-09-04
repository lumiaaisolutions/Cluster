/**
 * Gestión mejorada de empresas en convenio con vista previa modal
 */

class EmpresasConvenioManager {
    constructor() {
        window.empresasManager = this; // Asegurar disponibilidad global inmediata
        this.empresas = [];
        this.filtros = {
            estado: '', // Entidad Federativa (Estado geográfico)
            busqueda: '',
            sector: '',
            municipio: '',
            exporta: '',
            ordenPor: 'nombre'
        };
        this.apiUrl = './api/empresas-simple.php';
        this.init();
    }

    init() {
        // console.log('🏢 Inicializando gestor de empresas en convenio...');
        this.setupEventListeners();
        this.cargarEmpresas();
        this.crearModalVistaPrevia();
        this.setupCarouselControls();
    }

    setupEventListeners() {
        // Botón actualizar
        const btnActualizar = document.getElementById('btnActualizarEmpresas');
        if (btnActualizar) {
            btnActualizar.addEventListener('click', () => this.cargarEmpresas());
        }

        // Filtros de búsqueda
        const busquedaInput = document.getElementById('busquedaEmpresa');
        if (busquedaInput) {
            busquedaInput.addEventListener('input', () => {
                this.filtros.busqueda = busquedaInput.value;
                this.aplicarFiltros();
            });
        }

        // Filtro de estado (Geográfico)
        const filtroEstado = document.getElementById('filtroEstado');
        if (filtroEstado) {
            filtroEstado.addEventListener('change', () => {
                this.filtros.estado = filtroEstado.value;
                this.aplicarFiltros();
            });
        }

        // Filtro de ordenamiento
        const ordenarPor = document.getElementById('ordenarPor');
        if (ordenarPor) {
            ordenarPor.addEventListener('change', () => {
                this.filtros.ordenPor = ordenarPor.value;
                this.aplicarFiltros();
            });
        }

        // Filtro de sector
        const filtroSector = document.getElementById('filtroSector');
        if (filtroSector) {
            filtroSector.addEventListener('change', () => {
                this.filtros.sector = filtroSector.value;
                this.aplicarFiltros();
            });
        }

        // Filtro de municipio
        const filtroMunicipio = document.getElementById('filtroMunicipio');
        if (filtroMunicipio) {
            filtroMunicipio.addEventListener('input', () => {
                this.filtros.municipio = filtroMunicipio.value;
                this.aplicarFiltros();
            });
        }

        // Filtro exporta
        const filtroExporta = document.getElementById('filtroExporta');
        if (filtroExporta) {
            filtroExporta.addEventListener('change', () => {
                this.filtros.exporta = filtroExporta.value;
                this.aplicarFiltros();
            });
        }
    }

    async cargarEmpresas() {
        try {
            // console.log('📡 Cargando empresas desde API...');
            const response = await fetch(`${this.apiUrl}?action=listar`);
            const data = await response.json();

            if (data.success) {
                this.empresas = data.data.empresas;
                // console.log(`✅ ${this.empresas.length} empresas cargadas`);
                
                this.renderizarEmpresas();
                
                try {
                    this.renderCarrusel(); // Carrusel visual de socios
                } catch (c) {
                    console.error('⚠️ Error renderizando carrusel:', c);
                }
                
                this.actualizarContador();
                this.finalizarCarga(); 
            } else {
                this.finalizarCarga(); 
                throw new Error(data.message || 'Error al cargar empresas');
            }
        } catch (error) {
            console.error('❌ Error cargando empresas:', error);
            this.mostrarError('Error al cargar empresas: ' + error.message);
            this.finalizarCarga();
        }
    }

    aplicarFiltros() {
        let empresasFiltradas = [...this.empresas];

        // Filtrar por búsqueda
        if (this.filtros.busqueda) {
            const termino = this.filtros.busqueda.toLowerCase();
            empresasFiltradas = empresasFiltradas.filter(empresa => 
                empresa.nombre.toLowerCase().includes(termino) ||
                (empresa.descripcion && empresa.descripcion.toLowerCase().includes(termino)) ||
                (empresa.sector && empresa.sector.toLowerCase().includes(termino)) ||
                (empresa.municipio && empresa.municipio.toLowerCase().includes(termino))
            );
        }

        // Filtrar por estado (Geográfico)
        if (this.filtros.estado !== '') {
            empresasFiltradas = empresasFiltradas.filter(empresa => 
                empresa.entidad_federativa === this.filtros.estado
            );
        }

        // Filtrar por sector
        if (this.filtros.sector) {
            empresasFiltradas = empresasFiltradas.filter(empresa =>
                empresa.sector === this.filtros.sector
            );
        }

        // Filtrar por municipio
        if (this.filtros.municipio) {
            const mun = this.filtros.municipio.toLowerCase();
            empresasFiltradas = empresasFiltradas.filter(empresa =>
                empresa.municipio && empresa.municipio.toLowerCase().includes(mun)
            );
        }

        // Filtrar por exporta
        if (this.filtros.exporta !== '') {
            const exportaVal = this.filtros.exporta === '1';
            empresasFiltradas = empresasFiltradas.filter(empresa =>
                !!empresa.exporta === exportaVal
            );
        }

        // Ordenar
        empresasFiltradas.sort((a, b) => {
            switch (this.filtros.ordenPor) {
                case 'nombre':
                    return a.nombre.localeCompare(b.nombre);
                case 'sector':
                    return (a.sector || '').localeCompare(b.sector || '');
                case 'fecha':
                    return new Date(b.fecha_registro) - new Date(a.fecha_registro);
                default:
                    return 0;
            }
        });

        this.renderizarEmpresas(empresasFiltradas);
        this.actualizarContador(empresasFiltradas.length);
    }

    _poblarSelectSectores() {
        const select = document.getElementById('filtroSector');
        if (!select || select.dataset.poblado === '1') return;
        const sectores = [...new Set(this.empresas.map(e => e.sector).filter(Boolean))].sort();
        sectores.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            select.appendChild(opt);
        });
        select.dataset.poblado = '1';
    }

    _poblarSelectEstados() {
        const select = document.getElementById('filtroEstado');
        if (!select || select.dataset.poblado === '1') return;
        const estados = [...new Set(this.empresas.map(e => e.entidad_federativa).filter(Boolean))].sort();
        estados.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            select.appendChild(opt);
        });
        select.dataset.poblado = '1';
    }

    renderizarEmpresas(empresasList = null) {
        const container = document.getElementById('empresasContainer');
        if (!container) return;

        const empresas = empresasList || this.empresas;

        // Poblar los selects dinámicamente
        this._poblarSelectSectores();
        this._poblarSelectEstados();

        if (empresas.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-building text-6xl claut-text-muted mb-4"></i>
                    <h3 class="text-xl font-semibold claut-text-secondary mb-2">No hay empresas disponibles</h3>
                    <p class="claut-text-muted">No se encontraron empresas que coincidan con los filtros aplicados.</p>
                </div>
            `;
            return;
        }

        const empresasHTML = empresas.map(empresa => this.crearTarjetaEmpresa(empresa)).join('');
        container.innerHTML = empresasHTML;

        // Configurar eventos de click en las imágenes
        container.querySelectorAll('.empresa-logo-clickable').forEach(img => {
            img.addEventListener('click', (e) => {
                e.preventDefault();
                const empresaId = img.closest('.empresa-card').dataset.empresaId;
                this.abrirVistaPrevia(empresaId);
            });
        });
    }

    getLogoUrl(url) {
        const fallback = `./assets/img/apple-icon.png`;
        if (!url || url.trim() === '') return fallback;
        
        // Si la URL ya tiene un prefijo de servidor o es absoluta, usarla
        if (url.startsWith('http') || url.startsWith('./')) {
            return url;
        }
        
        // Caso estándar: archivo en la carpeta de uploads del servidor
        return `./uploads/empresas/${url}`;
    }

    crearTarjetaEmpresa(empresa) {
        const logoUrl = this.getLogoUrl(empresa.logo_url);
        const descripcion = empresa.descripcion_corta || empresa.descripcion || 'Información de la empresa no disponible.';
        const sector = empresa.sector || 'Sin especificar';
        const descuento = empresa.descuento_porcentaje ? `${empresa.descuento_porcentaje}% descuento` : 'Consultar beneficios';

        return `
            <div class="empresa-card glass-panel glass-panel--hover rounded-xl overflow-hidden"
                 data-empresa-id="${empresa.id}">
                <!-- Imagen de la empresa (clickeable) -->
                <div class="relative h-48 empresa-logo-bg cursor-pointer group"
                     title="Click para ver detalles">
                    <img src="${logoUrl}" 
                         alt="Logo de ${empresa.nombre}"
                         class="empresa-logo-clickable w-full h-full object-contain p-4 transition-transform duration-300 group-hover:scale-105"
                         onerror="this.src='./assets/img/apple-icon.png'">
                    
                    <!-- Overlay con icono de vista previa -->
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-300 flex items-center justify-center">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-search-plus text-white text-2xl"></i>
                        </div>
                    </div>
                    
                    <!-- Badge de descuento -->
                    ${empresa.descuento_porcentaje ? `
                        <div class="absolute top-3 right-3 bg-green-500 text-white px-2 py-1 rounded-full text-sm font-semibold">
                            -${empresa.descuento_porcentaje}%
                        </div>
                    ` : ''}
                </div>

                <!-- Información de la empresa -->
                <div class="p-6">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="text-lg font-bold claut-text-primary leading-tight">${empresa.nombre}</h3>
                        <span class="claut-badge ${empresa.estado === 'activa' ? 'claut-badge--success' : 'claut-badge--warning'}">
                            ${empresa.estado === 'activa' ? 'Activa' : 'Inactiva'}
                        </span>
                    </div>

                    <!-- Sector -->
                    <div class="mb-3">
                        <span class="claut-badge claut-badge--info">
                            <i class="fas fa-industry mr-1"></i>
                            ${sector}
                        </span>
                    </div>

                    <!-- Descripción -->
                    <p class="claut-text-secondary text-sm mb-4 line-clamp-3">${descripcion}</p>

                    <!-- Información de contacto rápida -->
                    <div class="space-y-2 mb-4">
                        ${empresa.email ? `
                            <div class="flex items-center text-sm claut-text-muted">
                                <i class="fas fa-envelope w-4 mr-2"></i>
                                <span class="truncate">${empresa.email}</span>
                            </div>
                        ` : ''}
                        ${empresa.telefono ? `
                            <div class="flex items-center text-sm claut-text-muted">
                                <i class="fas fa-phone w-4 mr-2"></i>
                                <span>${empresa.telefono}</span>
                            </div>
                        ` : ''}
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex space-x-2">
                        <button onclick="window.empresasManager.abrirVistaPrevia('${empresa.id}')"
                                class="flex-1 porsche-btn" style="padding: 0.5rem 1rem; font-size: 0.875rem; text-transform: none; letter-spacing: normal;">
                            <i class="fas fa-eye mr-1"></i>
                            Ver Detalles
                        </button>
                        ${empresa.sitio_web ? `
                            <a href="${empresa.sitio_web}" target="_blank"
                               class="porsche-btn porsche-btn--ghost" style="padding: 0.5rem 1rem; text-transform: none; letter-spacing: normal;">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    crearModalVistaPrevia() {
        // Crear modal si no existe
        if (document.getElementById('empresaModal')) return;

        const modal = document.createElement('div');
        modal.id = 'empresaModal';
        modal.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50';
        modal.innerHTML = `
            <div class="glass-modal rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto border border-white/10" 
                 style="background: rgba(20, 20, 20, 0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
                <!-- Header del modal -->
                <div class="flex items-center justify-between p-6 border-b border-white/10">
                    <h2 id="modalEmpresaNombre" class="text-2xl font-bold text-white"></h2>
                    <button id="btnCerrarModal" class="text-white/60 hover:text-white text-2xl transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Contenido del modal -->
                <div id="modalContenido" class="p-6 text-white">
                    <!-- Aquí se cargará el contenido dinámico -->
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Eventos del modal
        document.getElementById('btnCerrarModal').addEventListener('click', () => this.cerrarModal());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.cerrarModal();
        });
    }

    async abrirVistaPrevia(empresaId) {
        try {
            // console.log(`👁️ Abriendo vista previa para empresa ID: ${empresaId}`);
            
            // Buscar empresa en datos locales
            const empresa = this.empresas.find(e => e.id == empresaId);
            if (!empresa) {
                throw new Error('Empresa no encontrada');
            }

            // Actualizar contenido del modal
            document.getElementById('modalEmpresaNombre').textContent = empresa.nombre;
            document.getElementById('modalContenido').innerHTML = this.crearContenidoModal(empresa);

            // Mostrar modal
            const modal = document.getElementById('empresaModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.style.zIndex = '2147483647'; // Prioridad máxima absoluta
                document.body.style.overflow = 'hidden';
            }

        } catch (error) {
            console.error('❌ Error abriendo vista previa:', error);
            this.mostrarError('Error al cargar la vista previa');
        }
    }

    crearContenidoModal(empresa) {
        const logoUrl = this.getLogoUrl(empresa.logo_url);

        // --- Helpers ---
        const redSocial = (url, iconClass, label) => url ? `
            <a href="${url}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-sm transition-colors">
                <i class="${iconClass}"></i> ${label}
            </a>` : '';

        const hasSocial = empresa.redes_fb || empresa.redes_x || empresa.redes_linkedin || empresa.redes_instagram;

        const certBadges = empresa.certificaciones
            ? empresa.certificaciones.split(',').map(c => c.trim()).filter(Boolean).map(c =>
                `<span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-300 border border-blue-500/30">${c}</span>`
              ).join(' ')
            : '';

        return `
            <!-- Logo -->
            <div class="text-center mb-6">
                <div class="inline-block p-4 rounded-2xl bg-white/5 border border-white/10 shadow-xl backdrop-blur-sm">
                    <img src="${logoUrl}" alt="Logo de ${empresa.nombre}"
                         class="mx-auto h-28 w-auto object-contain"
                         onerror="this.src='./assets/img/apple-icon.png'">
                </div>
                <div class="flex items-center justify-center gap-3 mt-3 flex-wrap">
                    ${empresa.sector ? `<span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-300 border border-blue-500/30"><i class="fas fa-industry mr-1"></i>${empresa.sector}</span>` : ''}
                    ${empresa.municipio ? `<span class="px-3 py-1 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30"><i class="fas fa-map-marker-alt mr-1"></i>${empresa.municipio}${empresa.estado ? ', ' + empresa.estado : ''}</span>` : (empresa.estado ? `<span class="px-3 py-1 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30"><i class="fas fa-map-marker-alt mr-1"></i>${empresa.estado}</span>` : '')}
                    ${empresa.exporta ? `<span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"><i class="fas fa-globe-americas mr-1"></i>Exporta</span>` : ''}
                </div>
            </div>

            <!-- Grid principal -->
            <div class="grid md:grid-cols-2 gap-5 mb-6">
                <!-- Info general -->
                <div class="space-y-3 bg-white/5 p-4 rounded-xl border border-white/5">
                    <h3 class="text-sm font-semibold text-white/60 uppercase tracking-widest border-b border-white/10 pb-2">Información General</h3>

                    ${empresa.descripcion ? `
                    <p class="claut-text-muted text-sm leading-relaxed">${empresa.descripcion}</p>` : ''}

                    ${empresa.email ? `
                    <div class="flex items-center gap-3 text-sm">
                        <i class="fas fa-envelope text-blue-400 w-4 flex-shrink-0"></i>
                        <span class="text-white/80 break-all">${empresa.email}</span>
                    </div>` : ''}

                    ${empresa.telefono ? `
                    <div class="flex items-center gap-3 text-sm">
                        <i class="fas fa-phone text-blue-400 w-4 flex-shrink-0"></i>
                        <span class="text-white/80">${empresa.telefono}</span>
                    </div>` : ''}

                    ${empresa.sitio_web ? `
                    <div class="flex items-center gap-3 text-sm">
                        <i class="fas fa-globe text-blue-400 w-4 flex-shrink-0"></i>
                        <a href="${empresa.sitio_web}" target="_blank" rel="noopener noreferrer"
                           class="text-blue-400 hover:text-blue-300 underline break-all">${empresa.sitio_web.replace(/^https?:\/\//,'')}</a>
                    </div>` : ''}
                </div>

                <!-- Contacto -->
                <div class="space-y-3 bg-white/5 p-4 rounded-xl border border-white/5">
                    <h3 class="text-sm font-semibold text-white/60 uppercase tracking-widest border-b border-white/10 pb-2">Contacto Directo</h3>

                    ${empresa.contacto_nombre ? `
                    <div class="flex items-start gap-3 text-sm">
                        <i class="fas fa-user text-emerald-400 w-4 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-white font-medium">${empresa.contacto_nombre}</p>
                            ${empresa.contacto_cargo ? `<p class="claut-text-muted text-xs">${empresa.contacto_cargo}</p>` : ''}
                        </div>
                    </div>` : ''}

                    ${empresa.contacto_telefono ? `
                    <div class="flex items-center gap-3 text-sm">
                        <i class="fas fa-phone text-emerald-400 w-4 flex-shrink-0"></i>
                        <span class="text-white/80">${empresa.contacto_telefono}</span>
                    </div>` : ''}

                    ${empresa.contacto_email ? `
                    <div class="flex items-center gap-3 text-sm">
                        <i class="fas fa-envelope text-emerald-400 w-4 flex-shrink-0"></i>
                        <a href="mailto:${empresa.contacto_email}" class="text-emerald-400 hover:text-emerald-300 break-all">${empresa.contacto_email}</a>
                    </div>` : ''}

                    ${empresa.contacto_movil ? `
                    <div class="flex items-center gap-3 text-sm">
                        <i class="fas fa-mobile-alt text-emerald-400 w-4 flex-shrink-0"></i>
                        <span class="text-white/80">${empresa.contacto_movil}</span>
                    </div>` : ''}

                    ${(!empresa.contacto_nombre && !empresa.contacto_telefono && !empresa.contacto_email && !empresa.contacto_movil) ? `
                    <p class="claut-text-muted text-sm italic">Contacto no especificado</p>` : ''}
                </div>
            </div>

            <!-- Certificaciones -->
            ${certBadges ? `
            <div class="mb-5 bg-white/5 p-4 rounded-xl border border-white/5">
                <h3 class="text-sm font-semibold text-white/60 uppercase tracking-widest mb-3">Certificaciones</h3>
                <div class="flex flex-wrap gap-2">${certBadges}</div>
            </div>` : ''}

            <!-- Beneficios -->
            ${(empresa.beneficios || empresa.descuento_porcentaje || empresa.convenio_descripcion) ? `
            <div class="mb-5 p-4 rounded-xl border border-white/10 bg-gradient-to-br from-white/5 to-transparent">
                <h3 class="text-sm font-semibold text-white/60 uppercase tracking-widest mb-3">Beneficios Convenio</h3>

                ${empresa.descuento_porcentaje ? `
                <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/20 rounded-lg p-3 mb-3">
                    <i class="fas fa-percentage text-green-400 text-lg"></i>
                    <div>
                        <p class="font-semibold text-green-400 text-sm">Descuento Especial: ${empresa.descuento_porcentaje}%</p>
                        <p class="text-green-300 text-xs">Exclusivo comunidad Clúster</p>
                    </div>
                </div>` : ''}

                ${empresa.convenio_descripcion ? `
                <p class="claut-text-muted text-sm mb-3 font-medium border-l-2 border-emerald-400 pl-3">${empresa.convenio_descripcion}</p>` : ''}

                ${empresa.beneficios ? `
                <p class="claut-text-muted text-sm mb-3">${empresa.beneficios}</p>` : ''}
                
                ${(empresa.vigencia_inicio || empresa.vigencia_fin) ? `
                <div class="mt-3 text-xs claut-text-muted bg-black/20 p-2 rounded inline-block">
                    <i class="fas fa-calendar-alt mr-2"></i>Vigencia: 
                    <span class="text-white ml-1">${empresa.vigencia_inicio ? empresa.vigencia_inicio : 'No especificada'}</span> a 
                    <span class="text-white ml-1">${empresa.vigencia_fin ? empresa.vigencia_fin : 'No especificada'}</span>
                </div>` : ''}
            </div>` : ''}

            <!-- Condiciones -->
            ${empresa.condiciones ? `
            <div class="mb-5">
                <div class="bg-amber-500/5 border border-amber-500/10 rounded-lg p-3">
                    <p class="text-amber-400 text-xs italic"><i class="fas fa-info-circle mr-2"></i>${empresa.condiciones}</p>
                </div>
            </div>` : ''}

            <!-- Redes Sociales -->
            ${hasSocial ? `
            <div class="mb-5 bg-white/5 p-4 rounded-xl border border-white/5">
                <h3 class="text-sm font-semibold text-white/60 uppercase tracking-widest mb-3">Redes Sociales</h3>
                <div class="flex flex-wrap gap-2">
                    ${redSocial(empresa.redes_fb, 'fab fa-facebook', 'Facebook')}
                    ${redSocial(empresa.redes_x, 'fab fa-x-twitter', 'X')}
                    ${redSocial(empresa.redes_linkedin, 'fab fa-linkedin', 'LinkedIn')}
                    ${redSocial(empresa.redes_instagram, 'fab fa-instagram', 'Instagram')}
                </div>
            </div>` : ''}

            <!-- Acciones -->
            <div class="flex flex-wrap gap-3 pt-4 border-t border-white/10">
                ${empresa.sitio_web ? `
                <a href="${empresa.sitio_web}" target="_blank" rel="noopener noreferrer"
                   class="flex-1 min-w-[140px] text-white px-5 py-2.5 rounded-lg font-medium text-center text-sm transition-colors"
                   style="background: var(--surface-info); border: 1px solid rgba(59,130,246,.3);"
                   onmouseover="this.style.filter='brightness(1.15)'" onmouseout="this.style.filter='none'">
                    <i class="fas fa-external-link-alt mr-2"></i>Sitio Web
                </a>` : ''}

                ${empresa.contacto_email ? `
                <a href="mailto:${empresa.contacto_email}"
                   class="flex-1 min-w-[140px] bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg font-medium text-center text-sm transition-colors">
                    <i class="fas fa-envelope mr-2"></i>Contactar
                </a>` : ''}
            </div>
        `;
    }

    cerrarModal() {
        const modal = document.getElementById('empresaModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }

    actualizarContador(cantidad = null) {
        const contador = document.getElementById('contadorEmpresas');
        if (contador) {
            const total = cantidad !== null ? cantidad : this.empresas.length;
            contador.textContent = `${total} empresa${total !== 1 ? 's' : ''} disponible${total !== 1 ? 's' : ''}`;
        }
    }

    finalizarCarga() {
        // Ocultar pantalla de carga manual si existe
        const loader = document.getElementById('loadingScreen');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => {
                loader.style.display = 'none';
                loader.remove();
            }, 600);
        }
    }

    mostrarError(mensaje) {
        console.error('❌', mensaje);
        const container = document.getElementById('empresasContainer');
        const contador = document.getElementById('contadorEmpresas');
        
        if (contador) {
            contador.textContent = 'Fallo en la carga';
            contador.classList.add('text-red-500');
        }

        if (container) {
            container.innerHTML = `
                <div class="col-span-full text-center py-12 bg-red-500/10 rounded-2xl border border-red-500/20">
                    <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
                    <h3 class="text-xl font-semibold text-red-400 mb-2">Error de Ejecución</h3>
                    <p class="claut-text-muted mb-4">${mensaje}</p>
                    <button onclick="location.reload()" class="porsche-btn" style="text-transform: none; letter-spacing: normal;">
                        Reintentar cargar
                    </button>
                </div>
            `;
        }
    }

    renderCarrusel() {
        const carouselTrack = document.getElementById('carouselTrack');
        if (!carouselTrack) return;

        // Filtrar empresas que tengan logo para el carrusel visual
        const empresasConLogo = this.empresas.filter(e => e.logo_url && e.logo_url !== '');
        
        if (empresasConLogo.length === 0) {
            carouselTrack.innerHTML = `
                <div class="flex items-center justify-center h-full w-full">
                    <p class="claut-text-muted italic">Próximamente más socios disponibles...</p>
                </div>
            `;
            return;
        }

        // Configuración de tiempos y variables CSS para la animación infinita
        const total = empresasConLogo.length;
        const time = Math.max(20, total * 3); // 3 segundos por logo, mínimo 20s

        carouselTrack.style.setProperty('--total', total);
        carouselTrack.style.setProperty('--time', `${time}s`);

        carouselTrack.innerHTML = empresasConLogo.map((empresa, index) => {
            const logoUrl = this.getLogoUrl(empresa.logo_url);

            return `
                <div class="carousel-item" 
                     style="--i: ${index + 1}" 
                     data-empresa-id="${empresa.id}"
                     data-empresa-name="${empresa.nombre}">
                    <img src="${logoUrl}" alt="${empresa.nombre}" 
                         onerror="this.src='./assets/img/apple-icon.png'">
                </div>
            `;
        }).join('');

        // Re-vincular eventos para los nuevos items si es necesario
        // (Aunque el onclick directo en el botón ya funciona)
    }

    setupCarouselControls() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const carouselTrack = document.getElementById('carouselTrack');

        if (!prevBtn || !nextBtn || !carouselTrack) return;

        let isManualMode = false;
        let manualOffset = 0;

        const setManualMode = (state) => {
            isManualMode = state;
            const items = carouselTrack.querySelectorAll('.carousel-item');
            items.forEach(item => {
                if (state) {
                    item.classList.add('manual-control');
                } else {
                    item.classList.remove('manual-control');
                }
            });
        };

        nextBtn.addEventListener('click', () => {
            // console.log('➡️ Siguiente socio (Manual)');
            nextBtn.classList.add('clicked');
            setTimeout(() => nextBtn.classList.remove('clicked'), 300);
            
            // Lógica simple de rotación para modo manual si se desea implementar más adelante
            // Por ahora solo pausamos/reanudamos o damos feedback visual
        });

        prevBtn.addEventListener('click', () => {
            // console.log('⬅️ Anterior socio (Manual)');
            prevBtn.classList.add('clicked');
            setTimeout(() => prevBtn.classList.remove('clicked'), 300);
        });

        // Pausar en hover
        carouselTrack.addEventListener('mouseenter', () => {
            const items = carouselTrack.querySelectorAll('.carousel-item');
            items.forEach(item => item.classList.add('paused'));
        });

        carouselTrack.addEventListener('mouseleave', () => {
            const items = carouselTrack.querySelectorAll('.carousel-item');
            items.forEach(item => item.classList.remove('paused'));
        });

        // 🎯 Event Delegation para clics en el carrusel (Senior Approach)
        carouselTrack.addEventListener('click', (e) => {
            const target = e.target;
            
            // Buscar si el clic fue en el botón de detalles o en la tarjeta
            const btn = target.closest('[data-accion="ver-detalles"]');
            const card = target.closest('.carousel-item');
            
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const id = btn.dataset.id;
                // console.log(`🎯 Clic en botón detectado para ID: ${id}`);
                this.abrirVistaPrevia(id);
            } else if (card) {
                // Si hace clic en la tarjeta (logo), también abrimos vista previa
                e.preventDefault();
                const id = card.dataset.empresaId;
                // console.log(`🎯 Clic en tarjeta detectado para ID: ${id}`);
                this.abrirVistaPrevia(id);
            }
        });
    }

    exportarDatos() {
        // Funcionalidad para exportar datos de empresas
        const datosExportar = this.empresas.map(empresa => ({
            nombre: empresa.nombre,
            sector: empresa.sector,
            email: empresa.email,
            telefono: empresa.telefono,
            estado: empresa.estado,
            descuento: empresa.descuento_porcentaje,
            fecha_convenio: empresa.fecha_convenio
        }));

        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(datosExportar, null, 2));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", "empresas-convenio.json");
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.empresasManager = new EmpresasConvenioManager();
});