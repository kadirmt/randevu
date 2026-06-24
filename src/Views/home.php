<?php
use App\Core\Database;
$shops = Database::fetchAll('SELECT slug, name FROM shops WHERE is_active = TRUE ORDER BY name LIMIT 50');
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Randevu</title>
<style>
  :root{color-scheme:dark}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f1115;color:#f5f5f5;
       display:grid;place-items:center;min-height:100vh;padding:24px}
  .card{max-width:420px;width:100%;text-align:center}
  h1{font-weight:700;letter-spacing:-.02em}
  .muted{color:#9aa0a6}
  a.shop{display:flex;justify-content:space-between;align-items:center;gap:12px;text-decoration:none;color:inherit;
         background:#1a1d24;border:1px solid #2a2e38;border-radius:14px;padding:16px 18px;margin:10px 0}
  a.shop:hover{border-color:#c8a15a}
  .arr{color:#c8a15a}
</style>
</head>
<body>
<div class="card">
  <h1>Randevu</h1>
  <p class="muted">Berber &amp; kuaför için online randevu.</p>
  <?php foreach ($shops as $s): ?>
    <a class="shop" href="/d/<?= e($s['slug']) ?>"><span><?= e($s['name']) ?></span><span class="arr">Randevu al →</span></a>
  <?php endforeach; ?>
  <?php if (!$shops): ?><p class="muted">Henüz dükkan yok.</p><?php endif; ?>
</div>
</body>
</html>
