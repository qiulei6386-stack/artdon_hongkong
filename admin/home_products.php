<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/artdon_product_unify_v713.php';

if (!function_exists('hp_h')) {
    function hp_h($v): string {
        return function_exists('web_e') ? web_e((string)$v) : htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
function hp_csrf(): string { return function_exists('web_csrf_token') ? web_csrf_token() : ''; }
function hp_redirect(array $keep = []): void {
    $keys = ['q','type','cat','limit','dashq','board'];
    $q = [];
    foreach ($keys as $k) {
        $v = $keep[$k] ?? ($_REQUEST[$k] ?? '');
        if ($v !== '' && $v !== null) $q[$k] = (string)$v;
    }
    header('Location: home_products.php' . ($q ? ('?' . http_build_query($q)) : ''));
    exit;
}
function hp_notice(): void {
    if (!empty($_SESSION['admin_success'])) {
        echo '<div class="hp-notice ok"><b>操作成功</b><span>'.hp_h((string)$_SESSION['admin_success']).'</span></div>';
        unset($_SESSION['admin_success']);
    }
    if (!empty($_SESSION['admin_error'])) {
        echo '<div class="hp-notice err"><b>操作失败</b><span>'.hp_h((string)$_SESSION['admin_error']).'</span></div>';
        unset($_SESSION['admin_error']);
    }
}

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
artdon_v713_ensure($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        hp_redirect();
    }
    try {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_slot') {
            $id = (int)($_POST['slot_id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('首页绑定不存在。');
            $pdo->prepare('UPDATE artdon_home_product_slots_v713 SET sort_order=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([(int)($_POST['sort_order'] ?? 100), empty($_POST['is_active']) ? 0 : 1, $id]);
            if (function_exists('web_log')) web_log($pdo, (int)$user['id'], 'update_home_product_slot', 'artdon_home_product_slots_v713', (string)$id);
            $_SESSION['admin_success'] = '首页绑定已保存。';
            hp_redirect(['dashq'=>$_POST['keep_dashq'] ?? '', 'board'=>$_POST['keep_board'] ?? '']);
        }

        if ($action === 'delete_slot') {
            $id = (int)($_POST['slot_id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('首页绑定不存在。');
            $pdo->prepare('DELETE FROM artdon_home_product_slots_v713 WHERE id=?')->execute([$id]);
            if (function_exists('web_log')) web_log($pdo, (int)$user['id'], 'delete_home_product_slot', 'artdon_home_product_slots_v713', (string)$id);
            $_SESSION['admin_success'] = '已删除这个首页板块绑定，不会删除产品本身。';
            hp_redirect(['dashq'=>$_POST['keep_dashq'] ?? '', 'board'=>$_POST['keep_board'] ?? '']);
        }

        if ($action === 'save_item_boards') {
            $itemType = (string)($_POST['item_type'] ?? 'series');
            if (!in_array($itemType, ['series','product'], true)) $itemType = 'series';
            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($itemId <= 0) throw new RuntimeException('产品来源无效。');
            $slug = trim((string)($_POST['item_slug'] ?? '')) ?: (string)$itemId;
            $name = trim((string)($_POST['item_name'] ?? '')) ?: ucfirst($itemType) . ' ' . $itemId;
            $categorySlug = trim((string)($_POST['category_slug'] ?? ''));
            $sort = (int)($_POST['sort_order'] ?? 100);
            $boards = array_values(array_unique(array_filter(array_map('strval', (array)($_POST['boards'] ?? [])))));
            $activeBoardKeys = [];
            foreach (artdon_v713_categories($pdo, true) as $c) $activeBoardKeys[(string)$c['slug']] = true;
            $boards = array_values(array_filter($boards, static fn($b) => isset($activeBoardKeys[$b])));

            $pdo->beginTransaction();
            if ($boards) {
                $marks = implode(',', array_fill(0, count($boards), '?'));
                $vals = array_merge([$itemType, $itemId], $boards);
                $pdo->prepare("DELETE FROM artdon_home_product_slots_v713 WHERE item_type=? AND item_id=? AND board_key NOT IN ($marks)")->execute($vals);
                foreach ($boards as $board) {
                    artdon_v713_save_slot($pdo, $board, $itemType, $itemId, $slug, $name, $categorySlug, $sort, 1);
                }
            } else {
                $pdo->prepare('DELETE FROM artdon_home_product_slots_v713 WHERE item_type=? AND item_id=?')->execute([$itemType, $itemId]);
            }
            $pdo->commit();
            if (function_exists('web_log')) web_log($pdo, (int)$user['id'], 'save_home_product_boards', 'artdon_home_product_slots_v713', $itemType . '#' . $itemId, ['boards'=>$boards]);
            $_SESSION['admin_success'] = $boards ? '首页推荐板块已保存。' : '已从所有首页板块移除。';
            hp_redirect(['q'=>$_POST['keep_q'] ?? '', 'type'=>$_POST['keep_type'] ?? '', 'cat'=>$_POST['keep_cat'] ?? '', 'limit'=>$_POST['keep_limit'] ?? '', 'dashq'=>$_POST['keep_dashq'] ?? '']);
        }

        if ($action === 'save_categories') {
            $rows = (array)($_POST['catrow'] ?? []);
            $stmt = $pdo->prepare('UPDATE artdon_product_categories_v713 SET display_name=?, nav_label=?, home_tab_label=?, family_title=?, page_title=?, seo_title=?, sort_order=?, is_active=?, updated_at=CURRENT_TIMESTAMP WHERE slug=?');
            foreach ($rows as $slug => $r) {
                $slug = (string)$slug;
                if ($slug === '') continue;
                $display = trim((string)($r['display_name'] ?? '')) ?: ucwords(str_replace('-', ' ', $slug));
                $nav = trim((string)($r['nav_label'] ?? '')) ?: $display;
                $home = trim((string)($r['home_tab_label'] ?? '')) ?: $display;
                $family = trim((string)($r['family_title'] ?? '')) ?: $display;
                $page = trim((string)($r['page_title'] ?? '')) ?: $display;
                $seo = trim((string)($r['seo_title'] ?? '')) ?: ($page . ' | Artdon Lighting');
                $stmt->execute([$display, $nav, $home, $family, $page, $seo, (int)($r['sort_order'] ?? 100), empty($r['is_active']) ? 0 : 1, $slug]);
            }
            $changed = artdon_v713_sync_native_categories($pdo);
            artdon_v713_set_flag($pdo, 'category_unified_names', '1');
            if (function_exists('web_log')) web_log($pdo, (int)$user['id'], 'save_unified_product_categories', 'artdon_product_categories_v713', 'all', ['native_changed'=>$changed]);
            $_SESSION['admin_success'] = '产品分类统一名称已保存，并同步到原分类表。';
            hp_redirect(['board'=>$_POST['keep_board'] ?? '']);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['admin_error'] = '操作失败：' . $e->getMessage();
        hp_redirect();
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$cat = trim((string)($_GET['cat'] ?? ''));
$limit = max(20, min(80, (int)($_GET['limit'] ?? 30)));
$dashQ = trim((string)($_GET['dashq'] ?? ''));
$boardFilter = trim((string)($_GET['board'] ?? ''));

$categories = artdon_v713_categories($pdo, false);
$activeCategories = artdon_v713_categories($pdo, true);
if (!$activeCategories) $activeCategories = $categories;
$allSlots = artdon_v714_all_slots($pdo, $dashQ);
$slotsByBoard = [];
foreach ($allSlots as $slot) $slotsByBoard[(string)$slot['board_key']][] = $slot;
$results = $q !== '' ? artdon_v713_catalog_search($pdo, $q, $type, $cat, $limit) : [];
$displayName = (string)($user['display_name'] ?? $user['username'] ?? '管理员');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>首页发布与产品分类 | Artdon 官网后台</title>
<style>
:root{--bg:#f5f6f8;--panel:#fff;--text:#15171a;--muted:#667085;--line:#dde3ec;--red:#d71920;--dark:#101114;--shadow:0 10px 28px rgba(16,24,40,.06)}
*{box-sizing:border-box}html,body{margin:0;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",Arial,sans-serif;font-size:14px;line-height:1.5}a{text-decoration:none;color:inherit}button,input,select,textarea{font:inherit}input:not([type=checkbox]),select,textarea{width:100%;min-height:40px;border:1px solid #cfd6e2;border-radius:9px;background:#fff;padding:8px 11px;outline:none}input:focus,select:focus,textarea:focus{border-color:#8aa8d6;box-shadow:0 0 0 3px rgba(35,103,201,.10)}input[type=checkbox]{accent-color:var(--red)}
.hp-shell{min-height:100vh}.hp-top{position:sticky;top:0;z-index:30;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}.hp-top-inner{max-width:1480px;margin:0 auto;padding:14px 28px;display:flex;align-items:center;justify-content:space-between;gap:18px}.hp-brand{display:flex;align-items:center;gap:14px;min-width:0}.hp-back{width:36px;height:36px;border:1px solid var(--line);border-radius:10px;display:grid;place-items:center;background:#fff;color:#343942}.hp-title small{display:block;text-transform:uppercase;letter-spacing:.13em;color:#98a2b3;font-size:10px;font-weight:800}.hp-title h1{margin:2px 0 0;font-size:20px;line-height:1.2;letter-spacing:-.02em}.hp-user{font-size:12px;color:#667085;white-space:nowrap}.hp-wrap{max-width:1480px;margin:0 auto;padding:22px 28px 72px}.hp-tabs{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 18px}.hp-tabs a{height:36px;display:inline-flex;align-items:center;justify-content:center;padding:0 14px;border-radius:10px;border:1px solid var(--line);background:#fff;color:#2b3037;font-weight:800;font-size:13px}.hp-tabs a.on{background:var(--red);color:#fff;border-color:var(--red)}.hp-tabs a.soft{background:#f8fafc;color:#667085}.hp-note{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:0 0 18px;padding:14px 16px;border:1px solid #dce5f1;background:#fff;border-radius:16px;box-shadow:var(--shadow)}.hp-note b{font-size:16px}.hp-note span{color:#667085;font-size:13px}.hp-notice{margin:0 0 16px;border-radius:12px;padding:12px 14px;display:flex;gap:12px;align-items:center}.hp-notice.ok{background:#ecfdf3;border:1px solid #b7ebcb;color:#027a48}.hp-notice.err{background:#fff1f3;border:1px solid #fecdd3;color:#b42318}.hp-card{width:100%;background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow);padding:22px;margin:0 0 22px}.hp-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:0 0 18px}.hp-card-head h2{font-size:22px;line-height:1.25;margin:0 0 5px;letter-spacing:-.02em}.hp-card-head p{margin:0;color:var(--muted);font-size:13px}.hp-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.hp-btn{height:40px;border-radius:10px;border:1px solid var(--line);background:#fff;color:#111;padding:0 14px;font-weight:850;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:6px;white-space:nowrap}.hp-btn.primary{background:#111;color:#fff;border-color:#111}.hp-btn.red{background:var(--red);border-color:var(--red);color:#fff}.hp-btn.danger{background:#fff1f2;border-color:#fecdd3;color:#c5162c}.hp-search{display:grid;grid-template-columns:minmax(260px,1fr) 190px 230px 110px 150px;gap:12px;align-items:end}.hp-field{display:grid;gap:6px}.hp-field label{font-size:12px;color:#536072;font-weight:850}.hp-empty{border:1px dashed #cfd6e3;border-radius:14px;background:#fbfcfd;color:#778196;text-align:center;padding:28px;margin-top:16px;font-weight:650}.hp-results{display:grid;gap:14px;margin-top:16px}.hp-result{display:grid;grid-template-columns:250px minmax(0,1fr) 96px 130px;gap:16px;align-items:start;border:1px solid #e2e7ef;background:#fbfcfd;border-radius:16px;padding:16px}.hp-pill{display:inline-flex;align-items:center;justify-content:center;height:26px;padding:0 10px;border-radius:999px;background:#eef2f6;color:#344054;font-size:12px;font-weight:900}.hp-product-title{display:block;margin:8px 0 5px;font-size:20px;font-weight:950;letter-spacing:-.02em}.hp-product-meta{display:block;color:#667085;font-size:12px;line-height:1.55;word-break:break-all}.hp-checks{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:10px}.hp-checks label{display:flex;align-items:flex-start;gap:9px;min-height:58px;padding:10px 11px;border:1px solid #dfe5ee;border-radius:12px;background:#fff;cursor:pointer}.hp-checks input{margin-top:3px;flex:0 0 auto}.hp-checks b{display:block;font-size:13px;line-height:1.25}.hp-checks small{display:block;margin-top:3px;color:#667085;font-size:11px;line-height:1.2;word-break:break-word}.hp-board-filter{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.hp-board-filter input{width:220px}.hp-board-filter select{width:190px}.hp-board-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.hp-board{min-width:0;border:1px solid #e2e7ef;border-radius:16px;background:#fbfcfd;padding:15px}.hp-board h3{display:flex;justify-content:space-between;gap:10px;align-items:center;margin:0 0 12px;font-size:17px}.hp-board h3 small{font-size:12px;color:#667085}.hp-slot{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid #e5e9f0;background:#fff;border-radius:14px;padding:13px;margin:10px 0}.hp-slot b{display:block;font-size:16px}.hp-slot small{display:block;margin-top:4px;color:#667085;font-size:12px;line-height:1.45;word-break:break-word}.hp-slot-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.hp-slot-actions form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0}.hp-slot-actions input[type=number]{width:76px}.hp-cat-wrap{width:100%;overflow:auto;border:1px solid #e3e7ef;border-radius:14px;background:#fff}.hp-cat{width:100%;min-width:1320px;border-collapse:collapse;table-layout:fixed}.hp-cat th{position:sticky;top:0;background:#f7f9fb;color:#596276;font-size:12px;text-align:left;padding:10px;border-bottom:1px solid #e5e9f0;white-space:nowrap}.hp-cat td{padding:9px 10px;border-bottom:1px solid #edf0f5;vertical-align:middle}.hp-cat input{height:34px;min-height:34px;border-radius:8px}.hp-cat code{font-size:12px;color:#667085;white-space:nowrap}.hp-muted{color:#667085;font-size:12px}.hp-footer-note{margin-top:10px;color:#667085;font-size:13px}.hp-version{font-size:11px;color:#98a2b3;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.hp-section-anchor{scroll-margin-top:96px}@media(max-width:1280px){.hp-search{grid-template-columns:1fr 170px 190px 100px}.hp-search .hp-btn{grid-column:1/-1}.hp-checks{grid-template-columns:repeat(2,minmax(160px,1fr))}.hp-result{grid-template-columns:210px minmax(0,1fr)}.hp-result>.hp-field,.hp-result>.hp-actions{grid-column:auto}.hp-board-grid{grid-template-columns:1fr}.hp-slot{grid-template-columns:1fr}.hp-slot-actions{justify-content:flex-start}}@media(max-width:760px){.hp-top-inner,.hp-wrap{padding-left:14px;padding-right:14px}.hp-top-inner{align-items:flex-start;flex-direction:column}.hp-card{padding:15px;border-radius:14px}.hp-card-head{display:block}.hp-card-head .hp-actions{margin-top:10px}.hp-search,.hp-result{grid-template-columns:1fr}.hp-checks{grid-template-columns:1fr}.hp-board-filter input,.hp-board-filter select,.hp-btn{width:100%}.hp-note{display:block}.hp-tabs a{flex:1}}
</style>
</head>
<body>
<div class="hp-shell">
  <header class="hp-top">
    <div class="hp-top-inner">
      <div class="hp-brand">
        <a class="hp-back" href="product_center.php" title="返回产品中心">‹</a>
        <div class="hp-title"><small>Artdon Web / 产品中心</small><h1>首页发布与产品分类统一</h1></div>
      </div>
      <div class="hp-user">香港官网 · <?= hp_h($displayName) ?></div>
    </div>
  </header>

  <main class="hp-wrap">
    <nav class="hp-tabs" aria-label="产品中心快捷导航">
      <a class="soft" href="product_center.php">总览</a>
      <a class="soft" href="products.php">系列管理</a>
      <a class="soft" href="product_models.php">具体产品</a>
      <a class="soft" href="product_filters.php">筛选库</a>
      <a class="on" href="home_products.php">首页发布</a>
      <a class="soft" href="product_categories.php">产品分类</a>
      <a class="soft" href="../index.php#products" target="_blank">打开首页 ↗</a>
      <a class="soft" href="../api/artdon_home_products_v7135.php" target="_blank">首页产品 API ↗</a>
    </nav>

    <div class="hp-note"><div><b>V7.1.6 单页收口版</b><span>本页不再使用旧后台三列壳层，直接上下排版：推荐产品到首页 → 首页发布看板 → 产品分类统一修改。</span></div><span class="hp-version">single file</span></div>
    <?php hp_notice(); ?>

    <section class="hp-card hp-section-anchor" id="publish-search">
      <div class="hp-card-head">
        <div><h2>推荐产品到首页</h2><p>模糊搜索系列或具体产品，勾选要显示的首页板块。ALL 是独立板块，不会自动汇总。</p></div>
      </div>
      <form method="get" class="hp-search">
        <div class="hp-field"><label>模糊搜索</label><input name="q" value="<?= hp_h($q) ?>" placeholder="SPECTRUM / FLEXI / 型号 / slug"></div>
        <div class="hp-field"><label>类型</label><select name="type"><option value="" <?= $type===''?'selected':'' ?>>全部</option><option value="series" <?= $type==='series'?'selected':'' ?>>产品系列</option><option value="product" <?= $type==='product'?'selected':'' ?>>具体产品</option></select></div>
        <div class="hp-field"><label>来源分类</label><select name="cat"><option value="">全部分类</option><?php foreach($activeCategories as $c): ?><option value="<?= hp_h($c['slug']) ?>" <?= $cat===(string)$c['slug']?'selected':'' ?>><?= hp_h($c['display_name']) ?></option><?php endforeach; ?></select></div>
        <div class="hp-field"><label>数量</label><select name="limit"><?php foreach([20,30,50,80] as $n): ?><option value="<?= $n ?>" <?= $limit===$n?'selected':'' ?>><?= $n ?></option><?php endforeach; ?></select></div>
        <button class="hp-btn primary" type="submit">搜索</button>
      </form>

      <?php if ($q === ''): ?>
        <div class="hp-empty">输入关键词后搜索，不默认加载全部产品，避免后台卡死。</div>
      <?php elseif (!$results): ?>
        <div class="hp-empty">没有搜到结果。请换产品名、型号或 slug。</div>
      <?php else: ?>
        <div class="hp-results">
          <?php foreach($results as $item):
            $itemBoards = artdon_v713_item_boards($pdo, (string)$item['type'], (int)$item['id']);
            $firstBoard = $itemBoards ? reset($itemBoards) : null;
          ?>
          <form method="post" class="hp-result">
            <div>
              <span class="hp-pill"><?= $item['type']==='product' ? '具体产品' : '产品系列' ?></span>
              <strong class="hp-product-title"><?= hp_h($item['title']) ?></strong>
              <small class="hp-product-meta"><?= hp_h($item['slug']) ?> · <?= hp_h($item['_source_table'] ?? '') ?><br>来源分类：<?= hp_h($item['category_name'] ?: $item['category_slug']) ?></small>
              <input type="hidden" name="csrf" value="<?= hp_h(hp_csrf()) ?>">
              <input type="hidden" name="action" value="save_item_boards">
              <input type="hidden" name="item_type" value="<?= hp_h($item['type']) ?>">
              <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="item_slug" value="<?= hp_h($item['slug']) ?>">
              <input type="hidden" name="item_name" value="<?= hp_h($item['title']) ?>">
              <input type="hidden" name="category_slug" value="<?= hp_h($item['category_slug']) ?>">
              <input type="hidden" name="keep_q" value="<?= hp_h($q) ?>">
              <input type="hidden" name="keep_type" value="<?= hp_h($type) ?>">
              <input type="hidden" name="keep_cat" value="<?= hp_h($cat) ?>">
              <input type="hidden" name="keep_limit" value="<?= (int)$limit ?>">
            </div>
            <div class="hp-checks">
              <?php foreach($activeCategories as $c): $slug=(string)$c['slug']; ?>
              <label><input type="checkbox" name="boards[]" value="<?= hp_h($slug) ?>" <?= isset($itemBoards[$slug]) ? 'checked' : '' ?>><span><b><?= hp_h($c['home_tab_label'] ?: $c['display_name']) ?></b><small><?= hp_h($slug) ?></small></span></label>
              <?php endforeach; ?>
            </div>
            <div class="hp-field"><label>排序</label><input type="number" name="sort_order" value="<?= (int)($firstBoard['sort_order'] ?? 100) ?>"></div>
            <div class="hp-actions"><button class="hp-btn red" type="submit">保存推荐</button></div>
          </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="hp-card hp-section-anchor" id="publish-board">
      <div class="hp-card-head">
        <div><h2>首页发布看板</h2><p>这里显示当前所有已绑定到首页的产品/系列，可直接删除、改排序、显示/隐藏。</p></div>
        <form method="get" class="hp-board-filter">
          <input name="dashq" value="<?= hp_h($dashQ) ?>" placeholder="过滤已发布">
          <select name="board"><option value="">全部板块</option><?php foreach($activeCategories as $c): ?><option value="<?= hp_h($c['slug']) ?>" <?= $boardFilter===(string)$c['slug']?'selected':'' ?>><?= hp_h($c['home_tab_label'] ?: $c['display_name']) ?></option><?php endforeach; ?></select>
          <button class="hp-btn" type="submit">过滤</button>
        </form>
      </div>
      <div class="hp-board-grid">
        <?php foreach($activeCategories as $c):
          $boardKey=(string)$c['slug'];
          if ($boardFilter !== '' && $boardFilter !== $boardKey) continue;
          $rows = $slotsByBoard[$boardKey] ?? [];
        ?>
        <div class="hp-board">
          <h3><span><?= hp_h($c['home_tab_label'] ?: $c['display_name']) ?></span><small><?= count($rows) ?> 项</small></h3>
          <?php if(!$rows): ?><div class="hp-empty" style="margin-top:0;padding:18px">暂无产品。</div><?php endif; ?>
          <?php foreach($rows as $slot): ?>
          <div class="hp-slot">
            <div><b><?= hp_h($slot['item_name']) ?></b><small><?= hp_h($slot['item_type']) ?> · <?= hp_h($slot['item_slug']) ?> · <?= hp_h($slot['category_slug']) ?></small></div>
            <div class="hp-slot-actions">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= hp_h(hp_csrf()) ?>">
                <input type="hidden" name="action" value="update_slot">
                <input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
                <input type="hidden" name="keep_dashq" value="<?= hp_h($dashQ) ?>">
                <input type="hidden" name="keep_board" value="<?= hp_h($boardFilter) ?>">
                <input type="number" name="sort_order" value="<?= (int)$slot['sort_order'] ?>">
                <label><input type="checkbox" name="is_active" value="1" <?= !empty($slot['is_active']) ? 'checked' : '' ?>> 显示</label>
                <button class="hp-btn" type="submit">保存</button>
              </form>
              <form method="post" onsubmit="return confirm('只删除这个首页板块绑定？不会删除产品本身。')">
                <input type="hidden" name="csrf" value="<?= hp_h(hp_csrf()) ?>">
                <input type="hidden" name="action" value="delete_slot">
                <input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
                <input type="hidden" name="keep_dashq" value="<?= hp_h($dashQ) ?>">
                <input type="hidden" name="keep_board" value="<?= hp_h($boardFilter) ?>">
                <button class="hp-btn danger" type="submit">删除</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="hp-card hp-section-anchor" id="category-unify">
      <div class="hp-card-head">
        <div><h2>产品分类统一修改</h2><p>一处修改，首页板块、产品页分类、SEO 名称同步读取。Downlights 已统一为 Recessed Downlight。</p></div>
        <div class="hp-actions"><a class="hp-btn" href="product_categories.php">打开分类专页</a></div>
      </div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= hp_h(hp_csrf()) ?>">
        <input type="hidden" name="action" value="save_categories">
        <div class="hp-cat-wrap">
          <table class="hp-cat">
            <thead><tr><th style="width:170px">slug</th><th>统一名称</th><th>导航名称</th><th>首页 Tab</th><th>系列标题</th><th>页面标题</th><th>SEO 标题</th><th style="width:90px">排序</th><th style="width:80px">显示</th></tr></thead>
            <tbody>
            <?php foreach($categories as $c): ?>
              <tr>
                <td><code><?= hp_h($c['slug']) ?></code></td>
                <td><input name="catrow[<?= hp_h($c['slug']) ?>][display_name]" value="<?= hp_h($c['display_name']) ?>"></td>
                <td><input name="catrow[<?= hp_h($c['slug']) ?>][nav_label]" value="<?= hp_h($c['nav_label']) ?>"></td>
                <td><input name="catrow[<?= hp_h($c['slug']) ?>][home_tab_label]" value="<?= hp_h($c['home_tab_label']) ?>"></td>
                <td><input name="catrow[<?= hp_h($c['slug']) ?>][family_title]" value="<?= hp_h($c['family_title']) ?>"></td>
                <td><input name="catrow[<?= hp_h($c['slug']) ?>][page_title]" value="<?= hp_h($c['page_title']) ?>"></td>
                <td><input name="catrow[<?= hp_h($c['slug']) ?>][seo_title]" value="<?= hp_h($c['seo_title']) ?>"></td>
                <td><input type="number" name="catrow[<?= hp_h($c['slug']) ?>][sort_order]" value="<?= (int)$c['sort_order'] ?>"></td>
                <td><label><input type="checkbox" name="catrow[<?= hp_h($c['slug']) ?>][is_active]" value="1" <?= !empty($c['is_active'])?'checked':'' ?>>显示</label></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="hp-actions" style="margin-top:14px"><button class="hp-btn red" type="submit">保存分类统一名称</button><span class="hp-muted">保存后会同步原 web_product_categories 表。</span></div>
      </form>
    </section>

    <p class="hp-footer-note"><b>规则：</b>旧推荐产品流程已停用；首页只认“首页发布看板”和明确勾选的板块。ALL 是独立板块，不会自动汇总。</p>
  </main>
</div>
</body>
</html>
