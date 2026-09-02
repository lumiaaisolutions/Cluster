<?php
/**
 * csrf-token.php — Devuelve token CSRF para inyectar en <meta>.
 * GET /api/csrf-token.php
 */
require_once __DIR__ . '/../config/session-config.php';
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../middleware/security-headers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode([
    'success' => true,
    'token'   => $_SESSION['csrf_token'],
]);
