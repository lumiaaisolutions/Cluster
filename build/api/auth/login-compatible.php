<?php
/**
 * API de login compatible con cualquier estructura de tabla usuarios_perfil
 * Se adapta automáticamente a las columnas disponibles
 */

// Configuración segura de sesiones usando la clase centralizada
define('CLAUT_ACCESS', true);
require_once dirname(dirname(__DIR__)) . '/config/session-config.php';
SessionConfig::init();

header('Content-Type: application/json; charset=UTF-8');

// CORS Dinámico para permitir credenciales
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/api-response.php';

// ============================================================================
// RATE LIMITING — solo para POST con action=login (anti brute-force)
// 5 intentos por IP en ventana de 5 minutos.
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $parsed   = json_decode($rawInput, true);
    $action   = $parsed['action'] ?? $_POST['action'] ?? '';
    if ($action === 'login') {
        try {
            require_once dirname(dirname(__DIR__)) . '/middleware/rate-limiter.php';
            $rateLimiter = new RateLimiter();
            $clientIP = getRateLimitIdentifier();
            $rateLimiter->protect(
                $clientIP,
                RateLimitConfig::LOGIN['max'],
                RateLimitConfig::LOGIN['window'],
                RateLimitConfig::LOGIN['action']
            );
        } catch (Exception $e) {
            // Si el rate limiter falla por motivos internos, log y continúa
            // (preferimos no bloquear logins por bugs del middleware).
            error_log('[login rate-limiter] ' . $e->getMessage());
        }
    }
}

/**
 * Verificar usuario por credenciales - Compatible con cualquier estructura
 */
