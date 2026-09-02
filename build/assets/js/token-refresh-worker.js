/**
 * Token Refresh Worker - Renovación automática de tokens
 * 
 * Verifica cada minuto si el access token está por expirar
 * y lo renueva automáticamente usando el refresh token
 * 
 * @version 1.0.0
 * @date 2026-01-30
 */

class TokenRefreshWorker {
    constructor() {
        this.checkInterval = 60 * 1000; // 1 minuto
        this.intervalId = null;
        this.isRunning = false;
    }

    /**
     * Iniciar el worker de renovación
     */
    start() {
        if (this.isRunning) {
            console.log('⚠️ Worker de renovación ya está corriendo');
            return;
        }

        console.log('🔄 Iniciando worker de renovación de tokens');
        console.log('⏰ Verificación cada', this.checkInterval / 1000, 'segundos');

        this.isRunning = true;

        // Verificar inmediatamente
        this.checkAndRefresh();

        // Luego verificar cada minuto
        this.intervalId = setInterval(() => {
            this.checkAndRefresh();
        }, this.checkInterval);
    }

    /**
     * Detener el worker de renovación
     */
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
            this.isRunning = false;
            console.log('⏹️ Worker de renovación detenido');
        }
    }

    /**
     * Verificar y renovar token si es necesario
     */
    async checkAndRefresh() {
        // Verificar que jwtManager existe
        if (!window.jwtManager) {
            console.warn('⚠️ JWT Manager no disponible');
            return;
        }

        // Solo renovar si hay tokens
        const hasTokens = window.jwtManager.hasTokens();

        if (!hasTokens) {
            // No hay tokens, detener worker
            this.stop();
            return;
        }

        // Verificar si está por expirar
        if (window.jwtManager.isTokenExpiringSoon()) {
            const timeLeft = window.jwtManager.getTimeLeft();
            console.log(`⏰ Token expira en ${timeLeft} segundos, renovando...`);

            try {
                await window.jwtManager.refreshAccessToken();
                console.log('✅ Token renovado automáticamente por el worker');

                // Disparar evento personalizado
                if (typeof window !== 'undefined') {
                    window.dispatchEvent(new CustomEvent('tokenRefreshed', {
                        detail: {
                            timestamp: new Date().toISOString(),
                            timeLeft: timeLeft
                        }
                    }));
                }
            } catch (error) {
                console.error('❌ Error renovando token automáticamente:', error);

                // Detener worker y limpiar
                this.stop();
                window.jwtManager.clearTokens();

                // Redirigir a login si no estamos ya ahí
                if (typeof window !== 'undefined' && !window.location.pathname.includes('sign-in')) {
                    console.log('🔄 Redirigiendo a login...');
                    window.location.href = '/pages/sign-in.html?session_expired=1';
                }
            }
        } else {
            const timeLeft = window.jwtManager.getTimeLeft();
            console.log(`✅ Token válido, expira en ${timeLeft} segundos`);
        }
    }

    /**
     * Obtener estado del worker
     * @returns {object}
     */
    getStatus() {
        return {
            isRunning: this.isRunning,
            checkInterval: this.checkInterval,
            hasTokens: window.jwtManager ? window.jwtManager.hasTokens() : false,
            timeLeft: window.jwtManager ? window.jwtManager.getTimeLeft() : 0
        };
    }
}

// Crear instancia global
if (typeof window !== 'undefined') {
    window.tokenRefreshWorker = new TokenRefreshWorker();
    console.log('🔄 Token Refresh Worker inicializado');

    // Iniciar automáticamente si hay tokens guardados
    if (localStorage.getItem('claut_access_token')) {
        console.log('🔐 Tokens detectados, iniciando worker automáticamente');
        window.tokenRefreshWorker.start();
    }

    // Listener para cuando se guarden nuevos tokens
    window.addEventListener('storage', (e) => {
        if (e.key === 'claut_access_token' && e.newValue) {
            console.log('🔐 Nuevos tokens detectados, iniciando worker');
            window.tokenRefreshWorker.start();
        } else if (e.key === 'claut_access_token' && !e.newValue) {
            console.log('🗑️ Tokens eliminados, deteniendo worker');
            window.tokenRefreshWorker.stop();
        }
    });
}
