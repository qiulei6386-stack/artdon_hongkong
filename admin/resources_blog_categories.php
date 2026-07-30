<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/resources_blog_data.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_blog_seed($pdo);
$user = web_require_admin($pdo);

function rb_category_value(mixed $value): string { return trim((string)$value); }
function rb_category_back(): void { header('Location: resources_blog_categories.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        rb_category_back();
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        $rows = artdon_resource_blog_category_rows($pdo, false);
        if ($action === 'create' || $action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $label = rb_category_value($_POST['label'] ?? '');
            $slug = artdon_resource_blog_slug(rb_category_value($_POST['slug'] ?? $label));
            $sectionTitle = rb_category_value($_POST['section_title'] ?? '') ?: $label;
            $icon = artdon_resource_blog_slug(rb_category_value($_POST['icon'] ?? '')) ?: 'lighting-knowledge';
            $sortOrder = (int)($_POST['sort_order'] ?? 10);
            $visible = !empty($_POST['is_visible']) ? 1 : 0;
            if ($label === '' || $slug === '') throw new RuntimeException('分类名称不能为空。');
            if ($id > 0) {
                $old = null;
                foreach ($rows as $row) if ((int)$row['id'] === $id) { $old = $row; break; }
                if (!$old) throw new RuntimeException('未找到要修改的分类。');
                foreach ($rows as $row) {
                    if ((int)$row['id'] !== $id && (string)$row['slug'] === $slug) throw new RuntimeException('分类链接标识已存在，请换一个。');
                }
                $pdo->beginTransaction();
                if ((string)$old['slug'] !== $slug) {
                    $pdo->prepare('UPDATE web_resource_blog_articles SET category=? WHERE category=?')->execute([$slug, (string)$old['slug']]);
                }
                $pdo->prepare('UPDATE web_resource_blog_categories SET slug=?,label=?,section_title=?,icon=?,sort_order=?,is_visible=? WHERE id=?')->execute([$slug, $label, $sectionTitle, $icon, $sortOrder, $visible, $id]);
                $pdo->commit();
                $_SESSION['admin_success'] = '博客分类已保存。';
                web_log($pdo, (int)$user['id'], 'update_content', 'resources_blog_category', $slug, ['id'=>$id]);
            } else {
                foreach ($rows as $row) if ((string)$row['slug'] === $slug) throw new RuntimeException('分类链接标识已存在，请换一个。');
                $pdo->prepare('INSERT INTO web_resource_blog_categories (slug,label,section_title,icon,sort_order,is_visible) VALUES (?,?,?,?,?,?)')->execute([$slug, $label, $sectionTitle, $icon, $sortOrder, $visible]);
                $_SESSION['admin_success'] = '博客分类已新增。';
                web_log($pdo, (int)$user['id'], 'create_content', 'resources_blog_category', $slug, ['id'=>(int)$pdo->lastInsertId()]);
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $target = artdon_resource_blog_slug(rb_category_value($_POST['move_articles_to'] ?? ''));
            $current = null;
            foreach ($rows as $row) if ((int)$row['id'] === $id) { $current = $row; break; }
            if (!$current) throw new RuntimeException('未找到要删除的分类。');
            if (count($rows) <= 1) throw new RuntimeException('至少需要保留一个博客分类。');
            $targets = artdon_resource_blog_categories($pdo, false);
            unset($targets[(string)$current['slug']]);
            if (!isset($targets[$target])) $target = (string)(array_key_first($targets) ?: '');
            if ($target === '') throw new RuntimeException('请先选择文章要转移到的分类。');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE web_resource_blog_articles SET category=? WHERE category=?');
            $stmt->execute([$target, (string)$current['slug']]);
            $moved = $stmt->rowCount();
            $pdo->prepare('DELETE FROM web_resource_blog_categories WHERE id=?')->execute([$id]);
            $pdo->commit();
            $_SESSION['admin_success'] = '分类已删除，' . $moved . ' 篇文章已转移到 ' . $targets[$target] . '。';
            web_log($pdo, (int)$user['id'], 'delete_content', 'resources_blog_category', (string)$current['slug'], ['id'=>$id,'move_to'=>$target,'articles_moved'=>$moved]);
        } else {
            throw new RuntimeException('未知操作。');
        }
        web_public_cache_clear('');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
    }
    rb_category_back();
}

$categories = artdon_resource_blog_category_rows($pdo, false);
$articleCounts = [];
foreach ($pdo->query('SELECT category,COUNT(*) AS total FROM web_resource_blog_articles GROUP BY category')->fetchAll() ?: [] as $row) {
    $articleCounts[(string)$row['category']] = (int)$row['total'];
}

admin_page_start('博客分类管理', 'resources_blog', $user);
admin_notice();
?>
<section class="rbc-admin">
  <header class="rbc-head"><div><p>Resources / Blog &amp; Insights</p><h1>博客分类管理</h1><span>在这里新增、修改、排序、隐藏或删除前台博客分类。删除时，原分类的文章会自动转移到你选择的其他分类。</span></div><div><a class="admin-button-secondary" href="resources_blog.php">返回文章管理</a><a class="admin-button-secondary" href="../resources-blog.php" target="_blank" rel="noopener">预览前台</a></div></header>

  <form class="rbc-create" method="post">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <div><strong>新增分类</strong><span>新增后即可在文章编辑时选择，前台也会自动出现。</span></div>
    <label class="field"><span>分类名称</span><input name="label" required placeholder="例如 Project Stories"></label>
    <label class="field"><span>链接标识（英文）</span><input name="slug" placeholder="例如 project-stories"></label>
    <label class="field"><span>排序</span><input type="number" name="sort_order" value="40"></label>
    <label class="rbc-visible"><input type="checkbox" name="is_visible" value="1" checked> 前台显示</label>
    <button class="admin-button" type="submit">新增分类</button>
  </form>

  <div class="rbc-list">
    <?php foreach ($categories as $category): $slug = (string)$category['slug']; $otherCategories = array_filter($categories, static fn(array $row): bool => (int)$row['id'] !== (int)$category['id']); ?>
    <article class="rbc-card">
      <header><div><strong><?= web_e((string)$category['label']) ?></strong><span>#<?= web_e($slug) ?> · <?= (int)($articleCounts[$slug] ?? 0) ?> 篇文章 · <?= !empty($category['is_visible']) ? '前台显示' : '前台隐藏' ?></span></div><a href="resources_blog.php?new=1&amp;category=<?= web_e($slug) ?>">在此分类新增文章 →</a></header>
      <form method="post" class="rbc-edit">
        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
        <label class="field"><span>分类名称</span><input name="label" value="<?= web_e((string)$category['label']) ?>" required></label>
        <label class="field"><span>链接标识（英文）</span><input name="slug" value="<?= web_e($slug) ?>" required></label>
        <label class="field"><span>分组标题</span><input name="section_title" value="<?= web_e((string)$category['section_title']) ?>"></label>
        <label class="field"><span>图标</span><select name="icon"><option value="lighting-knowledge" <?= (string)$category['icon'] === 'lighting-knowledge' ? 'selected' : '' ?>>文档图标</option><option value="industry-news" <?= (string)$category['icon'] === 'industry-news' ? 'selected' : '' ?>>行业图标</option><option value="artdon-news" <?= (string)$category['icon'] === 'artdon-news' ? 'selected' : '' ?>>资讯图标</option></select></label>
        <label class="field"><span>排序</span><input type="number" name="sort_order" value="<?= (int)$category['sort_order'] ?>"></label>
        <label class="rbc-visible"><input type="checkbox" name="is_visible" value="1" <?= !empty($category['is_visible']) ? 'checked' : '' ?>> 前台显示</label>
        <button class="admin-button" type="submit">保存</button>
      </form>
      <?php if (count($categories) > 1): ?><form method="post" class="rbc-delete" onsubmit="return confirm('确认删除这个分类？分类内文章会转移到你选择的新分类。')"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><label>删除后将 <?= (int)($articleCounts[$slug] ?? 0) ?> 篇文章转移到 <select name="move_articles_to"><?php foreach ($otherCategories as $target): ?><option value="<?= web_e((string)$target['slug']) ?>"><?= web_e((string)$target['label']) ?></option><?php endforeach; ?></select></label><button class="admin-button-danger" type="submit">删除分类</button></form><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<style>
.rbc-admin{display:grid;gap:18px}.rbc-head{display:flex;align-items:flex-end;justify-content:space-between;gap:22px;padding:24px;border:1px solid #e1e3e6;background:#fff}.rbc-head p{margin:0 0 7px;color:#d71920;font-size:11px;font-weight:850;letter-spacing:.09em;text-transform:uppercase}.rbc-head h1{margin:0;color:#111;font-size:29px}.rbc-head span{display:block;max-width:760px;margin-top:8px;color:#70747a;font-size:13px;line-height:1.55}.rbc-head>div:last-child{display:flex;gap:8px;flex-wrap:wrap}.rbc-create,.rbc-card{border:1px solid #e1e3e6;background:#fff}.rbc-create{display:grid;grid-template-columns:minmax(220px,1.2fr) minmax(160px,1fr) minmax(180px,1fr) 110px auto auto;gap:12px;align-items:end;padding:18px}.rbc-create>div{display:grid;gap:4px;padding-bottom:5px}.rbc-create strong{font-size:15px}.rbc-create span{color:#73777e;font-size:11px;line-height:1.45}.rbc-visible{display:flex;align-items:center;gap:7px;min-height:38px;color:#52565d;font-size:12px;font-weight:700}.rbc-list{display:grid;gap:12px}.rbc-card{padding:18px}.rbc-card>header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:15px}.rbc-card>header div{display:grid;gap:4px}.rbc-card>header strong{font-size:18px}.rbc-card>header span{color:#777b82;font-size:12px}.rbc-card>header a{color:#d71920;font-size:12px;font-weight:800;text-decoration:none}.rbc-edit{display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto auto;gap:10px;align-items:end}.rbc-delete{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid #eceef0;color:#767a81;font-size:12px}.rbc-delete label{display:flex;align-items:center;gap:7px}.rbc-delete select{min-height:34px;border:1px solid #d8dbe0;border-radius:5px;padding:0 8px;background:#fff}.admin-button-danger{border:1px solid #d71920;background:#fff;color:#d71920;border-radius:4px;padding:8px 12px;font-weight:800;cursor:pointer}@media(max-width:1180px){.rbc-create,.rbc-edit{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.rbc-head,.rbc-create,.rbc-edit{display:grid;grid-template-columns:1fr}.rbc-delete,.rbc-card>header{align-items:flex-start;flex-direction:column}.rbc-delete label{align-items:flex-start;flex-direction:column}}
</style>
<?php admin_page_end(); ?>
