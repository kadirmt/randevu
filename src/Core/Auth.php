<?php

namespace App\Core;

/**
 * Oturum tabanlı kimlik doğrulama (iki tip: 'shop' = dükkan sahibi, 'super' =
 * süper-admin). Personelin girişi YOK. Brute-force koruması RateLimiter ile.
 * "Beni hatırla" şimdilik yok (oturum süresi tarayıcı kapanana dek).
 */
final class Auth
{
    private const TABLES = ['shop' => 'shop_admins', 'super' => 'super_admins'];

    /** @return array{ok:bool,error?:string,row?:array} */
    public static function attempt(string $type, string $email, string $password): array
    {
        $table = self::TABLES[$type] ?? null;
        if (!$table) return ['ok' => false, 'error' => 'Geçersiz giriş tipi.'];

        $ip = Helpers::clientIp();
        if ($block = RateLimiter::activeBlock($ip, $type)) {
            $s = RateLimiter::secondsLeft($block);
            return ['ok' => false, 'error' => "Çok fazla deneme. {$s} sn sonra tekrar deneyin."];
        }

        $email = mb_strtolower(trim($email));
        $row = Database::fetch("SELECT * FROM {$table} WHERE email = :e", [':e' => $email]);
        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            RateLimiter::recordFail($ip, $type, $email);
            Audit::log($type, null, 'login_fail', null, null, null, $email);
            return ['ok' => false, 'error' => 'E-posta veya parola hatalı.'];
        }

        RateLimiter::recordSuccess($ip, $type);
        session_regenerate_id(true);
        $_SESSION['auth_type'] = $type;
        $_SESSION['auth_id']   = (int) $row['id'];
        Audit::log($type, (int) $row['id'], 'login', $type === 'shop' ? (int) $row['shop_id'] : null);
        return ['ok' => true, 'row' => $row];
    }

    public static function user(string $type): ?array
    {
        if (($_SESSION['auth_type'] ?? null) !== $type || empty($_SESSION['auth_id'])) {
            return null;
        }
        $table = self::TABLES[$type] ?? null;
        if (!$table) return null;
        $row = Database::fetch("SELECT * FROM {$table} WHERE id = :i", [':i' => (int) $_SESSION['auth_id']]);
        if (!$row) { self::logout(); return null; }
        return $row;
    }

    public static function shopAdmin(): ?array  { return self::user('shop'); }
    public static function superAdmin(): ?array { return self::user('super'); }

    public static function logout(): void
    {
        $type = $_SESSION['auth_type'] ?? null;
        $id   = $_SESSION['auth_id'] ?? null;
        if ($type) Audit::log((string) $type, $id ? (int) $id : null, 'logout');
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
        }
    }
}
