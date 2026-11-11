/* Apx web download instructions. APK bytes go directly to the external source. */
(function () {
  'use strict';
  var button = document.getElementById('downloadButton');
  var overlay = document.getElementById('downloadOverlay');
  if (!button || !overlay) { return; }
  var sheet = document.getElementById('downloadSheet');
  var close = document.getElementById('downloadClose');
  var done = document.getElementById('downloadDone');
  var status = document.getElementById('downloadStatus');
  var busy = false;
  var previousOverflow = '';

  function openSheet() {
    previousOverflow = document.body.style.overflow;
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    close.focus();
  }
  function closeSheet() {
    overlay.hidden = true;
    document.body.style.overflow = previousOverflow;
    button.focus();
  }
  close.addEventListener('click', closeSheet);
  done.addEventListener('click', closeSheet);
  overlay.addEventListener('click', function (event) {
    if (event.target === overlay) { closeSheet(); }
  });
  document.addEventListener('keydown', function (event) {
    if (overlay.hidden) { return; }
    if (event.key === 'Escape') {
      event.preventDefault();
      closeSheet();
    } else if (event.key === 'Tab') {
      // This dialog has two focusable controls.
      if (event.shiftKey && (document.activeElement === close || document.activeElement === sheet)) {
        event.preventDefault(); done.focus();
      } else if (!event.shiftKey && document.activeElement === done) {
        event.preventDefault(); close.focus();
      }
    }
  });
  function formatCount(n) {
    if (n >= 1000000) { return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M'; }
    if (n >= 1000) { return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K'; }
    return String(n);
  }
  button.addEventListener('click', function (event) {
    if (busy) { event.preventDefault(); return; }
    busy = true;
    button.setAttribute('aria-disabled', 'true');
    status.textContent = '';
    openSheet();
    // Keep the real link's default action in the user gesture. Do not fetch the
    // external APK, use a Blob, or wait for the counter before opening the URL.
    window.setTimeout(function () {
      busy = false;
      button.removeAttribute('aria-disabled');
    }, 3000);
    if (!window.fetch || !window.crypto || !window.crypto.getRandomValues) {
      status.textContent = 'The click count could not be updated. The APK link still opens separately.';
      return;
    }
    var bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    var requestId = Array.prototype.map.call(bytes, function (b) {
      return b.toString(16).padStart(2, '0');
    }).join('');
    var body = new URLSearchParams();
    body.set('action', 'download_click');
    body.set('app_id', button.dataset.appId);
    body.set('csrf', button.dataset.csrf);
    body.set('request_id', requestId);
    var controller = window.AbortController ? new AbortController() : null;
    var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 10000) : null;
    fetch(button.dataset.endpoint, {
      method: 'POST', credentials: 'same-origin', body: body,
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      if (!response.ok) { throw new Error('Counter request failed'); }
      return response.json();
    }).then(function (result) {
      if (!result.success || !result.data || !Number.isFinite(result.data.download_count)) {
        throw new Error('Invalid counter response');
      }
      var count = document.getElementById('downloadCount');
      if (count) { count.textContent = formatCount(result.data.download_count); }
    }).catch(function () {
      status.textContent = 'The click count could not be confirmed. The APK link opens separately; check browser Downloads.';
    }).finally(function () {
      if (timeout) { window.clearTimeout(timeout); }
    });
  });
})();
