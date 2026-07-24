<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/sync.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_dashboard_helpers.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);

$syncStats = web_sync_queue_stats($pdo);
$syncEnabled = web_sync_enabled($pdo);
$lastSuccess = (string)web_setting_get($pdo, 'sync_last_success_at', '');
$lastError = (string)web_setting_get($pdo, 'sync_last_error', '');

$seriesTotal = admin_dash_count($pdo, 'web_products');
$seriesLive = admin_dash_column_exists($pdo, 'web_products', 'is_published') ? admin_dash_count($pdo, 'web_products', 'is_published=1') : $seriesTotal;
$modelTotal = admin_dash_count($pdo, 'web_product_variants');
$modelLive = admin_dash_column_exists($pdo, 'web_product_variants', 'is_published') ? admin_dash_count($pdo, 'web_product_variants', 'is_published=1') : $modelTotal;
$mediaTotal = admin_dash_count($pdo, 'web_media');
$newInquiries = admin_dash_count($pdo, 'web_inquiries', "status='new'");
$todayInquiries = admin_dash_count($pdo, 'web_inquiries', 'DATE(created_at)=CURDATE()');
$syncPending = (int)($syncStats['pending'] ?? 0);
$syncFailed = (int)($syncStats['failed'] ?? 0);
$homeLive = admin_dash_table_exists($pdo, 'artdon_home_product_slots_v713')
    ? admin_dash_count($pdo, 'artdon_home_product_slots_v713', 'is_active=1')
    : 0;

