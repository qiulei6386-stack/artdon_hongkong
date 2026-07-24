<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/artdon_product_unify_v713.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_product_center_nav.php';

if (!function_exists('cat130_h')) { function cat130_h($v): string { return function_exists('web_e') ? web_e((string)$v) : htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('cat130_int')) { function cat130_int($v, int $fallback, int $min, int $max): int { $n=(int)$v; if($n<=0)$n=$fallback; return max($min,min($max,$n)); } }
if (!function_exists('cat130_gap')) { function cat130_gap($v, int $fallback=12): int { if($v===''||$v===null)return $fallback; return max(0,min(180,(int)$v)); } }
if (!function_exists('cat130_slug')) { function cat130_slug(string $name): string { $s=trim($name); if($s==='') return ''; if(function_exists('artdon_v713_slug')) return artdon_v713_slug($s); $s=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$s)?:'', '-')); return $s!==''?$s:'category'; } }
if (!function_exists('cat130_unique_slug')) { function cat130_unique_slug(PDO $pdo, string $base): string { $base=cat130_slug($base); if($base==='') $base='category'; $slug=$base; $i=2; while(true){ $st=$pdo->prepare('SELECT COUNT(*) FROM artdon_product_categories_v713 WHERE slug=?'); $st->execute([$slug]); if((int)$st->fetchColumn()===0) return $slug; $slug=$base.'-'.$i; $i++; if($i>99) return $base.'-'.time(); } } }
if (!function_exists('cat130_is_default')) { function cat130_is_default(string $slug): bool { return in_array($slug, function_exists('artdon_v718129_canonical_slugs') ? artdon_v718129_canonical_slugs(true) : ['all'], true); } }

$dbError = null; $pdo = web_db($dbError); if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo); $user = web_require_admin($pdo); artdon_v713_ensure($pdo);

