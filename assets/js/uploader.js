/**
 * SM Apx Store - uploader helpers (Task 4.3).
 *
 * Loaded by upload.php. Wires the "Verify Link & Calculate Size" button:
 * sends the APK Source Link to ajax_check_size.php, auto-fills the
 * read-only size display and the hidden size_bytes / size_formatted
 * fields on success, and unlocks the manual size input when detection
 * fails or a Google Drive file is over 100 MB.
 *
 * Any edit to the link after a check clears the result so a stale size
 * can never be submitted with a different URL.
 */
(function () {
  var MB = 1048576; // 1024 * 1024

  var urlInput       = document.getElementById('apk_source_url');
  var verifyBtn      = document.getElementById('verifyLinkBtn');
  var sizeDisplay    = document.getElementById('apkSizeDisplay');
  var sizeStatus     = document.getElementById('apkSizeStatus');
  var manualWrap     = document.getElementById('manualSizeWrap');
  var manualInput    = document.getElementById('manual_size_mb');
  var hiddenBytes    = document.getElementById('size_bytes');
  var hiddenFormatted = document.getElementById('size_formatted');

  if (!urlInput || !verifyBtn) { return; }

  var defaultStatus = sizeStatus.textContent;

  function setStatus(message, isError) {
    sizeStatus.textContent = message;
    sizeStatus.classList.toggle('field-hint-error', !!isError);
  }

  function lockManual() {
    manualWrap.hidden = true;
    manualInput.required = false;
  }

  function unlockManual() {
    manualWrap.hidden = false;
    manualInput.required = true;
  }

  function clearSize() {
    sizeDisplay.value = '';
    hiddenBytes.value = '';
    hiddenFormatted.value = '';
  }

  // Editing the link invalidates any previous check.
  urlInput.addEventListener('input', function () {
    clearSize();
    lockManual();
    setStatus(defaultStatus, false);
  });

  // Manual entry: megabytes in, bytes + formatted string into the hidden
  // fields that Task 4.4 will save into app_submissions.
  manualInput.addEventListener('input', function () {
    var mb = parseFloat(manualInput.value);
    if (isNaN(mb) || mb <= 0 || mb > 4096) {
      hiddenBytes.value = '';
      hiddenFormatted.value = '';
      if (manualInput.value !== '') {
        setStatus('Enter a size between 0.1 and 4096 MB.', true);
      }
      return;
    }
    var bytes = Math.round(mb * MB);
    var formatted = (Math.round(mb * 10) / 10) + ' MB';
    hiddenBytes.value = String(bytes);
    hiddenFormatted.value = formatted;
    setStatus('Manual size saved: ' + formatted + ' (' + bytes.toLocaleString() + ' bytes).', false);
  });

  verifyBtn.addEventListener('click', function () {
    var url = urlInput.value.trim();
    if (url === '') {
      setStatus('Paste an APK link first.', true);
      urlInput.focus();
      return;
    }

    verifyBtn.disabled = true;
    verifyBtn.textContent = 'Checking...';
    setStatus('Contacting the file host...', false);

    var body = new URLSearchParams();
    body.set('apk_url', url);

    fetch('ajax_check_size.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        verifyBtn.disabled = false;
        verifyBtn.textContent = 'Verify Link & Calculate Size';

        if (json && json.success && json.data) {
          var d = json.data;
          sizeDisplay.value = d.size_formatted;

          if (d.manual_required) {
            // Google Drive file over 100 MB: size detected, but the
            // manual field stays mandatory as the roadmap requires.
            unlockManual();
            setStatus('Google Drive files over 100 MB need manual confirmation: type the size below, or switch to a GitHub Release link.', true);
            return;
          }

          hiddenBytes.value = String(d.size_bytes);
          hiddenFormatted.value = d.size_formatted;
          lockManual();
          setStatus('Size detected: ' + d.size_formatted + ' (' + Number(d.size_bytes).toLocaleString() + ' bytes).', false);
        } else {
          clearSize();
          unlockManual();
          setStatus((json && json.error) ? json.error : 'Could not detect the size. Enter it manually below.', true);
        }
      })
      .catch(function () {
        verifyBtn.disabled = false;
        verifyBtn.textContent = 'Verify Link & Calculate Size';
        clearSize();
        unlockManual();
        setStatus('Network error while checking the link. Enter the size manually below.', true);
      });
  });
})();
