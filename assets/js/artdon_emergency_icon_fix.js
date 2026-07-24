/* Artdon V6.8.2 emergency DOM guard for oversized icon SVGs */
(function () {
  function fixOversizedIcons() {
    document.querySelectorAll('svg, .footer-social-link, .social-link, .social-icon, [class*="social"] a').forEach(function (el) {
      var r = el.getBoundingClientRect();
      var isSocial = el.matches('.footer-social-link, .social-link, .social-icon, [class*="social"] a') || el.closest('.site-footer, .footer-social, [class*="social"]');
      if (!isSocial) return;
      if (el.tagName.toLowerCase() === 'svg' && (r.width > 80 || r.height > 80)) {
        el.style.setProperty('width', '18px', 'important');
        el.style.setProperty('height', '18px', 'important');
        el.style.setProperty('max-width', '18px', 'important');
        el.style.setProperty('max-height', '18px', 'important');
        el.style.setProperty('position', 'static', 'important');
        el.style.setProperty('transform', 'none', 'important');
      }
      if (el.tagName.toLowerCase() !== 'svg' && (r.width > 100 || r.height > 100)) {
        el.style.setProperty('width', '34px', 'important');
        el.style.setProperty('height', '34px', 'important');
        el.style.setProperty('min-width', '34px', 'important');
        el.style.setProperty('min-height', '34px', 'important');
        el.style.setProperty('max-width', '34px', 'important');
        el.style.setProperty('max-height', '34px', 'important');
        el.style.setProperty('padding', '0', 'important');
        el.style.setProperty('position', 'relative', 'important');
        el.style.setProperty('transform', 'none', 'important');
        el.style.setProperty('overflow', 'hidden', 'important');
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fixOversizedIcons);
  else fixOversizedIcons();
  window.addEventListener('load', fixOversizedIcons);
  new MutationObserver(fixOversizedIcons).observe(document.documentElement, {subtree:true, childList:true, attributes:true});
})();
