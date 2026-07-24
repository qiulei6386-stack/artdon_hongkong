(function(){
  'use strict';
  document.querySelectorAll('.ap-video-media video').forEach(function(video){
    video.addEventListener('play',function(){
      document.querySelectorAll('.ap-video-media video').forEach(function(other){if(other!==video)other.pause();});
    });
  });
  var search=document.querySelector('.ap-search input[type="search"]');
  if(search&&search.value){requestAnimationFrame(function(){search.setSelectionRange(search.value.length,search.value.length);});}
})();
