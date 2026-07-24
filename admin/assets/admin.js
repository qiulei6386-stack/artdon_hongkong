(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function reindexRepeater(container) {
    const items = $$('.repeat-item', container).filter(item => item.parentElement === container);
    items.forEach((item, index) => {
      item.dataset.index = String(index);
      $$('[name]', item).forEach(el => {
        let name = el.getAttribute('name') || '';
        name = name.replace(/\[(?:__INDEX__|\d+)\]/, `[${index}]`);
        name = name.replace(/_(?:__INDEX__|\d+)$/, `_${index}`);
        el.setAttribute('name', name);
      });
      const strong = $('.repeat-head strong', item);
      if (strong && /^新(轮播|产品|文章|分类|特点|能力)/.test(strong.textContent.trim())) {
        strong.dataset.newItem = '1';
      }
    });
  }

  function ensureRepeatTools(item) {
    const head = $('.repeat-head', item);
    if (!head) return;
    item.setAttribute('draggable', 'true');
    let tools = $('.repeat-tools', head);
    if (!tools) {
      tools = document.createElement('div');
      tools.className = 'repeat-tools';
      head.appendChild(tools);
    }
    if (!$('.drag-handle', head)) {
      const handle = document.createElement('button');
      handle.type = 'button';
      handle.className = 'drag-handle';
      handle.title = '拖动排序';
      handle.setAttribute('aria-label', '拖动排序');
      handle.textContent = '⋮⋮';
      head.insertBefore(handle, head.firstChild);
    }
    if (!$('[data-move-up]', tools)) {
      const up = document.createElement('button');
      up.type = 'button'; up.dataset.moveUp = ''; up.title = '上移'; up.textContent = '↑';
      tools.insertBefore(up, tools.firstChild);
    }
    if (!$('[data-move-down]', tools)) {
      const down = document.createElement('button');
      down.type = 'button'; down.dataset.moveDown = ''; down.title = '下移'; down.textContent = '↓';
      tools.insertBefore(down, tools.children[1] || null);
    }
    if (!$('[data-collapse-item]', tools) && !item.classList.contains('layout-item')) {
      const collapse = document.createElement('button');
      collapse.type = 'button'; collapse.dataset.collapseItem = ''; collapse.title = '折叠/展开'; collapse.textContent = '收起';
      tools.appendChild(collapse);
    }
    const remove = $('[data-remove-repeat]', head);
    if (remove && remove.parentElement !== tools) tools.appendChild(remove);
  }

  function initRepeaters(root = document) {
    $$('.repeater', root).forEach(container => {
      container.dataset.repeaterReady = '1';
      $$('.repeat-item', container).filter(item => item.parentElement === container).forEach(ensureRepeatTools);
      reindexRepeater(container);
    });
  }

  function moveItem(item, direction) {
    const container = item.parentElement;
    if (!container?.classList.contains('repeater')) return;
    if (direction < 0 && item.previousElementSibling) container.insertBefore(item, item.previousElementSibling);
    if (direction > 0 && item.nextElementSibling) container.insertBefore(item.nextElementSibling, item);
    reindexRepeater(container);
  }

  function setCollapsed(item, collapsed) {
    item.classList.toggle('is-collapsed', collapsed);
    const btn = $('[data-collapse-item]', item);
    if (btn) btn.textContent = collapsed ? '展开' : '收起';
  }

  let draggedItem = null;
  document.addEventListener('dragstart', event => {
    const item = event.target.closest('.repeat-item');
    if (!item || !item.parentElement?.classList.contains('repeater')) return;
    if (!event.target.closest('.drag-handle')) { event.preventDefault(); return; }
    draggedItem = item;
    item.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', item.dataset.index || '0');
  });
  document.addEventListener('dragend', () => {
    if (draggedItem) {
      const container = draggedItem.parentElement;
      draggedItem.classList.remove('is-dragging');
      if (container) reindexRepeater(container);
    }
    draggedItem = null;
    $$('.repeat-item.is-drag-over').forEach(el => el.classList.remove('is-drag-over'));
  });
  document.addEventListener('dragover', event => {
    if (!draggedItem) return;
    const target = event.target.closest('.repeat-item');
    if (!target || target === draggedItem || target.parentElement !== draggedItem.parentElement) return;
    event.preventDefault();
    target.classList.add('is-drag-over');
    const rect = target.getBoundingClientRect();
    const before = event.clientY < rect.top + rect.height / 2;
    target.parentElement.insertBefore(draggedItem, before ? target : target.nextElementSibling);
  });
  document.addEventListener('dragleave', event => {
    event.target.closest('.repeat-item')?.classList.remove('is-drag-over');
  });

  function inferMediaUsage(input, type) {
    const section = $('input[name="section"]')?.value || '';
    if (type === 'video') return 'videos';
    if (section === 'hero') return 'banners';
    if (section === 'products' || section === 'featured_system') return 'products';
    if (section === 'projects') return 'projects';
    if (section === 'insights') return 'articles';
    return 'images';
  }

  function mediaUrl(path) {
    if (!path) return '';
    if (/^(?:https?:)?\/\//i.test(path) || path.startsWith('/')) return path;
    return '../' + path.replace(/^\.\//, '');
  }

  function updateFieldPreview(input, type) {
    const field = input.closest('.field');
    if (!field) return;
    let preview = $('.media-field-preview', field);
    if (!preview) {
      preview = document.createElement('div');
      preview.className = 'media-field-preview';
      field.appendChild(preview);
    }
    const path = input.value.trim();
    if (!path) { preview.innerHTML = ''; preview.hidden = true; return; }
    preview.hidden = false;
    const url = mediaUrl(path);
    if (type === 'video') {
      preview.innerHTML = `<video src="${url.replace(/"/g, '&quot;')}" muted controls preload="metadata"></video>`;
    } else if (type === 'file') {
      const label = path.split('/').pop() || path;
      preview.innerHTML = `<a class="media-file-preview" href="${url.replace(/"/g, '&quot;')}" target="_blank" rel="noopener">${label.replace(/</g,'&lt;').replace(/>/g,'&gt;')} ↗</a>`;
    } else {
      preview.innerHTML = `<img src="${url.replace(/"/g, '&quot;')}" alt="当前媒体预览">`;
    }
  }

  function findMediaAltInput(input) {
    if (!input) return null;
    const form = input.form || document;
    const field = input.closest('.field');
    const name = input.name || '';
    const byName = targetName => {
      if (!targetName) return null;
      if (form.elements && form.elements[targetName]) return form.elements[targetName];
      return $$('input,textarea', form).find(el => el.name === targetName) || null;
    };
    if (input.dataset.mediaAltTarget) {
      const byTarget = byName(input.dataset.mediaAltTarget);
      if (byTarget) return byTarget;
    }
    const candidates = [];
    if (name === 'cover_image') candidates.push('cover_alt');
    if (name === 'project_image') candidates.push('project_image_alt');
    if (name.endsWith('[image]')) {
      candidates.push(name.replace(/\[image\]$/, '[image_alt]'));
      candidates.push(name.replace(/\[image\]$/, '[alt]'));
    }
    if (name.endsWith('_image')) candidates.push(name.replace(/_image$/, '_image_alt'), name.replace(/_image$/, '_alt'));
    for (const candidate of candidates) {
      const found = byName(candidate);
      if (found) return found;
    }
    if (field) {
      const next = field.nextElementSibling;
      if (next && next.classList.contains('field')) {
        const label = (next.textContent || '').toLowerCase();
        const inputNext = next.querySelector('input,textarea');
        if (inputNext && (label.includes('alt') || label.includes('图片名称'))) return inputNext;
      }
    }
    return null;
  }

  function setMediaAltFromPicker(input, pickerButton) {
    const altInput = findMediaAltInput(input);
    if (!altInput) return;
    const label = pickerButton?.querySelector('strong')?.textContent || '';
    const alt = (pickerButton?.dataset.mediaAlt || label || '').trim();
    if (alt) altInput.value = alt;
  }

  function clearMediaAltForInput(input) {
    const altInput = findMediaAltInput(input);
    if (altInput) altInput.value = '';
  }

  function enhanceMediaFields(root = document) {
    const candidates = $$('input[type="text"],input:not([type])', root).filter(input => {
      const name = input.name || '';
      return !!input.dataset.mediaField || name === 'image' || name === 'video' || /\[(image|video)\]$/.test(name);
    });
    candidates.forEach(input => {
      if (input.dataset.mediaEnhanced === '1') return;
      input.dataset.mediaEnhanced = '1';
      input.classList.add('media-path-input');
      const type = input.dataset.mediaField || (/video/.test(input.name) ? 'video' : 'image');
      const usage = input.dataset.mediaUsage || inferMediaUsage(input, type);
      const field = input.closest('.field');
      if (!field) return;
      const legacyPreview = $('.preview-thumb', field);
      if (legacyPreview) legacyPreview.remove();
      const actions = document.createElement('div');
      actions.className = 'media-field-actions';
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'admin-button-secondary';
      button.dataset.mediaOpen = ''; button.dataset.mediaType = type; button.dataset.mediaUsage = usage;
      button.textContent = '从媒体库选择';
      const clear = document.createElement('button');
      clear.type = 'button'; clear.className = 'admin-link-button'; clear.dataset.mediaClear = ''; clear.textContent = '清空';
      actions.append(button, clear);
      input.insertAdjacentElement('afterend', actions);
      input.addEventListener('change', () => updateFieldPreview(input, type));
      updateFieldPreview(input, type);
    });
  }

  const picker = $('#mediaPicker');
  let activeMediaInput = null;
  let activeMediaType = '';
  let activeMediaUsage = '';

  function mediaCardUsageMatch(card, usageWanted) {
    if (!usageWanted) return true;
    const usage = card.dataset.mediaUsage || '';
    const aliases = (card.dataset.mediaUsages || usage || '').split(',').map(v => v.trim()).filter(Boolean);
    if (usage === usageWanted || aliases.includes(usageWanted)) return true;
    // V7.1.8.133: product edit fields normally ask for usage=products.
    // Old media rows may be saved as images or have empty usage, but they are still valid product images.
    if (activeMediaType === 'image' && usageWanted === 'products' && (usage === '' || usage === 'images' || aliases.includes('images'))) return true;
    return false;
  }

  function filterMediaPicker() {
    if (!picker) return;
    const term = ($('#mediaPickerSearch')?.value || '').trim().toLowerCase();
    const usageSelect = $('#mediaPickerUsage');
    const chosenUsage = usageSelect?.value || '';
    const usageWanted = chosenUsage || activeMediaUsage;
    const cards = $$('.media-picker-card', picker);
    let visible = 0;
    cards.forEach(card => {
      const typeOk = !activeMediaType || card.dataset.mediaType === activeMediaType;
      const usageOk = mediaCardUsageMatch(card, usageWanted);
      const searchOk = !term || (card.dataset.mediaSearch || '').includes(term);
      const show = typeOk && usageOk && searchOk;
      card.hidden = !show;
      if (show) visible++;
    });
    // If strict product usage returns nothing, fall back to all images instead of showing a blank picker.
    if (!visible && activeMediaType === 'image' && !term) {
      cards.forEach(card => {
        const show = card.dataset.mediaType === 'image';
        card.hidden = !show;
        if (show) visible++;
      });
    }
    const empty = $('.media-picker-empty', picker);
    if (empty) empty.hidden = visible !== 0;
  }

  function openMediaPicker(input, type, usage) {
    if (!picker) return;
    activeMediaInput = input;
    activeMediaType = type || '';
    activeMediaUsage = usage || '';
    window.artdonActiveMediaInput = input;
    window.artdonActiveMediaType = activeMediaType;
    window.artdonActiveMediaUsage = activeMediaUsage;
    const usageSelect = $('#mediaPickerUsage');
    if (usageSelect) usageSelect.value = activeMediaUsage;
    const search = $('#mediaPickerSearch');
    if (search) search.value = '';
    picker.classList.add('is-open');
    picker.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admin-modal-open');
    filterMediaPicker();
    search?.focus();
  }

  function closeMediaPicker() {
    if (!picker) return;
    picker.classList.remove('is-open');
    picker.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('admin-modal-open');
  }

  document.addEventListener('click', event => {
    const add = event.target.closest('[data-add-repeater]');
    if (add) {
      const target = $(add.dataset.addRepeater);
      const template = $(add.dataset.template);
      if (target && template) {
        const index = target.querySelectorAll(':scope > .repeat-item').length;
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        target.insertAdjacentHTML('beforeend', html);
        const item = target.lastElementChild;
        if (item) {
          ensureRepeatTools(item);
          enhanceMediaFields(item);
          item.scrollIntoView({behavior:'smooth', block:'center'});
        }
        reindexRepeater(target);
      }
      return;
    }

    const remove = event.target.closest('[data-remove-repeat]');
    if (remove) {
      const item = remove.closest('.repeat-item');
      if (item && confirm('确定删除这一项吗？')) {
        const container = item.parentElement;
        item.remove();
        if (container) reindexRepeater(container);
      }
      return;
    }

    const up = event.target.closest('[data-move-up]');
    if (up) { moveItem(up.closest('.repeat-item'), -1); return; }
    const down = event.target.closest('[data-move-down]');
    if (down) { moveItem(down.closest('.repeat-item'), 1); return; }

    const collapse = event.target.closest('[data-collapse-item]');
    if (collapse) {
      const item = collapse.closest('.repeat-item');
      setCollapsed(item, !item.classList.contains('is-collapsed'));
      return;
    }
    if (event.target.closest('[data-collapse-all]')) { $$('.repeat-item').forEach(item => setCollapsed(item, true)); return; }
    if (event.target.closest('[data-expand-all]')) { $$('.repeat-item').forEach(item => setCollapsed(item, false)); return; }

    const mediaOpen = event.target.closest('[data-media-open]');
    if (mediaOpen) {
      const input = mediaOpen.closest('.field')?.querySelector('.media-path-input');
      if (input) openMediaPicker(input, mediaOpen.dataset.mediaType || 'image', mediaOpen.dataset.mediaUsage || '');
      return;
    }
    const mediaClear = event.target.closest('[data-media-clear]');
    if (mediaClear) {
      const input = mediaClear.closest('.field')?.querySelector('.media-path-input');
      if (input) { input.value = ''; updateFieldPreview(input, /video/.test(input.name) ? 'video' : 'image'); clearMediaAltForInput(input); }
      return;
    }
    const mediaSelect = event.target.closest('[data-media-select]');
    if (mediaSelect && activeMediaInput) {
      activeMediaInput.value = mediaSelect.dataset.mediaPath || '';
      updateFieldPreview(activeMediaInput, activeMediaType || 'image');
      setMediaAltFromPicker(activeMediaInput, mediaSelect);
      activeMediaInput.dispatchEvent(new Event('change', {bubbles:true}));
      closeMediaPicker();
      return;
    }
    if (event.target.closest('[data-media-close]')) { closeMediaPicker(); return; }

    const copy = event.target.closest('[data-copy]');
    if (copy) {
      navigator.clipboard?.writeText(copy.dataset.copy || '');
      const old = copy.textContent; copy.textContent = '已复制'; setTimeout(() => copy.textContent = old, 900);
    }
  });

  $('#mediaPickerSearch')?.addEventListener('input', filterMediaPicker);
  $('#mediaPickerUsage')?.addEventListener('change', filterMediaPicker);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMediaPicker(); });

  document.addEventListener('submit', event => {
    const form = event.target.closest('[data-homepage-form]');
    if (!form) return;
    $$('.repeater', form).forEach(reindexRepeater);
  });

  initRepeaters();
  enhanceMediaFields();
})();

