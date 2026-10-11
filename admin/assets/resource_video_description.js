(function () {
  'use strict';
  var root = document.querySelector('[data-video-description]');
  if (!root) return;
  var source = root.querySelector('[data-description-source]');
  var ui = root.querySelector('[data-description-ui]');
  var editor = root.querySelector('[data-description-editor]');
  var panel = root.querySelector('[data-description-link-panel]');
  var text = root.querySelector('[data-description-link-text]');
  var url = root.querySelector('[data-description-link-url]');
  var status = root.querySelector('[data-description-status]');
  var savedRange = null;
  var beforeClickRange = null;
  if (!source || !editor || !ui) return;

  function message(value) { status.textContent = value; }
  function escape(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  function cleanUrl(value) {
    value = String(value || '').trim();
    if (!value || value.length > 1000 || /[\u0000-\u0020\u007f\\]/.test(value)) return '';
    try { if (/[\u0000-\u001f\u007f\\]/.test(decodeURIComponent(value))) return ''; } catch (error) { return ''; }
    if (value.indexOf('//') === 0) return '';
    if (value.charAt(0) === '/') return value;
    if (/^[a-z0-9_/-]+\.php(?:[?#]|$)/i.test(value)) value = '/' + value;
    else if (/^(?:[a-z0-9-]+\.)+[a-z]{2,}(?::\d+)?(?:[/?#]|$)/i.test(value)) value = 'https://' + value;
    else if (!/^https?:\/\//i.test(value)) return '';
    if (value.length > 1000) return '';
    try {
      var parsed = new URL(value, location.origin);
      return /^https?:$/.test(parsed.protocol) && !parsed.username && !parsed.password ? value : '';
    } catch (error) { return ''; }
  }
  function serialize(node) {
    if (node.nodeType === 3) return node.nodeValue;
    if (node.nodeType !== 1) return '';
    if (node.tagName === 'BR') return '\n';
    if (node.tagName === 'A') {
      var href = cleanUrl(node.getAttribute('href'));
      return href ? '<a href="' + escape(href) + '">' + escape(node.textContent) + '</a>' : node.textContent;
    }
    var value = '';
    Array.prototype.forEach.call(node.childNodes, function (child) {
      var block = child.nodeType === 1 && /^(DIV|P|LI)$/.test(child.tagName);
      if (block && value && value.slice(-1) !== '\n') value += '\n';
      value += serialize(child);
      if (block && child.nextSibling && value.slice(-1) !== '\n') value += '\n';
    });
    return value;
  }
  function sync() { source.value = serialize(editor); }
  function selectionRange() {
    var selection = window.getSelection();
    if (!selection || !selection.rangeCount) return null;
    var range = selection.getRangeAt(0);
    return editor.contains(range.startContainer) && editor.contains(range.endContainer) ? range.cloneRange() : null;
  }
  function linkAt(node) {
    var element = node.nodeType === 1 ? node : node.parentElement;
    var link = element && element.closest('a');
    return link && editor.contains(link) ? link : null;
  }
  function endRange() {
    var range = document.createRange();
    range.selectNodeContents(editor);
    range.collapse(false);
    return range;
  }
  function restore(range) {
    editor.focus();
    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  }
  var addButton = root.querySelector('[data-description-add-link]');
  addButton.addEventListener('mousedown', function () { beforeClickRange = selectionRange(); });
  addButton.addEventListener('click', function () {
    savedRange = beforeClickRange || selectionRange() || endRange();
    beforeClickRange = null;
    var first = linkAt(savedRange.startContainer);
    var last = linkAt(savedRange.endContainer);
    if (first) savedRange.setStartBefore(first);
    if (last) savedRange.setEndAfter(last);
    text.value = savedRange.toString();
    url.value = first && first === last ? first.getAttribute('href') : '';
    panel.hidden = false;
    message('填写链接网址，然后点击“应用链接”。');
    (text.value ? url : text).focus();
  });
  root.querySelector('[data-description-apply-link]').addEventListener('click', function () {
    var href = cleanUrl(url.value);
    if (!text.value.trim()) { message('请填写链接文字。'); text.focus(); return; }
    if (!href) { message('请输入完整的 http / https 网址，或以 / 开头的站内链接。'); url.focus(); return; }
    if (!savedRange || !editor.contains(savedRange.startContainer) || !editor.contains(savedRange.endContainer)) savedRange = endRange();
    var link = document.createElement('a');
    link.setAttribute('href', href);
    link.textContent = text.value;
    savedRange.deleteContents();
    savedRange.insertNode(link);
    var after = document.createRange();
    after.setStartAfter(link);
    after.collapse(true);
    restore(after);
    panel.hidden = true;
    sync();
    message('文字链接已添加，保存视频后生效。');
  });
  root.querySelector('[data-description-cancel-link]').addEventListener('click', function () {
    panel.hidden = true;
    if (savedRange && editor.contains(savedRange.startContainer) && editor.contains(savedRange.endContainer)) restore(savedRange);
    message('');
  });
  panel.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.isComposing && event.target.tagName === 'INPUT') {
      event.preventDefault();
      root.querySelector('[data-description-apply-link]').click();
    } else if (event.key === 'Escape') {
      event.preventDefault();
      root.querySelector('[data-description-cancel-link]').click();
    }
  });
  root.querySelector('[data-description-remove-link]').addEventListener('click', function () {
    var range = selectionRange();
    var first = range && linkAt(range.startContainer);
    var last = range && linkAt(range.endContainer);
    if (!first || first !== last) { message('请先将光标放在要取消的文字链接中。'); return; }
    var plain = document.createTextNode(first.textContent);
    first.replaceWith(plain);
    range = document.createRange();
    range.selectNodeContents(plain);
    restore(range);
    sync();
    message('已取消链接，保留原文字。');
  });
  editor.addEventListener('input', sync);
  editor.addEventListener('click', function (event) {
    if (linkAt(event.target)) event.preventDefault();
  });
  editor.addEventListener('paste', function (event) {
    if (!event.clipboardData) return;
    event.preventDefault();
    var value = event.clipboardData.getData('text/plain');
    var range = selectionRange() || endRange();
    var plain = document.createTextNode(value);
    range.deleteContents();
    range.insertNode(plain);
    range.setStartAfter(plain);
    range.collapse(true);
    restore(range);
    sync();
  });
  editor.addEventListener('drop', function (event) {
    event.preventDefault();
    message('请使用粘贴添加文字。');
  });
  var form = source.closest('form');
  if (form) form.addEventListener('submit', sync);
  source.hidden = true;
  ui.hidden = false;
})();
