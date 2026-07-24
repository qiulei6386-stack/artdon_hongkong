<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/sync.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
$config = web_config();
$defaultGzUrl = (string)($config['sync']['guangzhou_api_url'] ?? 'http://119.91.27.19/artdon_erp/website_inquiry_staging_bridge.php');
$hkInboundEndpoint = (string)($config['sync']['hongkong_inbound_endpoint'] ?? 'http://43.132.210.162/api/internal.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: sync.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? 'save');
    try {
        if ($action === 'save') {
            $url = trim((string)($_POST['internal_api_url'] ?? $defaultGzUrl));
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('广州 API 地址格式不正确。');
            }
            web_setting_set($pdo, 'sync_enabled', !empty($_POST['sync_enabled']) ? '1' : '0');
            web_setting_set($pdo, 'internal_api_url', $url);
            web_setting_set($pdo, 'internal_api_timeout', (string)max(3, min(30, (int)($_POST['internal_api_timeout'] ?? 15))));
            web_setting_set($pdo, 'allowed_inbound_ip', trim((string)($_POST['allowed_inbound_ip'] ?? '119.91.27.19')));

            $outboundToken = trim((string)($_POST['internal_api_token'] ?? ''));
            if ($outboundToken !== '') { web_setting_set($pdo, 'internal_api_token', $outboundToken, true); }
            $incomingToken = trim((string)($_POST['incoming_api_token'] ?? ''));
            if ($incomingToken !== '') { web_setting_set($pdo, 'incoming_api_token', $incomingToken, true); }

            web_log($pdo, (int)$user['id'], 'update_sync_settings', 'system', 'dual_server_sync', [
                'enabled' => !empty($_POST['sync_enabled']),
                'internal_api_url' => $url,
                'allowed_inbound_ip' => trim((string)($_POST['allowed_inbound_ip'] ?? '')),
            ]);
            $_SESSION['admin_success'] = '同步设置已保存。';
        } elseif ($action === 'generate_tokens') {
            web_setting_set($pdo, 'internal_api_token', bin2hex(random_bytes(32)), true);
            web_setting_set($pdo, 'incoming_api_token', bin2hex(random_bytes(32)), true);
            web_setting_set($pdo, 'sync_cron_key', bin2hex(random_bytes(24)));
            web_log($pdo, (int)$user['id'], 'generate_sync_tokens', 'system', 'dual_server_sync');
            $_SESSION['admin_success'] = '新的 API Token 和定时任务密钥已生成。启用同步前，请把两个 Token 填入广州连接器配置。';
        } elseif ($action === 'test') {
            $result = web_sync_test_connection($pdo);
            if (!empty($result['ok'])) {
                $_SESSION['admin_success'] = '连接测试成功。广州接口返回：' . ($result['reference'] ?? 'OK');
            } else {
                $_SESSION['admin_error'] = '连接测试失败：' . ($result['message'] ?? '未知错误');
            }
        } elseif ($action === 'run') {
            $summary = web_sync_process_queue($pdo, 30);
            $_SESSION['admin_success'] = sprintf('队列处理完成：共 %d 条，成功 %d 条，失败 %d 条。', $summary['processed'], $summary['synced'], $summary['failed']);
        } elseif ($action === 'retry_all') {
            $count = web_sync_retry($pdo);
            $_SESSION['admin_success'] = $count . ' 条失败项目已重新放回待同步队列。';
        } elseif ($action === 'retry_one') {
            $id = (int)($_POST['queue_id'] ?? 0);
            $count = web_sync_retry($pdo, $id);
            $_SESSION['admin_success'] = $count ? '该项目已准备重新同步。' : '该项目状态没有变化。';
        } elseif ($action === 'cleanup') {
            $count = $pdo->exec("DELETE FROM web_sync_queue WHERE status='synced' AND synced_at<DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $pdo->exec("DELETE FROM web_sync_logs WHERE created_at<DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $_SESSION['admin_success'] = $count . ' 条超过 30 天的成功记录已清理。';
        }
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = $e->getMessage();
    }
    header('Location: sync.php');
    exit;
}

