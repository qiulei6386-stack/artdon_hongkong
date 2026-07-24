<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/upload.php';

/*
 * Artdon HK Website Admin V7.1.8.135
 * Media picker transparent/product image visible fix.
 * The picker no longer relies only on web_media.media_type / usage_category.
 * V7.1.8.135 fixes transparent/white product PNG previews by forcing a gray checkerboard preview background,
 * so 产品图片 / 尺寸图 / 配光曲线 will be visible like 项目案例 / 首页轮播.
 */

if (!function_exists('admin_media_picker_starts_v718135')) {
    function admin_media_picker_starts_v718135(string $s, string $prefix): bool
    {
        return $prefix === '' || strncmp($s, $prefix, strlen($prefix)) === 0;
    }
}

if (!function_exists('admin_media_picker_path_v718135')) {
    function admin_media_picker_path_v718135(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') return '';
        if (preg_match('#^https?://#i', $path)) {
            $u = parse_url($path);
            $p = is_array($u) ? (string)($u['path'] ?? '') : '';
            if ($p !== '') $path = $p;
        }
        $path = rawurldecode($path);
        $path = preg_replace('#/+#', '/', $path) ?: $path;
        $path = ltrim($path, '/');
        while (admin_media_picker_starts_v718135($path, '../')) $path = substr($path, 3);
        return $path;
    }
}

if (!function_exists('admin_media_picker_ext_type_v718135')) {
    function admin_media_picker_ext_type_v718135(string $path, string $mediaType = ''): string
    {
        $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg','avif','bmp'], true)) return 'image';
        if (in_array($ext, ['mp4','webm','mov','m4v'], true)) return 'video';
        $t = strtolower(trim($mediaType));
        if (in_array($t, ['image','img','images','photo','picture','pic','product_image','product-images','productimage'], true)) return 'image';
        if (in_array($t, ['video','videos','movie'], true)) return 'video';
        return $t !== '' ? $t : 'file';
    }
}

if (!function_exists('admin_media_picker_public_url_v718135')) {
    function admin_media_picker_public_url_v718135(string $path): string
    {
        $raw = trim(str_replace('\\', '/', $path));
        if ($raw === '') return '';
        if (preg_match('#^(?:https?:)?//#i', $raw) || admin_media_picker_starts_v718135($raw, 'data:') || admin_media_picker_starts_v718135($raw, 'blob:')) return $raw;
        $clean = admin_media_picker_path_v718135($raw);
        if ($clean === '') return '';
        return '../' . ltrim($clean, '/');
    }
}

if (!function_exists('admin_media_picker_fallback_url_v718135')) {
    function admin_media_picker_fallback_url_v718135(string $path): string
    {
        $clean = admin_media_picker_path_v718135($path);
        return $clean !== '' ? '/' . ltrim($clean, '/') : '';
    }
}

if (!function_exists('admin_media_picker_thumb_url_v718148')) {
    function admin_media_picker_thumb_url_v718148(string $path): string
    {
        $clean = admin_media_picker_path_v718135($path);
        if ($clean === '') return '';
        $abs = dirname(__DIR__) . '/' . $clean;
        $mtime = is_file($abs) ? (int)@filemtime($abs) : 0;
        return '_media_thumb.php?path=' . rawurlencode($clean) . ($mtime > 0 ? '&v=' . $mtime : '');
    }
}

if (!function_exists('admin_media_picker_abs_v718135')) {
    function admin_media_picker_abs_v718135(string $path): string
    {
        $clean = admin_media_picker_path_v718135($path);
        if ($clean === '') return '';
        return dirname(__DIR__) . '/' . $clean;
    }
}

