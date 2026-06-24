<?php

namespace App\Core;

class Helpers
{
    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Cache-bust'lı asset URL'i (?v=mtime). */
    public static function asset(string $path): string
    {
        $p    = ltrim($path, '/');
        $full = dirname(__DIR__, 2) . '/public/' . $p;
        $v    = @filemtime($full);
        return '/' . $p . ($v ? '?v=' . $v : '');
    }

    /** Cloudflare arkasında gerçek ziyaretçi IP'si CF-Connecting-IP'de. */
    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                return (string) $_SERVER[$k];
            }
        }
        return '0.0.0.0';
    }

    /** İstekten türetilmiş şema+host (örn. https://xxx.trycloudflare.com). */
    public static function baseUrl(): string
    {
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
            ?? ((($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http');
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $proto . '://' . $host;
    }

    /** TR telefon → E.164 (+90XXXXXXXXXX); geçersizse null. */
    public static function normalizePhoneTr(string $raw): ?string
    {
        $d = preg_replace('/\D+/', '', $raw) ?? '';
        if (strpos($d, '0090') === 0)                       $d = substr($d, 4);
        elseif (strpos($d, '90') === 0 && strlen($d) === 12) $d = substr($d, 2);
        elseif (strpos($d, '0') === 0)                       $d = substr($d, 1);
        if (strlen($d) === 10 && $d[0] === '5') {
            return '+90' . $d;
        }
        return null;
    }

    /** Görüntüleme için telefon: +905551112233 → 0555 111 22 33 */
    public static function phoneDisplay(string $e164): string
    {
        if (preg_match('/^\+90(\d{3})(\d{3})(\d{2})(\d{2})$/', $e164, $m)) {
            return '0' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4];
        }
        return $e164;
    }

    public static function token(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Fiyat: 250.00 → "250 ₺", 12.50 → "12,50 ₺". */
    public static function money($v): string
    {
        $v = (float) $v;
        $dec = (fmod($v, 1.0) === 0.0) ? 0 : 2;
        return number_format($v, $dec, ',', '.') . ' ₺';
    }

    /** Dakika → "1 sa 30 dk" / "45 dk". */
    public static function dur(int $min): string
    {
        if ($min < 60) return $min . ' dk';
        $h = intdiv($min, 60);
        $m = $min % 60;
        return $m ? "{$h} sa {$m} dk" : "{$h} sa";
    }

    public const WEEKDAYS_TR = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
    public const MONTHS_TR   = ['','Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

    /** DateTimeImmutable → "24 Haziran Çarşamba". */
    public static function dateTr(\DateTimeInterface $d): string
    {
        return ((int) $d->format('j')) . ' ' . self::MONTHS_TR[(int) $d->format('n')]
            . ' ' . self::WEEKDAYS_TR[(int) $d->format('w')];
    }
}
