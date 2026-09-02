<?php
/**
 * Verifica si un email ya está registrado a un evento.
 * Consumido por eventos.html y evento_detalle.php:
 *   GET ./check_registro.php?evento_id=N&email=...
 * Respuesta: { status: 'registered'|'not_registered', data: { estado_registro, fecha_registro } }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://intranet.clautmetropolitano.mx');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config/database.php';

$eventoId = isset($_GET['evento_id']) ? intval($_GET['evento_id']) : 0;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if ($eventoId <= 0 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parámetros evento_id y email requeridos']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    // NOTA: la tabla de producción usa la columna `estado` (no `estado_registro`);
    // el frontend espera la clave `estado_registro`, se mapea aquí.
    $stmt = $db->prepare(
        "SELECT estado, fecha_registro
         FROM registros_eventos
         WHERE evento_id = ? AND email = ?
         ORDER BY fecha_registro DESC
         LIMIT 1"
    );
    $stmt->execute([$eventoId, $email]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        echo json_encode([
            'status' => 'registered',
            'data' => [
                'estado_registro' => $registro['estado'],
                'fecha_registro' => $registro['fecha_registro']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'not_registered', 'data' => null]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al verificar el registro']);
}
