<?php /** @var string $state @var ?array $info @var string $token */ ?>
<main class="rv-flow rv-result">
<?php if ($state === 'ok' && $info): ?>
  <h1 class="rv-h">Randevunuzu iptal edelim mi?</h1>
  <div class="rv-summary rv-summary-lg">
    <div><span>Tarih</span><b><?= e($info['dateLabel']) ?></b></div>
    <div><span>Saat</span><b><?= e($info['time']) ?></b></div>
    <div><span>Personel</span><b><?= e($info['staff']) ?></b></div>
    <div><span>İşlem</span><b><?= e($info['service']) ?></b></div>
  </div>
  <form method="post" action="<?= turl('/iptal/' . $token) ?>">
    <?= csrf_field() ?>
    <button class="rv-btn rv-btn-danger" type="submit">Evet, iptal et</button>
  </form>
  <a class="rv-link" href="<?= turl('/') ?>">Vazgeç</a>
<?php elseif ($state === 'done'): ?>
  <div class="rv-check">✓</div>
  <h1 class="rv-h">Randevunuz iptal edildi.</h1>
  <a class="rv-link" href="<?= turl('/') ?>">Yeni randevu al</a>
<?php elseif ($state === 'expired'): ?>
  <div class="rv-cross">!</div>
  <h1 class="rv-h">Bu randevu geçmiş ya da iptal edilemiyor.</h1>
  <a class="rv-link" href="<?= turl('/') ?>">Ana sayfa</a>
<?php else: ?>
  <div class="rv-cross">!</div>
  <h1 class="rv-h">Randevu bulunamadı.</h1>
  <a class="rv-link" href="<?= turl('/') ?>">Ana sayfa</a>
<?php endif; ?>
</main>
