<?php
/**
 * API del modo visitante (FEATURE-032, aprobado 2026-09-08).
 *
 * Acciones:
 *  - entrar   : crea la sesión de visitante (sin usuario), registra la visita
 *               y redirige al panel /visitante.html. Punto de entrada del botón
 *               "Entrar como visitante" del login.
 *  - check    : ¿hay sesión válida (visitante o usuario real)? JSON.
 *  - eventos  : eventos activos futuros marcados visible_visitantes=1 (público).
 *  - boletines: boletines en estado 'publicado' (público).
 *  - metrica  : registra un click de conversión (POST tipo). Sin PII.
 *  - stats    : contadores para el panel admin de Control de Acceso (solo admin).
 *
 * Privacidad: visitante_metricas guarda SOLO tipo + fecha. Ni IP ni user agent.
 */
define('CLAUT_ACCESS', true);
require_once __DIR__ . '/../config/session-config.php';
SessionConfig::init();

require_once __DIR__ . '/../config/database.php';

$action = $_GET['action'] ?? '';

function conexionVisitante() {
    return Database::getInstance()->getConnection();
}

function asegurarInfraVisitante($conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS visitante_metricas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(30) NOT NULL,
        creado DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tipo (tipo),
        INDEX idx_creado (creado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        $conn->exec("ALTER TABLE eventos ADD COLUMN visible_visitantes TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Exception $e) {
        // columna ya existe — esperado en cada llamada posterior a la primera
    }
}

function esVisitanteOUsuario() {
    return !empty($_SESSION['visitante']) || isset($_SESSION['user_email']);
}

function esAdmin() {
    $rol = strtolower($_SESSION['user_rol'] ?? '');
    return isset($_SESSION['user_email']) && in_array($rol, ['admin', 'administrador', 'root'], true);
}

switch ($action) {
    case 'entrar':
        try {
            $conn = conexionVisitante();
            asegurarInfraVisitante($conn);
            if (!isset($_SESSION['user_email'])) {
                $_SESSION['visitante'] = true;
            }
            $stmt = $conn->prepare("INSERT INTO visitante_metricas (tipo) VALUES ('visita')");
            $stmt->execute();
        } catch (Exception $e) {
            error_log('visitante entrar: ' . $e->getMessage());
        }
        session_write_close();
        header('Location: ../visitante.html');
        exit;

    case 'check':
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => esVisitanteOUsuario()]);
        exit;

    case 'eventos':
        header('Content-Type: application/json; charset=utf-8');
        if (!esVisitanteOUsuario()) { echo json_encode(['success' => false]); exit; }
        try {
            $conn = conexionVisitante();
            asegurarInfraVisitante($conn);
            $stmt = $conn->prepare("SELECT id, titulo, descripcion, fecha_inicio, ubicacion, tipo, imagen
                FROM eventos
                WHERE estado = 'activo' AND visible_visitantes = 1 AND fecha_inicio >= NOW()
                ORDER BY fecha_inicio ASC LIMIT 6");
            $stmt->execute();
            echo json_encode(['success' => true, 'eventos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            error_log('visitante eventos: ' . $e->getMessage());
            echo json_encode(['success' => false, 'eventos' => []]);
        }
        exit;

    case 'boletines':
        header('Content-Type: application/json; charset=utf-8');
        if (!esVisitanteOUsuario()) { echo json_encode(['success' => false]); exit; }
        try {
            $conn = conexionVisitante();
            $stmt = $conn->prepare("SELECT id, titulo, LEFT(contenido, 220) AS resumen, fecha_creacion, archivo_adjunto
                FROM boletines
                WHERE estado = 'publicado'
                ORDER BY fecha_creacion DESC LIMIT 6");
            $stmt->execute();
            echo json_encode(['success' => true, 'boletines' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            error_log('visitante boletines: ' . $e->getMessage());
            echo json_encode(['success' => false, 'boletines' => []]);
        }
        exit;

    case 'metrica':
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !esVisitanteOUsuario()) {
            echo json_encode(['success' => false]); exit;
        }
        $tipo = $_POST['tipo'] ?? '';
        $permitidos = ['click_registro', 'click_whatsapp', 'click_correo', 'click_evento', 'click_boletin'];
        if (!in_array($tipo, $permitidos, true)) { echo json_encode(['success' => false]); exit; }
        try {
            $conn = conexionVisitante();
            asegurarInfraVisitante($conn);
            $stmt = $conn->prepare("INSERT INTO visitante_metricas (tipo) VALUES (?)");
            $stmt->execute([$tipo]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
        }
        exit;

    case 'stats':
        header('Content-Type: application/json; charset=utf-8');
        if (!esAdmin()) { echo json_encode(['success' => false, 'message' => 'Solo administradores']); exit; }
        try {
            $conn = conexionVisitante();
            asegurarInfraVisitante($conn);
            $stmt = $conn->prepare("SELECT tipo, COUNT(*) AS total
                FROM visitante_metricas
                WHERE creado >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY tipo");
            $stmt->execute();
            $porTipo = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $porTipo[$fila['tipo']] = (int) $fila['total'];
            }
            echo json_encode(['success' => true, 'dias' => 7, 'stats' => $porTipo]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'stats' => []]);
        }
        exit;

    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        exit;
}
