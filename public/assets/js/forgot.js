const form = document.getElementById('forgot-form');
const messageEl = document.getElementById('message');

function showMessage(text, isError) {
  messageEl.textContent = text;
  messageEl.classList.toggle('error', isError);
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  try {
    const response = await fetch('api/auth/forgot-password', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: form.elements.email.value }),
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error((payload.error && payload.error.message) || 'Gagal mengirim tautan');
    }

    showMessage(payload.data.message || 'Bila email terdaftar, tautan reset telah dikirim.');
    form.reset();
  } catch (error) {
    showMessage(error.message, true);
  }
});
