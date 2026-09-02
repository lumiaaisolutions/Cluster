/**
 * Frontend JavaScript para descuentos.html
 * Maneja la carga dinámica de descuentos desde la base de datos
 */

document.addEventListener('DOMContentLoaded', function () {
    loadDescuentos();
});

// Función principal para cargar descuentos
async function loadDescuentos() {
    try {
        // Desde /build/ necesitamos usar ../api/
        const response = await fetch('../api/descuentos.php?estado=vigente');
        const data = await response.json();

        if (data.success) {
            renderDescuentos(data.data);
            updateStats(data.stats);
        } else {
            console.error('Error al cargar descuentos:', data.message);
            showError('Error al cargar descuentos');
        }
    } catch (error) {
        console.error('Error de conexión:', error);
        showError('Error de conexión con el servidor');
    }
}

// Renderizar descuentos en tarjetas
function renderDescuentos(descuentos) {
    // Buscar el contenedor de la línea de descuentos para animación
    const container = document.getElementById('discountLine');

    if (!descuentos || descuentos.length === 0) {
        container.innerHTML = `
            <div class="discount-card-wrapper">
                <div class="text-center py-12 bg-white rounded-lg shadow">
                    <i class="fas fa-tags text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">No hay descuentos disponibles</h3>
                    <p class="text-gray-600">Pronto tendremos nuevas ofertas disponibles</p>
                </div>
            </div>
        `;
        return;
    }

    container.innerHTML = descuentos.map(descuento => {
        const porcentaje = descuento.porcentaje_descuento || 0;
        const monto = descuento.monto_descuento || 0;
        const diasRestantes = Math.ceil((new Date(descuento.fecha_fin) - new Date()) / (1000 * 60 * 60 * 24));

        let descuentoTexto = '';
        if (porcentaje > 0) {
            descuentoTexto = `${porcentaje}% de descuento`;
        } else if (monto > 0) {
            descuentoTexto = `$${monto.toLocaleString()} de descuento`;
        }

        let porcentajeUso = '';
        if (descuento.usos_maximos) {
            const porcentajeCalc = ((descuento.usos_actuales || 0) / descuento.usos_maximos) * 100;
            porcentajeUso = `
                <div class="mt-3">
                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                        <span>Usos: ${descuento.usos_actuales || 0}/${descuento.usos_maximos}</span>
                        <span>${porcentajeCalc.toFixed(1)}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-clúster-red h-2 rounded-full transition-all duration-300" style="width: ${porcentajeCalc}%"></div>
                    </div>
                </div>
            `;
        }

        return `
            <div class="discount-card-wrapper">
                <div class="porsche-card descuento-card relative overflow-hidden transition-all duration-300 hover:scale-105 cursor-pointer group"
                     onclick="mostrarDescuentoDetalle(${descuento.id})">
                <!-- Lado frontal -->
                <div class="descuento-front p-6 transition-all duration-500">
                    <!-- Icono de descuento -->
                    <div class="flex items-center justify-center mb-4 h-16">
                        <div class="w-16 h-16 bg-clúster-red rounded-full flex items-center justify-center">
                            <i class="fas fa-tag text-white text-2xl"></i>
                        </div>
                    </div>

                    <!-- Información principal -->
                    <div class="text-center">
                        <h3 class="font-bold text-lg text-gray-800 mb-2" style="word-wrap: break-word; overflow-wrap: break-word;">${descuento.titulo}</h3>
                        <p class="text-clúster-red font-semibold text-xl mb-2">${descuentoTexto}</p>
                        <p class="text-sm text-gray-600 mb-3">${descuento.empresa_nombre}</p>

                        ${descuento.codigo_descuento ?
                `<div class="bg-gray-100 rounded-lg px-3 py-2 mb-3">
                                <span class="text-xs text-gray-600">Código:</span>
                                <span class="font-mono font-bold text-clúster-red">${descuento.codigo_descuento}</span>
                            </div>` : ''
            }

                        <!-- Vigencia -->
                        <div class="flex items-center justify-center text-xs text-gray-500 mb-3">
                            <i class="fas fa-clock mr-1"></i>
                            <span>Vence en ${diasRestantes} días</span>
                        </div>

                        ${porcentajeUso}
                    </div>

                    <!-- Indicador de hover -->
                    <div class="absolute bottom-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                        <i class="fas fa-info-circle text-clúster-red"></i>
                    </div>
                </div>

                <!-- Lado posterior (se muestra en hover) -->
                <div class="descuento-back absolute inset-0 p-6 bg-white transform rotateY-180 opacity-0 transition-all duration-500 group-hover:opacity-100 group-hover:rotateY-0">
                    <div class="flex flex-col justify-center min-h-full">
                        <h4 class="font-bold text-lg text-gray-800 mb-3 text-center">${descuento.empresa_nombre}</h4>
                        <p class="text-sm text-gray-700 mb-4 text-center line-clamp-none overflow-visible" style="min-height: auto; max-height: none; line-height: 1.5;">${descuento.descripcion || 'Descuento especial para empleados de Clúster'}</p>

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Sector:</span>
                                <span class="font-medium">${descuento.sector || 'General'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Vigencia:</span>
                                <span class="font-medium">${new Date(descuento.fecha_fin).toLocaleDateString('es-MX')}</span>
                            </div>
                            ${descuento.telefono ?
                `<div class="flex justify-between">
                                    <span class="text-gray-600">Teléfono:</span>
                                    <span class="font-medium">${descuento.telefono}</span>
                                </div>` : ''
            }
                        </div>

                        <div class="mt-4 text-center">
                            ${renderBotonAccion(descuento)}
                        </div>
                    </div>
                </div>
                </div>
            </div>
        `;
    }).join('');

    // NOTA: se desactivó la marquesina infinita (duplicateDiscountCardsForInfiniteEffect +
    // DiscountStreamController). Movía los códigos de descuento en scroll continuo,
    // haciéndolos difíciles de leer/copiar, y su cálculo de ancho (cardWidth fijo de
    // 350px) entraba en conflicto con el ancho responsive real de las tarjetas
    // (clamp() en CSS), dejando el carrusel descentrado y roto en móvil.
    // La fila ahora es estática: centrada cuando cabe, con scroll horizontal nativo
    // cuando no (ver reglas .discount-stream / .discount-line en el <style>).
}

