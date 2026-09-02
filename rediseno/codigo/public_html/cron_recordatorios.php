<?php
/**
 * cron_recordatorios.php — Envía recordatorios de eventos próximos.
 * Configurar en Hostinger (cPanel → Cron Jobs):
 *   0 * * * * /usr/bin/php /home/USERNAME/public_html/cron_recordatorios.php > /dev/null 2>&1
 *
 * Lógica:
 *   - Cada hora busca eventos a 24h ± 30min y a 1h ± 30min de comenzar.
 *   - Envía email a TODOS los registros del evento.
 *   - Marca recordatorio_24h_enviado / recordatorio_1h_enviado para no duplicar.
 *   - Usa el SMTP existente (PHPMailer en /services/phpmailer).
 */

declare(strict_types=1);
date_default_timezone_set('America/Mexico_City');

$root = __DIR__;
require_once $root . '/config/database.php';

// Cargar .env mínimo
$envPath = $root . '/.env';
$env = [];
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

// Cargar PHPMailer
$phpMailerPath = $root . '/services/phpmailer';
require_once $phpMailerPath . '/Exception.php';
require_once $phpMailerPath . '/PHPMailer.php';
require_once $phpMailerPath . '/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function logLine(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents(__DIR__ . '/storage/cron_recordatorios.log', $line, FILE_APPEND);
}

function sendMail(array $env, string $to, string $name, string $subject, string $html): bool {
    try {
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host = $env['MAIL_HOST'] ?? 'localhost';
        $m->Port = (int)($env['MAIL_PORT'] ?? 587);
        $m->SMTPAuth = true;
        $m->Username = $env['MAIL_USER'] ?? '';
        $m->Password = $env['MAIL_PASS'] ?? '';
        $m->SMTPSecure = $env['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $m->CharSet = 'UTF-8';
        $m->setFrom($env['MAIL_FROM'] ?? 'auxsistemas@clautmetropolitano.mx', 'CLAUTMET');
        $m->addAddress($to, $name);
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body = $html;
        $m->AltBody = strip_tags($html);
        $m->send();
        return true;
    } catch (Throwable $e) {
        logLine("ERROR enviando a $to: " . $e->getMessage());
        return false;
    }
}

function emailTemplate(array $ev, int $horas): string {
    $titulo = htmlspecialchars($ev['titulo']);
    $fecha  = htmlspecialchars($ev['fecha_inicio']);
    $ubic   = htmlspecialchars($ev['ubicacion'] ?? 'Por confirmar');
    $when   = $horas === 24 ? 'mañana' : 'en una hora';
    return <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; max-width:600px; margin:0 auto; padding:24px; color:#1D1D1F">
  <div style="background:#FFFFFF; border:1px solid #E5E5E7; border-radius:16px; padding:32px">
    <div style="text-align:center; margin-bottom:24px">
      <strong style="font-size:14px; letter-spacing:0.1em; color:#C7252B">CLAUTMET</strong>
    </div>
    <h1 style="font-size:24px; font-weight:600; margin:0 0 16px">Tu evento es {$when}</h1>
    <p style="color:#6E6E73; font-size:16px; margin:0 0 24px">
      Te recordamos que <strong style="color:#1D1D1F">{$titulo}</strong> está programado.
    </p>
    <div style="background:#FAFAFA; border-radius:12px; padding:20px; margin:0 0 24px">
      <p style="margin:0 0 8px"><strong>Cuándo:</strong> {$fecha}</p>
      <p style="margin:0"><strong>Dónde:</strong> {$ubic}</p>
    </div>
    <p style="color:#6E6E73; font-size:14px; margin:0">
      Te esperamos. — Equipo Clúster Automotriz Metropolitano
    </p>
  </div>
</body></html>
HTML;
}

try {
    $pdo = (new Database())->getConnection();
    $now = new DateTime();

    $rangos = [
        ['horas' => 24, 'col' => 'recordatorio_24h_enviado', 'window' => 30],
        ['horas' => 1,  'col' => 'recordatorio_1h_enviado',  'window' => 30],
    ];

    $totalSent = 0;
    foreach ($rangos as $r) {
        $start = (clone $now)->modify("+{$r['horas']} hours")->modify("-{$r['window']} minutes");
        $end   = (clone $now)->modify("+{$r['horas']} hours")->modify("+{$r['window']} minutes");

        $stmt = $pdo->prepare("
            SELECT id, titulo, descripcion, fecha_inicio, ubicacion
            FROM eventos
            WHERE fecha_inicio BETWEEN :s AND :e
              AND estado = 'programado'
              AND ({$r['col']} IS NULL OR {$r['col']} = 0)
        ");
        $stmt->execute([':s' => $start->format('Y-m-d H:i:s'), ':e' => $end->format('Y-m-d H:i:s')]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($eventos as $ev) {
            $regs = $pdo->prepare("SELECT email, nombre FROM registros_eventos WHERE evento_id = :id AND email IS NOT NULL");
            $regs->execute([':id' => $ev['id']]);
            $rows = $regs->fetchAll(PDO::FETCH_ASSOC);

            $subject = "Recordatorio: {$ev['titulo']}";
            $html = emailTemplate($ev, $r['horas']);
            $sent = 0;
            foreach ($rows as $reg) {
                if (sendMail($env, $reg['email'], $reg['nombre'] ?? '', $subject, $html)) {
                    $sent++;
                }
            }
            $pdo->prepare("UPDATE eventos SET {$r['col']} = 1 WHERE id = :id")
                ->execute([':id' => $ev['id']]);
            $totalSent += $sent;
            logLine("Evento #{$ev['id']} ({$ev['titulo']}) — {$r['horas']}h: $sent emails enviados");
        }
    }

    logLine("Cron OK. Total emails enviados: $totalSent");
} catch (Throwable $e) {
    logLine('FATAL: ' . $e->getMessage());
}
