/* ============================================================
   E-INVITATION — MAIN JS
   Animations, RSVP, Guest Garden, Journey Map, Countdown
============================================================ */

// ── Particle Canvas ──────────────────────────────────────
(function initParticles() {
  const canvas = document.getElementById('particleCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, particles = [];

  const resize = () => {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  };
  resize();
  window.addEventListener('resize', resize);

  const COLORS = ['rgba(201,168,76,', 'rgba(240,208,128,', 'rgba(185,140,60,'];

  class Particle {
    constructor() { this.reset(true); }
    reset(initial = false) {
      this.x    = Math.random() * W;
      this.y    = initial ? Math.random() * H : H + 20;
      this.size = Math.random() * 2 + 0.5;
      this.speedY = -(Math.random() * 0.6 + 0.2);
      this.speedX = (Math.random() - 0.5) * 0.3;
      this.opacity = Math.random() * 0.5 + 0.1;
      this.color = COLORS[Math.floor(Math.random() * COLORS.length)];
      this.wobble = Math.random() * Math.PI * 2;
    }
    update() {
      this.y += this.speedY;
      this.wobble += 0.02;
      this.x += Math.sin(this.wobble) * 0.4 + this.speedX;
      this.opacity -= 0.0005;
      if (this.y < -20 || this.opacity <= 0) this.reset();
    }
    draw() {
      ctx.save();
      ctx.globalAlpha = this.opacity;
      ctx.fillStyle = this.color + this.opacity + ')';
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }
  }

  // Create 80 particles
  for (let i = 0; i < 80; i++) particles.push(new Particle());

  const animate = () => {
    ctx.clearRect(0, 0, W, H);
    particles.forEach(p => { p.update(); p.draw(); });
    requestAnimationFrame(animate);
  };
  animate();
})();

// ── Scroll Reveal ────────────────────────────────────────
(function initReveal() {
  const els = document.querySelectorAll('.reveal-up, .reveal-scale');
  if (!els.length) return;

  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const delay = parseFloat(e.target.style.animationDelay || 0) * 1000;
        setTimeout(() => e.target.classList.add('is-visible'), delay);
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.15 });

  els.forEach(el => obs.observe(el));

  // Trigger hero elements immediately
  document.querySelectorAll('.hero .reveal-up, .hero .reveal-scale').forEach(el => {
    const delay = parseFloat(el.style.animationDelay || 0) * 1000;
    setTimeout(() => el.classList.add('is-visible'), delay);
  });
})();

// ── Countdown Timer ──────────────────────────────────────
(function initCountdown() {
  const grid = document.querySelector('.countdown-grid');
  if (!grid) return;

  const target = new Date(EVENT_DATE + 'T00:00:00');

  const pad = n => String(Math.floor(n)).padStart(2, '0');
  let prevValues = {};

  const tick = () => {
    const diff = target - new Date();
    if (diff <= 0) {
      document.getElementById('cd-days').textContent  = '00';
      document.getElementById('cd-hours').textContent = '00';
      document.getElementById('cd-mins').textContent  = '00';
      document.getElementById('cd-secs').textContent  = '00';
      return;
    }
    const days  = Math.floor(diff / 86400000);
    const hours = Math.floor((diff % 86400000) / 3600000);
    const mins  = Math.floor((diff % 3600000) / 60000);
    const secs  = Math.floor((diff % 60000) / 1000);

    const update = (id, val) => {
      const el = document.getElementById(id);
      if (el && prevValues[id] !== val) {
        el.textContent = pad(val);
        el.classList.remove('flip');
        void el.offsetWidth; // reflow
        el.classList.add('flip');
        prevValues[id] = val;
      }
    };
    update('cd-days', days);
    update('cd-hours', hours);
    update('cd-mins', mins);
    update('cd-secs', secs);
  };

  tick();
  setInterval(tick, 1000);
})();

