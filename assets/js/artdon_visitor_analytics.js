(function(){
  'use strict';
  var endpoint = 'analytics_track.php';
  var activeSeconds = 0;
  var lastActiveTick = Date.now();
  var maxScroll = 0;
  var currentSection = '';
  var sectionStartedAt = Date.now();
  var sectionSeconds = {};
  var sentSections = {};

  function rand(prefix){
    try {
      var a = new Uint8Array(12);
      crypto.getRandomValues(a);
      return prefix + Array.prototype.map.call(a,function(x){return ('0'+x.toString(16)).slice(-2);}).join('');
    } catch(e) { return prefix + Math.random().toString(16).slice(2) + Date.now().toString(16); }
  }
  function getCookie(name){
    var m = document.cookie.match(new RegExp('(?:^|; )'+name.replace(/[.$?*|{}()\[\]\\\/\+^]/g,'\\$&')+'=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function setCookie(name,value,days){
    var d = new Date(Date.now()+days*864e5).toUTCString();
    document.cookie = name+'='+encodeURIComponent(value)+'; expires='+d+'; path=/; SameSite=Lax';
  }
  function lsGet(key){ try { return localStorage.getItem(key) || ''; } catch(e) { return ''; } }
  function lsSet(key,val){ try { localStorage.setItem(key, val); } catch(e) {} }
  function ssGet(key){ try { return sessionStorage.getItem(key) || ''; } catch(e) { return ''; } }
  function ssSet(key,val){ try { sessionStorage.setItem(key, val); } catch(e) {} }
  function simpleHash(str){
    var h1 = 0x811c9dc5, i = 0;
    str = String(str || '');
    for (; i < str.length; i++) {
      h1 ^= str.charCodeAt(i);
      h1 += (h1 << 1) + (h1 << 4) + (h1 << 7) + (h1 << 8) + (h1 << 24);
    }
    return ('00000000' + (h1 >>> 0).toString(16)).slice(-8);
  }
  function text(el){ return el ? (el.textContent || '').replace(/\s+/g,' ').trim() : ''; }
  function qs(sel,root){ return (root||document).querySelector(sel); }
  function path(){ return location.pathname + location.search; }
  function pageType(){
    var p = location.pathname.toLowerCase();
    if (p.indexOf('product.php') !== -1 || /\/home\/products\/.+\/.+\/.+/i.test(p)) return 'product';
    if (p.indexOf('products.php') !== -1 || /\/home\/products\/?$/i.test(p)) return 'products';
    if (p.indexOf('series.php') !== -1 || /\/home\/products\/.+\/.+\/?$/i.test(p)) return 'series';
    if (p.indexOf('project.php') !== -1 || p.indexOf('projects') !== -1) return 'project';
    if (p.indexOf('solution') !== -1) return 'solutions';
    if (p.indexOf('resource') !== -1 || p.indexOf('downloads.php') !== -1) return 'resources';
    if (p.indexOf('contact') !== -1) return 'contact';
    if (p === '/' || p.indexOf('index.php') !== -1) return 'home';
    return 'page';
  }
  function productName(){
    return text(qs('.product-variant-page h1')) || text(qs('main h1')) || document.title.replace(/\s*\|\s*Artdon.*$/i,'');
  }
  function categoryName(){
    var bc = qs('.catalog-breadcrumb.family-breadcrumb');
    if (!bc) return '';
    var links = bc.querySelectorAll('a');
    return links.length >= 3 ? text(links[2]) : '';
  }
  function seriesName(){
    var bc = qs('.catalog-breadcrumb.family-breadcrumb');
    if (!bc) return '';
    var links = bc.querySelectorAll('a');
    return links.length >= 4 ? text(links[3]) : '';
  }
  function params(){
    var sp = new URLSearchParams(location.search);
    return {utm_source: sp.get('utm_source') || '', utm_medium: sp.get('utm_medium') || '', utm_campaign: sp.get('utm_campaign') || ''};
  }
  function cloneSections(){
    var out = {};
    Object.keys(sectionSeconds).forEach(function(k){ out[k] = Math.round(sectionSeconds[k] || 0); });
    return out;
  }
  function basePayload(){
    var utm = params();
    var pt = pageType();
    return {
      visitor_id: visitorId,
      session_id: sessionId,
      pageview_id: pageviewId,
      url: location.href,
      path: path(),
      title: document.title || productName(),
      page_type: pt,
      product_name: pt === 'product' ? productName() : '',
      product_slug: (new URLSearchParams(location.search)).get('slug') || '',
      series_name: seriesName(),
      category_name: categoryName(),
      referrer: document.referrer || '',
      utm_source: utm.utm_source,
      utm_medium: utm.utm_medium,
      utm_campaign: utm.utm_campaign,
      screen: (screen && screen.width ? screen.width+'x'+screen.height : ''),
      timezone: (Intl.DateTimeFormat().resolvedOptions().timeZone || ''),
      browser_language: (navigator.language || (navigator.languages && navigator.languages[0]) || ''),
      visitor_fingerprint_hash: visitorFingerprintHash,
      duration_seconds: Math.round(activeSeconds),
      scroll_depth: maxScroll,
      section: currentSection,
      section_durations: cloneSections()
    };
  }
  function send(data, beacon){
    data = data || {};
    var body = JSON.stringify(data);
    if (beacon && navigator.sendBeacon) {
      try { navigator.sendBeacon(endpoint, new Blob([body], {type:'application/json'})); return; } catch(e) {}
    }
    fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json'}, body:body, credentials:'same-origin', keepalive:!!beacon}).catch(function(){});
  }
  function addSectionTime(now){
    if (!currentSection || document.hidden) { sectionStartedAt = now; return; }
    var add = Math.max(0, Math.min(30, (now - sectionStartedAt) / 1000));
    sectionSeconds[currentSection] = (sectionSeconds[currentSection] || 0) + add;
    sectionStartedAt = now;
  }
  function updateActive(){
    var now = Date.now();
    if (!document.hidden) {
      activeSeconds += Math.max(0, Math.min(30, (now - lastActiveTick) / 1000));
    }
    addSectionTime(now);
    lastActiveTick = now;
  }
  function updateScroll(){
    var doc = document.documentElement;
    var body = document.body;
    var top = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
    var h = Math.max(body.scrollHeight, doc.scrollHeight) - window.innerHeight;
    var pct = h > 0 ? Math.round((top / h) * 100) : 0;
    if (pct > maxScroll) maxScroll = Math.min(100, pct);
  }
  function sectionLabel(el){
    var h = text(qs('h1,h2,h3,.section-title,.kicker', el));
    if (h) return h;
    var id = el.getAttribute('id') || '';
    if (id === 'technical-files') return 'Downloads / Planning files';
    if (el.classList && el.classList.contains('variant-accessories')) return 'Compatible accessories';
    if (el.classList && el.classList.contains('variant-overview-photometric')) return 'Technical product information';
    if (el.classList && el.classList.contains('variant-hero')) return 'Product hero';
    return el.getAttribute('aria-label') || id || String(el.className || '').split(' ')[0] || 'Section';
  }
  function setCurrentSection(label){
    var now = Date.now();
    if (label === currentSection) return;
    addSectionTime(now);
    currentSection = label || '';
    sectionStartedAt = now;
  }
  function observeSections(){
    if (!('IntersectionObserver' in window)) return;
    var nodes = Array.prototype.slice.call(document.querySelectorAll('main section, main [id], .variant-section, .variant-hero, .site-footer'));
    nodes = nodes.filter(function(el){
      var r = el.getBoundingClientRect();
      return r.height > 80 && r.width > 120;
    }).slice(0, 60);
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting && entry.intersectionRatio >= 0.35) {
          var label = sectionLabel(entry.target);
          setCurrentSection(label);
          if (!sentSections[label]) {
            sentSections[label] = 1;
            var p = basePayload();
            p.action = 'section';
            p.event_name = 'view_section';
            p.section = label;
            send(p, false);
          }
        }
      });
    }, {threshold:[0.35,0.65]});
    nodes.forEach(function(n){ io.observe(n); });
  }
  function bindClicks(){
    document.addEventListener('click', function(e){
      var a = e.target.closest && e.target.closest('a,button');
      if (!a) return;
      var label = text(a).slice(0, 160);
      var href = a.href || a.getAttribute('data-share-url') || '';
      var kind = 'click';
      if (/quote/i.test(label) || /quote/i.test(href)) kind = 'quote';
      else if (/download|pdf|technical files/i.test(label) || /download|pdf/i.test(href)) kind = 'download';
      else if (/share/i.test(label)) kind = 'share';
      var p = basePayload();
      p.action = kind;
      p.event_name = label || kind;
      p.target_text = label;
      p.target_url = href;
      send(p, false);
    }, true);
  }
  function ensureInquiryFields(){
    Array.prototype.forEach.call(document.querySelectorAll('form[action*="submit_inquiry.php"]'), function(form){
      [['visitor_id', visitorId], ['visitor_session_id', sessionId], ['visitor_pageview_id', pageviewId], ['page_url', location.href]].forEach(function(pair){
        var input = form.querySelector('input[name="'+pair[0]+'"]');
        if (!input) {
          input = document.createElement('input');
          input.type = 'hidden';
          input.name = pair[0];
          form.appendChild(input);
        }
        input.value = pair[1];
      });
    });
  }

  var visitorId = getCookie('artdon_vid') || lsGet('artdon_vid') || rand('v_');
  setCookie('artdon_vid', visitorId, 365);
  lsSet('artdon_vid', visitorId);
  var sessionTimeoutMs = 30 * 60 * 1000;
  var sessionLast = parseInt(lsGet('artdon_sid_last') || ssGet('artdon_sid_last') || '0', 10) || 0;
  var sessionId = (Date.now() - sessionLast > sessionTimeoutMs) ? '' : (ssGet('artdon_sid') || lsGet('artdon_sid'));
  if (!sessionId) sessionId = rand('s_');
  ssSet('artdon_sid', sessionId);
  ssSet('artdon_sid_last', String(Date.now()));
  lsSet('artdon_sid', sessionId);
  lsSet('artdon_sid_last', String(Date.now()));
  var pageviewId = rand('p_');
  var visitorFingerprintHash = simpleHash([
    navigator.userAgent || '',
    navigator.language || '',
    (Intl.DateTimeFormat().resolvedOptions().timeZone || ''),
    (screen && screen.width ? screen.width+'x'+screen.height : ''),
    pageType()
  ].join('|'));

  document.addEventListener('visibilitychange', function(){ updateActive(); if (document.hidden) heartbeat(true); });
  window.addEventListener('scroll', function(){ updateScroll(); }, {passive:true});
  window.addEventListener('beforeunload', function(){ heartbeat(true); });

  function heartbeat(beacon){
    updateActive(); updateScroll();
    ssSet('artdon_sid_last', String(Date.now()));
    lsSet('artdon_sid_last', String(Date.now()));
    var p = basePayload(); p.action = 'heartbeat';
    send(p, !!beacon);
  }

  function init(){
    updateScroll();
    setCurrentSection('Page top');
    var p = basePayload(); p.action = 'pageview';
    send(p, false);
    observeSections();
    bindClicks();
    ensureInquiryFields();
    setInterval(function(){ heartbeat(false); }, 15000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