$defaults = ['title_font_size'=>64,'intro_font_size'=>20,'intro_line_height'=>28,'intro_gap'=>12];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) { $_SESSION['admin_error']='页面已过期，请刷新后重试。'; header('Location: product_categories.php'); exit; }
    $action = (string)($_POST['action'] ?? 'save');
    try {
        if ($action === 'add') {
            $name = trim((string)($_POST['new_category_name'] ?? ''));
            if ($name === '') throw new RuntimeException('请先填写新增分类名称。');
            $slug = trim((string)($_POST['new_category_slug'] ?? ''));
            $slug = $slug !== '' ? cat130_slug($slug) : cat130_slug($name);
            if ($slug === '' || $slug === 'all') throw new RuntimeException('分类 slug 不正确。');
            $slug = cat130_unique_slug($pdo, $slug);
            $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),80)+10 FROM artdon_product_categories_v713 WHERE COALESCE(is_deleted,0)=0')->fetchColumn();
            $stmt=$pdo->prepare("INSERT INTO artdon_product_categories_v713(slug,display_name,nav_label,home_tab_label,family_title,family_intro,family_title_font_size,family_intro_font_size,family_intro_line_height,family_intro_gap,page_title,seo_title,sort_order,is_active,is_deleted,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,1,0,CURRENT_TIMESTAMP)");
            $stmt->execute([$slug,$name,$name,$name,$name,'',$defaults['title_font_size'],$defaults['intro_font_size'],$defaults['intro_line_height'],$defaults['intro_gap'],$name,$name.' | Artdon Lighting',$sort]);
            try { artdon_v713_sync_native_categories($pdo); } catch (Throwable $ignore) {}
            if (function_exists('web_public_cache_clear')) web_public_cache_clear();
            web_log($pdo,(int)$user['id'],'add_product_category','artdon_product_categories_v713',$slug,['name'=>$name]);
            $_SESSION['admin_success']='已新增分类：'.$name.'。顶部菜单、Filter、系列编辑下拉会一起使用。';
            header('Location: product_categories.php'); exit;
        }
        if ($action === 'delete') {
            $slug = trim((string)($_POST['slug'] ?? ''));
            if ($slug === '') throw new RuntimeException('分类缺失。');
            $res = artdon_v713_delete_unused_category($pdo, $slug);
            if (empty($res['ok'])) throw new RuntimeException((string)($res['message'] ?? '删除失败'));
            try { artdon_v713_sync_native_categories($pdo); } catch (Throwable $ignore) {}
            if (function_exists('web_public_cache_clear')) web_public_cache_clear();
            web_log($pdo,(int)$user['id'],'delete_product_category','artdon_product_categories_v713',$slug,[]);
            $_SESSION['admin_success']=(string)($res['message'] ?? '分类已删除。');
            header('Location: product_categories.php'); exit;
        }

        $rows = (array)($_POST['catrow'] ?? []);
        $stmt = $pdo->prepare("INSERT INTO artdon_product_categories_v713(slug,display_name,nav_label,home_tab_label,family_title,family_intro,family_title_font_size,family_intro_font_size,family_intro_line_height,family_intro_gap,page_title,seo_title,sort_order,is_active,is_deleted,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),nav_label=VALUES(nav_label),home_tab_label=VALUES(home_tab_label),family_title=VALUES(family_title),family_intro=VALUES(family_intro),family_title_font_size=VALUES(family_title_font_size),family_intro_font_size=VALUES(family_intro_font_size),family_intro_line_height=VALUES(family_intro_line_height),family_intro_gap=VALUES(family_intro_gap),page_title=VALUES(page_title),seo_title=VALUES(seo_title),sort_order=VALUES(sort_order),is_active=VALUES(is_active),is_deleted=0,updated_at=CURRENT_TIMESTAMP");
        foreach ($rows as $slug => $r) {
            $slug = cat130_slug((string)$slug);
            if ($slug === '') continue;
            $r = (array)$r;
            $default = [];
            foreach (function_exists('artdon_v718129_canonical_category_rows') ? artdon_v718129_canonical_category_rows(true) : [] as $d) if ((string)$d['slug'] === $slug) $default=$d;
            $defaultName = (string)($default['display_name'] ?? ucwords(str_replace('-', ' ', $slug)));
            $isDefault = cat130_is_default($slug);
            $display = $isDefault ? $defaultName : (trim((string)($r['display_name'] ?? '')) ?: $defaultName);
            if ($slug === 'all') $display = 'All Products';
            $familyTitle = trim((string)($r['family_title'] ?? '')) ?: $display;
            $familyIntro = trim((string)($r['family_intro'] ?? ''));
            $titleFont = cat130_int($r['family_title_font_size'] ?? $defaults['title_font_size'], $defaults['title_font_size'], 24, 120);
            $introFont = cat130_int($r['family_intro_font_size'] ?? $defaults['intro_font_size'], $defaults['intro_font_size'], 10, 60);
            $introLine = cat130_int($r['family_intro_line_height'] ?? $defaults['intro_line_height'], $defaults['intro_line_height'], 12, 90);
            $introGap = cat130_gap($r['family_intro_gap'] ?? $defaults['intro_gap'], $defaults['intro_gap']);
            $pageTitle = trim((string)($r['page_title'] ?? '')) ?: $display;
            $seoTitle = trim((string)($r['seo_title'] ?? '')) ?: ($pageTitle . ' | Artdon Lighting');
            $sortOrder = isset($default['sort_order']) ? (int)$default['sort_order'] : max(1,min(999,(int)($r['sort_order'] ?? 100)));
            $active = !empty($r['is_active']) || $slug === 'all' ? 1 : 0;
            $stmt->execute([$slug,$display,$display,$display,$familyTitle,$familyIntro,$titleFont,$introFont,$introLine,$introGap,$pageTitle,$seoTitle,$sortOrder,$active]);
        }
        try { artdon_v718129_normalize_existing_product_categories($pdo); } catch (Throwable $ignore) {}
        try { artdon_v713_sync_native_categories($pdo); } catch (Throwable $ignore) {}
        if (function_exists('web_public_cache_clear')) web_public_cache_clear();
        web_log($pdo,(int)$user['id'],'save_product_categories','artdon_product_categories_v713','all',[]);
        $_SESSION['admin_success']='产品分类已保存：新增/删除、顶部菜单、左侧 Filter、产品列表分组、系列编辑下拉全部联动。';
        header('Location: product_categories.php'); exit;
    } catch (Throwable $e) { $_SESSION['admin_error']='操作失败：'.$e->getMessage(); }
}

