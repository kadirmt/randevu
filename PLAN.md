# Randevu — Berber/Kuaför Randevu SaaS · Uygulama Planı

_Plan sürümü: 2026-06-24 · Durum: Faz 0–3 uygulandı (altyapı, çekirdek, müşteri akışı, sahip paneli); Faz 4–6 planlı_

> Bu dosya, çok-ajanlı bir tasarım turunun (8 boyut + 3 eleştiri lensi) çıktısıyla,
> güvenlik/OTP bölümünün (ana thread) sentezidir. Sunucudaki diğer uygulamalar
> bu plandan etkilenmez — bkz. **§2 İzolasyon Garantileri**.

---

## 1) Ürün Özeti

Çok-kiracılı (multi-tenant) bir **online randevu SaaS'i**. Her dükkân bir "tenant":
- **Müşteri tarafı:** `demo.berber.com` (prod subdomain) veya test için `host/d/demo` (path fallback).
  Akış: **Gün+Saat → Personel → Hizmet (fiyatlar görünür) → Ad/Soyad/Telefon → (opsiyonel OTP) → Onay**.
  Üyelik yok, ödeme dükkânda. Mobil öncelikli, dark/light, dükkâna özel renk/font.
- **Sahip paneli:** dükkân başına **tek** yönetici hesabı. Görsel zaman çizelgesi (personel kolonları),
  araya sıkıştırma/override (kırmızı), hizmet/personel/çalışma saati CRUD'u, slot kapatma, istatistik.
- **Süper-admin:** dükkânları, paketleri, subdomain'leri manuel açar (`sys.berber.com` / `/sys`).

**Yığın:** PHP 8.3-FPM + PostgreSQL 16 + nginx — framework/Composer yok, elle yazılmış mini-MVC (App\ namespace, `public/index.php` front controller, `View` auto-layout).
Saat dilimi `Europe/Istanbul`. Test tüneli: cloudflared quick tunnel.

---

## 2) İzolasyon Garantileri

Randevu, aynı sunucuda başka uygulamalarla yan yana çalışabilecek şekilde her kaynağı ayırır:

