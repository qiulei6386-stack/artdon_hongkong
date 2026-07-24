<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
$user = web_require_admin($pdo);

function solution_icon_redirect(?int $editId = null): void
{
    $location = 'solution_icons.php';
    if ($editId && $editId > 0) {
        $location .= '?edit='.$editId;
    }
    header('Location: '.$location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        solution_icon_redirect();
    }

    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $key = web_solution_icon_normalize_key((string)($_POST['icon_key'] ?? ''));
            $label = trim((string)($_POST['label'] ?? ''));
            if ($key === '' || !preg_match('/^[a-z0-9][a-z0-9_-]{1,79}$/', $key)) {
                throw new InvalidArgumentException('图标代码需使用 2–80 位小写英文、数字、短横线或下划线。');
            }
            if ($label === '') {
                throw new InvalidArgumentException('请填写图标名称。');
            }
            if (web_solution_icon_key_exists($pdo, $key)) {
                throw new InvalidArgumentException('图标代码已存在，请换一个代码。');
            }
            $svg = web_solution_icon_sanitize((string)($_POST['svg_code'] ?? ''));
            $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM web_solution_icons')->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO web_solution_icons (icon_key, label, svg_code, sort_order, is_system, created_by, updated_by) VALUES (?, ?, ?, ?, 0, ?, ?)');
            $stmt->execute([$key, $label, $svg, $sort, (int)$user['id'], (int)$user['id']]);
            $id = (int)$pdo->lastInsertId();
            web_log($pdo, (int)$user['id'], 'create_solution_icon', 'solution_icon', (string)$id, ['icon_key'=>$key, 'label'=>$label]);
            $_SESSION['admin_success'] = '图标已新增，首页应用方案下拉框会自动读取。';
            solution_icon_redirect($id);
        }

        if ($action === 'update') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $icon = web_solution_icon_find($pdo, $id);
            if (!$icon) {
                throw new RuntimeException('图标不存在或已被删除。');
            }
            $label = trim((string)($_POST['label'] ?? ''));
            if ($label === '') {
                throw new InvalidArgumentException('请填写图标名称。');
            }
            $svg = web_solution_icon_sanitize((string)($_POST['svg_code'] ?? ''));
            $stmt = $pdo->prepare('UPDATE web_solution_icons SET label=?, svg_code=?, updated_by=? WHERE id=?');
            $stmt->execute([$label, $svg, (int)$user['id'], $id]);
            web_log($pdo, (int)$user['id'], 'update_solution_icon', 'solution_icon', (string)$id, ['icon_key'=>$icon['icon_key'], 'label'=>$label]);
            $_SESSION['admin_success'] = '图标已更新并立即用于官网前台。';
            solution_icon_redirect($id);
        }

        if ($action === 'delete') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $icon = web_solution_icon_find($pdo, $id);
            if (!$icon) {
                throw new RuntimeException('图标不存在或已被删除。');
            }
            if (!empty($icon['is_system'])) {
                throw new RuntimeException('retail、hospitality、museum、office 为系统保留图标，不能删除。');
            }
            $usage = web_solution_icon_usage($pdo, (string)$icon['icon_key']);
            if ($usage) {
                throw new RuntimeException('该图标正在被首页应用方案使用：'.implode('、', array_slice($usage, 0, 8)).'。请先更换这些方案的图标。');
            }
            $stmt = $pdo->prepare('DELETE FROM web_solution_icons WHERE id=?');
            $stmt->execute([$id]);
            web_log($pdo, (int)$user['id'], 'delete_solution_icon', 'solution_icon', (string)$id, ['icon_key'=>$icon['icon_key'], 'label'=>$icon['label']]);
            $_SESSION['admin_success'] = '图标已删除。';
            solution_icon_redirect();
        }

        if ($action === 'reorder') {
            $raw = trim((string)($_POST['order_ids'] ?? ''));
            $ids = [];
            foreach (explode(',', $raw) as $value) {
                $id = (int)$value;
                if ($id > 0 && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
            $existing = array_map(static fn(array $row): int => (int)$row['id'], web_solution_icons_all($pdo));
            if (!$ids || count($ids) !== count($existing) || array_diff($ids, $existing) || array_diff($existing, $ids)) {
                throw new RuntimeException('排序数据不完整，请刷新页面后重新排序。');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE web_solution_icons SET sort_order=?, updated_by=? WHERE id=?');
            foreach ($ids as $index => $id) {
                $stmt->execute([($index + 1) * 10, (int)$user['id'], $id]);
            }
            $pdo->commit();
            web_log($pdo, (int)$user['id'], 'reorder_solution_icons', 'solution_icon_library', 'homepage', ['count'=>count($ids)]);
            $_SESSION['admin_success'] = '图标顺序已保存，应用方案下拉框将按此顺序显示。';
            solution_icon_redirect();
        }

        throw new InvalidArgumentException('未知操作。');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['admin_error'] = $e->getMessage();
        $editId = $action === 'update' ? max(0, (int)($_POST['id'] ?? 0)) : null;
        solution_icon_redirect($editId);
    }
}

$icons = web_solution_icons_all($pdo);
$editId = max(0, (int)($_GET['edit'] ?? 0));
$editing = $editId > 0 ? web_solution_icon_find($pdo, $editId) : null;
$editingPreview = '<svg viewBox="0 0 24 24"><path d="M4 12h16M12 4v16"/></svg>';
if ($editing) {
    try {
        $editingPreview = web_solution_icon_sanitize((string)$editing['svg_code']);
    } catch (Throwable $e) {
        $editingPreview = (string)web_solution_icon_defaults()[0]['svg_code'];
    }
}
$usageByKey = [];
foreach ($icons as $icon) {
    $usageByKey[(string)$icon['icon_key']] = web_solution_icon_usage($pdo, (string)$icon['icon_key']);
}

admin_page_start('应用方案图标库', 'solution_icons', $user);
admin_notice();
?>
<div class="solution-icon-page">
  <section class="admin-card solution-icon-summary">
    <div>
      <p class="solution-icon-kicker">V6.9 · SVG ICON LIBRARY</p>
      <h2>应用方案图标库</h2>
      <p>首页后台“应用方案”的图标类型会自动读取这里的图标。SVG 保存前会移除脚本、事件、外部链接、样式和不安全标签。</p>
    </div>
    <div class="solution-icon-summary-stats">
      <span><strong><?= count($icons) ?></strong>全部图标</span>
      <span><strong><?= count(array_filter($icons, static fn(array $row): bool => !empty($row['is_system']))) ?></strong>系统保留</span>
      <a class="admin-button-secondary" href="homepage.php?section=solutions">返回应用方案</a>
    </div>
  </section>

  <div class="solution-icon-workspace">
    <aside class="admin-card solution-icon-editor">
      <div class="admin-card-head">
        <div>
          <h2><?= $editing ? '编辑图标' : '新增 SVG 图标' ?></h2>
          <p><?= $editing ? '图标代码固定不变，避免已使用的应用方案失联。' : '新增后会立即出现在应用方案的图标下拉框。' ?></p>
        </div>
        <?php if($editing): ?><a class="admin-button-secondary" href="solution_icons.php">新增图标</a><?php endif; ?>
      </div>
      <form method="post" class="solution-icon-form" data-svg-editor>
        <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="field">
          <label>图标代码</label>
          <input name="icon_key" value="<?= web_e($editing['icon_key'] ?? '') ?>" placeholder="例如 airport" <?= $editing ? 'readonly' : 'required' ?> pattern="[a-z0-9][a-z0-9_-]{1,79}">
          <span class="help">仅限小写英文、数字、短横线或下划线；保存后不可改名。</span>
        </div>
        <div class="field">
          <label>显示名称</label>
          <input name="label" value="<?= web_e($editing['label'] ?? '') ?>" placeholder="Airport / 机场" maxlength="120" required>
        </div>
        <div class="field">
          <label>SVG 代码</label>
          <textarea name="svg_code" rows="12" spellcheck="false" required data-svg-code><?= web_e($editing['svg_code'] ?? '<svg viewBox="0 0 24 24"><path d=""/></svg>') ?></textarea>
          <span class="help">支持 svg、g、path、circle、ellipse、line、polyline、polygon、rect。禁止 script、style、image、use、foreignObject、href 和外部资源。</span>
        </div>
        <div class="solution-icon-live-preview">
          <span>预览</span>
          <i data-svg-preview aria-hidden="true"><?= $editingPreview ?></i>
          <small>前台会继承当前文字颜色，并使用统一尺寸。</small>
        </div>
        <button class="admin-button" type="submit"><?= $editing ? '保存图标修改' : '新增到图标库' ?></button>
      </form>
    </aside>

    <main class="admin-card solution-icon-library">
      <div class="admin-card-head">
        <div><h2>图标列表</h2><p>拖动卡片或使用箭头排序，再点击“保存当前排序”。</p></div>
        <form method="post" id="solutionIconOrderForm">
          <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
          <input type="hidden" name="action" value="reorder">
          <input type="hidden" name="order_ids" value="<?= web_e(implode(',', array_map(static fn(array $row): int => (int)$row['id'], $icons))) ?>" data-icon-order>
          <button class="admin-button" type="submit">保存当前排序</button>
        </form>
      </div>

      <div class="solution-icon-grid" data-icon-sortable>
        <?php foreach($icons as $icon):
          $usage = $usageByKey[(string)$icon['icon_key']] ?? [];
          try { $safeSvg = web_solution_icon_sanitize((string)$icon['svg_code']); } catch(Throwable $e) { $safeSvg = web_solution_icon_defaults()[0]['svg_code']; }
        ?>
        <article class="solution-icon-card<?= $editing && (int)$editing['id']===(int)$icon['id'] ? ' is-editing' : '' ?>" draggable="true" data-icon-id="<?= (int)$icon['id'] ?>">
          <div class="solution-icon-card-preview"><i aria-hidden="true"><?= $safeSvg ?></i><button type="button" class="solution-icon-drag" title="拖动排序">⋮⋮</button></div>
          <div class="solution-icon-card-body">
            <div class="solution-icon-card-title"><strong><?= web_e($icon['label']) ?></strong><?php if(!empty($icon['is_system'])): ?><span>系统保留</span><?php endif; ?></div>
            <code><?= web_e($icon['icon_key']) ?></code>
            <p><?= $usage ? '首页使用：'.web_e(implode('、', $usage)) : '当前未被首页应用方案使用' ?></p>
            <div class="solution-icon-card-actions">
              <button type="button" data-icon-up title="上移">↑</button>
              <button type="button" data-icon-down title="下移">↓</button>
              <a href="solution_icons.php?edit=<?= (int)$icon['id'] ?>">编辑</a>
              <?php if(empty($icon['is_system'])): ?>
              <form method="post" onsubmit="return confirm('确定删除此图标吗？系统会再次检查是否正在使用。');">
                <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$icon['id'] ?>">
                <button type="submit" class="danger"<?= $usage ? ' disabled title="正在使用，无法删除"' : '' ?>><?= $usage ? '使用中' : '删除' ?></button>
              </form>
              <?php else: ?><span class="solution-icon-protected">不可删除</span><?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</div>
<script>
(() => {
  const list = document.querySelector('[data-icon-sortable]');
  const orderInput = document.querySelector('[data-icon-order]');
  if (list && orderInput) {
    const syncOrder = () => {
      orderInput.value = Array.from(list.querySelectorAll('[data-icon-id]')).map(item => item.dataset.iconId).join(',');
    };
    const move = (card, direction) => {
      if (direction < 0 && card.previousElementSibling) list.insertBefore(card, card.previousElementSibling);
      if (direction > 0 && card.nextElementSibling) list.insertBefore(card.nextElementSibling, card);
      syncOrder();
    };
    list.addEventListener('click', event => {
      const card = event.target.closest('[data-icon-id]');
      if (!card) return;
      if (event.target.closest('[data-icon-up]')) move(card, -1);
      if (event.target.closest('[data-icon-down]')) move(card, 1);
    });
    let dragged = null;
    let dragArmed = null;
    list.addEventListener('mousedown', event => {
      const handle = event.target.closest('.solution-icon-drag');
      dragArmed = handle ? handle.closest('[data-icon-id]') : null;
    });
    list.addEventListener('dragstart', event => {
      const card = event.target.closest('[data-icon-id]');
      if (!card || card !== dragArmed) { event.preventDefault(); return; }
      dragged = card; card.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragover', event => {
      if (!dragged) return;
      const target = event.target.closest('[data-icon-id]');
      if (!target || target === dragged) return;
      event.preventDefault();
      const rect = target.getBoundingClientRect();
      list.insertBefore(dragged, event.clientY < rect.top + rect.height / 2 ? target : target.nextElementSibling);
    });
    list.addEventListener('dragend', () => { if (dragged) dragged.classList.remove('is-dragging'); dragged = null; dragArmed = null; syncOrder(); });
  }

})();
</script>
<?php admin_page_end(); ?>
