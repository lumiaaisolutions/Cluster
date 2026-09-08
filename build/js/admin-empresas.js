/**
 * Administrador Elite de Empresas - demo_empresas.html
 * Gestión integral con Aprobación Granular y Diseño Glassmorphism
 */

class AdminEmpresasManager {
    constructor() {
        this.empresas = [];
        this.usuarios = [];
        this.solicitudes = [];
        this.empresaEditando = null;
        this.enviandoFormulario = false;

        const isProd = window.location.hostname.includes('clautmetropolitano.mx') || 
                       window.location.hostname.includes('clustermetropolitano.mx');
        
        this.apiUrl = './api/empresas-simple.php';
        this.solicitudesApiUrl = './api/solicitudes_empresa.php';
        this.apiUsuariosUrl = './api/admin/users.php';

        this.init();
    }

    async init() {
        console.log('🚀 Inicializando AdminEmpresasManager Elite...');
        this.setupEventListeners();
        await Promise.all([
            this.cargarEmpresas(),
            this.cargarUsuarios(),
            this.cargarSolicitudes()
        ]);

        // Refresco automático de solicitudes cada 45s
        setInterval(() => this.cargarSolicitudes(true), 45000);
    }

    setupEventListeners() {
        // Botones de acción principal
        const btnAgregar = document.getElementById('btnAgregarEmpresa');
        if (btnAgregar) btnAgregar.addEventListener('click', () => this.abrirModalCrear());

        const btnCerrar = document.getElementById('btnCerrarModal');
        if (btnCerrar) btnCerrar.addEventListener('click', () => this.cerrarModal());

        const btnCancelar = document.getElementById('btnCancelar');
        if (btnCancelar) btnCancelar.addEventListener('click', () => this.cerrarModal());

        const form = document.getElementById('formEmpresa');
        if (form) {
            form.addEventListener('submit', (e) => this.guardarEmpresa(e));
            console.log('✅ Evento submit vinculado al formulario');
        }

        // Búsqueda y Filtros
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                this.filtrarEmpresas(term);
            });
        }

        ['filterCategoria', 'filtroEstado', 'filterDestacado'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', () => this.aplicarFiltros());
        });

        // Exportar
        const btnExportar = document.getElementById('btnExportar');
        if (btnExportar) btnExportar.addEventListener('click', () => this.exportarEmpresas());

        // Logo Upload Preview
        const logoUrlInput = document.getElementById('logo_url');
        if (logoUrlInput) {
            logoUrlInput.addEventListener('input', (e) => this.actualizarPreviewLogo(e.target.value));
        }

        const logoFileInput = document.getElementById('logo_file');
        if (logoFileInput) {
            logoFileInput.addEventListener('change', (e) => this.manejarArchivoLogo(e));
        }
    }

    async cargarEmpresas() {
        const loading = document.getElementById('loadingState');
        const list = document.getElementById('empresasTableBody');
        
        if (loading) loading.classList.remove('hidden');
        if (list) list.classList.add('opacity-50');

        try {
            const res = await fetch(`${this.apiUrl}?action=listar&t=${Date.now()}`);
            const data = await res.json();

            if (data.success) {
                this.empresas = data.data.empresas || [];
                this.renderizarTablaAdmin();
                this.actualizarEstadisticas();
            }
        } catch (error) {
            console.error('Error cargando empresas:', error);
            this.mostrarNotificacion('Error al conectar con la base de datos', 'error');
        } finally {
            if (loading) loading.classList.add('hidden');
            if (list) list.classList.remove('opacity-50');
        }
    }

    async cargarUsuarios() {
        try {
            const res = await fetch(this.apiUsuariosUrl);
            const data = await res.json();
            if (data.success) {
                this.usuarios = data.data || [];
                this.poblarSelectorUsuarios();
            }
        } catch (e) { console.warn('Usuarios no disponibles para asignación'); }
    }

    poblarSelectorUsuarios() {
        const select = document.getElementById('admin_usuario_id');
        if (!select) return;

        // Limpiar opciones anteriores pero mantener la primera ("Seleccionar socio...")
        select.innerHTML = '<option value="">Seleccionar socio responsable...</option>';

        // Ordenar usuarios alfabéticamente por nombre
        const usuariosOrdenados = [...this.usuarios].sort((a, b) => 
            (a.nombre || '').localeCompare(b.nombre || '')
        );

        usuariosOrdenados.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            const empresaLabel = user.nombre_empresa ? ` [${user.nombre_empresa}]` : '';
            option.textContent = `${user.nombre} ${user.apellidos || ''} (${user.email})${empresaLabel}`;
            select.appendChild(option);
        });

        console.log(`✅ Selector de usuarios poblado con ${this.usuarios.length} registros`);
    }

    renderizarTablaAdmin(lista = null) {
        const grid = document.getElementById('empresasTableBody');
        const empty = document.getElementById('emptyState');
        if (!grid) return;

        const items = lista || this.empresas;

        if (items.length === 0) {
            grid.innerHTML = '';
            if (empty) empty.classList.remove('hidden');
            return;
        }

        if (empty) empty.classList.add('hidden');

        // Actualizar contador en la barra de controles
        const totalEnTabla = document.getElementById('totalEnTabla');
        if (totalEnTabla) totalEnTabla.textContent = items.length;

        grid.innerHTML = items.map(emp => {
            const logo = this.sanitizarLogo(emp.logo_url, emp.nombre);
            // Badge semántico por estado: verde activa, ámbar pendiente, neutro inactiva
            const estadoNorm = (emp.estado || 'inactiva').toLowerCase();
            let badgeClass, estadoLabel;
            if (estadoNorm === 'activa') {
                badgeClass = 'claut-badge--success';
                estadoLabel = 'Activa';
            } else if (estadoNorm === 'pendiente') {
                badgeClass = 'claut-badge--warning';
                estadoLabel = 'Pendiente';
            } else {
                badgeClass = 'claut-badge--neutral';
                estadoLabel = 'Inactiva';
            }

            const estrella = emp.destacado ? '<i class="fas fa-star" style="color:#fbbf24; font-size:12px; margin-left:6px;" title="Destacada"></i>' : '';
            const descuentoChip = parseFloat(emp.descuento_porcentaje) > 0
                ? `<span class="elite-chip"><i class="fas fa-tag"></i>${emp.descuento_porcentaje}% descuento</span>`
                : '';
            const contactoChips = [
                emp.email ? `<span class="elite-chip" title="${emp.email}"><i class="fas fa-envelope"></i><span style="overflow:hidden; text-overflow:ellipsis;">${emp.email}</span></span>` : '',
                emp.telefono ? `<span class="elite-chip"><i class="fas fa-phone"></i>${emp.telefono}</span>` : ''
            ].join('');
            const usuarioRow = emp.admin_nombre
                ? `<div class="row"><i class="fas fa-user-shield"></i><span>${emp.admin_nombre}</span></div>`
                : '<div class="row"><i class="fas fa-user-slash"></i><span style="font-style:italic; color:#61687a;">Sin asignar</span></div>';

            return `
                <article class="elite-card">
                    <div class="elite-card-body">
                        <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                            <img src="${logo}" class="elite-card-logo" alt="Logo de ${emp.nombre}" onerror="this.src='${this.generarLogoDefault(emp.nombre)}'">
                            <div style="min-width:0; flex:1;">
                                <h4>${emp.nombre}${estrella}</h4>
                                <div style="font-size:10.5px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:#8b93a1; margin-top:3px;">${emp.sector || 'General'}</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span class="claut-badge ${badgeClass}">${estadoLabel}</span>
                            ${descuentoChip}
                        </div>
                        <div style="display:flex; flex-direction:column; gap:5px; align-items:flex-start; min-width:0;">
                            ${contactoChips}
                        </div>
                        <div class="elite-card-meta">
                            ${usuarioRow}
                        </div>
                        <div class="elite-card-foot">
                            <div class="elite-actions">
                                <button type="button" onclick="window.adminEmpresas.mostrarDetallesEmpresa(${emp.id})" class="elite-btn elite-btn--view" title="Ver Detalles"><i class="fas fa-eye"></i> Ver</button>
                                <button type="button" onclick="window.adminEmpresas.editarEmpresa(${emp.id})" class="elite-btn elite-btn--edit" title="Editar"><i class="fas fa-edit"></i> Editar</button>
                                <button type="button" onclick="window.adminEmpresas.eliminarEmpresa(${emp.id})" class="elite-btn elite-btn--del" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </article>
            `;
        }).join('');
    }

    sanitizarLogo(url, nombre) {
        if (!url) return this.generarLogoDefault(nombre);
        // Si es ruta relativa sin el punto, añadirlo o asegurar uploads/
        if (url.startsWith('uploads/')) return `./${url}`;
        if (url.startsWith('./uploads/')) return url;
        return url;
    }

    generarLogoDefault(nombre) {
        // Usar UI Avatars como fallback más premium que placeholder de imágenes externas
        return `https://ui-avatars.com/api/?name=${encodeURIComponent(nombre)}&background=1a1a1a&color=C7252B&bold=true&length=2&size=128&font-size=0.4`;
    }

    // ============================================
    // SOLICITUDES Y NOTIFICACIONES
    // ============================================

    async cargarSolicitudes(silencioso = false) {
        try {
            const url = `${this.solicitudesApiUrl}?estado=pendiente&t=${Date.now()}`;
            console.log('📡 Fetching solicitudes desde:', url);

            const res = await fetch(url, {
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' }
            });

            console.log('📡 Status:', res.status, res.statusText);
            const rawText = await res.text();
            console.log('📡 Respuesta cruda:', rawText.substring(0, 500));

            let jsonData;
            try { jsonData = JSON.parse(rawText); }
            catch(e) { console.error('❌ JSON inválido:', e); return; }

            if (jsonData.success) {
                // ApiResponse::success() pone el payload dentro de "data"
                this.solicitudes = (jsonData.data && jsonData.data.solicitudes) 
                                 ? jsonData.data.solicitudes 
                                 : (jsonData.solicitudes || []);
                this.renderizarSolicitudes();
                console.log(`✅ ${this.solicitudes.length} solicitudes pendientes`);
            } else {
                console.warn('⚠️ API error:', jsonData.error || jsonData.message, '| user_rol implícito | user_id:', jsonData.user_id);
                // Mostrar estado de error en el panel
                const container = document.getElementById('solicitudesContainer');
                if (container) {
                    container.innerHTML = `<div class="col-span-2 text-center py-8 text-red-400">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                        <p class="text-sm font-medium">Error al cargar: ${data.error || data.message || 'Sin acceso'}</p>
                        <p class="text-xs text-gray-500 mt-1">Revisa la consola para más detalles</p>
                    </div>`;
                }
            }
        } catch (e) {
            if (!silencioso) console.warn('❌ Carga de solicitudes falló:', e.message);
        }
    }


    renderizarSolicitudes() {
        const section = document.getElementById('solicitudesSection');
        const container = document.getElementById('solicitudesContainer');
        const badge = document.getElementById('solicitudesBadge');
        if (!container) return;

        // Actualizar badge
        if (badge) badge.textContent = this.solicitudes.length;

        if (this.solicitudes.length === 0) {
            // Estado vacío — panel siempre visible pero con mensaje informativo
            container.innerHTML = `
                <div class="col-span-2 text-center py-10">
                    <div class="w-16 h-16 bg-green-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                    </div>
                    <p class="text-gray-300 font-semibold">Sin solicitudes pendientes</p>
                    <p class="text-gray-500 text-sm mt-1">Todas las solicitudes han sido procesadas</p>
                </div>
            `;
            if (badge) badge.classList.replace('bg-yellow-500', 'bg-green-500');
            return;
        }

        if (badge) badge.classList.replace('bg-green-500', 'bg-yellow-500');


        container.innerHTML = this.solicitudes.map(sol => {
            const icon = sol.tipo_solicitud === 'crear' ? 'fa-plus-circle' : 'fa-edit';
            const accent = sol.tipo_solicitud === 'crear' ? 'blue' : 'yellow';

            return `
                <div class="glass-card p-5 border-l-4 border-l-${accent}-500/50 hover:bg-white/[0.04] transition-all">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 rounded-xl bg-${accent}-500/10 flex items-center justify-center text-${accent}-500">
                                <i class="fas ${icon} text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-white tracking-tight">${sol.tipo_solicitud === 'crear' ? 'Nueva Empresa' : 'Actualización de Datos'}</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Por: <span class="text-gray-300 font-medium">${sol.usuario_nombre || 'Socio'}</span> · ${this.formatearFecha(sol.fecha_solicitud)}</p>
                                ${sol.empresa_nombre_existente ? `<p class="text-sm font-bold text-red-500 mt-2">${sol.empresa_nombre_existente}</p>` : ''}
                            </div>
                        </div>
                        <button onclick="window.adminEmpresas.abrirRevision(${sol.id})" 
                                class="px-5 py-2 bg-white/5 border border-white/10 rounded-xl text-white hover:bg-white/10 transition-all font-bold text-xs uppercase tracking-widest">
                            Revisar
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ============================================
    // APROBACIÓN GRANULAR (REVISIÓN)
    // ============================================

    async abrirRevision(id) {
        const sol = this.solicitudes.find(s => s.id == id);
        if (!sol) return;

        const modal = document.getElementById('modalReview');
        const container = document.getElementById('reviewContainer');
        if (!modal || !container) return;

        container.innerHTML = '<div class="flex items-center justify-center py-20"><i class="fas fa-spinner fa-spin text-3xl text-red-500"></i></div>';
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        try {
            // Obtener datos actuales de la empresa si es actualización
            let actual = {};
            if (sol.tipo_solicitud === 'actualizar' && sol.empresa_id) {
                const res = await fetch(`${this.apiUrl}?action=obtener&id=${sol.empresa_id}`);
                const data = await res.json();
                if (data.success) actual = data.data;
            }

            const camposNuevos = typeof sol.datos_empresa === 'string' ? JSON.parse(sol.datos_empresa) : sol.datos_empresa;
            
            // Render Comparación
            container.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="glass-card p-4 bg-white/5 border-white/10">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Empresa Destino</label>
                        <div class="text-lg font-bold text-white">${sol.empresa_nombre_existente || 'NUEVA EMPRESA'}</div>
                    </div>
                    <div class="glass-card p-4 bg-red-600/5 border-red-600/10">
                        <label class="block text-[10px] font-bold text-red-500 uppercase tracking-widest mb-1">Modificado Por</label>
                        <div class="text-lg font-bold text-gray-300">${sol.usuario_nombre}</div>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="grid grid-cols-12 gap-4 px-4 py-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                        <div class="col-span-1">¿Approve?</div>
                        <div class="col-span-3">Campo</div>
                        <div class="col-span-4">Valor Actual</div>
                        <div class="col-span-4">Valor Propuesto</div>
                    </div>
                    ${this.generarFilasDiff(camposNuevos, actual)}
                </div>
            `;

            // Configurar botones de acción en revisión
            document.getElementById('btnAprobarSeleccion').onclick = () => this.procesarRevision(sol.id, 'aprobar');
            document.getElementById('btnRechazarTodo').onclick = () => this.procesarRevision(sol.id, 'rechazar');

        } catch (e) {
            container.innerHTML = `<div class="p-10 text-center text-red-500">Error cargando detalles: ${e.message}</div>`;
        }
    }

    generarFilasDiff(nuevos, actuales) {
        const campoLabels = {
            'nombre_empresa': '🏢 Nombre de la Empresa',
            'nombre': '🏢 Nombre Comercial',
            'sector': '🔧 Sector Industrial',
            'sitio_web': '🌐 Sitio Web',
            'descripcion': '📝 Descripción',
            'direccion': '📍 Dirección',
            'entidad_federativa': '🗺️ Estado/Entidad',
            'municipio': '🏘️ Municipio',
            'exporta': '🚢 ¿Exporta?',
            'certificaciones': '📜 Certificaciones',
            'email': '📧 Email General',
            'telefono': '📞 Teléfono General',
            'contacto_persona': '👤 Persona Contacto',
            'contacto_nombre': '👤 Nombre de Contacto',
            'contacto_cargo': '💼 Cargo',
            'contacto_email': '✉️ Email Contacto',
            'contacto_movil': '📱 WhatsApp/Móvil',
            'contacto_telefono': '📞 Teléfono Directo',
            'beneficios': '🎁 Beneficios Socios',
            'convenio_descripcion': '🎁 Descripción Convenio',
            'descuento_porcentaje': '🏷️ % Descuento',
            'codigo_cupon': '🎟️ Código Cupón',
            'fecha_convenio': '📅 Inicio Vigencia',
            'redes_fb': '🔵 Facebook',
            'redes_linkedin': '🔵 LinkedIn',
            'redes_instagram': '🟣 Instagram',
            'logo_url': '🖼️ URL Logo'
        };

        const skip = ['updated_at', 'created_at', 'id', 'user_id', 'usuario_id'];
        return Object.entries(nuevos)
            .filter(([k]) => !skip.includes(k) && nuevos[k] !== null && nuevos[k] !== '')
            .map(([k, v]) => {
                const label = campoLabels[k] || k.replace(/_/g, ' ').toUpperCase();
                const actualVal = actuales[k] || '<span class="text-gray-600 italic">No definido</span>';
                const hasChanged = String(actualVal) !== String(v);

                return `
                    <div class="grid grid-cols-12 gap-4 px-4 py-3 rounded-xl hover:bg-white/[0.03] transition-all items-center ${hasChanged ? 'border border-red-500/10' : ''}">
                        <div class="col-span-1 flex justify-center">
                            <input type="checkbox" name="field-approve" value="${k}" checked 
                                   class="w-5 h-5 accent-red-600 cursor-pointer">
                        </div>
                        <div class="col-span-3">
                            <span class="text-xs font-bold text-gray-300 shadow-sm">${label}</span>
                        </div>
                        <div class="col-span-4 text-xs text-gray-500 truncate bg-black/10 p-1 rounded">
                            ${actualVal}
                        </div>
                        <div class="col-span-4 text-xs font-medium ${hasChanged ? 'text-green-400' : 'text-gray-400'} p-1">
                            ${v}
                        </div>
                    </div>
                `;
            }).join('');
    }

    async procesarRevision(id, accion) {
        if (!confirm(`¿Estás seguro de ${accion === 'aprobar' ? 'aplicar estos cambios' : 'rechazar la solicitud'}?`)) return;

        const approvedFields = Array.from(document.querySelectorAll('input[name="field-approve"]:checked')).map(cb => cb.value);
        
        try {
            const res = await fetch(this.solicitudesApiUrl, {
                method: 'PUT',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: id,
                    accion: accion,
                    approved_fields: approvedFields,
                    notas: accion === 'rechazar' ? prompt('Motivo del rechazo:') : 'Aprobado vía Panel Admin Elite'
                })
            });

            const data = await res.json();
            if (data.success) {
                this.mostrarNotificacion(data.message || 'Procesado correctamente', 'success');
                document.getElementById('modalReview').classList.add('hidden');
                this.cargarSolicitudes();
                this.cargarEmpresas();
            } else { throw new Error(data.error); }
        } catch (e) { this.mostrarNotificacion('Error: ' + e.message, 'error'); }
    }

    // ============================================
    // MODAL Y FORMULARIO
    // ============================================

    abrirModalCrear() {
        this.empresaEditando = null;
        const form = document.getElementById('formEmpresa');
        if (form) form.reset();
        document.getElementById('empresa_id_hidden').value = '';
        document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-plus-circle text-red-600"></i> Nueva Empresa';
        this.ocultarPreviewLogo();
        this.mostrarModal();
    }

    editarEmpresa(id) {
        const emp = this.empresas.find(e => e.id == id);
        if (!emp) return;
        this.empresaEditando = emp;
        this.llenarFormulario(emp);
        document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit text-red-600"></i> Editar Empresa';
        this.mostrarModal();
    }

    mostrarDetallesEmpresa(id) {
        const emp = this.empresas.find(e => e.id == id);
        if (!emp) return;

        const modal = document.getElementById('detallesEmpresaModal');
        if (!modal) return;

        // Poblar Header
        document.getElementById('detallesNombreEmpresa').textContent = emp.nombre;
        document.getElementById('detallesLogo').src = this.sanitizarLogo(emp.logo_url, emp.nombre);
        
        // Badges de estado
        const badgeContainer = document.getElementById('detallesBadges');
        const estadoNorm = (emp.estado || 'inactiva').toLowerCase();
        let statusHtml = '';
        if (estadoNorm === 'activa') {
            statusHtml = '<span class="px-2 py-0.5 rounded-full text-[10px] bg-green-500/20 text-green-400 border border-green-500/20 uppercase font-black">Activa</span>';
        } else if (estadoNorm === 'pendiente') {
            statusHtml = '<span class="px-2 py-0.5 rounded-full text-[10px] bg-yellow-500/20 text-yellow-400 border border-yellow-500/20 uppercase font-black">Pendiente de Revisión</span>';
        } else {
            statusHtml = '<span class="px-2 py-0.5 rounded-full text-[10px] bg-red-500/20 text-red-400 border border-red-500/20 uppercase font-black">Inactiva</span>';
        }
        if (emp.destacado == 1) statusHtml += '<span class="px-2 py-0.5 rounded-full text-[10px] bg-yellow-500/20 text-yellow-300 border border-yellow-500/20 uppercase font-black"><i class="fas fa-star mr-1"></i>Destacada</span>';
        badgeContainer.innerHTML = statusHtml;

        // Info General
        document.getElementById('detallesDescripcion').textContent = emp.descripcion || 'Sin descripción corporativa.';
        document.getElementById('detallesSector').textContent = emp.sector || 'N/A';
        document.getElementById('detallesCategoria').textContent = emp.categoria || 'N/A';
        
        // Contacto
        document.getElementById('detallesContactoNombre').textContent = emp.contacto_nombre || emp.contacto_persona || 'No especificado';
        document.getElementById('detallesEmail').textContent = emp.email || '—';
        document.getElementById('detallesTelefono').textContent = emp.telefono || '—';
        
        const sw = document.getElementById('detallesSitioWeb');
        if (emp.sitio_web) {
            sw.textContent = emp.sitio_web;
            sw.href = emp.sitio_web.startsWith('http') ? emp.sitio_web : `https://${emp.sitio_web}`;
            sw.classList.remove('hidden');
        } else {
            sw.textContent = 'Sin sitio web';
            sw.classList.add('hidden');
        }

        // Convenio
        document.getElementById('detallesDescuento').textContent = emp.descuento_porcentaje ? `${emp.descuento_porcentaje}%` : '0%';
        document.getElementById('detallesBeneficios').innerHTML = emp.beneficios || emp.convenio_descripcion || '<p class="text-gray-500 italic">No se han definido beneficios o convenios específicos.</p>';
        document.getElementById('detallesVigenciaInicio').textContent = this.formatearFecha(emp.vigencia_inicio || emp.fecha_convenio);
        document.getElementById('detallesVigenciaFin').textContent = this.formatearFecha(emp.vigencia_fin);

        // Ubicación
        document.getElementById('detallesDireccion').textContent = emp.direccion || 'Dirección no registrada.';

        // Mostrar Modal
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    llenarFormulario(emp) {
        const form = document.getElementById('formEmpresa');
        if (!form) return;
        
        document.getElementById('empresa_id_hidden').value = emp.id;
        
        // Mapeo dinámico de campos
        Object.keys(emp).forEach(key => {
            const input = document.getElementById(key);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = (emp[key] == 1 || emp[key] === true);
                } else if (input.tagName === 'SELECT' && (key === 'exporta' || key === 'autoriza_directorio')) {
                    // Mapeo especial para booleanos en cajas select
                    input.value = (emp[key] == 1 || emp[key] === true) ? "1" : "0";
                } else {
                    input.value = emp[key] || '';
                }
            }
        });

        if (emp.logo_url) this.actualizarPreviewLogo(emp.logo_url);
        
        // Asegurar que el select de usuario se actualice específicamente si no se mapeó automáticamente
        const userSelect = document.getElementById('admin_usuario_id');
        if (userSelect && emp.admin_usuario_id) {
            userSelect.value = emp.admin_usuario_id;
        }
    }

    async guardarEmpresa(e) {
        e.preventDefault();
        if (this.enviandoFormulario) return;

        const btn = document.getElementById('btnGuardar');
        const originalText = btn.innerHTML;
        this.enviandoFormulario = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;

        try {
            const formData = new FormData(document.getElementById('formEmpresa'));
            const action = this.empresaEditando ? 'actualizar' : 'crear';
            formData.append('action', action);
            if(this.empresaEditando) formData.append('id', this.empresaEditando.id);
            
            // Handle logo file if any
            const logoFile = document.getElementById('logo_file').files[0];
            if (logoFile) {
                const uploadRes = await this.subirImagen(logoFile);
                formData.set('logo_url', uploadRes);
            }

            const res = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                this.mostrarNotificacion('¡Excelentes noticias! La empresa se guardó correctamente.', 'success');
                this.cerrarModal();
                this.cargarEmpresas();
            } else { throw new Error(data.message); }

        } catch (e) {
            this.mostrarNotificacion('Vaya, algo salió mal: ' + e.message, 'error');
        } finally {
            this.enviandoFormulario = false;
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    async eliminarEmpresa(id) {
        if (!confirm('¿Estás seguro de eliminar esta empresa? Esta acción es irreversible.')) return;
        try {
            const fd = new FormData();
            fd.append('action', 'eliminar');
            fd.append('id', id);
            const res = await fetch(this.apiUrl, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                this.mostrarNotificacion('Empresa eliminada con éxito', 'success');
                this.cargarEmpresas();
            }
        } catch (e) { this.mostrarNotificacion('Error eliminando empresa', 'error'); }
    }

    // ============================================
    // UTILS
    // ============================================

    mostrarModal() {
        const modal = document.getElementById('modalEmpresa');
        modal.classList.add('open');
        const wizardEl = modal.querySelector('.claut-wizard');
        if (wizardEl && wizardEl.__clautWizardReset) wizardEl.__clautWizardReset();
        document.body.style.overflow = 'hidden';
    }

    cerrarModal() {
        const modal = document.getElementById('modalEmpresa');
        modal.classList.remove('open');
        document.body.style.overflow = 'auto';
        this.empresaEditando = null;
    }

    actualizarEstadisticas() {
        const stats = {
            total: this.empresas.length,
            activas: this.empresas.filter(e => (e.estado || '').toLowerCase() === 'activa').length,
            destacadas: this.empresas.filter(e => e.destacado == 1).length,
            descuentos: this.empresas.filter(e => parseFloat(e.descuento_porcentaje) > 0).length
        };

        // Actualizar tarjetas de estadísticas del header
        Object.entries(stats).forEach(([k, v]) => {
            const el = document.getElementById(`${k}Empresas`);
            if (el) {
                const currentVal = parseInt(el.textContent) || 0;
                this.animarNumero(el, currentVal, v);
            }
        });

        // Actualizar también totalEmpresas y badge en barra de controles
        const totalEl = document.getElementById('totalEmpresas');
        if (totalEl) {
            const currentVal = parseInt(totalEl.textContent) || 0;
            this.animarNumero(totalEl, currentVal, stats.total);
        }
        const totalEnTabla = document.getElementById('totalEnTabla');
        if (totalEnTabla) totalEnTabla.textContent = stats.total;
    }

    animarNumero(el, inicio, fin) {
        let actual = inicio;
        const duracion = 1000;
        const pasos = 30;
        const incremento = (fin - inicio) / pasos;
        const intervalo = duracion / pasos;

        const timer = setInterval(() => {
            actual += incremento;
            if ((incremento > 0 && actual >= fin) || (incremento < 0 && actual <= fin)) {
                el.textContent = fin;
                clearInterval(timer);
            } else {
                el.textContent = Math.round(actual);
            }
        }, intervalo);
    }

    mostrarNotificacion(msg, tipo = 'info') {
        const container = document.getElementById('notificacionesContainer') || this.crearContenedorNotif();
        const div = document.createElement('div');
        const icon = tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const color = tipo === 'success' ? 'bg-green-500' : 'bg-red-500';

        div.className = `flex items-center gap-3 p-4 rounded-2xl text-white shadow-2xl transition-all translate-x-10 opacity-0 ${color}`;
        div.innerHTML = `<i class="fas ${icon} text-lg"></i><span class="text-sm font-bold">${msg}</span>`;
        
        container.appendChild(div);
        setTimeout(() => {
            div.classList.remove('translate-x-10', 'opacity-0');
        }, 10);

        setTimeout(() => {
            div.classList.add('translate-x-10', 'opacity-0');
            setTimeout(() => div.remove(), 500);
        }, 5000);
    }

    crearContenedorNotif() {
        const c = document.createElement('div');
        c.id = 'notificacionesContainer';
        c.className = 'fixed top-6 right-6 z-[2000] flex flex-col gap-3 max-w-sm pointer-events-none';
        document.body.appendChild(c);
        return c;
    }

    formatearFecha(f) {
        if (!f) return '—';
        return new Date(f).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    async subirImagen(file) {
        const fd = new FormData();
        fd.append('image', file);
        const res = await fetch('./api/upload-image.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) return data.data.url;
        throw new Error(data.message || 'Error subiendo archivo');
    }

    manejarArchivoLogo(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => this.actualizarPreviewLogo(ev.target.result);
        reader.readAsDataURL(file);
    }

    actualizarPreviewLogo(src) {
        const p = document.getElementById('logoPreview');
        const img = document.getElementById('logoImg');
        if (!p || !img) return;
        img.src = src;
        p.classList.remove('hidden');
    }

    ocultarPreviewLogo() {
        const p = document.getElementById('logoPreview');
        if(p) p.classList.add('hidden');
    }

    filtrarEmpresas(term) {
        const results = this.empresas.filter(e => 
            e.nombre.toLowerCase().includes(term) || 
            (e.sector && e.sector.toLowerCase().includes(term))
        );
        this.renderizarTablaAdmin(results);
    }

    aplicarFiltros() {
        const ctg = document.getElementById('filterCategoria').value;
        const est = document.getElementById('filtroEstado').value;
        const dst = document.getElementById('filterDestacado').value;

        const results = this.empresas.filter(e => {
            const mcProp = !ctg || e.sector === ctg;
            const meProp = !est || e.estado === est;
            const mdProp = !dst || e.destacado == dst;
            return mcProp && meProp && mdProp;
        });
        this.renderizarTablaAdmin(results);
    }

    exportarEmpresas() {
        const datosExportar = this.empresas.map(empresa => ({
            id: empresa.id,
            nombre: empresa.nombre,
            sector: empresa.sector,
            email: empresa.email,
            telefono: empresa.telefono,
            sitio_web: empresa.sitio_web,
            estado: empresa.estado,
            descuento_porcentaje: empresa.descuento_porcentaje,
            fecha_convenio: empresa.fecha_convenio
        }));

        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(datosExportar, null, 2));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", `empresas-convenio-${new Date().toISOString().split('T')[0]}.json`);
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();

        this.mostrarExito('Datos exportados exitosamente');
    }

    mostrarExito(mensaje) {
        // console.log('✅', mensaje);
        this.mostrarNotificacion(mensaje, 'success');
    }

    mostrarError(mensaje) {
        console.error('❌', mensaje);
        this.mostrarNotificacion(mensaje, 'error');
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 4000) {
        // Crear contenedor de notificaciones si no existe
        let contenedor = document.getElementById('notificacionesContainer');
        if (!contenedor) {
            contenedor = document.createElement('div');
            contenedor.id = 'notificacionesContainer';
            contenedor.className = 'fixed top-4 right-4 z-50 space-y-2';
            document.body.appendChild(contenedor);
        }

        // Crear notificación
        const notificacion = document.createElement('div');
        notificacion.className = `notification max-w-sm p-4 rounded-lg shadow-lg border-l-4 transform translate-x-full transition-transform duration-300 ${this.obtenerClasesNotificacion(tipo)}`;

        notificacion.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="${this.obtenerIconoNotificacion(tipo)} mr-3"></i>
                    <span class="text-sm font-medium">${mensaje}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        contenedor.appendChild(notificacion);

        // Animar entrada
        setTimeout(() => {
            notificacion.classList.remove('translate-x-full');
        }, 10);

        // Auto-remover después de la duración especificada
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notificacion.parentElement) {
                        notificacion.remove();
                    }
                }, 300);
            }
        }, duracion);
    }

    obtenerClasesNotificacion(tipo) {
        switch (tipo) {
            case 'success':
                return 'bg-green-50 border-green-400 text-green-800';
            case 'error':
                return 'bg-red-50 border-red-400 text-red-800';
            case 'warning':
                return 'bg-yellow-50 border-yellow-400 text-yellow-800';
            default:
                return 'bg-blue-50 border-blue-400 text-blue-800';
        }
    }

    obtenerIconoNotificacion(tipo) {
        switch (tipo) {
            case 'success':
                return 'fas fa-check-circle text-green-500';
            case 'error':
                return 'fas fa-exclamation-circle text-red-500';
            case 'warning':
                return 'fas fa-exclamation-triangle text-yellow-500';
            default:
                return 'fas fa-info-circle text-blue-500';
        }
    }

    // ============================================
    // UTILS
    // ============================================

    formatearFecha(fechaStr) {
        if (!fechaStr) return '—';
        const fecha = new Date(fechaStr);
        return fecha.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        const contenedor = document.getElementById('notifications');
        if (!contenedor) return;

        const notificacion = document.createElement('div');
        notificacion.className = `notification max-w-sm p-4 rounded-xl shadow-2xl border border-white/10 backdrop-blur-xl transform translate-x-full transition-all duration-300 depth-3 mb-3 ${this.obtenerClasesNotificacion(tipo)}`;

        notificacion.innerHTML = `
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                        <i class="${this.obtenerIconoNotificacion(tipo)}"></i>
                    </div>
                    <span class="text-sm font-bold text-white">${mensaje}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        contenedor.appendChild(notificacion);

        // Animar entrada
        setTimeout(() => notificacion.classList.remove('translate-x-full'), 10);

        // Auto-remover
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.classList.add('opacity-0', 'scale-95');
                setTimeout(() => notificacion.remove(), 300);
            }
        }, duracion);
    }

    obtenerClasesNotificacion(tipo) {
        switch (tipo) {
            case 'success': return 'bg-green-500/20 border-green-500/30';
            case 'error': return 'bg-red-500/20 border-red-500/30';
            case 'warning': return 'bg-yellow-500/20 border-yellow-500/30';
            default: return 'bg-blue-500/20 border-blue-500/30';
        }
    }

    obtenerIconoNotificacion(tipo) {
        switch (tipo) {
            case 'success': return 'fas fa-check-circle text-green-500';
            case 'error': return 'fas fa-exclamation-circle text-red-500';
            case 'warning': return 'fas fa-exclamation-triangle text-yellow-500';
            default: return 'fas fa-info-circle text-blue-500';
        }
    }
}

// Nota: La inicialización se maneja en el HTML para asegurar que el DOM esté listo y permite inyección de debug.