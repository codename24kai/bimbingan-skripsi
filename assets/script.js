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
const notifBtn   = document.getElementById('notifBtn');
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
  document.querySelectorAll('.alert').forEach(el => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  });
}, 4000);

// ── Confirm delete ─────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', (e) => {
    if (!confirm(btn.dataset.confirm || 'Yakin ingin melanjutkan?')) {
      e.preventDefault();
    }
  });
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
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor='var(--navy-light)'; });
  zone.addEventListener('dragleave', () => { zone.style.borderColor=''; });
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.style.borderColor='';
    if (input && e.dataTransfer.files[0]) {
      const dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      input.files = dt.files;
      const label = zone.querySelector('.upload-label');
      if (label) label.textContent = e.dataTransfer.files[0].name;
    }
  });
});
