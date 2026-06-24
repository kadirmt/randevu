<?php
/** @var array $shop @var string $content @var string $csrf_token */
use App\Core\Tenant;
$accent = $shop['color_accent'] ?? '#c8a15a';
$theme  = $_COOKIE['rv_theme'] ?? 'dark';
if (!in_array($theme, ['dark', 'light'], true)) $theme = 'dark';
$active = $active ?? '';
$flash  = $flash ?? null;
?>
<!doctype html>
<html lang="tr" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark light">
<title><?= e($shop['name']) ?> · Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
<style>
:root{ --rv-accent: <?= e($accent) ?>; --rv-accent-contrast:#0b0b0c;
  --rv-font-head:"Poppins",system-ui,sans-serif; --rv-font-body:"Inter",system-ui,sans-serif; --rv-radius:14px; }
html[data-theme="dark"]{ --rv-bg:#0f1115; --rv-text:#f3f4f6; --rv-muted:#9aa0a6;
  --rv-surface:#181b22; --rv-surface-2:#20242d; --rv-border:#2a2f3a; --rv-shadow:0 10px 30px rgba(0,0,0,.5); }
html[data-theme="light"]{ --rv-bg:#f4f5f7; --rv-text:#161a20; --rv-muted:#697586;
  --rv-surface:#ffffff; --rv-surface-2:#eef0f3; --rv-border:#dfe3e8; --rv-shadow:0 8px 24px rgba(0,0,0,.10); }
</style>
<link rel="stylesheet" href="<?= asset('assets/panel.css') ?>">
</head>
<body>
<header class="pnav">
  <div class="pnav-brand"><b><?= e($shop['name']) ?></b><span>Yönetim</span></div>
  <nav class="pnav-menu">
    <a href="<?= turl('/') ?>" class="<?= $active === 'takvim' ? 'on' : '' ?>">Takvim</a>
    <a href="<?= turl('/services') ?>" class="<?= $active === 'hizmet' ? 'on' : '' ?>">Hizmetler</a>
    <a href="<?= turl('/staff') ?>" class="<?= $active === 'personel' ? 'on' : '' ?>">Personel</a>
    <a href="<?= turl('/hours') ?>" class="<?= $active === 'saat' ? 'on' : '' ?>">Saatler</a>
  </nav>
  <div class="pnav-right">
    <button id="rv-theme" class="rv-icon-btn" type="button" title="Tema">◐</button>
    <form method="post" action="<?= turl('/logout') ?>"><?= csrf_field() ?><button class="pnav-logout" type="submit">Çıkış</button></form>
  </div>
</header>
<?php if ($flash): ?><div class="pflash <?= $flash['t'] === 'err' ? 'is-err' : 'is-ok' ?>"><?= e($flash['m']) ?></div><?php endif; ?>
<main class="pmain"><?= $content ?></main>
<script>window.RV = { base: <?= json_encode(Tenant::base(), JSON_UNESCAPED_SLASHES) ?>, csrf: <?= json_encode($csrf_token) ?> };</script>
<script src="<?= asset('assets/panel.js') ?>" defer></script>
</body>
</html>
