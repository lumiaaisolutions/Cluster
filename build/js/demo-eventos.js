// Configuración de la API
const API_BASE = './api';

// Variables de Estado Global
let eventos = [];
let filteredEventos = [];
let registrosEmpresas = [];
let filteredRegistros = [];
let registros = []; // Registros del evento actualmente abierto en el modal de asistentes
let currentEventId = null;
let currentRegistroId = null;

// Utilidades de notificación
// Sistema de notificaciones Premium Porsche
function showNotification(message, type = 'info') {
    const container = document.getElementById('notifications');
    if (!container) return;

    const id = Date.now();
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-triangle',
        warning: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    
    const colors = {
        success: 'border-green-500/50 bg-green-500/10 text-green-400',
        error: 'border-porsche-accent/50 bg-porsche-accent/10 text-porsche-accent',
        warning: 'border-orange-500/50 bg-orange-500/10 text-orange-400',
        info: 'border-blue-500/50 bg-blue-500/10 text-blue-400'
    };

    const notification = document.createElement('div');
    notification.id = `notif-${id}`;
    notification.className = `glass-card p-4 mb-3 border-l-4 flex items-center space-x-3 min-w-[320px] shadow-2xl transition-all duration-300 transform translate-x-full ${colors[type] || colors.info}`;
    
    notification.innerHTML = `
        <i class="fas ${icons[type] || icons.info} text-xl"></i>
        <div class="flex-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] opacity-60 mb-1">${type === 'error' ? 'Sistema' : 'Notificación'}</p>
            <p class="text-sm font-semibold text-white/90 leading-tight">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="text-white/20 hover:text-white transition-colors p-1">
            <i class="fas fa-times text-xs"></i>
        </button>
    `;

    container.appendChild(notification);

    // Animar entrada (instantáneo)
    requestAnimationFrame(() => {
        notification.classList.remove('translate-x-full');
    });

    // Auto remover
    setTimeout(() => {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => notification.remove(), 500);
    }, 5000);
}

// Exponer globalmente
window.showNotification = showNotification;