$settings = [
    'enabled' => web_sync_enabled($pdo),
    'url' => (string)web_setting_get($pdo, 'internal_api_url', $defaultGzUrl),
    'timeout' => web_setting_int($pdo, 'internal_api_timeout', 15),
    'allowed_ip' => (string)web_setting_get($pdo, 'allowed_inbound_ip', '119.91.27.19'),
    'outbound_token' => (string)web_setting_get($pdo, 'internal_api_token', ''),
    'incoming_token' => (string)web_setting_get($pdo, 'incoming_api_token', ''),
    'cron_key' => (string)web_setting_get($pdo, 'sync_cron_key', ''),
    'last_success' => (string)web_setting_get($pdo, 'sync_last_success_at', ''),
    'last_error' => (string)web_setting_get($pdo, 'sync_last_error', ''),
];
$stats = web_sync_queue_stats($pdo);
$queue = $pdo->query("SELECT id, event_type, external_key, status, attempts, next_attempt_at, remote_reference, last_error, created_at, synced_at FROM web_sync_queue ORDER BY id DESC LIMIT 100")->fetchAll();
$logs = $pdo->query("SELECT direction, event_type, result, http_status, message, created_at FROM web_sync_logs ORDER BY id DESC LIMIT 30")->fetchAll();

admin_page_start('双服务器同步 V4.4', 'sync', $user);
admin_notice();
?>

<div class="status-card <?= $settings['enabled'] ? 'status-ok' : 'status-warn' ?>">
  <strong><?= $settings['enabled'] ? '双服务器自动同步已启用' : '双服务器自动同步暂未启用' ?></strong><br>
  <span>香港官网使用本地数据库 <b>artdon_web</b>；广州内部系统使用 <b>artdon_erp</b>。双方只通过带签名的 API 交换数据，不跨服务器直连 MySQL。</span>
</div>

<div class="admin-grid admin-grid-5">
  <article class="admin-card admin-stat"><strong><?= (int)$stats['pending'] ?></strong><span>待同步</span></article>
  <article class="admin-card admin-stat"><strong><?= (int)$stats['processing'] ?></strong><span>同步中</span></article>
  <article class="admin-card admin-stat"><strong><?= (int)$stats['synced'] ?></strong><span>已同步</span></article>
  <article class="admin-card admin-stat"><strong><?= (int)$stats['failed'] ?></strong><span>失败</span></article>
  <article class="admin-card admin-stat"><strong><?= web_e($settings['last_success'] ?: '—') ?></strong><span>最近成功时间</span></article>
</div>

<form class="admin-card" method="post" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <h2>香港 → 广州</h2>
  <p>广州连接器文件直接放在 <code>/www/wwwroot/Artdon/</code>。新联动只进入官网询盘暂存池和任务提醒，不直接创建正式客户。</p>
  <div class="admin-form-grid">
    <div class="field full"><label class="inline-check"><input type="checkbox" name="sync_enabled" value="1"<?= $settings['enabled'] ? ' checked' : '' ?>> 启用自动同步</label></div>
    <div class="field full"><label>广州 API 地址</label><input name="internal_api_url" value="<?= web_e($settings['url']) ?>" placeholder="http://119.91.27.19/artdon_erp/website_inquiry_staging_bridge.php"><span class="help">当前正确地址：http://119.91.27.19/artdon_erp/website_inquiry_staging_bridge.php</span></div>
    <div class="field"><label>香港发往广州的 Token</label><input name="internal_api_token" value="" placeholder="留空表示保留当前 Token"><span class="help">香港端状态：<?= $settings['outbound_token'] !== '' ? '已配置' : '未配置' ?>。注意：这里显示已配置，不代表广州 website_bridge_config.php 已填入同一个 Token。</span></div>
    <div class="field"><label>请求超时</label><input type="number" min="3" max="30" name="internal_api_timeout" value="<?= (int)$settings['timeout'] ?>"><span class="help">单位：秒，建议 15 秒</span></div>
  </div>

  <hr class="admin-divider">
  <h2>广州 → 香港</h2>
  <div class="admin-form-grid">
    <div class="field"><label>广州发往香港的 Token</label><input name="incoming_api_token" value="" placeholder="留空表示保留当前 Token"><span class="help">香港端状态：<?= $settings['incoming_token'] !== '' ? '已配置' : '未配置' ?>。广州配置中的 outbound_to_hongkong_token 必须与此完全一致。</span></div>
    <div class="field"><label>允许访问的广州来源 IP</label><input name="allowed_inbound_ip" value="<?= web_e($settings['allowed_ip']) ?>"><span class="help">应填写：119.91.27.19</span></div>
    <div class="field full"><label>香港接收接口</label><input readonly value="<?= web_e($hkInboundEndpoint) ?>"><span class="help">广州配置中的 hongkong.api_url 应填写这个地址。</span></div>
  </div>
  <div class="admin-actions"><button class="admin-button" type="submit">保存同步设置</button></div>
</form>

