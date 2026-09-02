<?php
/**
 * API de Exportación — genera CSV/Excel-compatible y PDF imprimible.
 *
 * Endpoints:
 *   GET /api/export.php?tipo=empresas&formato=csv
 *   GET /api/export.php?tipo=empresas&formato=pdf
 *   GET /api/export.php?tipo=eventos&formato=csv
 *   GET /api/export.php?tipo=eventos&formato=pdf
 *
 * PDF se genera vía HTML imprimible con CSS @media print
 * (el navegador hace la conversión real con Cmd+P → Guardar como PDF).
 * Esto evita dependencias de TCPDF/Dompdf.
 */

ini_set('display_errors', '0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    define('CLAUT_ACCESS', true);
    require_once dirname(__DIR__) . '/config/session-config.php';
    SessionConfig::init();
    require_once __DIR__ . '/../config/database.php';

    if (empty($_SESSION['user_email'])) {
        http_response_code(401);
        echo 'No autenticado';
        exit;
    }

    $tipo = $_GET['tipo'] ?? 'empresas';
    $formato = $_GET['formato'] ?? 'csv';

    $db = Database::getInstance()->getConnection();

    // === Obtener datos según tipo ===
    $headers = [];
    $rows = [];
    $titulo = '';

    if ($tipo === 'empresas') {
        $titulo = 'Directorio de Empresas — Clúster Metropolitano';
        $headers = ['ID', 'Nombre', 'Sector', 'Sitio Web', 'Email', 'Teléfono', 'Estado', 'Registrado'];
        $stmt = $db->query(
            "SELECT id,
                    COALESCE(NULLIF(nombre,''), nombre_empresa) AS nombre,
                    sector, sitio_web, email, telefono, estado,
                    DATE_FORMAT(created_at, '%Y-%m-%d') AS registrado
               FROM empresas_convenio
              WHERE activo = 1
              ORDER BY nombre ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } elseif ($tipo === 'eventos') {
        $titulo = 'Eventos — Clúster Metropolitano';
        $headers = ['ID', 'Título', 'Fecha', 'Lugar', 'Modalidad'];
        $stmt = $db->query(
            "SELECT id, titulo,
                    DATE_FORMAT(fecha_evento, '%Y-%m-%d %H:%i') AS fecha,
                    lugar, modalidad
               FROM eventos
              ORDER BY fecha_evento DESC
              LIMIT 1000"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } else {
        http_response_code(400);
        echo 'Tipo no soportado';
        exit;
    }

    // === Generar CSV (Excel-compatible) ===
    if ($formato === 'csv') {
        $filename = $tipo . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // BOM UTF-8 (Excel detecta encoding)
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    // === Generar PDF (HTML imprimible) ===
    if ($formato === 'pdf') {
        header('Content-Type: text/html; charset=UTF-8');
        $totalRows = count($rows);
        $fecha = date('d/m/Y H:i');
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titulo) ?></title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; color: #0f172a; margin: 0; padding: 16px; }
        .header { border-bottom: 3px solid #C7252B; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { margin: 0; font-size: 18px; color: #0f172a; }
        .header p { margin: 4px 0 0; color: #64748b; font-size: 11px; }
        .actions { text-align: right; margin-bottom: 8px; }
        .actions button { padding: 8px 16px; background: #0f172a; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; }
        .actions button:hover { background: #C7252B; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th { background: #f1f5f9; padding: 8px; text-align: left; font-weight: 700; border-bottom: 2px solid #C7252B; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background: #fafbfc; }
        .footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 10px; display: flex; justify-content: space-between; }
        @media print {
            .actions { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>
    <div class="header">
        <h1><?= htmlspecialchars($titulo) ?></h1>
        <p>Generado el <?= $fecha ?> · <?= $totalRows ?> registros</p>
    </div>
    <table>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                        <td><?= htmlspecialchars((string)($cell ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="footer">
        <span>© <?= date('Y') ?> Clúster Automotriz Metropolitano</span>
        <span>intranet.clautmetropolitano.mx</span>
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
</body>
</html>
        <?php
        exit;
    }

    http_response_code(400);
    echo 'Formato no soportado (use csv o pdf)';
} catch (Throwable $e) {
    error_log('[api/export] ' . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
