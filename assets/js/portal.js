/* ============================================================
   GUEST PORTAL JS
============================================================ */

// ── Particles (reuse mini version) ───────────────────────
(function() {
  const canvas = document.getElementById('particleCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H;
  const resize = () => { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; };
  resize();
  window.addEventListener('resize', resize);
  const pts = Array.from({length:50}, () => ({
    x: Math.random()*99999, y: Math.random()*9999,
    vy: -(Math.random()*.5+.1),
    a: Math.random()*.3+.05,
    s: Math.random()*1.5+.5,
    w: Math.random()*Math.PI*2
  }));
  const frame = () => {
    ctx.clearRect(0,0,W,H);
    pts.forEach(p => {
      p.y += p.vy; p.w += .015;
      p.x += Math.sin(p.w)*.3;
      if (p.x > W) p.x = 0; if (p.x < 0) p.x = W;
      if (p.y < -5) { p.y = H + 5; p.x = Math.random()*W; }
      ctx.globalAlpha = p.a;
      ctx.fillStyle = '#C9A84C';
      ctx.beginPath();
      ctx.arc(p.x % W, p.y % H, p.s, 0, Math.PI*2);
      ctx.fill();
    });
    ctx.globalAlpha = 1;
    requestAnimationFrame(frame);
  };
  frame();
})();

// ── Scroll reveal ─────────────────────────────────────────
document.querySelectorAll('.reveal-up').forEach((el, i) => {
  setTimeout(() => {
    el.style.transition = 'opacity .7s ease, transform .7s ease';
    el.classList.add('is-visible');
  }, 200 + i * 150);
});

// ── QR Code ───────────────────────────────────────────────
(function() {
  const el = document.getElementById('guestQR');
  if (!el || typeof QRCode === 'undefined') return;
  new QRCode(el, {
    text:       QR_DATA,
    width:      200,
    height:     200,
    colorDark:  '#1a0a14',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H,
  });
})();

// ── Download QR ───────────────────────────────────────────
document.getElementById('downloadQR')?.addEventListener('click', () => {
  const canvas = document.querySelector('#guestQR canvas');
  if (!canvas) { alert('QR not ready yet.'); return; }
  const link = document.createElement('a');
  link.download = 'my-event-qr.png';
  link.href     = canvas.toDataURL('image/png');
  link.click();
});

// ── Capsule Countdown ─────────────────────────────────────
(function() {
  const el = document.getElementById('capsuleTimer');
  if (!el) return;
  const tick = () => {
    const diff = new Date(UNLOCK_DATE) - new Date();
    if (diff <= 0) { el.textContent = 'Now!'; return; }
    const d = Math.floor(diff/86400000);
    const h = Math.floor(diff%86400000/3600000);
    const m = Math.floor(diff%3600000/60000);
    el.textContent = `${d}d ${h}h ${m}m`;
  };
  tick();
  setInterval(tick, 60000);
})();

// ── Apple Calendar (.ics download) ───────────────────────
document.getElementById('appleCalBtn')?.addEventListener('click', e => {
  e.preventDefault();
  const btn   = e.currentTarget;
  const title = btn.dataset.title;
  const date  = btn.dataset.date.replace(/-/g, '');
  const venue = btn.dataset.venue;

  const ics = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'BEGIN:VEVENT',
    `DTSTART:${date}T100000Z`,
    `DTEND:${date}T200000Z`,
    `SUMMARY:${title}`,
    `LOCATION:${venue}`,
    'END:VEVENT',
    'END:VCALENDAR',
  ].join('\r\n');

  const blob = new Blob([ics], { type: 'text/calendar' });
  const link = document.createElement('a');
  link.href     = URL.createObjectURL(blob);
  link.download = 'event.ics';
  link.click();
});

document.getElementById('outlookCalBtn')?.addEventListener('click', e => {
  e.preventDefault();
  document.getElementById('appleCalBtn')?.click();
});
