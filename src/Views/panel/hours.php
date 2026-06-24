<?php /** @var array $staff @var int $staffId @var array $byDay */
$wd = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
$order = [1, 2, 3, 4, 5, 6, 0]; // Pzt..Paz
?>
<div class="phead">
  <h1>Çalışma Saatleri</h1>
  <?php if ($staff): ?>
  <form method="get" class="pinline">
    <label>Personel
      <select name="staff" onchange="this.form.submit()">
        <?php foreach ($staff as $st): ?><option value="<?= $st['id'] ?>" <?= (int) $st['id'] === $staffId ? 'selected' : '' ?>><?= e($st['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
  </form>
  <?php endif; ?>
</div>

<?php if (!$staff): ?>
  <p class="pempty">Önce <a href="<?= turl('/staff') ?>">personel</a> ekleyin.</p>
<?php else: ?>
<form method="post" action="<?= turl('/hours') ?>" class="pcard">
  <?= csrf_field() ?><input type="hidden" name="staff_id" value="<?= $staffId ?>">
  <table class="phours">
    <thead><tr><th></th><th>1. aralık</th><th>2. aralık (öğle sonrası)</th></tr></thead>
    <tbody>
    <?php foreach ($order as $d): $wins = $byDay[$d] ?? []; $w0 = $wins[0] ?? ['s' => '', 'e' => '']; $w1 = $wins[1] ?? ['s' => '', 'e' => '']; ?>
      <tr>
        <th><?= $wd[$d] ?></th>
        <td><input type="time" name="h[<?= $d ?>][0][s]" value="<?= e($w0['s']) ?>"> – <input type="time" name="h[<?= $d ?>][0][e]" value="<?= e($w0['e']) ?>"></td>
        <td><input type="time" name="h[<?= $d ?>][1][s]" value="<?= e($w1['s']) ?>"> – <input type="time" name="h[<?= $d ?>][1][e]" value="<?= e($w1['e']) ?>"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="pmuted">İki aralık doldurursan öğle arası bölünmüş vardiya olur. Bir günü boş bırakırsan o gün kapalıdır.</p>
  <button class="rv-btn rv-btn-primary">Kaydet</button>
</form>
<?php endif; ?>
