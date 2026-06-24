-- =========================================================
-- /var/www/randevu/db/schema.sql
-- Randevu (berber/kuaför randevu SaaS) — PostgreSQL 16
-- DB: randevu   OWNER ROLE: randevu   (ayrı veritabanı ve rol)
-- Provisioning (bir kez, superuser ile — bu dosyanın parçası DEĞİL):
--   CREATE ROLE randevu LOGIN PASSWORD '...';
--   CREATE DATABASE randevu OWNER randevu;
--   CREATE EXTENSION btree_gist;   (superuser; randevu DB içinde)
-- Uygulama date_default_timezone_set('Europe/Istanbul') uygular; DB tarafında
-- her bağlantıda SET TIME ZONE 'Europe/Istanbul' (Core\Database).
-- =========================================================

BEGIN;

CREATE EXTENSION IF NOT EXISTS btree_gist;   -- appointments EXCLUDE kısıtı için gerekli

-- ---------- SÜPER-ADMIN (SaaS operatörü) ----------
CREATE TABLE IF NOT EXISTS super_admins (
    id               SERIAL PRIMARY KEY,
    email            VARCHAR(255) UNIQUE NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,
    password_version INTEGER NOT NULL DEFAULT 1,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ---------- DÜKKÂNLAR (tenant) ----------
CREATE TABLE IF NOT EXISTS shops (
    id               SERIAL PRIMARY KEY,
    slug             VARCHAR(100) UNIQUE NOT NULL,           -- path fallback /d/{slug}
    subdomain        VARCHAR(100) UNIQUE,                    -- demo.berber.com; atanana dek NULL
    name             VARCHAR(255) NOT NULL,
    timezone         VARCHAR(64)  NOT NULL DEFAULT 'Europe/Istanbul',
    -- tema: dükkân başına çift palet (light + dark) + fontlar
    color_bg         VARCHAR(7)  NOT NULL DEFAULT '#0f1115',
    color_text       VARCHAR(7)  NOT NULL DEFAULT '#f5f5f5',
    color_accent     VARCHAR(7)  NOT NULL DEFAULT '#c8a15a',
    color_muted      VARCHAR(7)  NOT NULL DEFAULT '#9aa0a6',
    color_bg_light   VARCHAR(7)  NOT NULL DEFAULT '#faf7f0',
    color_text_light VARCHAR(7)  NOT NULL DEFAULT '#1a1612',
    font_heading     VARCHAR(80) NOT NULL DEFAULT 'Poppins',
    font_body        VARCHAR(80) NOT NULL DEFAULT 'Inter',
    default_theme    VARCHAR(5)  NOT NULL DEFAULT 'dark' CHECK (default_theme IN ('dark','light')),
    otp_enabled      BOOLEAN     NOT NULL DEFAULT FALSE,     -- dükkân başına OTP kapısı (içi Faz 5)
    owner_phone      VARCHAR(20),                            -- sahibe bildirim hedefi (E.164)
    slot_step_min    SMALLINT    NOT NULL DEFAULT 15 CHECK (slot_step_min BETWEEN 5 AND 60),
    package          VARCHAR(20) NOT NULL DEFAULT 'basic' CHECK (package IN ('basic','pro','premium')),
    expires_at       TIMESTAMPTZ NULL,                       -- NULL = sınırsız; < NOW() => askıda
    is_active        BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMP   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_shops_slug      ON shops(slug);
CREATE INDEX IF NOT EXISTS idx_shops_subdomain ON shops(subdomain) WHERE subdomain IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_shops_expires   ON shops(expires_at) WHERE expires_at IS NOT NULL;

-- ---------- DÜKKÂN YÖNETİCİSİ (dükkân başına TEK giriş) ----------
CREATE TABLE IF NOT EXISTS shop_admins (
    id               SERIAL PRIMARY KEY,
    shop_id          INTEGER NOT NULL UNIQUE REFERENCES shops(id) ON DELETE CASCADE,
    email            VARCHAR(255) UNIQUE NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,
    password_version INTEGER NOT NULL DEFAULT 1,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_shop_admins_shop ON shop_admins(shop_id);

-- ---------- PERSONEL (kendi girişi YOK; sadece takvim kaynağı) ----------
CREATE TABLE IF NOT EXISTS staff (
    id            SERIAL PRIMARY KEY,
    shop_id       INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
    name          VARCHAR(120) NOT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_staff_shop ON staff(shop_id, display_order) WHERE is_active;

-- ---------- HİZMETLER (fiyatı görünür paketler) ----------
CREATE TABLE IF NOT EXISTS services (
    id            SERIAL PRIMARY KEY,
    shop_id       INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
    name          VARCHAR(160) NOT NULL,
    duration_min  SMALLINT NOT NULL CHECK (duration_min > 0 AND duration_min <= 600),
    price         NUMERIC(10,2) NOT NULL DEFAULT 0 CHECK (price >= 0),  -- TRY
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_services_shop ON services(shop_id, display_order) WHERE is_active;

-- ---------- PERSONEL_HİZMET (opsiyonel yetenek matrisi) ----------
-- Bir personel için HİÇ satır yoksa = 'tüm hizmetleri yapar' (uygulama konvansiyonu).
CREATE TABLE IF NOT EXISTS staff_services (
    shop_id    INTEGER NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
    staff_id   INTEGER NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    service_id INTEGER NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    PRIMARY KEY (staff_id, service_id)
);
CREATE INDEX IF NOT EXISTS idx_staff_services_shop    ON staff_services(shop_id);
CREATE INDEX IF NOT EXISTS idx_staff_services_service ON staff_services(service_id);

-- ---------- ÇALIŞMA SAATLERİ (haftalık tekrar; bölünmüş vardiya) ----------
-- weekday: 0=Pazar .. 6=Cumartesi (PHP date('w')). (staff,weekday) başına çok
-- satır = bölünmüş vardiya (örn. 09:00-13:00 ve 14:00-19:00).
CREATE TABLE IF NOT EXISTS working_hours (
    id         SERIAL PRIMARY KEY,
    shop_id    INTEGER  NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
    staff_id   INTEGER  NOT NULL REFERENCES staff(id) ON DELETE CASCADE,
    weekday    SMALLINT NOT NULL CHECK (weekday BETWEEN 0 AND 6),
    start_time TIME     NOT NULL,
    end_time   TIME     NOT NULL,
    CHECK (end_time > start_time)
);
CREATE INDEX IF NOT EXISTS idx_working_hours_lookup ON working_hours(staff_id, weekday);
CREATE INDEX IF NOT EXISTS idx_working_hours_shop   ON working_hours(shop_id);

-- ---------- ZAMAN BLOKLARI (kapatma: öğle / izin / acil) ----------
-- staff_id NULL => tüm dükkân kapalı. Somut tarih-saat aralığı.
CREATE TABLE IF NOT EXISTS time_blocks (
    id         SERIAL PRIMARY KEY,
    shop_id    INTEGER     NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
    staff_id   INTEGER     NULL     REFERENCES staff(id) ON DELETE CASCADE,
    starts_at  TIMESTAMPTZ NOT NULL,
    ends_at    TIMESTAMPTZ NOT NULL,
    reason     VARCHAR(12) NOT NULL DEFAULT 'timeoff' CHECK (reason IN ('lunch','timeoff','emergency')),
    note       VARCHAR(200),
    created_at TIMESTAMP   NOT NULL DEFAULT NOW(),
    CHECK (ends_at > starts_at)
);
CREATE INDEX IF NOT EXISTS idx_time_blocks_staff ON time_blocks(staff_id, starts_at) WHERE staff_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_time_blocks_shop  ON time_blocks(shop_id, starts_at);

-- ---------- RANDEVULAR (çekirdek) ----------
CREATE TABLE IF NOT EXISTS appointments (
    id             BIGSERIAL PRIMARY KEY,
    shop_id        INTEGER     NOT NULL REFERENCES shops(id)    ON DELETE CASCADE,
    staff_id       INTEGER     NOT NULL REFERENCES staff(id)    ON DELETE RESTRICT,
    service_id     INTEGER     NOT NULL REFERENCES services(id) ON DELETE RESTRICT,
    customer_name    VARCHAR(120) NOT NULL,
    customer_surname VARCHAR(120) NOT NULL,
    customer_phone   VARCHAR(20)  NOT NULL,             -- E.164, +905xxxxxxxxx
    service_price  NUMERIC(10,2) NOT NULL DEFAULT 0,    -- rezervasyon anı fiyat snapshot'ı
    starts_at      TIMESTAMPTZ NOT NULL,
    ends_at        TIMESTAMPTZ NOT NULL,
    status         VARCHAR(12) NOT NULL DEFAULT 'booked'
                     CHECK (status IN ('booked','completed','cancelled','no_show')),
    source         VARCHAR(8)  NOT NULL DEFAULT 'online'
                     CHECK (source IN ('online','manual')),
    is_override    BOOLEAN     NOT NULL DEFAULT FALSE,  -- sahip araya-sıkıştırma; çakışma muaf; KIRMIZI
    cancel_token   CHAR(32)    UNIQUE,                  -- şifresiz iptal linki; kullanılınca NULL
    otp_verified   BOOLEAN     NOT NULL DEFAULT FALSE,  -- seam: OTP modülü set eder
    cancelled_at   TIMESTAMPTZ NULL,
    created_at     TIMESTAMP   NOT NULL DEFAULT NOW(),
    CHECK (ends_at > starts_at),

    -- *** ÇİFT-REZERVASYON ENGELİ ***
    -- Aynı personel için iki AKTİF, NON-OVERRIDE randevu çakışamaz.
    -- iptal satırları slotu serbest bırakır; override (sahip) muaf.
    CONSTRAINT no_overlap_per_staff
      EXCLUDE USING gist (
        staff_id WITH =,
        tstzrange(starts_at, ends_at) WITH &&
      ) WHERE (status <> 'cancelled' AND is_override = FALSE)
);
CREATE INDEX IF NOT EXISTS idx_appts_shop_day   ON appointments(shop_id, starts_at);
CREATE INDEX IF NOT EXISTS idx_appts_staff_day  ON appointments(staff_id, starts_at);
CREATE INDEX IF NOT EXISTS idx_appts_status     ON appointments(shop_id, status, starts_at);
CREATE INDEX IF NOT EXISTS idx_appts_phone      ON appointments(shop_id, customer_phone);

-- ---------- BİLDİRİMLER (giden log: SMS / WhatsApp) ----------
CREATE TABLE IF NOT EXISTS notifications (
    id            BIGSERIAL PRIMARY KEY,
    shop_id       INTEGER     NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
    appointment_id BIGINT     NULL     REFERENCES appointments(id) ON DELETE SET NULL,
    channel       VARCHAR(10) NOT NULL CHECK (channel IN ('sms','whatsapp')),
    kind          VARCHAR(20) NOT NULL CHECK (kind IN ('confirmation','reminder','cancel_customer','cancel_owner','otp')),
    recipient     VARCHAR(20) NOT NULL,
    body          TEXT        NOT NULL DEFAULT '',
    status        VARCHAR(10) NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','sent','failed')),
    provider_ref  VARCHAR(120),
    error         VARCHAR(300),
    reminder_for  TIMESTAMPTZ NULL,                    -- hatırlatma: gönderilecek an (cron bunu tarar)
    created_at    TIMESTAMP   NOT NULL DEFAULT NOW(),
    sent_at       TIMESTAMPTZ NULL
);
CREATE INDEX IF NOT EXISTS idx_notif_shop  ON notifications(shop_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_notif_appt  ON notifications(appointment_id) WHERE appointment_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_notif_queue ON notifications(status, reminder_for) WHERE status = 'queued';

-- ---------- OTP KODLARI (yalnız ENTEGRASYON DİKİŞİ — içi Faz 5) ----------
CREATE TABLE IF NOT EXISTS otp_codes (
    id          BIGSERIAL PRIMARY KEY,
    shop_id     INTEGER     NOT NULL REFERENCES shops(id) ON DELETE CASCADE,
    phone       VARCHAR(20) NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,        -- düz kod asla saklanmaz
    expires_at  TIMESTAMPTZ NOT NULL,
    consumed_at TIMESTAMPTZ NULL,
    attempts    SMALLINT    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP   NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_otp_lookup ON otp_codes(shop_id, phone, created_at DESC);

-- ============================================================
-- PAYLAŞILAN ALTYAPI — oturum ve kimlik doğrulama tabloları.
-- shop_id ile scoped DEĞİL; (user_type,user_id) ile keylenir.
-- ============================================================

CREATE TABLE IF NOT EXISTS remember_tokens (
    id               SERIAL PRIMARY KEY,
    user_type        VARCHAR(20) NOT NULL CHECK (user_type IN ('super','shop')),
    user_id          INTEGER NOT NULL,
    selector         VARCHAR(32) UNIQUE NOT NULL,
    token_hash       VARCHAR(255) NOT NULL,
    password_version INTEGER NOT NULL,
    expires_at       TIMESTAMP NOT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_remember_lookup  ON remember_tokens(user_type, user_id);
CREATE INDEX IF NOT EXISTS idx_remember_expires ON remember_tokens(expires_at);

CREATE TABLE IF NOT EXISTS user_sessions (
    id                BIGSERIAL PRIMARY KEY,
    user_type         VARCHAR(20)  NOT NULL CHECK (user_type IN ('super','shop')),
    user_id           INTEGER      NOT NULL,
    session_id        VARCHAR(128) NOT NULL UNIQUE,
    remember_selector VARCHAR(32),
    ip                VARCHAR(45),
    user_agent        TEXT,
    created_at        TIMESTAMP    NOT NULL DEFAULT NOW(),
    last_seen_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_type, user_id, last_seen_at DESC);

CREATE TABLE IF NOT EXISTS login_attempts (
    id         BIGSERIAL PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    user_type  VARCHAR(20) NOT NULL CHECK (user_type IN ('super','shop')),
    identifier VARCHAR(255),
    user_agent TEXT,
    successful BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip      ON login_attempts(ip, user_type, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_login_attempts_created ON login_attempts(created_at);

CREATE TABLE IF NOT EXISTS login_blocks (
    id            BIGSERIAL PRIMARY KEY,
    ip            VARCHAR(45) NOT NULL,
    user_type     VARCHAR(20) NOT NULL CHECK (user_type IN ('super','shop')),
    identifier    VARCHAR(255),
    user_agent    TEXT,
    attempts      INTEGER NOT NULL DEFAULT 0,
    blocked_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    blocked_until TIMESTAMP NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_blocks_active ON login_blocks(ip, user_type, blocked_until DESC);
CREATE INDEX IF NOT EXISTS idx_login_blocks_until  ON login_blocks(blocked_until DESC);

CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGSERIAL PRIMARY KEY,
    actor_type  VARCHAR(16) NOT NULL,           -- super | shop | system
    actor_id    INTEGER NULL,
    shop_id     INTEGER NULL REFERENCES shops(id) ON DELETE SET NULL,
    action      VARCHAR(64) NOT NULL,
    target_type VARCHAR(32) NULL,
    target_id   INTEGER NULL,
    detail      VARCHAR(500) NULL,
    ip          VARCHAR(45) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_actor   ON audit_log(actor_type, actor_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_action  ON audit_log(action, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_shop    ON audit_log(shop_id, created_at DESC) WHERE shop_id IS NOT NULL;

COMMIT;
