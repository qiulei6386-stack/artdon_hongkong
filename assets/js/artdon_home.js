const navToggle=document.getElementById('navToggle');
const siteNav=document.getElementById('siteNav');
if(navToggle&&siteNav){
  navToggle.addEventListener('click',()=>{ const open=siteNav.classList.toggle('open'); navToggle.setAttribute('aria-expanded', open?'true':'false'); });
  siteNav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{ siteNav.classList.remove('open'); navToggle.setAttribute('aria-expanded','false'); }));
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
  const filterForm = document.querySelector('[data-product-filter-form]');
  if (filterForm) {
    let submitTimer = null;
    filterForm.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
      input.addEventListener('change', () => {
        clearTimeout(submitTimer);
        submitTimer = setTimeout(() => filterForm.requestSubmit(), 160);
      });
    });
  }

  const filterToggle = document.querySelector('[data-catalog-filter-toggle]');
  const filters = document.getElementById('catalogFilters');
  if (filterToggle && filters) {
    filterToggle.addEventListener('click', () => {
      const open = filters.classList.toggle('is-open');
      filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
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
