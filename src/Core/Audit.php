<?php

namespace App\Core;

/** Uygulama denetim günlüğü — kim, ne, ne zaman, hangi IP. Best-effort. */
final class Audit
{
    public static function log(
        string $actorType,
        ?int $actorId,
        string $action,
        ?int $shopId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $detail = null
    ): void {
        try {
            Database::execute(
                'INSERT INTO audit_log (actor_type, actor_id, shop_id, action, target_type, target_id, detail, ip)
                 VALUES (:at, :aid, :sid, :ac, :tt, :ti, :d, :ip)',
                [
                    ':at'  => mb_substr($actorType, 0, 16),
                    ':aid' => $actorId,
                    ':sid' => $shopId,
                    ':ac'  => mb_substr($action, 0, 64),
                    ':tt'  => $targetType !== null ? mb_substr($targetType, 0, 32) : null,
                    ':ti'  => $targetId,
                    ':d'   => $detail !== null ? mb_substr($detail, 0, 500) : null,
                    ':ip'  => Helpers::clientIp(),
                ]
            );
        } catch (\Throwable $e) {
            error_log('[randevu] Audit::log ' . $e->getMessage());
        }
    }
}
