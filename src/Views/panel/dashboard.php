<?php
/** @var string $date,$dateLabel,$prevDate,$nextDate,$todayDate @var array $staff,$services,$appts,$blocks,$workByStaff
 *  @var int $winStart,$winEnd @var float $ppm @var bool $isToday @var int $nowMin */
$reasonTr = ['lunch' => 'Öğle', 'timeoff' => 'İzin', 'emergency' => 'Acil'];
$H = (int) (($winEnd - $winStart) * $ppm);
?>
<div class="pday">
  <div class="pday-nav">
    <a class="pday-arrow" href="?date=<?= e($prevDate) ?>" aria-label="Önceki gün">‹</a>
    <div class="pday-c"><b><?= e($dateLabel) ?></b><?php if ($date !== $todayDate): ?><a href="?date=<?= e($todayDate) ?>">bugüne dön</a><?php endif; ?></div>
    <a class="pday-arrow" href="?date=<?= e($nextDate) ?>" aria-label="Sonraki gün">›</a>
  </div>
  <div class="pday-act">
    <button class="rv-btn rv-btn-ghost psm" id="p-block">＋ Saat kapat</button>
    <button class="rv-btn rv-btn-primary psm" id="p-add">＋ Randevu</button>
  </div>
</div>

<?php if (!$staff): ?>
  <p class="pempty">Önce <a href="<?= turl('/staff') ?>">personel</a> ve <a href="<?= turl('/services') ?>">hizmet</a> ekleyin.</p>
<?php else: ?>
<div class="ptl" data-win="<?= $winStart ?>" data-ppm="<?= $ppm ?>">
  <div class="ptl-axis" style="height:<?= $H ?>px">
    <?php for ($t = $winStart; $t <= $winEnd; $t += 60): ?>
      <div class="ptl-tick" style="top:<?= (int) (($t - $winStart) * $ppm) ?>px"><?= sprintf('%02d:00', intdiv($t, 60)) ?></div>
    <?php endfor; ?>
  </div>
  <div class="ptl-cols">
    <?php foreach ($staff as $st): $sid = (int) $st['id']; ?>
    <div class="ptl-col">
      <div class="ptl-colh"><?= e($st['name']) ?></div>
      <div class="ptl-grid" data-staff="<?= $sid ?>" style="height:<?= $H ?>px">
        <?php for ($t = $winStart; $t <= $winEnd; $t += 60): ?>
          <div class="ptl-line" style="top:<?= (int) (($t - $winStart) * $ppm) ?>px"></div>
        <?php endfor; ?>
        <?php foreach (($workByStaff[$sid] ?? []) as $win): ?>
          <div class="ptl-open" style="top:<?= (int) (($win[0] - $winStart) * $ppm) ?>px;height:<?= (int) (($win[1] - $win[0]) * $ppm) ?>px"></div>
        <?php endforeach; ?>
        <?php foreach ($blocks as $b): if ($b['staff_id'] !== null && $b['staff_id'] !== $sid) continue; ?>
          <form method="post" action="<?= turl('/blocks/' . $b['id'] . '/delete') ?>" class="ptl-block" data-confirm="Kapatmayı kaldır?"
                style="top:<?= max(0, (int) (($b['startMin'] - $winStart) * $ppm)) ?>px;height:<?= max(12, (int) (($b['endMin'] - $b['startMin']) * $ppm)) ?>px">
            <?= csrf_field() ?><span><?= e($reasonTr[$b['reason']] ?? 'Kapalı') ?></span>
          </form>
        <?php endforeach; ?>
        <?php foreach ($appts as $a): if ($a['staff_id'] !== $sid) continue; ?>
          <button type="button" class="ptl-appt<?= $a['override'] ? ' is-override' : '' ?><?= $a['status'] === 'completed' ? ' is-done' : '' ?><?= $a['status'] === 'no_show' ? ' is-noshow' : '' ?>"
                  style="top:<?= max(0, (int) (($a['startMin'] - $winStart) * $ppm)) ?>px;height:<?= max(24, (int) (($a['endMin'] - $a['startMin']) * $ppm)) ?>px"
                  data-id="<?= $a['id'] ?>" data-name="<?= e($a['name']) ?>" data-phone="<?= e($a['phone']) ?>"
                  data-service="<?= e($a['service']) ?>" data-time="<?= e($a['time']) ?>"
                  data-override="<?= $a['override'] ? '1' : '0' ?>" data-status="<?= e($a['status']) ?>">
            <b><?= e($a['time']) ?></b><span><?= e($a['name']) ?></span><small><?= e($a['service']) ?></small>
          </button>
        <?php endforeach; ?>
        <?php if ($isToday && $nowMin >= $winStart && $nowMin <= $winEnd): ?>
          <div class="ptl-now" style="top:<?= (int) (($nowMin - $winStart) * $ppm) ?>px"></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<p class="ptl-legend"><i class="lg-on"></i> Çalışma saati &nbsp; <i class="lg-ap"></i> Online &nbsp; <i class="lg-ov"></i> Araya sıkıştırma &nbsp; <i class="lg-bl"></i> Kapalı</p>
