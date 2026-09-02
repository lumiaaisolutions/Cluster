/**
 * EventsManager - Gestor de eventos con vista de tarjetas apiladas
 * Implementa el layout "Stacked Cards With Depth" y funcionalidad de registro
 *
 * Características:
 * - Tarjetas apiladas con efecto de profundidad
 * - Animaciones hover suaves
 * - Sistema de notificaciones toast
 * - Registro de eventos con validación
 * - Modal con iframe para ver eventos completos
 * - Responsive design
 */

class EventsManager {
    constructor() {
        this.eventos = [];
        this.currentUser = window.currentUser || this.getDefaultUser();

        this.init();
    }

    getDefaultUser() {
        // Datos de usuario por defecto - adaptar según sistema de autenticación real
        return {
            id: 1,
            nombre: 'Usuario Demo',
            email: 'usuario@claut.mx',
            empresa_id: null,
            empresa_nombre: 'CLAUT Metropolitano'
        };
    }


    async init() {
        console.log('🚀 Iniciando EventsManager...');

        // Cargar eventos desde API
        await this.loadEvents();

        // Configurar event listeners
        this.setupEventListeners();

        console.log('✅ EventsManager inicializado correctamente');
    }

    async loadEvents() {
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        const wrapper = document.getElementById('eventsStackWrapper');

        // Verificar que los elementos existen antes de usarlos
        if (!loadingState || !emptyState || !wrapper) {
            console.error('❌ Elementos del DOM no encontrados:', {
                loadingState: !!loadingState,
                emptyState: !!emptyState,
                wrapper: !!wrapper
            });
            return;
        }

        try {
            loadingState.classList.remove('hidden');
            emptyState.classList.add('hidden');

            console.log('🔄 Cargando eventos desde API...');
            const response = await fetch('./get_eventos.php');

            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
            }

            const responseText = await response.text();
            console.log('📦 Respuesta raw de API:', responseText.substring(0, 200) + '...');

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Error parseando JSON:', parseError);
                throw new Error('Respuesta inválida del servidor');
            }

            if (!data.success) {
                throw new Error(data.message || 'Error cargando eventos');
            }

            this.eventos = data.data.eventos || [];
            console.log(`📅 Cargados ${this.eventos.length} eventos desde la base de datos`);