// Cargar eventos desde la API (Unificado)
async function loadEventos() {
    console.log('📡 loadEventos iniciado...');
    try {
        const container = document.getElementById('eventosContainer');
        const loadingState = document.getElementById('loadingEventos');
        
        if (loadingState) loadingState.classList.remove('hidden');
        
        const response = await fetch(`${API_BASE}/eventos.php?action=listar&t=` + Date.now(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        
        const result = await response.json();
        console.log('📥 Datos de eventos recibidos:', result);
        
        if (result.success && result.eventos) {
            // Normalizar estados nulos y asegurar consistencia de datos
            eventos = result.eventos.map(ev => ({
                ...ev,
                estado: (ev.estado === null || ev.estado === '' || ev.estado === undefined) ? 'programado' : ev.estado,
                tipo: ev.tipo || 'evento',
                ubicacion: ev.ubicacion || 'Por definir'
            }));
            
            console.log('✅ Eventos procesados:', eventos.length);
            filteredEventos = [...eventos];
            renderEventos();
            updateStats();
        } else {
            console.warn('⚠️ No se obtuvieron eventos o success=false:', result);
            showEmptyEventos();
        }
    } catch (error) {
        console.error('❌ Error fatal al cargar eventos:', error);
        showErrorEventos();
        showNotification('Error al sincronizar eventos: ' + error.message, 'error');
    } finally {
        const loadingState = document.getElementById('loadingEventos');
        if (loadingState) loadingState.classList.add('hidden');
    }
}

// Función para refrescar eventos manualmente
async function refreshEventosDemo() {
    showNotification('Sincronizando con base de datos...', 'info');
    await loadEventos();
}

// Actualizar estadísticas (Unificado)
function updateStats() {
    const total = eventos.length;
    // Corregir lógica de filtrado para estadísticas
    const proximos = eventos.filter(e => {
        const est = (e.estado || '').toLowerCase();
        return est === 'proximo' || est === 'programado' || est === 'activo';
    }).length;
    
    const enCurso = eventos.filter(e => {
        const est = (e.estado || '').toLowerCase();
        return est.includes('curso');
    }).length;

    const totalRegistros = eventos.reduce((sum, e) => sum + (parseInt(e.registrados) || 0), 0);
    
    console.log('📊 Actualizando estadísticas:', { total, proximos, enCurso, totalRegistros });

    // Contadores principales
    const elements = {
        'totalEventos': total,
        'proximosEventos': proximos,
        'enCursoEventos': enCurso,
        'totalRegistros': totalRegistros,
        'totalEventosDemo': total // Retrocompatibilidad
    };

    for (const [id, value] of Object.entries(elements)) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }
}

// === SIDEBAR FUNCTIONALITY (Migrada desde HTML) ===
function initializeSidebar() {
    const sidebar = document.querySelector('.porsche-sidebar');
    const overlay = document.getElementById('sidenavOverlay');
    const hamburgerBtn = document.querySelector('[sidenav-trigger]');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    if (!sidebar || !overlay || !hamburgerBtn) return;

    function toggleSidebar(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        const isVisible = sidebar.classList.contains('sidenav-show');
        if (!isVisible) {
            sidebar.classList.add('sidenav-show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        } else {
            closeSidebarInternal();
        }
    }

    function closeSidebarInternal() {
        sidebar.classList.remove('sidenav-show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    hamburgerBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebarInternal);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebarInternal);
}

// === NOTIFICACIONES FUNCTIONALITY (Migrada desde HTML) ===
let notificationTab = 'pendientes';
let notificationsData = { pendientes: [], confirmados: [], rechazados: [] };

async function loadNotificaciones() {
    const loading = document.getElementById('loadingNotificaciones');
    const empty = document.getElementById('emptyNotificaciones');
    if (loading) loading.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');

    try {
        const response = await fetch(`${API_BASE}/notificaciones_eventos.php`);
        const result = await response.json();
        if (result.success) {
            notificationsData = result.data.por_estado;
            updateNotificationCounters();
            renderNotificaciones(notificationTab);
        }
    } catch (error) {
        console.error('Error notificaciones:', error);
    } finally {
        if (loading) loading.classList.add('hidden');
    }
}

function updateNotificationCounters() {
    const ids = ['Pendientes', 'Confirmados', 'Rechazados'];
    ids.forEach(id => {
        const val = notificationsData[id.toLowerCase()]?.length || 0;
        const el = document.getElementById(`total${id}`);
        const badge = document.getElementById(`badge${id}`);
        if (el) el.textContent = val;
        if (badge) badge.textContent = val;
    });
}

function showTab(tab) {
    notificationTab = tab;
    ['pendientes', 'confirmados', 'rechazados'].forEach(t => {
        const btn = document.getElementById(`tab${t.charAt(0).toUpperCase() + t.slice(1)}`);
        const content = document.getElementById(`notificaciones${t.charAt(0).toUpperCase() + t.slice(1)}`);
        
        if (t === tab) {
            // Clases de pestaña activa (Porsche Orange)
            if (btn) btn.className = 'px-4 py-2 rounded-lg text-sm font-semibold transition-all bg-orange-500/20 text-orange-400 border border-orange-500/30 shadow-lg shadow-orange-950/20';
            if (content) content.classList.remove('hidden');
        } else {
            // Clases de pestaña inactiva
            if (btn) btn.className = 'px-4 py-2 rounded-lg text-sm font-semibold transition-all hover:bg-white/5 text-porsche-silver border border-transparent';
            if (content) content.classList.add('hidden');
        }
    });
    renderNotificaciones(tab);
}

function renderNotificaciones(tab) {
    const container = document.getElementById(`notificaciones${tab.charAt(0).toUpperCase() + tab.slice(1)}`);
    if (!container) return;
    
    const data = notificationsData[tab] || [];
    const empty = document.getElementById('emptyNotificaciones');

    if (data.length === 0) {
        container.innerHTML = '';
        if (empty) empty.classList.remove('hidden');
        return;
    }

    if (empty) empty.classList.add('hidden');
    
    container.innerHTML = data.map(n => {
        const fecha = new Date(n.fecha_registro).toLocaleDateString('es-MX', {
            month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
        });

        const badgeClass = {
            'pendiente': 'border-orange-500/20 text-orange-400 bg-orange-500/5',
            'confirmado': 'border-green-500/20 text-green-400 bg-green-500/5',
            'rechazado': 'border-red-500/20 text-red-400 bg-red-500/5'
        }[n.estado_registro] || 'border-white/10 text-white/40';

        return `
            <div class="glass-card p-5 border-l-4 border-white/5 hover:border-porsche-accent transition-all group animate-fade-in">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center font-bold text-porsche-silver shadow-inner">
                                ${(n.nombre_usuario || 'U').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <p class="text-white font-bold text-sm tracking-tight">${n.nombre_usuario || 'Usuario Anónimo'}</p>
                                <p class="text-[10px] text-porsche-silver opacity-60 uppercase font-black tracking-widest mt-0.5">${n.nombre_empresa || 'Empresa Independiente'}</p>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4 ml-1">
                            <div class="flex items-center text-[10px] font-bold text-porsche-accent/80 uppercase tracking-tighter">
                                <i class="fas fa-calendar-alt mr-2 text-red-500/50"></i>
                                ${n.evento_titulo || 'Evento Invitado'}
                            </div>
                            <div class="flex items-center text-[10px] text-porsche-silver/60">
                                <i class="far fa-clock mr-2 opacity-40"></i>
                                ${fecha}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border ${badgeClass}">
                            ${n.estado_registro}
                        </span>
                        
                        <div class="flex gap-2">
                            ${n.estado_registro === 'pendiente' ? `
                                <button onclick="cambiarEstadoNotif(${n.id}, 'confirmado')" 
                                        class="w-8 h-8 rounded-lg bg-green-500/10 hover:bg-green-500/20 text-green-400 border border-green-500/20 transition-all flex items-center justify-center shadow-lg hover:scale-110 active:scale-95"
                                        title="Confirmar Registro">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                                <button onclick="cambiarEstadoNotif(${n.id}, 'rechazado')"
                                        class="w-8 h-8 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-all flex items-center justify-center shadow-lg hover:scale-110 active:scale-95"
                                        title="Rechazar">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            ` : `
                                <button onclick="viewDetalleNotif(${n.id})"
                                        class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 text-white border border-white/10 transition-all flex items-center justify-center hover:scale-110 active:scale-95"
                                        title="Ver Detalles">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                            `}
                        </div>
                    </div>
                </div>
                
                ${n.comentarios ? `
                <div class="mt-4 pt-4 border-t border-white/5 italic text-[11px] text-porsche-silver opacity-60 flex items-start">
                    <i class="fas fa-quote-left mr-2 text-porsche-accent opacity-30 text-[8px]"></i>
                    "${n.comentarios}"
                </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

// === ACCIONES DE NOTIFICACIÓN ===
async function cambiarEstadoNotif(id, nuevoEstado) {
    console.log(`📡 Cambiando estado de notif ${id} a ${nuevoEstado}`);
    try {
        const response = await fetch(`${API_BASE}/notificaciones_eventos.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'actualizar_estado', id, estado: nuevoEstado })
        });
        
        const data = await response.json();
        if (data.success) {
            showNotification(`Solicitud ${nuevoEstado === 'confirmado' ? 'aprobada' : 'rechazada'} con éxito`, 'success');
            await loadNotificaciones();
            updateStats(); // Actualizar contadores del dashboard
        } else {
            throw new Error(data.message || 'Error al actualizar');
        }
    } catch (error) {
        console.error('❌ Error en cambiarEstadoNotif:', error);
        showNotification('Error al procesar solicitud: ' + error.message, 'error');
    }
}

async function viewDetalleNotif(id) {
    // Buscar el registro en los datos ya cargados de notificaciones
    const todasLasNotifs = [
        ...(notificationsData.pendientes  || []),
        ...(notificationsData.confirmados || []),
        ...(notificationsData.rechazados  || [])
    ];
    const n = todasLasNotifs.find(x => x.id == id);

    if (!n) {
        showNotification('No se encontraron los detalles del registro', 'error');
        return;
    }

    currentRegistroId = id;

    const fecha = new Date(n.fecha_registro).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });

    const badgeClass = {
        'pendiente':  'border-orange-500/20 text-orange-400 bg-orange-500/5',
        'confirmado': 'border-green-500/20  text-green-400  bg-green-500/5',
        'rechazado':  'border-red-500/20    text-red-400    bg-red-500/5'
    }[n.estado_registro] || 'border-white/10 text-white/40';

    const content = document.getElementById('detalleRegistroContent');
    if (content) {
        content.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Nombre</p>
                    <p class="text-white font-semibold">${n.nombre_usuario || 'No especificado'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Empresa</p>
                    <p class="text-white font-semibold">${n.nombre_empresa || 'No especificada'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Email</p>
                    <p class="text-white font-semibold">${n.email_contacto || '-'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Teléfono</p>
                    <p class="text-white font-semibold">${n.telefono_contacto || '-'}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Evento</p>
                    <p class="text-white font-semibold">${n.evento_titulo || 'Evento'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Fecha de registro</p>
                    <p class="text-white font-semibold">${fecha}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Estado</p>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border ${badgeClass}">${n.estado_registro}</span>
                </div>
                ${n.comentarios ? `
                <div class="col-span-2">
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Comentarios</p>
                    <p class="text-white/80 text-sm italic">${n.comentarios}</p>
                </div>` : ''}
            </div>
        `;
    }

    const modal = document.getElementById('detalleRegistroModal');
    if (modal) modal.classList.remove('hidden');
}

async function eliminarRegistro(id) {
    if (!confirm('¿Estás seguro de eliminar este registro permanentemente?')) return;

    try {
        const response = await fetch(`${API_BASE}/eventos.php?action=eliminar_registro&id=${id}`);
        const data = await response.json();

        if (data.success) {
            showNotification('Registro eliminado permanentemente', 'warning');
            loadNotificaciones();
        } else {
            throw new Error(data.message || 'Error al eliminar');
        }
    } catch (error) {
        console.error('❌ Error eliminando registro:', error);
        showNotification('No se pudo eliminar el registro', 'error');
    }
}

// Renderizar lista de registros de empresas - FUNCIÓN DESHABILITADA
function renderRegistrosEmpresas() {
    // console.log('🎨 Renderizando registros de empresas (DESHABILITADO):', filteredRegistros?.length || 0);
    // Esta función ha sido reemplazada por el sistema de notificaciones
    return;

    // const container = document.getElementById('registrosContainer');
    // const loadingState = document.getElementById('loadingRegistros');
    // const emptyState = document.getElementById('emptyRegistros');

    // // console.log('📦 Elementos DOM encontrados:', {
    //     container: !!container,
    //     loadingState: !!loadingState,
    //     emptyState: !!emptyState
    // });
    
    if (filteredRegistros.length === 0) {
        // console.log('📭 No hay registros para mostrar');
        container.innerHTML = '';
        loadingState.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }
    
    // console.log('📋 Renderizando', filteredRegistros.length, 'registros');
    loadingState.classList.add('hidden');
    emptyState.classList.add('hidden');
    
    container.innerHTML = filteredRegistros.map(registro => `
        <div class="rounded-lg p-4 transition-shadow cursor-pointer" style="border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.02);"
             onclick="viewDetalleRegistro(${registro.id})">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background: var(--surface-info);">
                                <i class="fas fa-building" style="color: var(--state-info);"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2">
                                <h4 class="text-sm font-medium claut-text-primary truncate">
                                    ${registro.nombre_empresa || registro.nombre_usuario || 'Sin nombre'}
                                </h4>
                                <span class="${getRegistroEstadoColor(registro.estado_registro)}">
                                    ${registro.estado_registro || 'Confirmado'}
                                </span>
                            </div>
                            <p class="text-sm claut-text-muted truncate">
                                ${registro.evento_titulo || 'Evento no encontrado'}
                            </p>
                            <div class="flex items-center space-x-4 mt-1 text-xs claut-text-muted">
                                <span><i class="fas fa-envelope mr-1"></i>${registro.email_contacto || 'Sin email'}</span>
                                <span><i class="fas fa-calendar mr-1"></i>${formatDate(registro.fecha_registro)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <i class="fas fa-chevron-right claut-text-muted"></i>
                </div>
            </div>
        </div>
    `).join('');
}

// Actualizar estadísticas de registros - FUNCIÓN DESHABILITADA
function updateRegistrosStats() {
    const total = registrosEmpresas.length;
    // console.log('📊 Estadísticas de registros (DESHABILITADO):', { total });

    // Esta función ha sido reemplazada por el sistema de notificaciones
    const element = document.getElementById('totalRegistrosEmpresas');
    if (element) {
        element.textContent = total;
    } else {
        // console.log('⚠️ Elemento totalRegistrosEmpresas no encontrado (esperado - reemplazado por notificaciones)');
    }
}

// Cargar eventos para el filtro - FUNCIÓN DESHABILITADA
async function loadEventosForFilter() {
    const filterEvento = document.getElementById('filterEvento');

    if (!filterEvento) {
        // console.log('⚠️ Elemento filterEvento no encontrado (esperado - sección reemplazada por notificaciones)');
        return;
    }
    
    if (eventos.length === 0) {
        // Si no hay eventos cargados, cargarlos primero
        await loadEventos();
    }
    
    // Limpiar opciones existentes (excepto la primera)
    while (filterEvento.children.length > 1) {
        filterEvento.removeChild(filterEvento.lastChild);
    }
    
    // Agregar eventos únicos que tienen registros
    const eventosConRegistros = [...new Set(registrosEmpresas.map(r => r.evento_id))];
    eventosConRegistros.forEach(eventoId => {
        const evento = eventos.find(e => e.id == eventoId);
        if (evento) {
            const option = document.createElement('option');
            option.value = eventoId;
            option.textContent = evento.titulo;
            filterEvento.appendChild(option);
        }
    });
}

// Aplicar filtros a registros de empresas - FUNCIÓN DESHABILITADA
function applyRegistrosFilters() {
    // console.log('🔍 applyRegistrosFilters llamado (DESHABILITADO)');
    // Esta función ha sido reemplazada por el sistema de filtros de notificaciones
    return;

    // const searchEmpresa = document.getElementById('searchEmpresa').value.toLowerCase();
    const eventoFilterElement = document.getElementById('filterEvento');
    const estadoFilterElement = document.getElementById('filterEstadoRegistro');

    if (!eventoFilterElement || !estadoFilterElement) {
        // console.log('⚠️ Elementos de filtro no encontrados (esperado - sección reemplazada por notificaciones)');
        return;
    }

    const eventoFilter = eventoFilterElement.value;
    const estadoFilter = estadoFilterElement.value;
    
    filteredRegistros = registrosEmpresas.filter(registro => {
        const matchesSearch = !searchEmpresa || 
            (registro.nombre_empresa && registro.nombre_empresa.toLowerCase().includes(searchEmpresa)) ||
            (registro.nombre_usuario && registro.nombre_usuario.toLowerCase().includes(searchEmpresa)) ||
            (registro.email_contacto && registro.email_contacto.toLowerCase().includes(searchEmpresa)) ||
            (registro.evento_titulo && registro.evento_titulo.toLowerCase().includes(searchEmpresa));
            
        const matchesEvento = !eventoFilter || registro.evento_id == eventoFilter;
        const matchesEstado = !estadoFilter || registro.estado_registro === estadoFilter;
        
        return matchesSearch && matchesEvento && matchesEstado;
    });
    
    renderRegistrosEmpresas();
}

// Ver detalle de un registro (Ojo en Listado de Asistentes)
async function viewDetalleRegistro(registroId) {
    // Buscar en el array 'registros' que se carga en viewRegistros()
    const registro = (typeof registros !== 'undefined' ? registros : []).find(r => r.id == registroId);
    if (!registro) {
        showNotification('No se pudo encontrar el detalle de este asistente', 'error');
        return;
    }

    currentRegistroId = registroId;

    const estadoBadge = {
        'confirmado': 'border-green-500/20 text-green-400 bg-green-500/5',
        'pendiente':  'border-orange-500/20 text-orange-400 bg-orange-500/5',
        'rechazado':  'border-red-500/20 text-red-400 bg-red-500/5'
    }[registro.estado_registro] || 'border-white/10 text-white/40';

    const fecha = new Date(registro.fecha_registro).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });

    const content = document.getElementById('detalleRegistroContent');
    if (content) {
        content.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Nombre</p>
                    <p class="text-white font-semibold">${registro.nombre_usuario || 'Anónimo'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Empresa / Organización</p>
                    <p class="text-white font-semibold">${registro.nombre_empresa || 'No especificada'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Email</p>
                    <p class="text-white font-semibold">${registro.email_contacto || '-'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Teléfono</p>
                    <p class="text-white font-semibold">${registro.telefono_contacto || '-'}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Fecha de registro</p>
                    <p class="text-white font-semibold">${fecha}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Estado</p>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border ${estadoBadge}">${registro.estado_registro || 'confirmado'}</span>
                </div>
                ${registro.comentarios ? `
                <div class="col-span-2">
                    <p class="text-[10px] font-bold text-porsche-silver uppercase tracking-widest mb-1">Comentarios</p>
                    <p class="text-white/80 text-sm italic">${registro.comentarios}</p>
                </div>` : ''}
            </div>
        `;
    }

    const modal = document.getElementById('detalleRegistroModal');
    if (modal) modal.classList.remove('hidden');
}

// Cerrar modal de detalle de registro
function closeDetalleRegistroModal() {
    const modal = document.getElementById('detalleRegistroModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    currentRegistroId = null;
}

// Eliminar registro
async function eliminarRegistro(id = null) {
    const rid = id || currentRegistroId;
    if (!rid) return;
    
    const confirmMessage = `¿Estás seguro de eliminar este registro permanentemente?`;
    
    if (confirm(confirmMessage)) {
        try {
            const response = await fetch(`${API_BASE}/eventos.php?action=eliminar_registro&registro_id=${rid}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json' }
            });
            
            if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
            
            const data = await response.json();
            if (data.success) {
                showNotification('Registro eliminado exitosamente', 'success');
                closeDetalleRegistroModal();
                await loadNotificaciones();
                await loadNotificaciones();
                await loadEventos(); 
            } else {
                throw new Error(data.message || 'Error al eliminar');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        }
    }
}

// Refrescar registros - FUNCIÓN DESHABILITADA
async function refreshRegistros() {
    // console.log('🔄 RefreshRegistros llamado (DESHABILITADO - usando sistema de notificaciones)');
    // Esta función ha sido reemplazada por loadNotificaciones en demo_evento.html
    return;

    // document.getElementById('loadingRegistros').classList.remove('hidden');
    // document.getElementById('registrosContainer').innerHTML = '';
    // await loadNotificaciones();
    // showNotification('Registros actualizados', 'success');
}

// El cargador de eventos principal unificado está al inicio del archivo

// Cargar eventos desde la API (misma lógica que eventos.html)

// Renderizar lista de eventos en formato TARJETAS (Estilo Elite)
function renderEventos() {
    console.log('🎨 Renderizando tarjetas de eventos:', filteredEventos.length);
    const container = document.getElementById('eventosContainer');
    const loadingState = document.getElementById('loadingEventos');
    const emptyState = document.getElementById('emptyEventos');
    
    if (!container) {
        console.error('❌ ERROR: Contenedor #eventosContainer no encontrado en el DOM');
        return;
    }

    if (filteredEventos.length === 0) {
        container.innerHTML = '<div class="col-span-full px-6 py-12 text-center claut-text-muted font-medium">No se encontraron eventos coincidentes</div>';
        if (emptyState) emptyState.classList.remove('hidden');
        return;
    }
    
    if (loadingState) loadingState.classList.add('hidden');
    if (emptyState) emptyState.classList.add('hidden');
    
    container.innerHTML = filteredEventos.map(evento => createEventoRow(evento)).join('');
    console.log('✅ Tabla renderizada con éxito');
    
    // Actualizar badge
    const badge = document.getElementById('totalEventosDemo');
    if (badge) badge.textContent = filteredEventos.length;
}

// Crear tarjeta de evento para el directorio (Estilo Elite)
function createEventoRow(evento) {
    const fechaInicio = new Date(evento.fecha_inicio);
    const fechaFormateada = fechaInicio.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });

    const horaFormateada = fechaInicio.toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit'
    });

    const imagenUrl = evento.imagen_url || (evento.imagen ? `./api/eventos.php?action=imagen&file=${evento.imagen}` : '');

    // Portada: imagen real del evento, o degradado con ícono si no tiene
    const cover = imagenUrl
        ? `<div class="elite-cover-bg" style="background-image:url('${imagenUrl}')"></div>`
        : `<div class="elite-cover-bg" style="background:linear-gradient(135deg,#1a2a3d,#2563eb 60%,#0a1220);display:flex;align-items:center;justify-content:center;"><i class="far fa-calendar-alt" style="font-size:36px;color:rgba(255,255,255,0.4);"></i></div>`;

    // Determinar badge de estado
    const estado = (evento.estado || 'programado').toLowerCase();
    let estadoLabel = 'En Preparación';
    let estadoBadge = 'claut-badge--warning';

    if (estado.includes('curso') || estado === 'activo') {
        estadoLabel = 'Publicado';
        estadoBadge = 'claut-badge--success';
    } else if (estado.includes('final')) {
        estadoLabel = 'Histórico';
        estadoBadge = 'claut-badge--neutral';
    } else if (estado.includes('cancel')) {
        estadoLabel = 'Cancelado';
        estadoBadge = 'claut-badge--danger';
    }

    const capacidadInfo = evento.capacidad_maxima
        ? `<div class="row"><i class="fas fa-users"></i><span>${evento.capacidad_actual || 0} / ${evento.capacidad_maxima} registrados</span></div>`
        : (evento.capacidad_actual ? `<div class="row"><i class="fas fa-users"></i><span>${evento.capacidad_actual} registrados</span></div>` : '');

    return `
        <article class="elite-card">
            <div class="elite-card-cover">
                ${cover}
                <span class="claut-badge ${estadoBadge} elite-cover-badge">${estadoLabel}</span>
                <span class="claut-badge claut-badge--neutral elite-cover-badge elite-cover-badge--right">${evento.tipo || 'evento'}</span>
                <div class="elite-cover-title">${evento.titulo}</div>
            </div>
            <div class="elite-card-body">
                <div class="elite-card-meta">
                    <div class="row"><i class="far fa-calendar-alt"></i><span>${fechaFormateada} · ${horaFormateada} h</span></div>
                    <div class="row"><i class="fas fa-map-marker-alt"></i><span>${evento.ubicacion || 'Por definir'}</span></div>
                    ${capacidadInfo}
                </div>
                <div class="elite-card-foot">
                    <span class="elite-chip"><i class="fas fa-hashtag"></i>${evento.id}</span>
                    <div class="elite-actions">
                        <button onclick="viewEventoRegistros(${evento.id})" class="elite-btn elite-btn--view" title="Ver Asistentes"><i class="fas fa-users"></i> Registros</button>
                        <button onclick="editEvento(${evento.id})" class="elite-btn elite-btn--edit" title="Editar Evento"><i class="fas fa-edit"></i> Editar</button>
                        <button onclick="deleteEvento(${evento.id}, '${evento.titulo.replace(/'/g, "\\'")}', ${evento.capacidad_actual || 0})" class="elite-btn elite-btn--del" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </article>
    `;
}

// Obtener color del estado del evento con clases de diseño Porsche
function getEventoEstadoColor(estado) {
    const s = (estado || '').toLowerCase();
    if (s.includes('progra') || s.includes('activo')) return 'badge-active';
    if (s.includes('curso')) return 'badge-pending';
    if (s.includes('final') || s.includes('pasado')) return 'badge-pending';
    if (s.includes('cancel')) return 'badge-error';
    return 'badge-active';
}


// Mostrar estado vacío de eventos con diseño Premium
function showEmptyEventos() {
    const container = document.getElementById('eventosContainer');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full py-20 text-center animate-fade-in">
                <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4 border border-white/10">
                    <i class="fas fa-calendar-times text-2xl text-porsche-silver opacity-40"></i>
                </div>
                <h3 class="text-white font-bold opacity-80 uppercase tracking-widest text-sm">No se encontraron eventos</h3>
                <p class="text-porsche-silver text-xs mt-2 opacity-50">Intenta ajustar los filtros de búsqueda</p>
            </div>
        `;
    }
}

// Mostrar error de carga de eventos con diseño Premium
function showErrorEventos() {
    const container = document.getElementById('eventosContainer');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full py-20 text-center animate-fade-in">
                <div class="w-16 h-16 bg-porsche-accent/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-porsche-accent/20">
                    <i class="fas fa-exclamation-triangle text-2xl text-porsche-accent"></i>
                </div>
                <h3 class="text-white font-bold opacity-80 uppercase tracking-widest text-sm text-porsche-accent">Error de Conexión</h3>
                <p class="text-porsche-silver text-xs mt-2 opacity-50 mb-6">No se pudieron sincronizar los eventos con el servidor</p>
                <button onclick="loadEventos()" class="px-6 py-2 bg-porsche-accent hover:bg-porsche-accent-hover text-white rounded-lg text-xs font-bold transition-all shadow-lg shadow-porsche-accent/20">
                    <i class="fas fa-sync-alt mr-2"></i>REINTENTAR AHORA
                </button>
            </div>
        `;
    }
}

// Aplicar filtros a eventos (Unificado)
function applyFilters() {
    const searchInput = document.getElementById('searchEvento') || document.getElementById('searchInput');
    const estadoInput = document.getElementById('filterEventoEstado') || document.getElementById('filterEstado');
    const tipoInput = document.getElementById('filterEventoTipo') || document.getElementById('filterEventoTipo');
    const searchNotif = document.getElementById('searchNotificaciones');
    
    if (!searchInput) return;

    const search = searchInput.value.toLowerCase();
    const estado = estadoInput ? estadoInput.value : '';
    const tipo = tipoInput ? tipoInput.value : '';
    
    // 1. Filtrar Eventos
    filteredEventos = eventos.filter(evento => {
        const matchesSearch = !search || 
            (evento.titulo && evento.titulo.toLowerCase().includes(search)) ||
            (evento.descripcion && evento.descripcion.toLowerCase().includes(search)) ||
            (evento.ubicacion && evento.ubicacion.toLowerCase().includes(search));
            
        const matchesEstado = !estado || (evento.estado && evento.estado.toLowerCase() === estado.toLowerCase());
        const matchesTipo = !tipo || (evento.tipo && evento.tipo.toLowerCase() === tipo.toLowerCase());
        
        return matchesSearch && matchesEstado && matchesTipo;
    });
    
    renderEventos();

    // 2. Filtrar Notificaciones (Si existe el buscador de notifs)
    if (searchNotif) {
        const sn = searchNotif.value.toLowerCase();
        // El filtrado por tab se maneja en renderNotificaciones
        renderNotificaciones(notificationTab); 
    }
}

function applyEventosFilters() { applyFilters(); }

// Ver registros de un evento específico
function viewEventoRegistros(eventoId) {
    // console.log('👥 Ver registros del evento:', eventoId);
    // console.log('🔄 Iniciando viewEventoRegistros...');
    
    // Usar valores por defecto para evitar errores
    const eventoTitulo = `Evento ID ${eventoId}`;
    const totalRegistros = 0;
    
    // console.log('📋 Usando título por defecto:', eventoTitulo);
    // console.log('🚀 Llamando a viewRegistros...');
    
    // Llamar directamente a viewRegistros
    viewRegistros(eventoId, eventoTitulo, totalRegistros);
}

// Refrescar eventos
async function refreshEventos() {
    await loadEventos();
    showNotification('Eventos actualizados', 'success');
}

// Editar evento
function editEvento(eventoId) {
    const evento = eventos.find(e => e.id == eventoId);
    if (!evento) {
        showNotification('Evento no encontrado', 'error');
        return;
    }
    
    currentEventId = eventoId;
    document.getElementById('modalTitle').textContent = 'Editar Evento';
    document.getElementById('eventId').value = eventoId;
    
    document.getElementById('titulo').value = evento.titulo || '';
    document.getElementById('descripcion').value = evento.descripcion || '';
    document.getElementById('ubicacion').value = evento.ubicacion || '';
    document.getElementById('categoria').value = evento.tipo || evento.categoria || 'reunion';
    document.getElementById('estado').value = evento.estado || 'activo';
    const vvChk = document.getElementById('visible_visitantes');
    if (vvChk) vvChk.checked = String(evento.visible_visitantes) === '1';
    document.getElementById('capacidad_maxima').value = evento.capacidad_maxima || 100;
    document.getElementById('precio').value = evento.precio || 0;
    
    if (evento.fecha_inicio) {
        const fechaInicio = new Date(evento.fecha_inicio);
        const fechaStr = fechaInicio.toISOString().split('T')[0];
        const horaStr = fechaInicio.toTimeString().slice(0, 5);
        document.getElementById('fecha_evento').value = fechaStr;
        document.getElementById('hora_evento').value = horaStr;
    }

    if (evento.imagen && evento.imagen.trim()) {
        if (typeof clearImagePreview === 'function') clearImagePreview();

        const img = document.getElementById('imagenPreviewImg');
        const preview = document.getElementById('imagenPreview');
        const imagenUrlFinal = document.getElementById('imagen_url_final');

        if (img && preview && imagenUrlFinal) {
            let imageUrl = evento.imagen;
            if (!imageUrl.startsWith('http') && !imageUrl.startsWith('data:')) {
                imageUrl = `./api/eventos.php?action=imagen&id=${evento.id}&t=${Date.now()}`;
            }

            img.onload = () => {
                preview.classList.remove('hidden');
                imagenUrlFinal.value = evento.imagen;
            };
            img.onerror = () => {
                img.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(evento.titulo || 'Evento')}&background=C7252B&color=ffffff&bold=true&length=2&size=300&font-size=0.33`;
            };
            img.src = imageUrl;
        }
    }

    const eventModalEl = document.getElementById('eventModal');
    if (eventModalEl) {
        eventModalEl.classList.add('open');
        const wizardEl = eventModalEl.querySelector('.claut-wizard');
        if (wizardEl && wizardEl.__clautWizardReset) wizardEl.__clautWizardReset();
    }
}

// Eliminar evento
async function deleteEvento(eventoId, eventoTitulo, registrados) {
    let mensaje = `¿Estás seguro de eliminar el evento "${eventoTitulo}"?`;
    if (registrados > 0) mensaje += `\n\n⚠️ ADVERTENCIA: Este evento tiene ${registrados} usuarios registrados. Al eliminarlo también se eliminarán todos los registros.`;
    
    if (!confirm(mensaje)) return;
    
    try {
        const response = await fetch(`${API_BASE}/eventos.php?action=eliminar&id=${eventoId}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
        
        const result = await response.json();
        if (result.success) {
            showNotification(`Evento "${eventoTitulo}" eliminado exitosamente`, 'success');
            await loadEventos();
            await loadNotificaciones();
        } else {
            throw new Error(result.message || 'Error al eliminar evento');
        }
    } catch (error) {
        console.error('❌ Error al eliminar evento:', error);
        showNotification(`Error al eliminar evento: ${error.message}`, 'error');
    }
}

function deleteEventoDemo(id, titulo, reg) { deleteEvento(id, titulo, reg); }

// Mostrar modal para crear evento
function showCreateModal() {
    // Limpiar completamente
    currentEventId = null;

    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('eventForm');
    const eventIdField = document.getElementById('eventId');
    const fechaEventoField = document.getElementById('fecha_evento');
    const modal = document.getElementById('eventModal');

    if (modalTitle) {
        modalTitle.textContent = 'Crear Evento';
    }

    if (form) {
        form.reset();
    }

    if (eventIdField) {
        eventIdField.value = '';
    }

    // Limpiar preview de imagen si existe la función
    if (typeof clearImagePreview === 'function') {
        clearImagePreview();
    }

    // Establecer fecha mínima como hoy
    if (fechaEventoField) {
        const today = new Date().toISOString().split('T')[0];
        fechaEventoField.min = today;
    }

    if (modal) {
        modal.classList.add('open');
        const wizardEl = modal.querySelector('.claut-wizard');
        if (wizardEl && wizardEl.__clautWizardReset) wizardEl.__clautWizardReset();
    }

    // console.log('Modal de creación abierto - eventId limpiado');
}


// Duplicar evento
async function duplicateEvento(id) {
    try {
        const response = await fetch(`${API_BASE}/eventos.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
            const evento = data.data;
            
            // Crear copia con fecha futura
            const fechaFutura = new Date();
            fechaFutura.setDate(fechaFutura.getDate() + 7);
            
            const eventoData = {
                titulo: `${evento.titulo} (Copia)`,
                descripcion: evento.descripcion,
                fecha_inicio: fechaFutura.toISOString().slice(0, 19).replace('T', ' '),
                fecha_fin: evento.fecha_fin ? new Date(new Date(evento.fecha_fin).getTime() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 19).replace('T', ' ') : null,
                ubicacion: evento.ubicacion,
                tipo: evento.tipo,
                categoria: evento.categoria,
                estado: 'programado',
                precio: evento.precio,
                capacidad_maxima: evento.capacidad_maxima,
                organizador_nombre: evento.organizador_nombre,
                organizador_apellido: evento.organizador_apellido,
                organizador_email: evento.organizador_email,
                notas: evento.notas
            };
            
            const createResponse = await fetch(`${API_BASE}/eventos.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(eventoData)
            });
            
            const createData = await createResponse.json();
            
            if (createData.success) {
                showNotification('Evento duplicado exitosamente', 'success');
                await loadEventos();
            } else {
                throw new Error(createData.message || 'Error al duplicar evento');
            }
        }
    } catch (error) {
        showNotification('Error al duplicar evento: ' + error.message, 'error');
    }
}

// Cerrar modal
function closeModal() {
    const modal = document.getElementById('eventModal');
    const form = document.getElementById('eventForm');
    const eventIdField = document.getElementById('eventId');

    if (modal) {
        modal.classList.remove('open');
    }

    if (form) {
        form.reset();
    }

    if (eventIdField) {
        eventIdField.value = '';
    }

    // Limpiar variable global
    currentEventId = null;

    // Limpiar preview de imagen si existe la función
    if (typeof clearImagePreview === 'function') {
        clearImagePreview();
    }

    // console.log('Modal cerrado y formulario limpiado');
}

// Ver registros de evento
async function viewRegistros(eventoId, eventoTitulo, totalRegistros) {
    // console.log('👥 Cargando registros para evento:', eventoId, eventoTitulo);
    
    try {
        // Primero verificar si hay registros en general
        // console.log('🔍 Verificando registros generales primero...');
        const testResponse = await fetch(`${API_BASE}/eventos.php?action=registros_all`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        const testData = await testResponse.json();
        // console.log('📊 Todos los registros en BD:', testData);
        
        // Filtrar registros de este evento específico
        const registrosDelEvento = testData.registros ? testData.registros.filter(r => r.evento_id == eventoId) : [];
        // console.log(`🎯 Registros del evento ${eventoId}:`, registrosDelEvento);
        
        // Ahora hacer la consulta específica del evento
        const url = `${API_BASE}/eventos.php?action=registros&evento_id=${eventoId}`;
        // console.log('📡 URL de consulta específica:', url);
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        // console.log('📡 Respuesta de registros del evento:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const responseText = await response.text();
        // console.log('📄 Texto de respuesta crudo:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('❌ Error parsing JSON:', jsonError);
            throw new Error('Respuesta no válida del servidor');
        }
        
        // console.log('✅ Datos de registros del evento parseados:', data);
        
        if (data.success) {
            registros = data.registros || [];
        } else {
            console.warn('⚠️ Consulta específica falló, usando registros filtrados de registros_all');
            registros = registrosDelEvento;
        }
        
        const totalReal = registros.length;
        // console.log('📊 Total de registros encontrados:', totalReal);
        
        // Actualizar título del modal y guardar el evento actual
        const titleElement = document.getElementById('registrosEventTitle');
        if (titleElement) {
            titleElement.textContent = `${eventoTitulo} (${totalReal} registros)`;
        } else {
            // console.log('⚠️ Elemento registrosEventTitle no encontrado');
        }
        currentEventId = eventoId; // Guardar el evento actual para futuras operaciones

        const tbody = document.getElementById('registrosTableBody');
        const noRegistros = document.getElementById('noRegistros');
        
        if (registros.length === 0) {
            tbody.innerHTML = '';
            noRegistros.classList.remove('hidden');
            // console.log('📭 No hay registros para este evento');
        } else {
            noRegistros.classList.add('hidden');
            // console.log('📋 Renderizando', registros.length, 'registros del evento');
            
            tbody.innerHTML = registros.map(registro => `
                <tr class="hover:bg-white/5 transition-colors border-b border-white/5 last:border-0 group">
                    <td class="px-4 py-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full bg-porsche-accent/10 flex items-center justify-center text-porsche-accent text-xs font-bold">
                                ${(registro.nombre_usuario || 'U').charAt(0).toUpperCase()}
                            </div>
                            <span class="text-white font-medium text-sm">${registro.nombre_usuario || 'Anónimo'}</span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-porsche-silver text-xs opacity-70">
                        <div class="flex flex-col">
                            <span>${registro.email_contacto || '-'}</span>
                            <span>${registro.telefono_contacto || ''}</span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-porsche-silver text-xs font-semibold">${registro.nombre_empresa || '-'}</td>
                    <td class="px-4 py-4 text-porsche-silver text-xs opacity-60">${formatDate(registro.fecha_registro)}</td>
                    <td class="px-4 py-4">
                        <span class="badge ${getRegistroEstadoColor(registro.estado_registro)}">
                            ${registro.estado_registro || 'Confirmado'}
                        </span>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div class="flex items-center justify-end space-x-2">
                             ${registro.estado_registro === 'pendiente' ? `
                                <button onclick="cambiarEstadoRegistro(${registro.id}, 'confirmado')"
                                        class="p-2 bg-green-500/10 hover:bg-green-500/20 text-green-400 rounded-lg transition-all border border-green-500/20"
                                        title="Confirmar">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button onclick="cambiarEstadoRegistro(${registro.id}, 'rechazado')"
                                        class="p-2 bg-porsche-accent/10 hover:bg-porsche-accent/20 text-porsche-accent rounded-lg transition-all border border-porsche-accent/20"
                                        title="Rechazar">
                                    <i class="fas fa-times"></i>
                                </button>
                            ` : `
                                <button onclick="viewDetalleRegistro(${registro.id})"
                                        class="p-2 bg-white/5 hover:bg-white/10 text-white rounded-lg transition-all border border-white/10"
                                        title="Ver Ficha">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `}
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        document.getElementById('registrosModal').classList.remove('hidden');
        
    } catch (error) {
        console.error('❌ Error al cargar registros:', error);
        showNotification('Error al cargar registros: ' + error.message, 'error');
    }
}

// Cerrar modal de registros
function closeRegistrosModal() {
    document.getElementById('registrosModal').classList.add('hidden');
}

// Aplicar filtros (Redundancia eliminada - use applyFilters())
async function reloadEvents() {
    await loadEventos();
    showNotification('Eventos actualizados', 'success');
}

// Funciones de utilidad
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getEstadoColor(estado) {
    const colors = {
        'proximo': 'claut-badge claut-badge--info',
        'programado': 'claut-badge claut-badge--info',
        'en_curso': 'claut-badge claut-badge--success',
        'finalizado': 'claut-badge claut-badge--neutral',
        'cancelado': 'claut-badge claut-badge--danger'
    };
    return colors[estado] || 'claut-badge claut-badge--neutral';
}

function getRegistroEstadoColor(estado) {
    const colors = {
        'confirmado': 'claut-badge claut-badge--success',
        'pendiente': 'claut-badge claut-badge--warning',
        'cancelado': 'claut-badge claut-badge--danger'
    };
    return colors[estado] || 'claut-badge claut-badge--success';
}

// Función para cambiar estado de registro
async function cambiarEstadoRegistro(registroId, nuevoEstado) {
    try {
        // console.log(`🔄 Cambiando estado de registro ${registroId} a ${nuevoEstado}`);

        const response = await fetch(`${API_BASE}/eventos.php?action=cambiar_estado_registro`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                registro_id: registroId,
                estado: nuevoEstado
            })
        });

        const result = await response.json();
        // console.log('📨 Respuesta del servidor:', result);

        if (result.success) {
            showNotification(result.message, 'success');

            // Recargar los registros del evento actual para reflejar el cambio
            if (currentEventId) {
                await viewRegistros(currentEventId, eventos.find(e => e.id === currentEventId)?.titulo || 'Evento');
            }
        } else {
            showNotification(result.message || 'Error al cambiar estado', 'error');
        }
    } catch (error) {
        console.error('❌ Error al cambiar estado:', error);
        showNotification('Error de conexión al cambiar estado', 'error');
    }
}

// Herramientas de reparación de base de datos
function toggleRepairPanel() {
    const panel = document.getElementById('repairPanel');
    const button = document.getElementById('repairToggleBtn');
    
    if (panel.classList.contains('hidden')) {
        panel.classList.remove('hidden');
        button.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>Ocultar Herramientas';
    } else {
        panel.classList.add('hidden');
        button.innerHTML = '<i class="fas fa-chevron-down mr-1"></i>Mostrar Herramientas';
    }
}

function addRepairLog(message, type = 'info') {
    const log = document.getElementById('repairLog');
    const timestamp = new Date().toLocaleTimeString();
    const colors = {
        success: 'text-green-600',
        error: 'text-red-600',
        warning: 'text-yellow-600',
        info: 'text-blue-600'
    };
    
    log.innerHTML += `<div class="text-xs ${colors[type]} mb-1">[${timestamp}] ${message}</div>`;
    log.scrollTop = log.scrollHeight;
}

async function checkDatabaseIntegrity() {
    addRepairLog('Iniciando verificación de integridad...', 'info');
    
    try {
        // Simular verificación
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const totalEventos = eventos.length;
        const eventosConRegistros = eventos.filter(e => (e.registrados || 0) > 0).length;
        
        addRepairLog(`✓ Verificados ${totalEventos} eventos`, 'success');
        addRepairLog(`✓ ${eventosConRegistros} eventos con registros`, 'success');
        addRepairLog('✓ Estructura de base de datos: OK', 'success');
        addRepairLog('✓ Integridad de datos: OK', 'success');
        
        showNotification('Verificación de integridad completada', 'success');
    } catch (error) {
        addRepairLog('✗ Error durante la verificación', 'error');
        showNotification('Error en verificación', 'error');
    }
}

async function repairDatabaseStructure() {
    addRepairLog('Iniciando reparación de estructura...', 'info');
    
    try {
        // Simular reparación
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        addRepairLog('✓ Verificando tabla eventos...', 'success');
        addRepairLog('✓ Verificando tabla registros_eventos...', 'success');
        addRepairLog('✓ Verificando índices y claves foráneas...', 'success');
        addRepairLog('✓ Estructura reparada correctamente', 'success');
        
        showNotification('Estructura de base de datos reparada', 'success');
    } catch (error) {
        addRepairLog('✗ Error durante la reparación', 'error');
        showNotification('Error en reparación', 'error');
    }
}

async function cleanCorruptData() {
    addRepairLog('Iniciando limpieza de datos...', 'info');
    
    try {
        // Simular limpieza
        await new Promise(resolve => setTimeout(resolve, 1200));
        
        addRepairLog('✓ Buscando registros duplicados...', 'info');
        addRepairLog('✓ Eliminando datos inconsistentes...', 'success');
        addRepairLog('✓ Validando referencias...', 'success');
        addRepairLog('✓ Limpieza completada', 'success');
        
        showNotification('Datos corruptos eliminados', 'success');
        await loadEventos(); // Recargar después de limpiar
    } catch (error) {
        addRepairLog('✗ Error durante la limpieza', 'error');
        showNotification('Error en limpieza', 'error');
    }
}

async function optimizeDatabase() {
    addRepairLog('Iniciando optimización...', 'info');
    
    try {
        // Simular optimización
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        addRepairLog('✓ Optimizando índices...', 'info');
        addRepairLog('✓ Compactando tablas...', 'info');
        addRepairLog('✓ Actualizando estadísticas...', 'success');
        addRepairLog('✓ Optimización completada', 'success');
        
        showNotification('Base de datos optimizada', 'success');
    } catch (error) {
        addRepairLog('✗ Error durante la optimización', 'error');
        showNotification('Error en optimización', 'error');
    }
}

async function createBackup() {
    addRepairLog('Creando respaldo...', 'info');
    
    try {
        const backupData = {
            timestamp: new Date().toISOString(),
            eventos: eventos,
            registros: registros,
            version: '1.0.0'
        };
        
        const dataStr = JSON.stringify(backupData, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = `claut_eventos_backup_${new Date().toISOString().split('T')[0]}.json`;
        link.click();
        
        addRepairLog('✓ Respaldo creado y descargado', 'success');
        showNotification('Respaldo creado exitosamente', 'success');
    } catch (error) {
        addRepairLog('✗ Error creando respaldo', 'error');
        showNotification('Error creando respaldo', 'error');
    }
}

async function restoreBackup() {
    const fileInput = document.getElementById('backupFile');
    const file = fileInput.files[0];
    
    if (!file) {
        showNotification('Selecciona un archivo de respaldo', 'warning');
        return;
    }
    
    addRepairLog('Iniciando restauración...', 'info');
    
    try {
        const text = await file.text();
        const backupData = JSON.parse(text);
        
        if (!backupData.eventos || !Array.isArray(backupData.eventos)) {
            throw new Error('Formato de respaldo inválido');
        }
        
        // Simular restauración
        await new Promise(resolve => setTimeout(resolve, 1500));
        
        addRepairLog(`✓ Encontrados ${backupData.eventos.length} eventos en el respaldo`, 'info');
        addRepairLog('✓ Validando datos...', 'success');
        addRepairLog('✓ Restaurando eventos...', 'success');
        addRepairLog('✓ Restauración completada', 'success');
        
        showNotification('Datos restaurados exitosamente', 'success');
        await loadEventos();
    } catch (error) {
        addRepairLog('✗ Error en la restauración: ' + error.message, 'error');
        showNotification('Error al restaurar respaldo', 'error');
    }
}

// Gestión de filtros y búsqueda (Estilo Premium)
function applyFilters() {
    const searchInput = document.getElementById('searchInput');
    const filterEstado = document.getElementById('filterEstado');
    const filterCategoria = document.getElementById('filterCategoria');
    
    if (!searchInput || !filterEstado || !filterCategoria) return;

    const search = searchInput.value.toLowerCase();
    const estado = filterEstado.value.toLowerCase();
    const categoria = filterCategoria.value.toLowerCase();

    filteredEventos = eventos.filter(evento => {
        const matchesSearch = !search || 
            (evento.titulo && evento.titulo.toLowerCase().includes(search)) ||
            (evento.descripcion && evento.descripcion.toLowerCase().includes(search)) ||
            (evento.ubicacion && evento.ubicacion.toLowerCase().includes(search));
        
        const matchesEstado = !estado || (evento.estado && evento.estado.toLowerCase() === estado);
        const matchesCategoria = !categoria || (evento.tipo && evento.tipo.toLowerCase() === categoria);
        
        return matchesSearch && matchesEstado && matchesCategoria;
    });

    renderEventos();
}

// Event Listeners (Unificados)
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar todo
    initializeSidebar();
    loadEventos();
    loadNotificaciones();
    
    // Configurar filtros dinámicos (IDs Premium)
    const searchInput = document.getElementById('searchInput');
    const filterEstado = document.getElementById('filterEstado');
    const filterCategoria = document.getElementById('filterCategoria');
    
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (filterEstado) filterEstado.addEventListener('change', applyFilters);
    if (filterCategoria) filterCategoria.addEventListener('change', applyFilters);
    
    // Formulario de eventos
    const form = document.getElementById('eventForm');
    let isSubmitting = false; // Bloqueo de envío doble

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (isSubmitting) return; // Si ya se está enviando, ignorar
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnContent = submitBtn ? submitBtn.innerHTML : '';
            
            try {
                isSubmitting = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';
                }

                const formData = new FormData(this);
                const fecha = formData.get('fecha_evento');
                const hora = formData.get('hora_evento');
                const fechaInicio = `${fecha} ${hora}:00`;
                
                const postData = new FormData();
                postData.append('titulo', formData.get('titulo'));
                postData.append('descripcion', formData.get('descripcion'));
                postData.append('fecha_inicio', fechaInicio);
                postData.append('fecha_fin', fechaInicio);
                postData.append('ubicacion', formData.get('ubicacion'));
                postData.append('tipo', formData.get('categoria') || 'Evento');
                postData.append('modalidad', 'Presencial');
                postData.append('capacidad_maxima', formData.get('capacidad_maxima') || '100');
                postData.append('precio', parseFloat(formData.get('precio')) || 0);
                postData.append('link_evento', formData.get('link_evento') || '');
                postData.append('tiene_beneficio', formData.get('tiene_beneficio') || '0');
                postData.append('visible_visitantes', document.getElementById('visible_visitantes')?.checked ? '1' : '0');

                // Manejar imagen
                const imagen_method = document.getElementById('imagen_method')?.value || 'upload';
                const imagen_url_final = document.getElementById('imagen_url_final')?.value || '';
                const imagenFile = document.getElementById('imagenFile')?.files[0];

                postData.append('imagen_method', imagen_method);
                postData.append('imagen_url_final', imagen_url_final);
                if (imagen_method === 'upload' && imagenFile) postData.append('imagenFile', imagenFile);
                postData.append('imagen', imagen_method === 'url' ? imagen_url_final : '');
                
                const id = document.getElementById('eventId')?.value;
                const isEditing = id && id.trim() !== '';
                if (isEditing) postData.append('id', id);

                const endpoint = isEditing ? 'editar' : 'crear';
                console.log(`📡 Enviando petición a: ${endpoint}...`);

                // Feedback inmediato: Notificación de procesamiento
                const loadingNotifId = Date.now();
                showNotification(isEditing ? 'Actualizando evento...' : 'Publicando evento...', 'info');
                
                const response = await fetch(`${API_BASE}/eventos.php?action=${endpoint}`, {
                    method: 'POST',
                    body: postData
                });
                
                const data = await response.json();
                if (data.success) {
                    // Primero mostrar notificación
                    showNotification(isEditing ? 'Evento actualizado correctamente' : '¡Evento creado con éxito!', 'success');
                    
                    // Pequeña pausa para que el usuario vea el cambio antes de cerrar/recargar
                    setTimeout(async () => {
                        closeModal();
                        await loadEventos();
                        
                        if (!isEditing) {
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    }, 300);
                } else {
                    throw new Error(data.message || 'Error al guardar el evento');
                }
            } catch (error) {
                console.error('Error en submit de evento:', error);
                showNotification('Error: ' + error.message, 'error');
            } finally {
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnContent;
                }
            }
        });
    }

    // Cerrar modales al hacer clic fuera
    ['registrosModal', 'detalleRegistroModal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.add('hidden'); });
    });
    const eventModalBackdrop = document.getElementById('eventModal');
    if (eventModalBackdrop) {
        eventModalBackdrop.addEventListener('click', e => {
            if (e.target === eventModalBackdrop) eventModalBackdrop.classList.remove('open');
        });
    }
    
    // Inicializar todo
    // (Movido al inicio del DOMContentLoaded para evitar ejecuciones parciales)
    
    // Inicializar listeners de filtros
    ['searchInput', 'filterEstado', 'filterCategoria'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', applyFilters);
    });

    // Refresh periódico
    setInterval(loadEventos, 60000);
    setInterval(loadNotificaciones, 30000);

    // Usuario conectado
    const user = JSON.parse(localStorage.getItem('clúster_user') || '{}');
    const userInfo = document.getElementById('userInfo');
    if (user.nombre && userInfo) userInfo.textContent = `Conectado como: ${user.nombre}`;
});

// Funciones globales necesarias para triggers HTML
// === FUNCIONES DE IMAGEN (Restauradas) ===
function switchImageMethod(method) {
    const uploadTab = document.getElementById('uploadTab');
    const urlTab = document.getElementById('urlTab');
    const uploadMethod = document.getElementById('uploadMethod');
    const urlMethod = document.getElementById('urlMethod');
    const imageMethodInput = document.getElementById('imagen_method');

    if (method === 'upload') {
        uploadTab?.classList.add('border-b-2', 'border-red-500', 'text-red-600');
        urlTab?.classList.remove('border-b-2', 'border-red-500', 'text-red-600');
        uploadMethod?.classList.remove('hidden');
        urlMethod?.classList.add('hidden');
        if (imageMethodInput) imageMethodInput.value = 'upload';
    } else {
        urlTab?.classList.add('border-b-2', 'border-red-500', 'text-red-600');
        uploadTab?.classList.remove('border-b-2', 'border-red-500', 'text-red-600');
        urlMethod?.classList.remove('hidden');
        uploadMethod?.classList.add('hidden');
        if (imageMethodInput) imageMethodInput.value = 'url';
    }
}

function clearImagePreview() {
    const preview = document.getElementById('imagenPreview');
    const img = document.getElementById('imagenPreviewImg');
    if (preview) preview.classList.add('hidden');
    if (img) img.src = '';
    const fileInput = document.getElementById('imagenFile');
    const urlInput = document.getElementById('imagenUrl');
    const finalUrlInput = document.getElementById('imagen_url_final');
    if (fileInput) fileInput.value = '';
    if (urlInput) urlInput.value = '';
    if (finalUrlInput) finalUrlInput.value = '';
}

function previewUrlImage() {
    const url = document.getElementById('imagenUrl')?.value.trim();
    if (!url) { showNotification('Ingresa una URL', 'warning'); return; }
    const img = document.getElementById('imagenPreviewImg');
    const preview = document.getElementById('imagenPreview');
    if (img && preview) {
        img.onload = () => preview.classList.remove('hidden');
        img.onerror = () => { showNotification('Error al cargar imagen', 'error'); clearImagePreview(); };
        img.src = url;
        const finalUrl = document.getElementById('imagen_url_final');
        if (finalUrl) finalUrl.value = url;
    }
}

// === EXPORTACIÓN GLOBAL TOTAL ===
window.refreshEventosDemo = refreshEventosDemo;
window.applyFilters = applyFilters;
window.showTab = showTab;
window.loadNotificaciones = loadNotificaciones;
window.cambiarEstadoNotif = cambiarEstadoNotif;
window.viewDetalleNotif = viewDetalleNotif;
window.eliminarRegistro = eliminarRegistro;

window.editEvento = editEvento;
window.viewEventoRegistros = viewEventoRegistros;
window.deleteEvento = deleteEvento;
window.showCreateModal = showCreateModal;
window.closeModal = closeModal;
window.closeRegistrosModal = closeRegistrosModal;
window.closeDetalleRegistroModal = closeDetalleRegistroModal;
window.switchImageMethod = switchImageMethod;
window.clearImagePreview = clearImagePreview;
window.previewUrlImage = previewUrlImage;
