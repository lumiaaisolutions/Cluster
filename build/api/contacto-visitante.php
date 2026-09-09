<?php
/**
 * Formulario de contacto del Portal Visitante (FEATURE-036 r5).
 *
 * POST { nombre, correo, mensaje, empresa_web (honeypot) }
 * → envía el mensaje por correo a atención del Clúster vía EmailService
 *   (mismo SMTP/plantilla que el resto del sitio). No persiste nada en BD:
 *   el mensaje viaja solo por correo, sin PII almacenada (mismo criterio
 *   de privacidad que visitante_metricas).
 *
 * Anti-abuso sin CAPTCHA:
 *  - honeypot: el campo oculto "empresa_web" debe venir vacío (los bots lo llenan)
 *  - rate limit por sesión: máx 3 envíos por 10 minutos
 *  - requiere sesión de visitante o de usuario (mismo guard que el portal)
 */
define('CLAUT_ACCESS', true);
require_once __DIR__ . '/../config/session-config.php';
SessionConfig::init();

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $data): void {
    http_response_code($codigo);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['success' => false, 'message' => 'Método no permitido']);
}

if (empty($_SESSION['visitante']) && !isset($_SESSION['user_email'])) {
    responder(401, ['success' => false, 'message' => 'Sesión no válida — recarga la página.']);
}

// honeypot: campo invisible para humanos; si trae contenido es un bot
if (!empty($_POST['empresa_web'])) {
    // responder éxito silencioso para no darle señal al bot
    responder(200, ['success' => true]);
}

// rate limit por sesión: 3 envíos / 10 min
$ahora = time();
$_SESSION['contacto_envios'] = array_values(array_filter(
    $_SESSION['contacto_envios'] ?? [],
    fn($t) => ($ahora - $t) < 600
));
if (count($_SESSION['contacto_envios']) >= 3) {
    responder(429, ['success' => false, 'message' => 'Has enviado varios mensajes seguidos — espera unos minutos e intenta de nuevo.']);
}

$nombre  = trim($_POST['nombre'] ?? '');
$correo  = trim($_POST['correo'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

if ($nombre === '' || $correo === '' || $mensaje === '') {
    responder(400, ['success' => false, 'message' => 'Completa nombre, correo y mensaje.']);
}
if (mb_strlen($nombre) > 120 || mb_strlen($mensaje) > 3000) {
    responder(400, ['success' => false, 'message' => 'El mensaje es demasiado largo.']);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    responder(400, ['success' => false, 'message' => 'El correo no parece válido — revísalo.']);
}

require_once __DIR__ . '/../services/EmailService.php';

$contenido = "Nuevo mensaje desde el Portal Visitante:\n\n"
    . "Nombre: {$nombre}\n"
    . "Correo: {$correo}\n\n"
    . "Mensaje:\n{$mensaje}";

$resultado = EmailService::sendNotification(
    'atencion@clautedomex.mx',
    'Atención Clúster',
    'Nuevo mensaje del Portal Visitante',
    $contenido
);

if (!empty($resultado['success'])) {
    $_SESSION['contacto_envios'][] = $ahora;
    responder(200, ['success' => true, 'message' => '¡Mensaje enviado! Te responderemos al correo que dejaste.']);
}

error_log('contacto-visitante: fallo de envío — ' . ($resultado['message'] ?? 'sin detalle'));
responder(502, ['success' => false, 'message' => 'No se pudo enviar en este momento — intenta de nuevo o escríbenos por WhatsApp.']);
