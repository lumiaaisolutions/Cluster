// ==================== CONTROLADOR DE TIPOS DE DATOS ====================
// Maneja la selección de tipos de datos y la información descriptiva

// console.log('📊 Cargando controlador de tipos de datos...');

class ControladorTiposDatos {
    constructor() {
        this.tipoActual = 'empresas';
        this.descripciones = {
            empresas: {
                titulo: '📈 Empresas Registradas',
                descripcion: 'Muestra la evolución histórica de empresas registradas en la plataforma, incluyendo nuevas incorporaciones y estado actual.',
                explicacion: 'Datos extraídos de la tabla `empresas` donde estado = "activa". Incluye fecha de registro, sector y tipo de empresa.',
                campos: ['Total empresas', 'Nuevas registradas', 'Sector de actividad', 'Estado']
            },
            usuarios: {
                titulo: '👥 Usuarios Nuevos',
                descripcion: 'Evolución de usuarios registrados en el sistema, incluyendo miembros activos y nuevos socios.',
                explicacion: 'Datos de la tabla `usuarios` con estado = "activo". Incluye tipos de usuario, fecha de registro y actividad.',
                campos: ['Total usuarios', 'Nuevos registros', 'Tipo de usuario', 'Última actividad']
            },
            eventos: {
                titulo: '📅 Eventos Programados',
                descripcion: 'Análisis de eventos organizados, programados y realizados en la plataforma.',
                explicacion: 'Información de la tabla `eventos` con diferentes estados. Incluye tipo de evento, asistentes y fechas.',
                campos: ['Total eventos', 'Eventos programados', 'Asistentes esperados', 'Tipo de evento']
            },
            comites: {
                titulo: '🏛️ Miembros de Comités',
                descripcion: 'Seguimiento de la participación en comités y grupos de trabajo organizacionales.',
                explicacion: 'Datos de las tablas `comites` y `usuarios` para mostrar participación activa en grupos.',
                campos: ['Total miembros', 'Comités activos', 'Participación', 'Roles asignados']
            },
            descuentos: {
                titulo: '💰 Descuentos Activos',
                descripcion: 'Análisis de descuentos disponibles y su utilización por parte de los miembros.',
                explicacion: 'Información de la tabla `descuentos` con estado activo. Incluye porcentajes, empresas y vigencia.',
                campos: ['Total descuentos', 'Descuentos activos', 'Porcentaje promedio', 'Empresa asociada']
            },
            custom: {
                titulo: '🔧 Datos Personalizados',
                descripcion: 'Datos ingresados manualmente para análisis específicos o pruebas.',
                explicacion: 'Datos proporcionados por el usuario en formato JSON. Permite análisis de cualquier tipo de información.',
                campos: ['Definidos por usuario', 'Formato JSON', 'Estructura flexible', 'Análisis personalizado']
            }
        };
        this.init();
    }

    init() {
        this.configurarSelector();
        this.configurarBotones();
        this.actualizarDescripcion('empresas');
        // console.log('✅ Controlador de tipos de datos inicializado');
    }

    configurarSelector() {
        const selector = document.getElementById('dataSource');
        if (selector) {
            // Remover eventos anteriores
            selector.onchange = null;
            
            // Agregar nuevo evento
            selector.addEventListener('change', (e) => {
                const nuevoTipo = e.target.value;
                // console.log(`🔄 Cambiando tipo de datos a: ${nuevoTipo}`);
                
                this.tipoActual = nuevoTipo;
                this.actualizarDescripcion(nuevoTipo);
                this.cargarDatosTipo(nuevoTipo);
            });
            
            // console.log('✅ Selector configurado');
        }
    }

    configurarBotones() {
        // Botón recargar datos
        const btnRecargar = document.getElementById('cargarDatosRapido');
        if (btnRecargar) {
            btnRecargar.addEventListener('click', () => {
                // console.log('🔄 Recargando datos forzadamente...');
                this.recargarDatosForzado();
            });
        }

        // Botón ver detalles
        const btnDetalles = document.getElementById('verDetallesDatos');
        if (btnDetalles) {
            btnDetalles.addEventListener('click', () => {
                // console.log('ℹ️ Mostrando detalles de datos...');
                this.mostrarDetallesDatos();
            });
        }

        // console.log('✅ Botones configurados');
    }

