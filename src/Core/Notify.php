<?php

namespace App\Core;

/**
 * Bildirim cephe sınıfı. Her mesaj önce notifications tablosuna yazılır,
 * sonra aktif sürücüye verilir. Varsayılan 'stub' dış API'ye gitmez —
 * satırı 'sent' işaretler; mesaj metni onay ekranında gösterilir (test).
 * Gerçek sağlayıcılar (netgsm/twilio) sonradan aynı arayüzle takılır (Faz 6).
 */
final class Notify
{
    private static function driver(): string
    {
        return (string) ($GLOBALS['config']['notify']['driver'] ?? 'stub');
    }

    /** Anında gönderim (onay / iptal bilgilendirme). Notification id döner. */
    public static function send(int $shopId, ?int $apptId, string $kind, string $recipient, string $body, string $channel = 'sms'): int
    {
        $stub   = self::driver() === 'stub';
        $status = $stub ? 'sent' : 'queued';
        return Database::insertReturningId(
            "INSERT INTO notifications (shop_id, appointment_id, channel, kind, recipient, body, status, sent_at)
             VALUES (:sh, :ap, :ch, :ki, :re, :bo, :st, :sa) RETURNING id",
            [
                ':sh' => $shopId, ':ap' => $apptId, ':ch' => $channel, ':ki' => $kind,
                ':re' => $recipient, ':bo' => $body, ':st' => $status,
                ':sa' => $stub ? date('c') : null,
            ]
        );
        // Gerçek sürücüde burada provider->sendSms($recipient,$body) çağrılır (Faz 6).
    }

    /** Hatırlatma kuyruğa: reminder_for anında cron tarafından gönderilecek (Faz 4). */
    public static function scheduleReminder(int $shopId, int $apptId, string $recipient, string $body, string $reminderForIso, string $channel = 'sms'): int
    {
        return Database::insertReturningId(
            "INSERT INTO notifications (shop_id, appointment_id, channel, kind, recipient, body, status, reminder_for)
             VALUES (:sh, :ap, :ch, 'reminder', :re, :bo, 'queued', :rf) RETURNING id",
            [':sh' => $shopId, ':ap' => $apptId, ':ch' => $channel, ':re' => $recipient, ':bo' => $body, ':rf' => $reminderForIso]
        );
    }

    /** Dükkân sahibine anlık bilgi (iptal/yeni randevu). */
    public static function notifyOwner(int $shopId, ?int $apptId, string $kind, string $body): void
    {
        $phone = Database::fetch('SELECT owner_phone FROM shops WHERE id=:s', [':s' => $shopId])['owner_phone'] ?? null;
        if ($phone) {
            self::send($shopId, $apptId, $kind, $phone, $body);
        }
    }
}
