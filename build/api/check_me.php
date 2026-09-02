<?php
/**
 * Endpoint de diagnóstico de sesión.
 * SECURITY: nunca exponer $_SESSION completo ni session_id en la respuesta
 * (fuga de datos de sesión — misma clase de vulnerabilidad que se corrigió
 * en login-compatible.php). Solo se devuelve el rol detectado.
 */
define('CLAUT_ACCESS', true);
require_once dirname(__DIR__) . '/config/session-config.php';
SessionConfig::init();

header('Content-Type: application/json');

$rol = strtolower(
    $_SESSION['user_rol'] ??
    $_SESSION['usuario_rol'] ??
    $_SESSION['rol'] ??
    $_SESSION['user_role'] ??
    $_SESSION['usuario_tipo'] ??
    'none'
);

$autenticado = !empty($_SESSION['user_email']);

echo json_encode([
    'success' => true,
    'authenticated' => $autenticado,
    'detected_role' => $autenticado ? $rol : 'none',
    'is_admin' => $autenticado && in_array($rol, ['admin', 'administrador', 'root'], true)
]);