/* V6.5 online image crop + safe image removal */
(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function publicUrl(path) {
    if (!path) return '';
    if (/^(?:https?:)?\/\//i.test(path) || path.startsWith('/')) return path;
    return '../' + path.replace(/^\.\//, '');
  }

  function csrfToken() {
    return $('input[name="csrf"]')?.value || '';
  }

  function showToast(message, isError = false) {
    let toast = $('#adminCropToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'adminCropToast';
      toast.className = 'admin-crop-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.toggle('is-error', isError);
    toast.classList.add('is-show');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('is-show'), 2600);
  }

  const modal = document.createElement('div');
  modal.className = 'image-crop-modal';
  modal.id = 'imageCropModal';
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML = `
    <div class="image-crop-backdrop" data-crop-close></div>
    <section class="image-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="imageCropTitle">
      <header>
        <div><h2 id="imageCropTitle">在线裁切图片</h2><p>拖动图片调整位置，滚轮或滑杆缩放。裁切后的图片会按比例保存，不会拉伸。</p></div>
        <button type="button" class="image-crop-close" data-crop-close aria-label="关闭">×</button>
      </header>
      <div class="image-crop-toolbar">
        <div class="image-crop-ratios" role="group" aria-label="裁切比例">
          <button type="button" data-crop-ratio="1:1">1:1 正方形</button>
          <button type="button" data-crop-ratio="4:3">4:3</button>
          <button type="button" data-crop-ratio="16:9">16:9</button>
          <button type="button" data-crop-ratio="original">原图比例</button>
        </div>
        <div class="image-crop-rotate" role="group" aria-label="旋转图片">
          <button type="button" data-crop-rotate="-90">左转</button>
          <button type="button" data-crop-rotate="90">右转</button>
        </div>
        <label class="image-crop-zoom">缩放 <input type="range" id="imageCropZoom" min="100" max="300" value="100" step="1"><output id="imageCropZoomValue">100%</output></label>
      </div>
      <div class="image-crop-stage"><canvas id="imageCropCanvas" width="760" height="520"></canvas><span>拖动图片定位 · 鼠标滚轮缩放</span></div>
      <footer>
        <div class="image-crop-info" id="imageCropInfo">等待图片</div>
        <div class="admin-actions"><button type="button" class="admin-button-secondary" id="imageCropUseOriginal">使用原图</button><button type="button" class="admin-button-secondary" id="imageCropReset">重新居中</button><button type="button" class="admin-button" id="imageCropSave">保存裁切</button></div>
      </footer>
    </section>`;
  document.body.appendChild(modal);

  const canvas = $('#imageCropCanvas', modal);
  const ctx = canvas.getContext('2d');
  const zoomInput = $('#imageCropZoom', modal);
  const zoomValue = $('#imageCropZoomValue', modal);
  const cropInfo = $('#imageCropInfo', modal);

  const state = {
    image: null,
    ratioKey: '1:1',
    ratio: 1,
    baseScale: 1,
    zoom: 1,
    x: 0,
    y: 0,
    crop: {x: 0, y: 0, w: 0, h: 0},
    dragging: false,
    lastX: 0,
    lastY: 0,
    mode: '',
    fileInput: null,
    pathInput: null,
    sourcePath: '',
    mediaId: 0,
    usage: 'products',
    originalFile: null,
    objectUrl: '',
  };

  function ratioFromKey(key) {
    if (key === '4:3') return 4 / 3;
    if (key === '16:9') return 16 / 9;
    if (key === 'original' && state.image) return state.image.naturalWidth / state.image.naturalHeight;
    return 1;
  }

  function inferRatio(usage, explicit = '') {
    if (explicit) return explicit;
    if (usage === 'banners' || usage === 'videos') return '16:9';
    if (usage === 'projects' || usage === 'articles') return '4:3';
    return '1:1';
  }

  function calculateCropRect() {
    const marginX = 54;
    const marginY = 38;
    const maxW = canvas.width - marginX * 2;
    const maxH = canvas.height - marginY * 2;
    state.ratio = ratioFromKey(state.ratioKey);
    let w = maxW;
    let h = w / state.ratio;
    if (h > maxH) {
      h = maxH;
      w = h * state.ratio;
    }
    state.crop = {x: (canvas.width - w) / 2, y: (canvas.height - h) / 2, w, h};
  }

  function clampImage() {
    if (!state.image) return;
    const scale = state.baseScale * state.zoom;
    const drawW = state.image.naturalWidth * scale;
    const drawH = state.image.naturalHeight * scale;
    const minX = state.crop.x + state.crop.w - drawW;
    const maxX = state.crop.x;
    const minY = state.crop.y + state.crop.h - drawH;
    const maxY = state.crop.y;
    state.x = Math.min(maxX, Math.max(minX, state.x));
    state.y = Math.min(maxY, Math.max(minY, state.y));
  }

  function resetImage() {
    if (!state.image) return;
    calculateCropRect();
    state.zoom = 1;
    zoomInput.value = '100';
    zoomValue.value = '100%';
    state.baseScale = Math.max(state.crop.w / state.image.naturalWidth, state.crop.h / state.image.naturalHeight);
    const drawW = state.image.naturalWidth * state.baseScale;
    const drawH = state.image.naturalHeight * state.baseScale;
    state.x = state.crop.x + (state.crop.w - drawW) / 2;
    state.y = state.crop.y + (state.crop.h - drawH) / 2;
    drawCrop();
  }

  function rotateImage(degrees) {
    if (!state.image) return;
    const normalized = ((degrees % 360) + 360) % 360;
    if (normalized === 0) return;
    const swap = normalized === 90 || normalized === 270;
    const out = document.createElement('canvas');
    out.width = swap ? state.image.naturalHeight : state.image.naturalWidth;
    out.height = swap ? state.image.naturalWidth : state.image.naturalHeight;
    const outCtx = out.getContext('2d');
    outCtx.imageSmoothingEnabled = true;
    outCtx.imageSmoothingQuality = 'high';
    outCtx.translate(out.width / 2, out.height / 2);
    outCtx.rotate(normalized * Math.PI / 180);
    outCtx.drawImage(state.image, -state.image.naturalWidth / 2, -state.image.naturalHeight / 2);
    const rotated = new Image();
    rotated.decoding = 'async';
    rotated.onload = () => {
      state.image = rotated;
      if (state.ratioKey === 'original') state.ratio = ratioFromKey('original');
      resetImage();
      showToast(normalized === 90 ? '已右转 90 度。' : '已左转 90 度。');
    };
    rotated.onerror = () => showToast('旋转图片失败。', true);
    rotated.src = out.toDataURL('image/png');
  }

  function drawCrop() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#202124';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    if (!state.image) return;
    const scale = state.baseScale * state.zoom;
    const drawW = state.image.naturalWidth * scale;
    const drawH = state.image.naturalHeight * scale;
    ctx.drawImage(state.image, state.x, state.y, drawW, drawH);
    const c = state.crop;
    ctx.fillStyle = 'rgba(0,0,0,.58)';
    ctx.fillRect(0, 0, canvas.width, c.y);
    ctx.fillRect(0, c.y + c.h, canvas.width, canvas.height - c.y - c.h);
    ctx.fillRect(0, c.y, c.x, c.h);
    ctx.fillRect(c.x + c.w, c.y, canvas.width - c.x - c.w, c.h);
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 2;
    ctx.strokeRect(c.x + 1, c.y + 1, c.w - 2, c.h - 2);
    ctx.strokeStyle = 'rgba(255,255,255,.36)';
    ctx.lineWidth = 1;
    for (let i = 1; i < 3; i++) {
      ctx.beginPath(); ctx.moveTo(c.x + c.w * i / 3, c.y); ctx.lineTo(c.x + c.w * i / 3, c.y + c.h); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(c.x, c.y + c.h * i / 3); ctx.lineTo(c.x + c.w, c.y + c.h * i / 3); ctx.stroke();
    }
    const sourceW = Math.round(c.w / scale);
    const sourceH = Math.round(c.h / scale);
    cropInfo.textContent = `${state.ratioKey === 'original' ? '原图比例' : state.ratioKey} · 裁切区域约 ${sourceW} × ${sourceH}px`;
  }

  function setZoom(next) {
    if (!state.image) return;
    const oldScale = state.baseScale * state.zoom;
    const centerX = state.crop.x + state.crop.w / 2;
    const centerY = state.crop.y + state.crop.h / 2;
    const imageCenterX = (centerX - state.x) / oldScale;
    const imageCenterY = (centerY - state.y) / oldScale;
    state.zoom = Math.max(1, Math.min(3, next));
    const newScale = state.baseScale * state.zoom;
    state.x = centerX - imageCenterX * newScale;
    state.y = centerY - imageCenterY * newScale;
    clampImage();
    zoomInput.value = String(Math.round(state.zoom * 100));
    zoomValue.value = `${Math.round(state.zoom * 100)}%`;
    drawCrop();
  }

  function loadImage(url, options) {
    const previousObjectUrl = state.objectUrl;
    if (previousObjectUrl && previousObjectUrl !== (options.objectUrl || '')) URL.revokeObjectURL(previousObjectUrl);
    Object.assign(state, options);
    state.objectUrl = options.objectUrl || '';
    state.ratioKey = inferRatio(state.usage, options.ratioKey || '');
    const img = new Image();
    img.decoding = 'async';
    if (/^https?:\/\//i.test(url) && !url.startsWith(location.origin)) img.crossOrigin = 'anonymous';
    img.onload = () => {
      state.image = img;
      resetImage();
      $$('.image-crop-ratios button', modal).forEach(btn => btn.classList.toggle('is-active', btn.dataset.cropRatio === state.ratioKey));
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('admin-modal-open');
    };
    img.onerror = () => showToast('图片读取失败，可能是跨域图片或文件已经不存在。', true);
    img.src = url;
  }

  function closeCrop() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('admin-modal-open');
    state.dragging = false;
  }

  function canvasPoint(event) {
    const rect = canvas.getBoundingClientRect();
    const source = event.touches?.[0] || event;
    return {x: (source.clientX - rect.left) * canvas.width / rect.width, y: (source.clientY - rect.top) * canvas.height / rect.height};
  }

  async function cropBlob() {
    const scale = state.baseScale * state.zoom;
    const c = state.crop;
    const sx = Math.max(0, (c.x - state.x) / scale);
    const sy = Math.max(0, (c.y - state.y) / scale);
    const sw = Math.min(state.image.naturalWidth - sx, c.w / scale);
    const sh = Math.min(state.image.naturalHeight - sy, c.h / scale);
    const maxWidth = state.ratioKey === '16:9' ? 1920 : 1600;
    const outW = Math.max(320, Math.min(maxWidth, Math.round(sw)));
    const outH = Math.max(240, Math.round(outW / state.ratio));
    const out = document.createElement('canvas');
    out.width = outW; out.height = outH;
    const outCtx = out.getContext('2d');
    outCtx.imageSmoothingEnabled = true;
    outCtx.imageSmoothingQuality = 'high';
    outCtx.drawImage(state.image, sx, sy, sw, sh, 0, 0, outW, outH);
    const sourceType = state.originalFile?.type || (/\.png(?:\?|$)/i.test(state.sourcePath) ? 'image/png' : /\.webp(?:\?|$)/i.test(state.sourcePath) ? 'image/webp' : 'image/jpeg');
    const mime = ['image/png','image/webp'].includes(sourceType) ? sourceType : 'image/jpeg';
    return await new Promise((resolve, reject) => out.toBlob(blob => blob ? resolve({blob, mime, width: outW, height: outH}) : reject(new Error('生成裁切图片失败。')), mime, .92));
  }

  function updatePathField(input, path) {
    if (!input) return;
    input.value = path;
    input.dispatchEvent(new Event('change', {bubbles: true}));
  }

  async function saveCrop() {
    const save = $('#imageCropSave', modal);
    save.disabled = true; save.textContent = '保存中…';
    try {
      const result = await cropBlob();
      const ext = result.mime === 'image/png' ? 'png' : result.mime === 'image/webp' ? 'webp' : 'jpg';
      if (state.mode === 'file') {
        const oldName = state.originalFile?.name || 'image.jpg';
        const base = oldName.replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]+/g, '-') || 'image';
        const file = new File([result.blob], `${base}-cropped.${ext}`, {type: result.mime, lastModified: Date.now()});
        const transfer = new DataTransfer(); transfer.items.add(file);
        state.fileInput.files = transfer.files;
        state.fileInput.dataset.cropped = '1';
        const status = state.fileInput.parentElement?.querySelector('.image-upload-status');
        if (status) status.textContent = `已裁切：${result.width} × ${result.height}px`;
        showToast('裁切完成，保存表单时会上传这张图片。');
        closeCrop();
        return;
      }

      const form = new FormData();
      form.append('csrf', csrfToken());
      form.append('source_path', state.sourcePath || '');
      form.append('media_id', String(state.mediaId || 0));
      form.append('usage', state.usage || 'products');
      form.append('cropped_file', new File([result.blob], `cropped-${Date.now()}.${ext}`, {type: result.mime}));
      const response = await fetch('media_crop.php', {method: 'POST', body: form, credentials: 'same-origin'});
      const data = await response.json().catch(() => ({ok:false,message:'服务器返回内容无法解析。'}));
      if (!response.ok || !data.ok) throw new Error(data.message || '保存裁切图片失败。');
      const target = state.pathInput || window.artdonActiveMediaInput || null;
      updatePathField(target, data.path);
      showToast('裁切版已保存，并已写入当前图片字段。');
      closeCrop();
      if (!target && location.pathname.endsWith('/media.php')) setTimeout(() => location.reload(), 500);
      if ($('#mediaPicker')?.classList.contains('is-open') && target) $('[data-media-close]')?.click();
    } catch (error) {
      showToast(error.message || '裁切失败。', true);
    } finally {
      save.disabled = false; save.textContent = '保存裁切';
    }
  }

  function openFileCrop(input, file) {
    const url = URL.createObjectURL(file);
    const usage = input.dataset.mediaUsage || input.closest('form')?.querySelector('[name="usage"]')?.value || (input.name.includes('cover') ? 'products' : 'images');
    loadImage(url, {mode:'file', fileInput:input, originalFile:file, sourcePath:'', mediaId:0, usage, pathInput:null, ratioKey:input.dataset.cropRatio || '', objectUrl:url});
  }

  function openExistingCrop(button) {
    const field = button.closest('.field');
    const input = field?.querySelector('.media-path-input') || window.artdonActiveMediaInput || null;
    const sourcePath = button.dataset.mediaPath || input?.value?.trim() || '';
    if (!sourcePath) { showToast('请先选择或填写一张图片。', true); return; }
    const usage = button.dataset.mediaUsage || input?.dataset.mediaUsage || window.artdonActiveMediaUsage || 'products';
    loadImage(publicUrl(sourcePath), {mode:'path', pathInput:input, originalFile:null, sourcePath, mediaId:Number(button.dataset.mediaId || 0), usage, ratioKey:button.dataset.cropRatio || inferRatio(usage)});
  }

  function enhanceImageInputs(root = document) {
    $$('input[type="file"]', root).forEach(input => {
      if (input.dataset.cropEnhanced === '1') return;
      const accept = (input.getAttribute('accept') || '').toLowerCase();
      if (!accept.includes('image') && !input.name.includes('cover') && input.id !== 'media-file') return;
      input.dataset.cropEnhanced = '1';
      const tools = document.createElement('div');
      tools.className = 'image-upload-tools';
      tools.innerHTML = '<button type="button" class="admin-button-secondary" data-crop-file>裁切所选图片</button><button type="button" class="admin-link-button" data-clear-file>清除待上传</button><span class="image-upload-status">尚未选择图片</span>';
      input.insertAdjacentElement('afterend', tools);
      input.addEventListener('change', () => {
        const file = input.files?.[0];
        const status = $('.image-upload-status', tools);
        if (!file) { if (status) status.textContent = '尚未选择图片'; return; }
        if (!file.type.startsWith('image/')) { if (status) status.textContent = file.name; return; }
        if (status) status.textContent = `${file.name} · 等待裁切`;
        if (input.dataset.autoCrop === '1' || input.id === 'media-file') openFileCrop(input, file);
      });
    });
  }

  function enhanceImagePathActions(root = document) {
    $$('.media-path-input', root).forEach(input => {
      const type = input.dataset.mediaField || (/video/.test(input.name) ? 'video' : 'image');
      if (type !== 'image') return;
      const field = input.closest('.field');
      const actions = $('.media-field-actions', field);
      if (!actions || actions.dataset.cropEnhanced === '1') return;
      actions.dataset.cropEnhanced = '1';
      const crop = document.createElement('button');
      crop.type = 'button'; crop.className = 'admin-button-secondary'; crop.dataset.mediaCropExisting = ''; crop.textContent = '在线裁切';
      const clear = $('[data-media-clear]', actions);
      if (clear) { clear.textContent = '删除当前图片'; clear.classList.add('media-clear-danger'); actions.insertBefore(crop, clear); }
      else actions.appendChild(crop);
    });
  }

  document.addEventListener('change', event => {
    const kind = event.target.closest('#media-kind');
    if (kind) {
      const file = $('#media-file');
      if (file) file.dataset.autoCrop = kind.value === 'image' ? '1' : '0';
    }
  });

  document.addEventListener('click', event => {
    const ratio = event.target.closest('[data-crop-ratio]');
    if (ratio) {
      state.ratioKey = ratio.dataset.cropRatio;
      $$('.image-crop-ratios button', modal).forEach(btn => btn.classList.toggle('is-active', btn === ratio));
      resetImage();
      return;
    }
    const rotate = event.target.closest('[data-crop-rotate]');
    if (rotate) {
      rotateImage(Number(rotate.dataset.cropRotate || 0));
      return;
    }
    if (event.target.closest('[data-crop-close]')) { closeCrop(); return; }
    const cropFile = event.target.closest('[data-crop-file]');
    if (cropFile) {
      const input = cropFile.closest('.field')?.querySelector('input[type="file"]') || cropFile.parentElement?.previousElementSibling;
      const file = input?.files?.[0];
      if (!file || !file.type.startsWith('image/')) showToast('请先选择一张 JPG、PNG 或 WebP 图片。', true); else openFileCrop(input, file);
      return;
    }
    const clearFile = event.target.closest('[data-clear-file]');
    if (clearFile) {
      const input = clearFile.closest('.field')?.querySelector('input[type="file"]') || clearFile.parentElement?.previousElementSibling;
      if (input) input.value = '';
      const status = clearFile.parentElement?.querySelector('.image-upload-status');
      if (status) status.textContent = '尚未选择图片';
      return;
    }
    const cropExisting = event.target.closest('[data-media-crop-existing]');
    if (cropExisting) { openExistingCrop(cropExisting); return; }
    const clearImage = event.target.closest('[data-media-clear]');
    if (clearImage) {
      const input = clearImage.closest('.field')?.querySelector('.media-path-input');
      if (input && input.dataset.mediaField !== 'video') {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (confirm('确定从当前内容中移除这张图片吗？媒体库原文件不会被删除。')) {
          input.value = '';
          input.dispatchEvent(new Event('change', {bubbles:true}));
        }
      }
    }
  }, true);

  zoomInput.addEventListener('input', () => setZoom(Number(zoomInput.value) / 100));
  canvas.addEventListener('wheel', event => { event.preventDefault(); setZoom(state.zoom + (event.deltaY < 0 ? .08 : -.08)); }, {passive:false});
  canvas.addEventListener('pointerdown', event => { if (!state.image) return; state.dragging = true; const p = canvasPoint(event); state.lastX = p.x; state.lastY = p.y; canvas.setPointerCapture?.(event.pointerId); });
  canvas.addEventListener('pointermove', event => { if (!state.dragging) return; const p = canvasPoint(event); state.x += p.x - state.lastX; state.y += p.y - state.lastY; state.lastX = p.x; state.lastY = p.y; clampImage(); drawCrop(); });
  canvas.addEventListener('pointerup', () => state.dragging = false);
  canvas.addEventListener('pointercancel', () => state.dragging = false);
  $('#imageCropReset', modal).addEventListener('click', resetImage);
  $('#imageCropSave', modal).addEventListener('click', saveCrop);
  $('#imageCropUseOriginal', modal).addEventListener('click', () => {
    if (state.mode === 'file') {
      const status = state.fileInput?.parentElement?.querySelector('.image-upload-status');
      if (status && state.originalFile) status.textContent = `${state.originalFile.name} · 使用原图`;
      closeCrop();
    } else closeCrop();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && modal.classList.contains('is-open')) closeCrop(); });


  function syncMediaUploadKind() {
    const usage = $('#media-usage');
    const kind = $('#media-kind');
    const file = $('#media-file');
    if (!kind || !file) return;
    const expected = usage?.options?.[usage.selectedIndex]?.dataset?.kind || '';
    if (expected && expected !== 'any') kind.value = expected;
    file.accept = kind.value === 'image'
      ? 'image/jpeg,image/png,image/webp'
      : (kind.value === 'video'
        ? 'video/mp4,video/webm,video/quicktime'
        : '.pdf,.zip,.xlsx,.docx,.ies,.ldt,.dwg,.dxf,.rfa,.rvt,.step,.stp,.igs,.iges,.3ds,.obj,.skp,.txt,.csv');
    file.dataset.autoCrop = kind.value === 'image' ? '1' : '0';
  }
  $('#media-usage')?.addEventListener('change', syncMediaUploadKind);
  $('#media-kind')?.addEventListener('change', syncMediaUploadKind);

  const observer = new MutationObserver(mutations => mutations.forEach(m => m.addedNodes.forEach(node => {
    if (!(node instanceof Element)) return;
    enhanceImageInputs(node);
    setTimeout(() => enhanceImagePathActions(node), 0);
  })));
  observer.observe(document.body, {childList:true, subtree:true});
  syncMediaUploadKind();
  enhanceImageInputs();
  setTimeout(() => enhanceImagePathActions(), 0);
})();


/* V6.6 compact homepage editor behavior */
(function(){
  const workspace=document.querySelector('[data-homepage-workspace]');
  const form=document.querySelector('[data-homepage-form]');
  if(!workspace||!form)return;
  const state=document.querySelector('[data-save-state]');
  let dirty=false;
  const markDirty=()=>{if(dirty)return;dirty=true;if(state){state.textContent='有未保存修改';state.classList.add('is-dirty')}};
  form.addEventListener('input',markDirty);
  form.addEventListener('change',markDirty);
  form.addEventListener('submit',()=>{if(state){state.textContent='正在保存…';state.classList.remove('is-dirty')}});
  window.addEventListener('beforeunload',e=>{if(!dirty)return;e.preventDefault();e.returnValue=''});
  // 默认折叠重复项目，仅展开第一项，布局页不处理。
  const section=form.querySelector('input[name="section"]')?.value||'';
  if(section!=='layout'){
    form.querySelectorAll('.repeater').forEach(rep=>{
      const items=[...rep.children].filter(el=>el.classList.contains('repeat-item'));
      items.forEach((item,i)=>{if(i>0){item.classList.add('is-collapsed');const b=item.querySelector('[data-collapse-item]');if(b)b.textContent='展开'}})
    })
  }
})();