// Mostrar detalle del descuento
function mostrarDescuentoDetalle(id) {
    // console.log('Mostrar detalle del descuento:', id);
}

// Función de test para debugging
function testUsuarioEstado() {
    // console.log('🔍 === ESTADO COMPLETO DEL USUARIO ===');
    // console.log('window.currentUser:', window.currentUser);
    // console.log('localStorage.userData:', localStorage.getItem('userData'));
    // console.log('localStorage.currentUser:', localStorage.getItem('currentUser'));
    // console.log('sessionStorage.userSession:', sessionStorage.getItem('userSession'));
    // console.log('obtenerUsuarioId() result:', obtenerUsuarioId());
    // console.log('=== FIN ESTADO USUARIO ===');
}

// Hacer función disponible globalmente para testing
window.testUsuarioEstado = testUsuarioEstado;

// Renderizar el botón de acción configurable por descuento
function renderBotonAccion(descuento) {
    const tipo = descuento.accion_tipo || 'ninguno';
    const valor = descuento.accion_valor || '';
    const etiqueta = descuento.accion_etiqueta;

    // Si no tiene acción configurada, mostrar botón genérico de "Usar Descuento"
    if (tipo === 'ninguno' || !tipo) {
        return `<button class="bg-clúster-red text-white px-4 py-2 rounded-lg hover:bg-clúster-red-dark transition-colors text-sm"
                        onclick="usarDescuento(${descuento.id}); event.stopPropagation();">
                    <i class="fas fa-tag mr-1"></i> Usar Descuento
                </button>`;
    }

    // Mapeo de tipo → { icono, etiqueta por defecto, color, acción JS }
    const config = {
        link: {
            icon: 'fas fa-external-link-alt',
            label: etiqueta || 'Visitar sitio',
            color: 'bg-blue-600 hover:bg-blue-700',
            action: valor ? `window.open(${JSON.stringify(valor)}, '_blank'); event.stopPropagation();` : null
        },
        telefono: {
            icon: 'fas fa-phone',
            label: etiqueta || 'Llamar ahora',
            color: 'bg-green-600 hover:bg-green-700',
            action: valor ? `window.location.href='tel:${valor}'; event.stopPropagation();` : null
        },
        email: {
            icon: 'fas fa-envelope',
            label: etiqueta || 'Enviar email',
            color: 'bg-purple-600 hover:bg-purple-700',
            action: valor ? `window.location.href='mailto:${valor}'; event.stopPropagation();` : null
        },
        whatsapp: {
            icon: 'fab fa-whatsapp',
            label: etiqueta || 'WhatsApp',
            color: 'bg-green-500 hover:bg-green-600',
            action: valor ? `window.open('https://wa.me/${valor.replace(/[^0-9]/g, '')}', '_blank'); event.stopPropagation();` : null
        },
        mapa: {
            icon: 'fas fa-map-marker-alt',
            label: etiqueta || 'Ver en mapa',
            color: 'bg-orange-500 hover:bg-orange-600',
            action: valor ? `window.open('https://maps.google.com/?q=${encodeURIComponent(valor)}', '_blank'); event.stopPropagation();` : null
        }
    };

    const cfg = config[tipo];
    if (!cfg || !cfg.action) {
        // Si no hay valor configurado, renderiza botón deshabilitado
        return `<button class="bg-gray-400 text-white px-4 py-2 rounded-lg text-sm cursor-not-allowed" disabled>
                    <i class="${cfg ? cfg.icon : 'fas fa-tag'} mr-1"></i> ${cfg ? cfg.label : tipo}
                </button>`;
    }

    return `<button class="${cfg.color} text-white px-4 py-2 rounded-lg transition-colors text-sm"
                    onclick="${cfg.action}">
                <i class="${cfg.icon} mr-1"></i> ${cfg.label}
            </button>`;
}

