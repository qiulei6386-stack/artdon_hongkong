/* Artdon Lighting 官网 V7.0.9.1 - 产品 / 系列卡片修正版 */
(function(){
  'use strict';
  if (window.__ARTDON_CARD_V7091__) return;
  window.__ARTDON_CARD_V7091__ = true;

  var path = String(location.pathname || '').toLowerCase();
  if (path.indexOf('/admin/') >= 0) return;

  var DEFAULTS = {
    hide_view_details:'1',
    hide_info_icon:'1',
    title_font_size:'18',
    subtitle_font_size:'13',
    description_font_size:'12',
    spec_label_font_size:'12',
    spec_value_font_size:'13',
    tag_font_size:'11',
    meta_font_size:'11',
    family_heading_font_size:'30',
    badge_font_size:'11',
    badge_top:'14',
    badge_left:'14',
    badge_radius:'999',
    card_width:'',
    card_min_height:'',
    image_subject_scale:''
  };
  var state = {settings:Object.assign({}, DEFAULTS), flags:[], timer:0};

  function text(v){ return String(v == null ? '' : v).replace(/\s+/g,' ').trim(); }
  function lower(v){ return text(v).toLowerCase(); }
  function num(v, fallback, min, max){
    var n = Number(v);
    if (!Number.isFinite(n)) n = fallback;
    if (Number.isFinite(min)) n = Math.max(min,n);
    if (Number.isFinite(max)) n = Math.min(max,n);
    return n;
  }
  function setVar(name,value){
    if (!document.body) return;
    document.body.style.setProperty(name,value);
  }
  function flagOn(v){ return String(v) !== '0' && String(v).toLowerCase() !== 'false'; }

  function applySettings(){
    var b = document.body;
    if(!b) return;
    var s = state.settings;
    b.classList.add('artdon-v7091-card-ready');
    b.classList.toggle('artdon-v7091-hide-details', flagOn(s.hide_view_details));
    b.classList.toggle('artdon-v7091-hide-info', flagOn(s.hide_info_icon));

    setVar('--artdon-card-title-size', num(s.title_font_size,18,8,80)+'px');
    setVar('--artdon-card-subtitle-size', num(s.subtitle_font_size,13,8,60)+'px');
    setVar('--artdon-card-description-size', num(s.description_font_size,12,8,60)+'px');
    setVar('--artdon-card-spec-label-size', num(s.spec_label_font_size,12,8,60)+'px');
    setVar('--artdon-card-spec-value-size', num(s.spec_value_font_size,13,8,60)+'px');
    setVar('--artdon-card-tag-size', num(s.tag_font_size,11,8,40)+'px');
    setVar('--artdon-card-meta-size', num(s.meta_font_size,11,8,40)+'px');
    setVar('--artdon-family-heading-size', num(s.family_heading_font_size,30,12,72)+'px');
    setVar('--artdon-card-badge-size', num(s.badge_font_size,11,8,36)+'px');
    setVar('--artdon-card-badge-top', num(s.badge_top,14,0,300)+'px');
    setVar('--artdon-card-badge-left', num(s.badge_left,14,0,300)+'px');
    setVar('--artdon-card-badge-radius', num(s.badge_radius,999,0,999)+'px');

    var width = text(s.card_width);
    b.classList.toggle('artdon-v7091-card-width-on', width !== '');
    if(width !== '') setVar('--artdon-card-custom-width', num(width,270,120,1000)+'px');

    var height = text(s.card_min_height);
    b.classList.toggle('artdon-v7091-card-height-on', height !== '');
    if(height !== '') setVar('--artdon-card-custom-height', num(height,0,80,1600)+'px');

    var scale = text(s.image_subject_scale);
    b.classList.toggle('artdon-v7091-image-scale-on', scale !== '');
    if(scale !== '') setVar('--artdon-card-image-scale', num(scale,80,20,150)+'%');
  }

  function isViewDetails(el){
    if(!el || el.nodeType !== 1) return false;
    var value = el.tagName === 'INPUT' ? el.value : el.textContent;
    var t = lower(value).replace(/[→›»]+$/,'').trim();
    return /^(view\s+details?|view\s+product|product\s+details?|details?)$/.test(t);
  }

  function hideViewDetails(root){
    if(!flagOn(state.settings.hide_view_details)) return;
    var scope = root && root.querySelectorAll ? root : document;
    var known = scope.querySelectorAll('.view-details,.view-detail,.details-btn,.btn-details,.product-detail-btn,.series-detail-btn,.catalog-card-cta,.catalog-card-action,.catalog-card-button,a,button,[role="button"],input[type="button"],input[type="submit"]');
    known.forEach(function(el){
      if(!isViewDetails(el) && !el.matches('.view-details,.view-detail,.details-btn,.btn-details,.product-detail-btn,.series-detail-btn,.catalog-card-cta,.catalog-card-action,.catalog-card-button')) return;
      el.setAttribute('data-artdon-v7091-hidden','view-details');
      var p = el.parentElement;
      if(p && p.children.length === 1 && /(action|actions|button|buttons|footer|cta|detail)/i.test(String(p.className||''))){
        p.setAttribute('data-artdon-v7091-hidden','view-details-wrap');
      }
    });

    var cards = scope.querySelectorAll('.catalog-card,.product-card,.series-card,.product-item,.series-item,.family-card,.collection-card,.variant-card,.series-variant-card,.family-variant-card');
    cards.forEach(function(card){
      card.querySelectorAll('a,button,span,div').forEach(function(el){
        ['::after'].forEach(function(pseudo){
          try{
            var c = getComputedStyle(el,pseudo).content || '';
            c = lower(c.replace(/^['"]|['"]$/g,''));
            if(c.indexOf('view details') >= 0 || c === 'details'){
              el.classList.add('artdon-v7091-pseudo-details-after');
            }
          }catch(e){}
        });
      });
    });
  }

  function infoGlyph(el){
    if(!el || el.nodeType !== 1) return false;
    var cls = String(el.className || '');
    if(/catalog-card-info|catalog-info|info-icon|info-dot|card-info-icon|product-info-icon|series-info-icon|circle-info|hotspot-info/i.test(cls)) return true;
    var aria = lower(el.getAttribute('aria-label') || el.getAttribute('title') || '');
    if(aria === 'info' || aria === 'information' || aria === 'details') return true;
    var t = text(el.textContent);
    if(!/^(i|!|ℹ|ⓘ|❕)$/.test(t)) return false;
    var r = el.getBoundingClientRect();
    if(r.width <= 0 || r.height <= 0 || r.width > 116 || r.height > 116) return false;
    var card = el.closest('.catalog-card,.product-card,.series-card,.product-item,.series-item,.family-card,.collection-card,.variant-card,figure,.product-image,.series-image,.catalog-card-image');
    if(!card) return false;
    try{
      var cs = getComputedStyle(el);
      var br = parseFloat(cs.borderRadius) || 0;
      return br >= Math.min(r.width,r.height) * .28 || Math.abs(r.width-r.height) < 18;
    }catch(e){ return true; }
  }

  function hideInfo(root){
    if(!flagOn(state.settings.hide_info_icon)) return;
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('.catalog-card-info,.catalog-info,.info-icon,.info-dot,.card-info-icon,.product-info-icon,.series-info-icon,.circle-info,.hotspot-info,[aria-label],[title],span,button,div').forEach(function(el){
      if(infoGlyph(el)) el.setAttribute('data-artdon-v7091-hidden','info-icon');
    });
  }

  var CARD_SELECTOR = '.catalog-card,.product-card,.series-card,.product-item,.series-item,.family-card,.collection-card,.variant-card,.series-variant-card,.family-variant-card,.variant-siblings>div>a,.family-variants a[href*="product.php"],.series-products a[href*="product.php"],[data-product-id],[data-series-id],[data-card-type]';

  function uniqueCards(root){
    var scope = root && root.querySelectorAll ? root : document;
    var out = [];
    if(scope.matches && scope.matches(CARD_SELECTOR)) out.push(scope);
    scope.querySelectorAll(CARD_SELECTOR).forEach(function(el){ if(out.indexOf(el) < 0) out.push(el); });
    return out;
  }

  function cardAnchor(card){ if(card.matches && card.matches('a[href]')) return card; return card.querySelector('a[href*="series.php"],a[href*="product.php"],a[href]'); }
  function cardType(card){
    var explicit = lower(card.getAttribute('data-card-type'));
    if(explicit.indexOf('series') >= 0 || explicit.indexOf('family') >= 0) return 'series';
    if(explicit.indexOf('product') >= 0 || explicit.indexOf('variant') >= 0) return 'product';
    var a = cardAnchor(card), href = a ? lower(a.getAttribute('href')) : '';
    if(href.indexOf('series.php') >= 0) return 'series';
    if(href.indexOf('product.php') >= 0) return 'product';
    var cls = lower(card.className);
    if(cls.indexOf('series') >= 0 || cls.indexOf('family') >= 0 || cls.indexOf('collection') >= 0) return 'series';
    return 'product';
  }

  function urlKeys(href){
    var keys=[];
    if(!href) return keys;
    try{
      var u = new URL(href,location.href);
      ['id','slug','model','code','product','series'].forEach(function(k){ if(u.searchParams.get(k)) keys.push(lower(u.searchParams.get(k))); });
      var last = u.pathname.split('/').filter(Boolean).pop() || '';
      if(last && last.indexOf('.') < 0) keys.push(lower(last));
    }catch(e){}
    return keys;
  }

  function cardKeys(card){
    var keys=[];
    ['data-product-id','data-series-id','data-id','data-slug','data-model','data-code'].forEach(function(k){ var v=card.getAttribute(k); if(v) keys.push(lower(v)); });
    if(card.id) keys.push(lower(card.id));
    var a=cardAnchor(card); if(a) keys=keys.concat(urlKeys(a.getAttribute('href')));
    return Array.from(new Set(keys.filter(Boolean)));
  }

  function cardName(card){
    var el = card.querySelector('.catalog-card-body h3,.product-title,.series-title,.card-title,h3,h2,h4,[data-name]');
    return lower(el ? (el.getAttribute('data-name') || el.textContent) : card.getAttribute('aria-label'));
  }

  function matchFlag(card){
    var type=cardType(card), keys=cardKeys(card), name=cardName(card);
    var best=null, score=-1;
    state.flags.forEach(function(f){
      if(!f || String(f.enabled) === '0' || f.badge_type === 'none') return;
      var ft=lower(f.item_type || 'product');
      if(ft && ft !== type && ft !== 'all') return;
      var id=lower(f.item_id), fn=lower(f.item_name), s=0;
      if(id && id.indexOf('name:') !== 0 && keys.indexOf(id) >= 0) s=100;
      else if(fn && name && (name === fn || name.indexOf(fn) >= 0 || fn.indexOf(name) >= 0)) s=70;
      else if(id && id.indexOf('name:') === 0 && fn && name.indexOf(fn) >= 0) s=60;
      if(s>score){best=f;score=s;}
    });
    return best;
  }

  function badgeHost(card){
    return card.querySelector('.catalog-card-image,.product-card-image,.series-card-image,.product-image,.series-image,figure') || card;
  }
  function ensureBadge(card){
    var old=card.querySelector(':scope > .artdon-v7091-badge,.artdon-v7091-badge');
    var f=matchFlag(card);
    if(!f){ if(old) old.remove(); return; }
    var host=badgeHost(card);
    if(!host) return;
    host.classList.add('artdon-v7091-badge-host');
    var b=host.querySelector(':scope > .artdon-v7091-badge');
    if(!b){ b=document.createElement('span'); b.className='artdon-v7091-badge'; host.appendChild(b); }
    var type=lower(f.badge_type || 'new');
    b.classList.toggle('artdon-v7091-star',type==='star');
    b.textContent=text(f.badge_text) || (type==='star'?'★':'NEW');
    b.setAttribute('aria-label',b.textContent);
  }

  function importantSize(root, selector, size){
    root.querySelectorAll(selector).forEach(function(el){ el.style.setProperty('font-size',size,'important'); });
  }
  function patchTypography(card){
    var s=state.settings;
    importantSize(card,'.catalog-card-body h3,.product-title,.series-title,.card-title,h3',num(s.title_font_size,18,8,80)+'px');
    importantSize(card,'.catalog-card-subtitle,.product-subtitle,.series-subtitle,.card-subtitle',num(s.subtitle_font_size,13,8,60)+'px');
    importantSize(card,'.catalog-card-description,.description,.desc,.card-description',num(s.description_font_size,12,8,60)+'px');
    importantSize(card,'.catalog-card-metrics dt,.spec-label,.parameter-label',num(s.spec_label_font_size,12,8,60)+'px');
    importantSize(card,'.catalog-card-metrics dd,.spec-value,.parameter-value',num(s.spec_value_font_size,13,8,60)+'px');
    importantSize(card,'.catalog-card-tags span,.catalog-card-tags a,.tags span,.tag-list span,.card-tags span,.product-tags span,.series-tags span',num(s.tag_font_size,11,8,40)+'px');
    importantSize(card,'small,.meta,.note',num(s.meta_font_size,11,8,40)+'px');
  }

  function patch(root){
    applySettings();
    hideViewDetails(root);
    hideInfo(root);
    uniqueCards(root).forEach(function(card){ patchTypography(card); ensureBadge(card); });
    document.querySelectorAll('.catalog-family-divider h3').forEach(function(el){
      el.style.setProperty('font-size',num(state.settings.family_heading_font_size,30,12,72)+'px','important');
    });
  }

  function schedule(root){
    clearTimeout(state.timer);
    state.timer=setTimeout(function(){ patch(root || document); },45);
  }

  function loadSettings(){
    return fetch('/api/artdon_card_settings_v709_api.php?v=7091&t='+Date.now(),{credentials:'same-origin',cache:'no-store'})
      .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
      .then(function(j){
        if(j && j.settings) state.settings=Object.assign({},DEFAULTS,j.settings);
        if(j && Array.isArray(j.flags)) state.flags=j.flags;
      })
      .catch(function(){ state.settings=Object.assign({},DEFAULTS); state.flags=[]; });
  }

  function boot(){
    patch(document); // API 即使失败，也先按默认值立即隐藏按钮和 i
    loadSettings().then(function(){ patch(document); });
    if(window.MutationObserver){
      var mo=new MutationObserver(function(list){
        var root=document;
        for(var i=0;i<list.length;i++){
          if(list[i].addedNodes && list[i].addedNodes.length){
            for(var j=0;j<list[i].addedNodes.length;j++){
              if(list[i].addedNodes[j].nodeType===1){ root=list[i].addedNodes[j]; break; }
            }
          }
        }
        schedule(document);
      });
      mo.observe(document.documentElement,{childList:true,subtree:true});
    }
    window.addEventListener('pageshow',function(){schedule(document);});
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
})();
