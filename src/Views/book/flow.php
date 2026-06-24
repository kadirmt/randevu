<?php /** @var array $services @var array $staff @var array $days */ ?>
<main class="rv-flow" id="rv-flow">
  <nav class="rv-steps" aria-label="Adımlar">
    <span class="rv-step is-active" data-dot="1">Hizmet</span>
    <span class="rv-step" data-dot="2">Personel</span>
    <span class="rv-step" data-dot="3">Gün &amp; Saat</span>
    <span class="rv-step" data-dot="4">Bilgiler</span>
  </nav>
  <div class="rv-progress"><i id="rv-bar" style="width:25%"></i></div>

  <form id="rv-form" method="post" action="<?= turl('/book') ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="service_id" id="f-service">
    <input type="hidden" name="staff_id"   id="f-staff">
    <input type="hidden" name="start"      id="f-start">
    <input type="text" name="website" class="rv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">

    <!-- 1 · Hizmet -->
    <section class="rv-panel is-active" data-panel="1">
      <h2 class="rv-h">Hangi işlemi yaptıracaksınız?</h2>
      <div class="rv-list">
        <?php foreach ($services as $s): ?>
        <button type="button" class="rv-card rv-pick" data-pick="service"
                data-id="<?= (int) $s['id'] ?>" data-name="<?= e($s['name']) ?>"
                data-price="<?= e(money($s['price'])) ?>">
          <span class="rv-card-main">
            <span class="rv-card-title"><?= e($s['name']) ?></span>
            <span class="rv-card-sub"><?= e(dur((int) $s['duration_min'])) ?></span>
          </span>
          <span class="rv-price"><?= e(money($s['price'])) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 2 · Personel -->
    <section class="rv-panel" data-panel="2">
      <h2 class="rv-h">Kiminle olsun?</h2>
      <div class="rv-list rv-grid">
        <?php foreach ($staff as $p): ?>
        <button type="button" class="rv-card rv-staff rv-pick" data-pick="staff"
                data-id="<?= (int) $p['id'] ?>" data-name="<?= e($p['name']) ?>">
          <span class="rv-avatar"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span>
          <span class="rv-card-title"><?= e($p['name']) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 3 · Gün & Saat -->
    <section class="rv-panel" data-panel="3">
      <h2 class="rv-h">Gün</h2>
      <div class="rv-days">
        <?php foreach ($days as $d): ?>
        <button type="button" class="rv-day rv-pick" data-pick="day" data-ymd="<?= e($d['ymd']) ?>">
          <span class="rv-day-wd"><?= e($d['wdShort']) ?></span>
          <span class="rv-day-n"><?= e($d['day']) ?></span>
          <span class="rv-day-m"><?= e($d['month']) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
      <h2 class="rv-h rv-h-2">Saat</h2>
      <div class="rv-slots" id="rv-slots"><p class="rv-hint">Saatleri görmek için bir gün seçin.</p></div>
    </section>

    <!-- 4 · Bilgiler -->
    <section class="rv-panel" data-panel="4">
      <h2 class="rv-h">Son adım</h2>
      <div class="rv-summary" id="rv-summary"></div>
      <label class="rv-field"><span>Ad</span>
        <input name="name" required maxlength="120" autocomplete="given-name" enterkeyhint="next"></label>
      <label class="rv-field"><span>Soyad</span>
        <input name="surname" required maxlength="120" autocomplete="family-name" enterkeyhint="next"></label>
      <label class="rv-field"><span>Telefon</span>
        <input name="phone" required inputmode="tel" placeholder="05XX XXX XX XX" autocomplete="tel"></label>
      <p class="rv-kvkk">Devam ederek ad-soyad ve telefonunuzun randevu için işlenmesini kabul edersiniz. Ödeme dükkanda yapılır.</p>
      <button type="submit" class="rv-btn rv-btn-primary" id="rv-submit">Randevuyu Onayla</button>
    </section>

    <div class="rv-nav">
      <button type="button" class="rv-btn rv-btn-ghost" id="rv-back" hidden>← Geri</button>
    </div>
  </form>
</main>