// ── RSVP Form ────────────────────────────────────────────
(function initRsvpForm() {
  const form        = document.getElementById('rsvpForm');
  const wrap        = document.getElementById('rsvpFormWrap');
  const success     = document.getElementById('rsvpSuccess');
  const qrSection   = document.getElementById('qrSection');
  const plusCheck   = document.getElementById('plusOneCheck');
  const plusName    = document.getElementById('plusOneName');
  const fileInput   = document.getElementById('capsulePhotoInput');
  const fileDrop    = document.getElementById('capsuleFileDrop');
  const preview     = document.getElementById('capsulePhotoPreview');

  if (!form) return;

  // Plus one toggle
  plusCheck?.addEventListener('change', () => {
    plusName.style.display = plusCheck.checked ? 'flex' : 'none';
  });

  // Capsule photo preview
  fileInput?.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = e => {
        preview.style.display = 'block';
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
        fileDrop.querySelector('span').textContent = file.name;
      };
      reader.readAsDataURL(file);
    }
  });

  // Form submit
  form.addEventListener('submit', async e => {
    e.preventDefault();

    const btn     = form.querySelector('.btn-submit');
    const btnText = btn.querySelector('.btn-text');
    const btnLoad = btn.querySelector('.btn-loading');
    btn.disabled  = true;
    btnText.style.display = 'none';
    btnLoad.style.display = 'inline-flex';

    const fd = new FormData(form);

    try {
      const res  = await fetch(BASE_URL + '/api/rsvp.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.error) {
        alert(data.error);
        btn.disabled = false;
        btnText.style.display = 'inline';
        btnLoad.style.display = 'none';
        return;
      }

      // Show success
      wrap.style.display    = 'none';
      success.style.display = 'block';

      const msg = document.getElementById('successMessage');
      if (data.attending === 'no') {
        document.querySelector('.success-icon i').className = 'fas fa-heart-crack';
        if (msg) msg.textContent = 'Thank you for letting us know. You will be missed!';
      }

      // Fire confetti
      launchConfetti();

      // Show QR if attending
      if (data.attending !== 'no' && data.qr_token) {
        qrSection.style.display = 'block';
        const qrEl = document.getElementById('qrCode');
        new QRCode(qrEl, {
          text:   data.portal_url,
          width:  160,
          height: 160,
          colorDark:  '#000',
          colorLight: '#fff',
        });
        const link = document.getElementById('guestPortalLink');
        if (link) link.href = data.portal_url;
      }

    } catch (err) {
      alert('Something went wrong. Please try again.');
      btn.disabled = false;
      btnText.style.display = 'inline';
      btnLoad.style.display = 'none';
    }
  });
})();

// ── Confetti ─────────────────────────────────────────────
function launchConfetti() {
  const canvas = document.getElementById('confettiCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  canvas.width  = canvas.offsetWidth;
  canvas.height = canvas.offsetHeight;

  const GOLD   = ['#C9A84C','#F0D080','#9B7A30','#FFD700','#E8B923'];
  const pieces = Array.from({length: 80}, () => ({
    x:    Math.random() * canvas.width,
    y:    -10,
    w:    Math.random() * 8 + 4,
    h:    Math.random() * 12 + 6,
    rot:  Math.random() * 360,
    rotS: (Math.random() - 0.5) * 8,
    vx:   (Math.random() - 0.5) * 4,
    vy:   Math.random() * 3 + 2,
    color: GOLD[Math.floor(Math.random() * GOLD.length)],
    alpha: 1,
  }));

  let frame;
  const draw = () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    let alive = 0;
    pieces.forEach(p => {
      p.x   += p.vx;
      p.y   += p.vy;
      p.rot += p.rotS;
      p.vy  += 0.08;
      if (p.y > canvas.height - 40) p.alpha -= 0.02;
      if (p.alpha > 0 && p.y < canvas.height) {
        alive++;
        ctx.save();
        ctx.globalAlpha = p.alpha;
        ctx.fillStyle   = p.color;
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot * Math.PI / 180);
        ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
        ctx.restore();
      }
    });
    if (alive > 0) frame = requestAnimationFrame(draw);
  };
  draw();
  setTimeout(() => cancelAnimationFrame(frame), 5000);
}

