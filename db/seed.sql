-- =========================================================
-- /var/www/randevu/db/seed.sql — demo veri (tekrar çalıştırılabilir)
-- Demo dükkân: slug 'demo'. Test giriş bilgileri config dışı not'ta.
-- =========================================================
BEGIN;

-- ---------- süper-admin (parola güvenli notta; repoda tutulmaz) ----------
INSERT INTO super_admins (email, password_hash) VALUES
 ('admin@randevu.local', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH')
ON CONFLICT (email) DO NOTHING;

-- ---------- dükkân: demo ----------
INSERT INTO shops (slug, name, owner_phone, default_theme, otp_enabled, slot_step_min, package,
                   color_bg, color_text, color_accent, color_muted, color_bg_light, color_text_light,
                   font_heading, font_body)
VALUES ('demo', 'Demo Barber Studio', '+905551112233', 'dark', FALSE, 15, 'pro',
        '#0f1115', '#f5f5f5', '#c8a15a', '#9aa0a6', '#faf7f0', '#1a1612', 'Poppins', 'Inter')
ON CONFLICT (slug) DO NOTHING;

-- ---------- dükkân yöneticisi (parola güvenli notta; repoda tutulmaz) ----------
INSERT INTO shop_admins (shop_id, email, password_hash)
SELECT id, 'owner@randevu.local', '$2y$10$REPLACE_WITH_REAL_BCRYPT_HASH'
FROM shops WHERE slug='demo'
ON CONFLICT (email) DO NOTHING;

-- ---------- personel (Alex, Sam) ----------
INSERT INTO staff (shop_id, name, display_order)
SELECT s.id, v.name, v.ord
FROM shops s CROSS JOIN (VALUES ('Alex',1),('Sam',2)) AS v(name,ord)
WHERE s.slug='demo'
  AND NOT EXISTS (SELECT 1 FROM staff x WHERE x.shop_id=s.id AND x.name=v.name);

-- ---------- hizmetler (fiyatlar görünür) ----------
INSERT INTO services (shop_id, name, duration_min, price, display_order)
SELECT s.id, v.name, v.dur, v.price, v.ord
FROM shops s CROSS JOIN (VALUES
  ('Saç Kesimi',   30, 250, 1),
  ('Saç + Sakal',  45, 350, 2),
  ('Sakal Tıraşı', 20, 150, 3),
  ('Cilt Bakımı',  60, 500, 4)
) AS v(name,dur,price,ord)
WHERE s.slug='demo'
  AND NOT EXISTS (SELECT 1 FROM services x WHERE x.shop_id=s.id AND x.name=v.name);

-- ---------- çalışma saatleri: Pzt-Cmt (1..6), öğle arası bölünmüş vardiya ----------
INSERT INTO working_hours (shop_id, staff_id, weekday, start_time, end_time)
SELECT st.shop_id, st.id, d.wd, w.st, w.en
FROM staff st
CROSS JOIN generate_series(1,6) AS d(wd)
CROSS JOIN (VALUES (TIME '10:00', TIME '13:00'), (TIME '14:00', TIME '19:00')) AS w(st,en)
WHERE st.shop_id = (SELECT id FROM shops WHERE slug='demo')
  AND NOT EXISTS (
    SELECT 1 FROM working_hours wh
    WHERE wh.staff_id=st.id AND wh.weekday=d.wd AND wh.start_time=w.st
  );

COMMIT;
