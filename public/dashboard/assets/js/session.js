// Sesi dashboard: cek login dulu, lalu fetch dengan auto-refresh token.
// Base href mengarah ke /dashboard/ atau /framework/dashboard/, jadi ../api dan ../signin ikut host itu.
(function () {
  const ACCESS_KEY = 'framework_access';
  const REFRESH_KEY = 'framework_refresh';

  document.documentElement.style.visibility = 'hidden';

  function appUrl(path) {
    return new URL(path, document.baseURI).href;
  }

  function goSignIn() {
    const target = appUrl('../signin');

    if (location.href !== target) {
      location.replace(target);
    }
  }

  async function refreshAccess() {
    const refresh = localStorage.getItem(REFRESH_KEY);

    if (!refresh) {
      return null;
    }

    try {
      const response = await fetch(appUrl('../api/auth/refresh'), {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refresh }),
      });

      if (!response.ok) {
        localStorage.removeItem(ACCESS_KEY);
        localStorage.removeItem(REFRESH_KEY);
        return null;
      }

      const payload = await response.json();
      localStorage.setItem(ACCESS_KEY, payload.data.access_token);
      localStorage.setItem(REFRESH_KEY, payload.data.refresh_token);

      return payload.data.access_token;
    } catch (error) {
      return null;
    }
  }

  function buildOptions(options, token) {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };

    if (!(options.body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    if (token) {
      headers.Authorization = 'Bearer ' + token;
    }

    return { ...options, headers };
  }

  async function nxFetch(url, options = {}) {
    let response = await fetch(url, buildOptions(options, localStorage.getItem(ACCESS_KEY)));

    if (response.status === 401) {
      const fresh = await refreshAccess();

      if (fresh) {
        response = await fetch(url, buildOptions(options, fresh));
      }
    }

    return response;
  }

  async function ensureSession() {
    if (!localStorage.getItem(ACCESS_KEY) && !localStorage.getItem(REFRESH_KEY)) {
      goSignIn();
      return;
    }

    try {
      const response = await nxFetch(appUrl('../api/me'));

      if (response.status === 401) {
        localStorage.removeItem(ACCESS_KEY);
        localStorage.removeItem(REFRESH_KEY);
        goSignIn();
        return;
      }
    } catch (error) {
      goSignIn();
      return;
    }

    document.documentElement.style.visibility = '';
  }

  window.nxFetch = nxFetch;
  window.nxRefreshAccess = refreshAccess;
  window.nxEnsureSession = ensureSession();
})();