/* Artdon Lighting 官网 V7.0.9.1 - 产品系列后台紧凑排版 */
(function(){
  'use strict';
  if(window.__ARTDON_ADMIN_PRODUCT_COMPACT_V7091__) return;
  window.__ARTDON_ADMIN_PRODUCT_COMPACT_V7091__=true;

  var STORE='artdon_v7091_product_admin_compact';
  var timer=0;

  function txt(v){return String(v==null?'':v).replace(/\s+/g,' ').trim();}
  function low(v){return txt(v).toLowerCase();}
  function pathSignal(){return /admin|backend|manage|dashboard|product|series|catalog/.test(low(location.pathname+' '+location.search));}
  function contentSignal(){
    var title=low(document.title);
    var body=low((document.body&&document.body.innerText||'').slice(0,120000));
    var controls=[];
    document.querySelectorAll('input,select,textarea,button,a,[data-page],[data-module],[data-section]').forEach(function(el,index){
      if(index>650)return;
      controls.push(el.id,el.getAttribute('name'),el.getAttribute('placeholder'),el.getAttribute('data-page'),el.getAttribute('data-module'),el.getAttribute('data-section'),(el.textContent||'').slice(0,90));
    });
    var s=title+' '+body+' '+low(controls.filter(Boolean).join(' '));
    return /(产品中心|产品系列|系列管理|产品管理|产品大类|产品库|产品编辑|系列编辑|规格型号|商品管理|product center|product series|series management|product management|product library|edit product|edit series|web_product_series|series name|series_name|product hierarchy)/i.test(s);
  }
  function isTargetPage(){return pathSignal() && contentSignal() && document.querySelectorAll('input:not([type="hidden"]),select,textarea').length>=4;}
  function compactOn(){try{return localStorage.getItem(STORE)!=='0';}catch(e){return true;}}
  function applyMode(){
    if(!document.body)return;
    document.body.classList.add('artdon-v7091-product-admin');
    document.body.classList.toggle('artdon-v7091-compact',compactOn());
    var b=document.getElementById('artdonV7091DensityBtn');
    if(b){b.textContent=compactOn()?'切换标准排版':'切换紧凑排版';b.title=compactOn()?'当前为紧凑排版，点击恢复标准布局':'当前为标准排版，点击启用紧凑布局';}
  }

  function controlKey(el,wrap){
    var parts=[el.id,el.name,el.getAttribute('placeholder'),el.getAttribute('data-field'),el.getAttribute('aria-label')];
    if(wrap){
      var label=wrap.querySelector('label');
      if(label)parts.push(label.textContent);
      parts.push(wrap.getAttribute('data-label'));
    }
    return low(parts.filter(Boolean).join(' '));
  }
  function usableControl(el){
    if(!el||el.nodeType!==1)return false;
    var type=low(el.getAttribute('type'));
    if(type==='hidden'||type==='submit'||type==='button'||type==='reset'||type==='image')return false;
    if(el.closest('table,thead,tbody,tfoot,.toolbar,.actions,.btns,.buttons,.form-actions,.button-row'))return false;
    return /^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName);
  }
  function controlCount(node){return node?node.querySelectorAll('input:not([type="hidden"]),select,textarea').length:0;}
  function wrapperFor(el){
    var label=el.closest('label');
    if(label&&controlCount(label)===1)return label;
    var w=el.closest('.field,.form-group,.form-item,.input-group,.control-group,.control,.form-field,.field-row,.field-item,.col,.column,.cell');
    if(w&&controlCount(w)<=3&&!w.closest('table'))return w;
    var p=el.parentElement;
    if(p&&controlCount(p)<=3&&!p.closest('table'))return p;
    return null;
  }
  function spanFor(el,key){
    var type=low(el.getAttribute('type'));
    if(el.tagName==='TEXTAREA')return /(short|brief|summary|subtitle)/.test(key)?6:12;
    if(/description|full.?description|content|detail|remark|note|intro|seo.?description|technical|specification|downloads|gallery/.test(key))return 12;
    if(type==='file'||/image|picture|photo|cover|drawing|upload|file|video|datasheet|manual|photometric|cad|bim/.test(key))return 6;
    if(/tags?|keywords?|application|mounting|optic|beam|finish|dimming|voltage|cct|cri|filter/.test(key))return 4;
    if(/seo.?title|page.?title|subtitle|series.?name|product.?name|display.?name|category|slug|model|code|prefix/.test(key))return 3;
    if(type==='checkbox'||type==='radio'||/status|active|publish|published|featured|recommend|new|star|sort|order|priority|type|id$/.test(key))return 2;
    return 3;
  }
  function clearSpan(w){
    ['2','3','4','6','8','12'].forEach(function(n){w.classList.remove('artdon-v7091-span-'+n);});
  }
  function decorateWrapper(w,el){
    if(!w)return;
    var key=controlKey(el,w),span=spanFor(el,key);
    w.classList.add('artdon-v7091-field');
    clearSpan(w);w.classList.add('artdon-v7091-span-'+span);
    if(el.tagName==='TEXTAREA'&&!/(short|brief|summary|subtitle)/.test(key))w.classList.add('artdon-v7091-long-text');
    if(low(el.getAttribute('type'))==='checkbox'||low(el.getAttribute('type'))==='radio')w.classList.add('artdon-v7091-check-field');
  }

  function patchForms(root){
    var scope=root&&root.querySelectorAll?root:document;
    var controls=[];
    if(scope.matches&&usableControl(scope))controls.push(scope);
    scope.querySelectorAll('input,select,textarea').forEach(function(el){if(usableControl(el))controls.push(el);});
    var groups=new Map();
    controls.forEach(function(el){
      var w=wrapperFor(el);if(!w)return;
      decorateWrapper(w,el);
      var p=w.parentElement;if(!p||p.closest('table'))return;
      if(!groups.has(p))groups.set(p,[]);
      if(groups.get(p).indexOf(w)<0)groups.get(p).push(w);
    });
    groups.forEach(function(wrappers,parent){
      if(wrappers.length>=2&&controlCount(parent)<=Math.max(24,wrappers.length*3))parent.classList.add('artdon-v7091-form-grid');
    });

    // 把旧页面的 row2 / row3 小分组展开，让全部短字段真正连续排成一行四格，
    // 而不是每个两字段小组仍然独占一整行。
    scope.querySelectorAll('.row2,.row3,.row4,.grid2,.grid3,.grid4,.form-row,.fields-row,.field-row,.form-grid,.fields-grid').forEach(function(row){
      if(row.closest('table,.toolbar,.actions,.btns,.buttons,.form-actions'))return;
      var fields=row.querySelectorAll('.artdon-v7091-field');
      if(!fields.length)return;
      row.classList.add('artdon-v7091-flatten-row');
      var parent=row.parentElement;
      if(parent&&!parent.closest('table'))parent.classList.add('artdon-v7091-form-grid');
    });

    scope.querySelectorAll('.actions,.btns,.buttons,.button-row,.form-actions,.toolbar,.tools,.operate,.operations').forEach(function(el){el.classList.add('artdon-v7091-actions');});
  }

  function addToolbar(){
    if(document.getElementById('artdonV7091AdminToolbar')||!document.body)return;
    var bar=document.createElement('div');bar.id='artdonV7091AdminToolbar';
    var toggle=document.createElement('button');toggle.type='button';toggle.id='artdonV7091DensityBtn';
    toggle.addEventListener('click',function(){
      try{localStorage.setItem(STORE,compactOn()?'0':'1');}catch(e){}
      applyMode();
    });
    var link=document.createElement('a');link.href='/admin/artdon_product_card_settings_v709.php';link.textContent='卡片显示设置';
    bar.appendChild(toggle);bar.appendChild(link);document.body.appendChild(bar);applyMode();
  }

  function patch(root){
    if(!isTargetPage())return;
    applyMode();patchForms(root||document);addToolbar();
  }
  function schedule(root){clearTimeout(timer);timer=setTimeout(function(){patch(root||document);},70);}
  function boot(){
    patch(document);
    if(window.MutationObserver){
      var mo=new MutationObserver(function(list){
        var root=document;
        for(var i=0;i<list.length;i++){
          for(var j=0;j<(list[i].addedNodes||[]).length;j++){
            var n=list[i].addedNodes[j];if(n&&n.nodeType===1){root=n;break;}
          }
        }
        schedule(root);
      });
      mo.observe(document.documentElement,{childList:true,subtree:true});
    }
    window.addEventListener('pageshow',function(){schedule(document);});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
