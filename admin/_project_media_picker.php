<div class="media-picker" id="mediaPicker" aria-hidden="true" data-version="blog-media-picker-v2">
  <div class="media-picker-backdrop" data-media-close></div>
  <section class="media-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="mediaPickerTitle">
    <header>
      <div><h2 id="mediaPickerTitle">从媒体资料库选择</h2><p>按页读取媒体库，每页 20 张。</p></div>
      <button type="button" class="media-picker-close" data-media-close aria-label="关闭">×</button>
    </header>
    <form class="media-picker-toolbar" id="mediaPickerToolbar">
      <input type="search" id="mediaPickerSearch" placeholder="搜索标题 / 文件名 / ALT / 路径">
      <select id="mediaPickerCategory" aria-label="用途分类">
        <option value="">全部分类</option>
        <option value="products">产品图片</option>
        <option value="dimensions">尺寸图 / 结构图</option>
        <option value="photometric">配光曲线</option>
        <option value="accessories">配件图片</option>
        <option value="projects">项目案例</option>
        <option value="banners">首页轮播</option>
        <option value="articles">文章封面</option>
        <option value="downloads">下载资料</option>
        <option value="videos">视频文件</option>
        <option value="images">通用图片</option>
      </select>
      <select id="mediaPickerFileType" aria-label="文件类型">
        <option value="">全部</option>
        <option value="image">图片</option>
        <option value="video">视频</option>
        <option value="document">PDF / 文档</option>
        <option value="archive">ZIP / 资料</option>
      </select>
      <select id="mediaPickerSort" aria-label="排序">
        <option value="newest">最新</option>
        <option value="oldest">最旧</option>
        <option value="title">标题 A-Z</option>
      </select>
      <button type="button" class="admin-button-secondary" data-media-reset>重置筛选</button>
    </form>
    <div class="media-picker-status" id="mediaPickerStatus">打开后加载媒体库。</div>
    <div class="media-picker-grid" id="mediaPickerGrid"></div>
    <div class="media-picker-empty" id="mediaPickerEmpty" hidden>没有找到匹配的媒体文件</div>
    <div class="media-picker-pager" id="mediaPickerPager">
      <button type="button" class="admin-button-secondary" data-media-page-prev>上一页</button>
      <span data-media-page-info>第 1 / 1 页</span>
      <button type="button" class="admin-button-secondary" data-media-page-next>下一页</button>
    </div>
  </section>
