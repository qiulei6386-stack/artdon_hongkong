<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
$user = web_require_admin($pdo);
$config = web_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: storage.php');
        exit;
    }
    try {
        // 当前阶段固定使用本地存储。COS 参数只先保存，不立即启用。
        web_setting_set($pdo, 'storage_active_driver', 'local');
        web_setting_set($pdo, 'storage_cos_region', trim((string)($_POST['cos_region'] ?? '')));
        web_setting_set($pdo, 'storage_cos_bucket', trim((string)($_POST['cos_bucket'] ?? '')));
        web_setting_set($pdo, 'storage_cos_public_url', rtrim(trim((string)($_POST['cos_public_url'] ?? '')), '/'));
        web_setting_set($pdo, 'storage_cos_prefix', trim((string)($_POST['cos_prefix'] ?? 'website')));

        $secretId = trim((string)($_POST['cos_secret_id'] ?? ''));
        $secretKey = trim((string)($_POST['cos_secret_key'] ?? ''));
        if ($secretId !== '') {
            web_setting_set($pdo, 'storage_cos_secret_id', $secretId, true);
        }
        if ($secretKey !== '') {
            web_setting_set($pdo, 'storage_cos_secret_key', $secretKey, true);
        }
        web_log($pdo, (int)$user['id'], 'update_storage_settings', 'storage_settings', 'local_cos_reserved');
        $_SESSION['admin_success'] = '存储设置已保存。当前仍使用香港服务器本地存储。';
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
    }
    header('Location: storage.php');
    exit;
}

$baseDir = rtrim((string)($config['upload_dir'] ?? ''), '/\\');
$baseUrl = rtrim((string)($config['upload_url'] ?? ''), '/');
$diskFree = is_dir($baseDir) ? @disk_free_space($baseDir) : false;
$diskTotal = is_dir($baseDir) ? @disk_total_space($baseDir) : false;
$secretIdConfigured = (string)web_setting_get($pdo, 'storage_cos_secret_id', '') !== '';
$secretKeyConfigured = (string)web_setting_get($pdo, 'storage_cos_secret_key', '') !== '';

admin_page_start('存储设置', 'storage', $user);
admin_notice();
?>
<section class="admin-grid storage-summary-grid">
    <article class="admin-card admin-stat">
        <span>当前存储模式</span>
        <strong>本地存储</strong>
        <small>香港服务器 43.132.210.162</small>
    </article>
    <article class="admin-card admin-stat">
        <span>本地上传目录</span>
        <strong class="storage-path-strong"><?= web_e($baseDir) ?></strong>
        <small>数据库只保存相对路径</small>
    </article>
    <article class="admin-card admin-stat">
        <span>服务器剩余空间</span>
        <strong><?= $diskFree !== false ? web_e(number_format($diskFree / 1073741824, 1)) . ' GB' : '无法读取' ?></strong>
        <small><?= $diskTotal !== false ? '总容量 ' . web_e(number_format($diskTotal / 1073741824, 1)) . ' GB' : '' ?></small>
    </article>
</section>

<section class="admin-card">
    <h2>当前本地存储规则</h2>
    <p>系统已按用途自动分目录，并在目录下面继续按年份、月份保存。以后迁移到 COS 时，数据库中的相对路径可以继续沿用。</p>
    <div class="storage-rule-grid">
        <?php foreach (web_media_usage_map() as $usage => $item): ?>
            <div>
                <strong><?= web_e($item['label']) ?></strong>
                <code><?= web_e($baseUrl . '/' . $item['dir'] . '/YYYY/MM/') ?></code>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<form class="admin-card" method="post">
    <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">
    <h2>腾讯 COS 预留参数</h2>
    <p>现在不用启用 COS。可以先留空，等网站和后台全部完成、开始批量上传产品资料前再填写。当前保存这些参数不会改变上传位置。</p>
    <div class="notice notice-success">当前固定使用本地存储，不会因为误填 COS 参数导致官网图片失效。</div>
    <div class="admin-form-grid">
        <div class="field">
            <label>COS 地域 Region</label>
            <input name="cos_region" value="<?= web_e(web_setting_get($pdo, 'storage_cos_region', '')) ?>" placeholder="例如 ap-hongkong">
        </div>
        <div class="field">
            <label>存储桶 Bucket</label>
            <input name="cos_bucket" value="<?= web_e(web_setting_get($pdo, 'storage_cos_bucket', '')) ?>" placeholder="例如 artdon-media-1250000000">
        </div>
        <div class="field full">
            <label>公开访问域名 / CDN 域名</label>
            <input name="cos_public_url" value="<?= web_e(web_setting_get($pdo, 'storage_cos_public_url', '')) ?>" placeholder="例如 https://media.artdonlighting.com">
        </div>
        <div class="field">
            <label>文件前缀</label>
            <input name="cos_prefix" value="<?= web_e(web_setting_get($pdo, 'storage_cos_prefix', 'website')) ?>" placeholder="website">
        </div>
        <div class="field">
            <label>SecretId</label>
            <input type="password" name="cos_secret_id" autocomplete="new-password" placeholder="<?= $secretIdConfigured ? '已保存，留空保持不变' : '以后接入 COS 时填写' ?>">
        </div>
        <div class="field">
            <label>SecretKey</label>
            <input type="password" name="cos_secret_key" autocomplete="new-password" placeholder="<?= $secretKeyConfigured ? '已保存，留空保持不变' : '以后接入 COS 时填写' ?>">
        </div>
    </div>
    <div class="admin-actions">
        <button class="admin-button" type="submit">保存预留参数</button>
        <a class="admin-button-secondary" href="media.php">返回媒体资料库</a>
    </div>
</form>
<?php admin_page_end(); ?>
