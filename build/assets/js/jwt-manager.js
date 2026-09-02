/**
 * JWT Manager - Gestión automática de tokens JWT
 * 
 * Funcionalidades:
 * - Guardar tokens en localStorage
 * - Recuperar tokens
 * - Verificar expiración
 * - Renovar tokens automáticamente
 * - Limpiar tokens en logout
 * 
 * @version 1.0.0
 * @date 2026-01-30
 */

class JWTManager {
    constructor() {
        this.ACCESS_TOKEN_KEY = 'claut_access_token';
        this.REFRESH_TOKEN_KEY = 'claut_refresh_token';
        this.TOKEN_EXPIRY_KEY = 'claut_token_expiry';
        this.USER_DATA_KEY = 'claut_user_data';
    }

    /**
     * Guardar tokens después del login
     * @param {string} accessToken - Token de acceso (15 min)
     * @param {string} refreshToken - Token de renovación (7 días)
     * @param {number} expiresIn - Tiempo de expiración en segundos
     */
    saveTokens(accessToken, refreshToken, expiresIn = 900) {
        try {
            localStorage.setItem(this.ACCESS_TOKEN_KEY, accessToken);
            localStorage.setItem(this.REFRESH_TOKEN_KEY, refreshToken);

            // Calcular tiempo de expiración
            const expiryTime = Date.now() + (expiresIn * 1000);
            localStorage.setItem(this.TOKEN_EXPIRY_KEY, expiryTime.toString());

            console.log('✅ Tokens JWT guardados en localStorage');
            console.log('⏰ Expira en:', expiresIn, 'segundos');

            return true;
        } catch (error) {
            console.error('❌ Error guardando tokens:', error);
            return false;
        }
    }

    /**
     * Guardar datos del usuario
     * @param {object} userData - Datos del usuario
     */
    saveUserData(userData) {
        try {
            localStorage.setItem(this.USER_DATA_KEY, JSON.stringify(userData));
            return true;
        } catch (error) {
            console.error('❌ Error guardando datos de usuario:', error);
            return false;
        }
    }

    /**
     * Obtener datos del usuario
     * @returns {object|null}
     */
    getUserData() {
        try {
            const data = localStorage.getItem(this.USER_DATA_KEY);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.error('❌ Error obteniendo datos de usuario:', error);
            return null;
        }
    }

    /**
     * Obtener access token
     * @returns {string|null}
     */
    getAccessToken() {
        return localStorage.getItem(this.ACCESS_TOKEN_KEY);
    }

    /**
     * Obtener refresh token
     * @returns {string|null}
     */
    getRefreshToken() {
        return localStorage.getItem(this.REFRESH_TOKEN_KEY);
    }

    /**
     * Verificar si hay tokens guardados
     * @returns {boolean}
     */
    hasTokens() {
        return !!(this.getAccessToken() && this.getRefreshToken());
    }

    /**
     * Verificar si el token expiró
     * @returns {boolean}
     */
    isTokenExpired() {
        const expiry = localStorage.getItem(this.TOKEN_EXPIRY_KEY);
        if (!expiry) return true;

        return Date.now() >= parseInt(expiry);
    }

    /**
     * Verificar si el token está por expirar (2 minutos antes)
     * @returns {boolean}
     */
    isTokenExpiringSoon() {
        const expiry = localStorage.getItem(this.TOKEN_EXPIRY_KEY);
        if (!expiry) return true;

        const timeLeft = parseInt(expiry) - Date.now();
        const twoMinutes = 2 * 60 * 1000;

        return timeLeft < twoMinutes;
    }

    /**
     * Obtener tiempo restante del token en segundos
     * @returns {number}
     */
    getTimeLeft() {
        const expiry = localStorage.getItem(this.TOKEN_EXPIRY_KEY);
        if (!expiry) return 0;

        const timeLeft = parseInt(expiry) - Date.now();
        return Math.max(0, Math.floor(timeLeft / 1000));
    }

    /**
     * Renovar access token usando refresh token
     * @returns {Promise<string>} Nuevo access token
     */
    async refreshAccessToken() {
        const refreshToken = this.getRefreshToken();

        if (!refreshToken) {
            throw new Error('No hay refresh token disponible');
        }

        console.log('🔄 Renovando access token...');

        try {
            const response = await fetch('/api/auth/refresh-token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    refresh_token: refreshToken
                })
            });

            const data = await response.json();

            if (data.success && data.access_token) {
                // Guardar nuevo access token
                this.saveTokens(
                    data.access_token,
                    refreshToken,
                    data.expires_in || 900
                );

                console.log('✅ Access token renovado exitosamente');
                return data.access_token;
            } else {
                throw new Error(data.message || 'Error renovando token');
            }
        } catch (error) {
            console.error('❌ Error renovando token:', error);
            throw error;
        }
    }

    /**
     * Limpiar todos los tokens (logout)
     */
    clearTokens() {
        try {
            localStorage.removeItem(this.ACCESS_TOKEN_KEY);
            localStorage.removeItem(this.REFRESH_TOKEN_KEY);
            localStorage.removeItem(this.TOKEN_EXPIRY_KEY);
            localStorage.removeItem(this.USER_DATA_KEY);

            console.log('🗑️ Tokens limpiados del localStorage');
            return true;
        } catch (error) {
            console.error('❌ Error limpiando tokens:', error);
            return false;
        }
    }

    /**
     * Hacer petición HTTP con token automático
     * Renueva el token si está por expirar
     * 
     * @param {string} url - URL del endpoint
     * @param {object} options - Opciones de fetch
     * @returns {Promise<Response>}
     */
    async fetchWithToken(url, options = {}) {
        // Verificar si necesita renovación
        if (this.hasTokens() && this.isTokenExpiringSoon()) {
            try {
                await this.refreshAccessToken();
            } catch (error) {
                console.error('❌ Error renovando token automáticamente:', error);

                // Si falla la renovación, limpiar y redirigir a login
                this.clearTokens();

                if (typeof window !== 'undefined' && !window.location.pathname.includes('sign-in')) {
                    window.location.href = '/pages/sign-in.html?session_expired=1';
                }

                throw error;
            }
        }

        // Agregar token a headers
        const token = this.getAccessToken();
        if (token) {
            options.headers = options.headers || {};
            options.headers['Authorization'] = `Bearer ${token}`;
        }

        // Agregar credentials por defecto
        options.credentials = options.credentials || 'include';

        return fetch(url, options);
    }

    /**
     * Enviar token en logout
     * @returns {Promise<boolean>}
     */
    async logout() {
        const token = this.getAccessToken();

        try {
            // Enviar token al servidor para blacklist
            if (token) {
                await fetch('/api/auth/login-compatible.php?action=logout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    credentials: 'include',
                    body: JSON.stringify({ token })
                });
            }

            // Limpiar tokens locales
            this.clearTokens();

            console.log('✅ Logout completado');
            return true;
        } catch (error) {
            console.error('❌ Error en logout:', error);
            // Limpiar tokens aunque falle la petición
            this.clearTokens();
            return false;
        }
    }
}

// Crear instancia global
if (typeof window !== 'undefined') {
    window.jwtManager = new JWTManager();
    console.log('🔐 JWT Manager inicializado');
}