if (!function_exists('admin_media_picker_usage_aliases_v718135')) {
    function admin_media_picker_usage_aliases_v718135(string $usage, string $path, string $type): string
    {
        $usage = trim($usage);
        $aliases = [];
        if ($usage !== '') $aliases[] = $usage;
        $pathUsage = function_exists('web_media_infer_usage_from_path') ? web_media_infer_usage_from_path($path, $type === 'video' ? 'video' : ($type === 'file' ? 'file' : 'image')) : '';
        if ($pathUsage !== '') $aliases[] = $pathUsage;
        if ($type === 'image') {
            $aliases[] = 'images';
            $aliases[] = 'products';
            if (stripos($path, 'products/') !== false) $aliases[] = 'products';
            if (stripos($path, 'banners/') !== false) $aliases[] = 'banners';
            if (stripos($path, 'projects/') !== false) $aliases[] = 'projects';
        }
        if ($type === 'file') $aliases[] = 'downloads';
        if ($type === 'video') $aliases[] = 'videos';
        return implode(',', array_values(array_unique(array_filter($aliases))));
    }
}

if (!function_exists('admin_media_picker_filesystem_rows_v718135')) {
    function admin_media_picker_filesystem_rows_v718135(array $seen, int $limit = 700): array
    {
        $roots = [
            dirname(__DIR__) . '/uploads/website/products',
            dirname(__DIR__) . '/uploads/website',
            dirname(__DIR__) . '/uploads',
        ];
        $items = [];
        $imageExt = ['jpg'=>1,'jpeg'=>1,'png'=>1,'webp'=>1,'gif'=>1,'svg'=>1,'avif'=>1,'bmp'=>1];
        foreach ($roots as $root) {
            if (!is_dir($root)) continue;
            try {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if (!$file instanceof SplFileInfo || !$file->isFile()) continue;
                    $ext = strtolower($file->getExtension());
                    if (!isset($imageExt[$ext])) continue;
                    $abs = str_replace('\\', '/', $file->getPathname());
                    $base = str_replace('\\', '/', dirname(__DIR__) . '/');
                    if (strpos($abs, $base) !== 0) continue;
                    $rel = substr($abs, strlen($base));
                    $rel = admin_media_picker_path_v718135($rel);
                    if ($rel === '' || isset($seen[$rel])) continue;
                    $seen[$rel] = 1;
                    $items[] = [
                        'id' => 0,
                        'media_type' => 'image',
                        'usage_category' => function_exists('web_media_infer_usage_from_path') ? web_media_infer_usage_from_path($rel, 'image') : 'products',
                        'title' => basename($rel),
                        'file_path' => $rel,
                        'created_at' => date('Y-m-d H:i:s', @filemtime($abs) ?: time()),
                        '_mtime' => @filemtime($abs) ?: 0,
                        '_source' => 'filesystem',
                    ];
                    if (count($items) >= $limit) break 2;
                }
            } catch (Throwable $e) {}
        }
        usort($items, static fn($a, $b): int => (int)($b['_mtime'] ?? 0) <=> (int)($a['_mtime'] ?? 0));
        return $items;
    }
}

if (!function_exists('admin_media_picker_rows_v718135')) {
    function admin_media_picker_rows_v718135(PDO $pdo): array
    {
        $rows = [];
        $seen = [];
        try {
            $tableRows = $pdo->query('SELECT * FROM web_media ORDER BY id DESC LIMIT 900')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $tableRows = [];
        }
        foreach ($tableRows as $row) {
            $path = admin_media_picker_path_v718135((string)($row['file_path'] ?? ($row['path'] ?? ($row['url'] ?? ''))));
            if ($path === '') continue;
            $row['file_path'] = $path;
            $row['media_type'] = admin_media_picker_ext_type_v718135($path, (string)($row['media_type'] ?? ''));
            if (trim((string)($row['usage_category'] ?? '')) === '' && function_exists('web_media_infer_usage_from_path')) {
                $row['usage_category'] = web_media_infer_usage_from_path($path, (string)$row['media_type']);
            }
            $seen[$path] = 1;
            $rows[] = $row;
        }
        foreach (admin_media_picker_filesystem_rows_v718135($seen) as $row) $rows[] = $row;
        return array_slice($rows, 0, 1000);
    }
}

