<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Auth;
use App\Core\View;
use App\Core\Tenant;
use App\Core\Helpers;
use App\Core\Audit;
use App\Services\Booking;

/**
 * Dükkan sahibi paneli (tek hesap). Zaman çizelgesi + araya sıkıştırma (override)
 * + hizmet/personel/çalışma-saati/slot-kapatma yönetimi. Tüm sorgular shop_id ile
 * scoped; gelen id'ler dükkana ait mi diye doğrulanır (IDOR).
 */
final class PanelController
{
    private array $shop;
    private array $admin;
    private \DateTimeZone $tz;

    public function __construct()
    {
        $this->tz = new \DateTimeZone('Europe/Istanbul');
    }

    public function route(string $path, string $method): void
    {
        if ($path === '/login')  { $method === 'POST' ? $this->doLogin() : $this->loginForm(); return; }
        if ($path === '/logout') { Auth::logout(); $this->redirect('/login'); }

        $admin = Auth::shopAdmin();
        if (!$admin) $this->redirect('/login');
        $shop = Database::fetch('SELECT * FROM shops WHERE id = :i', [':i' => (int) $admin['shop_id']]);
        if (!$shop) { Auth::logout(); $this->redirect('/login'); }
        $this->admin = $admin;
        $this->shop  = $shop;

        if ($path === '/' && $method === 'GET')                  { $this->dashboard(); return; }
        if ($path === '/appointments' && $method === 'POST')     { $this->createAppointment(); return; }
        if (preg_match('#^/appointments/(\d+)/status$#', $path, $m) && $method === 'POST') { $this->appointmentStatus((int) $m[1]); return; }
        if ($path === '/services')                               { $method === 'POST' ? $this->saveService() : $this->services(); return; }
        if (preg_match('#^/services/(\d+)/delete$#', $path, $m) && $method === 'POST') { $this->deleteService((int) $m[1]); return; }
        if ($path === '/staff')                                  { $method === 'POST' ? $this->saveStaff() : $this->staffPage(); return; }
        if (preg_match('#^/staff/(\d+)/delete$#', $path, $m) && $method === 'POST') { $this->deleteStaff((int) $m[1]); return; }
        if ($path === '/hours')                                  { $method === 'POST' ? $this->saveHours() : $this->hours(); return; }
        if ($path === '/blocks' && $method === 'POST')           { $this->saveBlock(); return; }
        if (preg_match('#^/blocks/(\d+)/delete$#', $path, $m) && $method === 'POST') { $this->deleteBlock((int) $m[1]); return; }

        http_response_code(404);
        View::render('errors/404', [], 'none');
    }

    // ---------------- helpers ----------------
    private function redirect(string $path): never { header('Location: ' . Tenant::base() . $path); exit; }
    private function sid(): int { return (int) $this->shop['id']; }
    private function flash(string $m, string $t = 'ok'): void { $_SESSION['flash'] = ['m' => $m, 't' => $t]; }
    private function takeFlash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
    private function tmin(string $t): int { [$h, $m] = array_map('intval', explode(':', $t)); return $h * 60 + $m; }
    private function dmin(\DateTimeInterface $d): int { return (int) $d->format('G') * 60 + (int) $d->format('i'); }

