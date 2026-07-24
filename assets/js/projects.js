(function(){
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.proj-tab'));
  var region = document.querySelector('.proj-region');
  var cards = Array.prototype.slice.call(document.querySelectorAll('.proj-card'));
  var empty = document.querySelector('[data-project-empty]');
  var load = document.querySelector('[data-project-load]');
  var activeCategory = 'All Projects';

  function matches(card){
    var category = card.getAttribute('data-category') || '';
    var cardRegion = card.getAttribute('data-region') || '';
    var selectedRegion = region ? region.value : 'All Regions';
    var categoryOk = activeCategory === 'All Projects' || category === activeCategory;
    var regionOk = selectedRegion === 'All Regions' || cardRegion === selectedRegion;
    return categoryOk && regionOk;
  }

  function applyFilters(){
    var visibleCount = 0;
    cards.forEach(function(card){
      var show = matches(card);
      card.hidden = !show;
      if (show) visibleCount += 1;
    });
    if (empty) empty.classList.toggle('is-visible', visibleCount === 0);
    if (load) load.classList.toggle('is-hidden', visibleCount === 0);
  }

  tabs.forEach(function(tab){
    tab.addEventListener('click', function(){
      activeCategory = tab.getAttribute('data-category') || 'All Projects';
      tabs.forEach(function(item){
        var isActive = item === tab;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      applyFilters();
    });
  });

  if (region) {
    region.addEventListener('change', applyFilters);
  }
  if (load) {
    load.addEventListener('click', function(){
      load.classList.add('is-hidden');
    });
  }

  applyFilters();
})();
