// assets/script.js — Portal Bimbingan Skripsi

// ── Sidebar toggle ────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('show');
}
document.getElementById('sidebarOverlay')?.addEventListener('click', closeSidebar);

// ── Notification panel ─────────────────────────
const notifBtn = document.getElementById('notifBtn');
const notifPanel = document.getElementById('notifPanel');

notifBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  notifPanel?.classList.toggle('show');
});

document.addEventListener('click', (e) => {
  if (!notifBtn?.contains(e.target) && !notifPanel?.contains(e.target)) {
    notifPanel?.classList.remove('show');
  }
});

// ── Flash auto-dismiss ─────────────────────────
setTimeout(() => {
  document.querySelectorAll('.alert:not(.alert-static)').forEach(el => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  });
}, 4000);

// ── Modal konfirmasi (pengganti confirm() bawaan browser) ─────────
// Pakai: <form data-confirm="Pesan" data-confirm-ok="Hapus"> atau pada tombol submit.
// data-confirm-tone="primary" untuk aksi yang tidak berbahaya.
function konfirmasi({ pesan, ok = 'Lanjutkan', judul = 'Konfirmasi', tone = 'danger' } = {}) {
  const dlg = document.getElementById('confirmDialog');
  if (!dlg || typeof dlg.showModal !== 'function') {
    return Promise.resolve(window.confirm(pesan || 'Yakin ingin melanjutkan?'));
  }
  dlg.querySelector('[data-confirm-title]').textContent = judul;
  dlg.querySelector('[data-confirm-msg]').textContent = pesan || 'Yakin ingin melanjutkan?';
  const okBtn = dlg.querySelector('[data-confirm-yes]');
  okBtn.textContent = ok;
  okBtn.className = 'btn ' + (tone === 'primary' ? 'btn-primary' : 'btn-danger-solid');
  return new Promise(resolve => {
    const selesai = (hasil) => { dlg.close(); cleanup(); resolve(hasil); };
    const onYes = () => selesai(true);
    const onNo = () => selesai(false);
    const onCancel = (e) => { e.preventDefault(); selesai(false); };
    const onBackdrop = (e) => { if (e.target === dlg) selesai(false); };
    function cleanup() {
      okBtn.removeEventListener('click', onYes);
      dlg.querySelector('[data-confirm-no]').removeEventListener('click', onNo);
      dlg.removeEventListener('cancel', onCancel);
      dlg.removeEventListener('click', onBackdrop);
    }
    okBtn.addEventListener('click', onYes);
    dlg.querySelector('[data-confirm-no]').addEventListener('click', onNo);
    dlg.addEventListener('cancel', onCancel);
    dlg.addEventListener('click', onBackdrop);
    dlg.showModal();
    dlg.querySelector('[data-confirm-no]').focus();
  });
}
window.konfirmasi = konfirmasi;

// ── Kirim form: konfirmasi + status loading (cegah kirim ganda) ───
document.addEventListener('submit', async (e) => {
  const form = e.target;
  const btn = e.submitter || null;
  const src = (btn && btn.dataset.confirm) ? btn : (form.dataset.confirm ? form : null);

  if (src && form.dataset.confirmed !== '1') {
    e.preventDefault();
    const ya = await konfirmasi({
      pesan: src.dataset.confirm,
      ok: src.dataset.confirmOk || 'Lanjutkan',
      tone: src.dataset.confirmTone || 'danger',
    });
    if (!ya) return;
    form.dataset.confirmed = '1';
    if (form.requestSubmit) { form.requestSubmit(btn || undefined); }
    else {
      if (btn && btn.name) {
        const h = document.createElement('input');
        h.type = 'hidden'; h.name = btn.name; h.value = btn.value; form.appendChild(h);
      }
      form.submit();
    }
    return;
  }
  delete form.dataset.confirmed;

  if ((form.method || 'get').toLowerCase() !== 'post') return;
  if (form.dataset.busy === '1') { e.preventDefault(); return; }
  form.dataset.busy = '1';

  // Jangan pakai `disabled` di sini: tombol yang di-disable tidak ikut terkirim.
  const target = btn || form.querySelector('button[type=submit], button:not([type])');
  if (target) {
    const unggah = form.enctype === 'multipart/form-data' && [...form.querySelectorAll('input[type=file]')].some(i => i.files && i.files.length);
    target.dataset.label = target.innerHTML;
    target.classList.add('is-loading');
    target.setAttribute('aria-busy', 'true');
    if (!target.classList.contains('btn-icon') && !target.classList.contains('badge')) {
      target.innerHTML = '<span class="spinner" aria-hidden="true"></span>' + (unggah ? 'Mengunggah…' : 'Memproses…');
    }
  }
});

// Kembali lewat tombol Back: pulihkan tombol yang sempat "loading"
window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[data-busy]').forEach(f => delete f.dataset.busy);
  document.querySelectorAll('.is-loading').forEach(b => {
    b.classList.remove('is-loading'); b.removeAttribute('aria-busy');
    if (b.dataset.label) b.innerHTML = b.dataset.label;
  });
});

