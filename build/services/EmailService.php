<?php
/**
 * EmailService — Servicio Central de Correo Claut Intranet
 *
 * Motor de envío de correos vía SMTP usando PHPMailer standalone.
 * Toda la configuración se lee del .env vía EnvLoader.
 *
 * USO:
 *   require_once __DIR__ . '/../services/EmailService.php';
 *   $result = EmailService::sendPasswordReset($email, $nombre, $token);
 *
 * IMPORTANTE:
 *   - NUNCA hardcodear credenciales aquí — solo leer de EnvLoader
 *   - Todos los métodos retornan ['success' => bool, 'message' => string]
 *   - El envío es best-effort: un fallo NO debe detener el flujo principal
 */

// Carga PHPMailer standalone (sin Composer)
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Carga variables de entorno
if (!class_exists('EnvLoader')) {
    require_once __DIR__ . '/../config/env-loader.php';
}
EnvLoader::load();

class EmailService
{
    // ─── Paleta Corporativa ──────────────────────────────────────────────
    private const BRAND_RED    = '#C7252B';
    private const BRAND_DARK   = '#1e293b';
    private const BRAND_LIGHT  = '#f8fafc';
    private const BRAND_NAME   = 'Clúster Automotriz Metropolitano';
    private const APP_URL      = 'https://intranet.clautmetropolitano.mx';

