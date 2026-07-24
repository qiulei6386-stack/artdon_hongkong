/* V7.1.6.22 - disable old floating product-card/compact toolbar */
(function(){
  function removeTools(){
    var box=document.getElementById('artdonV7092AdminTools');
    if(box) box.remove();
    document.body.classList.remove('artdon-v7092-admin-compact');
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', removeTools); else removeTools();
  window.addEventListener('load', removeTools);
})();