$categories = artdon_v713_categories($pdo, false, true);
$usage = function_exists('artdon_v713_category_usage_counts') ? artdon_v713_category_usage_counts($pdo) : [];
admin_page_start('产品分类', 'product_categories', $user); admin_notice(); admin_product_center_tabs('categories');
?>
<style>
.cat130{max-width:1440px;margin:0 auto;padding-bottom:70px}.cat130-hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px 20px;margin-bottom:16px;box-shadow:0 10px 26px rgba(16,24,40,.05)}.cat130-hero h2{margin:0 0 6px;font-size:22px}.cat130-hero p{margin:0;color:#667085;line-height:1.6;font-size:13px}.cat130-order{display:flex;gap:6px;flex-wrap:wrap;max-width:860px}.cat130-chip{display:inline-flex;align-items:center;height:30px;border-radius:999px;background:#111;color:#fff;padding:0 10px;font-size:12px;font-weight:900}.cat130-rule{border:1px solid #dbe3ef;background:#f8fafc;border-radius:14px;padding:12px 14px;color:#475467;font-size:13px;line-height:1.65;margin-bottom:14px}.cat130-add{background:#fff;border:1px solid #dbe3ef;border-radius:18px;padding:14px 16px;margin-bottom:14px;box-shadow:0 8px 22px rgba(16,24,40,.04)}.cat130-add-grid{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end}.cat130-list{display:grid;gap:12px}.cat130-row{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:16px;box-shadow:0 8px 22px rgba(16,24,40,.04)}.cat130-top{display:grid;grid-template-columns:44px 260px 130px 1fr auto;gap:12px;align-items:center;margin-bottom:14px}.cat130-num{width:36px;height:36px;border-radius:999px;background:#111;color:#fff;display:grid;place-items:center;font-weight:1000}.cat130-name b{font-size:18px;display:block}.cat130-name code{font-size:12px;color:#98a2b3}.cat130-count{font-size:12px;color:#667085;font-weight:900;background:#f2f4f7;border-radius:999px;padding:7px 9px;text-align:center}.cat130-grid{display:grid;grid-template-columns:1.05fr 1.25fr 1.5fr repeat(4,112px) 92px;gap:10px;align-items:end}.cat130-field label{display:block;font-size:12px;font-weight:900;color:#475467;margin:0 0 5px}.cat130-field input,.cat130-field textarea{width:100%;border:1px solid #cfd6e2;border-radius:10px;padding:8px 10px;background:#fff;font-size:14px}.cat130-field textarea{height:76px;resize:vertical;line-height:1.45}.cat130-field input[type=number]{text-align:center}.cat130-field .readonly{background:#f8fafc;color:#667085}.cat130-help{display:block;margin-top:4px;color:#98a2b3;font-size:11px;line-height:1.35}.cat130-actions{display:flex;gap:10px;margin-top:16px;position:sticky;bottom:12px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border:1px solid #e5e7eb;border-radius:14px;padding:10px;z-index:10}.cat130-btn{height:42px;border-radius:11px;border:1px solid #111;background:#111;color:#fff;font-weight:900;padding:0 18px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}.cat130-btn.secondary{background:#fff;color:#111;border-color:#d0d5dd}.cat130-btn.danger{background:#fff1f2;color:#b42318;border-color:#fecaca}.cat130-btn[disabled]{opacity:.45;cursor:not-allowed}.cat130-active{display:flex;gap:6px;align-items:center;font-weight:900;color:#475467}.cat130-active input{width:auto}.cat130-delete-form{display:inline}@media(max-width:1260px){.cat130-top{grid-template-columns:40px 1fr}.cat130-delete-form{grid-column:1/-1}.cat130-grid,.cat130-add-grid{grid-template-columns:1fr 1fr}.cat130-hero{display:block}.cat130-order{margin-top:12px}}@media(max-width:760px){.cat130-grid,.cat130-add-grid{grid-template-columns:1fr}.cat130-actions{position:static}}
</style>
<div class="cat130">
  <section class="cat130-hero">
    <div><h2>产品分类</h2><p>这里是唯一分类源。顶部 Products 菜单、左侧 Filter、产品列表分组、系列编辑下拉全部从这里读取。默认分类按菜单顺序固定，自定义分类自动追加并联动。</p></div>
    <div class="cat130-order"><?php foreach ($categories as $c): if (($c['slug']??'')==='all') continue; ?><span class="cat130-chip"><?= cat130_h($c['display_name'] ?? $c['name'] ?? '') ?></span><?php endforeach; ?></div>
  </section>
  <div class="cat130-rule"><b>保存不会回填默认值：</b>当前是多少 px，后台就显示多少 px；改多少就保存多少。删除只允许删除“0 个产品/系列引用”的分类，避免产品断分类。</div>

  <form class="cat130-add" method="post">
    <input type="hidden" name="csrf" value="<?= cat130_h(web_csrf_token()) ?>"><input type="hidden" name="action" value="add">
    <div class="cat130-add-grid">
      <div class="cat130-field"><label>新增分类名称</label><input name="new_category_name" placeholder="例如：Decorative Lighting"></div>
      <div class="cat130-field"><label>URL Slug（可留空自动生成）</label><input name="new_category_slug" placeholder="decorative-lighting"></div>
      <button class="cat130-btn" type="submit">新增分类</button>
    </div>
  </form>

  <form method="post"><input type="hidden" name="csrf" value="<?= cat130_h(web_csrf_token()) ?>"><input type="hidden" name="action" value="save">
    <div class="cat130-list">
    <?php foreach ($categories as $idx=>$c): $slug=(string)($c['slug']??''); $used=(int)($usage[$slug]??0); $isDefault=cat130_is_default($slug); $titlePx=cat130_int($c['family_title_font_size']??64,64,24,120); $introPx=cat130_int($c['family_intro_font_size']??20,20,10,60); $linePx=cat130_int($c['family_intro_line_height']??28,28,12,90); $gapPx=cat130_gap($c['family_intro_gap']??12,12); ?>
      <section class="cat130-row">
        <div class="cat130-top">
          <div class="cat130-num"><?= $idx+1 ?></div>
          <div class="cat130-name"><b><?= cat130_h($c['display_name'] ?? $c['name'] ?? '') ?></b><code><?= cat130_h($slug) ?><?= $isDefault?' · 默认':' · 自定义' ?></code></div>
          <div class="cat130-count">系列/产品：<?= $used ?></div>
          <div class="cat130-help">有系列的地方全部联动：菜单 / Filter / 分组 / 系列编辑。</div>
          <?php if ($slug !== 'all'): ?><button class="cat130-btn danger" type="submit" form="delete_<?= cat130_h($slug) ?>" <?= $used>0?'disabled title="还有产品/系列引用，不能删除"':'' ?>>删除分类</button><?php endif; ?>
        </div>
        <div class="cat130-grid">
          <div class="cat130-field"><label>分类名称</label><input class="<?= $isDefault?'readonly':'' ?>" name="catrow[<?= cat130_h($slug) ?>][display_name]" value="<?= cat130_h($c['display_name'] ?? $c['name'] ?? '') ?>" <?= $isDefault?'readonly':'' ?>><span class="cat130-help"><?= $isDefault?'默认分类名称按菜单固定':'自定义分类可改名' ?></span></div>
          <div class="cat130-field"><label>系列页标题</label><input name="catrow[<?= cat130_h($slug) ?>][family_title]" value="<?= cat130_h($c['family_title'] ?? ($c['display_name'] ?? $c['name'] ?? '')) ?>"></div>
          <div class="cat130-field"><label>系列页说明</label><textarea name="catrow[<?= cat130_h($slug) ?>][family_intro]" placeholder="留空不显示"><?= cat130_h($c['family_intro'] ?? '') ?></textarea></div>
          <div class="cat130-field"><label>标题字号 px</label><input type="number" min="24" max="120" name="catrow[<?= cat130_h($slug) ?>][family_title_font_size]" value="<?= $titlePx ?>"></div>
          <div class="cat130-field"><label>说明字号 px</label><input type="number" min="10" max="60" name="catrow[<?= cat130_h($slug) ?>][family_intro_font_size]" value="<?= $introPx ?>"></div>
          <div class="cat130-field"><label>说明行高 px</label><input type="number" min="12" max="90" name="catrow[<?= cat130_h($slug) ?>][family_intro_line_height]" value="<?= $linePx ?>"></div>
          <div class="cat130-field"><label>到卡片间距 px</label><input type="number" min="0" max="180" name="catrow[<?= cat130_h($slug) ?>][family_intro_gap]" value="<?= $gapPx ?>"></div>
          <div class="cat130-field"><label>启用</label><label class="cat130-active"><input type="checkbox" name="catrow[<?= cat130_h($slug) ?>][is_active]" value="1" <?= ((int)($c['is_active']??1)===1 || $slug==='all')?'checked':'' ?> <?= $slug==='all'?'disabled':'' ?>> 显示</label></div>
          <input type="hidden" name="catrow[<?= cat130_h($slug) ?>][sort_order]" value="<?= (int)($c['sort_order'] ?? (($idx+1)*10)) ?>">
          <input type="hidden" name="catrow[<?= cat130_h($slug) ?>][page_title]" value="<?= cat130_h($c['page_title'] ?? ($c['display_name'] ?? $c['name'] ?? '')) ?>">
          <input type="hidden" name="catrow[<?= cat130_h($slug) ?>][seo_title]" value="<?= cat130_h($c['seo_title'] ?? (($c['display_name'] ?? $c['name'] ?? '').' | Artdon Lighting')) ?>">
        </div>
      </section>
    <?php endforeach; ?>
    </div>
    <div class="cat130-actions"><button class="cat130-btn" type="submit">保存分类设置</button><a class="cat130-btn secondary" href="../products.php" target="_blank">查看前台产品页 ↗</a></div>
  </form>
  <?php foreach ($categories as $c): $slug=(string)($c['slug']??''); if($slug===''||$slug==='all') continue; ?><form id="delete_<?= cat130_h($slug) ?>" class="cat130-delete-form" method="post" onsubmit="return confirm('确认删除这个空分类？只允许删除 0 引用分类。');"><input type="hidden" name="csrf" value="<?= cat130_h(web_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?= cat130_h($slug) ?>"></form><?php endforeach; ?>
</div>
<?php admin_page_end(); ?>