<?php endif; ?>

<!-- Randevu ekle modal -->
<div class="pmodal" id="m-add" hidden>
  <form class="pmodal-box pform" method="post" action="<?= turl('/appointments') ?>">
    <?= csrf_field() ?>
    <h3>Randevu ekle</h3>
    <label>Personel<select name="staff_id" id="a-staff" required>
      <?php foreach ($staff as $st): ?><option value="<?= $st['id'] ?>"><?= e($st['name']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Hizmet<select name="service_id" required>
      <?php foreach ($services as $sv): ?><option value="<?= $sv['id'] ?>"><?= e($sv['name']) ?> · <?= e(money($sv['price'])) ?> · <?= e(dur((int) $sv['duration_min'])) ?></option><?php endforeach; ?>
    </select></label>
    <div class="prow">
      <label>Tarih<input type="date" name="date" value="<?= e($date) ?>" required></label>
      <label>Saat<input type="time" name="time" id="a-time" required></label>
    </div>
    <div class="prow">
      <label>Ad<input name="name" required></label>
      <label>Soyad<input name="surname"></label>
    </div>
    <label>Telefon<input name="phone" inputmode="tel" placeholder="05XX XXX XX XX"></label>
    <label class="pcheck"><input type="checkbox" name="override" value="1"> Dolu saatin üzerine ekle (araya sıkıştır)</label>
    <div class="pmodal-act"><button type="button" class="rv-btn rv-btn-ghost" data-close>Vazgeç</button><button class="rv-btn rv-btn-primary">Ekle</button></div>
  </form>
</div>

<!-- Saat kapat modal -->
<div class="pmodal" id="m-block" hidden>
  <form class="pmodal-box pform" method="post" action="<?= turl('/blocks') ?>">
    <?= csrf_field() ?>
    <h3>Saat kapat</h3>
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <label>Personel<select name="staff_id"><option value="">Tüm dükkan</option>
      <?php foreach ($staff as $st): ?><option value="<?= $st['id'] ?>"><?= e($st['name']) ?></option><?php endforeach; ?>
    </select></label>
    <div class="prow">
      <label>Başlangıç<input type="time" name="start" required></label>
      <label>Bitiş<input type="time" name="end" required></label>
    </div>
    <label>Sebep<select name="reason"><option value="lunch">Öğle molası</option><option value="timeoff">İzin</option><option value="emergency">Acil</option></select></label>
    <div class="pmodal-act"><button type="button" class="rv-btn rv-btn-ghost" data-close>Vazgeç</button><button class="rv-btn rv-btn-primary">Kapat</button></div>
  </form>
</div>

<!-- Randevu detay modal -->
<div class="pmodal" id="m-appt" hidden>
  <div class="pmodal-box">
    <h3 id="ma-name"></h3>
    <p class="pmuted" id="ma-meta"></p>
    <p id="ma-phone"></p>
    <div class="pmodal-act pstack">
      <form method="post" id="ma-f-complete"><?= csrf_field() ?><input type="hidden" name="status" value="completed"><button class="rv-btn rv-btn-primary">Tamamlandı</button></form>
      <form method="post" id="ma-f-noshow"><?= csrf_field() ?><input type="hidden" name="status" value="no_show"><button class="rv-btn rv-btn-ghost">Gelmedi</button></form>
      <form method="post" id="ma-f-cancel" data-confirm="Randevuyu iptal et?"><?= csrf_field() ?><input type="hidden" name="status" value="cancelled"><button class="rv-btn rv-btn-danger">İptal et</button></form>
    </div>
    <button class="plink" data-close>Kapat</button>
  </div>
</div>
