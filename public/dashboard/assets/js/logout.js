// Logout dashboard: cabut token, hapus sesi browser, kembali ke Sign In.
(function () {
  const ACCESS_KEY = 'framework_access';
  const REFRESH_KEY = 'framework_refresh';

  async function logout() {
    const token = localStorage.getItem(ACCESS_KEY);

    try {
      if (token) {
        await fetch('../api/auth/logout', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            Authorization: 'Bearer ' + token,
          },
        });
      }
    } catch (error) {
      // tetap lanjut hapus sesi walau server gagal dihubungi
    }

    localStorage.removeItem(ACCESS_KEY);
    localStorage.removeItem(REFRESH_KEY);
    window.location.href = '../signin';
  }

  document.querySelectorAll('[data-logout]').forEach(function (element) {
    element.addEventListener('click', function (event) {
      event.preventDefault();
      logout();
    });
  });
})();