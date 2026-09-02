<?php
/**
 * TOTP — Time-based One-Time Password (RFC 6238)
 *
 * Implementación pura PHP sin dependencias externas.
 * Compatible con Google Authenticator, Authy, 1Password, Microsoft Authenticator.
 *
 * USO:
 *   $secret = TOTP::generateSecret();                // base32 random 32 chars
 *   $uri = TOTP::buildUri($secret, $email, 'Clúster Intranet');  // URI para QR
 *   $ok = TOTP::verify($secret, $code);              // valida código de 6 dígitos
 */

if (!class_exists('TOTP')) {

class TOTP
{
    const DIGITS = 6;
    const PERIOD = 30;        // segundos
    const ALGORITHM = 'sha1';

    /** Genera un secret aleatorio en Base32 (recomendado 160 bits = 32 chars Base32) */
    public static function generateSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /** Construye URI otpauth:// para QR codes */
    public static function buildUri(string $secret, string $account, string $issuer = 'Clúster Intranet'): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALGORITHM),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** Verifica código contra el secret (con tolerancia ±1 periodo para clock skew) */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) return false;

        $currentSlot = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::generateCode($secret, $currentSlot + $i);
            if (hash_equals($expected, $code)) return true;
        }
        return false;
    }

    /** Genera código para un slot dado */
    public static function generateCode(string $secret, ?int $slot = null): string
    {
        $slot = $slot ?? (int) floor(time() / self::PERIOD);
        $key = self::base32Decode($secret);
        $bin = pack('N*', 0) . pack('N*', $slot); // big-endian 64-bit

        $hash = hash_hmac(self::ALGORITHM, $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset])    & 0x7F) << 24) |
            ((ord($hash[$offset+1]) & 0xFF) << 16) |
            ((ord($hash[$offset+2]) & 0xFF) <<  8) |
             (ord($hash[$offset+3]) & 0xFF)
        );
        $code = $value % (10 ** self::DIGITS);
        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Decodifica Base32 → bytes */
    private static function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(str_replace('=', '', $b32));
        $binary = '';
        $bits = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos($alphabet, $b32[$i]);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $binary .= chr(bindec(substr($bits, $i, 8)));
        }
        return $binary;
    }

    /** Genera N códigos de recuperación */
    public static function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
        }
        return $codes;
    }
}

} // class_exists
