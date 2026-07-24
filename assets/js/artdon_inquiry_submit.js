(function () {
  'use strict';

  function isInquiryForm(form) {
    if (!form || form.tagName !== 'FORM') return false;
    var action = form.getAttribute('action') || '';
    return /(?:^|\/)submit_inquiry\.php(?:$|[?#])/i.test(action);
  }

  function feedbackHost(form) {
    var scope = form.closest('.home-inquiry, .contact-page-inquiry, .quote-dialog') || form.parentElement || form;
    return scope.querySelector('.home-inquiry-copy, .quote-dialog-head') || scope;
  }

  function showFeedback(form, type, message) {
    var host = feedbackHost(form);
    var node = host.querySelector('.inquiry-feedback[data-live-inquiry="1"]');
    if (!node) {
      node = document.createElement('p');
      node.setAttribute('data-live-inquiry', '1');
      node.setAttribute('role', 'status');
      node.setAttribute('aria-live', 'polite');
      host.appendChild(node);
    }
    node.className = 'inquiry-feedback ' + (type === 'success' ? 'inquiry-feedback-success' : 'inquiry-feedback-error');
    node.textContent = message;
  }

  function setSubmitting(form, submitting) {
    var button = form.querySelector('button[type="submit"]');
    if (!button) return;
    if (submitting) {
      if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.innerHTML = 'Sending…';
    } else {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    }
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!isInquiryForm(form)) return;
    if (form.dataset.ajaxSubmitting === '1') {
      event.preventDefault();
      return;
    }

    event.preventDefault();
    form.dataset.ajaxSubmitting = '1';
    setSubmitting(form, true);

    var action = form.getAttribute('action') || 'submit_inquiry.php';
    fetch(action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, status: 'error', message: 'The inquiry could not be submitted. Please try again or contact us by email.' };
      });
    }).then(function (data) {
      var ok = data && data.ok === true;
      showFeedback(form, ok ? 'success' : 'error', (data && data.message) || 'The inquiry could not be submitted.');
      if (ok) {
        form.reset();
      }
    }).catch(function () {
      showFeedback(form, 'error', 'The inquiry could not be submitted. Please check your connection or contact us by email.');
    }).finally(function () {
      form.dataset.ajaxSubmitting = '0';
      setSubmitting(form, false);
    });
  });
})();
