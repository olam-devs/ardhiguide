(() => {
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- Reveal-on-scroll ----
  const reveals = Array.from(document.querySelectorAll('.reveal'));
  if (!('IntersectionObserver' in window) || reveals.length === 0) {
    reveals.forEach(e => e.classList.add('on'));
  } else {
    const io = new IntersectionObserver((entries) => {
      for (const ent of entries) {
        if (!ent.isIntersecting) continue;
        ent.target.classList.add('on');
        io.unobserve(ent.target);
      }
    }, { threshold: 0.10 });
    reveals.forEach(e => io.observe(e));
  }

  // ---- Hero slider ----
  const slidesWrap = document.querySelector('[data-hero-slides]');
  if (slidesWrap) {
    const slides = Array.from(slidesWrap.querySelectorAll('[data-hero-slide]'));
    const dotsHost = document.querySelector('[data-hero-dots]');
    const prevBtn = document.querySelector('[data-hero-prev]');
    const nextBtn = document.querySelector('[data-hero-next]');
    let idx = 0;
    let timer = null;

    function go(n) {
      idx = (n + slides.length) % slides.length;
      slides.forEach((s, i) => s.classList.toggle('is-active', i === idx));
      if (dotsHost) {
        Array.from(dotsHost.children).forEach((d, i) => d.classList.toggle('is-active', i === idx));
      }
    }
    function next() { go(idx + 1); }
    function prev() { go(idx - 1); }

    if (dotsHost) {
      slides.forEach((_, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'hero-dot' + (i === 0 ? ' is-active' : '');
        b.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        b.addEventListener('click', () => { go(i); restart(); });
        dotsHost.appendChild(b);
      });
    }
    if (prevBtn) prevBtn.addEventListener('click', () => { prev(); restart(); });
    if (nextBtn) nextBtn.addEventListener('click', () => { next(); restart(); });

    function start() {
      if (reduceMotion || slides.length <= 1) return;
      timer = window.setInterval(next, 6500);
    }
    function stop() { if (timer) window.clearInterval(timer); timer = null; }
    function restart() { stop(); start(); }

    slidesWrap.addEventListener('mouseenter', stop);
    slidesWrap.addEventListener('mouseleave', start);
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stop(); else start();
    });
    start();
  }

  // ---- Animated stat counters ----
  const counters = Array.from(document.querySelectorAll('[data-count]'));
  if (counters.length) {
    const reduce = reduceMotion;
    const animate = (el) => {
      const target = parseInt(el.dataset.count || '0', 10) || 0;
      if (reduce) { el.textContent = String(target); return; }
      const start = performance.now();
      const dur = 1600;
      const step = (now) => {
        const t = Math.min(1, (now - start) / dur);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = Math.floor(target * eased).toString();
        if (t < 1) requestAnimationFrame(step);
        else el.textContent = String(target);
      };
      requestAnimationFrame(step);
    };

    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        for (const e of entries) {
          if (e.isIntersecting) {
            animate(e.target);
            io.unobserve(e.target);
          }
        }
      }, { threshold: 0.3 });
      counters.forEach(c => io.observe(c));
    } else {
      counters.forEach(animate);
    }
  }
})();
