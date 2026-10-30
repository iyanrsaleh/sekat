function handleGoogleSignIn(response) {
  const messageEl = document.getElementById('message');

  function showMessage(text, isError) {
    if (!messageEl) {
      return;
    }

    messageEl.textContent = text;
    messageEl.classList.toggle('error', isError);
  }

  fetch('api/auth/google', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ credential: response.credential }),
  })
    .then((result) => result.json().then((body) => ({ ok: result.ok, body })))
    .then(({ ok, body }) => {
      if (!ok) {
        throw new Error((body.error && body.error.message) || 'Masuk dengan Google gagal');
      }

      localStorage.setItem('framework_access', body.data.access_token);
      localStorage.setItem('framework_refresh', body.data.refresh_token);
      window.location.href = 'dashboard';
    })
    .catch((error) => showMessage(error.message, true));
}