</div>
<style>
#mediaPicker{position:fixed;inset:0;z-index:1200;display:none}
#mediaPicker.is-open{display:block}
#mediaPicker .media-picker-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.46)}
#mediaPicker .media-picker-dialog{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(1480px,96vw);height:min(900px,94vh);max-height:94vh;display:grid;grid-template-rows:auto auto auto minmax(420px,1fr) auto auto;background:#fff;border-radius:12px;overflow:hidden}
#mediaPicker header{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px 20px;border-bottom:1px solid #e5e5e5}
#mediaPicker h2{margin:0;font-size:22px}
#mediaPicker p{margin:4px 0 0;color:#666}
#mediaPicker .media-picker-close{border:0;background:#111;color:#fff;width:38px;height:38px;border-radius:50%;font-size:22px;cursor:pointer}
#mediaPicker .media-picker-toolbar{display:grid;grid-template-columns:minmax(220px,1fr) 180px 140px 120px auto;gap:10px;align-items:center;padding:12px 20px;border-bottom:1px solid #e5e5e5;background:#fff}
#mediaPicker .media-picker-toolbar input,#mediaPicker .media-picker-toolbar select{height:40px;min-width:0;border:1px solid #ddd;border-radius:6px;padding:0 12px;background:#fff;font:inherit;font-size:13px}
#mediaPicker .media-picker-status{padding:9px 20px;background:#f7f7f7;color:#666;font-size:12px;font-weight:800;border-bottom:1px solid #e5e5e5}
#mediaPicker .media-picker-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(250px,1fr))!important;gap:16px!important;overflow:auto;padding:20px;align-content:start!important;grid-auto-rows:auto!important;background:#fff!important}
#mediaPicker .media-picker-card{display:grid!important;grid-template-rows:auto 1fr;border:1px solid #e5e5e5;background:#fff;overflow:hidden;min-height:326px;border-radius:8px}
#mediaPicker .media-picker-select{display:grid;grid-template-rows:auto 1fr;width:100%;height:100%;padding:0;border:0;background:#fff;text-align:left;color:inherit;cursor:pointer}
#mediaPicker .media-picker-preview{position:relative;display:grid;place-items:center;aspect-ratio:16/10;background:#f2f3f4;overflow:hidden;border-bottom:1px solid #e5e5e5}
#mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{display:block;width:100%;height:100%;object-fit:cover;background:#f2f3f4}
#mediaPicker .media-picker-preview.is-broken::after{content:"图片不存在";position:absolute;inset:0;display:grid;place-items:center;background:#f2f3f4;color:#999;font-size:13px;font-weight:800}
#mediaPicker .media-picker-file-icon{display:grid;place-items:center;width:78px;height:94px;border:2px solid #111;border-radius:6px;color:#111;font-size:16px;font-weight:900;letter-spacing:.06em;background:#fff}
#mediaPicker .media-picker-card-body{display:grid;gap:5px;padding:12px;min-height:138px}
#mediaPicker .media-picker-title{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;color:#111;font-size:14px;font-weight:700;line-height:1.4}
#mediaPicker .media-picker-line{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#777;font-size:12px;line-height:1.35}
#mediaPicker .media-picker-line.is-alt{color:#555}
#mediaPicker .media-picker-choose{align-self:end;display:inline-flex;width:max-content;margin-top:6px;padding:6px 12px;border-radius:4px;background:#111;color:#fff;font-size:12px;font-weight:800}
#mediaPicker .media-picker-empty{padding:28px;text-align:center;color:#777;font-weight:800;border-top:1px solid #e5e5e5}
#mediaPicker .media-picker-empty[hidden]{display:none!important}
#mediaPicker .media-picker-pager{display:flex;align-items:center;justify-content:center;gap:14px;padding:14px 20px;border-top:1px solid #e5e5e5;background:#fff}
#mediaPicker .media-picker-pager span{min-width:150px;text-align:center;font-weight:800;color:#555}
#mediaPicker .media-picker-pager button[disabled]{opacity:.45;cursor:not-allowed}
@media(max-width:900px){#mediaPicker .media-picker-toolbar{grid-template-columns:1fr 1fr}#mediaPicker .media-picker-toolbar input{grid-column:1/-1}}
@media(max-width:640px){#mediaPicker .media-picker-dialog{width:96vw;height:92vh}#mediaPicker .media-picker-toolbar{grid-template-columns:1fr}#mediaPicker .media-picker-grid{grid-template-columns:1fr!important;padding:12px!important}}
</style>
<script>
(function(){
  var picker=document.getElementById('mediaPicker'), grid=document.getElementById('mediaPickerGrid'), status=document.getElementById('mediaPickerStatus'), empty=document.getElementById('mediaPickerEmpty');
  var search=document.getElementById('mediaPickerSearch'), category=document.getElementById('mediaPickerCategory'), fileType=document.getElementById('mediaPickerFileType'), sort=document.getElementById('mediaPickerSort'), pager=document.getElementById('mediaPickerPager');
  if(!picker||picker.dataset.ready==='1')return; picker.dataset.ready='1';
  var activeInput=null, activeType='image', activeUsage='', activePreferred='', page=1, perPage=20, timer=0;
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function fileTypeValue(){return fileType.value || activeType || '';}
  function categoryValue(){return category.value || activePreferred || activeUsage || '';}
  function isImageField(){return (activeType||'')==='image';}
  function defaultCategoryFor(usage,type){
    if(type==='image' && usage==='articles') return 'blog-images';
    return usage || '';
  }
  function defaultTypeFor(type){return type==='file'?'document':(type||'image');}
  function imgSrc(path){return path && /^(?:https?:)?\/\//i.test(path) ? path : '../'+String(path||'').replace(/^\/+/,'');}
  function renderCard(item){
    var type=item.type||'file', ext=(item.ext||String(item.path||'').split('.').pop()||'FILE').toUpperCase();
    var title=item.title||item.basename||item.path||'', basename=item.basename||String(item.path||'').split('/').pop(), alt=item.alt||'', path=item.path||'';
    var searchText=[title,basename,alt,path,item.usage||'',item.usage_label||'',item.aliases||''].join(' ').toLowerCase();
    var preview=type==='image'
      ? '<img src="'+esc(item.thumb||imgSrc(path))+'" data-fallback="'+esc(imgSrc(path))+'" alt="" loading="lazy" onerror="if(this.dataset.fallback&&this.src.indexOf(this.dataset.fallback)===-1){this.src=this.dataset.fallback;}else{this.closest(\\'.media-picker-preview\\').classList.add(\\'is-broken\\');this.remove();}">'
      : (type==='video'
        ? '<video src="'+esc(imgSrc(path))+'" muted preload="metadata"></video>'
        : '<span class="media-picker-file-icon">'+esc(ext.slice(0,4))+'</span>');
    return '<article class="media-picker-card" data-media-type="'+esc(type)+'" data-media-usage="'+esc(item.usage||'')+'" data-media-usages="'+esc(item.aliases||'')+'" data-media-search="'+esc(searchText)+'"><button type="button" class="media-picker-select" data-media-select data-media-path="'+esc(path)+'" data-media-alt="'+esc(alt||title||basename)+'" data-media-type="'+esc(type)+'" data-media-usage="'+esc(item.usage||'')+'">'
      + '<span class="media-picker-preview">'+preview+'</span>'
      + '<span class="media-picker-card-body">'
      + '<strong class="media-picker-title" title="'+esc(title)+'">'+esc(title)+'</strong>'
      + '<span class="media-picker-line" title="'+esc(basename)+'">'+esc(basename)+'</span>'
      + '<span class="media-picker-line is-alt" title="'+esc(alt)+'">'+esc(alt||'ALT 未填写')+'</span>'
      + '<span class="media-picker-line" title="'+esc(path)+'">'+esc(path)+'</span>'
      + '<span class="media-picker-choose">选择</span>'
      + '</span></button></article>';
  }
  function setStatus(text){if(status)status.textContent=text;}
  function load(){
    var params=new URLSearchParams({page:String(page),per_page:String(perPage),q:(search.value||'').trim(),file_type:fileTypeValue(),category:categoryValue(),sort:sort.value||'newest',scan:'1'});
    setStatus('正在读取媒体库...'); if(empty)empty.hidden=true;
    grid.innerHTML='<div style="grid-column:1/-1;padding:30px;text-align:center;color:#777">加载中...</div>';
    fetch('_media_picker_lazy.php?'+params.toString(),{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(function(data){
      var items=data.items||[];
      grid.innerHTML=items.map(renderCard).join('');
      if(!items.length){grid.innerHTML=''; if(empty)empty.hidden=false;}
      setStatus('本页 '+items.length+' 个，共 '+(data.total||0)+' 个。');
      pager.querySelector('[data-media-page-info]').textContent='第 '+(data.page||page)+' / '+(data.pages||1)+' 页';
      pager.querySelector('[data-media-page-prev]').disabled=!data.has_prev;
      pager.querySelector('[data-media-page-next]').disabled=!data.has_next;
    }).catch(function(){setStatus('媒体库读取失败。'); grid.innerHTML=''; if(empty)empty.hidden=false;});
  }
  function open(input,type,usage){
    activeInput=input; activeType=type||'image'; activeUsage=usage||''; activePreferred=defaultCategoryFor(activeUsage,activeType); page=1;
    if(search)search.value=''; if(fileType)fileType.value=defaultTypeFor(activeType); if(category)category.value=activePreferred==='blog-images'?'':activePreferred; if(sort)sort.value='newest';
    picker.classList.add('is-open'); picker.setAttribute('aria-hidden','false'); document.body.classList.add('admin-modal-open');
    load();
  }
  function close(){picker.classList.remove('is-open'); picker.setAttribute('aria-hidden','true'); document.body.classList.remove('admin-modal-open');}
  function findNamedInput(form,name){return form&&Array.prototype.slice.call(form.querySelectorAll('input[name],textarea[name]')).find(function(el){return el.name===name;});}
  function altInputFor(input){
    if(!input||!input.form)return null;
    var name=input.name||'', altName='';
    if(name==='cover_image') altName='cover_alt';
    else if(name==='hero_image') altName='hero_image_alt';
    else if(name==='module_image') altName='module_image_alt';
    else if(name==='project_image') altName='project_image_alt';
    else if(name.indexOf('[image]')>-1) altName=name.replace('[image]','[image_alt]');
    else if(/image$/.test(name)) altName=name.replace(/image$/,'image_alt');
    return altName?findNamedInput(input.form,altName):null;
  }
  document.addEventListener('click',function(e){
    var btn=e.target.closest&&e.target.closest('[data-media-open]');
    if(btn){var input=btn.closest('.field').querySelector('.media-path-input'); if(input){e.preventDefault();e.stopImmediatePropagation();open(input,btn.dataset.mediaType||'image',btn.dataset.mediaUsage||'');} return;}
    var clear=e.target.closest&&e.target.closest('[data-media-clear]');
    if(clear){var ci=clear.closest('.field').querySelector('.media-path-input'); if(ci){e.preventDefault();e.stopImmediatePropagation();ci.value=''; var p=clear.closest('.field').querySelector('.media-field-preview'); if(p)p.innerHTML='';} return;}
    var reset=e.target.closest&&e.target.closest('[data-media-reset]');
    if(reset){e.preventDefault();e.stopImmediatePropagation();if(search)search.value=''; if(fileType)fileType.value=defaultTypeFor(activeType); if(category)category.value=activePreferred==='blog-images'?'':activePreferred; if(sort)sort.value='newest'; page=1; load(); return;}
    var sel=e.target.closest&&e.target.closest('#mediaPicker [data-media-select]');
    if(sel&&activeInput){
      e.preventDefault();e.stopImmediatePropagation();
      activeInput.value=sel.dataset.mediaPath||'';
      var alt=altInputFor(activeInput); if(alt)alt.value=sel.dataset.mediaAlt||alt.value||'';
      var preview=activeInput.closest('.field').querySelector('.media-field-preview');
      if(preview)preview.innerHTML=isImageField()?'<img src="../'+esc(activeInput.value)+'" alt="">':esc(activeInput.value);
      close(); return;
    }
    if(e.target.closest&&e.target.closest('#mediaPicker [data-media-close]')){e.preventDefault();e.stopImmediatePropagation();close(); return;}
    if(e.target.closest&&e.target.closest('[data-media-page-prev]')){e.preventDefault();e.stopImmediatePropagation();page=Math.max(1,page-1);load(); return;}
    if(e.target.closest&&e.target.closest('[data-media-page-next]')){e.preventDefault();e.stopImmediatePropagation();page++;load(); return;}
  }, true);
  search&&search.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();page=1;load();}});
  search&&search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){page=1;load();},350);});
  category&&category.addEventListener('change',function(){activePreferred='';page=1;load();});
  fileType&&fileType.addEventListener('change',function(){page=1;load();});
  sort&&sort.addEventListener('change',function(){page=1;load();});
})();
</script>
