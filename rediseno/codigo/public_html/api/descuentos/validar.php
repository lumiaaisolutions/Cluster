<?php
/**
 * descuentos/validar.php — Valida código QR de descuento.
 * GET /api/descuentos/validar.php?c=<codigo>
 * Sin auth (comerciante escanea con cualquier lector QR).
 *
 * Estados: 'valido', 'usado', 'invalido', 'expirado'
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/cors.php';
require_once __DIR__ . '/../../middleware/security-headers.php';

header('Content-Type: application/json; charset=utf-8');

$codigo = trim($_GET['c'] ?? '');
if (!$codigo || !preg_match('/^[a-f0-9]{16,64}$/i', $codigo)) {
    echo json_encode(['estado' => 'invalido', 'message' => 'Código inválido']);
    exit;
}

try {
    $pdo = (new Database())->getConnection();
    $stmt = $pdo->prepare("
        SELECT du.*, d.titulo, d.porcentaje_descuento, d.fecha_fin, e.nombre AS empresa
        FROM descuentos_usos du
        JOIN descuentos d ON d.id = du.descuento_id
        LEFT JOIN empresas_convenio e ON e.id = d.empresa_oferente_id
        WHERE du.codigo_unico = :c
    ");
    $stmt->execute([':c' => $codigo]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['estado' => 'invalido']);
        exit;
    }

    if ((int)$row['usado'] === 1) {
        echo json_encode([
            'estado' => 'usado',
            'fecha_validacion' => $row['fecha_validacion'],
            'descuento' => $row['titulo'],
            'empresa' => $row['empresa'],
        ]);
        exit;
    }

    // Verificar expiración
    $hoy = date('Y-m-d');
    if (!empty($row['fecha_fin']) && $row['fecha_fin'] < $hoy) {
        echo json_encode([
            'estado' => 'expirado',
            'descuento' => $row['titulo'],
            'fecha_fin' => $row['fecha_fin'],
        ]);
        exit;
    }

    // Marcar como usado
    $pdo->prepare("UPDATE descuentos_usos SET usado = 1, fecha_validacion = NOW() WHERE codigo_unico = :c")
        ->execute([':c' => $codigo]);

    echo json_encode([
        'estado' => 'valido',
        'descuento' => $row['titulo'],
        'porcentaje' => $row['porcentaje_descuento'],
        'empresa' => $row['empresa'],
        'validado_en' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['estado' => 'error', 'message' => 'Error interno']);
    error_log('[descuentos/validar] ' . $e->getMessage());
}
