<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Tenant;
use App\Core\View;
use App\Core\Helpers;
use App\Core\Notify;
use App\Core\Templates;
use App\Services\Availability;
use App\Services\Booking;

/**
 * Müşteri randevu akışı (üyeliksiz). Akış: Hizmet → Personel → Gün & Saat →
 * Bilgiler → Onay. Saat en sona koyuldu ki seçilen hizmet süresi + personel ile
 * gösterilen her saat gerçekten uygun olsun (çıkmaz sokak yok = pürüzsüz).
 */
final class BookingController
{
    public function route(string $path, string $method): void
    {
        if ($path === '/' && $method === 'GET') {
            $this->index();
            return;
        }
        if ($path === '/api/availability' && $method === 'GET') {
            $this->apiAvailability();
            return;
        }
        if ($path === '/book' && $method === 'POST') {
            $this->confirm();
            return;
        }
        if (preg_match('#^/iptal/([0-9a-f]{32})$#', $path, $m)) {
            $method === 'POST' ? $this->cancelDo($m[1]) : $this->cancelShow($m[1]);
            return;
        }
        http_response_code(404);
        View::render('errors/404', [], 'none');
    }

    private function index(): void
    {
        $shopId   = (int) Tenant::shopId();
        $services = Database::fetchAll(
            'SELECT id, name, duration_min, price FROM services
             WHERE shop_id=:s AND is_active = TRUE ORDER BY display_order, id',
            [':s' => $shopId]
        );
        $staff = Database::fetchAll(
            'SELECT id, name FROM staff WHERE shop_id=:s AND is_active = TRUE ORDER BY display_order, id',
            [':s' => $shopId]
        );

        // Önümüzdeki 14 gün (dükkan TZ'sinde)
        $zone = new \DateTimeZone('Europe/Istanbul');
        $today = new \DateTimeImmutable('today', $zone);
        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $d = $today->modify("+{$i} days");
            $days[] = [
                'ymd'   => $d->format('Y-m-d'),
                'day'   => $d->format('j'),
                'month' => Helpers::MONTHS_TR[(int) $d->format('n')],
                'wd'    => Helpers::WEEKDAYS_TR[(int) $d->format('w')],
                'wdShort' => mb_substr(Helpers::WEEKDAYS_TR[(int) $d->format('w')], 0, 3),
            ];
        }

