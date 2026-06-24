<?php
/** @var array $shop @var string $content @var string $csrf_token */
use App\Core\Tenant;

$themeDefault = $shop['default_theme'] ?? 'dark';
$theme = $_COOKIE['rv_theme'] ?? $themeDefault;
if (!in_array($theme, ['dark', 'light'], true)) $theme = $themeDefault;

$accent     = $shop['color_accent'] ?? '#c8a15a';
$darkBg     = $shop['color_bg'] ?? '#0f1115';
$darkText   = $shop['color_text'] ?? '#f5f5f5';
$darkMuted  = $shop['color_muted'] ?? '#9aa0a6';
$lightBg    = $shop['color_bg_light'] ?? '#faf7f0';
$lightText  = $shop['color_text_light'] ?? '#1a1612';
$fontHead   = $shop['font_heading'] ?? 'Poppins';
$fontBody   = $shop['font_body'] ?? 'Inter';
$gf = rawurlencode($fontHead) . ':wght@500;600;700&family=' . rawurlencode($fontBody) . ':wght@400;500;600';
?>
<!doctype html>
<html lang="tr" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark light">
<title><?= e($shop['name']) ?> — Online Randevu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=<?= $gf ?>&display=swap" rel="stylesheet">
<style>
:root{
  --rv-accent: <?= e($accent) ?>;
  --rv-accent-contrast: #0b0b0c;
  --rv-font-head: "<?= e($fontHead) ?>", system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  --rv-font-body: "<?= e($fontBody) ?>", system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  --rv-radius: 16px;
}
html[data-theme="dark"]{
  --rv-bg: <?= e($darkBg) ?>;
  --rv-text: <?= e($darkText) ?>;
  --rv-muted: <?= e($darkMuted) ?>;
  --rv-surface: color-mix(in srgb, var(--rv-bg) 88%, #fff 12%);
  --rv-surface-2: color-mix(in srgb, var(--rv-bg) 80%, #fff 20%);
  --rv-border: color-mix(in srgb, var(--rv-bg) 70%, #fff 30%);
  --rv-shadow: 0 10px 30px rgba(0,0,0,.45);
}
html[data-theme="light"]{
  --rv-bg: <?= e($lightBg) ?>;
  --rv-text: <?= e($lightText) ?>;
  --rv-muted: #6b7280;
  --rv-surface: #ffffff;
  --rv-surface-2: color-mix(in srgb, var(--rv-bg) 78%, #000 6%);
  --rv-border: color-mix(in srgb, var(--rv-bg) 80%, #000 14%);
  --rv-shadow: 0 8px 24px rgba(0,0,0,.10);
}
</style>
<link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
</head>
<body>
<div class="rv-app">
  <header class="rv-top">
    <div class="rv-brand"><?= e($shop['name']) ?></div>
    <button id="rv-theme" class="rv-icon-btn" type="button" aria-label="Tema değiştir" title="Tema">◐</button>
  </header>
  <?= $content ?>
</div>
<script>
window.RV = {
  base: <?= json_encode(Tenant::base(), JSON_UNESCAPED_SLASHES) ?>,
  csrf: <?= json_encode($csrf_token) ?>,
  theme: <?= json_encode($theme) ?>
};
</script>
<script src="<?= asset('assets/book.js') ?>" defer></script>
</body>
</html>
