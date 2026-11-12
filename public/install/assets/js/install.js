(function () {
  'use strict';

  var base = '../api/install';

  function $(id) {
    return document.getElementById(id);
  }

  function show(id) {
    var steps = document.querySelectorAll('.step');
    for (var i = 0; i < steps.length; i++) {
      steps[i].classList.add('hidden');
    }
    $(id).classList.remove('hidden');
  }

  function actions(id, visible) {
    var el = $(id);
    if (el) {
      el.classList.toggle('hidden', !visible);
    }
  }

  function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function setMsg(id, text, type) {
    var el = $(id);
    el.textContent = text;
    el.className = 'form-msg ' + (type || 'error');
    el.classList.toggle('hidden', !text);
  }

  function json(path, body, method) {
    var opts = {
      method: method || (body ? 'POST' : 'GET'),
      headers: { 'Content-Type': 'application/json' }
    };
    if (body) {
      opts.body = JSON.stringify(body);
    }
    return fetch(base + path, opts).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (payload) {
        if (!res.ok) {
          throw new Error((payload.error && payload.error.message) || res.statusText);
        }
        return payload.data;
      });
    });
  }

  var LABELS = {
    pdo: 'PDO',
    pdo_mysql: 'MySQL (PDO)',
    mbstring: 'Multibyte String',
    json: 'JSON',
    root: 'Folder proyek',
    env: 'File .env',
    vendor_storage: 'Folder storage'
  };

  function label(key) {
    return LABELS[key] || key;
  }

  function checkRow(name, ok) {
    var cls = ok ? 'ok' : 'bad';
    var text = ok ? 'OK' : 'TIDAK';
    return '<div class="check-item">' +
      '<span class="dot ' + cls + '"></span>' +
      '<span class="check-name">' + escapeHtml(name) + '</span>' +
      '<span class="check-status ' + cls + '">' + text + '</span>' +
      '</div>';
  }

  function group(title, map) {
    var html = '<div class="check-group-title">' + escapeHtml(title) + '</div>';
    var allOk = true;
    var keys = Object.keys(map);
    for (var i = 0; i < keys.length; i++) {
      var key = keys[i];
      var ok = map[key];
      allOk = allOk && ok;
      html += checkRow(label(key), ok);
    }
    return { html: html, ok: allOk };
  }

  function init() {
    json('/status').then(function (status) {
      var content = $('status-content');
      var act = $('status-actions');

      if (status.installed) {
        content.innerHTML = '<div>Sudah terinstal. <a href="../dashboard">Buka dasbor</a></div>';
        actions('status-actions', false);
      } else {
        content.innerHTML = '<div>Belum terinstal (' + escapeHtml(status.reason) + ').</div>';
        actions('status-actions', true);
      }
    }).catch(function (err) {
      $('status-content').innerHTML = '<div class="msg-error">Gagal memeriksa status: ' + escapeHtml(err.message) + '</div>';
    });
  }

  function runCheck() {
    var content = $('check-content');

    json('/check').then(function (data) {
      var html = '';
      var allOk = true;

      html += '<div class="check-group-title">PHP ' + escapeHtml(data.php) + '</div>';
      html += checkRow('Versi PHP (min. 8.1)', data.php_ok);

      var ext = group('Ekstensi PHP', data.extensions);
      html += ext.html;
      allOk = allOk && ext.ok;

      var writ = group('Izin tulis folder', data.writable);
      html += writ.html;
      allOk = allOk && writ.ok;

      content.innerHTML = html;
      actions('check-actions', allOk);
      if (!allOk) {
        content.innerHTML += '<div class="msg-error mt">Beberapa persyaratan tidak terpenuhi.</div>';
      }
    }).catch(function (err) {
      content.innerHTML = '<div class="msg-error">Gagal memeriksa server: ' + escapeHtml(err.message) + '</div>';
    });
  }

  function saveConfig() {
    var form = $('config-form');
    var body = {};
    var formData = new FormData(form);
    formData.forEach(function (val, key) { body[key] = val; });

    json('/config', body).then(function () {
      show('step-migrate');
      runMigrate();
    }).catch(function (err) {
      setMsg('config-msg', err.message, 'error');
    });
  }

  function runMigrate() {
    var content = $('migrate-content');
    var act = $('migrate-actions');

    json('/migrate', null, 'POST').then(function (result) {
      content.innerHTML =
        '<div class="msg-success">Migrasi selesai.</div>' +
        '<div>Admin: ' + escapeHtml(result.admin.email) + ' (' + (result.admin.created ? 'dibuat' : 'sudah ada') + ')</div>';
      actions('migrate-actions', true);
    }).catch(function (err) {
      content.innerHTML = '<div class="msg-error">Gagal migrasi: ' + escapeHtml(err.message) + '</div>';
    });
  }

  function bind() {
    var btnToCheck = $('btn-to-check');
    if (btnToCheck) {
      btnToCheck.addEventListener('click', function () { show('step-check'); runCheck(); });
    }

    var btnToConfig = $('btn-to-config');
    if (btnToConfig) {
      btnToConfig.addEventListener('click', function () { show('step-config'); });
    }

    var btnBack = $('btn-back-check');
    if (btnBack) {
      btnBack.addEventListener('click', function () { show('step-check'); });
    }

    var btnFinish = $('btn-finish');
    if (btnFinish) {
      btnFinish.addEventListener('click', function () { window.location.href = 'done'; });
    }

    var form = $('config-form');
    if (form) {
      form.addEventListener('submit', function (e) { e.preventDefault(); saveConfig(); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { bind(); init(); });
  } else {
    bind();
    init();
  }
})();