<?php

namespace App\Core;

/**
 * Login brute-force koruması. (IP, user_type) başına başarısız denemeler bir
 * pencere içinde sayılır; eşik aşılınca IP sabit süre bloklanır. Başarılı giriş
 * sayacı sıfırlar. Yalnız başarısız denemeler bloğa sayılır.
 */
final class RateLimiter
{
    public const MAX_FAILS  = 8;
    public const WINDOW_MIN = 10;
    public const BLOCK_MIN  = 10;

    public static function activeBlock(string $ip, string $type): ?array
    {
        return Database::fetch(
            'SELECT * FROM login_blocks
             WHERE ip=:ip AND user_type=:t AND blocked_until > NOW()
             ORDER BY blocked_until DESC LIMIT 1',
            [':ip' => $ip, ':t' => $type]
        );
    }

    public static function isBlocked(string $ip, string $type): bool
    {
        return self::activeBlock($ip, $type) !== null;
    }

    public static function secondsLeft(array $block): int
    {
        $until = strtotime((string) ($block['blocked_until'] ?? 'now'));
        return $until ? max(0, $until - time()) : 0;
    }

    public static function recordFail(string $ip, string $type, ?string $identifier): void
    {
        Database::execute(
            'INSERT INTO login_attempts (ip, user_type, identifier, user_agent, successful)
             VALUES (:ip,:t,:id,:ua,FALSE)',
            [':ip' => $ip, ':t' => $type, ':id' => $identifier ? mb_substr($identifier, 0, 255) : null,
             ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000)]
        );
        $fails = (int) (Database::fetch(
            "SELECT COUNT(*) AS n FROM login_attempts
             WHERE ip=:ip AND user_type=:t AND successful=FALSE
               AND created_at > NOW() - (:w || ' minutes')::interval",
            [':ip' => $ip, ':t' => $type, ':w' => self::WINDOW_MIN]
        )['n'] ?? 0);

        if ($fails >= self::MAX_FAILS) {
            Database::execute(
                "INSERT INTO login_blocks (ip, user_type, identifier, user_agent, attempts, blocked_until)
                 VALUES (:ip,:t,:id,:ua,:n, NOW() + (:b || ' minutes')::interval)",
                [':ip' => $ip, ':t' => $type, ':id' => $identifier ? mb_substr($identifier, 0, 255) : null,
                 ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
                 ':n' => $fails, ':b' => self::BLOCK_MIN]
            );
        }
    }

    public static function recordSuccess(string $ip, string $type): void
    {
        Database::execute(
            'INSERT INTO login_attempts (ip, user_type, successful) VALUES (:ip,:t,TRUE)',
            [':ip' => $ip, ':t' => $type]
        );
        // Aktif blokları kaldır (temiz başlangıç)
        Database::execute(
            'DELETE FROM login_blocks WHERE ip=:ip AND user_type=:t',
            [':ip' => $ip, ':t' => $type]
        );
    }
}
