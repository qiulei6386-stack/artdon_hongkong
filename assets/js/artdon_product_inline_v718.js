/* Extracted from product.php normal detail page inline scripts. */

/* Keep Technical files on the current product page and scroll to its download section. */
document.addEventListener('click', function(event){
  if(!event.target || !event.target.closest) return;
  var trigger = event.target.closest('[data-technical-files-link]');
  if(!trigger) return;
  var target = document.getElementById('technical-files');
  if(!target) return;
  event.preventDefault();
  window.history.replaceState(null, '', trigger.getAttribute('href'));
  target.scrollIntoView({behavior:'smooth', block:'start'});
});

/* product.php inline script block 1 */
document.querySelectorAll('[data-variant-image]').forEach(function(button){button.addEventListener('click',function(){var image=document.getElementById('variantMainImage');if(!image)return;image.src=this.dataset.variantImage||'';image.alt=this.dataset.variantAlt||'';var figure=image.closest('.variant-main-figure');if(figure){figure.classList.toggle('is-dimension-view',(this.dataset.variantKind||'')==='dimension');}document.querySelectorAll('[data-variant-image]').forEach(function(item){item.classList.remove('is-active');});this.classList.add('is-active');});});

/* product.php inline script block 2 */
(function(){
  function qs(scope, selector){ return scope ? scope.querySelector(selector) : null; }
  function qsa(selector){ return Array.prototype.slice.call(document.querySelectorAll(selector)); }
  function closeShareMenus(){
    qsa('.artdon-share-wrap.is-open').forEach(function(wrap){
      wrap.classList.remove('is-open');
      var menu = qs(wrap, '.artdon-share-menu');
      var trigger = qs(wrap, '.artdon-share-action');
      if(menu) menu.hidden = true;
      if(trigger) trigger.setAttribute('aria-expanded','false');
    });
  }
  function openShareMenu(wrap){
    if(!wrap) return;
    var menu = qs(wrap, '.artdon-share-menu');
    var trigger = qs(wrap, '.artdon-share-action');
    if(!menu || !trigger) return;
    var nativeButton = qs(menu, '[data-share-native]');
    if(nativeButton){ nativeButton.hidden = !navigator.share; }
    wrap.classList.add('is-open');
    menu.hidden = false;
    trigger.setAttribute('aria-expanded','true');
  }
  function sharePayloadFromWrap(wrap){
    var trigger = qs(wrap, '.artdon-share-action');
    var title = trigger ? (trigger.getAttribute('data-share-title') || document.title) : document.title;
    var text = trigger ? (trigger.getAttribute('data-share-text') || title) : title;
    var url = trigger ? (trigger.getAttribute('data-share-url') || window.location.href) : window.location.href;
    return {title:title, text:text, url:url};
  }
  function fallbackCopy(text){
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly','readonly');
    area.style.position = 'fixed';
    area.style.left = '-9999px';
    area.style.top = '0';
    document.body.appendChild(area);
    area.focus();
    area.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(area);
    return Promise.resolve();
  }
  function copyToClipboard(text){
    if(navigator.clipboard && navigator.clipboard.writeText){
      return navigator.clipboard.writeText(text).catch(function(){ return fallbackCopy(text); });
    }
    return fallbackCopy(text);
  }
  function flashButton(button, message){
    if(!button) return;
    var old = button.textContent;
    button.textContent = message || 'Copied';
    window.clearTimeout(button._artdonShareTimer);
    button._artdonShareTimer = window.setTimeout(function(){ button.textContent = old; }, 1400);
  }
  document.addEventListener('click', function(event){
    if(!event.target || !event.target.closest) return;

    var trigger = event.target.closest('.artdon-share-action');
    if(trigger){
      event.preventDefault();
      event.stopPropagation();
      var wrap = trigger.closest('.artdon-share-wrap');
      var shouldOpen = !(wrap && wrap.classList.contains('is-open'));
      closeShareMenus();
      if(shouldOpen) openShareMenu(wrap);
      return;
    }

    var nativeButton = event.target.closest('[data-share-native]');
    if(nativeButton){
      event.preventDefault();
      event.stopPropagation();
      var nativeWrap = nativeButton.closest('.artdon-share-wrap');
      var payload = sharePayloadFromWrap(nativeWrap);
      if(navigator.share){
        navigator.share(payload).catch(function(){ openShareMenu(nativeWrap); });
      }else{
        flashButton(nativeButton, 'Not supported');
      }
      return;
    }

    var copyButton = event.target.closest('[data-share-copy],[data-share-wechat]');
    if(copyButton){
      event.preventDefault();
      event.stopPropagation();
      var copyWrap = copyButton.closest('.artdon-share-wrap');
      var copyUrl = sharePayloadFromWrap(copyWrap).url;
      copyToClipboard(copyUrl).then(function(){
        flashButton(copyButton, copyButton.hasAttribute('data-share-wechat') ? 'Copied for WeChat' : 'Copied');
      });
      return;
    }

    if(!event.target.closest('.artdon-share-wrap')) closeShareMenus();
  }, false);

  document.addEventListener('keydown', function(event){
    if(event.key === 'Escape') closeShareMenus();
  });
})();

