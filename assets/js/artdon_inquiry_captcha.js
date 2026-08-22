(function () {
  'use strict';

  function isInquiryForm(form) {
    if (!form || form.tagName !== 'FORM') return false;
    return /(?:^|\/)submit_inquiry\.php(?:$|[?#])/i.test(form.getAttribute('action') || '');
  }

  function randomToken() {
    var bytes = new Uint8Array(16);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(bytes);
    } else {
      for (var i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }
    return Array.prototype.map.call(bytes, function (value) {
      return value.toString(16).padStart(2, '0');
    }).join('');
  }

  function refresh(form, focusInput) {
    if (!isInquiryForm(form)) return;
    var field = form.querySelector('[data-inquiry-captcha]');
    if (!field) return;
    var token = randomToken();
    var tokenInput = field.querySelector('input[name="captcha_token"]');
    var codeInput = field.querySelector('input[name="captcha_code"]');
    var image = field.querySelector('[data-captcha-image]');
    if (tokenInput) tokenInput.value = token;
    if (codeInput) codeInput.value = '';
    if (image) image.src = '/inquiry_captcha.php?token=' + encodeURIComponent(token) + '&v=' + Date.now();
    if (focusInput && codeInput) codeInput.focus();
  }

  function build(form, index) {
    if (!isInquiryForm(form) || form.querySelector('[data-inquiry-captcha]')) return;
    var field = document.createElement('div');
    var inputId = 'inquiryCaptchaCode' + index;
    field.className = 'artdon-captcha-field';
    field.setAttribute('data-inquiry-captcha', '');
    field.innerHTML = ''
      + '<label class="artdon-captcha-label" for="' + inputId + '">Verification code <span aria-hidden="true">*</span></label>'
      + '<div class="artdon-captcha-row">'
      + '  <img class="artdon-captcha-image" data-captcha-image width="156" height="50" alt="Verification code image">'
      + '  <input type="text" class="artdon-captcha-code" id="' + inputId + '" name="captcha_code" maxlength="5" minlength="5" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="' + inputId + 'Hint" placeholder="Code" required>'
      + '  <button type="button" class="artdon-captcha-refresh" data-captcha-refresh aria-label="Show a new verification code">Refresh</button>'
      + '</div>'
      + '<input type="hidden" name="captcha_token" value="">'
      + '<small class="artdon-captcha-hint" id="' + inputId + 'Hint">Enter the 5 characters shown above. Letters are not case-sensitive.</small>';

    var target = form.querySelector('.inquiry-actions, .quote-dialog-actions, .contact-actions, .artdon-fi-actions-v71871');
    if (target) form.insertBefore(field, target);
    else {
      var submit = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submit && submit.parentNode === form) form.insertBefore(field, submit);
      else form.appendChild(field);
    }

    field.querySelector('[data-captcha-refresh]').addEventListener('click', function () {
      refresh(form, true);
    });
    var codeInput = field.querySelector('input[name="captcha_code"]');
    codeInput.addEventListener('input', function () {
      this.value = this.value.toUpperCase().replace(/[^2-9A-HJ-NP-Z]/g, '').slice(0, 5);
    });
    form.addEventListener('reset', function () {
      window.setTimeout(function () { refresh(form, false); }, 0);
    });
    refresh(form, false);
  }

  function mountAll(root) {
    var forms = [];
    if (root && root.matches && root.matches('form')) forms.push(root);
    if (root && root.querySelectorAll) {
      forms = forms.concat(Array.prototype.slice.call(root.querySelectorAll('form')));
    }
    forms.filter(isInquiryForm).forEach(function (form) {
      build(form, document.querySelectorAll('[data-inquiry-captcha]').length + 1);
    });
  }

  window.ArtdonInquiryCaptcha = {
    refresh: refresh,
    mount: mountAll
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { mountAll(document); });
  } else {
    mountAll(document);
  }

  if (window.MutationObserver) {
    new MutationObserver(function (records) {
      records.forEach(function (record) {
        Array.prototype.forEach.call(record.addedNodes || [], function (node) {
          if (node.nodeType === 1) mountAll(node);
        });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
