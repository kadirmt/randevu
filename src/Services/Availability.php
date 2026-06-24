<?php

namespace App\Services;

use App\Core\Database;

/**
 * Müsaitlik hesabı: (dükkan, personel, hizmet, tarih) için uygun başlangıç
 * saatleri. Saf/yan-etkisiz. Tüm zaman matematiği dükkan TZ'sinde (duvar-saati);
 * saklama UTC timestamptz → DST günleri inşaen doğru.
 */
final class Availability
{
    /** @return array<int,array{start:string,end:string,label:string}> */
    public static function slots(int $shopId, int $staffId, int $serviceId, string $dateYmd, string $tz = 'Europe/Istanbul'): array
    {
        $service = Database::fetch(
            'SELECT duration_min FROM services WHERE id=:i AND shop_id=:s AND is_active = TRUE',
            [':i' => $serviceId, ':s' => $shopId]
        );
        if (!$service) return [];
        $dur = (int) $service['duration_min'];

        $step = (int) (Database::fetch('SELECT slot_step_min FROM shops WHERE id=:s', [':s' => $shopId])['slot_step_min'] ?? 15);

        $zone = new \DateTimeZone($tz);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateYmd, $zone);
        if (!$date) return [];
        $weekday = (int) $date->format('w');   // 0=Pazar..6=Cumartesi

        $windows = Database::fetchAll(
            'SELECT start_time, end_time FROM working_hours
             WHERE staff_id=:st AND weekday=:w ORDER BY start_time',
            [':st' => $staffId, ':w' => $weekday]
        );
        if (!$windows) return [];

        $dayStart = $date->setTime(0, 0);
        $dayEnd   = $dayStart->modify('+1 day');

        // Meşgul aralıklar: aktif randevular + zaman blokları (personel veya tüm-dükkan)
        $busy = [];
        foreach (Database::fetchAll(
            "SELECT starts_at, ends_at FROM appointments
             WHERE staff_id=:st AND status <> 'cancelled' AND starts_at < :de AND ends_at > :ds",
            [':st' => $staffId, ':ds' => $dayStart->format('c'), ':de' => $dayEnd->format('c')]
        ) as $a) {
            $busy[] = [strtotime($a['starts_at']), strtotime($a['ends_at'])];
        }
        foreach (Database::fetchAll(
            "SELECT starts_at, ends_at FROM time_blocks
             WHERE shop_id=:sh AND (staff_id=:st OR staff_id IS NULL) AND starts_at < :de AND ends_at > :ds",
            [':sh' => $shopId, ':st' => $staffId, ':ds' => $dayStart->format('c'), ':de' => $dayEnd->format('c')]
        ) as $b) {
            $busy[] = [strtotime($b['starts_at']), strtotime($b['ends_at'])];
        }

        $now   = new \DateTimeImmutable('now', $zone);
        $slots = [];
        foreach ($windows as $win) {
            $ws = self::at($date, (string) $win['start_time']);
            $we = self::at($date, (string) $win['end_time']);
            for ($t = $ws; ; $t = $t->modify("+{$step} minutes")) {
                $end = $t->modify("+{$dur} minutes");
                if ($end > $we) break;             // hizmet pencere sonuna sığmıyor
                if ($t < $now) continue;            // geçmiş saat
                $ts = $t->getTimestamp();
                $te = $end->getTimestamp();
                $free = true;
                foreach ($busy as [$bs, $be]) {
                    if ($ts < $be && $te > $bs) { $free = false; break; }
                }
                if ($free) {
                    $slots[] = ['start' => $t->format('c'), 'end' => $end->format('c'), 'label' => $t->format('H:i')];
                }
            }
        }
        return $slots;
    }

    /** O güne ait, içinde en az bir uygun slot olan tarihleri işaretle (takvim için). */
    public static function dayHasSlots(int $shopId, int $staffId, int $serviceId, string $dateYmd): bool
    {
        return count(self::slots($shopId, $staffId, $serviceId, $dateYmd)) > 0;
    }

    private static function at(\DateTimeImmutable $date, string $time): \DateTimeImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return $date->setTime($h, $m);
    }
}
