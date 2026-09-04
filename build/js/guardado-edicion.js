// ==================== SISTEMA DE GUARDADO Y EDICIÓN MEJORADO ====================
// Funciones específicas para guardado y edición

// console.log('💾 Cargando sistema de guardado y edición...');

// Extender el sistema de emergencia con funciones de edición
if (window.sistemaEmergencia) {
    
    // Función mejorada de guardado con feedback
    window.sistemaEmergencia.guardarMejorado = function() {
        // console.log('💾 Iniciando proceso de guardado mejorado...');
        
        // Mostrar modal de guardado personalizado
        const modalHTML = `
            <div id="modalGuardado" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            ">
                <div style="
                    background: #16161a;
                    padding: 30px;
                    border-radius: 12px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    max-width: 400px;
                    width: 90%;
                ">
                    <h3 style="margin: 0 0 20px 0; color: #f8fafc; font-size: 20px;">
                        💾 Guardar Configuración
                    </h3>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; color: #f8fafc; font-weight: 500;">
                            Nombre de la configuración:
                        </label>
                        <input type="text" id="nombreConfig" placeholder="Mi configuración" style="
                            width: 100%;
                            padding: 10px;
                            border: 2px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #f8fafc;
                            border-radius: 6px;
                            font-size: 14px;
                            box-sizing: border-box;
                        ">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; color: #f8fafc; font-weight: 500;">
                            Descripción (opcional):
                        </label>
                        <textarea id="descripcionConfig" placeholder="Descripción de la configuración..." style="
                            width: 100%;
                            padding: 10px;
                            border: 2px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #f8fafc;
                            border-radius: 6px;
                            font-size: 14px;
                            height: 60px;
                            resize: vertical;
                            box-sizing: border-box;
                        "></textarea>
                    </div>
                    
                    <div id="infoGuardado" style="
                        background: rgba(255,255,255,0.04);
                        padding: 12px;
                        border-radius: 6px;
                        margin-bottom: 20px;
                        font-size: 12px;
                        color: #94a3b8;
                    ">
                        📊 Datos: ${this.currentData.length} registros<br>
                        🎨 Tipo: ${this.config.type}<br>
                        🌈 Color: ${this.config.color}
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button onclick="cerrarModalGuardado()" style="
                            padding: 10px 20px;
                            background: #6b7280;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 14px;
                        ">Cancelar</button>
                        
                        <button onclick="confirmarGuardado()" style="
                            padding: 10px 20px;
                            background: #3b82f6;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 14px;
                        ">💾 Guardar</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Enfocar el input
        setTimeout(() => {
            document.getElementById('nombreConfig').focus();
        }, 100);
    };
    
    // Función mejorada de edición de datos
    window.sistemaEmergencia.editarDatosMejorado = function() {
        // console.log('📝 Abriendo editor de datos mejorado...');
        
        const panel = document.getElementById('customDataPanel');
        if (panel) {
            panel.style.display = 'flex';
            
            // Cargar datos actuales en el editor
            const editor = document.getElementById('customDataEditor');
            if (editor) {
                editor.value = JSON.stringify(this.currentData, null, 2);
            }
            
            // Configurar botones del editor
            this.configurarEditorDatos();
            
            this.mostrarNotificacion('Editor de datos abierto - Modifica el JSON y valida', 'info');
        } else {
            this.mostrarNotificacion('Panel de edición no encontrado', 'error');
        }
    };
    
    // Configurar funcionalidad del editor de datos
    window.sistemaEmergencia.configurarEditorDatos = function() {
        // console.log('⚙️ Configurando editor de datos...');
        
        // Botón validar datos
        const btnValidar = document.getElementById('validarDatos');
        if (btnValidar) {
            btnValidar.onclick = () => this.validarYActualizarDatos();
        }
        
        // Botón cargar ejemplo
        const btnEjemplo = document.getElementById('cargarEjemplo');
        if (btnEjemplo) {
            btnEjemplo.onclick = () => this.cargarEjemploEnEditor();
        }
        
        // Botón generar aleatorio
        const btnAleatorio = document.getElementById('generarAleatorio');
        if (btnAleatorio) {
            btnAleatorio.onclick = () => this.generarDatosAleatoriosEnEditor();
        }
        
        // Botón formatear
        const btnFormatear = document.getElementById('formatearJSON');
        if (btnFormatear) {
            btnFormatear.onclick = () => this.formatearJSONEnEditor();
        }
        
        // Botón limpiar
        const btnLimpiar = document.getElementById('limpiarEditor');
        if (btnLimpiar) {
            btnLimpiar.onclick = () => this.limpiarEditor();
        }
        
        // Botón guardar datos personalizados
        const btnGuardarDatos = document.getElementById('guardarDatosPersonalizados');
        if (btnGuardarDatos) {
            btnGuardarDatos.onclick = () => this.aplicarDatosPersonalizados();
        }
    };
    
    // Validar y actualizar datos desde el editor
    window.sistemaEmergencia.validarYActualizarDatos = function() {
        // console.log('🔍 Validando datos del editor...');
        
        const editor = document.getElementById('customDataEditor');
        const preview = document.getElementById('dataPreview');
        
        if (!editor) {
            this.mostrarNotificacion('Editor no encontrado', 'error');
            return;
        }
        
        try {
            const jsonText = editor.value.trim();
            
            if (!jsonText) {
                throw new Error('El editor está vacío');
            }
            
            const data = JSON.parse(jsonText);
            
            if (!Array.isArray(data)) {
                throw new Error('Los datos deben ser un array');
            }
            
            if (data.length === 0) {
                throw new Error('El array no puede estar vacío');
            }
            
            // Validar estructura básica
            const firstItem = data[0];
            const hasLabel = firstItem.mes || firstItem.label || firstItem.etiqueta;
            const hasValue = typeof firstItem.valor === 'number' || typeof firstItem.empresas === 'number' || typeof firstItem.count === 'number';
            
            if (!hasLabel) {
                throw new Error('Cada elemento necesita un campo "mes", "label" o "etiqueta"');
            }
            
            if (!hasValue) {
                throw new Error('Cada elemento necesita un campo numérico "valor", "empresas" o "count"');
            }
            
            // Si llegamos aquí, los datos son válidos
            this.mostrarVistaPrevia(data, preview);
            this.mostrarEstadisticas(data);
            this.mostrarNotificacion(`✅ Datos válidos: ${data.length} registros`, 'success');
            
            // console.log('✅ Datos validados correctamente:', data);
            
        } catch (error) {
            console.error('❌ Error validando datos:', error);
            
            if (preview) {
                preview.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #ef4444;">
                        <div style="font-size: 48px; margin-bottom: 16px;">⚠️</div>
                        <div style="font-weight: bold; margin-bottom: 8px;">Error en los datos JSON</div>
                        <div style="font-size: 14px;">${error.message}</div>
                    </div>
                `;
            }
            
            this.mostrarNotificacion('Error: ' + error.message, 'error');
        }
    };
    
    // Mostrar vista previa de los datos
    window.sistemaEmergencia.mostrarVistaPrevia = function(data, container) {
        if (!container) return;
        
        container.innerHTML = `
            <div style="padding: 10px;">
                <h4 style="margin: 0 0 10px 0; color: #f8fafc;">📊 Vista Previa</h4>
                <div style="background: rgba(255,255,255,0.04); padding: 10px; border-radius: 6px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto;">
                    ${data.slice(0, 5).map(item => {
                        const label = item.mes || item.label || item.etiqueta;
                        const value = item.valor || item.empresas || item.count;
                        return `${label}: ${value}`;
                    }).join('<br>')}
                    ${data.length > 5 ? '<br>... y ' + (data.length - 5) + ' más' : ''}
                </div>
            </div>
        `;
    };
    
    // Mostrar estadísticas
    window.sistemaEmergencia.mostrarEstadisticas = function(data) {
        const stats = document.getElementById('dataStats');
        if (!stats) return;
        
        const values = data.map(item => item.valor || item.empresas || item.count || 0);
        const min = Math.min(...values);
        const max = Math.max(...values);
        
        const statsRecords = document.getElementById('statsRecords');
        const statsFields = document.getElementById('statsFields');
        const statsRange = document.getElementById('statsRange');
        
        if (statsRecords) statsRecords.textContent = data.length;
        if (statsFields) statsFields.textContent = Object.keys(data[0]).length;
        if (statsRange) statsRange.textContent = `${min} - ${max}`;
        
        stats.style.display = 'block';
    };
    
    // Aplicar datos personalizados
    window.sistemaEmergencia.aplicarDatosPersonalizados = function() {
        // console.log('✨ Aplicando datos personalizados...');
        
        const editor = document.getElementById('customDataEditor');
        if (!editor) return;
        
        try {
            const data = JSON.parse(editor.value);
            
            if (!Array.isArray(data) || data.length === 0) {
                throw new Error('Datos inválidos');
            }
            
            // Aplicar los nuevos datos
            this.currentData = data;
            
            // Cerrar el editor
            this.cerrarEditor();
            
            // Crear el gráfico con los nuevos datos
            this.crearGrafico();
            
            this.mostrarNotificacion(`✅ Datos aplicados: ${data.length} registros`, 'success');
            
        } catch (error) {
            this.mostrarNotificacion('Error aplicando datos: ' + error.message, 'error');
        }
    };
    
    // Cargar ejemplo en editor
    window.sistemaEmergencia.cargarEjemploEnEditor = function() {
        const ejemplo = [
            { "mes": "Ene", "valor": 25, "categoria": "Ventas" },
            { "mes": "Feb", "valor": 32, "categoria": "Ventas" },
            { "mes": "Mar", "valor": 28, "categoria": "Ventas" },
            { "mes": "Abr", "valor": 35, "categoria": "Ventas" },
            { "mes": "May", "valor": 42, "categoria": "Ventas" }
        ];
        
        const editor = document.getElementById('customDataEditor');
        if (editor) {
            editor.value = JSON.stringify(ejemplo, null, 2);
            this.mostrarNotificacion('Ejemplo cargado en el editor', 'info');
        }
    };
    
    // Generar datos aleatorios en editor
    window.sistemaEmergencia.generarDatosAleatoriosEnEditor = function() {
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago'];
        const datos = [];
        
        for (let i = 0; i < 6; i++) {
            datos.push({
                mes: meses[i],
                valor: Math.floor(Math.random() * 50) + 10,
                categoria: `Grupo ${String.fromCharCode(65 + (i % 3))}`
            });
        }
        
        const editor = document.getElementById('customDataEditor');
        if (editor) {
            editor.value = JSON.stringify(datos, null, 2);
            this.mostrarNotificacion('Datos aleatorios generados', 'info');
        }
    };
    
    // Formatear JSON en editor
    window.sistemaEmergencia.formatearJSONEnEditor = function() {
        const editor = document.getElementById('customDataEditor');
        if (!editor) return;
        
        try {
            const data = JSON.parse(editor.value);
            editor.value = JSON.stringify(data, null, 2);
            this.mostrarNotificacion('JSON formateado', 'success');
        } catch (error) {
            this.mostrarNotificacion('Error formateando: JSON inválido', 'error');
        }
    };
    
    // Limpiar editor
    window.sistemaEmergencia.limpiarEditor = function() {
        const editor = document.getElementById('customDataEditor');
        const preview = document.getElementById('dataPreview');
        const stats = document.getElementById('dataStats');
        
        if (editor) editor.value = '';
        if (preview) preview.innerHTML = '<p style="text-align: center; color: #94a3b8; padding: 40px;">Los datos validados aparecerán aquí</p>';
        if (stats) stats.style.display = 'none';
        
        this.mostrarNotificacion('Editor limpiado', 'info');
    };
    
    // Mostrar configuraciones guardadas
    window.sistemaEmergencia.mostrarConfiguracionesGuardadas = function() {
        const configs = JSON.parse(localStorage.getItem('configs_emergencia') || '[]');
        
        if (configs.length === 0) {
            this.mostrarNotificacion('No hay configuraciones guardadas', 'info');
            return;
        }
        
        let listaHTML = '<h3>📚 Configuraciones Guardadas:</h3><ul>';
        configs.forEach((config, index) => {
            listaHTML += `
                <li style="margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.04); border-radius: 6px;">
                    <strong>${config.name}</strong><br>
                    <small>Guardado: ${new Date(config.timestamp).toLocaleString()}</small><br>
                    <small>Datos: ${config.data.length} registros</small>
                    <button onclick="cargarConfiguracion(${index})" style="margin-left: 10px; padding: 5px 10px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cargar
                    </button>
                </li>
            `;
        });
        listaHTML += '</ul>';
        
        const modal = document.createElement('div');
        modal.innerHTML = `
            <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div style="background: #16161a; padding: 30px; border-radius: 12px; max-width: 500px; max-height: 70vh; overflow-y: auto;">
                    ${listaHTML}
                    <button onclick="this.parentElement.parentElement.remove()" style="margin-top: 20px; padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        Cerrar
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
    };
    
    // console.log('✅ Sistema de guardado y edición mejorado cargado');
}

// Funciones globales para el modal de guardado
window.cerrarModalGuardado = function() {
    const modal = document.getElementById('modalGuardado');
    if (modal) modal.remove();
};

window.confirmarGuardado = function() {
    const nombre = document.getElementById('nombreConfig').value.trim();
    const descripcion = document.getElementById('descripcionConfig').value.trim();
    
    if (!nombre) {
        alert('Por favor ingresa un nombre para la configuración');
        return;
    }
    
    const config = {
        name: nombre,
        descripcion: descripcion,
        data: window.sistemaEmergencia.currentData,
        config: window.sistemaEmergencia.config,
        timestamp: new Date().toISOString()
    };
    
    const configs = JSON.parse(localStorage.getItem('configs_emergencia') || '[]');
    configs.push(config);
    localStorage.setItem('configs_emergencia', JSON.stringify(configs));
    
    window.sistemaEmergencia.mostrarNotificacion(`✅ "${nombre}" guardado correctamente`, 'success');
    // console.log('💾 Configuración guardada:', config);
    
    cerrarModalGuardado();
};

window.cargarConfiguracion = function(index) {
    const configs = JSON.parse(localStorage.getItem('configs_emergencia') || '[]');
    if (configs[index]) {
        const config = configs[index];
        
        window.sistemaEmergencia.currentData = config.data;
        window.sistemaEmergencia.config = config.config;
        
        // Actualizar interfaz
        if (config.config.type) {
            const radio = document.querySelector(`input[name="chartType"][value="${config.config.type}"]`);
            if (radio) radio.checked = true;
        }
        
        if (config.config.color) {
            const colorInput = document.getElementById('primaryColor');
            if (colorInput) colorInput.value = config.config.color;
        }
        
        // Recrear gráfico
        window.sistemaEmergencia.crearGrafico();
        
        window.sistemaEmergencia.mostrarNotificacion(`📁 "${config.name}" cargado`, 'success');
        
        // Cerrar modal
        const modal = document.querySelector('[style*="position: fixed"]');
        if (modal) modal.remove();
    }
};

// console.log('💾 Sistema de guardado y edición completamente cargado');
