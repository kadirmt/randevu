<?php /** @var array $rows */ ?>
<div class="phead"><h1>Personel</h1></div>

<div class="pcard">
  <form method="post" action="<?= turl('/staff') ?>" class="prow-form">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
    <input name="name" placeholder="Yeni personel adı" required>
    <button class="rv-btn rv-btn-primary psm">Ekle</button>
  </form>
</div>

<div class="plist">
  <?php foreach ($rows as $r): $on = ($r['is_active'] === 't' || $r['is_active'] === true); if (!$on) continue; ?>
  <div class="prow-item">
    <form method="post" action="<?= turl('/staff') ?>" class="prow-form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <input name="name" value="<?= e($r['name']) ?>" required>
      <button class="rv-btn rv-btn-ghost psm">Kaydet</button>
    </form>
    <form method="post" action="<?= turl('/staff/' . $r['id'] . '/delete') ?>" data-confirm="Bu personeli kaldır?"><?= csrf_field() ?><button class="pdel" title="Kaldır">✕</button></form>
  </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="pmuted">Henüz personel yok.</p><?php endif; ?>
</div>
