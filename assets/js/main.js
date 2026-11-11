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

/* Report App form. Independent of the download button. */
(function () {
  'use strict';
  var openButton = document.getElementById('reportButton');
  var overlay = document.getElementById('reportOverlay');
  if (!openButton || !overlay) { return; }
  var sheet = document.getElementById('reportSheet');
  var form = document.getElementById('reportForm');
  var closeButton = document.getElementById('reportClose');
  var cancel = document.getElementById('reportCancel');
  var submit = document.getElementById('reportSubmit');
  var reason = document.getElementById('reportReason');
  var details = document.getElementById('reportDetails');
  var count = document.getElementById('reportCount');
  var status = document.getElementById('reportStatus');
  var toast = document.getElementById('reportToast');
  var sending = false;
  var toastTimer = null;
  var previousOverflow = '';
  var UNSURE = 'We could not confirm that your report was sent. It may already have been received, so please wait a moment before sending it again.';

  function setStatus(text, isError) {
    status.textContent = text;
    status.className = 'caption report-status' + (isError ? ' is-error' : '');
  }
  function showToast(text) {
    toast.textContent = text;
    toast.hidden = false;
    if (toastTimer) { window.clearTimeout(toastTimer); }
    toastTimer = window.setTimeout(function () { toast.hidden = true; }, 4000);
  }
  function openSheet() {
    previousOverflow = document.body.style.overflow;
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    reason.focus();
  }
  function closeSheet() {
    if (sending) { return; }
    overlay.hidden = true;
    document.body.style.overflow = previousOverflow;
    openButton.focus();
  }
  function updateCount() {
    count.textContent = details.value.length + ' / 1000';
  }
  function focusables() {
    return [closeButton, reason, details, submit, cancel].filter(function (el) { return !el.disabled; });
  }

  openButton.addEventListener('click', function () {
    setStatus('', false);
    openSheet();
  });
  closeButton.addEventListener('click', closeSheet);
  cancel.addEventListener('click', closeSheet);
  overlay.addEventListener('click', function (event) {
    if (event.target === overlay) { closeSheet(); }
  });
  details.addEventListener('input', updateCount);
  document.addEventListener('keydown', function (event) {
    if (overlay.hidden) { return; }
    if (event.key === 'Escape') {
      event.preventDefault();
      closeSheet();
    } else if (event.key === 'Tab') {
      var items = focusables();
      var first = items[0];
      var last = items[items.length - 1];
      if (event.shiftKey && (document.activeElement === first || document.activeElement === sheet)) {
        event.preventDefault(); last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
      }
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (sending) { return; }
    if (!reason.value) {
      setStatus('Please select a reason.', true);
      reason.focus();
      return;
    }
    if (!window.fetch) {
      setStatus('This browser cannot send the report. Please update your browser.', true);
      return;
    }
    sending = true;
    submit.disabled = true;
    submit.textContent = 'Sending...';
    setStatus('', false);
    var payload = { app_id: parseInt(openButton.dataset.appId, 10), reason: reason.value };
    var text = details.value.trim();
    if (text !== '') { payload.details = text; }
    var controller = window.AbortController ? new AbortController() : null;
    var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 15000) : null;
    var httpStatus = 0;
    fetch(openButton.dataset.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      httpStatus = response.status;
      return response.json().catch(function () { return null; });
    }).then(function (result) {
      if (httpStatus === 201 && result && result.success) {
        sending = false;
        form.reset();
        updateCount();
        closeSheet();
        showToast('Report sent. Thank you.');
        return;
      }
      if (httpStatus === 429) {
        setStatus('Too many reports. Please wait a minute and try again.', true);
      } else if (httpStatus === 404) {
        setStatus('This app is no longer available.', true);
      } else if (httpStatus === 400) {
        setStatus('Please check the reason and details, then try again.', true);
      } else {
        // Unknown outcome: the server may have saved the report before failing.
        setStatus(UNSURE, true);
      }
    }).catch(function () {
      // Timeout or network loss: the request may still have reached the server.
      setStatus(UNSURE, true);
    }).finally(function () {
      if (timeout) { window.clearTimeout(timeout); }
      sending = false;
      submit.disabled = false;
      submit.textContent = 'Send report';
    });
  });
})();
