const ALIAS = 'jsonplaceholder';

const pathInput = document.getElementById('path');
const fetchButton = document.getElementById('fetch-button');
const messageEl = document.getElementById('message');
const outputEl = document.getElementById('output');

function showMessage(text, isError = false) {
  messageEl.textContent = text;
  messageEl.classList.toggle('error', isError);
}

async function fetchProxy(path) {
  const url = `api/${ALIAS}${path.startsWith('/') ? path : '/' + path}`;

  try {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const text = await response.text();

    outputEl.textContent = text;
    showMessage(`HTTP ${response.status} — ${url}`);
  } catch (error) {
    showMessage(error.message, true);
  }
}

fetchButton.addEventListener('click', () => fetchProxy(pathInput.value));

document.querySelectorAll('button[data-path]').forEach((button) => {
  button.addEventListener('click', () => {
    pathInput.value = button.dataset.path;
    fetchProxy(button.dataset.path);
  });
});

fetchProxy(pathInput.value);