// ── Guest Garden ─────────────────────────────────────────
(function initGarden() {
  const garden = document.getElementById('guestGarden');
  if (!garden) return;
  const count = parseInt(garden.dataset.count) || 0;

  const FLOWERS = ['🌸','🌺','🌷','🌻','🌼','💐','🌹','🪷'];
  const frag = document.createDocumentFragment();

  for (let i = 0; i < Math.min(count, 60); i++) {
    const el    = document.createElement('div');
    const emoji = FLOWERS[Math.floor(Math.random() * FLOWERS.length)];
    const left  = 2 + Math.random() * 95;
    const delay = Math.random() * 3;
    const dur   = 2 + Math.random() * 3;
    const size  = 1.2 + Math.random() * 1.8;

    el.style.cssText = `
      position: absolute;
      left: ${left}%;
      bottom: ${Math.random() * 40}%;
      font-size: ${size}rem;
      animation: gardenFloat ${dur}s ${delay}s ease-in-out infinite alternate;
      opacity: 0;
      animation-fill-mode: forwards;
      cursor: default;
      user-select: none;
    `;
    el.textContent = emoji;
    el.title = 'A confirmed guest 💛';
    frag.appendChild(el);
  }

  garden.appendChild(frag);

  // Inject keyframe
  if (!document.getElementById('gardenStyle')) {
    const style = document.createElement('style');
    style.id = 'gardenStyle';
    style.textContent = `
      @keyframes gardenFloat {
        0%   { transform: translateY(0) scale(1);    opacity: 0.7; }
        100% { transform: translateY(-15px) scale(1.1); opacity: 1; }
      }
    `;
    document.head.appendChild(style);
  }
})();

// ── Guest Journey Map ─────────────────────────────────────
(function initMap() {
  const mapEl = document.getElementById('guestMap');
  if (!mapEl || typeof L === 'undefined') return;

  const map = L.map(mapEl, { zoomControl: true, scrollWheelZoom: false })
    .setView([20, 0], 2);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '© CartoDB',
    subdomains: 'abcd',
    maxZoom: 18,
  }).addTo(map);

  // Custom gold marker
  const goldIcon = L.divIcon({
    className: '',
    html: '<div style="width:12px;height:12px;border-radius:50%;background:#C9A84C;border:2px solid #F0D080;box-shadow:0 0 8px rgba(201,168,76,0.8)"></div>',
    iconSize: [12, 12],
    iconAnchor: [6, 6],
  });

  // Venue marker (centre)
  L.marker([5.6037, -0.187], {
    icon: L.divIcon({
      html: '<div style="width:16px;height:16px;border-radius:50%;background:#C9A84C;border:3px solid #fff;box-shadow:0 0 12px rgba(201,168,76,1)"></div>',
      iconSize: [16,16], iconAnchor: [8,8],
    })
  }).addTo(map).bindPopup('<b>Venue</b>');

  // Fetch & place pins
  fetch(BASE_URL + '/api/map-pins.php')
    .then(r => r.json())
    .then(pins => {
      // Simple geocode: use known coordinates or skip without coords
      pins.forEach(pin => {
        if (pin.lat && pin.lng) {
          const marker = L.marker([pin.lat, pin.lng], { icon: goldIcon }).addTo(map);
          marker.bindPopup(`<b>${pin.guest_name}</b><br>${pin.city}, ${pin.country}`);
        }
      });
    })
    .catch(() => {});
})();

// ── Music toggle ─────────────────────────────────────────
(function initMusic() {
  const btn   = document.getElementById('musicToggle');
  const audio = document.getElementById('bgMusic');
  if (!btn || !audio) return;
  let playing = audio.autoplay;

  btn.addEventListener('click', () => {
    if (playing) { audio.pause(); btn.innerHTML = '<i class="fas fa-music" style="opacity:.4"></i>'; }
    else         { audio.play();  btn.innerHTML = '<i class="fas fa-music"></i>'; }
    playing = !playing;
  });
})();
