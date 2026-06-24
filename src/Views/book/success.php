<?php /** @var array $appt @var array $staff @var array $service @var string $dateLabel @var string $time @var string $price @var string $cancelUrl @var ?string $stubBody */ ?>
<main class="rv-flow rv-result">
  <div class="rv-check">✓</div>
  <h1 class="rv-h">Randevunuz alındı!</h1>
  <p class="rv-sub">Görüşmek üzere, <?= e($appt['customer_name']) ?>.</p>

  <div class="rv-summary rv-summary-lg">
    <div><span>Tarih</span><b><?= e($dateLabel) ?></b></div>
    <div><span>Saat</span><b><?= e($time) ?></b></div>
    <div><span>Personel</span><b><?= e($staff['name']) ?></b></div>
    <div><span>İşlem</span><b><?= e($service['name']) ?></b></div>
    <div><span>Ücret</span><b><?= e($price) ?> · dükkanda</b></div>
  </div>

  <?php if ($stubBody): ?>
    <div class="rv-stub">
      <div class="rv-stub-h">📱 Müşteriye gidecek mesaj <span>(test · stub)</span></div>
      <pre><?= e($stubBody) ?></pre>
    </div>
  <?php endif; ?>

  <a class="rv-btn rv-btn-ghost" href="<?= e($cancelUrl) ?>">Randevuyu iptal et</a>
  <a class="rv-link" href="<?= turl('/') ?>">Yeni randevu al</a>
</main>
