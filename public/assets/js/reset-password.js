const form = document.getElementById('reset-form');
const messageEl = document.getElementById('message');
const token = new URLSearchParams(location.search).get('token') || '';

function showMessage(text, isError) {
  messageEl.textContent = text;
  messageEl.classList.toggle('error', isError);
}

if (!token) {
  showMessage('Tautan reset tidak lengkap.', true);
  form.hidden = true;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (form.elements.password.value !== form.elements.confirm.value) {
    showMessage('Password tidak sama.', true);
    return;
  }

  try {
    const response = await fetch('api/auth/reset-password', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({
        token,
        password: form.elements.password.value,
      }),
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error((payload.error && payload.error.message) || 'Reset gagal');
    }

    showMessage('Password sudah diganti. Mengalihkan ke Sign In...');
    window.location.href = 'signin';
  } catch (error) {
    showMessage(error.message, true);
  }
});
