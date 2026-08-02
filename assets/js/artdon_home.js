const navToggle=document.getElementById('navToggle');
const siteNav=document.getElementById('siteNav');
if(navToggle&&siteNav){
  const closeNav=()=>{
    siteNav.classList.remove('open');
    document.body.classList.remove('nav-menu-open');
    navToggle.setAttribute('aria-expanded','false');
  };
  navToggle.addEventListener('click',()=>{
    const open=siteNav.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', open?'true':'false');
    document.body.classList.toggle('nav-menu-open', open);
    if(open) requestAnimationFrame(()=>{ siteNav.scrollTop=0; });
  });
  siteNav.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeNav));
  document.addEventListener('keydown',event=>{ if(event.key==='Escape'&&siteNav.classList.contains('open')) closeNav(); });
}
const header=document.querySelector('.site-header');
window.addEventListener('scroll',()=>{ if(header) header.style.boxShadow=window.scrollY>18?'0 8px 28px rgba(0,0,0,.045)':'none'; },{passive:true});

const carousel=document.getElementById('heroCarousel');
if(carousel){
  const slides=[...carousel.querySelectorAll('.slide')];
  const dots=[...carousel.querySelectorAll('.dot')];
  const now=document.getElementById('slideNow');
  const progressWrap=carousel.querySelector('.progress');
  let index=0;
  let timer=null;
  const duration=6000;
  function restartProgress(){
    if(!progressWrap) return;
    progressWrap.classList.remove('run');
    void progressWrap.offsetWidth;
    progressWrap.classList.add('run');
  }
  function show(i){
    index=(i+slides.length)%slides.length;
    slides.forEach((s,n)=>s.classList.toggle('is-active',n===index));
    dots.forEach((d,n)=>{
      const active=n===index;
      d.classList.toggle('is-active',active);
      d.setAttribute('aria-current',active?'true':'false');
    });
    if(now) now.textContent=String(index+1).padStart(2,'0');
    restartProgress();
  }
  function next(){ show(index+1); }
  function start(){ clearInterval(timer); timer=setInterval(next,duration); restartProgress(); }
  carousel.querySelectorAll('[data-dir]').forEach(btn=>btn.addEventListener('click',()=>{ show(index+(btn.dataset.dir==='next'?1:-1)); start(); }));
  dots.forEach(btn=>btn.addEventListener('click',()=>{ show(Number(btn.dataset.go)||0); start(); }));
  carousel.addEventListener('mouseenter',()=>clearInterval(timer));
  carousel.addEventListener('mouseleave',start);
  carousel.addEventListener('quote:open',()=>clearInterval(timer));
  carousel.addEventListener('quote:close',start);
  show(0); start();
}


// V2.8 product catalogue: lightweight accordions and show-more rows.
document.querySelectorAll('.filter-head').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const group=btn.closest('.filter-group');
    if(!group) return;
    group.classList.toggle('is-open');
    const mark=btn.querySelector('span');
    if(mark) mark.textContent=group.classList.contains('is-open')?'−':'+';
  });
});
document.querySelectorAll('[data-toggle-section]').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const section=btn.closest('.catalog-section');
    if(!section) return;
    section.classList.toggle('is-expanded');
    const expanded=section.classList.contains('is-expanded');
    btn.firstChild.nodeValue=expanded?'show less ':'show more ';
  });
});

// V3.2: load the hero video after the page is usable; keep the poster as the fast LCP image.
window.addEventListener('load',()=>{
  const video=document.querySelector('.hero-video');
  if(!video) return;
  const source=video.querySelector('source[data-src]');
  if(!source) return;
  const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection;
  const shouldSkipVideo=
    (window.matchMedia&&window.matchMedia('(max-width: 760px)').matches) ||
    (connection&&connection.saveData) ||
    (connection&&/^(slow-2g|2g|3g)$/i.test(connection.effectiveType||''));
  if(shouldSkipVideo){
    video.removeAttribute('autoplay');
    source.removeAttribute('data-src');
    return;
  }
  const loadVideo=()=>{
    if(source.src) return;
    source.src=source.dataset.src;
    video.load();
    const playPromise=video.play();
    if(playPromise&&typeof playPromise.catch==='function') playPromise.catch(()=>{});
  };
  if('requestIdleCallback' in window){ requestIdleCallback(loadVideo,{timeout:1800}); }
  else{ setTimeout(loadVideo,700); }
});

document.querySelectorAll('.hero-video').forEach(video=>{
  video.addEventListener('canplay',()=>video.classList.add('is-ready'),{once:true});
});

// V3.3: product-aware Get a Quote modal for each hero slide.
(()=>{
  const modal=document.getElementById('heroQuoteModal');
  if(!modal) return;
  const triggers=[...document.querySelectorAll('.hero-quote-trigger')];
  const productInput=document.getElementById('quoteSelectedProduct');
  const productLinkInput=document.getElementById('quoteProductLink');
  const nameInput=document.getElementById('quoteCustomerName');
  let lastFocus=null;

  const openModal=(trigger)=>{
    lastFocus=trigger;
    if(productInput) productInput.value=trigger.dataset.quoteProduct||'';
    if(productLinkInput) productLinkInput.value=trigger.dataset.quoteLink||'';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    document.body.classList.add('quote-modal-open');
    carousel?.dispatchEvent(new CustomEvent('quote:open'));
    window.setTimeout(()=>nameInput?.focus(),80);
  };
  const closeModal=()=>{
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    document.body.classList.remove('quote-modal-open');
    carousel?.dispatchEvent(new CustomEvent('quote:close'));
    lastFocus?.focus();
  };

  triggers.forEach(trigger=>trigger.addEventListener('click',()=>openModal(trigger)));
  modal.querySelectorAll('[data-quote-close]').forEach(el=>el.addEventListener('click',closeModal));
  document.addEventListener('keydown',event=>{
    if(event.key==='Escape'&&modal.classList.contains('is-open')) closeModal();
  });
})();