$latestInquiries = admin_dash_rows($pdo, "SELECT id,name,email,company,country,support_type,product,status,sync_status,internal_reference,dispatch_task_id,created_at
    FROM web_inquiries ORDER BY id DESC LIMIT 8");
$latestLogs = admin_dash_table_exists($pdo, 'web_admin_logs')
    ? admin_dash_rows($pdo, "SELECT l.id,l.action,l.target_type,l.target_key,l.created_at,COALESCE(u.display_name,u.username,'系统') AS actor
        FROM web_admin_logs l LEFT JOIN web_admin_users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 8")
    : [];
$latestSync = admin_dash_table_exists($pdo, 'web_sync_queue')
    ? admin_dash_rows($pdo, "SELECT id,event_type,status,attempts,last_error,updated_at,remote_reference
        FROM web_sync_queue WHERE status IN ('pending','failed','processing') ORDER BY updated_at DESC,id DESC LIMIT 6")
    : [];

$quality = [];
if ($seriesTotal > 0 && admin_dash_column_exists($pdo, 'web_products', 'cover_image')) {
    $quality[] = [
        'count'=>admin_dash_count($pdo, 'web_products', "TRIM(COALESCE(cover_image,''))=''"),
        'title'=>'系列缺少封面图',
        'text'=>'会影响产品目录和首页发布效果。',
        'href'=>admin_nav_file_exists('products.php') ? 'products.php' : '#',
    ];
}
if ($modelTotal > 0 && admin_dash_column_exists($pdo, 'web_product_variants', 'cover_image')) {
    $quality[] = [
        'count'=>admin_dash_count($pdo, 'web_product_variants', "TRIM(COALESCE(cover_image,''))=''"),
        'title'=>'具体产品缺少主图',
        'text'=>'筛选结果与产品详情可能出现空白。',
        'href'=>admin_nav_file_exists('product_models.php') ? 'product_models.php' : 'product_variants.php',
    ];
}
if ($modelTotal > 0 && admin_dash_column_exists($pdo, 'web_product_variants', 'dimension_image')) {
    $quality[] = [
        'count'=>admin_dash_count($pdo, 'web_product_variants', "TRIM(COALESCE(dimension_image,''))=''"),
        'title'=>'具体产品缺少尺寸图',
        'text'=>'打印产品资料时技术图区域不完整。',
        'href'=>admin_nav_file_exists('product_models.php') ? 'product_models.php' : 'product_variants.php',
    ];
}
if ($modelTotal > 0 && admin_dash_column_exists($pdo, 'web_product_variants', 'datasheet_path')) {
    $quality[] = [
        'count'=>admin_dash_count($pdo, 'web_product_variants', "TRIM(COALESCE(datasheet_path,''))=''"),
        'title'=>'具体产品缺少规格书',
        'text'=>'下载中心和详情页技术资料不完整。',
        'href'=>admin_nav_file_exists('product_models.php') ? 'product_models.php' : 'product_variants.php',
    ];
}
if ($modelTotal > 0 && admin_dash_table_exists($pdo, 'web_product_variant_filter_values')) {
    $missingFilter = (int)admin_dash_scalar($pdo, "SELECT COUNT(*) FROM web_product_variants v
        LEFT JOIN (SELECT DISTINCT variant_id FROM web_product_variant_filter_values) f ON f.variant_id=v.id
        WHERE f.variant_id IS NULL", [], 0);
    $quality[] = [
        'count'=>$missingFilter,
        'title'=>'具体产品未绑定筛选',
        'text'=>'客户使用筛选时不会找到这些型号。',
        'href'=>admin_nav_file_exists('product_filters.php') ? 'product_filters.php' : '#',
    ];
}
$quality = array_slice($quality, 0, 5);

$uploadsDir = dirname(__DIR__) . '/uploads';
$storageWritable = is_dir($uploadsDir) ? is_writable($uploadsDir) : is_writable(dirname(__DIR__));
$syncState = !$syncEnabled ? 'warn' : ($lastError !== '' ? 'error' : ($lastSuccess !== '' ? 'ok' : 'warn'));
$syncStateText = !$syncEnabled ? '未启用' : ($lastError !== '' ? '存在错误' : ($lastSuccess !== '' ? '运行正常' : '等待首次同步'));

admin_page_start('工作台', 'dashboard', $user);
admin_notice();
?>
<div class="v7-dashboard">
  <section class="admin-card v7-dashboard-hero">
    <div>
      <p class="v7-dashboard-kicker">ARTDON WEBSITE CONTROL CENTER</p>
      <h2>官网内容、产品与客户联动工作台</h2>
      <p>香港官网使用本地数据库 <strong>artdon_web</strong>。广州 CRM／派工继续通过 HTTPS API 和同步队列联动，本工作台只读取状态，不会自动修改 Token、队列或广州数据。</p>
    </div>
    <div class="v7-dashboard-quick">
      <?php if(admin_nav_file_exists('product_edit.php')): ?><a class="admin-button" href="product_edit.php">新增产品系列</a><?php endif; ?>
      <?php if(admin_nav_file_exists('product_models.php')): ?><a class="admin-button-secondary" href="product_models.php?action=new">新增具体产品</a><?php endif; ?>
      <a class="admin-button-secondary" href="homepage.php">编辑首页</a>
    </div>
  </section>

  <div class="v7-status-strip">
    <article class="v7-status-card is-ok">
      <span class="v7-status-icon"><?= admin_icon('storage') ?></span>
      <span class="v7-status-copy"><strong>香港数据库</strong><small>artdon_web · 本地连接正常</small></span>
      <span class="v7-status-value">正常</span>
    </article>
    <article class="v7-status-card <?= $storageWritable ? 'is-ok' : 'is-error' ?>">
      <span class="v7-status-icon"><?= admin_icon('media') ?></span>
      <span class="v7-status-copy"><strong>上传与媒体目录</strong><small><?= $storageWritable ? '目录可写，可正常保存图片与资料' : '目录不可写，请检查宝塔权限' ?></small></span>
      <span class="v7-status-value"><?= $storageWritable ? '可写' : '异常' ?></span>
    </article>
    <article class="v7-status-card is-<?= web_e($syncState) ?>">
      <span class="v7-status-icon"><?= admin_icon('sync') ?></span>
      <span class="v7-status-copy"><strong>广州 CRM／派工联动</strong><small><?= web_e($lastSuccess !== '' ? '最近成功：'.admin_dash_time_label($lastSuccess) : '119.91.27.19 · '.$syncStateText) ?></small></span>
      <span class="v7-status-value"><?= web_e($syncStateText) ?></span>
    </article>
  </div>

  <div class="v7-stat-grid">
    <a class="v7-stat-card" href="<?= admin_nav_file_exists('products.php') ? 'products.php' : '#' ?>">
      <span>产品系列</span><strong><?= $seriesTotal ?></strong><small><?= $seriesLive ?> 个已发布</small><span class="admin-nav-icon"><?= admin_icon('series') ?></span>
    </a>
    <a class="v7-stat-card" href="<?= admin_nav_file_exists('product_models.php') ? 'product_models.php' : (admin_nav_file_exists('product_variants.php') ? 'product_variants.php' : '#') ?>">
      <span>具体产品</span><strong><?= $modelTotal ?></strong><small><?= $modelLive ?> 个已发布</small><span class="admin-nav-icon"><?= admin_icon('model') ?></span>
    </a>
    <a class="v7-stat-card" href="media.php">
      <span>媒体资料</span><strong><?= $mediaTotal ?></strong><small>图片、视频与技术文件</small><span class="admin-nav-icon"><?= admin_icon('media') ?></span>
    </a>
    <a class="v7-stat-card" href="inquiries.php">
      <span>新客户询盘</span><strong><?= $newInquiries ?></strong><small>今日新增 <?= $todayInquiries ?> 条</small><span class="admin-nav-icon"><?= admin_icon('inquiry') ?></span>
    </a>
    <a class="v7-stat-card" href="sync.php">
      <span>同步待处理</span><strong><?= $syncPending + $syncFailed ?></strong><small><?= $syncPending ?> 待同步 · <?= $syncFailed ?> 失败</small><span class="admin-nav-icon"><?= admin_icon('sync') ?></span>
    </a>
    <?php if($homeLive > 0): ?>
    <a class="v7-stat-card" href="home_products.php">
      <span>首页产品发布</span><strong><?= $homeLive ?></strong><small>正在首页展示</small><span class="admin-nav-icon"><?= admin_icon('publish') ?></span>
    </a>
    <?php endif; ?>
  </div>

  <div class="v7-dashboard-grid">
    <section class="admin-card">
      <div class="v7-panel-title"><div><h2>最新官网询盘</h2><p>查看客户信息以及 CRM／派工回写状态。</p></div><a href="inquiries.php">查看全部</a></div>
      <?php if(!$latestInquiries): ?>
        <div class="empty">暂无客户询盘。</div>
      <?php else: ?>
      <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>提交时间</th><th>客户</th><th>需求</th><th>官网状态</th><th>CRM / 派工</th></tr></thead>
          <tbody>
          <?php foreach($latestInquiries as $row):
              $need = trim((string)($row['product'] ?? '')) ?: trim((string)($row['support_type'] ?? ''));
              $linked = !empty($row['dispatch_task_id']) || !empty($row['internal_reference']);
          ?>
            <tr>
              <td><strong><?= web_e(date('m-d H:i', strtotime((string)$row['created_at']))) ?></strong><br><span class="help">#<?= (int)$row['id'] ?></span></td>
              <td><strong><?= web_e((string)$row['name']) ?></strong><br><span class="help"><?= web_e((string)$row['email']) ?><?= trim((string)($row['company'] ?? ''))!==''?' · '.web_e((string)$row['company']):'' ?></span></td>
              <td><?= web_e(admin_dash_short_text($need, 48)) ?></td>
              <td><span class="badge <?= ($row['status'] ?? '') === 'new' ? 'badge-new' : '' ?>"><?= web_e(admin_status_label((string)$row['status'])) ?></span></td>
              <td><span class="sync-pill <?= web_e((string)$row['sync_status']) ?>"><?= web_e($linked ? '已联动' : admin_status_label((string)$row['sync_status'])) ?></span><?php if(!empty($row['dispatch_task_id'])): ?><br><span class="help">派工 #<?= (int)$row['dispatch_task_id'] ?></span><?php elseif(!empty($row['internal_reference'])): ?><br><span class="help"><?= web_e((string)$row['internal_reference']) ?></span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <div>
      <section class="admin-card">
        <div class="v7-panel-title"><div><h2>产品资料完整度</h2><p>优先补齐会影响客户浏览和下载的资料。</p></div><a href="<?= admin_nav_file_exists('product_center.php') ? 'product_center.php' : 'products.php' ?>">产品中心</a></div>
        <?php if(!$quality): ?><div class="empty">暂无可检查的产品资料。</div><?php else: ?><div class="v7-quality-list">
          <?php foreach($quality as $item): ?>
          <a class="v7-quality-item" href="<?= web_e((string)$item['href']) ?>"><span><?= (int)$item['count'] ?></span><span><strong><?= web_e((string)$item['title']) ?></strong><small><?= web_e((string)$item['text']) ?></small></span><em><?= (int)$item['count'] > 0 ? '待处理' : '完整' ?></em></a>
          <?php endforeach; ?>
        </div><?php endif; ?>
      </section>

      <section class="admin-card">
        <div class="v7-panel-title"><div><h2>广州同步状态</h2><p>这里只显示现状，不会自动测试或重发。</p></div><a href="sync.php">打开同步中心</a></div>
        <?php if($lastError !== ''): ?><div class="v7-sync-alert"><?= web_e(admin_dash_short_text($lastError, 240)) ?></div><?php endif; ?>
        <?php if(!$latestSync): ?><div class="empty">当前没有待处理或失败队列。</div><?php else: ?><div class="v7-sync-list">
          <?php foreach($latestSync as $row): ?>
          <div class="v7-sync-item"><span class="sync-pill <?= web_e((string)$row['status']) ?>"><?= web_e(admin_status_label((string)$row['status'])) ?></span><span><strong><?= web_e(admin_event_label((string)$row['event_type'])) ?></strong><small>#<?= (int)$row['id'] ?> · 尝试 <?= (int)$row['attempts'] ?> 次<?= !empty($row['last_error']) ? ' · '.web_e(admin_dash_short_text((string)$row['last_error'], 60)) : '' ?></small></span></div>
          <?php endforeach; ?>
        </div><?php endif; ?>
      </section>
    </div>
  </div>

  <section class="admin-card">
    <div class="v7-panel-title"><div><h2>最近操作</h2><p>后台内容与设置的最近变更记录。</p></div><a href="logs.php">查看完整日志</a></div>
    <?php if(!$latestLogs): ?><div class="empty">暂无操作日志。</div><?php else: ?><div class="v7-activity-list">
      <?php foreach($latestLogs as $row): ?>
      <div class="v7-activity-item"><i></i><span><strong><?= web_e(admin_action_label((string)$row['action'])) ?></strong><small><?= web_e((string)$row['actor']) ?><?= trim((string)$row['target_key'])!==''?' · '.web_e((string)$row['target_key']):'' ?></small></span><time><?= web_e(admin_dash_time_label((string)$row['created_at'])) ?></time></div>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </section>
</div>
<?php admin_page_end(); ?>
