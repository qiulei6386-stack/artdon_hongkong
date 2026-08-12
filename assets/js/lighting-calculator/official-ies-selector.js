(function (root) {
  'use strict';

  var state = { catalog:null, category:'all', query:'', onSelect:null, loading:false };
  function $(id) { return document.getElementById(id); }
  function all(selector, rootNode) { return Array.prototype.slice.call((rootNode || document).querySelectorAll(selector)); }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[ch];
    });
  }
  function fmt(value, digits) {
    if (value == null || !Number.isFinite(Number(value))) return '';
    return Number(value).toLocaleString('en-US', { maximumFractionDigits:digits == null ? 1 : digits });
  }
  function imageUrl(path) {
    path = String(path || '').trim();
    if (!path || /^(?:javascript:|data:)/i.test(path)) return '';
    if (/^https?:\/\//i.test(path) || path.charAt(0) === '/') return path;
    return '/' + path.replace(/^\/+/, '');
  }
  function setOpen(open) {
    var modal = $('lcOfficialModal');
    if (!modal) return;
    modal.hidden = !open;
    modal.classList.toggle('is-open', open);
    document.body.classList.toggle('lc-official-open', open);
    if (open) window.setTimeout(function () { $('lcOfficialSearch').focus(); }, 30);
  }
  function status(message, error) {
    var node = $('lcOfficialStatus');
    node.textContent = message || '';
    node.classList.toggle('is-error', !!error);
    node.hidden = !message;
  }
  function optionLabel(option) {
    var label = fmt(option.beam_angle, 1) + '° Beam';
    if (option.nominal_angle != null && Math.abs(Number(option.nominal_angle) - Number(option.beam_angle)) >= 1) label += ' · file ' + fmt(option.nominal_angle, 1) + '°';
    return label;
  }
  function variantHtml(variant, series) {
    var image = imageUrl(variant.image || series.image);
    var meta = [variant.model_code, variant.power_text, variant.lumen_text].filter(Boolean).join(' · ');
    var options = (variant.ies_options || []).map(function (option) {
      var detail = [option.watts != null ? fmt(option.watts, 2) + ' W' : '', option.lumens != null ? fmt(option.lumens, 0) + ' lm' : ''].filter(Boolean).join(' · ');
      return '<button type="button" class="lc-official-option" data-variant-id="' + esc(variant.id) + '" data-member-id="' + esc(option.id) + '">'
        + '<strong>' + esc(optionLabel(option)) + '</strong>' + (detail ? '<small>' + esc(detail) + '</small>' : '') + '</button>';
    }).join('');
    return '<article class="lc-official-variant">'
      + (image ? '<img src="' + esc(image) + '" alt="" loading="lazy">' : '<span class="lc-official-placeholder" aria-hidden="true">IES</span>')
      + '<div class="lc-official-variant-copy"><strong>' + esc(variant.name) + '</strong>' + (meta ? '<small>' + esc(meta) + '</small>' : '') + '<div class="lc-official-options">' + options + '</div></div>'
      + '</article>';
  }
  function render() {
    if (!state.catalog) return;
    var query = state.query.toLowerCase();
    var matching = (state.catalog.series || []).map(function (series) {
      if (state.category !== 'all' && series.category_slug !== state.category) return null;
      var seriesMatch = !query || String(series.name + ' ' + series.slug).toLowerCase().indexOf(query) >= 0;
      var variants = (series.variants || []).filter(function (variant) {
        return seriesMatch || String(variant.name + ' ' + variant.model_code).toLowerCase().indexOf(query) >= 0;
      });
      return variants.length ? { series:series, variants:variants } : null;
    }).filter(Boolean);
    $('lcOfficialResults').innerHTML = matching.map(function (group) {
      return '<section class="lc-official-series"><header><div><span>' + esc(group.series.category_slug.replace(/-/g, ' ')) + '</span><h3>' + esc(group.series.name) + '</h3></div><small>' + group.variants.length + ' product' + (group.variants.length === 1 ? '' : 's') + '</small></header>'
        + group.variants.map(function (variant) { return variantHtml(variant, group.series); }).join('') + '</section>';
    }).join('');
    status(matching.length ? '' : 'No official product IES matches this search.', false);
    bindOptions();
  }
  function bindOptions() {
    all('.lc-official-option', $('lcOfficialResults')).forEach(function (button) {
      button.addEventListener('click', function () {
        if (typeof state.onSelect === 'function') state.onSelect({ variantId:Number(button.dataset.variantId), memberId:button.dataset.memberId });
        setOpen(false);
      });
    });
  }
  function renderCategories() {
    var categories = [{slug:'all',name:'All products'}].concat(state.catalog.categories || []);
    $('lcOfficialCategories').innerHTML = categories.map(function (category) {
      return '<button type="button" data-category="' + esc(category.slug) + '" class="' + (category.slug === state.category ? 'is-active' : '') + '">' + esc(category.name) + '</button>';
    }).join('');
    all('[data-category]', $('lcOfficialCategories')).forEach(function (button) {
      button.addEventListener('click', function () {
        state.category = button.dataset.category;
        renderCategories();
        render();
      });
    });
  }
  function loadCatalog() {
    if (state.catalog || state.loading) { render(); return; }
    state.loading = true;
    status('Loading official photometric library…', false);
    fetch('/api/lighting-calculator-products.php?action=catalog', { credentials:'same-origin', headers:{Accept:'application/json'} })
      .then(function (response) { return response.json().catch(function () { return {}; }).then(function (data) { return {response:response,data:data}; }); })
      .then(function (result) {
        if (!result.response.ok || !result.data.ok) throw new Error(result.data.message || 'The product library could not be loaded.');
        state.catalog = result.data.catalog;
        var stats = state.catalog.stats || {};
        $('lcOfficialStats').textContent = fmt(stats.variant_count, 0) + ' products · ' + fmt(stats.ies_count, 0) + ' verified IES options';
        renderCategories();
        render();
      }).catch(function (error) {
        status(error && error.message ? error.message : 'The product library could not be loaded.', true);
      }).finally(function () { state.loading = false; });
  }
  function open() { setOpen(true); loadCatalog(); }
  function init(options) {
    state.onSelect = options && options.onSelect;
    $('lcOfficialSearch').addEventListener('input', function () { state.query = this.value.trim(); render(); });
    all('[data-lc-official-close]').forEach(function (button) { button.addEventListener('click', function () { setOpen(false); }); });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !$('lcOfficialModal').hidden) setOpen(false);
    });
  }
  root.ArtdonOfficialIesSelector = { init:init, open:open };
})(window);