// V3.6: homepage product-family tabs.
(()=>{
  const tabs=[...document.querySelectorAll('.product-family-tab[data-product-filter]')];
  const cards=[...document.querySelectorAll('#homeProductGrid .clean-product-card[data-product-categories]')];
  if(!tabs.length||!cards.length) return;

  const applyFilter=(filter)=>{
    tabs.forEach(tab=>{
      const active=tab.dataset.productFilter===filter;
      tab.classList.toggle('is-active',active);
      tab.setAttribute('aria-selected',active?'true':'false');
    });

    cards.forEach(card=>{
      const categories=(card.dataset.productCategories||'').split(/\s+/).filter(Boolean);
      const show=filter==='all' ? card.dataset.featured==='1' : categories.includes(filter);
      card.hidden=!show;
      card.classList.toggle('is-filtered-out',!show);
      card.classList.remove('is-filtered-in');
      if(show){
        void card.offsetWidth;
        card.classList.add('is-filtered-in');
      }
    });
  };

  tabs.forEach(tab=>tab.addEventListener('click',()=>applyFilter(tab.dataset.productFilter||'all')));
  applyFilter('all');
})();

// V5.0 product catalogue filters and product gallery.
(() => {
  const mobileFilterQuery = window.matchMedia ? window.matchMedia('(max-width: 800px)') : null;
  const isMobileFilter = () => mobileFilterQuery ? mobileFilterQuery.matches : window.innerWidth <= 800;

  const filterForm = document.querySelector('[data-product-filter-form]');
  if (filterForm) {
    let submitTimer = null;
    filterForm.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
      input.addEventListener('change', () => {
        if (isMobileFilter()) return;
        clearTimeout(submitTimer);
        submitTimer = setTimeout(() => filterForm.requestSubmit(), 160);
      });
    });
  }

  const filterToggle = document.querySelector('[data-catalog-filter-toggle]');
  const filters = document.getElementById('catalogFilters');
  const filterBackdrop = document.querySelector('[data-catalog-filter-backdrop]');
  const filterCloseButtons = Array.from(document.querySelectorAll('[data-catalog-filter-close]'));
  const mobileShowButton = document.querySelector('[data-catalog-filter-mobile-show]');
  const mobileResetButton = document.querySelector('[data-catalog-filter-mobile-reset]');
  const updateMobileShowLabel = () => {
    if (!mobileShowButton) return;
    const visibleCards = Array.from(document.querySelectorAll('[data-artdon-filter-card]')).filter(card => {
      if (card.hidden) return false;
      if (card.classList.contains('artdon-v718103-hidden')) return false;
      if (card.classList.contains('artdon-v71898-hidden')) return false;
      if (card.classList.contains('artdon-v71895-hidden')) return false;
      if (card.classList.contains('artdon-filter-hidden-v71889')) return false;
      return card.style.display !== 'none';
    }).length;
    const count = visibleCards || Number((filterToggle && filterToggle.querySelector('span') ? filterToggle.querySelector('span').textContent : '').replace(/\D+/g, '')) || 0;
    const singular = mobileShowButton.dataset.labelSingular || 'Product';
    const plural = mobileShowButton.dataset.labelPlural || 'Products';
    mobileShowButton.textContent = `Show ${count} ${count === 1 ? singular : plural}`;
  };
  const setFilterOpen = (open) => {
    if (!filterToggle || !filters) return;
    filters.classList.toggle('is-open', open);
    filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    document.body.classList.toggle('catalog-filter-open', open);
    if (filterBackdrop) {
      filterBackdrop.classList.toggle('is-open', open);
      filterBackdrop.hidden = !open;
    }
    if (open) {
      updateMobileShowLabel();
      window.setTimeout(updateMobileShowLabel, 260);
    }
  };
  if (filterToggle && filters) {
    filterToggle.addEventListener('click', () => {
      setFilterOpen(!filters.classList.contains('is-open'));
    });
    filterCloseButtons.forEach(button => button.addEventListener('click', () => setFilterOpen(false)));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && filters.classList.contains('is-open')) setFilterOpen(false);
    });
    window.addEventListener('resize', () => {
      if (!isMobileFilter() && filters.classList.contains('is-open')) setFilterOpen(false);
    });
  }
  if (filterForm) {
    filterForm.addEventListener('change', () => window.setTimeout(updateMobileShowLabel, 40), true);
    filterForm.addEventListener('submit', () => setFilterOpen(false), true);
  }
  if (mobileResetButton && filterForm) {
    mobileResetButton.addEventListener('click', event => {
      if (!isMobileFilter()) return;
      event.preventDefault();
      filterForm.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => { input.checked = false; });
      filterForm.dispatchEvent(new Event('change', { bubbles: true }));
      window.setTimeout(updateMobileShowLabel, 80);
    });
  }

  const galleryMain = document.getElementById('productGalleryMain');
  const galleryButtons = Array.from(document.querySelectorAll('[data-product-gallery-image]'));
  if (galleryMain && galleryButtons.length) {
    galleryButtons.forEach(button => {
      button.addEventListener('click', () => {
        const src = button.dataset.productGalleryImage || '';
        const alt = button.dataset.productGalleryAlt || '';
        if (!src) return;
        galleryMain.classList.add('is-changing');
        window.setTimeout(() => {
          galleryMain.src = src;
          galleryMain.alt = alt;
          galleryMain.classList.remove('is-changing');
        }, 110);
        galleryButtons.forEach(item => item.classList.toggle('is-active', item === button));
      });
    });
  }
})();
