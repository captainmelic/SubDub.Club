'use strict';
const $ = (s) => document.querySelector(s);
let csrf = '';
let authMode = 'login';
let rating = 0;
let chosenBlob = null;
let me = '';

/* ---------- helpers ---------- */
async function api(path, opts = {}) {
  const res = await fetch('api/' + path, {
    credentials: 'same-origin',
    ...opts,
    headers: { 'X-CSRF-Token': csrf, ...(opts.headers || {}) },
  });
  let data = {};
  try { data = await res.json(); } catch (_) {}
  if (!res.ok) {
    const err = new Error(data.error || 'Something went wrong');
    err.status = res.status;
    throw err;
  }
  return data;
}
const postJSON = (path, body) =>
  api(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });

function el(tag, props = {}, ...kids) {
  const n = document.createElement(tag);
  for (const [k, v] of Object.entries(props)) {
    if (k === 'class') n.className = v; else n[k] = v;   // textContent only: no HTML injection
  }
  kids.forEach((k) => n.append(k));
  return n;
}
function toast(msg) {
  const t = $('#toast');
  t.textContent = msg;
  t.classList.remove('hidden');
  clearTimeout(toast._t);
  toast._t = setTimeout(() => t.classList.add('hidden'), 4000);
}
const starText = (n) => '★'.repeat(n) + '☆'.repeat(5 - n);

/* ---------- boot ---------- */
async function init() {
  const s = await api('session.php');
  csrf = s.csrf;
  if (s.loggedIn) showDashboard(); else showAuth();
}

function showAuth() {
  $('#dash-view').classList.add('hidden');
  $('#auth-view').classList.remove('hidden');
}
async function showDashboard() {
  try {
    const d = await api('dashboard.php');
    renderDashboard(d);
    $('#auth-view').classList.add('hidden');
    $('#dash-view').classList.remove('hidden');
  } catch (e) {
    if (e.status === 401) showAuth(); else toast(e.message);
  }
}

/* ---------- auth ---------- */
function setAuthMode(mode) {
  authMode = mode;
  $('#tab-login').classList.toggle('active', mode === 'login');
  $('#tab-register').classList.toggle('active', mode === 'register');
  $('#auth-submit').textContent = mode === 'login' ? 'Log in' : 'Create account';
  $('#auth-password').autocomplete = mode === 'login' ? 'current-password' : 'new-password';
  $('#auth-error').textContent = '';
}
$('#tab-login').addEventListener('click', () => setAuthMode('login'));
$('#tab-register').addEventListener('click', () => setAuthMode('register'));

$('#auth-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = $('#auth-submit');
  btn.disabled = true;
  $('#auth-error').textContent = '';
  try {
    await postJSON(authMode === 'login' ? 'login.php' : 'register.php', {
      username: $('#auth-username').value,
      password: $('#auth-password').value,
    });
    $('#auth-password').value = '';
    const s = await api('session.php');   // fresh CSRF token after session regeneration
    csrf = s.csrf;
    await showDashboard();
  } catch (err) {
    $('#auth-error').textContent = err.message;
  } finally {
    btn.disabled = false;
  }
});

$('#logout-btn').addEventListener('click', async () => {
  try { await postJSON('logout.php', {}); } catch (_) {}
  const s = await api('session.php');
  csrf = s.csrf;
  showAuth();
});

/* ---------- dashboard rendering ---------- */
function renderDashboard(d) {
  me = d.username;
  $('#user-pill').textContent = d.username;
  $('#streak-num').textContent = d.streak;
  $('#streak-unit').textContent = d.streak === 1 ? 'day' : 'days';
  $('#longest-num').textContent = d.longest;

  const tile = $('#upload-tile');
  tile.classList.toggle('done', d.uploaded_today);
  $('#upload-label').textContent = d.uploaded_today ? "Today's sub is in. Replace it?" : "Upload today's sub";

  // leaderboard (name + streak only)
  const lb = $('#leaderboard-list');
  lb.replaceChildren();
  if (!d.leaderboard.length) lb.append(el('li', { class: 'empty', textContent: 'No streaks yet. Be the first!' }));
  d.leaderboard.forEach((r, i) => {
    const li = el('li', { class: r.username === me ? 'me' : '' },
      el('span', {}, el('span', { class: 'rank', textContent: String(i + 1) }), document.createTextNode(r.username)),
      el('span', { class: 'val', textContent: `${r.streak} day${r.streak === 1 ? '' : 's'}` }));
    lb.append(li);
  });
/*
  // top subs (this user's)
  const ts = $('#topsubs-list');
  ts.replaceChildren();
  if (!d.top_subs.length) ts.append(el('li', { class: 'empty', textContent: 'Upload a sub to start your list.' }));
  d.top_subs.forEach((r, i) => {
    ts.append(el('li', {},
      el('span', {}, el('span', { class: 'rank', textContent: String(i + 1) }), document.createTextNode(r.sub_type)),
      el('span', { class: 'val', textContent: `${r.avg.toFixed(1)}★ ×${r.count}` })));
  });
*/
  // calendar week (7 days)
  const wk = $('#week');
  wk.replaceChildren();
  d.week.forEach((day, i) => {
    const dt = new Date(day.date + 'T00:00:00');
    const label = dt.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' });
    const box = el('div', { class: 'day' + (day.upload ? ' filled' : '') + (i === 6 ? ' today' : '') },
      el('span', { class: 'dlabel', textContent: label }));
    if (day.upload) {
      const img = el('img', { src: day.upload.image, alt: `${day.upload.sub_type} sub`, loading: 'lazy' });
      if (day.upload.note) img.title = day.upload.note;
      box.append(img,
        el('span', { class: 'dsub', textContent: day.upload.sub_type }),
        el('span', { class: 'dstars', textContent: starText(day.upload.rating) }));
    } else {
      box.append(el('span', { class: 'blank', textContent: '·' }));
    }
    wk.append(box);
  });
}

