<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;

/**
 * Randevu oluşturma/iptal. Online yazımda asıl çift-rezervasyon güvencesi DB'nin
 * no_overlap_per_staff EXCLUDE kısıtıdır (23P01 → "slot doldu"). Sahip override'ı
 * is_override=true ile bu kısıttan muaf (Faz 3).
 */
final class Booking
{
    /** @return array{ok:bool,error?:string,appointment?:array,service?:array,staff?:array,token?:string} */
    public static function create(int $shopId, int $staffId, int $serviceId, string $startIso, string $name, string $surname, string $phoneE164): array
    {
        $service = Database::fetch(
            'SELECT id, name, duration_min, price FROM services WHERE id=:i AND shop_id=:s AND is_active = TRUE',
            [':i' => $serviceId, ':s' => $shopId]
        );
        if (!$service) return ['ok' => false, 'error' => 'Hizmet bulunamadı.'];

        $staff = Database::fetch(
            'SELECT id, name FROM staff WHERE id=:i AND shop_id=:s AND is_active = TRUE',
            [':i' => $staffId, ':s' => $shopId]
        );
        if (!$staff) return ['ok' => false, 'error' => 'Personel bulunamadı.'];

        $zone = new \DateTimeZone('Europe/Istanbul');
        try {
            $start = new \DateTimeImmutable($startIso, $zone);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Geçersiz saat.'];
        }
        if ($start < new \DateTimeImmutable('now', $zone)) {
            return ['ok' => false, 'error' => 'Geçmiş bir saat seçilemez.'];
        }
        $end   = $start->modify('+' . (int) $service['duration_min'] . ' minutes');
        $token = Helpers::token(16); // 32 hex

        try {
            $id = Database::insertReturningId(
                "INSERT INTO appointments
                   (shop_id, staff_id, service_id, customer_name, customer_surname, customer_phone,
                    service_price, starts_at, ends_at, status, source, cancel_token)
                 VALUES (:sh,:st,:sv,:n,:su,:ph,:pr,:s,:e,'booked','online',:tok)
                 RETURNING id",
                [
                    ':sh' => $shopId, ':st' => $staffId, ':sv' => $serviceId,
                    ':n' => $name, ':su' => $surname, ':ph' => $phoneE164,
                    ':pr' => $service['price'], ':s' => $start->format('c'), ':e' => $end->format('c'),
                    ':tok' => $token,
                ]
            );
        } catch (\PDOException $e) {
            if ($e->getCode() === '23P01' || str_contains($e->getMessage(), 'no_overlap_per_staff')) {
                return ['ok' => false, 'error' => 'Bu saat az önce doldu, lütfen başka bir saat seçin.'];
            }
            error_log('[randevu] booking insert: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Randevu oluşturulamadı, tekrar deneyin.'];
        }

        $appt = Database::fetch('SELECT * FROM appointments WHERE id=:i', [':i' => $id]);
        return ['ok' => true, 'appointment' => $appt, 'service' => $service, 'staff' => $staff, 'token' => $token];
    }

    /** Şifresiz iptal. @return array{ok:bool,error?:string,appointment?:array} */
    public static function cancelByToken(string $token): array
    {
        $appt = Database::fetch('SELECT * FROM appointments WHERE cancel_token=:t', [':t' => $token]);
        if (!$appt) {
            // Belki zaten iptal edilmiş (token NULL'a çekildi) — bulamadık demektir.
            return ['ok' => false, 'error' => 'not_found'];
        }
        $n = Database::execute(
            "UPDATE appointments SET status='cancelled', cancelled_at=NOW(), cancel_token=NULL
             WHERE cancel_token=:t AND status='booked' AND starts_at > NOW()",
            [':t' => $token]
        );
        if ($n === 0) {
            return ['ok' => false, 'error' => 'expired', 'appointment' => $appt];
        }
        return ['ok' => true, 'appointment' => $appt];
    }
}
