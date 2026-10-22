<?php
declare(strict_types=1);

/**
 * Normalización de teléfonos a E.164. Enfocado en Colombia (+57), con
 * soporte para números que ya vienen en formato internacional.
 */
final class PhoneNormalizer
{
    public static function normalize(?string $raw, string $pais = 'CO'): ?string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/^whatsapp:/i', '', $s) ?? $s;
        $s = preg_replace('/[^\d+]/', '', $s) ?? '';
        if ($s === '') {
            return null;
        }

        if (str_starts_with($s, '00')) {
            $s = '+' . substr($s, 2);
        }

        if (str_starts_with($s, '+')) {
            $digits = preg_replace('/\D/', '', $s) ?? '';
            return self::valid('+' . $digits);
        }

        $digits = preg_replace('/\D/', '', $s) ?? '';
        if ($digits === '') {
            return null;
        }

        if ($pais === 'CO') {
            // 57 + 10 dígitos (celular) o 57 + 7 dígitos (fijo)
            if (str_starts_with($digits, '57') && strlen($digits) >= 11 && strlen($digits) <= 13) {
                return self::valid('+' . $digits);
            }
            // Celular local: 3XX XXX XXXX (10 dígitos)
            if (strlen($digits) === 10 && $digits[0] === '3') {
                return self::valid('+57' . $digits);
            }
            // Fijo local: 7 dígitos
            if (strlen($digits) === 7) {
                return self::valid('+57' . $digits);
            }
            // Algunos exportadores dejan 0 inicial (0 3XX...)
            if (strlen($digits) === 11 && $digits[0] === '0' && $digits[1] === '3') {
                return self::valid('+57' . substr($digits, 1));
            }
        }

        // Genérico: si ya tiene 11-15 dígitos se asume internacional.
        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return self::valid('+' . $digits);
        }

        return null;
    }

    public static function isEmail(?string $email): bool
    {
        $email = trim((string) $email);
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        return self::isEmail($email) ? $email : null;
    }

    public static function display(?string $address): string
    {
        $address = trim((string) $address);
        if ($address === '') {
            return '—';
        }
        return preg_replace('/^whatsapp:/i', '', $address) ?? $address;
    }

    private static function valid(string $e164): ?string
    {
        if (!preg_match('/^\+\d{8,15}$/', $e164)) {
            return null;
        }
        return $e164;
    }
}
