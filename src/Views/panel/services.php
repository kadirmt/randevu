<?php /** @var array $rows */ ?>
<div class="phead"><h1>Hizmetler</h1></div>

<div class="pcard">
  <form method="post" action="<?= turl('/services') ?>" class="prow-form">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
    <input name="name" placeholder="Yeni hizmet adı" required>
    <input name="duration_min" type="number" min="5" max="600" placeholder="dk" required class="pw-sm">
    <input name="price" inputmode="decimal" placeholder="₺" required class="pw-md">
    <button class="rv-btn rv-btn-primary psm">Ekle</button>
  </form>
</div>

<div class="plist">
  <?php foreach ($rows as $r): $on = ($r['is_active'] === 't' || $r['is_active'] === true); if (!$on) continue; ?>
  <div class="prow-item">
    <form method="post" action="<?= turl('/services') ?>" class="prow-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <input name="name" value="<?= e($r['name']) ?>" required>
      <input name="duration_min" type="number" min="5" max="600" value="<?= (int) $r['duration_min'] ?>" class="pw-sm">
      <input name="price" value="<?= e((string) (float) $r['price']) ?>" class="pw-md">
      <button class="rv-btn rv-btn-ghost psm">Kaydet</button>
    </form>
    <form method="post" action="<?= turl('/services/' . $r['id'] . '/delete') ?>" data-confirm="Bu hizmeti kaldır?"><?= csrf_field() ?><button class="pdel" title="Kaldır">✕</button></form>
  </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="pmuted">Henüz hizmet yok.</p><?php endif; ?>
</div>
