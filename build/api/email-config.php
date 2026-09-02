<?php
/**
 * email-config.php — API de Configuración de Notificaciones por Correo
 *
 * Gestiona los toggles del Panel de Control de Correos en el admin.
 * Solo accesible por administradores con sesión activa.
 *
 * GET  ?action=list           → Lista toda la configuración actual
 * POST { evento_tipo, activo, dirigido_a } → Actualiza un toggle
 * POST { action: 'test', evento_tipo }     → Envía correo de prueba
 */

define('CLAUT_ACCESS', true);
require_once dirname(__DIR__) . '/config/session-config.php';
SessionConfig::init();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env-loader.php';
require_once __DIR__ . '/../services/EmailService.php';

EnvLoader::load();

function jsonOut(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Verificar sesión admin ────────────────────────────────────────────────
$rol = $_SESSION['user_rol'] ?? '';
if (!in_array($rol, ['admin', 'Administrador', 'superadmin'], true)) {
    http_response_code(403);
    jsonOut(false, 'Acceso restringido a administradores.');
}

try {
    $db = Database::getInstance()->getConnection();

    // Auto-crear tabla si no existe (por si la migración aún no corrió)
    $db->exec("CREATE TABLE IF NOT EXISTS `email_notification_config` (
        `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
        `evento_tipo`  VARCHAR(50)    NOT NULL,
        `nombre`       VARCHAR(100)   NOT NULL,
        `descripcion`  VARCHAR(255)   DEFAULT NULL,
        `activo`       TINYINT(1)     NOT NULL DEFAULT 1,
        `dirigido_a`   ENUM('admin','todos','destinatario') NOT NULL DEFAULT 'admin',
        `icono`        VARCHAR(50)    DEFAULT 'fa-bell',
        `updated_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_evento_tipo` (`evento_tipo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insertar defaults si la tabla está vacía
    $count = $db->query("SELECT COUNT(*) FROM email_notification_config")->fetchColumn();
    if ((int)$count === 0) {
        $db->exec("INSERT INTO email_notification_config (evento_tipo, nombre, descripcion, activo, dirigido_a, icono) VALUES
            ('nuevo_evento',    'Nuevo Evento',            'Notifica cuando se crea un nuevo evento',          1, 'todos',        'fa-calendar-plus'),
            ('nuevo_descuento', 'Nuevo Descuento',         'Notifica cuando se publica un nuevo descuento',    1, 'todos',        'fa-tag'),
            ('nueva_empresa',   'Nueva Empresa Registrada','Alerta al admin cuando se registra una empresa',   1, 'admin',        'fa-building'),
            ('mensaje_buzon',   'Mensaje en Buzón',        'Copia por correo de notificaciones internas',      1, 'destinatario', 'fa-envelope')");
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET: Listar configuración ─────────────────────────────────────────
    if ($method === 'GET') {
        $rows = $db->query(
            "SELECT id, evento_tipo, nombre, descripcion, activo, dirigido_a, icono,
                    DATE_FORMAT(updated_at, '%d/%m/%Y %H:%i') AS ultima_modificacion
             FROM email_notification_config
             ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Convertir tipos
        foreach ($rows as &$row) {
            $row['activo']  = (bool)(int)$row['activo'];
            $row['id']      = (int)$row['id'];
        }

        jsonOut(true, 'Configuración cargada', ['config' => $rows]);
    }

    // ── POST: Actualizar toggle o enviar correo de prueba ─────────────────
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $input['action'] ?? 'update';

        // ── Prueba de correo ──────────────────────────────────────────────
        if ($action === 'test') {
            $adminEmail = EnvLoader::get('MAIL_ADMIN', EnvLoader::get('MAIL_USER'));
            $eventTipo  = htmlspecialchars($input['evento_tipo'] ?? 'prueba', ENT_QUOTES);

            $result = EmailService::sendNotification(
                $adminEmail,
                'Administrador',
                'Correo de prueba — ' . $eventTipo,
                "Este es un correo de prueba del sistema de notificaciones de la Intranet del Clúster.\n\n" .
                "Evento simulado: $eventTipo\n" .
                "Fecha: " . date('d/m/Y H:i:s') . "\n\n" .
                "Si recibes este correo, la configuración SMTP está funcionando correctamente."
            );

            jsonOut($result['success'], $result['success']
                ? "Correo de prueba enviado a $adminEmail"
                : "Error: " . $result['message']
            );
        }

        // ── Actualizar config ─────────────────────────────────────────────
        $eventoTipo = trim($input['evento_tipo'] ?? '');
        $activo     = isset($input['activo']) ? (int)(bool)$input['activo'] : null;
        $dirigidoA  = $input['dirigido_a'] ?? null;

        if (empty($eventoTipo)) {
            jsonOut(false, 'evento_tipo es requerido.');
        }

        // Verificar que el tipo existe
        $existe = $db->prepare("SELECT id FROM email_notification_config WHERE evento_tipo = ?");
        $existe->execute([$eventoTipo]);
        if (!$existe->fetch()) {
            jsonOut(false, "Tipo de notificación '$eventoTipo' no existe.");
        }

        // Construir UPDATE dinámico
        $sets   = [];
        $params = [];

        if ($activo !== null) {
            $sets[]   = 'activo = ?';
            $params[] = $activo;
        }
        if ($dirigidoA !== null && in_array($dirigidoA, ['admin', 'todos', 'destinatario'], true)) {
            $sets[]   = 'dirigido_a = ?';
            $params[] = $dirigidoA;
        }

        if (empty($sets)) {
            jsonOut(false, 'Nada que actualizar.');
        }

        $params[] = $eventoTipo;
        $db->prepare("UPDATE email_notification_config SET " . implode(', ', $sets) . " WHERE evento_tipo = ?")
           ->execute($params);

        $adminUser = $_SESSION['user_email'] ?? 'unknown';
        error_log("✅ [email-config] Admin '$adminUser' actualizó '$eventoTipo': " . json_encode(['activo' => $activo, 'dirigido_a' => $dirigidoA]));

        jsonOut(true, 'Configuración actualizada correctamente.');
    }

    jsonOut(false, 'Método no soportado.');

} catch (Exception $e) {
    error_log('[email-config] Error: ' . $e->getMessage());
    jsonOut(false, 'Error del servidor: ' . $e->getMessage());
}
?>
