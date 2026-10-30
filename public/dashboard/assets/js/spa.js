// Navigasi dashboard: ganti isi .content-body saja. Shell (menu, header) tidak di-reload.
(function () {
  const slot = document.querySelector('.content-body');
  if (!slot) return;

  const style = document.createElement('style');
  style.textContent = '.content-body.is-loading{opacity:.45;pointer-events:none}';
  document.head.appendChild(style);

  let current = 0;

  function pageKey(pathname) {
    const parts = pathname.split('/').filter(Boolean);
    const at = parts.indexOf('dashboard');
    if (at < 0) return '';
    const page = parts[at + 1] || 'index';
    return page === 'index' ? 'index' : page;
  }

  function isDashboard(url) {
    return pageKey(url.pathname) !== '';
  }

  function markActive() {
    const key = pageKey(location.pathname);
    document.querySelectorAll('.nav-aside a.nav-link[href]').forEach(function (link) {
      const raw = (link.getAttribute('href') || '').trim();
      const skip = link.hasAttribute('data-logout') || raw === '' || raw === '#' || raw.charAt(0) === '#';
      let on = false;
      if (!skip) {
        try {
          const href = new URL(raw, document.baseURI);
          on = pageKey(href.pathname) === key && key !== '';
        } catch (error) {
          on = false;
        }
      }
      link.classList.toggle('active', on);
      if (link.parentElement && link.parentElement.classList.contains('nav-item')) {
        link.parentElement.classList.toggle('active', on);
      }
    });
  }

  // Nama modul mengikuti nama berkas: settings.js → settings, model-free.js → modelFree.
  // Skrip halaman mendaftarkan window.nxPages[nama] dan memanggilnya di baris terakhir.
  // Tanpa itu, const di tingkat atas gagal saat halaman dibuka kedua kali.
  function moduleName(src) {
    const file = (src.split('?')[0].split('/').pop() || '');
    if (!/^[a-z0-9_-]+\.js$/i.test(file)) return '';
    const base = file.slice(0, -3);
    return base.replace(/-([a-z])/g, function (_, letter) {
      return letter.toUpperCase();
    });
  }

  function runExternal(src) {
    const name = moduleName(src);
    if (name && window.nxPages && typeof window.nxPages[name] === 'function') {
      window.nxPages[name]();
      return Promise.resolve();
    }
    return new Promise(function (resolve, reject) {
      const script = document.createElement('script');
      script.src = src;
      script.onload = function () { resolve(); };
      script.onerror = function () { reject(new Error(src)); };
      document.body.appendChild(script);
    });
  }

  async function show(url, push) {
    const requestId = ++current;
    slot.classList.add('is-loading');
    try {
      const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
      if (!response.ok) {
        location.href = url;
        return;
      }
      const html = await response.text();
      if (requestId !== current) return;
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const next = doc.querySelector('.content-body');
      if (!next) {
        location.href = url;
        return;
      }
      if (push) history.pushState({ spa: 1 }, '', url);
      const title = doc.querySelector('title');
      if (title) document.title = title.textContent;
      slot.innerHTML = next.innerHTML;
      const scripts = Array.prototype.slice.call(slot.querySelectorAll('script'));
      for (let i = 0; i < scripts.length; i++) {
        const raw = scripts[i].getAttribute('src');
        if (raw) {
          await runExternal(new URL(raw, document.baseURI).href);
        } else if ((scripts[i].textContent || '').trim()) {
          const inline = document.createElement('script');
          inline.textContent = scripts[i].textContent;
          document.body.appendChild(inline);
        }
        if (requestId !== current) return;
      }
      if (window.feather) feather.replace();
      markActive();
      slot.scrollTop = 0;
    } catch (error) {
      if (requestId === current) location.href = url;
    } finally {
      if (requestId === current) slot.classList.remove('is-loading');
    }
  }

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('a[href]');
    if (!link || link.hasAttribute('data-logout') || link.target === '_blank' || link.hasAttribute('download')) return;
    let url;
    try {
      url = new URL(link.href, location.href);
    } catch (error) {
      return;
    }
    if (url.origin !== location.origin) return;
    if (!isDashboard(url)) return;
    if (url.pathname.indexOf('/dashboard/assets/') !== -1) return;
    event.preventDefault();
    show(url.href, true);
  });

  window.addEventListener('popstate', function () {
    show(location.href, false);
  });

  markActive();
})();
