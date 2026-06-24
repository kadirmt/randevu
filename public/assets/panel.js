/* Randevu paneli — etkileşim. */
(function () {
  'use strict';

  // Tema
  var tbtn = document.getElementById('rv-theme');
  if (tbtn) tbtn.addEventListener('click', function () {
    var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    var next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    document.cookie = 'rv_theme=' + next + ';path=/;max-age=31536000;samesite=lax';
  });

  // data-confirm olan formlarda onay
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset && f.dataset.confirm && !confirm(f.dataset.confirm)) e.preventDefault();
  }, true);

  // Modal aç/kapat
  function open(id) { var m = document.getElementById(id); if (m) m.hidden = false; }
  function closeM(m) { m.hidden = true; }
  document.querySelectorAll('.pmodal').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) closeM(m); });
    m.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { closeM(m); }); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('.pmodal:not([hidden])').forEach(closeM);
  });

  var addBtn = document.getElementById('p-add');
  if (addBtn) addBtn.addEventListener('click', function () { open('m-add'); });
  var blkBtn = document.getElementById('p-block');
  if (blkBtn) blkBtn.addEventListener('click', function () { open('m-block'); });

  // Çizelgede boşluğa tıkla → randevu ekle modalını personel+saat ile doldur
  var tl = document.querySelector('.ptl');
  if (tl) {
    var win = +tl.dataset.win, ppm = +tl.dataset.ppm;
    tl.querySelectorAll('.ptl-grid').forEach(function (grid) {
      grid.addEventListener('click', function (e) {
        if (e.target.closest('.ptl-appt') || e.target.closest('.ptl-block')) return;
        var rect = grid.getBoundingClientRect();
        var mins = win + Math.max(0, Math.round(((e.clientY - rect.top) / ppm) / 15) * 15);
        var hh = String(Math.floor(mins / 60)).padStart(2, '0'), mm = String(mins % 60).padStart(2, '0');
        var ss = document.getElementById('a-staff'); if (ss) ss.value = grid.dataset.staff;
        var ti = document.getElementById('a-time'); if (ti) ti.value = hh + ':' + mm;
        open('m-add');
      });
    });
  }

  // Blok (kapatma) — tıklayınca formu gönder (data-confirm ile onay)
  document.querySelectorAll('.ptl-block').forEach(function (f) {
    f.addEventListener('click', function (e) {
      e.stopPropagation();
      if (f.requestSubmit) f.requestSubmit(); else f.submit();
    });
  });

  // Randevu detay modalı
  var STAT = { booked: 'Bekliyor', completed: 'Tamamlandı', no_show: 'Gelmedi' };
  document.querySelectorAll('.ptl-appt').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      document.getElementById('ma-name').textContent = b.dataset.name;
      var ov = b.dataset.override === '1' ? ' · araya sıkıştırma' : '';
      document.getElementById('ma-meta').textContent =
        b.dataset.time + ' · ' + b.dataset.service + ov + ' · ' + (STAT[b.dataset.status] || b.dataset.status);
      var ph = document.getElementById('ma-phone');
      ph.innerHTML = b.dataset.phone ? '<a href="tel:' + b.dataset.phone + '">' + b.dataset.phone + '</a>' : '';
      var act = RV.base + '/appointments/' + b.dataset.id + '/status';
      ['complete', 'noshow', 'cancel'].forEach(function (k) { document.getElementById('ma-f-' + k).action = act; });
      open('m-appt');
    });
  });
})();
