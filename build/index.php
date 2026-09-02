<?php
/**
 * Router principal del dominio Clúster Intranet
 *
 * Flujo:
 *   - Visitante NO autenticado en "/"      → sirve landing/index.html (página pública)
 *   - Visitante autenticado en "/"         → sirve dashboard.html
 *   - "/" con ?dashboard=1 o ?home=1       → fuerza dashboard.html
 *   - "/" con ?landing=1                   → fuerza landing (debug / preview)
 *
 * Los enlaces de "Iniciar sesión" / "Crear cuenta" de la landing apuntan
 * directamente a /pages/sign-in.html y /pages/sign-up.html.
 *
 * NOTA: Todas las rutas internas de la landing son absolutas con prefijo
 * /landing/ (ej: /landing/assets/css/...) — funcionan igual desde "/" o
 * desde "/landing/", sin necesidad de <base> ni redirects.
 */

error_log("Index.php accessed from: " . $_SERVER['REQUEST_URI']);

session_start();

function isAuthenticated() {
    if (isset($_GET['authenticated']) && $_GET['authenticated'] === 'true') {
        error_log("User authenticated via GET parameter");
        return true;
    }
    if (isset($_COOKIE['claut_authenticated']) && $_COOKIE['claut_authenticated'] === 'true') {
        error_log("User authenticated via cookie");
        return true;
    }
    if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
        error_log("User authenticated via PHP session: " . $_SESSION['usuario_id']);
        return true;
    }
    error_log("User not authenticated");
    return false;
}

$forceDashboard = isset($_GET['dashboard']) || isset($_GET['home']);
$forceLanding   = isset($_GET['landing']);

if ($forceLanding) {
    error_log("Serving landing (forced via ?landing=1)");
    include __DIR__ . '/landing/index.html';
    exit;
}

if (isAuthenticated() || $forceDashboard) {
    error_log("Serving dashboard.html");
    include __DIR__ . '/dashboard.html';
    exit;
}

error_log("Serving public landing");
include __DIR__ . '/landing/index.html';
exit;
