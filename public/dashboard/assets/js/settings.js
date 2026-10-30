window.nxPages = window.nxPages || {};
window.nxPages.settings = function () {
const ACCESS_KEY = 'framework_access';
const FIELDS = ['app_name', 'site_title', 'contact_email', 'timezone'];

const form = document.getElementById('settings-form');
const messageEl = document.getElementById('message');

const passwordForm = document.getElementById('password-form');
const passwordMessage = document.getElementById('password-message');

const accountForm = document.getElementById('account-form');
const accountMessage = document.getElementById('account-message');
const logoutAllButton = document.getElementById('logout-all');
const logoutMessage = document.getElementById('logout-message');

function token() {
  return localStorage.getItem(ACCESS_KEY);
}

function show(el, text, isError = false) {
  if (!el) return;
  el.textContent = text;
  el.classList.toggle('error', isError);
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

async function api(url, options = {}) {
  const response = await window.nxFetch(url, options);

  if (response.status === 204) {
    return null;
  }

  const payload = await response.json();

  if (!response.ok) {
    throw new Error(payload.error?.message ?? 'Something went wrong');
  }

  return payload.data;
}

// --- Ringkasan akun & statistik ---
async function loadMe() {
  try {
    const user = await api('../api/me');

    if (accountForm) {
      accountForm.elements.name.value = user.name ?? '';
      accountForm.elements.email.value = user.email ?? '';
    }

    setText('sum-name', user.name ?? '—');
    setText('sum-email', user.email ?? '—');
    setText('sum-role', user.role ?? '—');
    setText('sum-avatar', user.avatar ? 'Set' : 'Not set');
    setText('sum-initial', (user.name ?? 'A').charAt(0).toUpperCase());

    const google = user.provider === 'google';
    setText('sum-provider', google ? 'Google' : 'Password');
    const providerBadge = document.getElementById('sum-provider');
    if (providerBadge) {
      providerBadge.classList.toggle('badge-primary', google);
      providerBadge.classList.toggle('badge-secondary', !google);
    }
    const passwordCard = document.getElementById('password-card');
    const googleAccount = document.getElementById('google-account');
    if (passwordCard) passwordCard.classList.toggle('d-none', google);
    if (googleAccount) googleAccount.classList.toggle('d-none', !google);
  } catch (error) {
    show(accountMessage, `${error.message} — sign in as admin first.`, true);
  }
}

async function loadStats() {
  if (!document.getElementById('sum-posts') && !document.getElementById('sum-users') && !document.getElementById('sum-uploads')) {
    return;
  }
  if (!localStorage.getItem('framework_access')) {
    return;
  }

  try {
    const stats = await api('../api/stats');
    const counts = stats.counts ?? {};

    setText('sum-posts', counts.posts ?? '—');
    setText('sum-users', counts.users ?? '—');
    setText('sum-uploads', counts.uploads ?? '—');
  } catch (error) {
    // statistik butuh admin; biarkan nilai default
  }
}

// --- Info akun ---
accountForm?.addEventListener('submit', async (event) => {
  event.preventDefault();

  try {
    const user = await api('../api/me', {
      method: 'PUT',
      body: JSON.stringify({
        name: accountForm.elements.name.value,
      }),
    });

    accountForm.elements.name.value = user.name;
    accountForm.elements.email.value = user.email;

    setText('sum-name', user.name);
    setText('sum-email', user.email);
    setText('sum-initial', (user.name ?? 'A').charAt(0).toUpperCase());

    show(accountMessage, 'Profile saved.');
  } catch (error) {
    show(accountMessage, error.message, true);
  }
});

logoutAllButton?.addEventListener('click', async () => {
  if (!window.confirm('Sign out of all sessions on every device?')) {
    return;
  }

  try {
    await api('../api/me/logout-all', { method: 'POST' });
  } catch (error) {
    show(logoutMessage, error.message, true);
  }

  localStorage.removeItem(ACCESS_KEY);
  localStorage.removeItem('framework_refresh');
  window.location.href = '../signin';
});

// --- Identitas aplikasi ---
async function loadSettings() {
  if (!form) return;

  try {
    const data = await api('../api/settings');
    FIELDS.forEach((key) => {
      if (form.elements[key]) {
        form.elements[key].value = data[key] ?? '';
      }
    });

    setText('sum-app', data.app_name ?? '—');
    show(messageEl, 'Settings loaded.');
  } catch (error) {
    show(messageEl, `${error.message} — sign in as admin first.`, true);
  }
}

if (form) form.addEventListener('submit', async (event) => {
  event.preventDefault();

  const payload = {};
  FIELDS.forEach((key) => {
    payload[key] = form.elements[key].value;
  });

  try {
    const data = await api('../api/settings', { method: 'PUT', body: JSON.stringify(payload) });
    setText('sum-app', data.app_name ?? '—');
    show(messageEl, 'Settings saved.');
  } catch (error) {
    show(messageEl, error.message, true);
  }
});

// --- Ubah password ---
passwordForm?.addEventListener('submit', async (event) => {
  event.preventDefault();

  const current = passwordForm.elements.current_password.value;
  const next = passwordForm.elements.new_password.value;
  const confirm = passwordForm.elements.confirm_password.value;

  if (next !== confirm) {
    show(passwordMessage, 'Password confirmation does not match.', true);
    return;
  }

  try {
    await api('../api/me/password', {
      method: 'PUT',
      body: JSON.stringify({ current_password: current, new_password: next }),
    });
    passwordForm.reset();
    show(passwordMessage, 'Password changed.');
  } catch (error) {
    show(passwordMessage, error.message, true);
  }
});

async function loadAppName() {
  try {
    const data = await api('../api/settings/public');
    setText('sum-app', data.app_name ?? '—');
  } catch (error) {
    // abaikan
  }
}

loadMe();

if (form) {
  loadSettings();
}

loadStats();
loadAppName();
};
window.nxPages.settings();