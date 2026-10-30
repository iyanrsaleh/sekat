window.nxPages = window.nxPages || {};
window.nxPages.uploads = function () {
const ACCESS_KEY = 'framework_access';

const form = document.getElementById('upload-form');
if (!form) return;
const fileInput = form.elements.file;
const messageEl = document.getElementById('message');
const listEl = document.getElementById('upload-list');

function showMessage(text, isError = false) {
  messageEl.textContent = text;
  messageEl.classList.toggle('error', isError);
}

function token() {
  return localStorage.getItem(ACCESS_KEY);
}

function goToSignIn() {
  window.location.replace('../signin');
}

async function api(url, options = {}) {
  const response = await window.nxFetch(url, options);

  if (response.status === 204) {
    return null;
  }

  const payload = await response.json();

  if (response.status === 401) {
    goToSignIn();
    return null;
  }

  if (!response.ok) {
    throw new Error(payload.error?.message ?? 'Something went wrong');
  }

  return payload.data;
}

function formatBytes(bytes) {
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${bytes} B`;
}

function renderItem(item) {
  const li = document.createElement('li');
  li.className = 'upload-item';

  const img = document.createElement('img');
  img.src = "../" + item.url;
  img.alt = item.name;

  const info = document.createElement('div');
  const name = document.createElement('code');
  name.textContent = item.name;

  const size = document.createElement('span');
  size.textContent = ` ${formatBytes(item.size)}`;

  const remove = document.createElement('button');
  remove.type = 'button';
  remove.textContent = 'Hapus';
  remove.addEventListener('click', () => removeUpload('../' + item.url));

  info.append(name, size);
  li.append(img, info, remove);

  return li;
}

async function loadUploads() {
  if (!token()) {
    goToSignIn();
    return;
  }

  listEl.replaceChildren();

  try {
    const data = await api('../api/uploads');

    if (!data) {
      return;
    }

    const items = data.items ?? [];

    if (items.length === 0) {
      showMessage('Belum ada files.');
      return;
    }

    showMessage(`${items.length} files.`);
    listEl.append(...items.map(renderItem));
  } catch (error) {
    showMessage(error.message, true);
  }
}

async function removeUpload(url) {
  if (!window.confirm(`Hapus ${url}?`)) {
    return;
  }

  try {
    await api(url, { method: 'DELETE' });
    showMessage('File deleted.');
    await loadUploads();
  } catch (error) {
    showMessage(error.message, true);
  }
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (!fileInput.files.length) {
    showMessage('Select a file first.', true);
    return;
  }

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  try {
    const data = await api('../api/uploads', { method: 'POST', body: formData });

    if (!data) {
      return;
    }

    showMessage(`Success: ${data.url}`);
    fileInput.value = '';
    await loadUploads();
  } catch (error) {
    showMessage(error.message, true);
  }
});

loadUploads();
};
window.nxPages.uploads();