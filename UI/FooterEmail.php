<script>
  // Pass connection success state from PHP to JS
  window.connectionSuccess = <?php echo json_encode(isset($connectionSuccess) ? $connectionSuccess : true); ?>;

  // --- Robust mailto interception (event delegation, capture phase) ---
  function initEmailInterception() {
    // Capture phase ensures we beat other handlers.
    document.addEventListener('click', function (e) {
      const a = e.target.closest('a[href^="mailto:"]');
      if (!a) return;

      // If backend connection not ready, let the normal mail client open.
      if (!window.connectionSuccess) return;

      // Intercept and stop everything so no default mail client opens.
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

      const href = a.href;
      const mailData = parseMailto(href);
      sendEmail(mailData, href);
    }, true); // <-- capture

    // Optional: keyboard safety (e.g., if you later remove href attributes)
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const a = e.target.closest('a[data-mailto]');
      if (!a || !window.connectionSuccess) return;
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      const href = encodeURI(a.dataset.mailto);
      const mailData = parseMailto(href);
      sendEmail(mailData, href);
    }, true);
  }

  // Run immediately or on DOMContentLoaded depending on readyState
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEmailInterception);
  } else {
    initEmailInterception();
  }

  // --- Safe mailto parser (no new URL for mailto) ---
  function parseMailto(mailto) {
    try {
      // mailto:to1,to2?subject=...&body=...&cc=...&bcc=...
      const raw = mailto.replace(/^mailto:/i, '');
      const qIndex = raw.indexOf('?');
      const toPart = qIndex === -1 ? raw : raw.slice(0, qIndex);
      const query = qIndex === -1 ? '' : raw.slice(qIndex + 1);

      // multiple recipients separated by comma or semicolon
      const to = decodeURIComponent(toPart || '').trim();

      const params = new URLSearchParams(query);
      const get = (k) =>
        params.get(k) ?? params.get(k.toUpperCase()) ?? '';

      return {
        to,
        subject: get('subject'),
        body: get('body'),
        cc: get('cc'),
        bcc: get('bcc')
      };
    } catch (err) {
      console.error('Failed to parse mailto:', mailto, err);
      return { to: '', subject: '', body: '', cc: '', bcc: '' };
    }
  }

  // --- Sender (kept close to your original, but safer JSON handling) ---
  function sendEmail(mailData, fallbackHref = null, attachments = []) {
    let fetchOptions;
    if (attachments.length > 0) {
      const formData = new FormData();
      formData.append('mailData', JSON.stringify(mailData));
      attachments.forEach(file => formData.append('attachments[]', file, file.name));
      fetchOptions = { method: 'POST', body: formData, credentials: 'same-origin' };
    } else {
      fetchOptions = {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(mailData),
        credentials: 'same-origin'
      };
    }
    fetch('AjaxHeaderGeneralSendEmail.php', fetchOptions)
    .then(async (res) => {
      // Parse response text first so we can log it if something goes wrong.
      let text = '';
      let data = {};
      try {
        text = await res.text();
        data = JSON.parse(text);
      } catch (_) {
        // leave text/data as captured for later logging
      }
      if (!res.ok) {
        const msg = (data && data.error) ? data.error : `${res.status} ${res.statusText}`;
        const error = new Error(msg);
        error.status = res.status;
        error.statusText = res.statusText;
        error.body = text;
        throw error;
      }
      if (!data || !data.success) {
        const error = new Error((data && data.error) ? data.error : 'Unknown error');
        error.status = res.status;
        error.statusText = res.statusText;
        error.body = text;
        throw error;
      }

      if (!data.savedToSent) {
        if (data.copiedToSelf) {
          console.debug('Email sent but could not be added to SENT folder, but a copy was mailed back to you.');
        } else {
          console.debug('Email sent but could not be added to SENT folder or copied to your inbox.');
        }
      }

      // alert('Email sent successfully!');
    })
    .catch(err => {
      console.error('Send email failed:', err.message, {
        status: err.status,
        statusText: err.statusText,
        response: err.body
      });
      
      let opened = false;
      if (fallbackHref) {
        opened = openMailClient(fallbackHref);
      }

      if (!opened) {
        alert('We are very sorry, but sending the email failed. Please try again or copy the message and send it manually instead.');
      }
    });
  }

  // --- MAIL CLIENT FALLBACK ---
  function openMailClient(href) {
    try {
      const win = window.open(href, '_self');
      if (win !== null) return true;
      window.location.href = href;
      return true;
    } catch (_) {
      return false;
    }
  }

  // --- SIMPLE MAILTO FALLBACK ---
  // Instead of sending via AJAX, build a mailto link and open it directly.
  // function sendEmail(mailData) {
  //   let mailto = `mailto:${encodeURIComponent(mailData.to || '')}`;
  //   const params = [];
  //   if (mailData.subject) params.push(`subject=${encodeURIComponent(mailData.subject)}`);
  //   if (mailData.body)    params.push(`body=${encodeURIComponent(mailData.body)}`);
  //   if (mailData.cc)      params.push(`cc=${encodeURIComponent(mailData.cc)}`);
  //   if (mailData.bcc)     params.push(`bcc=${encodeURIComponent(mailData.bcc)}`);
  //   if (params.length) mailto += `?${params.join('&')}`;
  //   window.location.href = mailto;
  // }
</script>
