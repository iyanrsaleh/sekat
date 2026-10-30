const ACCESS_KEY = 'framework_access';
const REFRESH_KEY = 'framework_refresh';

const form = document.getElementById('signup-form');
const nameInput = form.elements.name;
const emailInput = form.elements.email;
const passwordInput = form.elements.password;
const messageEl = document.getElementById('message');
const outputEl = document.getElementById('output');

function showMessage(text, isError = false) {
  messageEl.textContent = text;
  messageEl.classList.toggle('error', isError);
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  try {
    const response = await fetch('api/auth/register', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: nameInput.value,
        email: emailInput.value,
        password: passwordInput.value,
      }),
    });

    const payload = await response.json();

    if (!response.ok) {
      const code = payload.error?.code ?? 'ERROR';
      const text = payload.error?.message ?? 'Pendaftaran gagal';

      throw new Error(`${response.status} ${code}: ${text}`);
    }

    const data = payload.data;
    localStorage.setItem(ACCESS_KEY, data.access_token);
    localStorage.setItem(REFRESH_KEY, data.refresh_token);

    showMessage(`Akun ${data.user.email} berhasil dibuat. Mengalihkan ke dashboard...`);
    outputEl.hidden = false;
    outputEl.textContent = JSON.stringify(
      { token_type: data.token_type, expires_in: data.expires_in, user: data.user },
      null,
      2
    );

    form.reset();
    passwordInput.value = '';

    // Masuk otomatis ke dashboard setelah registrasi.
    window.location.href = 'dashboard';
  } catch (error) {
    showMessage(error.message, true);
  }
});