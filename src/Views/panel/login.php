<?php /** @var ?string $error @var array $shop @var string $csrf_token */
$accent = $shop['color_accent'] ?? '#c8a15a';
?>
<!doctype html>
<html lang="tr" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Girişi · <?= e($shop['name'] ?? 'Randevu') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@700&display=swap" rel="stylesheet">
<style>
:root{--ac:<?= e($accent) ?>}
*{box-sizing:border-box}
body{margin:0;font-family:"Inter",system-ui,sans-serif;background:#0f1115;color:#f3f4f6;
  display:grid;place-items:center;min-height:100vh;min-height:100dvh;padding:22px}
.box{width:100%;max-width:380px}
h1{font-family:"Poppins",sans-serif;font-size:24px;margin:0 0 4px;text-align:center}
.sub{color:#9aa0a6;text-align:center;margin:0 0 26px;font-size:14px}
label{display:block;font-size:13px;color:#9aa0a6;font-weight:600;margin:0 0 6px}
input{width:100%;background:#181b22;border:1.5px solid #2a2f3a;border-radius:12px;padding:14px 15px;
  color:#f3f4f6;font-size:16px;outline:none;margin-bottom:14px}
input:focus{border-color:var(--ac)}
button{width:100%;background:var(--ac);color:#0b0b0c;border:0;border-radius:12px;padding:15px;
  font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}
.err{background:rgba(229,72,77,.15);border:1px solid rgba(229,72,77,.5);color:#ffb4b6;
  padding:11px 14px;border-radius:10px;font-size:14px;margin-bottom:16px}
</style>
</head>
<body>
<form class="box" method="post" action="">
  <h1><?= e($shop['name'] ?? 'Randevu') ?></h1>
  <p class="sub">Yönetim paneli girişi</p>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>E-posta</label>
  <input name="email" type="email" autocomplete="username" required autofocus>
  <label>Parola</label>
  <input name="password" type="password" autocomplete="current-password" required>
  <button type="submit">Giriş yap</button>
</form>
</body>
</html>