    actualizarDescripcion(tipo) {
        const descripcionContainer = document.getElementById('descripcionTipoDato');
        if (!descripcionContainer) return;

        const info = this.descripciones[tipo];
        if (!info) return;

        descripcionContainer.innerHTML = `
            <div class="text-sm text-blue-800">
                <div class="font-medium mb-1">${info.titulo}</div>
                <div class="text-blue-600">${info.descripcion}</div>
            </div>
        `;

        // Cambiar colores según el tipo
        const colores = {
            empresas: 'border-blue-200 bg-blue-50 text-blue-800',
            usuarios: 'border-green-200 bg-green-50 text-green-800',
            eventos: 'border-orange-200 bg-orange-50 text-orange-800',
            comites: 'border-purple-200 bg-purple-50 text-purple-800',
            descuentos: 'border-yellow-200 bg-yellow-50 text-yellow-800',
            custom: 'border-gray-200 bg-gray-50 text-gray-800'
        };

        const colorClasses = colores[tipo] || colores.empresas;
        descripcionContainer.className = `mt-2 p-3 rounded border ${colorClasses}`;
        
        // Actualizar el título del gráfico automáticamente
        this.actualizarTituloGrafico(tipo);

        // console.log(`📝 Descripción actualizada para: ${tipo}`);
    }
    
    actualizarTituloGrafico(tipo) {
        const chartTitleInput = document.getElementById('chartTitle');
        if (!chartTitleInput) return;
        
        const titulos = {
            empresas: 'Empresas Registradas',
            usuarios: 'Usuarios Nuevos',
            eventos: 'Eventos Programados', 
            comites: 'Miembros de Comités',
            descuentos: 'Descuentos Activos',
            custom: 'Datos Personalizados'
        };
        
        const nuevoTitulo = titulos[tipo] || 'Gráfico de Datos';
        chartTitleInput.value = nuevoTitulo;
        
        // Actualizar el gráfico si existe
        if (window.sistemaEmergencia) {
            window.sistemaEmergencia.config.title = nuevoTitulo;
            
            // Recrear gráfico con nuevo título
            setTimeout(() => {
                if (window.sistemaEmergencia.currentData.length > 0) {
                    window.sistemaEmergencia.crearGrafico();
                }
            }, 100);
        }
        
        // console.log(`🎨 Título actualizado a: ${nuevoTitulo}`);
    }

    async cargarDatosTipo(tipo) {
        if (tipo === 'custom') {
            this.abrirEditorDatos();
            return;
        }

        try {
            // Mostrar estado de carga
            this.mostrarEstadoCarga(`Cargando datos de ${tipo}...`);

            // Usar el gestor de datos reales
            if (window.gestorDatosReales) {
                const datos = await window.gestorDatosReales.obtenerDatos(tipo);
                
                if (datos && datos.length > 0) {
                    // Actualizar el sistema de emergencia
                    if (window.sistemaEmergencia) {
                        window.sistemaEmergencia.currentData = datos;
                        window.sistemaEmergencia.crearGrafico();
                        window.sistemaEmergencia.actualizarInformacionDatos(datos, tipo);
                    }
                    
                    this.mostrarEstadoExito(`${datos.length} registros de ${tipo} cargados`);
                } else {
                    throw new Error('No se obtuvieron datos válidos');
                }
            } else {
                throw new Error('Gestor de datos no disponible');
            }

        } catch (error) {
            console.error('❌ Error cargando datos:', error);
            this.mostrarEstadoError(`Error cargando ${tipo}: ${error.message}`);
        }
    }

    async recargarDatosForzado() {
        const btnRecargar = document.getElementById('cargarDatosRapido');
        if (btnRecargar) {
            btnRecargar.disabled = true;
            btnRecargar.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Cargando...';
        }

        try {
            // Limpiar cache si existe
            if (window.gestorDatosReales) {
                window.gestorDatosReales.datosCache = {};
            }

            // Recargar datos del tipo actual
            await this.cargarDatosTipo(this.tipoActual);

            // Mostrar notificación de éxito
            this.mostrarNotificacion('Datos recargados desde la base de datos', 'success');

        } catch (error) {
            console.error('❌ Error recargando datos:', error);
            this.mostrarNotificacion('Error recargando datos: ' + error.message, 'error');
        } finally {
            // Restaurar botón
            if (btnRecargar) {
                btnRecargar.disabled = false;
                btnRecargar.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Recargar BD';
            }
        }
    }