        View::render('book/flow', [
            'services' => $services,
            'staff'    => $staff,
            'days'     => $days,
        ]);
    }

    private function apiAvailability(): void
    {
        $shopId    = (int) Tenant::shopId();
        $staffId   = (int) ($_GET['staff'] ?? 0);
        $serviceId = (int) ($_GET['service'] ?? 0);
        $date      = (string) ($_GET['date'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            View::json(['ok' => false, 'error' => 'Geçersiz tarih'], 400);
            return;
        }
        // Sahiplik doğrulama (IDOR): personel ve hizmet bu dükkana ait olmalı
        $okStaff = Database::fetch('SELECT 1 FROM staff WHERE id=:i AND shop_id=:s AND is_active', [':i' => $staffId, ':s' => $shopId]);
        $okSvc   = Database::fetch('SELECT 1 FROM services WHERE id=:i AND shop_id=:s AND is_active', [':i' => $serviceId, ':s' => $shopId]);
        if (!$okStaff || !$okSvc) {
            View::json(['ok' => false, 'error' => 'Geçersiz seçim'], 400);
            return;
        }
        $slots = Availability::slots($shopId, $staffId, $serviceId, $date);
        View::json(['ok' => true, 'slots' => $slots]);
    }

    private function confirm(): void
    {
        $shopId    = (int) Tenant::shopId();
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $staffId   = (int) ($_POST['staff_id'] ?? 0);
        $startIso  = (string) ($_POST['start'] ?? '');
        $name      = trim((string) ($_POST['name'] ?? ''));
        $surname   = trim((string) ($_POST['surname'] ?? ''));
        $phoneRaw  = (string) ($_POST['phone'] ?? '');
        $hp        = (string) ($_POST['website'] ?? ''); // honeypot

        $err = null;
        $phone = Helpers::normalizePhoneTr($phoneRaw);
        if ($hp !== '')                              $err = 'Geçersiz istek.';
        elseif (mb_strlen($name) < 2)                $err = 'Adınızı girin.';
        elseif (mb_strlen($surname) < 2)             $err = 'Soyadınızı girin.';
        elseif ($phone === null)                     $err = 'Geçerli bir telefon numarası girin (05XX XXX XX XX).';

        if ($err === null) {
            $res = Booking::create($shopId, $staffId, $serviceId, $startIso,
                mb_substr($name, 0, 120), mb_substr($surname, 0, 120), $phone);
            if (!$res['ok']) {
                $err = $res['error'];
            } else {
                $this->onBooked($res);
                return;
            }
        }

        // Hata: akışı baştan göster + uyarı
        http_response_code(422);
        View::render('book/error', ['message' => $err, 'backUrl' => Tenant::url('/')]);
    }

    /** Başarılı rezervasyon: bildirim + hatırlatma + onay ekranı. */
    private function onBooked(array $res): void
    {
        $shop  = Tenant::shop();
        $appt  = $res['appointment'];
        $token = $res['token'];
        $zone  = new \DateTimeZone('Europe/Istanbul');
        $start = new \DateTimeImmutable($appt['starts_at'], $zone);

        $dateLabel = Helpers::dateTr($start);
        $time      = $start->format('H:i');
        $price     = Helpers::money((float) $appt['service_price']);
        $cancelUrl = Helpers::baseUrl() . Tenant::url('/iptal/' . $token);

        $body = Templates::confirmation($shop['name'], $dateLabel, $time, $res['staff']['name'], $res['service']['name'], $price, $cancelUrl);
        Notify::send((int) $shop['id'], (int) $appt['id'], 'confirmation', $appt['customer_phone'], $body);

        // Hatırlatma: 2 saat önce (gelecekteyse kuyruğa)
        $remindAt = $start->modify('-2 hours');
        if ($remindAt > new \DateTimeImmutable('now', $zone)) {
            $rbody = Templates::reminder($shop['name'], $time, $res['staff']['name'], $res['service']['name'], $cancelUrl);
            Notify::scheduleReminder((int) $shop['id'], (int) $appt['id'], $appt['customer_phone'], $rbody, $remindAt->format('c'));
        }

        View::render('book/success', [
            'appt'      => $appt,
            'staff'     => $res['staff'],
            'service'   => $res['service'],
            'dateLabel' => $dateLabel,
            'time'      => $time,
            'price'     => $price,
            'cancelUrl' => $cancelUrl,
            'stubBody'  => ($this->isStub() ? $body : null),
        ]);
    }

    private function cancelShow(string $token): void
    {
        $appt = Database::fetch(
            "SELECT a.*, s.name AS service_name, st.name AS staff_name
             FROM appointments a
             JOIN services s ON s.id=a.service_id
             JOIN staff st ON st.id=a.staff_id
             WHERE a.cancel_token=:t AND a.shop_id=:sh",
            [':t' => $token, ':sh' => (int) Tenant::shopId()]
        );
        $zone = new \DateTimeZone('Europe/Istanbul');
        $info = null;
        $state = 'invalid';
        if ($appt) {
            $start = new \DateTimeImmutable($appt['starts_at'], $zone);
            $past  = $start <= new \DateTimeImmutable('now', $zone);
            $state = $past ? 'expired' : 'ok';
            $info = [
                'dateLabel' => Helpers::dateTr($start),
                'time'      => $start->format('H:i'),
                'staff'     => $appt['staff_name'],
                'service'   => $appt['service_name'],
            ];
        }
        View::render('book/cancel', ['state' => $state, 'info' => $info, 'token' => $token]);
    }

    private function cancelDo(string $token): void
    {
        $res  = Booking::cancelByToken($token);
        $shop = Tenant::shop();

        if ($res['ok']) {
            $appt = $res['appointment'];
            $zone = new \DateTimeZone('Europe/Istanbul');
            $start = new \DateTimeImmutable($appt['starts_at'], $zone);
            // Müşteriye bilgi + sahibe anlık iptal bildirimi
            Notify::send((int) $shop['id'], (int) $appt['id'], 'cancel_customer', $appt['customer_phone'],
                Templates::cancelCustomer($shop['name'], Helpers::dateTr($start), $start->format('H:i')));
            $staffName = Database::fetch('SELECT name FROM staff WHERE id=:i', [':i' => $appt['staff_id']])['name'] ?? '';
            Notify::notifyOwner((int) $shop['id'], (int) $appt['id'], 'cancel_owner',
                Templates::cancelOwner(Helpers::dateTr($start), $start->format('H:i'), $staffName,
                    $appt['customer_name'] . ' ' . $appt['customer_surname']));
        }
        View::render('book/cancel', [
            'state' => $res['ok'] ? 'done' : ($res['error'] === 'expired' ? 'expired' : 'invalid'),
            'info'  => null,
            'token' => $token,
        ]);
    }

    private function isStub(): bool
    {
        return ($GLOBALS['config']['notify']['driver'] ?? 'stub') === 'stub';
    }
}
