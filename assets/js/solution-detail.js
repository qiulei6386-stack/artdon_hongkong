(function(){
  function ready(fn){
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }
  ready(function(){
    var links = Array.prototype.slice.call(document.querySelectorAll('[data-solution-scroll]'));
    if(!links.length) return;
    function setActive(id){
      links.forEach(function(link){
        var active = link.getAttribute('href') === '#' + id;
        link.classList.toggle('is-active', active);
      });
    }
    links.forEach(function(link){
      link.addEventListener('click', function(event){
        var id = (link.getAttribute('href') || '').replace('#', '');
        var target = id ? document.getElementById(id) : null;
        if(!target) return;
        event.preventDefault();
        target.scrollIntoView({behavior:'smooth', block:'start'});
        setActive(id);
      });
    });
    var sections = links.map(function(link){
      var id = (link.getAttribute('href') || '').replace('#', '');
      return id ? document.getElementById(id) : null;
    }).filter(Boolean);
    if('IntersectionObserver' in window){
      var observer = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if(entry.isIntersecting) setActive(entry.target.id);
        });
      }, {rootMargin:'-35% 0px -55% 0px', threshold:0});
      sections.forEach(function(section){observer.observe(section);});
    }
  });
})();
