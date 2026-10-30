const form = document.getElementById('post-form');
const titleInput = form.elements.title;
const bodyInput = form.elements.body;
const submitButton = document.getElementById('submit-button');
const cancelButton = document.getElementById('cancel-button');
const messageEl = document.getElementById('message');
const listEl = document.getElementById('post-list');

let editingId = null;

async function request(url, options = {}) {
  const token = localStorage.getItem('framework_access');
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(url, { ...options, headers });

  if (response.status === 204) {
    return null;
  }

  const payload = await response.json();

  if (!response.ok) {
    const hint = response.status === 401 || response.status === 403 ? 'Masuk sebagai admin dulu di halaman Sign In.' : '';
    throw new Error(`${payload.error?.message ?? 'Terjadi kesalahan'}${hint === '' ? '' : ' ' + hint}`);
  }

  return payload.data;
}

function showMessage(text, isError = false) {
  messageEl.textContent = text;
  messageEl.classList.toggle('error', isError);
}

function renderItem(post) {
  const item = document.createElement('li');
  item.className = 'post-item';

  const title = document.createElement('h2');
  title.textContent = post.title;

  const body = document.createElement('p');
  body.textContent = post.body;

  const actions = document.createElement('div');
  actions.className = 'actions';

  const editButton = document.createElement('button');
  editButton.type = 'button';
  editButton.textContent = 'Edit';
  editButton.addEventListener('click', () => startEdit(post));

  const deleteButton = document.createElement('button');
  deleteButton.type = 'button';
  deleteButton.textContent = 'Hapus';
  deleteButton.addEventListener('click', () => removePost(post.id));

  actions.append(editButton, deleteButton);
  item.append(title, body, actions);

  return item;
}

async function loadPosts() {
  listEl.replaceChildren();

  try {
    const data = await request('api/posts');
    const items = data.items ?? [];

    if (items.length === 0) {
      showMessage('Belum ada post. Tambahkan lewat form di atas.');
      return;
    }

    showMessage(`${items.length} post.`);
    listEl.append(...items.map(renderItem));
  } catch (error) {
    showMessage(error.message, true);
  }
}

function startEdit(post) {
  editingId = post.id;
  titleInput.value = post.title;
  bodyInput.value = post.body;
  submitButton.textContent = 'Simpan perubahan';
  cancelButton.hidden = false;
  titleInput.focus();
}

function resetForm() {
  editingId = null;
  form.reset();
  submitButton.textContent = 'Tambah';
  cancelButton.hidden = true;
}

async function removePost(id) {
  if (!window.confirm('Hapus post ini?')) {
    return;
  }

  try {
    await request(`api/posts/${id}`, { method: 'DELETE' });
    showMessage('Post dihapus.');
    await loadPosts();
  } catch (error) {
    showMessage(error.message, true);
  }
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  const payload = JSON.stringify({
    title: titleInput.value,
    body: bodyInput.value,
  });

  try {
    if (editingId === null) {
      await request('api/posts', { method: 'POST', body: payload });
      showMessage('Post ditambahkan.');
    } else {
      await request(`api/posts/${editingId}`, { method: 'PUT', body: payload });
      showMessage('Post diperbarui.');
    }

    resetForm();
    await loadPosts();
  } catch (error) {
    showMessage(error.message, true);
  }
});

cancelButton.addEventListener('click', resetForm);

loadPosts();