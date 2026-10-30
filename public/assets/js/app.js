const statusEl = document.getElementById('api-status');
const outputEl = document.getElementById('api-output');

async function checkApi() {
  try {
    const response = await fetch('api/ping', {
      headers: { Accept: 'application/json' },
    });
    const payload = await response.json();

    statusEl.textContent = payload.ok ? 'OK' : 'GAGAL';
    outputEl.textContent = JSON.stringify(payload, null, 2);
  } catch (error) {
    statusEl.textContent = 'TIDAK TERHUBUNG';
    outputEl.textContent = String(error);
  }
}

checkApi();