/* ---------- upload popup ---------- */
function buildStars() {
  const wrap = $('#stars');
  wrap.replaceChildren();
  for (let i = 1; i <= 5; i++) {
    const b = el('button', { type: 'button', class: 'star', textContent: '★' });
    b.setAttribute('role', 'radio');
    b.setAttribute('aria-label', `${i} star${i > 1 ? 's' : ''}`);
    b.addEventListener('click', () => { rating = i; paintStars(); });
    wrap.append(b);
  }
}
function paintStars() {
  document.querySelectorAll('#stars .star').forEach((b, i) => {
    b.classList.toggle('on', i < rating);
    b.setAttribute('aria-checked', String(i < rating && i === rating - 1));
  });
}
function openModal() {
  rating = 0; chosenBlob = null;
  buildStars();
  $('#file-input').value = '';
  $('#note-input').value = '';
  $('#modal-error').textContent = '';
  $('#preview-img').classList.add('hidden');
  $('#preview-empty').classList.remove('hidden');
  $('#modal').classList.remove('hidden');
}
function closeModal() { $('#modal').classList.add('hidden'); }

$('#upload-tile').addEventListener('click', openModal);
$('#modal-close').addEventListener('click', closeModal);
$('#modal').addEventListener('click', (e) => { if (e.target.id === 'modal') closeModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

// Downscale in the browser so phone photos upload fast.
function downscale(file, max = 1280) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      const scale = Math.min(1, max / Math.max(img.width, img.height));
      const c = document.createElement('canvas');
      c.width = Math.round(img.width * scale);
      c.height = Math.round(img.height * scale);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);   // respects EXIF orientation in modern browsers
      c.toBlob((b) => { URL.revokeObjectURL(url); b ? resolve(b) : reject(new Error('Could not process image')); }, 'image/jpeg', 0.85);
    };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('That file is not a readable image.')); };
    img.src = url;
  });
}

$('#file-input').addEventListener('change', async (e) => {
  const f = e.target.files[0];
  if (!f) return;
  try {
    chosenBlob = await downscale(f);
    const p = $('#preview-img');
    p.src = URL.createObjectURL(chosenBlob);
    p.classList.remove('hidden');
    $('#preview-empty').classList.add('hidden');
    $('#modal-error').textContent = '';
  } catch (err) {
    chosenBlob = null;
    $('#modal-error').textContent = err.message;
  }
});

$('#submit-btn').addEventListener('click', async () => {
  const err = $('#modal-error');
  err.textContent = '';
  if (!chosenBlob) { err.textContent = 'Choose a photo first.'; return; }
  if (!rating) { err.textContent = 'Give your sub a star rating.'; return; }

  const btn = $('#submit-btn');
  btn.disabled = true;
  btn.textContent = 'Identifying your sub…';
  const fd = new FormData();
  fd.append('image', chosenBlob, 'sub.jpg');
  fd.append('rating', String(rating));
  fd.append('note', $('#note-input').value);
  try {
    const r = await api('upload.php', { method: 'POST', body: fd });
    closeModal();
    toast(`Nice! That's a ${r.sub_type} sub. Streak: ${r.streak} 🔥`);
    await showDashboard();
  } catch (e) {
    err.textContent = e.message;
    if (e.status === 401) { closeModal(); showAuth(); }
  } finally {
    btn.disabled = false;
    btn.textContent = 'Submit';
  }
});

init().catch(() => showAuth());