// Usar descuento (registrar uso)
async function usarDescuento(id) {
    try {
        // console.log('🎯 === INICIANDO USO DE DESCUENTO ===');
        // console.log('ID del descuento:', id);

        // Debug completo del estado del usuario
        testUsuarioEstado();

        // Obtener información del usuario actual
        const usuarioId = obtenerUsuarioId();
        // console.log('🔍 Usuario ID obtenido:', usuarioId);

        // Simplificar: si hay algún signo de usuario logueado, usar ID temporal
        const tieneUsuario = window.currentUser ||
            localStorage.getItem('userData') ||
            localStorage.getItem('currentUser') ||
            sessionStorage.getItem('userSession') ||
            document.getElementById('userDisplayName')?.textContent !== 'Usuario';

        // console.log('🔍 Tiene usuario logueado:', tieneUsuario);

        if (!usuarioId && !tieneUsuario) {
            console.error('❌ No hay usuario logueado');
            showErrorMessage('Debes iniciar sesión para usar descuentos');
            return;
        }

        // Usar ID obtenido o ID temporal
        const usuarioIdFinal = usuarioId || 1;
        // console.log('🔧 Usuario ID final a usar:', usuarioIdFinal);

        if (!usuarioId) {
            // console.log('⚠️ Usuario logueado pero sin ID - usando ID temporal 1');

        }

        // Registrar el uso del descuento con ID final (real o temporal)
        const response = await fetch('../api/descuentos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'usar_descuento',
                descuento_id: id,
                usuario_id: usuarioIdFinal
            })
        });

        const data = await response.json();

        if (data.success) {
            showSuccessMessage(`
                <strong>¡Descuento registrado exitosamente!</strong><br>
                ${data.data.codigo ? `Código: <strong>${data.data.codigo}</strong><br>` : ''}
                Presenta tu credencial de empleado en ${data.data.empresa}
            `);

            setTimeout(() => {
                loadDescuentos();
            }, 2000);
        } else {
            showErrorMessage(data.message);
        }
    } catch (error) {
        console.error('Error al usar descuento:', error);
        showErrorMessage('Error al procesar el descuento');
    }
}

