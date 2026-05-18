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

  // ---- Toast notifications ----
  const toastHost = document.getElementById('toast-host');
  function showToast(message, type) {
    if (!toastHost || !message) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'err' ? 'err' : type === 'info' ? 'info' : 'ok');
    el.textContent = message;
    toastHost.appendChild(el);
    window.setTimeout(() => {
      el.style.opacity = '0';
      el.style.transition = 'opacity .3s ease';
      window.setTimeout(() => el.remove(), 320);
    }, type === 'info' ? 2200 : 4500);
  }

  if (window.__pageFlash) {
    if (window.__pageFlash.ok) showToast(window.__pageFlash.ok, 'ok');
    if (window.__pageFlash.err) showToast(window.__pageFlash.err, 'err');
  }

  if (window.__adminHighlight) {
    const h = window.__adminHighlight;
    const row = document.getElementById('listing-row-' + h.id);
    if (row) {
      row.scrollIntoView({ behavior: 'smooth', block: 'center' });
      const btn = row.querySelector('[data-action="' + h.action + '"]');
      if (btn) {
        btn.classList.add('is-pressed');
        window.setTimeout(() => btn.classList.remove('is-pressed'), 1200);
      }
    }
    if (h.action && h.action !== 'delete') {
      const labels = {
        approve: h.fail ? 'Approve failed' : 'Approved successfully',
        reject: h.fail ? 'Reject failed' : 'Rejected successfully',
        review: h.fail ? 'Update failed' : 'Marked under review',
        feature_on: h.fail ? 'Feature failed' : 'Listing featured',
        feature_off: h.fail ? 'Unfeature failed' : 'Unfeatured',
        badge: h.fail ? 'Badge save failed' : 'Badge saved',
      };
      showToast(labels[h.action] || (h.fail ? 'Action failed' : 'Done'), h.fail ? 'err' : 'ok');
    }
    if (h.action === 'delete' && !h.fail) {
      showToast('Listing deleted', 'ok');
    }
  }

  document.querySelectorAll('.admin-action-form').forEach((form) => {
    form.addEventListener('submit', (ev) => {
      const btn = ev.submitter;
      if (!btn || !(btn instanceof HTMLButtonElement)) return;
      btn.classList.add('is-pressed', 'is-loading');
      const action = btn.getAttribute('data-action') || btn.value || 'action';
      const id = form.getAttribute('data-listing-id') || '';
      const pending = {
        approve: 'Approving listing…',
        reject: 'Rejecting listing…',
        review: 'Setting under review…',
        feature_on: 'Featuring listing…',
        feature_off: 'Removing feature…',
        badge: 'Saving badge…',
        delete: 'Deleting listing…',
      };
      showToast(pending[action] || 'Processing…', 'info');
    });
  });

  // ---- Password show / hide toggle ----
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest && ev.target.closest('.pwd-toggle');
    if (!btn) return;
    ev.preventDefault();
    const wrap = btn.closest('.pwd');
    if (!wrap) return;
    const input = wrap.querySelector('input');
    if (!input) return;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.classList.toggle('is-on', isHidden);
    btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
  });
})();
