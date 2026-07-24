(function(){
  function ready(fn){
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }
  ready(function(){
    var page = document.querySelector('.sol-page');
    if(!page) return;
    var tabs = Array.prototype.slice.call(page.querySelectorAll('[data-sol-tab]'));
    var panels = Array.prototype.slice.call(page.querySelectorAll('[data-sol-panel]'));
    var apps = document.getElementById('solutionsApplications');

    function showTab(key, scroll){
      var found = false;
      tabs.forEach(function(tab){
        var active = tab.getAttribute('data-sol-tab') === key;
        if(active) found = true;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      if(!found && tabs[0]) key = tabs[0].getAttribute('data-sol-tab') || '';
      panels.forEach(function(panel){
        var active = panel.getAttribute('data-sol-panel') === key;
        panel.hidden = !active;
        panel.classList.toggle('is-active', active);
      });
      if(scroll && apps){
        apps.scrollIntoView({behavior:'smooth', block:'start'});
      }
    }

    tabs.forEach(function(tab){
      tab.addEventListener('click', function(){
        showTab(tab.getAttribute('data-sol-tab') || '', false);
      });
    });

    Array.prototype.slice.call(page.querySelectorAll('[data-sol-target]')).forEach(function(card){
      card.addEventListener('click', function(){
        showTab(card.getAttribute('data-sol-target') || '', true);
      });
    });

    if(tabs[0]) showTab(tabs[0].getAttribute('data-sol-tab') || '', false);
  });
})();