    // ─── Crear instancia PHPMailer configurada ───────────────────────────
    private static function buildMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true); // true = lanzar excepciones

        // SMTP config desde .env
        $mailer->isSMTP();
        $mailer->Host       = EnvLoader::get('MAIL_HOST', 'smtp.hostinger.com');
        $mailer->SMTPAuth   = true;
        $mailer->Username   = EnvLoader::get('MAIL_USER', '');
        $mailer->Password   = EnvLoader::get('MAIL_PASS', '');
        $mailer->Port       = (int) EnvLoader::get('MAIL_PORT', 465);

        // Cifrado por puerto
        $encryption = EnvLoader::get('MAIL_ENCRYPTION', 'ssl');
        $mailer->SMTPSecure = ($encryption === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS
                                                      : PHPMailer::ENCRYPTION_SMTPS;
        // Remitente
        $fromEmail = EnvLoader::get('MAIL_FROM', 'auxsistemas@clautmetropolitano.mx');
        $fromName  = EnvLoader::get('MAIL_FROM_NAME', 'Claut Intranet');
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->CharSet   = 'UTF-8';
        $mailer->isHTML(true);

        // Debug: solo en desarrollo
        $mailer->SMTPDebug = (EnvLoader::get('APP_ENV', 'production') === 'development')
            ? SMTP::DEBUG_SERVER
            : SMTP::DEBUG_OFF;

        return $mailer;
    }

    // ─── Envío genérico interno ──────────────────────────────────────────
    private static function send(string $toEmail, string $toName, string $subject, string $htmlBody): array
    {
        try {
            $mailer = self::buildMailer();
            $mailer->addAddress($toEmail, $toName);
            $mailer->Subject = $subject;
            $mailer->Body    = $htmlBody;
            $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

            $mailer->send();

            error_log("✅ [EmailService] Correo enviado a: $toEmail | Asunto: $subject");
            return ['success' => true, 'message' => 'Correo enviado correctamente'];

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("❌ [EmailService] Error enviando a $toEmail: $errorMsg");
            return ['success' => false, 'message' => 'Error al enviar correo: ' . $errorMsg];
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // MÉTODOS PÚBLICOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Correo de recuperación de contraseña
     */
    public static function sendPasswordReset(string $email, string $nombre, string $token): array
    {
        $resetUrl = self::APP_URL . '/pages/sign-in.html?reset_token=' . urlencode($token);
        $subject  = 'Recupera tu contraseña — Clúster Intranet';

        $body = self::wrapTemplate(
            'Recuperación de Contraseña',
            "
            <p style=\"color: #475569; font-size: 16px; line-height: 1.7;\">
                Hola <strong style=\"color: " . self::BRAND_DARK . ";\">$nombre</strong>,
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                Recibimos una solicitud para restablecer la contraseña de tu cuenta en la Intranet del
                <strong>" . self::BRAND_NAME . "</strong>.
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                Haz clic en el botón para crear una nueva contraseña. Este enlace es válido durante
                <strong>1 hora</strong>.
            </p>
            " . self::ctaButton($resetUrl, 'Restablecer Contraseña') . "
            <p style=\"margin-top: 24px; color: #94a3b8; font-size: 13px; line-height: 1.6;\">
                Si no solicitaste este cambio, ignora este correo. Tu contraseña actual permanece sin cambios.
            </p>
            <p style=\"color: #94a3b8; font-size: 12px; margin-top: 8px;\">
                ¿Problemas con el botón? Copia y pega este enlace en tu navegador:<br>
                <a href=\"$resetUrl\" style=\"color: " . self::BRAND_RED . "; word-break: break-all;\">$resetUrl</a>
            </p>"
        );

        return self::send($email, $nombre, $subject, $body);
    }

    /**
     * Correo de verificación de cuenta al registrarse
     */
    public static function sendAccountVerification(string $email, string $nombre, string $token): array
    {
        $verifyUrl = self::APP_URL . '/api/auth/verify-account.php?token=' . urlencode($token);
        $subject   = 'Verifica tu cuenta — Clúster Intranet';

        $body = self::wrapTemplate(
            'Verifica tu Cuenta',
            "
            <p style=\"color: #475569; font-size: 16px; line-height: 1.7;\">
                ¡Bienvenido/a a la Intranet, <strong style=\"color: " . self::BRAND_DARK . ";\">$nombre</strong>!
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                Tu registro en el <strong>" . self::BRAND_NAME . "</strong> fue recibido y está
                <strong>pendiente de aprobación</strong> por un administrador.
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                Para agilizar el proceso, por favor confirma tu dirección de correo electrónico:
            </p>
            " . self::ctaButton($verifyUrl, 'Confirmar mi Correo') . "
            <div style=\"background: #f1f5f9; border-radius: 12px; padding: 16px; margin-top: 20px;\">
                <p style=\"color: #64748b; font-size: 13px; margin: 0;\">
                    Una vez que confirmes tu correo y un administrador apruebe tu cuenta,
                    recibirás otro correo para iniciar sesión.
                </p>
            </div>"
        );

        return self::send($email, $nombre, $subject, $body);
    }

    /**
     * Correo cuando el admin aprueba una cuenta
     */
    public static function sendAccountApproved(string $email, string $nombre): array
    {
        $loginUrl = self::APP_URL . '/pages/sign-in.html';
        $subject  = '¡Tu cuenta fue aprobada! — Clúster Intranet';

        $body = self::wrapTemplate(
            '¡Cuenta Aprobada!',
            "
            <p style=\"color: #475569; font-size: 16px; line-height: 1.7;\">
                ¡Excelente noticia, <strong style=\"color: " . self::BRAND_DARK . ";\">$nombre</strong>!
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                Tu cuenta en la Intranet del <strong>" . self::BRAND_NAME . "</strong> ha sido
                <strong style=\"color: #16a34a;\">aprobada y activada</strong>.
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                Ya puedes acceder al sistema con las credenciales que registraste:
            </p>
            " . self::ctaButton($loginUrl, 'Acceder al Sistema') . "
            <div style=\"background: #f0fdf4; border-left: 4px solid #16a34a; border-radius: 8px; padding: 16px; margin-top: 20px;\">
                <p style=\"color: #15803d; font-size: 13px; margin: 0;\">
                    Accede con tu correo electrónico y la contraseña que definiste al registrarte.
                </p>
            </div>"
        );

        return self::send($email, $nombre, $subject, $body);
    }

    /**
     * Correo cuando el admin rechaza una cuenta
     */
    public static function sendAccountRejected(string $email, string $nombre, string $razon = ''): array
    {
        $subject = 'Solicitud de cuenta — Clúster Intranet';

        $razonTexto = $razon
            ? "<p style=\"color: #475569; font-size: 14px; margin-top: 8px;\"><strong>Motivo:</strong> " . htmlspecialchars($razon) . "</p>"
            : '';

        $body = self::wrapTemplate(
            'Solicitud No Aprobada',
            "
            <p style=\"color: #475569; font-size: 16px; line-height: 1.7;\">
                Hola <strong style=\"color: " . self::BRAND_DARK . ";\">$nombre</strong>,
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                Lamentamos informarte que tu solicitud de acceso a la Intranet del
                <strong>" . self::BRAND_NAME . "</strong> no fue aprobada en esta ocasión.
            </p>
            $razonTexto
            <div style=\"background: #fef2f2; border-left: 4px solid " . self::BRAND_RED . "; border-radius: 8px; padding: 16px; margin-top: 20px;\">
                <p style=\"color: #991b1b; font-size: 13px; margin: 0;\">
                    Si consideras que esto es un error, por favor contacta a tu coordinador o al
                    área de sistemas del Clúster.
                </p>
            </div>"
        );

        return self::send($email, $nombre, $subject, $body);
    }

    /**
     * Correo de alerta/notificación básica
     */
    public static function sendNotification(string $email, string $nombre, string $titulo, string $contenido): array
    {
        $subject = $titulo . ' — Clúster Intranet';

        $body = self::wrapTemplate(
            $titulo,
            "
            <p style=\"color: #475569; font-size: 16px; line-height: 1.7;\">
                Hola <strong style=\"color: " . self::BRAND_DARK . ";\">$nombre</strong>,
            </p>
            <p style=\"color: #475569; font-size: 15px; line-height: 1.7;\">
                " . nl2br(htmlspecialchars($contenido)) . "
            </p>
            " . self::ctaButton(self::APP_URL . '/dashboard.html', 'Ver en el Sistema')
        );

        return self::send($email, $nombre, $subject, $body);
    }

    /**
     * Alerta de seguridad para el administrador del sistema
     */
    public static function sendAdminAlert(string $titulo, string $contenido): array
    {
        $adminEmail = EnvLoader::get('MAIL_ADMIN', 'auxsistemas@clautmetropolitano.mx');
        return self::sendNotification($adminEmail, 'Administrador', $titulo, $contenido);
    }

    // ════════════════════════════════════════════════════════════════════
    // HELPERS DE PLANTILLA HTML
    // ════════════════════════════════════════════════════════════════════

    /**
     * Envuelve el contenido en el layout HTML base con branding Claut
     */
    private static function wrapTemplate(string $titulo, string $contenido): string
    {
        $year      = date('Y');
        $brandRed  = self::BRAND_RED;
        $brandDark = self::BRAND_DARK;
        $brandName = self::BRAND_NAME;
        $appUrl    = self::APP_URL;

        // NOTA: emails usan colores SÓLIDOS (no gradientes) y atributo bgcolor para
        // máxima compatibilidad con Apple Mail, Gmail, Outlook. Forzamos light mode
        // para evitar que el cliente invierta colores automáticamente.
        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>$titulo — Clúster Intranet</title>
    <style type="text/css">
        :root { color-scheme: light only; supported-color-schemes: light only; }
        body { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; -ms-interpolation-mode: bicubic; }
        /* Forzar light mode en Apple Mail / iOS Mail */
        @media (prefers-color-scheme: dark) {
            .light-bg     { background-color: #f1f5f9 !important; }
            .card         { background-color: #ffffff !important; }
            .header-cell  { background-color: #C7252B !important; }
            .text-dark    { color: #1e293b !important; }
            .text-muted   { color: #475569 !important; }
            .text-light-muted { color: #94a3b8 !important; }
            .cta-btn      { background-color: #C7252B !important; color: #ffffff !important; }
        }
    </style>
</head>
<body class="light-bg" style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background-color:#f1f5f9; color:#1e293b;" bgcolor="#f1f5f9">

    <!-- Preheader hidden -->
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">$titulo</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="light-bg" style="background-color:#f1f5f9;" bgcolor="#f1f5f9">
        <tr>
            <td align="center" style="padding: 40px 20px;">

                <!-- Card -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="card" style="background-color:#ffffff; border-radius:16px; overflow:hidden; max-width:600px;" bgcolor="#ffffff">

                    <!-- Header (color sólido, no gradient) -->
                    <tr>
                        <td class="header-cell" align="center" style="background-color:$brandRed; padding: 36px 40px;" bgcolor="$brandRed">
                            <p style="margin:0; color:#ffffff; font-size:11px; font-weight:bold; letter-spacing:3px; text-transform:uppercase; opacity:0.92;">
                                Clúster Automotriz Metropolitano
                            </p>
                            <h1 class="text-light" style="margin:10px 0 0; color:#ffffff; font-size:24px; font-weight:bold; letter-spacing:-0.3px; line-height:1.3;">
                                $titulo
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="text-dark" style="padding: 36px 40px; color:#1e293b; font-size:15px; line-height:1.65;">
                            $contenido
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:1px solid #e2e8f0; font-size:0; line-height:0;">&nbsp;</td></tr></table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" class="card" style="padding: 24px 40px 32px; background-color:#ffffff;" bgcolor="#ffffff">
                            <p class="text-muted" style="margin:0; color:#64748b; font-size:12px; line-height:1.7;">
                                Este es un correo automático del sistema Intranet del<br>
                                <strong class="text-dark" style="color:$brandDark;">$brandName</strong>.<br>
                                Por favor no respondas directamente a este mensaje.
                            </p>
                            <p class="text-light-muted" style="margin: 14px 0 0; color:#94a3b8; font-size:11px;">
                                © $year $brandName &middot; <a href="$appUrl" style="color:#94a3b8; text-decoration:underline;">intranet.clautmetropolitano.mx</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera un botón CTA — color sólido + bordes para máxima compatibilidad
     */
    private static function ctaButton(string $url, string $texto): string
    {
        $brandRed = self::BRAND_RED;
        return <<<HTML
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 32px 0;">
    <tr>
        <td align="center">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="$url" style="height:48px;v-text-anchor:middle;width:240px;" arcsize="29%" stroke="f" fillcolor="$brandRed">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:15px;font-weight:bold;">$texto</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-- -->
            <a href="$url" class="cta-btn" style="display:inline-block; background-color:$brandRed; color:#ffffff !important; text-decoration:none; font-weight:bold; font-size:15px; padding: 14px 36px; border-radius:12px; border: 2px solid $brandRed; mso-padding-alt:0; letter-spacing:0.3px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;" bgcolor="$brandRed">
                <span style="color:#ffffff !important; text-decoration:none;">$texto</span>
            </a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
HTML;
    }
}
?>