// Obtener ID del usuario actual - versión simplificada que usa los datos del navbar
function obtenerUsuarioId() {
    // console.log('🔍 Obteniendo ID de usuario...');

    // Verificar si hay usuario en el navbar (mismo que está funcionando)
    const userDisplayName = document.getElementById('userDisplayName')?.textContent;
    const dropdownUserName = document.getElementById('dropdownUserName')?.textContent;

    // console.log('📊 Datos del navbar:');
    // console.log('  - userDisplayName:', userDisplayName);
    // console.log('  - dropdownUserName:', dropdownUserName);

    // Si hay usuario en navbar, usar ID basado en el nombre
    if (userDisplayName && userDisplayName !== 'Usuario') {
        const tempId = Math.abs(userDisplayName.split('').reduce((a, b) => {
            a = ((a << 5) - a) + b.charCodeAt(0);
            return a & a;
        }, 0)) % 10000 + 1;
        // console.log(`✅ ID generado desde navbar: ${tempId} para "${userDisplayName}"`);
        return tempId;
    }

    // Verificar window.currentUser (puede estar disponible)
    if (window.currentUser) {
        // console.log('📊 window.currentUser disponible:', window.currentUser);
        if (window.currentUser.id) {
            // console.log('✅ ID encontrado en window.currentUser.id:', window.currentUser.id);
            return window.currentUser.id;
        }
        if (window.currentUser.user_id) {
            // console.log('✅ ID encontrado en window.currentUser.user_id:', window.currentUser.user_id);
            return window.currentUser.user_id;
        }
        if (window.currentUser.nombre || window.currentUser.email) {
            const identifier = window.currentUser.nombre || window.currentUser.email;
            const tempId = Math.abs(identifier.split('').reduce((a, b) => {
                a = ((a << 5) - a) + b.charCodeAt(0);
                return a & a;
            }, 0)) % 10000 + 1;
            // console.log(`✅ ID generado desde window.currentUser: ${tempId} para "${identifier}"`);
            return tempId;
        }
    }

    // Verificar storage como fallback
    const sessionSources = [
        localStorage.getItem('userData'),
        localStorage.getItem('currentUser'),
        sessionStorage.getItem('userSession'),
        localStorage.getItem('userInfo'),
        sessionStorage.getItem('loginData')
    ];

    for (let i = 0; i < sessionSources.length; i++) {
        const source = sessionSources[i];
        if (source) {
            try {
                const userData = JSON.parse(source);
                // console.log(`📊 Datos de fuente ${i}:`, userData);

                if (userData && (userData.id || userData.user_id)) {
                    const foundId = userData.id || userData.user_id;
                    // console.log(`✅ ID real encontrado en fuente ${i}:`, foundId);
                    return foundId;
                }

                if (userData && (userData.nombre || userData.email)) {
                    const identifier = userData.nombre || userData.email;
                    const tempId = Math.abs(identifier.split('').reduce((a, b) => {
                        a = ((a << 5) - a) + b.charCodeAt(0);
                        return a & a;
                    }, 0)) % 10000 + 1;
                    // console.log(`✅ ID generado desde storage: ${tempId} para "${identifier}"`);
                    return tempId;
                }
            } catch (e) {
                console.warn(`⚠️ Error parseando fuente ${i}:`, e);
                continue;
            }
        }
    }

    console.warn('❌ No se pudo obtener ID de usuario desde ninguna fuente');
    return null;
}

// Actualizar estadísticas
function updateStats(stats) {
    if (!stats) return;

    const statsArea = document.querySelector('.stats-area');
    if (statsArea) {
        statsArea.innerHTML = `
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-2xl font-bold text-clúster-red">${stats.total || 0}</div>
                    <div class="text-sm text-gray-600">Total Descuentos</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-600">${stats.activos || 0}</div>
                    <div class="text-sm text-gray-600">Activos</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-600">${stats.empresas_participantes || 0}</div>
                    <div class="text-sm text-gray-600">Empresas</div>
                </div>
            </div>
        `;
    }
}

// Mostrar mensaje de error
function showError(message) {
    console.error(message);

    const container = document.querySelector('.grid.grid-cols-1');
    container.innerHTML = `
        <div class="col-span-full text-center py-12">
            <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
            <h3 class="text-lg font-medium text-white mb-2">Error al cargar descuentos</h3>
            <p class="text-gray-300 mb-4">${message}</p>
            <button onclick="loadDescuentos()" class="bg-clúster-red text-white px-4 py-2 rounded-lg hover:bg-clúster-red-dark transition-colors">
                <i class="fas fa-refresh mr-2"></i>
                Reintentar
            </button>
        </div>
    `;
}

// Mostrar mensaje de éxito
function showSuccessMessage(message) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md mx-4">
            <div class="text-center">
                <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                <div class="text-gray-800">${message}</div>
                <button onclick="this.closest('.fixed').remove()"
                        class="mt-4 bg-clúster-red text-white px-4 py-2 rounded-lg hover:bg-clúster-red-dark transition-colors">
                    Cerrar
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        if (modal.parentNode) {
            modal.remove();
        }
    }, 5000);
}

