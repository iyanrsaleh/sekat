// Header dashboard: isi nama/email, ganti foto profil dengan klik avatar.
(function () {
  const DEFAULT_AVATAR =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#cfd8dc"/><circle cx="32" cy="24" r="11" fill="#90a4ae"/><path d="M10 56c4-11 12-16 22-16s18 5 22 16z" fill="#90a4ae"/></svg>'
    );

  const avatarImg = document.getElementById('avatar-img');
  const avatarInput = document.getElementById('avatar-input');
  const avatarMessage = document.getElementById('avatar-message');
  const nameEl = document.getElementById('account-name');
  const emailEl = document.getElementById('account-email');

  function setAvatarPreview(url) {
    if (avatarImg) {
      avatarImg.src = url ? '../' + url : DEFAULT_AVATAR;
    }
  }

  function showAvatarMessage(text, isError) {
    if (avatarMessage) {
      avatarMessage.textContent = text;
      avatarMessage.classList.toggle('tx-danger', !!isError);
    }
  }

  async function loadAccount() {
    if (!localStorage.getItem('framework_access')) {
      if (nameEl) nameEl.textContent = 'Belum masuk';
      if (emailEl) emailEl.textContent = '';
      setAvatarPreview('');
      return;
    }

    try {
      const response = await window.nxFetch('../api/me');
      const payload = await response.json();

      if (!response.ok) {
        if (nameEl) nameEl.textContent = 'Sesi berakhir';
        return;
      }

      const user = payload.data;
      if (nameEl) nameEl.textContent = user.name ?? 'Pengguna';
      if (emailEl) emailEl.textContent = user.email ?? '';
      setAvatarPreview(user.avatar || '');
    } catch (error) {
      // abaikan; header tetap tampil
    }
  }

  async function uploadAvatar(file) {
    showAvatarMessage('Mengunggah...', false);

    const body = new FormData();
    body.append('file', file);

    try {
      const response = await window.nxFetch('../api/me/avatar', { method: 'POST', body });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.error?.message ?? 'Gagal mengunggah');
      }

      setAvatarPreview(payload.data.avatar);
      showAvatarMessage('Foto profil diperbarui.', false);
    } catch (error) {
      showAvatarMessage(error.message, true);
    } finally {
      if (avatarInput) avatarInput.value = '';
    }
  }

  function openPicker(event) {
    event.preventDefault();
    avatarInput?.click();
  }

  document.getElementById('avatar-trigger')?.addEventListener('click', openPicker);
  document.getElementById('avatar-trigger-2')?.addEventListener('click', openPicker);

  avatarInput?.addEventListener('change', function () {
    if (this.files.length) {
      uploadAvatar(this.files[0]);
    }
  });

  if (avatarImg) {
    avatarImg.src = DEFAULT_AVATAR;
  }

  loadAccount();
})();