function admin_render_media_picker(PDO $pdo): void
{
    $rows = [];
    ?>
    <div class="media-picker" id="mediaPicker" aria-hidden="true" data-version="v718135">
      <div class="media-picker-backdrop" data-media-close></div>
      <section class="media-picker-dialog" role="dialog" aria-modal="true" aria-labelledby="mediaPickerTitle">
        <header><div><h2 id="mediaPickerTitle">从媒体资料库选择</h2><p>分页读取媒体库，每页 20 张；支持搜索标题、ALT、文件名和路径。</p></div><button type="button" class="media-picker-close" data-media-close aria-label="关闭">×</button></header>
        <div class="media-picker-filters">
          <input type="search" id="mediaPickerSearch" placeholder="搜索标题 / 文件名 / ALT / 路径">
          <select id="mediaPickerUsage" aria-label="用途分类"><option value="">当前分类</option><option value="__all">全部分类</option><?php foreach(web_media_usage_map() as $key=>$cfg): ?><option value="<?= web_e($key) ?>"><?= web_e($cfg['label']) ?></option><?php endforeach; ?></select>
          <select id="mediaPickerType" aria-label="文件类型"><option value="image">图片</option><option value="video">视频</option><option value="file">PDF / 文档</option><option value="__all">全部类型</option></select>
          <button type="button" class="admin-button-secondary" data-media-filter-reset>重置筛选</button>
        </div>
        <div class="media-picker-status" id="mediaPickerStatus">打开后加载媒体库。</div>
        <div class="media-picker-grid" id="mediaPickerGrid">
          <?php foreach($rows as $row):
              $filePath = admin_media_picker_path_v718135((string)($row['file_path'] ?? ''));
              if ($filePath === '') continue;
              $mediaType = admin_media_picker_ext_type_v718135($filePath, (string)($row['media_type'] ?? ''));
              $usage = trim((string)($row['usage_category'] ?? ''));
              if ($usage === '' && function_exists('web_media_infer_usage_from_path')) $usage = web_media_infer_usage_from_path($filePath, $mediaType);
              $preview = $mediaType === 'image' ? admin_media_picker_thumb_url_v718148($filePath) : admin_media_picker_public_url_v718135($filePath);
              $fallback = admin_media_picker_fallback_url_v718135($filePath);
              $aliases = admin_media_picker_usage_aliases_v718135($usage, $filePath, $mediaType);
              $title = trim((string)($row['title'] ?? '')) ?: basename($filePath);
              $alt = trim((string)($row['alt_text'] ?? ''));
              $search = strtolower($title . ' ' . $alt . ' ' . basename($filePath) . ' ' . $filePath . ' ' . $usage . ' ' . $aliases);
              $exists = is_file(admin_media_picker_abs_v718135($filePath)) ? '1' : '0';
          ?>
          <article class="media-picker-card" data-media-type="<?= web_e($mediaType) ?>" data-media-usage="<?= web_e($usage) ?>" data-media-usages="<?= web_e($aliases) ?>" data-media-search="<?= web_e($search) ?>" data-media-exists="<?= web_e($exists) ?>">
            <button type="button" class="media-picker-select" data-media-select data-media-path="<?= web_e($filePath) ?>" data-media-id="<?= (int)($row['id'] ?? 0) ?>" data-media-type="<?= web_e($mediaType) ?>" data-media-usage="<?= web_e($usage) ?>" data-media-alt="<?= web_e($alt) ?>">
              <span class="media-picker-preview">
                <?php if($mediaType==='image'): ?>
                  <img src="<?= web_e($preview) ?>" alt="" loading="eager" decoding="sync" data-fallback-src="<?= web_e($fallback) ?>" data-original-src="<?= web_e($preview) ?>" onerror="if(this.dataset.fallbackSrc&&this.src.indexOf(this.dataset.fallbackSrc)===-1){this.src=this.dataset.fallbackSrc;}else{this.closest('.media-picker-preview')&&this.closest('.media-picker-preview').classList.add('is-broken');}">
                <?php elseif($mediaType==='video'): ?>
                  <video src="<?= web_e($preview) ?>" muted preload="metadata"></video><b>视频</b>
                <?php else: ?>
                  <b><?= web_e(strtoupper(pathinfo($filePath,PATHINFO_EXTENSION) ?: 'FILE')) ?></b>
                <?php endif; ?>
              </span>
              <strong><?= web_e($title) ?></strong><small><?= web_e(function_exists('web_media_usage_label') ? web_media_usage_label($usage) : $usage) ?><?= !empty($row['_source']) ? ' · 文件扫描' : '' ?></small>
            </button>
            <?php if($mediaType==='image'): ?>
              <div class="media-picker-card-tools"><button type="button" data-media-crop-existing data-media-id="<?= (int)($row['id'] ?? 0) ?>" data-media-path="<?= web_e($filePath) ?>" data-media-usage="<?= web_e($usage !== '' ? $usage : 'products') ?>">裁切后使用</button></div>
            <?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
        <div class="media-picker-pager" id="mediaPickerPager"><button type="button" class="admin-button-secondary" data-media-page-prev>上一页</button><span data-media-page-info>第 1 / 1 页</span><button type="button" class="admin-button-secondary" data-media-page-next>下一页</button></div>
        <div class="media-picker-empty" hidden>没有符合条件的媒体文件。</div>
      </section>
    </div>
    <style>
    /* ARTDON_V718135_MEDIA_PICKER_HARD_VISIBLE_START */
    #mediaPicker .media-picker-grid{align-content:start!important;grid-auto-rows:auto!important;background:#fff!important;min-height:260px!important;}
    #mediaPicker .media-picker-card[hidden]{display:none!important;}
    #mediaPicker .media-picker-card{display:grid!important;grid-template-rows:auto auto!important;min-height:0!important;background:#fff!important;overflow:hidden!important;border:1px solid var(--line,#e5e7eb)!important;border-radius:9px!important;}
    #mediaPicker .media-picker-select{display:block!important;width:100%!important;border:0!important;background:#fff!important;padding:0!important;text-align:left!important;color:inherit!important;cursor:pointer!important;min-height:0!important;}
    #mediaPicker .media-picker-preview{display:flex!important;align-items:center!important;justify-content:center!important;width:100%!important;aspect-ratio:4/3!important;min-height:120px!important;background-color:#d1d4d5!important;background-image:linear-gradient(45deg,rgba(255,255,255,.28) 25%,transparent 25%),linear-gradient(-45deg,rgba(255,255,255,.28) 25%,transparent 25%),linear-gradient(45deg,transparent 75%,rgba(255,255,255,.28) 75%),linear-gradient(-45deg,transparent 75%,rgba(255,255,255,.28) 75%)!important;background-size:18px 18px!important;background-position:0 0,0 9px,9px -9px,-9px 0!important;overflow:hidden!important;border-bottom:1px solid var(--line,#e5e7eb)!important;position:relative!important;}
    #mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{display:block!important;width:100%!important;height:100%!important;object-fit:contain!important;object-position:center center!important;background:transparent!important;opacity:1!important;visibility:visible!important;}
    #mediaPicker .media-picker-card[data-media-usage="projects"] .media-picker-preview,
    #mediaPicker .media-picker-card[data-media-usage="banners"] .media-picker-preview,
    #mediaPicker .media-picker-card[data-media-usage="homepage"] .media-picker-preview{background:#f2f3f4!important;background-image:none!important;}
    #mediaPicker .media-picker-select>strong{color:#111!important;font-weight:900!important;line-height:1.3!important;margin-top:8px!important;}
    #mediaPicker .media-picker-select>small{color:#64748b!important;line-height:1.3!important;margin-bottom:8px!important;}
    #mediaPicker .media-picker-preview.is-broken::after{content:'图片无法读取';position:absolute;inset:0;display:grid;place-items:center;color:#9ca3af;font-size:12px;font-weight:800;background:#f8fafc;}
    #mediaPicker .media-picker-select>strong,#mediaPicker .media-picker-select>small{display:block!important;min-height:16px!important;padding-left:10px!important;padding-right:10px!important;}
    #mediaPicker .media-picker-card-tools{display:flex!important;border-top:1px solid var(--line,#e5e7eb)!important;background:#fafafa!important;}
    #mediaPicker .media-picker-debug{position:sticky;top:0;z-index:3;grid-column:1/-1;padding:8px 10px;border:1px dashed #cbd5e1;background:#f8fafc;color:#64748b;font-size:12px;font-weight:800;border-radius:8px;}
    #mediaPicker .media-picker-status{padding:9px 22px;background:#f7f7f7;color:#666;font-size:12px;font-weight:800;border-bottom:1px solid var(--line,#e5e7eb);}
    #mediaPicker .media-picker-filters{display:grid!important;grid-template-columns:minmax(260px,1fr) 180px 140px auto!important;gap:10px!important;align-items:center!important;padding:12px 22px!important;border-bottom:1px solid var(--line,#e5e7eb)!important;background:#fff!important;}
    #mediaPicker .media-picker-filters input,#mediaPicker .media-picker-filters select{height:40px!important;min-width:0!important;border:1px solid #ddd!important;border-radius:6px!important;padding:0 12px!important;background:#fff!important;font:inherit!important;font-size:13px!important;}
    #mediaPicker .media-picker-pager{display:flex;align-items:center;justify-content:center;gap:14px;padding:14px 22px;border-top:1px solid var(--line,#e5e7eb);background:#fff;}
    #mediaPicker .media-picker-pager span{min-width:150px;text-align:center;font-weight:800;color:#555;}
    #mediaPicker .media-picker-dialog{inset:auto!important;left:50%!important;top:50%!important;transform:translate(-50%,-50%)!important;width:min(1480px,96vw)!important;height:min(900px,94vh)!important;max-height:94vh!important;grid-template-rows:auto auto auto minmax(420px,1fr) auto!important;}
    #mediaPicker .media-picker-grid{grid-template-columns:repeat(auto-fill,minmax(320px,1fr))!important;gap:18px!important;max-height:none!important;overflow:auto!important;padding:22px!important;}
    #mediaPicker .media-picker-card{min-height:286px!important;}
    #mediaPicker .media-picker-preview{height:230px!important;min-height:230px!important;max-height:230px!important;aspect-ratio:auto!important;background:#f2f3f4!important;background-image:none!important;}
    #mediaPicker .media-picker-preview img,#mediaPicker .media-picker-preview video{object-fit:cover!important;background:#fff!important;}
    @media(max-width:760px){#mediaPicker .media-picker-dialog{width:96vw!important;height:92vh!important}#mediaPicker .media-picker-filters{grid-template-columns:1fr 1fr!important;padding:12px!important}#mediaPicker .media-picker-filters input{grid-column:1/-1!important}#mediaPicker .media-picker-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;padding:12px!important;gap:12px!important}#mediaPicker .media-picker-card{min-height:210px!important}#mediaPicker .media-picker-preview{height:150px!important;min-height:150px!important;max-height:150px!important}}
    /* ARTDON_V718135_MEDIA_PICKER_HARD_VISIBLE_END */
    </style>
    <script>
    (function(){
      var picker = document.getElementById('mediaPicker');
      if(!picker || picker.dataset.hardVisibleV718135 === '1') return;
      picker.dataset.hardVisibleV718135 = '1';
      function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
      function isImagePath(path){ return /\.(?:jpe?g|png|webp|gif|svg|avif|bmp)(?:\?|#|$)/i.test(path || ''); }
      function normalizeCard(card){
        if(!card) return;
        var btn = card.querySelector('[data-media-select]');
        var path = btn ? (btn.getAttribute('data-media-path') || '') : '';
        if(isImagePath(path) || card.getAttribute('data-media-type') === 'img' || card.getAttribute('data-media-type') === 'images') {
          card.dataset.mediaType = 'image';
          if(btn) btn.dataset.mediaType = 'image';
        }
        var img = card.querySelector('.media-picker-preview img');
        if(img){
          img.removeAttribute('loading');
          img.loading = 'eager';
          var src = img.getAttribute('src') || img.dataset.originalSrc || '';
          if(src && img.getAttribute('src') !== src) img.setAttribute('src', src);
          img.style.opacity = '1';
          img.style.visibility = 'visible';
        }
      }
      function currentType(){ return window.artdonActiveMediaType || ''; }
      function currentUsage(){ return window.artdonActiveMediaUsage || ''; }
      function cardUsageMatch(card, wanted){
        if(!wanted) return true;
        var usage = card.dataset.mediaUsage || '';
        var aliases = (card.dataset.mediaUsages || usage || '').split(',').map(function(v){return v.trim();}).filter(Boolean);
        if(usage === wanted || aliases.indexOf(wanted) >= 0) return true;
        if(currentType() === 'image' && wanted === 'products' && (usage === '' || usage === 'images' || aliases.indexOf('images') >= 0 || aliases.indexOf('products') >= 0)) return true;
        return false;
      }
      function hardFilter(){
        var cards = qsa('.media-picker-card', picker);
        cards.forEach(normalizeCard);
        var termInput = document.getElementById('mediaPickerSearch');
        var usageSelect = document.getElementById('mediaPickerUsage');
        var term = (termInput && termInput.value || '').trim().toLowerCase();
        var chosenUsage = usageSelect && usageSelect.value || '';
        var typeWanted = currentType();
        var usageWanted = chosenUsage || currentUsage();
        var visible = 0;
        cards.forEach(function(card){
          var typeOk = !typeWanted || card.dataset.mediaType === typeWanted || (typeWanted === 'image' && card.dataset.mediaType === 'image');
          var usageOk = cardUsageMatch(card, usageWanted);
          var searchOk = !term || (card.dataset.mediaSearch || '').indexOf(term) >= 0;
          var show = typeOk && usageOk && searchOk;
          card.hidden = !show;
          if(show) visible++;
        });
        if(!visible && typeWanted === 'image' && !term){
          cards.forEach(function(card){
            var show = card.dataset.mediaType === 'image';
            card.hidden = !show;
            if(show) visible++;
          });
        }
        var empty = picker.querySelector('.media-picker-empty');
        if(empty) empty.hidden = visible !== 0;
        var grid = document.getElementById('mediaPickerGrid');
        var debug = picker.querySelector('.media-picker-debug');
        if(grid && !debug){
          debug = document.createElement('div');
          debug.className = 'media-picker-debug';
          grid.insertBefore(debug, grid.firstChild);
        }
        if(debug){
          var totalImages = cards.filter(function(c){return c.dataset.mediaType === 'image';}).length;
          debug.textContent = '媒体库图片：' + totalImages + '，当前显示：' + visible + '。白色/透明产品图已使用灰底显示；如仍空白，请切换右上角为“全部图片”。';
        }
      }
      document.addEventListener('click', function(e){
        if(e.target && e.target.closest && e.target.closest('[data-media-open]')){
          setTimeout(function(){
            var usageSelect = document.getElementById('mediaPickerUsage');
            // Use all images by default. Product fields can still choose 产品图片 manually.
            if(usageSelect) usageSelect.value = '';
            hardFilter();
          }, 80);
          setTimeout(hardFilter, 250);
        }
      }, false);
      document.addEventListener('input', function(e){ if(e.target && e.target.id === 'mediaPickerSearch') setTimeout(hardFilter, 0); }, true);
      document.addEventListener('change', function(e){ if(e.target && e.target.id === 'mediaPickerUsage') setTimeout(hardFilter, 0); }, true);
      if(picker.classList.contains('is-open')) setTimeout(hardFilter, 0);
    })();
    </script>
    <script>
    (function(){
      var picker=document.getElementById('mediaPicker'), grid=document.getElementById('mediaPickerGrid'), status=document.getElementById('mediaPickerStatus'), search=document.getElementById('mediaPickerSearch'), usage=document.getElementById('mediaPickerUsage'), typeSelect=document.getElementById('mediaPickerType'), pager=document.getElementById('mediaPickerPager');
      if(!picker||picker.dataset.lazyV20==='1')return; picker.dataset.lazyV20='1';
      var page=1, perPage=20, timer=0, loading=false;
      function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
      function activeType(){var v=typeSelect&&typeSelect.value||''; return v==='__all'?'':(v||window.artdonActiveMediaType||'image');}
      function activeUsage(){var v=usage&&usage.value||''; return v==='__all'?'':(v||window.artdonActiveMediaUsage||'');}
      function emptyNode(){return picker ? picker.querySelector('.media-picker-empty') : null;}
      function setEmptyVisible(show){
        var empty=emptyNode();
        if(empty) empty.hidden=!show;
      }
      function render(items){
        items=items||[];
        setEmptyVisible(!items.length);
        grid.innerHTML=items.map(function(item){
          var type=item.type||'image', thumb=item.thumb||item.path||'', label=item.title||item.basename||item.path||'', usage=item.usage||'', aliases=item.aliases||usage||'', alt=item.alt||label||'';
          var original=(item.path||'').match(/^(?:https?:)?\/\//i)?(item.path||''):'../'+String(item.path||'').replace(/^\/+/,'');
          var preview=type==='image'?'<img src="'+esc(thumb||original)+'" alt="" loading="lazy">':(type==='video'?'<video src="'+esc(thumb||item.path)+'" muted preload="metadata"></video><b>视频</b>':'<b>'+esc((item.ext||'file').toUpperCase())+'</b>');
          return '<article class="media-picker-card" data-media-type="'+esc(type)+'" data-media-usage="'+esc(usage)+'" data-media-usages="'+esc(aliases)+'" data-media-search="'+esc([label,alt,item.basename,item.path,usage,aliases].join(' ').toLowerCase())+'"><button type="button" class="media-picker-select" data-media-select data-media-path="'+esc(item.path||'')+'" data-media-id="'+esc(item.id||0)+'" data-media-type="'+esc(type)+'" data-media-usage="'+esc(usage)+'" data-media-alt="'+esc(alt)+'"><span class="media-picker-preview">'+preview+'</span><strong>'+esc(label)+'</strong><small>'+esc(item.path||'')+'</small></button></article>';
        }).join('');
      }
      function load(){
        if(!picker.classList.contains('is-open')||loading)return;
        loading=true;
        var params=new URLSearchParams({type:activeType()||'image',usage:activeUsage(),q:(search&&search.value||'').trim(),page:String(page),per_page:String(perPage)});
        setEmptyVisible(false);
        status.textContent='正在读取媒体库，每页只加载 20 张...'; grid.innerHTML='<div style="grid-column:1/-1;padding:30px;text-align:center;color:#777">加载中...</div>';
        fetch('_media_picker_lazy.php?'+params.toString(),{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(function(data){
          render(data.items||[]);
          status.textContent='本页 '+((data.items||[]).length)+' 张，共 '+(data.total||0)+' 张。';
          if(pager){pager.querySelector('[data-media-page-info]').textContent='第 '+(data.page||page)+' / '+(data.pages||1)+' 页';pager.querySelector('[data-media-page-prev]').disabled=!data.has_prev;pager.querySelector('[data-media-page-next]').disabled=!data.has_next;}
        }).catch(function(){status.textContent='媒体库读取失败，请刷新后台或检查 _media_picker_lazy.php。';grid.innerHTML='';setEmptyVisible(true);}).finally(function(){loading=false;});
      }
      document.addEventListener('click',function(e){
        if(e.target.closest&&e.target.closest('[data-media-open]')){var btn=e.target.closest('[data-media-open]'); if(typeSelect) typeSelect.value=(btn.dataset.mediaType==='video'?'video':(btn.dataset.mediaType==='file'?'file':'image')); page=1;setTimeout(load,80);return;}
        if(e.target.closest&&e.target.closest('[data-media-filter-reset]')){if(search)search.value=''; if(usage)usage.value=''; if(typeSelect)typeSelect.value=window.artdonActiveMediaType==='file'?'file':(window.artdonActiveMediaType==='video'?'video':'image'); page=1; load(); return;}
        if(e.target.closest&&e.target.closest('[data-media-page-prev]')){page=Math.max(1,page-1);load();return;}
        if(e.target.closest&&e.target.closest('[data-media-page-next]')){page++;load();return;}
      },true);
      search&&search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){page=1;load();},250);});
      usage&&usage.addEventListener('change',function(){page=1;load();});
      typeSelect&&typeSelect.addEventListener('change',function(){page=1;load();});
    })();
    </script>
    <?php
}
