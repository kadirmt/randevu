/* Randevu — müşteri akış kontrolcüsü (vanilla, ~3KB). */
(function () {
  'use strict';
  var form = document.getElementById('rv-form');
  if (!form) return;

  var panels = [].slice.call(form.querySelectorAll('.rv-panel'));
  var dots   = [].slice.call(document.querySelectorAll('.rv-step'));
  var bar    = document.getElementById('rv-bar');
  var back   = document.getElementById('rv-back');
  var slotsBox = document.getElementById('rv-slots');
  var summary  = document.getElementById('rv-summary');
  var fService = document.getElementById('f-service');
  var fStaff   = document.getElementById('f-staff');
  var fStart   = document.getElementById('f-start');
  var TOTAL = panels.length;

  var state = { panel: 1, service: null, staff: null, day: null, slot: null };

  function goto(n, push) {
    n = Math.max(1, Math.min(TOTAL, n));
    state.panel = n;
    panels.forEach(function (p) { p.classList.toggle('is-active', +p.dataset.panel === n); });
    dots.forEach(function (d) {
      var i = +d.dataset.dot;
      d.classList.toggle('is-active', i === n);
      d.classList.toggle('is-done', i < n);
    });
    if (bar) bar.style.width = Math.round(n / TOTAL * 100) + '%';
    back.hidden = n === 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (push !== false) { try { history.pushState({ panel: n }, ''); } catch (e) {} }
  }

  form.addEventListener('click', function (ev) {
    var el = ev.target.closest('.rv-pick');
    if (!el) return;
    var kind = el.dataset.pick;
    if (kind === 'service') {
      mark(el, 'service'); fService.value = el.dataset.id;
      state.service = { id: el.dataset.id, name: el.dataset.name, price: el.dataset.price };
      goto(2);
    } else if (kind === 'staff') {
      mark(el, 'staff'); fStaff.value = el.dataset.id;
      state.staff = { id: el.dataset.id, name: el.dataset.name };
      goto(3);
    } else if (kind === 'day') {
      mark(el, 'day'); state.day = el.dataset.ymd;
      loadSlots();
    }
  });

  function mark(el, group) {
    form.querySelectorAll('[data-pick="' + group + '"]').forEach(function (x) { x.classList.remove('is-selected'); });
    el.classList.add('is-selected');
  }

  function loadSlots() {
    if (!state.service || !state.staff || !state.day) return;
    slotsBox.innerHTML = '';
    for (var i = 0; i < 6; i++) { var s = document.createElement('div'); s.className = 'rv-skel'; slotsBox.appendChild(s); }
    var url = RV.base + '/api/availability?service=' + enc(state.service.id) + '&staff=' + enc(state.staff.id) + '&date=' + enc(state.day);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        slotsBox.innerHTML = '';
        if (!d.ok || !d.slots || !d.slots.length) {
          slotsBox.innerHTML = '<p class="rv-empty">Bu gün için uygun saat yok. Başka bir gün seçin.</p>';
          return;
        }
        d.slots.forEach(function (sl) {
          var b = document.createElement('button');
          b.type = 'button'; b.className = 'rv-slot'; b.textContent = sl.label;
          b.addEventListener('click', function () {
            slotsBox.querySelectorAll('.rv-slot').forEach(function (x) { x.classList.remove('is-selected'); });
            b.classList.add('is-selected');
            state.slot = { start: sl.start, label: sl.label };
            fStart.value = sl.start;
            fillSummary();
            goto(4);
          });
          slotsBox.appendChild(b);
        });
      })
      .catch(function () { slotsBox.innerHTML = '<p class="rv-empty">Saatler yüklenemedi, tekrar deneyin.</p>'; });
  }

  function fillSummary() {
    if (!summary) return;
    summary.innerHTML =
      rowEl('İşlem', state.service.name + ' · ' + state.service.price) +
      rowEl('Personel', state.staff.name) +
      rowEl('Tarih', formatDay(state.day)) +
      rowEl('Saat', state.slot.label);
  }
  function rowEl(k, v) { return '<div><span>' + esc(k) + '</span><b>' + esc(v) + '</b></div>'; }

  var WD = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
  var MO = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
  function formatDay(ymd) {
    var p = ymd.split('-'); var d = new Date(+p[0], +p[1] - 1, +p[2]);
    return d.getDate() + ' ' + MO[d.getMonth()] + ' ' + WD[d.getDay()];
  }

  back.addEventListener('click', function () { goto(state.panel - 1); });
  window.addEventListener('popstate', function (ev) {
    goto(ev.state && ev.state.panel ? ev.state.panel : 1, false);
  });

  form.addEventListener('submit', function (ev) {
    if (!fService.value || !fStaff.value || !fStart.value) {
      ev.preventDefault();
      goto(!fService.value ? 1 : (!fStaff.value ? 2 : 3));
    } else {
      var btn = document.getElementById('rv-submit');
      if (btn) { btn.textContent = 'Gönderiliyor…'; btn.disabled = true; }
    }
  });

  var tbtn = document.getElementById('rv-theme');
  if (tbtn) {
    tbtn.addEventListener('click', function () {
      var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      var next = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      document.cookie = 'rv_theme=' + next + ';path=/;max-age=31536000;samesite=lax';
    });
  }

  function enc(s) { return encodeURIComponent(s); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  try { history.replaceState({ panel: 1 }, ''); } catch (e) {}
})();