/* product.php inline script block 3 */
(function(){
  var modal = document.querySelector('[data-artdon-share-modal]');
  if(!modal) return;
  var input = modal.querySelector('[data-artdon-share-modal-input]');
  var copyBtn = modal.querySelector('[data-artdon-share-modal-copy]');
  var wechatBtn = modal.querySelector('[data-artdon-share-modal-wechat]');
  var lastActive = null;
  var payload = {title: document.title, text: document.title, url: window.location.href};

  function fallbackCopy(text){
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly','readonly');
    area.style.position = 'fixed';
    area.style.left = '-9999px';
    area.style.top = '0';
    document.body.appendChild(area);
    area.focus();
    area.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(area);
    return Promise.resolve();
  }
  function copyText(text){
    if(navigator.clipboard && navigator.clipboard.writeText){
      return navigator.clipboard.writeText(text).catch(function(){ return fallbackCopy(text); });
    }
    return fallbackCopy(text);
  }
  function flash(button, message){
    if(!button) return;
    var old = button.getAttribute('data-old-label') || button.textContent;
    button.setAttribute('data-old-label', old);
    button.textContent = message;
    window.clearTimeout(button._artdonShareV25Timer);
    button._artdonShareV25Timer = window.setTimeout(function(){ button.textContent = old; }, 1300);
  }
  function openModal(button){
    lastActive = document.activeElement;
    payload = {
      title: button.getAttribute('data-share-title') || document.title,
      text: button.getAttribute('data-share-text') || document.title,
      url: button.getAttribute('data-share-url') || window.location.href
    };
    if(input) input.value = payload.url;
    modal.hidden = false;
    modal.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('artdon-share-modal-lock-v25');
    window.setTimeout(function(){ if(input){ input.focus(); input.select(); } }, 30);
  }
  function closeModal(){
    modal.hidden = true;
    modal.setAttribute('aria-hidden','true');
    document.documentElement.classList.remove('artdon-share-modal-lock-v25');
    if(lastActive && lastActive.focus) lastActive.focus();
  }

  document.addEventListener('click', function(event){
    if(!event.target || !event.target.closest) return;
    var opener = event.target.closest('[data-artdon-share-modal-open]');
    if(opener){
      event.preventDefault();
      openModal(opener);
      return;
    }
    if(event.target.closest('[data-artdon-share-modal-close]')){
      event.preventDefault();
      closeModal();
      return;
    }
    if(event.target.closest('[data-artdon-share-modal-copy]')){
      event.preventDefault();
      copyText(payload.url).then(function(){ flash(copyBtn, 'Copied'); });
      return;
    }
    if(event.target.closest('[data-artdon-share-modal-wechat]')){
      event.preventDefault();
      copyText(payload.url).then(function(){ flash(wechatBtn, 'Copied for WeChat'); });
      return;
    }
  });
  document.addEventListener('keydown', function(event){
    if(event.key === 'Escape' && !modal.hidden) closeModal();
  });
})();

/* product.php inline script block 4 */
(function(){
  // V7.1.8.70: pretty URL replaceState disabled. Stable PHP URLs prevent nginx 404 on refresh/copy.
})();