            // Renderizar eventos
            this.renderStackedCards();

        } catch (error) {
            console.error('❌ Error cargando eventos:', error);
            this.showToast('Error cargando eventos: ' + error.message, 'error');

            // Mostrar estado vacío en caso de error
            if (emptyState) {
                emptyState.classList.remove('hidden');
            }
        } finally {
            if (loadingState) {
                loadingState.classList.add('hidden');
            }
        }
    }

    renderStackedCards() {
        const wrapper = document.getElementById('eventsStackWrapper');
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');

        // Verificar que los elementos existen
        if (!wrapper) {
            console.error('❌ eventsStackWrapper no encontrado en el DOM');
            return;
        }
        if (!loadingState) {
            console.error('❌ loadingState no encontrado en el DOM');
            return;
        }
        if (!emptyState) {
            console.error('❌ emptyState no encontrado en el DOM');
            return;
        }

        // Limpiar contenido existente
        wrapper.innerHTML = '';

        if (this.eventos.length === 0) {
            emptyState.classList.remove('hidden');
            loadingState.classList.add('hidden');
            return;
        }

        // Ocultar estados de carga y vacío
        loadingState.classList.add('hidden');
        emptyState.classList.add('hidden');

        // Crear contenedor de stack
        const stackContainer = document.createElement('div');
        stackContainer.className = 'stacked-cards-container relative';
        stackContainer.style.minHeight = '600px';

        this.eventos.forEach((evento, index) => {
            const card = this.createEventCard(evento, index);
            stackContainer.appendChild(card);
        });

        wrapper.appendChild(stackContainer);
    }

    createEventCard(evento, index) {
        const card = document.createElement('div');
        card.className = 'event-card w-full max-w-lg mx-auto bg-white rounded-2xl shadow-xl overflow-hidden transition-all duration-300 ease-out cursor-pointer';

        // HTML de la tarjeta
        card.innerHTML = `
            <!-- Imagen del evento -->
            <div class="relative h-48 bg-gradient-to-r from-red-500 to-red-600 overflow-hidden">
                <img src="${evento.imagen}"
                     alt="${evento.titulo}"
                     class="w-full h-full object-cover transition-transform duration-300 hover:scale-105"
                     onerror="this.src='./assets/img/placeholder.svg'">
                <div class="absolute inset-0 bg-black bg-opacity-20"></div>

                <!-- Badge de estado -->
                <div class="absolute top-3 right-3">
                    <span class="status-badge status-${evento.estado_calculado} px-3 py-1 text-xs font-semibold rounded-full text-white">
                        ${this.getStatusText(evento.estado_calculado)}
                    </span>
                </div>

                <!-- Badge de precio -->
                <div class="absolute bottom-3 left-3">
                    <span class="price-badge bg-white bg-opacity-90 px-3 py-1 text-sm font-bold rounded-full text-gray-800">
                        ${evento.precio_formateado}
                    </span>
                </div>
            </div>

            <!-- Contenido de la tarjeta -->
            <div class="p-6">
                <!-- Título -->
                <h3 class="text-xl font-bold text-gray-900 mb-2 line-clamp-2">
                    ${evento.titulo}
                </h3>

                <!-- Descripción -->
                <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                    ${evento.descripcion_corta}
                </p>

                <!-- Información del evento -->
                <div class="space-y-2 mb-6">
                    <!-- Fecha -->
                    <div class="flex items-center text-sm text-gray-500">
                        <i class="fas fa-calendar-alt mr-2" style="color: #C7252B;"></i>
                        <span>${evento.fecha_corta}</span>
                    </div>

                    <!-- Ubicación -->
                    <div class="flex items-center text-sm text-gray-500">
                        <i class="fas fa-map-marker-alt mr-2" style="color: #C7252B;"></i>
                        <span class="line-clamp-1">${evento.ubicacion}</span>
                    </div>

                    <!-- Capacidad -->
                    <div class="flex items-center text-sm text-gray-500">
                        <i class="fas fa-users mr-2" style="color: #C7252B;"></i>
                        <span>${evento.capacidad_actual}/${evento.capacidad_maxima} registrados</span>
                        <div class="ml-2 flex-1 bg-gray-200 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full transition-all duration-300"
                                 style="width: ${evento.porcentaje_ocupacion}%; background-color: #C7252B;"></div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="flex space-x-3">
                    <button onclick="window.eventsManager.showRegisterConfirmation(${evento.id})"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg font-semibold transition-colors duration-200 text-sm"
                            style="background-color: #C7252B; border-color: #C7252B;">
                        <i class="fas fa-user-plus mr-1"></i>Registrarse
                    </button>

                    <button onclick="window.eventsManager.showEventDetails(${evento.id}, '${evento.titulo}')"
                            class="flex-1 border-2 border-gray-300 hover:border-red-500 text-gray-700 hover:text-red-600 py-2 px-4 rounded-lg font-semibold transition-colors duration-200 text-sm">
                        <i class="fas fa-eye mr-1"></i>Ver más
                    </button>
                </div>
            </div>
        `;

        return card;
    }

    getStatusText(status) {
        const statusTexts = {
            'proximo': 'Próximo',
            'en_curso': 'En Curso',
            'finalizado': 'Finalizado',
            'cancelado': 'Cancelado'
        };
        return statusTexts[status] || status;
    }

    showRegisterConfirmation(eventoId) {
        const evento = this.eventos.find(e => e.id === eventoId);

        if (!evento) {
            this.showToast('Evento no encontrado', 'error');
            return;
        }

        // Verificar si hay cupo disponible
        if (evento.capacidad_actual >= evento.capacidad_maxima) {
            this.showToast('Cupo agotado para este evento', 'warning');
            return;
        }

        // Crear modal de confirmación
        const confirmModal = this.createConfirmationModal(evento);
        document.body.appendChild(confirmModal);

        // Mostrar modal
        setTimeout(() => {
            confirmModal.classList.remove('opacity-0');
            confirmModal.classList.add('opacity-100');
        }, 10);
    }

    createConfirmationModal(evento) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 transition-opacity duration-300';
        modal.style.backdropFilter = 'blur(5px)';

        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-transform duration-300 scale-95">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-xl" style="color: #C7252B;"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">Registrarse al Evento</h3>
                            <p class="text-sm text-gray-500">¿Confirmas tu registro?</p>
                        </div>
                    </div>

                    <!-- Información del evento -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-gray-900 mb-2">${evento.titulo}</h4>
                        <div class="space-y-1 text-sm text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-calendar mr-2 w-4"></i>
                                <span>${evento.fecha_formateada}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-map-marker-alt mr-2 w-4"></i>
                                <span>${evento.ubicacion}</span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-dollar-sign mr-2 w-4"></i>
                                <span>${evento.precio_formateado}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario -->
                    <form id="registrationForm">
                        <div class="space-y-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                                <input type="text" id="regNombre" value="${this.currentUser.nombre}" required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" id="regEmail" value="${this.currentUser.email}" required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input type="tel" id="regTelefono"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                                <input type="text" id="regEmpresa" value="${this.currentUser.empresa_nombre || ''}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Comentarios (opcional)</label>
                                <textarea id="regComentarios" rows="2"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="flex space-x-3">
                            <button type="button" onclick="this.closest('.fixed').remove()"
                                    class="flex-1 border border-gray-300 text-gray-700 py-3 px-4 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="flex-1 text-white py-3 px-4 rounded-lg font-semibold hover:opacity-90 transition-all"
                                    style="background-color: #C7252B;">
                                <i class="fas fa-check mr-2"></i>Confirmar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        // Configurar evento del formulario
        const form = modal.querySelector('#registrationForm');
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.processRegistration(evento.id, modal);
        });

        // Animar entrada
        setTimeout(() => {
            modal.querySelector('.bg-white').classList.remove('scale-95');
            modal.querySelector('.bg-white').classList.add('scale-100');
        }, 10);

        return modal;
    }

    async processRegistration(eventoId, modal) {
        const submitBtn = modal.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        try {
            // Cambiar botón a estado de carga
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Registrando...';
            submitBtn.disabled = true;

            // Recopilar datos del formulario
            const formData = {
                evento_id: eventoId,
                usuario_id: this.currentUser.id,
                empresa_id: this.currentUser.empresa_id,
                nombre_usuario: modal.querySelector('#regNombre').value.trim(),
                email_contacto: modal.querySelector('#regEmail').value.trim(),
                telefono_contacto: modal.querySelector('#regTelefono').value.trim(),
                nombre_empresa: modal.querySelector('#regEmpresa').value.trim(),
                comentarios: modal.querySelector('#regComentarios').value.trim()
            };

            // Validar campos requeridos
            if (!formData.nombre_usuario || !formData.email_contacto) {
                throw new Error('Nombre y email son requeridos');
            }

            // Enviar registro
            const response = await fetch('./register_evento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            // Procesar respuesta
            if (result.status === 'ok') {
                this.showToast('¡Registro exitoso! Te has registrado al evento.', 'success');

                // Actualizar evento local
                const evento = this.eventos.find(e => e.id === eventoId);
                if (evento) {
                    evento.capacidad_actual += 1;
                    evento.porcentaje_ocupacion = (evento.capacidad_actual / evento.capacidad_maxima) * 100;
                }

                // Re-renderizar tarjetas
                this.renderStackedCards();

            } else if (result.status === 'exists') {
                this.showToast('Ya estás registrado a este evento', 'warning');
            } else if (result.status === 'full') {
                this.showToast('Cupo agotado para este evento', 'warning');
            } else {
                throw new Error(result.message || 'Error en el registro');
            }

            // Cerrar modal
            modal.remove();

        } catch (error) {
            console.error('❌ Error en registro:', error);
            this.showToast('Error al registrarse: ' + error.message, 'error');

            // Restaurar botón
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    showEventDetails(eventoId, eventoTitulo) {
        // Usar la función global definida en eventos.html
        if (typeof openEventModal === 'function') {
            openEventModal(eventoId, eventoTitulo);
        } else {
            // Fallback si la función no está disponible
            const url = `demo_evento.html?id=${eventoId}`;
            window.open(url, '_blank');
        }
    }

    setupEventListeners() {
        // Configurar eventos globales si son necesarios
        console.log('📡 Event listeners configurados');

        // Configurar efecto de separación de tarjetas al hacer scroll
        this.setupScrollEffect();
    }

    setupScrollEffect() {
        let ticking = false;

        const handleScroll = () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    this.updateCardsOnScroll();
                    ticking = false;
                });
                ticking = true;
            }
        };

        window.addEventListener('scroll', handleScroll);
    }

    updateCardsOnScroll() {
        const scrollY = window.scrollY;
        const cards = document.querySelectorAll('.event-card');

        cards.forEach((card, index) => {
            const cardTop = card.offsetTop;
            const threshold = cardTop - window.innerHeight * 0.7;

            if (scrollY > threshold) {
                // Separar la tarjeta cuando se hace scroll
                card.classList.add('separated');
            } else {
                // Mantener apilada cuando está arriba
                card.classList.remove('separated');
            }
        });
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) {
            console.warn('Toast container no encontrado');
            return;
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type} max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden transform transition-all duration-300 translate-x-full`;

        const colors = {
            success: { bg: 'bg-green-50', icon: 'text-green-400', iconClass: 'fa-check-circle' },
            error: { bg: 'bg-red-50', icon: 'text-red-400', iconClass: 'fa-exclamation-circle' },
            warning: { bg: 'bg-yellow-50', icon: 'text-yellow-400', iconClass: 'fa-exclamation-triangle' },
            info: { bg: 'bg-blue-50', icon: 'text-blue-400', iconClass: 'fa-info-circle' }
        };

        const color = colors[type] || colors.info;

        toast.innerHTML = `
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas ${color.iconClass} ${color.icon} text-xl"></i>
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-medium text-gray-900">${message}</p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none"
                                onclick="this.closest('.toast').remove()">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        container.appendChild(toast);

        // Animar entrada
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
            toast.classList.add('translate-x-0');
        }, 10);

        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (toast.parentNode) {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }
        }, 5000);
    }

    // Método para refrescar eventos
    async refreshEvents() {
        console.log('🔄 Refrescando eventos...');
        await this.loadEvents();
    }
}

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Evitar inicialización múltiple
    if (window.eventsManager) {
        console.log('⚠️ EventsManager ya está inicializado, omitiendo...');
        return;
    }

    console.log('🎯 Iniciando EventsManager...');
    window.eventsManager = new EventsManager();
});

// Exponer globalmente para uso en HTML
window.EventsManager = EventsManager;