// Mostrar mensaje de error
function showErrorMessage(message) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md mx-4">
            <div class="text-center">
                <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4"></i>
                <div class="text-gray-800">${message}</div>
                <button onclick="this.closest('.fixed').remove()"
                        class="mt-4 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                    Cerrar
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    setTimeout(() => {
        if (modal.parentNode) {
            modal.remove();
        }
    }, 8000);
}

// ============= SISTEMA DE ANIMACIÓN HORIZONTAL =============

// Función para duplicar tarjetas para efecto infinito
function duplicateDiscountCardsForInfiniteEffect() {
    const container = document.getElementById('discountLine');
    if (!container) return;

    const cards = Array.from(container.children);

    // Duplicar las tarjetas para efecto de loop infinito
    const repetitions = Math.max(3, Math.ceil(30 / cards.length));
    for (let i = 0; i < repetitions; i++) {
        cards.forEach(card => {
            const clone = card.cloneNode(true);
            container.appendChild(clone);
        });
    }
}

// Controlador principal del stream de descuentos
class DiscountStreamController {
    constructor() {
        this.container = document.getElementById("discountStream");
        this.cardLine = document.getElementById("discountLine");
        this.speedIndicator = document.getElementById("discountSpeedValue");

        this.position = 0;
        this.velocity = 200;
        this.direction = -1;
        this.isAnimating = true;
        this.isDragging = false;

        this.lastTime = 0;
        this.lastMouseX = 0;
        this.mouseVelocity = 0;
        this.friction = 0.95;
        this.minVelocity = 30;

        this.containerWidth = 0;
        this.cardLineWidth = 0;

        this.init();
    }

    init() {
        this.calculateDimensions();
        this.setupEventListeners();
        this.updateCardPosition();
        this.animate();
    }

    calculateDimensions() {
        this.containerWidth = this.container.offsetWidth;
        const cardWidth = 350;
        const cardGap = 32;
        const cardCount = this.cardLine.children.length;
        this.cardLineWidth = (cardWidth + cardGap) * cardCount;
    }

    setupEventListeners() {
        this.cardLine.addEventListener("mousedown", (e) => this.startDrag(e));
        document.addEventListener("mousemove", (e) => this.onDrag(e));
        document.addEventListener("mouseup", () => this.endDrag());

        this.cardLine.addEventListener(
            "touchstart",
            (e) => this.startDrag(e.touches[0]),
            { passive: false }
        );
        document.addEventListener("touchmove", (e) => this.onDrag(e.touches[0]), {
            passive: false,
        });
        document.addEventListener("touchend", () => this.endDrag());

        this.cardLine.addEventListener("wheel", (e) => this.onWheel(e));
        this.cardLine.addEventListener("selectstart", (e) => e.preventDefault());
        this.cardLine.addEventListener("dragstart", (e) => e.preventDefault());

        window.addEventListener("resize", () => this.calculateDimensions());
    }

    startDrag(e) {
        e.preventDefault();

        this.isDragging = true;
        this.isAnimating = false;
        this.lastMouseX = e.clientX;
        this.mouseVelocity = 0;

        const transform = window.getComputedStyle(this.cardLine).transform;
        if (transform !== "none") {
            const matrix = new DOMMatrix(transform);
            this.position = matrix.m41;
        }

        this.cardLine.classList.add("dragging");
        document.body.style.userSelect = "none";
        document.body.style.cursor = "grabbing";
    }

    onDrag(e) {
        if (!this.isDragging) return;
        e.preventDefault();

        const deltaX = e.clientX - this.lastMouseX;
        this.position += deltaX;
        this.mouseVelocity = deltaX * 60;
        this.lastMouseX = e.clientX;

        this.cardLine.style.transform = `translateX(${this.position}px)`;
    }

    endDrag() {
        if (!this.isDragging) return;

        this.isDragging = false;
        this.cardLine.classList.remove("dragging");

        if (Math.abs(this.mouseVelocity) > this.minVelocity) {
            this.velocity = Math.abs(this.mouseVelocity);
            this.direction = this.mouseVelocity > 0 ? 1 : -1;
        } else {
            this.velocity = 120;
        }

        this.isAnimating = true;
        this.updateSpeedIndicator();

        document.body.style.userSelect = "";
        document.body.style.cursor = "";
    }

