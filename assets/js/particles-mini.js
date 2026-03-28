// Minimal particle background for login page
(function() {
  const c = document.getElementById('particleCanvas');
  if (!c) return;
  const x = c.getContext('2d');
  let W, H;
  const r = () => { W = c.width = window.innerWidth; H = c.height = window.innerHeight; };
  r(); window.addEventListener('resize', r);
  const p = Array.from({length:40}, () => ({
    x: Math.random()*9999, y: Math.random()*9999,
    vy: -(Math.random()*.4+.1), a: Math.random()*.2+.05,
    s: Math.random()*1.2+.3, w: Math.random()*6.28
  }));
  (function f() {
    x.clearRect(0,0,W,H);
    p.forEach(pt => {
      pt.y += pt.vy; pt.w += .012; pt.x += Math.sin(pt.w)*.25;
      if (pt.y < -5) { pt.y = H+5; pt.x = Math.random()*W; }
      x.globalAlpha = pt.a; x.fillStyle = '#C9A84C';
      x.beginPath(); x.arc(pt.x%W, pt.y%H, pt.s, 0, 6.28); x.fill();
    });
    x.globalAlpha = 1;
    requestAnimationFrame(f);
  })();
})();