// ── Area unggah: tampilkan nama file & efek seret ───────────────
document.querySelectorAll('.upload-zone').forEach(zone => {
  const input = zone.querySelector('input[type=file]');
  const label = zone.querySelector('.upload-label');
  if (!input || !label) return;
  const def = label.dataset.default || label.textContent;
  input.addEventListener('change', () => {
    const f = input.files && input.files[0];
    label.textContent = f ? f.name : def;
    zone.classList.toggle('has-file', !!f);
  });
  ['dragenter', 'dragover'].forEach(ev => input.addEventListener(ev, () => zone.classList.add('is-drag')));
  ['dragleave', 'drop'].forEach(ev => input.addEventListener(ev, () => zone.classList.remove('is-drag')));
});

// ── Tab switcher ───────────────────────────────
document.querySelectorAll('.tab-btn[data-tab]').forEach(btn => {
  btn.addEventListener('click', () => {
    const group = btn.closest('.tabs');
    group.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const target = btn.dataset.tab;
    document.querySelectorAll('.tab-content').forEach(c => {
      c.style.display = c.id === target ? 'block' : 'none';
    });
  });
});

// ── Upload zone label ──────────────────────────
document.querySelectorAll('.upload-zone').forEach(zone => {
  const input = zone.querySelector('input[type=file]');
  input?.addEventListener('change', () => {
    const label = zone.querySelector('.upload-label');
    if (label && input.files[0]) label.textContent = input.files[0].name;
  });
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--navy-light)'; });
  zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.style.borderColor = '';
    if (input && e.dataTransfer.files[0]) {
      const dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      input.files = dt.files;
      const label = zone.querySelector('.upload-label');
      if (label) label.textContent = e.dataTransfer.files[0].name;
    }
  });
});

/* =========================================
   TOGGLE PASSWORD VISIBILITY
   ========================================= */
document.addEventListener("DOMContentLoaded", function () {
  const toggleBtn = document.getElementById('togglePasswordBtn');
  const passwordInput = document.getElementById('password');
  const eyeClosed = document.getElementById('eyeClosed');
  const eyeOpen = document.getElementById('eyeOpen');

  // Pengecekan ini memastikan script hanya berjalan jika elemennya ada di halaman tersebut
  if (toggleBtn && passwordInput && eyeClosed && eyeOpen) {
    toggleBtn.addEventListener('click', function () {
      const isPassword = passwordInput.getAttribute('type') === 'password';

      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

      if (isPassword) {
        eyeClosed.style.display = 'none';
        eyeOpen.style.display = 'block';
        toggleBtn.style.color = 'var(--navy)';
      } else {
        eyeClosed.style.display = 'block';
        eyeOpen.style.display = 'none';
        toggleBtn.style.color = 'var(--slate)';
      }
    });
  }
});

/* =========================================
   TOPBAR NOTIFICATION TOGGLE
   ========================================= */
document.addEventListener("DOMContentLoaded", function () {
  const notifBtn = document.getElementById('notifBtn');
  const notifPanel = document.getElementById('notifPanel');

  if (notifBtn && notifPanel) {
    // Secara default sembunyikan panel notifikasi
    notifPanel.style.display = 'none';

    // Tampilkan/Sembunyikan saat tombol lonceng diklik
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation(); // Mencegah klik menembus ke window
      if (notifPanel.style.display === 'none' || notifPanel.style.display === '') {
        notifPanel.style.display = 'block';
      } else {
        notifPanel.style.display = 'none';
      }
    });

    // Sembunyikan panel jika user klik area kosong di luar panel
    window.addEventListener('click', function (e) {
      if (!notifPanel.contains(e.target) && e.target !== notifBtn) {
        notifPanel.style.display = 'none';
      }
    });
  }
});
// ── Cek kemiripan judul saat mengetik (D11) ───
document.querySelectorAll('[data-cek-judul]').forEach(input => {
  const kotak = document.getElementById(input.dataset.cekJudul);
  if (!kotak) return;
  const base = (document.querySelector('script[src*="assets/script.js"]')?.src || '').replace(/assets\/script\.js.*$/, '');
  let timer;
  const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.split(/\s+/).length < 3) { kotak.innerHTML = ''; return; }
    timer = setTimeout(async () => {
      try {
        const res = await fetch(base + 'api/cek-judul.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.hasil || !data.hasil.length) {
          kotak.innerHTML = '<div class="cek-judul__ok">Tidak ditemukan judul yang mirip.</div>';
          return;
        }
        kotak.innerHTML = '<div class="judul-mirip"><div class="judul-mirip__judul">Judul serupa yang sudah ada</div><ul>' +
          data.hasil.map(h => '<li><span class="badge ' + (h.skor >= 60 ? 'danger' : 'warning') + '">' + h.skor + '%</span> ' + esc(h.judul) +
            ' <span class="meta">· ' + esc(h.status) + (h.angkatan ? ', angkatan ' + esc(h.angkatan) : '') + (h.nama ? ', ' + esc(h.nama) : '') + '</span></li>').join('') +
          '</ul><div class="meta">Ubah objek, fokus, atau metode agar judul Anda berbeda.</div></div>';
      } catch (e) { kotak.innerHTML = ''; }
    }, 450);
  });
});