    animate() {
        const currentTime = performance.now();
        const deltaTime = (currentTime - this.lastTime) / 1000;
        this.lastTime = currentTime;

        if (this.isAnimating && !this.isDragging) {
            if (this.velocity > this.minVelocity) {
                this.velocity *= this.friction;
            } else {
                this.velocity = Math.max(this.minVelocity, this.velocity);
            }

            this.position += this.velocity * this.direction * deltaTime;
            this.updateCardPosition();
            this.updateSpeedIndicator();
        }

        requestAnimationFrame(() => this.animate());
    }

    updateCardPosition() {
        const containerWidth = this.containerWidth;
        const cardLineWidth = this.cardLineWidth;

        if (this.position < -cardLineWidth) {
            this.position = containerWidth;
        } else if (this.position > containerWidth) {
            this.position = -cardLineWidth;
        }

        this.cardLine.style.transform = `translateX(${this.position}px)`;
    }

    updateSpeedIndicator() {
        if (this.speedIndicator) {
            this.speedIndicator.textContent = Math.round(this.velocity);
        }
    }

    toggleAnimation() {
        this.isAnimating = !this.isAnimating;
        const btn = document.querySelector(".animation-controls .control-btn");
        if (btn) {
            btn.textContent = this.isAnimating ? "⏸️ Pausa" : "▶️ Play";
        }
    }

    resetPosition() {
        this.position = this.containerWidth;
        this.velocity = 120;
        this.direction = -1;
        this.isAnimating = true;
        this.isDragging = false;

        this.cardLine.classList.remove("dragging");
        this.cardLine.style.transform = `translateX(${this.position}px)`;
        this.updateSpeedIndicator();

        const btn = document.querySelector(".animation-controls .control-btn");
        if (btn) {
            btn.textContent = "⏸️ Pausa";
        }
    }

    changeDirection() {
        this.direction *= -1;
        this.updateSpeedIndicator();
    }

    onWheel(e) {
        e.preventDefault();

        const scrollSpeed = 20;
        const delta = e.deltaY > 0 ? scrollSpeed : -scrollSpeed;

        this.position += delta;
        this.updateCardPosition();
    }

    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
    }
}

// Sistema de partículas simplificado para descuentos
class DiscountParticleSystem {
    constructor() {
        this.canvas = document.getElementById("discountParticleCanvas");
        if (!this.canvas) return;

        this.ctx = this.canvas.getContext("2d");
        this.particles = [];
        this.maxParticles = 100;

        this.setupCanvas();
        this.createParticles();
        this.animate();
    }

    setupCanvas() {
        const container = this.canvas.parentElement;
        this.canvas.width = container.offsetWidth;
        this.canvas.height = container.offsetHeight;
        this.canvas.style.width = container.offsetWidth + "px";
        this.canvas.style.height = container.offsetHeight + "px";
    }

    createParticles() {
        for (let i = 0; i < this.maxParticles; i++) {
            this.particles.push({
                x: Math.random() * this.canvas.width,
                y: Math.random() * this.canvas.height,
                vx: (Math.random() - 0.5) * 2,
                vy: (Math.random() - 0.5) * 0.5,
                radius: Math.random() * 2 + 1,
                opacity: Math.random() * 0.5 + 0.1,
                life: 1
            });
        }
    }

    updateParticle(particle) {
        particle.x += particle.vx;
        particle.y += particle.vy;

        if (particle.x < 0 || particle.x > this.canvas.width) {
            particle.vx *= -1;
        }
        if (particle.y < 0 || particle.y > this.canvas.height) {
            particle.vy *= -1;
        }

        particle.opacity += (Math.random() - 0.5) * 0.02;
        particle.opacity = Math.max(0.05, Math.min(0.5, particle.opacity));
    }

    drawParticle(particle) {
        this.ctx.globalAlpha = particle.opacity;
        this.ctx.fillStyle = "#c9302c";
        this.ctx.beginPath();
        this.ctx.arc(particle.x, particle.y, particle.radius, 0, Math.PI * 2);
        this.ctx.fill();
    }

    animate() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.particles.forEach(particle => {
            this.updateParticle(particle);
            this.drawParticle(particle);
        });

        requestAnimationFrame(() => this.animate());
    }
}

// Funciones globales para los controles
function toggleDiscountAnimation() {
    if (window.discountStreamController) {
        window.discountStreamController.toggleAnimation();
    }
}

function resetDiscountPosition() {
    if (window.discountStreamController) {
        window.discountStreamController.resetPosition();
    }
}

function changeDiscountDirection() {
    if (window.discountStreamController) {
        window.discountStreamController.changeDirection();
    }
}