    // ---------------- auth ----------------
    private function loginForm(): void
    {
        if (Auth::shopAdmin()) $this->redirect('/');
        View::render('panel/login', ['error' => null], 'none');
    }
    private function doLogin(): void
    {
        $res = Auth::attempt('shop', (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($res['ok']) $this->redirect('/');
        View::render('panel/login', ['error' => $res['error']], 'none');
    }

    // ---------------- dashboard / timeline ----------------
    private function dashboard(): void
    {
        $dateStr = (string) ($_GET['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $dateStr = (new \DateTimeImmutable('today', $this->tz))->format('Y-m-d');
        }
        $date     = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr, $this->tz);
        $weekday  = (int) $date->format('w');
        $dayStart = $date->setTime(0, 0);
        $dayEnd   = $dayStart->modify('+1 day');

        $staff = Database::fetchAll('SELECT id, name FROM staff WHERE shop_id=:s AND is_active ORDER BY display_order, id', [':s' => $this->sid()]);

        $appts = Database::fetchAll(
            "SELECT a.*, (a.is_override)::int AS ovr, s.name AS service_name
             FROM appointments a JOIN services s ON s.id=a.service_id
             WHERE a.shop_id=:sh AND a.starts_at < :de AND a.ends_at > :ds AND a.status <> 'cancelled'
             ORDER BY a.starts_at",
            [':sh' => $this->sid(), ':ds' => $dayStart->format('c'), ':de' => $dayEnd->format('c')]
        );
        $wh = Database::fetchAll('SELECT staff_id, start_time, end_time FROM working_hours WHERE shop_id=:s AND weekday=:w', [':s' => $this->sid(), ':w' => $weekday]);
        $blocks = Database::fetchAll(
            "SELECT id, staff_id, starts_at, ends_at, reason, note FROM time_blocks
             WHERE shop_id=:sh AND starts_at < :de AND ends_at > :ds ORDER BY starts_at",
            [':sh' => $this->sid(), ':ds' => $dayStart->format('c'), ':de' => $dayEnd->format('c')]
        );

        // Zaman penceresi (dk)
        $minStart = 24 * 60; $maxEnd = 0;
        foreach ($wh as $w) { $minStart = min($minStart, $this->tmin($w['start_time'])); $maxEnd = max($maxEnd, $this->tmin($w['end_time'])); }
        if ($maxEnd <= $minStart) { $minStart = 9 * 60; $maxEnd = 20 * 60; }
        $winStart = intdiv($minStart, 60) * 60;
        $winEnd   = (int) (ceil($maxEnd / 60) * 60);

        $workByStaff = [];
        foreach ($wh as $w) { $workByStaff[(int) $w['staff_id']][] = [$this->tmin($w['start_time']), $this->tmin($w['end_time'])]; }

        $apptView = [];
        foreach ($appts as $a) {
            $s = new \DateTimeImmutable($a['starts_at'], $this->tz);
            $e = new \DateTimeImmutable($a['ends_at'], $this->tz);
            $apptView[] = [
                'id' => (int) $a['id'], 'staff_id' => (int) $a['staff_id'],
                'startMin' => $this->dmin($s), 'endMin' => $this->dmin($e),
                'time' => $s->format('H:i') . '–' . $e->format('H:i'),
                'name' => trim($a['customer_name'] . ' ' . $a['customer_surname']),
                'phone' => $a['customer_phone'], 'service' => $a['service_name'],
                'override' => ((int) $a['ovr']) === 1, 'source' => $a['source'], 'status' => $a['status'],
            ];
        }
        $blockView = [];
        foreach ($blocks as $b) {
            $s = new \DateTimeImmutable($b['starts_at'], $this->tz);
            $e = new \DateTimeImmutable($b['ends_at'], $this->tz);
            $blockView[] = [
                'id' => (int) $b['id'], 'staff_id' => $b['staff_id'] !== null ? (int) $b['staff_id'] : null,
                'startMin' => $this->dmin($s), 'endMin' => $this->dmin($e), 'reason' => $b['reason'], 'note' => $b['note'],
            ];
        }

        $now = new \DateTimeImmutable('now', $this->tz);
        $services = Database::fetchAll('SELECT id, name, duration_min, price FROM services WHERE shop_id=:s AND is_active ORDER BY display_order, id', [':s' => $this->sid()]);

        View::render('panel/dashboard', [
            'active' => 'takvim',
            'date' => $dateStr, 'dateLabel' => Helpers::dateTr($date),
            'prevDate' => $date->modify('-1 day')->format('Y-m-d'),
            'nextDate' => $date->modify('+1 day')->format('Y-m-d'),
            'todayDate' => $now->format('Y-m-d'),
            'staff' => $staff, 'services' => $services,
            'appts' => $apptView, 'blocks' => $blockView, 'workByStaff' => $workByStaff,
            'winStart' => $winStart, 'winEnd' => $winEnd, 'ppm' => 1.4,
            'isToday' => $now->format('Y-m-d') === $dateStr, 'nowMin' => $this->dmin($now),
            'flash' => $this->takeFlash(),
        ]);
    }

    private function createAppointment(): void
    {
        $staffId   = (int) ($_POST['staff_id'] ?? 0);
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $dateStr   = (string) ($_POST['date'] ?? '');
        $time      = (string) ($_POST['time'] ?? '');
        $name      = trim((string) ($_POST['name'] ?? ''));
        $surname   = trim((string) ($_POST['surname'] ?? ''));
        $phoneRaw  = (string) ($_POST['phone'] ?? '');
        $override  = !empty($_POST['override']);
        $back = '/?date=' . ($dateStr ?: (new \DateTimeImmutable('today', $this->tz))->format('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            $this->flash('Tarih/saat geçersiz.', 'err'); $this->redirect($back);
        }
        if (mb_strlen($name) < 1) { $this->flash('Müşteri adı gerekli.', 'err'); $this->redirect($back); }

        $phone = $phoneRaw !== '' ? Helpers::normalizePhoneTr($phoneRaw) : null;
        $res = Booking::createManual($this->sid(), $staffId, $serviceId, $dateStr . 'T' . $time . ':00',
            mb_substr($name, 0, 120), mb_substr($surname !== '' ? $surname : '-', 0, 120), $phone, $override);

        if ($res['ok']) {
            Audit::log('shop', (int) $this->admin['id'], $override ? 'appointment_override' : 'appointment_manual', $this->sid(), 'appointment', $res['id']);
            $this->flash($override ? 'Araya sıkıştırıldı (override).' : 'Randevu eklendi.');
        } else {
            $this->flash($res['error'], 'err');
        }
        $this->redirect($back);
    }

    private function appointmentStatus(int $id): void
    {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['completed', 'no_show', 'cancelled', 'booked'], true)) { http_response_code(400); echo 'bad'; return; }
        $appt = Database::fetch('SELECT starts_at FROM appointments WHERE id=:i AND shop_id=:s', [':i' => $id, ':s' => $this->sid()]);
        if (!$appt) { http_response_code(404); echo 'yok'; return; }
        if ($status === 'cancelled') {
            Database::execute("UPDATE appointments SET status='cancelled', cancelled_at=NOW(), cancel_token=NULL WHERE id=:i AND shop_id=:s", [':i' => $id, ':s' => $this->sid()]);
        } else {
            Database::execute('UPDATE appointments SET status=:st WHERE id=:i AND shop_id=:s', [':st' => $status, ':i' => $id, ':s' => $this->sid()]);
        }
        Audit::log('shop', (int) $this->admin['id'], 'appointment_status_' . $status, $this->sid(), 'appointment', $id);
        $this->flash('Randevu güncellendi.');
        $this->redirect('/?date=' . (new \DateTimeImmutable($appt['starts_at'], $this->tz))->format('Y-m-d'));
    }

    // ---------------- services ----------------
    private function services(): void
    {
        $rows = Database::fetchAll('SELECT id, name, duration_min, price, is_active FROM services WHERE shop_id=:s ORDER BY is_active DESC, display_order, id', [':s' => $this->sid()]);
        View::render('panel/services', ['active' => 'hizmet', 'rows' => $rows, 'flash' => $this->takeFlash()]);
    }
    private function saveService(): void
    {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $dur  = (int) ($_POST['duration_min'] ?? 0);
        $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
        if ($name === '' || $dur < 5 || $dur > 600) { $this->flash('Ad ve süre (5–600 dk) gerekli.', 'err'); $this->redirect('/services'); }
        if ($id > 0) {
            Database::execute('UPDATE services SET name=:n, duration_min=:d, price=:p WHERE id=:i AND shop_id=:s', [':n' => $name, ':d' => $dur, ':p' => $price, ':i' => $id, ':s' => $this->sid()]);
        } else {
            $ord = (int) (Database::fetch('SELECT COALESCE(MAX(display_order),0)+1 AS o FROM services WHERE shop_id=:s', [':s' => $this->sid()])['o']);
            Database::execute('INSERT INTO services (shop_id, name, duration_min, price, display_order) VALUES (:s,:n,:d,:p,:o)', [':s' => $this->sid(), ':n' => $name, ':d' => $dur, ':p' => $price, ':o' => $ord]);
        }
        $this->flash('Hizmet kaydedildi.');
        $this->redirect('/services');
    }
    private function deleteService(int $id): void
    {
        Database::execute('UPDATE services SET is_active=FALSE WHERE id=:i AND shop_id=:s', [':i' => $id, ':s' => $this->sid()]);
        $this->flash('Hizmet kaldırıldı.');
        $this->redirect('/services');
    }

    // ---------------- staff ----------------
    private function staffPage(): void
    {
        $rows = Database::fetchAll('SELECT id, name, is_active FROM staff WHERE shop_id=:s ORDER BY is_active DESC, display_order, id', [':s' => $this->sid()]);
        View::render('panel/staff', ['active' => 'personel', 'rows' => $rows, 'flash' => $this->takeFlash()]);
    }
    private function saveStaff(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') { $this->flash('İsim gerekli.', 'err'); $this->redirect('/staff'); }
        if ($id > 0) {
            Database::execute('UPDATE staff SET name=:n WHERE id=:i AND shop_id=:s', [':n' => $name, ':i' => $id, ':s' => $this->sid()]);
        } else {
            $ord = (int) (Database::fetch('SELECT COALESCE(MAX(display_order),0)+1 AS o FROM staff WHERE shop_id=:s', [':s' => $this->sid()])['o']);
            Database::execute('INSERT INTO staff (shop_id, name, display_order) VALUES (:s,:n,:o)', [':s' => $this->sid(), ':n' => $name, ':o' => $ord]);
        }
        $this->flash('Personel kaydedildi.');
        $this->redirect('/staff');
    }
    private function deleteStaff(int $id): void
    {
        Database::execute('UPDATE staff SET is_active=FALSE WHERE id=:i AND shop_id=:s', [':i' => $id, ':s' => $this->sid()]);
        $this->flash('Personel kaldırıldı.');
        $this->redirect('/staff');
    }

    // ---------------- working hours ----------------
    private function hours(): void
    {
        $staff = Database::fetchAll('SELECT id, name FROM staff WHERE shop_id=:s AND is_active ORDER BY display_order, id', [':s' => $this->sid()]);
        $staffId = (int) ($_GET['staff'] ?? 0);
        if (!$staffId && $staff) $staffId = (int) $staff[0]['id'];
        $rows = Database::fetchAll('SELECT weekday, start_time, end_time FROM working_hours WHERE staff_id=:st ORDER BY weekday, start_time', [':st' => $staffId]);
        $byDay = [];
        foreach ($rows as $r) { $byDay[(int) $r['weekday']][] = ['s' => substr($r['start_time'], 0, 5), 'e' => substr($r['end_time'], 0, 5)]; }
        View::render('panel/hours', ['active' => 'saat', 'staff' => $staff, 'staffId' => $staffId, 'byDay' => $byDay, 'flash' => $this->takeFlash()]);
    }
    private function saveHours(): void
    {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if (!Database::fetch('SELECT 1 FROM staff WHERE id=:i AND shop_id=:s', [':i' => $staffId, ':s' => $this->sid()])) {
            $this->flash('Personel bulunamadı.', 'err'); $this->redirect('/hours');
        }
        $h = $_POST['h'] ?? [];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::execute('DELETE FROM working_hours WHERE staff_id=:st', [':st' => $staffId]);
        foreach ($h as $wd => $wins) {
            $wd = (int) $wd;
            if ($wd < 0 || $wd > 6 || !is_array($wins)) continue;
            foreach ($wins as $w) {
                $s = trim((string) ($w['s'] ?? '')); $e = trim((string) ($w['e'] ?? ''));
                if (!preg_match('/^\d{2}:\d{2}$/', $s) || !preg_match('/^\d{2}:\d{2}$/', $e) || $e <= $s) continue;
                Database::execute('INSERT INTO working_hours (shop_id, staff_id, weekday, start_time, end_time) VALUES (:sh,:st,:w,:s,:e)',
                    [':sh' => $this->sid(), ':st' => $staffId, ':w' => $wd, ':s' => $s, ':e' => $e]);
            }
        }
        $pdo->commit();
        $this->flash('Çalışma saatleri kaydedildi.');
        $this->redirect('/hours?staff=' . $staffId);
    }

    // ---------------- time blocks (slot kapatma) ----------------
    private function saveBlock(): void
    {
        $staffRaw = $_POST['staff_id'] ?? '';
        $staffId  = $staffRaw === '' ? null : (int) $staffRaw;
        $dateStr  = (string) ($_POST['date'] ?? '');
        $s = (string) ($_POST['start'] ?? '');
        $e = (string) ($_POST['end'] ?? '');
        $reason = (string) ($_POST['reason'] ?? 'timeoff');
        if (!in_array($reason, ['lunch', 'timeoff', 'emergency'], true)) $reason = 'timeoff';
        $back = '/?date=' . $dateStr;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) || !preg_match('/^\d{2}:\d{2}$/', $s) || !preg_match('/^\d{2}:\d{2}$/', $e) || $e <= $s) {
            $this->flash('Geçersiz kapatma aralığı.', 'err'); $this->redirect($back);
        }
        if ($staffId && !Database::fetch('SELECT 1 FROM staff WHERE id=:i AND shop_id=:s', [':i' => $staffId, ':s' => $this->sid()])) {
            $staffId = null;
        }
        Database::execute('INSERT INTO time_blocks (shop_id, staff_id, starts_at, ends_at, reason) VALUES (:sh,:st,:s,:e,:r)', [
            ':sh' => $this->sid(), ':st' => $staffId,
            ':s' => (new \DateTimeImmutable($dateStr . 'T' . $s . ':00', $this->tz))->format('c'),
            ':e' => (new \DateTimeImmutable($dateStr . 'T' . $e . ':00', $this->tz))->format('c'),
            ':r' => $reason,
        ]);
        $this->flash('Saat kapatıldı.');
        $this->redirect($back);
    }
    private function deleteBlock(int $id): void
    {
        $b = Database::fetch('SELECT starts_at FROM time_blocks WHERE id=:i AND shop_id=:s', [':i' => $id, ':s' => $this->sid()]);
        Database::execute('DELETE FROM time_blocks WHERE id=:i AND shop_id=:s', [':i' => $id, ':s' => $this->sid()]);
        $date = $b ? (new \DateTimeImmutable($b['starts_at'], $this->tz))->format('Y-m-d') : (new \DateTimeImmutable('today', $this->tz))->format('Y-m-d');
        $this->flash('Kapatma kaldırıldı.');
        $this->redirect('/?date=' . $date);
    }
}
