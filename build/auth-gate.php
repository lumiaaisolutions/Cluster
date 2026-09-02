<?php
/**
 * Gate de autenticación de servidor para páginas administrativas estáticas (demo_*.html).
 *
 * Las páginas demo_* son paneles administrativos reales. Antes eran públicas
 * (regla "PÁGINAS DEMO PÚBLICAS" en .htaccess) y solo se protegían del lado
 * cliente con auth-session.js, lo cual es bypasseable con curl / view-source.
 *
 * .htaccess reescribe /demo_*.html hacia este gate, que valida la sesión PHP
 * (misma configuración que las APIs) y sirve el archivo solo si hay sesión activa.
 */

define('CLAUT_ACCESS', true);
require_once __DIR__ . '/config/session-config.php';
SessionConfig::init();

$page = basename($_GET['page'] ?? '');

// Whitelist estricta: solo archivos demo_*.html existentes en este directorio
if (!preg_match('/^demo_[a-z_]+\.html$/', $page) || !is_file(__DIR__ . '/' . $page)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Página no encontrada';
    exit;
}

$autenticado = !empty($_SESSION['user_email']) && !empty($_SESSION['user_rol']);

if (!$autenticado) {
    header('Location: /pages/sign-in.html?redirect=' . urlencode('/' . $page), true, 302);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
readfile(__DIR__ . '/' . $page);
