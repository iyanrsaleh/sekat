const ACCESS_KEY = 'framework_access';
const REFRESH_KEY = 'framework_refresh';

const form = document.getElementById('signin-form');
const emailInput = form.elements.email;
const passwordInput = form.elements.password;
const logoutButton = document.getElementById('logout-button');
const messageEl = document.getElementById('message');
const outputEl = document.getElementById('output');

function showMessage(text, isError = false) {
  messageEl.textContent = text;
  messageEl.classList.toggle('error', isError);
}

function showOutput(data) {
  outputEl.hidden = false;
  outputEl.textContent = JSON.stringify(data, null, 2);
}

function accessToken() {
  return localStorage.getItem(ACCESS_KEY);
}

async function refreshTokens() {
  const refresh = localStorage.getItem(REFRESH_KEY);

  if (!refresh) {
    throw new Error('Tidak ada refresh token.');
  }

  const response = await fetch('api/auth/refresh', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh_token: refresh }),
  });

  const payload = await response.json();

  if (!response.ok) {
    localStorage.removeItem(ACCESS_KEY);
    localStorage.removeItem(REFRESH_KEY);
    throw new Error(payload.error?.message ?? 'Sesi berakhir, silakan masuk lagi.');
  }

  localStorage.setItem(ACCESS_KEY, payload.data.access_token);
  localStorage.setItem(REFRESH_KEY, payload.data.refresh_token);

  return payload.data.access_token;
}

async function api(url, options = {}) {
  const headers = { Accept: 'application/json', ...(options.headers ?? {}) };
  const token = accessToken();

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const doRequest = async (tokenToUse) => {
    const finalHeaders = { ...headers };

    if (tokenToUse) {
      finalHeaders.Authorization = `Bearer ${tokenToUse}`;
    }

    return fetch(url, { ...options, headers: finalHeaders });
  };

  let response = await doRequest(token);

  if (response.status === 401 && accessToken()) {
    try {
      const fresh = await refreshTokens();
      response = await doRequest(fresh);
    } catch (error) {
      logoutButton.hidden = true;
      throw error;
    }
  }

  if (response.status === 204) {
    return null;
  }

  const payload = await response.json();

  if (!response.ok) {
    throw new Error(payload.error?.message ?? 'Terjadi kesalahan');
  }

  return payload.data;
}

async function loadMe() {
  if (!accessToken()) {
    logoutButton.hidden = true;
    return;
  }

  try {
    const user = await api('api/me');
    showMessage(`Masuk sebagai ${user.name} (${user.email}).`);
    showOutput(user);
    logoutButton.hidden = false;

    // Sudah login (mis. setelah refresh) → langsung ke dashboard.
    window.location.href = 'dashboard';
  } catch (error) {
    showMessage(error.message, true);
    logoutButton.hidden = true;
  }
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  try {
    const response = await fetch('api/auth/login', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: emailInput.value, password: passwordInput.value }),
    });

    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.error?.message ?? 'Login gagal');
    }

    const data = payload.data;
    localStorage.setItem(ACCESS_KEY, data.access_token);
    localStorage.setItem(REFRESH_KEY, data.refresh_token);

    showMessage('Login berhasil. Mengalihkan ke dashboard...');
    showOutput({ token_type: data.token_type, expires_in: data.expires_in, user: data.user });
    logoutButton.hidden = false;
    passwordInput.value = '';

    // Masuk otomatis ke dashboard setelah login.
    window.location.href = 'dashboard';
  } catch (error) {
    showMessage(error.message, true);
  }
});

logoutButton.addEventListener('click', async () => {
  try {
    await api('api/auth/logout', { method: 'POST' });
  } catch (error) {
    showMessage(error.message, true);
  }

  localStorage.removeItem(ACCESS_KEY);
  localStorage.removeItem(REFRESH_KEY);
  logoutButton.hidden = true;
  outputEl.hidden = true;
  showMessage('Token dihapus dari browser.');
});

loadMe();