function verificarCredenciales($email, $password) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Query incluyendo estado_usuario Y email_verificado para validación completa
        $selectQuery = "SELECT email, password, nombre, nombre_empresa, rol, estado_usuario, email_verificado FROM usuarios_perfil WHERE email = :email";
        error_log("Attempting login for email: " . $email);
        
        $stmt = $conn->prepare($selectQuery);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("User found: " . ($user ? "YES" : "NO"));
        if ($user) {
            error_log("User data: " . json_encode(array_keys($user)));
            error_log("Password in DB exists: " . (!empty($user['password']) ? "YES" : "NO"));
        }
        
        if ($user && !empty($user['password'])) {
            if (password_verify($password, $user['password'])) {
                $rolLower = strtolower($user['rol'] ?? '');
                $esAdmin  = in_array($rolLower, ['admin', 'administrador', 'root'], true);

                // 1. Verificar email verificado.
                // Defensa: si la columna no existe (BD sin migración aplicada) o es NULL,
                // NO bloquear (modo legacy). Solo bloquear si está explícitamente en 0.
                $emailVerificadoRaw = array_key_exists('email_verificado', $user) ? $user['email_verificado'] : null;
                $bloquearPorEmail = ($emailVerificadoRaw !== null) && ((int)$emailVerificadoRaw === 0);
                if ($bloquearPorEmail && !$esAdmin) {
                    return [
                        'error' => 'email_not_verified',
                        'message' => 'Debes verificar tu correo antes de iniciar sesión. Revisa tu bandeja de entrada.'
                    ];
                }

                // 2. Verificar estado de aprobación admin
                $estado_usuario = $user['estado_usuario'] ?? 'pendiente';
                if ($estado_usuario !== 'activo' && !$esAdmin) {
                    $mensajes_estado = [
                        'pendiente'    => 'Tu cuenta está pendiente de aprobación por un administrador. Te notificaremos cuando sea aprobada.',
                        'rechazado'    => 'Tu cuenta ha sido rechazada. Contacta al administrador para más información.',
                        'lista_espera' => 'Tu cuenta está en lista de espera. Te notificaremos cuando sea aprobada.'
                    ];
                    return [
                        'error'   => 'account_not_approved',
                        'message' => $mensajes_estado[$estado_usuario] ?? 'Tu cuenta no está activa. Contacta al administrador.'
                    ];
                }

                // Usuario aprobado y email verificado - permitir acceso
                return [
                    'email'          => $user['email'],
                    'nombre'         => $user['nombre'],
                    'nombre_empresa' => $user['nombre_empresa'],
                    'rol'            => $user['rol'],
                    'estado_usuario' => $estado_usuario
                ];
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error verificando credenciales: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener usuario actual de la sesión - Simplificado
 */
function obtenerUsuarioSesion() {
    // Verificación estricta: Debe tener email Y rol para ser válida
    if (isset($_SESSION['user_email']) && !empty($_SESSION['user_rol'])) {
        return [
            'email' => $_SESSION['user_email'],
            'nombre' => $_SESSION['user_nombre'] ?? '',
            'nombre_empresa' => $_SESSION['user_nombre_empresa'] ?? '',
            'rol' => $_SESSION['user_rol']
        ];
    }

    return false;
}

/**
 * Iniciar sesión de usuario
 */
function iniciarSesion($user) {
    // Limpiar cualquier basura anterior (como datos de tests)
    session_unset();

    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_nombre'] = $user['nombre'];
    $_SESSION['user_nombre_empresa'] = $user['nombre_empresa'];
    $_SESSION['user_rol'] = $user['rol'];
    $_SESSION['login_time'] = time();
    // Limpiar timestamp duplicado
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // TRACE LOG INTENSO - REMOVED after debugging
    // error_log("LOGIN TRACE [1/3]: Variables asignadas en memoria.");
    // error_log("LOGIN TRACE [2/3]: ID de sesión actual: " . session_id());
    // error_log("LOGIN TRACE [3/3]: Contenido: " . print_r($_SESSION, true));

    // Forzar escritura inmediata para prueba
    // session_write_close(); 
    // session_start(); // Reabrir si fuera necesario, pero por ahora confiamos en el write_close final

    return true;
}

/**
 * Cerrar sesión
 */
function cerrarSesion() {
    session_destroy();
    return true;
}

// Manejar las diferentes acciones
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        switch ($action) {
            case 'check':
                // Verificar si hay una sesión activa
                $user = obtenerUsuarioSesion();
                if ($user) {
                    ApiResponse::success($user, ['message' => 'Usuario autenticado']);
                } else {
                    ApiResponse::error('No hay sesión activa');
                }
                break;
                
            case 'user':
                // Obtener datos del usuario actual
                $user = obtenerUsuarioSesion();
                if ($user) {
                    ApiResponse::success($user, ['message' => 'Datos de usuario obtenidos']);
                } else {
                    ApiResponse::error('Usuario no autenticado');
                }
                break;
                
            default:
                ApiResponse::error('Acción no válida');
        }
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        switch ($action) {
            case 'login':
                if (!isset($input['email']) || !isset($input['password'])) {
                    ApiResponse::error('Email y contraseña requeridos');
                }
                
                $user = verificarCredenciales($input['email'], $input['password']);

                if ($user) {
                    // Verificar errores de aprobación o verificación de email
                    if (isset($user['error']) && in_array($user['error'], ['account_not_approved', 'email_not_verified'], true)) {
                        ApiResponse::error($user['message'], 403, ['error_type' => $user['error']]);
                    } else {
                        // Usuario aprobado - iniciar sesión
                        iniciarSesion($user);
                        
                        // ============================================
                        // NUEVO: Generar tokens JWT
                        // ============================================
                        $accessToken = null;
                        $refreshToken = null;
                        $hasJWT = false;
                        
                        try {
                            // Cargar sistema JWT si está disponible
                            $jwtValidatorPath = dirname(dirname(__DIR__)) . '/middleware/jwt-validator.php';
                            
                            if (file_exists($jwtValidatorPath)) {
                                require_once $jwtValidatorPath;
                                
                                // Obtener secreto desde .env o usar default
                                $jwtSecret = getenv('JWT_SECRET') ?: 'CLAUT_SECRET_KEY_2024_SECURE';
                                
                                // Obtener ID del usuario de la base de datos
                                $db = Database::getInstance();
                                $conn = $db->getConnection();
                                $stmt = $conn->prepare("SELECT id FROM usuarios_perfil WHERE email = :email");
                                $stmt->bindParam(':email', $user['email']);
                                $stmt->execute();
                                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($userData) {
                                    // Payload para los tokens
                                    $payload = [
                                        'user_id' => $userData['id'],
                                        'email' => $user['email'],
                                        'rol' => $user['rol']
                                    ];
                                    
                                    // Generar access token (15 minutos)
                                    $accessToken = JwtValidator::generate($payload, $jwtSecret, JwtConfig::ACCESS_TOKEN_EXPIRY);
                                    
                                    // Generar refresh token (7 días)
                                    $refreshToken = JwtValidator::generateRefreshToken($payload, $jwtSecret);
                                    
                                    if ($accessToken && $refreshToken) {
                                        $hasJWT = true;
                                        error_log("✅ Tokens JWT generados para usuario: {$user['email']}");
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log("⚠️ Error generando tokens JWT: " . $e->getMessage());
                            // Continuar sin tokens JWT (fallback a solo sesiones)
                        }
                        
                        // Preparar respuesta
                        $responseData = $user;
                        $extraData = [];
                        
                        // Agregar tokens si están disponibles
                        if ($hasJWT && $accessToken && $refreshToken) {
                            $extraData['token'] = $accessToken;
                            $extraData['refresh_token'] = $refreshToken;
                            $extraData['token_type'] = 'Bearer';
                            $extraData['expires_in'] = 900; // 15 minutos
                        }
                        
                        ApiResponse::success($responseData, array_merge(['message' => 'Login exitoso'], $extraData));
                    }
                } else {
                    ApiResponse::error('Credenciales incorrectas');
                }
                break;
                
            case 'logout':
                // ============================================
                // NUEVO: Agregar token a blacklist si existe
                // ============================================
                $tokenRevoked = false;
                
                try {
                    // Intentar obtener token del header Authorization
                    $headers = getallheaders();
                    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                    $token = str_replace('Bearer ', '', $authHeader);
                    
                    // Si no hay token en header, intentar desde POST
                    if (empty($token) && isset($input['token'])) {
                        $token = $input['token'];
                    }
                    
                    // Si hay token, agregarlo a blacklist
                    if (!empty($token)) {
                        $blacklistPath = dirname(dirname(__DIR__)) . '/utils/token-blacklist.php';
                        $jwtValidatorPath = dirname(dirname(__DIR__)) . '/middleware/jwt-validator.php';
                        
                        if (file_exists($blacklistPath) && file_exists($jwtValidatorPath)) {
                            require_once $jwtValidatorPath;
                            require_once $blacklistPath;
                            
                            // Obtener expiry del token
                            $payload = JwtValidator::getPayload($token);
                            $expiry = $payload['exp'] ?? null;
                            
                            // Agregar a blacklist
                            if (TokenBlacklist::add($token, $expiry)) {
                                $tokenRevoked = true;
                                error_log("✅ Token agregado a blacklist en logout");
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("⚠️ Error agregando token a blacklist: " . $e->getMessage());
                    // Continuar con logout normal
                }
                
                // Cerrar sesión
                cerrarSesion();
                
                // Responder con información de revocación
                $extraData = $tokenRevoked ? ['token_revoked' => true] : [];
                ApiResponse::success(null, array_merge(['message' => 'Logout exitoso'], $extraData));
                break;
                
            default:
                ApiResponse::error('Acción no válida');
        }
        break;
        
    default:
        http_response_code(405);
        ApiResponse::error('Método no permitido');
}
?>