<section class="admin-card">
  <h2>Token 与定时任务</h2>
  <p>Token 只需在首次配置或需要更换密钥时生成。重新生成后，必须同步更新广州配置，否则连接会立即失效。</p>
  <div class="secret-grid">
    <div><span>香港发往广州 Token</span><code><?= web_e($settings['outbound_token'] ?: '未配置') ?></code><?php if ($settings['outbound_token'] !== ''): ?><small>指纹：<?= web_e(substr(hash('sha256', $settings['outbound_token']), 0, 12)) ?></small><?php endif; ?></div>
    <div><span>广州发往香港 Token</span><code><?= web_e($settings['incoming_token'] ?: '未配置') ?></code><?php if ($settings['incoming_token'] !== ''): ?><small>指纹：<?= web_e(substr(hash('sha256', $settings['incoming_token']), 0, 12)) ?></small><?php endif; ?></div>
    <div><span>网页定时任务地址</span><code><?= web_e('http://43.132.210.162/sync_cron.php?key=' . $settings['cron_key']) ?></code></div>
    <div><span>命令行定时任务</span><code>php /www/wwwroot/43.132.210.162/cron/sync_worker.php</code></div>
  </div>
  <form method="post" class="admin-actions" onsubmit="return confirm('确定重新生成 Token 吗？生成后两台服务器必须重新填写 Token。');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="generate_tokens"><button class="admin-button-secondary" type="submit">重新生成 Token</button></form>
</section>

<section class="admin-card">
  <div class="admin-card-head"><div><h2>连接测试与队列操作（V4.4 已修复配置缓存并增加 Token 指纹诊断）</h2><p>客户询盘会先保存到香港数据库。即使广州暂时无法连接，询盘也不会丢失。</p></div></div>
  <?php if ($settings['last_error'] !== ''): ?><div class="notice notice-error">最近同步错误：<?= web_e($settings['last_error']) ?></div><?php endif; ?>
  <div class="admin-actions">
    <form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="test"><button class="admin-button" type="submit">测试广州连接</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="run"><button class="admin-button-secondary" type="submit">立即处理队列</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="retry_all"><button class="admin-button-secondary" type="submit">重试全部失败项目</button></form>
    <form method="post" onsubmit="return confirm('确定清理 30 天前的成功队列和 90 天前的同步日志吗？');"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="cleanup"><button class="admin-link-button" type="submit">清理旧记录</button></form>
  </div>
</section>

<section class="admin-card">
  <h2>同步队列</h2>
  <?php if (!$queue): ?><div class="empty">同步队列为空。</div><?php else: ?>
  <div class="table-scroll"><table class="admin-table"><thead><tr><th>ID / 时间</th><th>事件</th><th>状态</th><th>尝试次数</th><th>广州编号</th><th>错误 / 操作</th></tr></thead><tbody>
  <?php foreach ($queue as $row): ?>
    <tr>
      <td>#<?= (int)$row['id'] ?><br><span class="help"><?= web_e($row['created_at']) ?></span></td>
      <td><strong><?= web_e(admin_event_label((string)$row['event_type'])) ?></strong><br><span class="help"><?= web_e($row['external_key']) ?></span></td>
      <td><span class="badge badge-<?= web_e($row['status']) ?>"><?= web_e(admin_status_label((string)$row['status'])) ?></span><?php if($row['next_attempt_at']): ?><br><span class="help">下次重试：<?= web_e($row['next_attempt_at']) ?></span><?php endif; ?></td>
      <td><?= (int)$row['attempts'] ?></td>
      <td><?= web_e($row['remote_reference'] ?: '—') ?></td>
      <td><?= web_e($row['last_error'] ?: '—') ?><?php if($row['status']==='failed'): ?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>"><input type="hidden" name="action" value="retry_one"><input type="hidden" name="queue_id" value="<?= (int)$row['id'] ?>"><button class="admin-link-button" type="submit">重新同步</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>

<section class="admin-card">
  <h2>最近同步日志</h2>
  <?php if (!$logs): ?><div class="empty">暂无同步日志。</div><?php else: ?>
  <div class="table-scroll"><table class="admin-table"><thead><tr><th>时间</th><th>方向</th><th>事件</th><th>结果</th><th>信息</th></tr></thead><tbody>
  <?php foreach ($logs as $row): ?><tr><td><?= web_e($row['created_at']) ?></td><td><?= web_e(admin_status_label((string)$row['direction'])) ?></td><td><?= web_e(admin_event_label((string)$row['event_type'])) ?></td><td><span class="badge badge-<?= web_e($row['result']) ?>"><?= web_e(admin_status_label((string)$row['result'])) ?><?= $row['http_status'] ? ' '.$row['http_status'] : '' ?></span></td><td><?= web_e($row['message']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>

<?php admin_page_end(); ?>
