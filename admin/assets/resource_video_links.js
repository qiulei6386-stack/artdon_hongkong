(function () {
  'use strict';
  var root = document.querySelector('[data-rv-links]');
  if (!root) return;
  var search = root.querySelector('[data-rv-product-search]');
  var results = root.querySelector('[data-rv-product-results]');
  var status = root.querySelector('[data-rv-product-status]');
  var url = root.querySelector('[name="related_url"]');
  var label = root.querySelector('[name="related_label"]');
  var preview = root.querySelector('[data-rv-link-preview]');
  var data = document.getElementById('rv-link-products');
  var products = [];
  try { products = JSON.parse(data.textContent); } catch (ignored) {}
  function normal(value) {
    return String(value || '').toLowerCase().replace(/[\s._\/(),—–-]+/g, '');
  }
  function refreshPreview() {
    var value = url.value.trim();
    var target = value;
    if (/^[a-z0-9_/-]+\.php(?:[?#]|$)/i.test(target)) target = '/' + target;
    else if (/^(?:[a-z0-9-]+\.)+[a-z]{2,}(?::\d+)?(?:[/?#]|$)/i.test(target)) target = 'https://' + target;
    if (target && !/^[a-z][a-z0-9+.-]*:/i.test(target) && target.charAt(0) !== '/') target = '/' + target;
    var safe = !/[\x00-\x20\x7f\\]/.test(target) && !/^\/\//.test(target) && /^(?:https?:\/\/[^/]+|\/(?!\/))/i.test(target);
    preview.hidden = !value || !safe;
    if (preview.hidden) preview.removeAttribute('href');
    else preview.href = target;
  }
  function render() {
    var terms = search.value.trim().split(/\s+/).map(normal).filter(Boolean);
    results.textContent = '';
    results.hidden = terms.length === 0;
    if (!terms.length) { status.textContent = ''; return; }
    var matches = products.filter(function (item) {
      var text = normal(item.search);
      return terms.every(function (term) { return text.indexOf(term) !== -1; });
    });
    status.textContent = matches.length ? '找到 ' + matches.length + ' 项；点击填入链接' : '未找到产品，可直接输入或粘贴下方链接。';
    matches.slice(0, 30).forEach(function (item) {
      var button = document.createElement('button');
      button.type = 'button';
      var title = document.createElement('strong');
      title.textContent = item.title;
      var detail = document.createElement('small');
      detail.textContent = item.detail;
      button.appendChild(title);
      button.appendChild(detail);
      button.addEventListener('click', function () {
        url.value = item.url;
        if (!label.value.trim()) label.value = 'View Product';
        results.hidden = true;
        status.textContent = '已选择：' + item.title;
        refreshPreview();
      });
      results.appendChild(button);
    });
    if (matches.length > 30) status.textContent += '（显示前 30 项，可继续输入缩小范围）';
  }
  search.addEventListener('input', render);
  search.addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
      event.preventDefault();
      var first = results.querySelector('button');
      if (first && !results.hidden) first.click();
    }
    if (event.key === 'Escape') { event.stopPropagation(); results.hidden = true; }
  });
  url.addEventListener('input', refreshPreview);
  root.querySelector('[data-rv-link-clear]').addEventListener('click', function () {
    url.value = ''; label.value = ''; search.value = '';
    render(); refreshPreview();
  });
  root.querySelector('[data-rv-link-copy]').addEventListener('click', function () {
    if (!url.value.trim()) { status.textContent = '请先填写链接。'; return; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url.value.trim()).then(function () {
        status.textContent = '链接已复制。';
      }).catch(function () { url.focus(); url.select(); status.textContent = '链接已选中，请按 Ctrl/Cmd+C 复制。'; });
    } else { url.focus(); url.select(); status.textContent = '链接已选中，请按 Ctrl/Cmd+C 复制。'; }
  });
  refreshPreview();
})();