    mostrarDetallesDatos() {
        const info = this.descripciones[this.tipoActual];
        if (!info) return;

        const datos = window.sistemaEmergencia?.currentData || [];
        const primerDato = datos[0] || {};

        const modalHTML = `
            <div id="modalDetallesDatos" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                padding: 20px;
                box-sizing: border-box;
            ">
                <div style="
                    background: #16161a;
                    padding: 30px;
                    border-radius: 12px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                    max-width: 600px;
                    width: 100%;
                    max-height: 80vh;
                    overflow-y: auto;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0; color: #f8fafc; font-size: 24px;">
                            ${info.titulo} - Detalles
                        </h3>
                        <button onclick="cerrarModalDetalles()" style="
                            background: #6b7280;
                            color: white;
                            border: none;
                            border-radius: 50%;
                            width: 30px;
                            height: 30px;
                            cursor: pointer;
                            font-size: 16px;
                        ">×</button>
                    </div>
                    
                    <div style="space-y: 16px;">
                        <div style="background: rgba(255,255,255,0.04); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 8px 0; color: #f8fafc;">📊 Descripción</h4>
                            <p style="margin: 0; color: #94a3b8; font-size: 14px;">${info.descripcion}</p>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.04); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 8px 0; color: #f8fafc;">🗄️ Fuente de Datos</h4>
                            <p style="margin: 0; color: #94a3b8; font-size: 14px;">${info.explicacion}</p>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.04); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 8px 0; color: #f8fafc;">📈 Información Actual</h4>
                            <ul style="margin: 8px 0; padding-left: 20px; color: #94a3b8; font-size: 14px;">
                                <li>Total de registros: <strong>${datos.length}</strong></li>
                                <li>Tipo de datos: <strong>${this.tipoActual}</strong></li>
                                <li>Último valor: <strong>${datos.length > 0 ? datos[datos.length - 1].valor : 'N/A'}</strong></li>
                                <li>Fuente: <strong>${primerDato.detalles?.generado ? 'Datos de ejemplo' : 'Base de datos'}</strong></li>
                            </ul>
                        </div>
                        
                        <div style="background: rgba(255,255,255,0.04); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 8px 0; color: #f8fafc;">🔍 Campos Disponibles</h4>
                            <ul style="margin: 8px 0; padding-left: 20px; color: #94a3b8; font-size: 14px;">
                                ${info.campos.map(campo => `<li>${campo}</li>`).join('')}
                            </ul>
                        </div>
                        
                        ${datos.length > 0 ? `
                        <div style="background: rgba(255,255,255,0.04); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 8px 0; color: #f8fafc;">📋 Muestra de Datos</h4>
                            <pre style="background: #16161a; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; margin: 0;">${JSON.stringify(primerDato, null, 2)}</pre>
                        </div>
                        ` : ''}
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <button onclick="cerrarModalDetalles()" style="
                            background: #3b82f6;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            padding: 10px 20px;
                            cursor: pointer;
                            font-size: 14px;
                        ">Cerrar</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    abrirEditorDatos() {
        if (window.sistemaEmergencia && window.sistemaEmergencia.editarDatosMejorado) {
            window.sistemaEmergencia.editarDatosMejorado();
        } else if (window.sistemaEmergencia && window.sistemaEmergencia.abrirEditor) {
            window.sistemaEmergencia.abrirEditor();
        } else {
            alert('Editor de datos no disponible');
        }
    }

    // Métodos de estado
    mostrarEstadoCarga(mensaje) {
        this.actualizarEstado(mensaje, 'loading');
    }

    mostrarEstadoExito(mensaje) {
        this.actualizarEstado(mensaje, 'success');
    }

    mostrarEstadoError(mensaje) {
        this.actualizarEstado(mensaje, 'error');
    }

    actualizarEstado(mensaje, tipo) {
        const statusText = document.getElementById('dataStatusText');
        const statusIcon = document.getElementById('dataStatusIcon');

        if (statusText) {
            statusText.textContent = mensaje;
        }

        if (statusIcon) {
            const colores = {
                loading: 'bg-blue-500 animate-pulse',
                success: 'bg-green-500',
                error: 'bg-red-500'
            };

            statusIcon.className = `w-2 h-2 rounded-full ${colores[tipo] || 'bg-gray-500'}`;
        }
    }

    mostrarNotificacion(mensaje, tipo) {
        if (window.sistemaEmergencia && window.sistemaEmergencia.mostrarNotificacion) {
            window.sistemaEmergencia.mostrarNotificacion(mensaje, tipo);
        } else {
            // console.log(`${tipo.toUpperCase()}: ${mensaje}`);
        }
    }
}

// Función global para cerrar modal
window.cerrarModalDetalles = function() {
    const modal = document.getElementById('modalDetallesDatos');
    if (modal) modal.remove();
};

// Crear instancia global
window.controladorTiposDatos = new ControladorTiposDatos();

// console.log('✅ Controlador de tipos de datos cargado');
