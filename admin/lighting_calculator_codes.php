<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/lighting_calculator_access.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_lc_access_migrate($pdo);
$user = web_require_admin($pdo);

function lc_codes_back(): never { header('Location: lighting_calculator_codes.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        lc_codes_back();
    }
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        if ($action === 'create') {
            $customer = trim((string)($_POST['customer_label'] ?? ''));
            if ($customer === '') throw new RuntimeException('客户或公司名称不能为空。');
            $expiresInput = trim((string)($_POST['expires_at'] ?? ''));
            $expiresAt = $expiresInput !== '' ? date('Y-m-d 23:59:59', strtotime($expiresInput) ?: 0) : null;
            if ($expiresInput !== '' && (!$expiresAt || strtotime($expiresAt) <= time())) throw new RuntimeException('有效期必须晚于当前时间。');
            $maxInput = trim((string)($_POST['max_activations'] ?? ''));
            $maxActivations = $maxInput !== '' ? max(1, min(100000, (int)$maxInput)) : null;

            do {
                $plainCode = artdon_lc_access_generate_code();
                $digest = artdon_lc_access_digest($plainCode);
                $check = $pdo->prepare('SELECT COUNT(*) FROM web_lighting_calculator_codes WHERE code_digest=?');
                $check->execute([$digest]);
            } while ((int)$check->fetchColumn() > 0);

            $stmt = $pdo->prepare('INSERT INTO web_lighting_calculator_codes
                (customer_label,code_digest,code_hash,code_hint,expires_at,max_activations,is_active,created_by)
                VALUES (?,?,?,?,?,?,1,?)');
            $stmt->execute([$customer, $digest, password_hash($plainCode, PASSWORD_DEFAULT), artdon_lc_access_code_hint($plainCode), $expiresAt, $maxActivations, (int)$user['id']]);
            $_SESSION['lighting_calculator_new_code'] = $plainCode;
            $_SESSION['admin_success'] = '授权码已生成。请立即复制，离开本页后将不再显示完整授权码。';
            web_log($pdo, (int)$user['id'], 'create_lighting_calculator_code', 'lighting_calculator_code', (string)$pdo->lastInsertId(), ['customer'=>$customer,'expires_at'=>$expiresAt,'max_activations'=>$maxActivations]);
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('UPDATE web_lighting_calculator_codes SET is_active=IF(is_active=1,0,1) WHERE id=?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('未找到授权码。');
            $_SESSION['admin_success'] = '授权码状态已更新。';
            web_log($pdo, (int)$user['id'], 'toggle_lighting_calculator_code', 'lighting_calculator_code', (string)$id);
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM web_lighting_calculator_grants WHERE code_id=?')->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM web_lighting_calculator_codes WHERE id=?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('未找到授权码。');
            $_SESSION['admin_success'] = '授权码已删除。';
            web_log($pdo, (int)$user['id'], 'delete_lighting_calculator_code', 'lighting_calculator_code', (string)$id);
        } else {
            throw new RuntimeException('未知操作。');
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '操作失败：' . $e->getMessage();
    }
    lc_codes_back();
}

$newCode = trim((string)($_SESSION['lighting_calculator_new_code'] ?? ''));
unset($_SESSION['lighting_calculator_new_code']);
$rows = $pdo->query('SELECT * FROM web_lighting_calculator_codes ORDER BY id DESC')->fetchAll() ?: [];
$stats = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(is_active=1 AND (expires_at IS NULL OR expires_at>NOW())) AS usable,
    SUM(activation_count) AS activations
    FROM web_lighting_calculator_codes")->fetch() ?: [];

admin_page_start('IES 计算器授权码', 'lighting_calculator_codes', $user);
admin_notice();
?>
<section class="lc-admin">
  <header class="lc-admin-head"><div><p>Technical tools</p><h1>IES 计算器授权码</h1><span>客户打开计算器页无需授权；选择或拖入 IES 文件时需要有效授权码。IES 文件仍只在客户浏览器内解析。</span></div><a class="admin-button-secondary" href="../lighting-calculator.php" target="_blank" rel="noopener">打开计算器</a></header>

  <div class="lc-admin-stats"><article><span>授权码总数</span><strong><?= (int)($stats['total'] ?? 0) ?></strong></article><article><span>当前可用</span><strong><?= (int)($stats['usable'] ?? 0) ?></strong></article><article><span>成功激活次数</span><strong><?= (int)($stats['activations'] ?? 0) ?></strong></article></div>

  <?php if ($newCode !== ''): ?><div class="lc-new-code"><div><strong>新授权码（只显示这一次）</strong><code id="lcNewCode"><?= web_e($newCode) ?></code></div><button class="admin-button" type="button" onclick="navigator.clipboard.writeText(document.getElementById('lcNewCode').textContent)">复制授权码</button></div><?php endif; ?>

  <form class="lc-create" method="post">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="create">
    <div><strong>生成客户授权码</strong><span>系统随机生成，数据库不保存可读明文。</span></div>
    <label class="field"><span>客户 / 公司</span><input name="customer_label" required maxlength="160" placeholder="例如 Seoul Lighting Design"></label>
    <label class="field"><span>有效期（可留空）</span><input name="expires_at" type="date" min="<?= web_e(date('Y-m-d', strtotime('+1 day'))) ?>"></label>
    <label class="field"><span>最大激活次数（可留空）</span><input name="max_activations" type="number" min="1" max="100000" placeholder="不限"></label>
    <button class="admin-button" type="submit">生成授权码</button>
  </form>

  <div class="lc-code-list">
    <?php if (!$rows): ?><div class="lc-empty">暂无授权码。</div><?php endif; ?>
    <?php foreach ($rows as $row):
      $expired = !empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time();
      $exhausted = $row['max_activations'] !== null && (int)$row['activation_count'] >= (int)$row['max_activations'];
      $usable = (int)$row['is_active'] === 1 && !$expired && !$exhausted;
    ?>
      <article class="lc-code-card">
        <div class="lc-code-main"><div><strong><?= web_e((string)$row['customer_label']) ?></strong><code><?= web_e((string)$row['code_hint']) ?></code></div><span class="<?= $usable ? 'is-usable' : '' ?>"><?= $usable ? '可用' : ((int)$row['is_active'] !== 1 ? '已停用' : ($expired ? '已过期' : '已用完')) ?></span></div>
        <dl><div><dt>激活次数</dt><dd><?= (int)$row['activation_count'] ?><?= $row['max_activations'] !== null ? ' / ' . (int)$row['max_activations'] : ' / 不限' ?></dd></div><div><dt>有效期</dt><dd><?= $row['expires_at'] ? web_e(substr((string)$row['expires_at'], 0, 10)) : '长期' ?></dd></div><div><dt>最近使用</dt><dd><?= $row['last_used_at'] ? web_e((string)$row['last_used_at']) : '尚未使用' ?></dd></div><div><dt>最近 IP</dt><dd><?= web_e((string)($row['last_used_ip'] ?: '-')) ?></dd></div></dl>
        <div class="lc-code-actions"><form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="admin-button-secondary" type="submit"><?= (int)$row['is_active'] === 1 ? '停用' : '启用' ?></button></form><form method="post" onsubmit="return confirm('确认删除这个授权码？')"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="admin-button-danger" type="submit">删除</button></form></div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<style>
.lc-admin{display:grid;gap:18px}.lc-admin-head{display:flex;align-items:flex-end;justify-content:space-between;gap:22px;padding:24px;border:1px solid #e1e3e6;background:#fff}.lc-admin-head p{margin:0 0 7px;color:#d71920;font-size:11px;font-weight:850;letter-spacing:.09em;text-transform:uppercase}.lc-admin-head h1{margin:0;color:#111;font-size:29px}.lc-admin-head span{display:block;max-width:780px;margin-top:8px;color:#70747a;font-size:13px;line-height:1.55}.lc-admin-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.lc-admin-stats article{display:grid;gap:7px;padding:18px;border:1px solid #e1e3e6;background:#fff}.lc-admin-stats span{color:#73777e;font-size:12px}.lc-admin-stats strong{font-size:27px}.lc-new-code{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:20px;border:2px solid #111;background:#f6f6f6}.lc-new-code div{display:grid;gap:8px}.lc-new-code code{font-size:22px;font-weight:850;letter-spacing:.08em}.lc-create{display:grid;grid-template-columns:minmax(220px,1.2fr) repeat(3,minmax(150px,1fr)) auto;gap:12px;align-items:end;padding:18px;border:1px solid #e1e3e6;background:#fff}.lc-create>div{display:grid;gap:4px;padding-bottom:5px}.lc-create>div span{color:#73777e;font-size:11px}.lc-code-list{display:grid;gap:12px}.lc-empty,.lc-code-card{padding:18px;border:1px solid #e1e3e6;background:#fff}.lc-code-main{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.lc-code-main>div{display:flex;align-items:center;gap:14px}.lc-code-main strong{font-size:18px}.lc-code-main code{color:#6c7077}.lc-code-main>span{padding:5px 9px;background:#eee;color:#777;font-size:11px;font-weight:800}.lc-code-main>span.is-usable{background:#e7f6eb;color:#147333}.lc-code-card dl{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}.lc-code-card dl div{display:grid;gap:5px}.lc-code-card dt{color:#777;font-size:11px}.lc-code-card dd{margin:0;font-size:13px;font-weight:700}.lc-code-actions{display:flex;justify-content:flex-end;gap:8px;padding-top:14px;border-top:1px solid #eceef0}.admin-button-danger{border:1px solid #d71920;background:#fff;color:#d71920;border-radius:4px;padding:8px 12px;font-weight:800;cursor:pointer}@media(max-width:1100px){.lc-create{grid-template-columns:repeat(2,1fr)}.lc-code-card dl{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.lc-admin-head,.lc-new-code{align-items:flex-start;flex-direction:column}.lc-admin-stats,.lc-create,.lc-code-card dl{grid-template-columns:1fr}.lc-code-main,.lc-code-main>div{align-items:flex-start;flex-direction:column}.lc-new-code code{font-size:16px}}
</style>
<?php admin_page_end(); ?>