| Yüzey | randevu |
|---|---|
| Kod ağacı | `/var/www/randevu` |
| PostgreSQL DB | **`randevu`** (owner **`randevu`**) — ayrı rol, başka veritabanlarına yetki YOK |
| PHP-FPM havuzu | **`randevu`** → `/run/php/randevu-fpm.sock` (ayrı pool.d/*.conf) |
| nginx vhost | **adlandırılmış** vhost: `*.berber.com`, `panel/sys/api.berber.com` |
| Oturum/çerez | **`rv_*`**: `rv_panel_sess`, `rv_sys_sess`, `rv_*_remember`, `rv_csrf`, `rv_theme` |
| Cron | **`/etc/cron.d/randevu-remind`** (ayrı log) |
| Loglar | `randevu.access.log`, `randevu.error.log` |
| Storage | `randevu/storage` (ayrı setgid/640) |
| Test tüneli | kendi quick tunnel'ı ile dışa çıkar |

**DB ayrımı kritik:** `randevu` rolü yalnız `randevu` DB'sine `CONNECT` eder; `REVOKE CONNECT ... FROM PUBLIC`
ile çapraz erişim kapatılır. Biri ele geçse bile başka bir veritabanına ulaşamaz.

**`config.php` tuzağı:** sahibi `www-data:www-data`, mod **640** olmalı — yoksa site 500 verir.

---

## 3) Proje Dosya Ağacı

```
/var/www/randevu/
├── config.example.php          # DB, app.secret, notify driver, app.path_tenancy
├── config.php                  # (git-ignore, www-data:640)
├── db/
│   ├── schema.sql              # §4 — tam DDL (btree_gist + EXCLUDE)
│   └── seed.sql                # demo dükkân "demo" + personel + hizmet
├── bin/
│   ├── remind.php              # cron: yaklaşan randevulara hatırlatma (idempotent)
│   └── purge.php               # KVKK: eski PII anonimleştirme
├── deploy/
│   ├── nginx-randevu.conf
│   ├── php-fpm-randevu.conf    # ayrı havuz
│   └── cron-randevu-remind
├── public/                     # DOCROOT
│   ├── index.php               # front controller + Tenant::resolve()
│   ├── assets/{app.css,book.js,panel.js,theme.js}
│   └── robots.txt
├── src/
│   ├── Core/                   # Database, Tenant, Auth, Csrf, RateLimiter, View,
│   │   …                       #   Helpers, Audit, UserSession, Notify, Theme
│   ├── Services/               # Availability, Booking, Stats
│   ├── Controllers/            # Booking, Panel, Sys
│   ├── Views/                  # book/*, panel/*, sys/*, layouts/*
│   └── Lang/tr.php
├── storage/{cache,sessions,logs}
└── PLAN.md                     # bu dosya
```

---

## 4) Veri Modeli (kanonik — eleştiri turuyla uzlaştırıldı)

> **Eleştiri bulgusu (çözüldü):** boyutlar `appointments` için 3 farklı zaman şeması üretmişti
> (`starts_at/ends_at TIMESTAMPTZ` vs `appt_date+start_min` vs `start_at` tek-s'siz).
> **Kanonik karar:** **`starts_at`/`ends_at TIMESTAMPTZ`**, fiyat snapshot kolonu **`service_price`**,
> durum sözlüğü **`booked|completed|cancelled|no_show`**. Panel ve istatistik bu isimlere uyar.

Tam DDL bu dosyanın sonundaki **Ek-A**'da. Özet tablolar:

- **`super_admins`** — SaaS operatörü (sen). E-posta/hash repoda tutulmaz.
- **`shops`** — tenant: `slug`, `subdomain`, isim, tz, **iki palet (light+dark)** + 2 font + `default_theme`,
  `otp_enabled`, `owner_phone`, `slot_step_min`, `package`, `expires_at`, `is_active`.
- **`shop_admins`** — `UNIQUE(shop_id)` → **dükkân başına tek hesap**.
- **`staff`** — personel (login YOK; sadece takvim kaynağı).
- **`services`** — `duration_min`, `price` (TRY).
- **`staff_services`** — opsiyonel yetenek matrisi (satır yoksa "hepsini yapar").
- **`working_hours`** — haftalık, **bölünmüş vardiya** için (staff,weekday) başına çok satır.
- **`time_blocks`** — kapatmalar (`lunch|timeoff|emergency`); `staff_id NULL` = tüm dükkân.
- **`appointments`** — çekirdek. **Çift-rezervasyon engeli:** `EXCLUDE USING gist (staff_id WITH =,
  tstzrange(starts_at,ends_at) WITH &&) WHERE (status<>'cancelled' AND is_override=FALSE)`.
  Override (araya sıkıştırma) bu kısıttan **muaf** → dolu slota bile yazılır, panelde **kırmızı**.
- **`notifications`** — giden mesaj logu (sms/whatsapp; confirmation/reminder/cancel).
- **`otp_codes`** — sadece **dikiş yeri** (hash'li kod, expiry); iç mantık §10'da.
- Paylaşılan altyapı: `remember_tokens`, `user_sessions`,
  `login_attempts`, `login_blocks`, `audit_log` — `user_type IN ('super','shop')`.

---

## 5) Tenant Çözümleme (çift mod)

`App\Core\Tenant::resolve()` — **routing'den ÖNCE**, `public/index.php` içinde bir kez çalışır,
immutable bir `TenantContext` üretir. Host sınıfları **kapalı küme**:

- `SHOP` → `{slug}.berber.com` (prod) **veya** `host/d/{slug}` (test, `app.path_tenancy=true`).
  İkisi de **aynı** TenantContext üretir → alt katmanlar URL şekline bakmaz.
- `PANEL` → `panel.berber.com` (test: `/panel`) — dükkân, **giriş yapan oturumdan** belirlenir (URL'de slug yok).
- `SYS` → `sys.berber.com` (test: `/sys`).
- `APEX/WWW/API` → pazarlama/redirect/JSON.
- **Rezerve slug'lar** (`www,panel,sys,api,app,admin,static,assets,cdn,mail,berber`) hem oluşturmada
  hem çözümlemede reddedilir → bir berber `slug=panel` ile paneli kaçıramaz.

**Müşteri GET sayfaları cookieless** (statik cache dostu): yalnız POST'lar (book/otp/cancel) ve panel/sys
oturum kullanır → yüksek-trafik tarama sayfaları cache'lenebilir, çerez çakışması yüzeyi minimum.

---

## 6) Müsaitlik Algoritması (`App\Services\Availability`)

**Okuma yolu** — `GET /api/availability?staff=&service=&date=` (yan etkisiz, saf hesap):
1. Dükkân tz'sinde tarihin `weekday`'ini bul; o personelin `working_hours` pencerelerini al (bölünmüş vardiya destekli).
2. O güne ait iptal-olmayan `appointments` + `time_blocks` (staff veya tüm-dükkân) aralıklarını çıkar.
3. Her pencereyi `slot_step_min` adımıyla yürü; bir başlangıç ancak **hizmet süresi pencere sonuna sığıyorsa
   ve hiçbir dolu aralıkla çakışmıyorsa** "uygun".
4. Tüm zaman matematiği `Europe/Istanbul` duvar-saatinde (`DateTimeImmutable`), saklama UTC `timestamptz` →
   **DST günleri doğru**. JSON: `{date, slots:[{start:"14:15", end:"14:45"}], tz}`.

**Yazma yolu** — `POST .../book`: aynı sığma kontrolü **`SERIALIZABLE`** işlemde tekrar koşulur; asıl
güvence DB'nin `EXCLUDE` kısıtı. Çakışma → `SQLSTATE 23P01` yakalanır → müşteriye "slot az önce doldu".

**Override yolu** (sahip): ayrı insert rutini, sığma kontrolünü **bilerek atlar**, `is_override=true`,
kısıttan partial-WHERE ile muaf.

---

## 7) Müşteri Akışı (`App\Controllers\BookingController`)

4 adımlı, mobil-öncelikli **durum makinesi**, tam **server-rendered** (JS kapalıyken de çalışır,
progressive enhancement); `book.js` adımları JSON ile yükleyip yatay slide ile geçirir, `history` ile
geri tuşu adımlar arası gezer, skeleton gösterir.

- Adım 1 **Gün + Saat** · Adım 2 **Personel** · Adım 3 **Hizmet** (fiyatlar görünür, tek ana paket)
  · Adım 4 **Ad/Soyad/Telefon**
- **OTP dikiş yeri:** `shop.otp_enabled` ise adım 4 ile onay arasında (yer belli, iç mantık §10).
- **Onay sayfası:** randevu özeti + **şifresiz iptal linki** (`.../iptal/{token}`). Stub modda mesaj
  metni ekranda da gösterilir (gerçek SMS olmadan test).
- **İptal sayfası:** `/iptal/{token}` — login yok, tek tık; panele anlık düşer.
- JSON uçları: `/api/availability`, `/api/staff`, `/api/services` (hepsi tenant-scoped).
- Tema: `<style>` içine inline CSS değişkenleri; `data-theme` `<html>`'de (`rv_theme` cookie / `prefers-color-scheme`).

---

## 8) Sahip Paneli (`App\Controllers\PanelController`)

Tek-admin (`shop` aktör tipi). Personelin login'i yok — takvim kaynağıdır.

- **Giriş** — `Auth` (oturum + remember-me), `RateLimiter` brute-force koruması.
- **Zaman çizelgesi** `/panel` — personel kolonları, çalışma-saati arka planı, mutlak-konumlu bloklar
  (vanilla JS). Online randevu = aksан rengi; **manuel/override = KIRMIZI**. Tek JSON endpoint besler.
- **Araya sıkıştırma** — dolu/iki-arası saate `is_override=true` ile ekleme (müsaitlik atlanır).
- **CRUD** — Hizmetler (ad/dakika/fiyat), Personel, Çalışma saatleri (bölünmüş vardiya),
  **Slot kapatma** (`time_blocks`: öğle/izin/acil).
- İptal/yeni randevu için panel periyodik poll eder (anlık görünüm).

---

## 9) İstatistik (`/panel/stats`)

Hepsi `:shop_id` ile scoped, tarih-aralığı parametreli, `AT TIME ZONE 'Europe/Istanbul'` ile bucketlanır.
Grafik **elle çizilen inline SVG bar** (JS lib yok). Gelir `service_price` snapshot'ından (sonraki fiyat
değişiminden bağımsız), yalnız `booked/completed`, `cancelled/no_show` hariç.
- Aylık **ciro tahmini** · en çok alınan **hizmetler** · en yoğun **saat/gün** · **personel** yükü.
- Hız için `appointments(shop_id,status,starts_at)` ve `(shop_id,starts_at)` bileşik index'leri.

---

## 10) Bildirim (`App\Core\Notify`) + Hatırlatma Cron'u

Swappable **Provider** arayüzü (`sendSms`, `sendWhatsApp`), `config.notify.driver` ile seçilir.
Her mesaj önce `notifications` tablosuna yazılır, sonra provider'a verilir.
- **StubProvider (varsayılan):** dış API'ye gitmez, satırı `sent` işaretler, **iptal linkli mesaj
  metnini onay ekranında gösterir** → sıfır gerçek SMS ile uçtan uca test.
- Gerçek sağlayıcılar (**Netgsm / İletimerkezi / Vatansms / Twilio WhatsApp**) → her biri aynı arayüzü
  yazan küçük bir Provider sınıfı, **sadece config** ile takılır (controller değişmez).
- **TR şablonlar** (`Templates` sınıfı, `{yer_tutucu}`): onay / hatırlatma / iptal-bilgi.
- **Hatırlatma:** `bin/remind.php` cron. Vadesi gelen, gönderilmemiş
  hatırlatmaları `FOR UPDATE SKIP LOCKED` ile alır, `reminder_sent_at` flip eder → **iki kez gönderilmez**.
  Ayrı cron girdisi (`/etc/cron.d/randevu-remind`).
- **OTP:** yalnız `Notify::sendOtp()` dikiş yeri olarak referanslanır (içi §11).

---

## 11) Güvenlik / OTP / Anti-Spam (ana thread bölümü)

- **OTP randevu (dükkân başına):** 6 hane, `otp_codes`'ta **hash'li** (`sha256(secret|code|phone)`), ömür 3 dk,
  max 5 deneme; telefon başına 60 sn cooldown, IP başına saatlik limit. Doğrulanınca randevu yazılır.
- **Troll/bot engeli (OTP kapalıyken de):** IP/telefon hız limiti (`RateLimiter`), honeypot + min-süre tuzağı,
  Cloudflare **Turnstile** dikiş yeri (config bayrağı), TR telefon normalizasyon, mükerrer-randevu guard.
- **İptal token:** `bin2hex(random_bytes(16))` — tahmin edilemez, ID değil; `hash_equals` sabit-zamanlı;
  iptal/saat geçince ölür.
- **Panel:** `password_hash` (Argon2id/bcrypt) + `password_version`, login brute-force blok,
  çerez `HttpOnly+Secure+SameSite=Lax`, tüm POST'larda `Csrf::verifyOrAbort()`.
- **Tenant izolasyonu (IDOR):** her sorgu `WHERE shop_id`; gelen `staff/service/appointment` id'leri
  aktif dükkâna ait mi diye doğrulanır.
- **SQLi:** yalnız prepared statements. **XSS:** `Helpers::e()` + CSP header.
- **KVKK:** sadece ad/soyad/telefon; ödeme verisi yok; `bin/purge.php` eski PII'yi anonimleştirir; aydınlatma notu.
- **Ağ:** önde Cloudflare WAF; nginx `limit_req` (book/otp uçları); hassas işlemler `audit_log`'a.

---

## 12) Tema & Tasarım Sistemi

- Dükkân **iki tam palet** saklar (light + dark: bg/surface/text/muted/accent) + 2 font + `default_theme`.
- Sunucu, **her iki paleti** `[data-theme="light"]`/`[data-theme="dark"]` ile `<style>`'a basar; aktif tema
  `rv_theme` cookie → `<html data-theme>` (FOUC/layout-shift yok). Küçük inline JS toggle çevirir.
- CSS sözleşmesi: namespaced `--rv-*` değişkenleri; stylesheet'te ham hex yok.
- Fontlar **self-hosted** woff2 (latin+latin-ext, `font-display:swap`, preload) → mobilde 3. parti RTT yok.
- Müşteri sayfaları **tam dinamik** (canlı müsaitlik + tema cookie) — statik HTML cache'i kullanılmaz.
- Hazır paletler: **Classic Barbershop** (krem/kömür+pirinç), **Midnight Fade** (arduvaz+elektrik mavi),
  **Vintage Rosewood** (gül ağacı+krem).

---

## 13) Yapım Sırası (faz faz, her faz tek başına çalışır/test edilir)

- **Faz 0 — Altyapı & izolasyon:** DB+rol `randevu`, `schema.sql`, ayrı fpm pool, nginx vhost, `config.php`,
  cloudflared quick tunnel. Çıktı: boş ama ayağa kalkan site + canlı test URL'i.
- **Faz 1 — Çekirdek:** `Core/*` (Database, Tenant, Auth, Csrf, RateLimiter, View, Helpers, Audit), front controller, layout + tema.
- **Faz 2 — Müşteri MVP (ilk deployable dilim):** `Availability` + `BookingController` + book.js + onay/iptal
  + StubProvider. **Uçtan uca: telefon → randevu → iptal linki çalışır.** ← _ilk gerçek demo burada_
- **Faz 3 — Panel:** giriş + zaman çizelgesi (override kırmızı) + hizmet/personel/saat/kapatma CRUD.
- **Faz 4 — İstatistik + Bildirim cron + Süper-admin onboarding.**
- **Faz 5 — Güvenlik sertleştirme:** OTP, anti-spam, Turnstile, KVKK purge, CSP, limit_req.
- **Faz 6 — Gerçek SMS/WhatsApp sağlayıcı** (sen API bilgisi verince tek Provider sınıfı).

---

## 14) Deploy & trycloudflare (test)

1. `sudo -u postgres` ile `CREATE ROLE randevu LOGIN PASSWORD …; CREATE DATABASE randevu OWNER randevu;`
   `REVOKE CONNECT ON DATABASE randevu FROM PUBLIC;` → `schema.sql` + `seed.sql`.
2. `deploy/php-fpm-randevu.conf` → `/etc/php/8.3/fpm/pool.d/randevu.conf`; fpm reload.
3. `deploy/nginx-randevu.conf` → sites-available + symlink; `nginx -t && reload`
   (test modunda `server_name _;` yerine ayrı port/host; default vhost'a dokunulmaz).
4. `config.php`: `app.path_tenancy=true`, `notify.driver=stub`. `www-data:640`.
5. **Test tüneli:** `cloudflared tunnel --url http://localhost:<port>` → rastgele `*.trycloudflare.com`.
   Subdomain olmadığı için tenant **path modunda**: `https://xxx.trycloudflare.com/d/demo`.
6. Telefondan `…/d/demo` aç → randevu al → panelde gör → iptal linkiyle iptal et.

> **Prod subdomain** (`demo.berber.com`) için gerçek bir domain + Cloudflare **named tunnel** + `*.berber.com`
> wildcard DNS gerekir; trycloudflare quick tunnel wildcard veremez. Plan ikisini de destekler.

---

## 15) Build Öncesi Netleşecek Sorular

1. Marka/kök domain adı (`berber.com` örnekti) — sahip olduğun bir domain var mı, yoksa test sadece trycloudflare path modunda mı kalsın?
2. Demo dükkân: `seed.sql` "demo" + örnek personel (Alex, Sam) + hizmetler (Saç, Saç+Sakal, Cilt Bakımı) — fiyat/süreleri sen mi vereceksin yoksa örnek mi koyayım?
3. Süper-admin host: ayrı `sys.*` mı, yoksa test boyunca `/sys` path'i mi yeterli?

---

## Ek-A) Tam `schema.sql`

> (db/schema.sql olarak birebir kullanılacak — `btree_gist` + EXCLUDE dahil. Faz 0'da yazılır.)

```sql
BEGIN;
CREATE EXTENSION IF NOT EXISTS btree_gist;
-- super_admins, shops, shop_admins, staff, services, staff_services,
-- working_hours, time_blocks, appointments (EXCLUDE no_overlap_per_staff),
-- notifications, otp_codes, remember_tokens, user_sessions,
-- login_attempts, login_blocks, audit_log
COMMIT;
```

_(DDL'in tam metni tasarım turunda üretildi; Faz 0'da bu dosyaya birebir yazılacak. Kanonik kararlar §4'te.)_
