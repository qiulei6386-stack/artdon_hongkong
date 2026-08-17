<?php

declare(strict_types=1);

/**
 * Artdon Web Admin V7 shell.
 *
 * This file intentionally changes presentation only. It does not connect to
 * Guangzhou, write sync settings, alter tokens or modify business tables.
 */

function admin_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v9h-6zM4 14h6v6H4zM14 17h6v3h-6z"/></svg>',
        'home' => '<svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v9h-6v-6H9v6H3z"/></svg>',
        'footer' => '<svg viewBox="0 0 24 24"><path d="M3 4h18v12H3zM3 19h18M8 16v3M16 16v3"/></svg>',
        'spark' => '<svg viewBox="0 0 24 24"><path d="m12 2 1.5 5.1L19 9l-5.5 1.9L12 16l-1.5-5.1L5 9l5.5-1.9L12 2ZM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/></svg>',
        'products' => '<svg viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>',
        'series' => '<svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="7" cy="6" r="1"/><circle cx="7" cy="12" r="1"/><circle cx="7" cy="18" r="1"/></svg>',
        'model' => '<svg viewBox="0 0 24 24"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/></svg>',
        'filter' => '<svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5Z"/></svg>',
        'publish' => '<svg viewBox="0 0 24 24"><path d="M12 16V3m0 0L7 8m5-5 5 5M4 14v6h16v-6"/></svg>',
        'category' => '<svg viewBox="0 0 24 24"><path d="M4 5h6v6H4zM14 5h6v6h-6zM4 15h6v4H4zM14 15h6v4h-6z"/></svg>',
        'inquiry' => '<svg viewBox="0 0 24 24"><path d="M4 4h16v12H8l-4 4V4Z"/><path d="M8 8h8M8 12h5"/></svg>',
        'routing' => '<svg viewBox="0 0 24 24"><path d="M5 4v5c0 2 1 3 3 3h8M12 8l4 4-4 4M5 20v-3"/></svg>',
        'media' => '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="1"/><circle cx="8" cy="9" r="2"/><path d="m4 18 5-5 3 3 3-4 5 6"/></svg>',
        'storage' => '<svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19 13.5V10.5l-2-.7-.6-1.4.9-1.9-2.1-2.1-1.9.9-1.4-.6-.7-2H10.5l-.7 2-1.4.6-1.9-.9-2.1 2.1.9 1.9-.6 1.4-2 .7v3l2 .7.6 1.4-.9 1.9 2.1 2.1 1.9-.9 1.4.6.7 2h3l.7-2 1.4-.6 1.9.9 2.1-2.1-.9-1.9.6-1.4 2-.7Z"/></svg>',
        'sync' => '<svg viewBox="0 0 24 24"><path d="M20 7h-7l2.7-2.7M4 17h7l-2.7 2.7M18.5 15A7 7 0 0 1 7 18M5.5 9A7 7 0 0 1 17 6"/></svg>',
        'logs' => '<svg viewBox="0 0 24 24"><path d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h4"/></svg>',
        'accounts' => '<svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c.6-4.2 2.6-6 6-6s5.4 1.8 6 6M16 8h5M18.5 5.5v5"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 4.8-3 8.1-8 9.8C7 20.1 4 16.8 4 12V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>',
        'activity' => '<svg viewBox="0 0 24 24"><path d="M3 12h4l2-6 4 12 2-6h6"/></svg>',
        'external' => '<svg viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M18 13v7H4V6h7"/></svg>',
        'search' => '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>',
        'user' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c.8-5 3.4-7 8-7s7.2 2 8 7"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24"><path d="M10 4H4v16h6M14 8l4 4-4 4M8 12h10"/></svg>',
        'collapse' => '<svg viewBox="0 0 24 24"><path d="m14 6-6 6 6 6M20 4v16"/></svg>',
    ];
    return $icons[$name] ?? $icons['chevron'];
}

function admin_nav_groups(): array
{
    return [
        [
            'key' => 'workspace',
            'label' => '工作台',
            'items' => [
                ['key'=>'dashboard','label'=>'工作台','href'=>'index.php','icon'=>'dashboard','permission'=>'dashboard.view'],
            ],
        ],
        [
            'key' => 'content',
            'label' => '内容中心',
            'items' => [
                ['key'=>'homepage','label'=>'首页编排','href'=>'homepage.php','icon'=>'home','permission'=>'homepage.view'],
                ['key'=>'solutions_page','label'=>'Solutions 页面','href'=>'solutions_page.php','icon'=>'spark','permission'=>'homepage.view'],
                ['key'=>'solutions_retail_page','label'=>'Retail 方案页','href'=>'solutions_retail_page.php','icon'=>'spark','permission'=>'homepage.view'],
                ['key'=>'retail_applications','label'=>'Solution Applications','href'=>'retail_applications.php','icon'=>'spark','permission'=>'homepage.view'],
                ['key'=>'project_details','label'=>'Projects 详情页','href'=>'project_details.php','icon'=>'spark','permission'=>'homepage.view'],
                ['key'=>'about_pages','label'=>'About Us 页面管理','href'=>'about_pages.php','icon'=>'spark','permission'=>'homepage.view'],
                ['key'=>'contact_page','label'=>'Contact 页面管理','href'=>'contact_page.php','icon'=>'inquiry','permission'=>'homepage.view'],
                ['key'=>'footer','label'=>'页脚管理','href'=>'footer.php','icon'=>'footer','permission'=>'footer.view'],
                ['key'=>'solution_icons','label'=>'应用图标库','href'=>'solution_icons.php','icon'=>'spark','permission'=>'solution_icons.view'],
            ],
        ],
        [
            'key' => 'product',
            'label' => '产品中心',
            'items' => [
                ['key'=>'product_center','label'=>'产品总览','href'=>'product_center.php','icon'=>'products','permission'=>'products.view'],
                ['key'=>'products','label'=>'系列管理','href'=>'products.php','icon'=>'series','permission'=>'products.view'],
                ['key'=>'product_models','label'=>'具体产品','href'=>'product_models.php','icon'=>'model','permission'=>'products.view'],
                ['key'=>'product_filters','label'=>'筛选库','href'=>'product_filters.php','icon'=>'filter','permission'=>'filters.view'],
                ['key'=>'product_accessories','label'=>'共用配件库','href'=>'products.php?panel=accessories','icon'=>'category','permission'=>'products.view'],
                ['key'=>'home_products','label'=>'推荐到首页','href'=>'home_products.php','icon'=>'publish','permission'=>'home_products.view'],
                ['key'=>'product_categories','label'=>'产品分类','href'=>'product_categories.php','icon'=>'category','permission'=>'categories.view'],
                ['key'=>'bulk_io','label'=>'导入 / 导出','href'=>'product_bulk_io.php','icon'=>'sync','permission'=>'products.view'],
            ],
        ],
        [
            'key' => 'customer',
            'label' => '客户中心',
            'items' => [
                ['key'=>'inquiries','label'=>'官网询盘','href'=>'inquiries.php','icon'=>'inquiry','permission'=>'inquiries.view'],
                ['key'=>'inquiry_spam','label'=>'广告询盘拦截','href'=>'inquiry_spam.php','icon'=>'shield','permission'=>'inquiries.view'],
                ['key'=>'routing','label'=>'自动分配与派工','href'=>'inquiry_routing.php','icon'=>'routing','permission'=>'routing.view'],
            ],
        ],
        [
            'key' => 'resource',
            'label' => '资源中心',
            'items' => [
                ['key'=>'media','label'=>'媒体资料库','href'=>'media.php','icon'=>'media','permission'=>'media.view'],
                ['key'=>'resources_pages','label'=>'Resources 页面管理','href'=>'resources_pages.php','icon'=>'settings','permission'=>'homepage.view'],
                ['key'=>'resources_blog','label'=>'Blog & Insights','href'=>'resources_blog.php','icon'=>'logs','permission'=>'homepage.view'],
                ['key'=>'resources_faq','label'=>'FAQ 管理','href'=>'resources_faq.php','icon'=>'inquiry','permission'=>'homepage.view'],
                ['key'=>'resources_videos','label'=>'Videos 管理','href'=>'resources_videos.php','icon'=>'media','permission'=>'homepage.view'],
                ['key'=>'lighting_calculator_codes','label'=>'IES 计算器授权','href'=>'lighting_calculator_codes.php','icon'=>'shield','permission'=>'settings.view'],
                ['key'=>'storage','label'=>'存储状态','href'=>'storage.php','icon'=>'storage','permission'=>'storage.view'],
            ],
        ],
        [
            'key' => 'system',
            'label' => '系统管理',
            'items' => [
                ['key'=>'settings','label'=>'网站设置','href'=>'settings.php','icon'=>'settings','permission'=>'settings.view'],
                ['key'=>'sync','label'=>'双服务器同步','href'=>'sync.php','icon'=>'sync','permission'=>'sync.view'],
                ['key'=>'users','label'=>'账号管理','href'=>'users.php','icon'=>'accounts','permission'=>'users.view'],
                ['key'=>'roles','label'=>'角色与权限','href'=>'roles.php','icon'=>'shield','permission'=>'roles.view'],
                ['key'=>'visitor_analytics','label'=>'网站访问日志','href'=>'visitor_analytics.php','icon'=>'activity','permission'=>'logs.view'],
                ['key'=>'logs','label'=>'日志中心','href'=>'logs.php','icon'=>'activity','permission'=>'logs.view'],
            ],
        ],
    ];
}

function admin_route_key(string $fallback): string
{
    $file = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $map = [
        'index.php'=>'dashboard',
        'homepage.php'=>'homepage', 'save_homepage.php'=>'homepage',
        'solutions_page.php'=>'solutions_page', 'save_solutions_page.php'=>'solutions_page',
        'solutions_retail_page.php'=>'solutions_retail_page', 'save_solutions_retail_page.php'=>'solutions_retail_page',
        'retail_applications.php'=>'retail_applications', 'save_retail_application.php'=>'retail_applications', 'retail_applications_api.php'=>'retail_applications',
        'project_details.php'=>'project_details', 'save_project_detail.php'=>'project_details', 'project_action.php'=>'project_details',
        'about_pages.php'=>'about_pages', 'save_about_page.php'=>'about_pages',
        'contact_page.php'=>'contact_page', 'save_contact_page.php'=>'contact_page',
        'footer.php'=>'footer', 'solution_icons.php'=>'solution_icons',
        'product_center.php'=>'product_center',
        'products.php'=>((string)($_GET['panel'] ?? '')==='accessories'?'product_accessories':'products'), 'product_edit.php'=>'products', 'product_series_page.php'=>'products',
        'product_models.php'=>'product_models', 'product_variants.php'=>'product_models', 'product_variant_edit.php'=>'product_models',
                'product_filters.php'=>'product_filters',
        'home_products.php'=>'home_products', 'home_product_edit.php'=>'home_products',
        'product_categories.php'=>'product_categories',
        'product_bulk_io.php'=>'bulk_io',
        'inquiries.php'=>'inquiries', 'inquiry_spam.php'=>'inquiry_spam', 'inquiry_routing.php'=>'routing',
        'resources_pages.php'=>'resources_pages', 'save_resources_page.php'=>'resources_pages',
        'resources_blog.php'=>'resources_blog', 'resources_blog_categories.php'=>'resources_blog', 'save_resources_blog.php'=>'resources_blog',
        'resources_faq.php'=>'resources_faq', 'save_resources_faq.php'=>'resources_faq',
        'resources_videos.php'=>'resources_videos', 'save_resources_video.php'=>'resources_videos',
        'lighting_calculator_codes.php'=>'lighting_calculator_codes',
        'media.php'=>'media', 'media_crop.php'=>'media',
        'storage.php'=>'storage', 'settings.php'=>'settings', 'sync.php'=>'sync',
        'users.php'=>'users', 'user_edit.php'=>'users',
        'roles.php'=>'roles', 'role_edit.php'=>'roles',
        'visitor_analytics.php'=>'visitor_analytics',
        'logs.php'=>'logs', 'log_detail.php'=>'logs',
        'profile.php'=>'profile',
    ];
    return $map[$file] ?? $fallback;
}

function admin_nav_file_exists(string $href): bool
{
    if ($href === '' || str_contains($href, '://')) return true;
    $file = strtok($href, '?') ?: $href;
    return is_file(__DIR__ . '/' . ltrim($file, '/'));
}

function admin_user_initial(array $user): string
{
    $name = trim((string)($user['display_name'] ?? $user['username'] ?? 'A'));
    if ($name === '') return 'A';
    if (function_exists('mb_substr')) return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    return strtoupper(substr($name, 0, 1));
}

function admin_page_start(string $title, string $active, array $user): void
{
    $route = admin_route_key($active);
    $groups = admin_nav_groups();
    $availableGroups = [];
    $groupLabel = '工作台';
    $commandItems = [];

    foreach ($groups as $group) {
        $items = [];
        foreach ($group['items'] as $item) {
            if (!admin_nav_file_exists((string)$item['href'])) continue;
            if (!web_admin_user_can($user, (string)($item['permission'] ?? ''))) continue;
            $items[] = $item;
            $commandItems[] = $item + ['group_label'=>$group['label']];
            if ($item['key'] === $route) $groupLabel = (string)$group['label'];
        }
        if ($items) {
            $group['items'] = $items;
            $availableGroups[] = $group;
        }
    }

    $GLOBALS['admin_v7_command_items'] = $commandItems;
    $GLOBALS['admin_v7_user'] = $user;
    $GLOBALS['admin_v7_title'] = $title;
    $GLOBALS['admin_v7_route'] = $route;
    $displayName = (string)($user['display_name'] ?? $user['username'] ?? '管理员');
    $username = (string)($user['username'] ?? '');
    $roleLabel = implode(' / ', array_slice((array)($user['role_names'] ?? []), 0, 2));
    if ($roleLabel === '') $roleLabel = '未分配角色';
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= web_e($title) ?> | Artdon 官网后台</title>
  <link rel="stylesheet" href="assets/admin.css?v=6.12.2">
  <link rel="stylesheet" href="assets/admin_v7.css?v=7.0.2">
  <link rel="stylesheet" href="assets/admin_security.css?v=7.0.3">
<!-- ARTDON_V7092_ADMIN_COMPACT_START -->
<link rel="stylesheet" href="/assets/css/artdon_admin_product_series_compact_v7092.css?v=7092">
<script src="/assets/js/artdon_admin_product_series_compact_v7092.js?v=7092" defer></script>
<!-- ARTDON_V7092_ADMIN_COMPACT_END -->
</head>
<body class="admin-v7" data-admin-route="<?= web_e($route) ?>">
<div class="admin-shell" data-admin-shell>
  <aside class="admin-side" data-admin-sidebar>
    <div class="admin-side-head">
      <a class="admin-brand" href="index.php" aria-label="返回工作台">
        <span class="admin-brand-mark" aria-hidden="true"><i></i><b></b></span>
        <span class="admin-brand-copy"><strong>Artdon Web</strong><small>Content Management</small></span>
      </a>
      <button class="admin-side-close" type="button" data-sidebar-close aria-label="关闭菜单"><?= admin_icon('collapse') ?></button>
    </div>

    <nav class="admin-nav" aria-label="后台主菜单">
      <?php foreach ($availableGroups as $group):
          $isOpen = false;
          foreach ($group['items'] as $item) {
              if ($item['key'] === $route) { $isOpen = true; break; }
          }
      ?>
      <section class="admin-nav-group <?= $isOpen ? 'is-current' : '' ?>" data-nav-group="<?= web_e((string)$group['key']) ?>">
        <button class="admin-nav-group-title" type="button" data-nav-group-toggle aria-expanded="true">
          <span><?= web_e((string)$group['label']) ?></span><?= admin_icon('chevron') ?>
        </button>
        <div class="admin-nav-group-items">
          <?php foreach ($group['items'] as $item): $isActive = $item['key'] === $route; ?>
          <a class="admin-nav-link <?= $isActive ? 'is-active' : '' ?>" href="<?= web_e((string)$item['href']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?> data-command-label="<?= web_e((string)$item['label']) ?>">
            <span class="admin-nav-icon"><?= admin_icon((string)$item['icon']) ?></span>
            <span class="admin-nav-text"><?= web_e((string)$item['label']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
    </nav>

    <div class="admin-side-foot">
      <div class="admin-server-mini"><i></i><span>香港官网</span><small>43.132.210.162</small></div>
      <div class="admin-user-card">
        <span class="admin-user-avatar"><?= web_e(admin_user_initial($user)) ?></span>
        <span class="admin-user-meta"><strong><?= web_e($displayName) ?></strong><small><?= web_e($roleLabel) ?></small></span>
      </div>
      <div class="admin-side-links">
        <a href="profile.php"><?= admin_icon('user') ?><span>我的账号</span></a>
        <a href="../index.php" target="_blank" rel="noopener"><?= admin_icon('external') ?><span>打开官网</span></a>
        <a href="logout.php"><?= admin_icon('logout') ?><span>退出登录</span></a>
      </div>
    </div>
  </aside>

  <div class="admin-backdrop" data-sidebar-backdrop></div>

  <div class="admin-workspace">
    <header class="admin-topbar">
      <div class="admin-topbar-left">
        <button class="admin-icon-button admin-mobile-menu" type="button" data-sidebar-open aria-label="打开菜单"><?= admin_icon('menu') ?></button>
        <button class="admin-icon-button admin-desktop-collapse" type="button" data-sidebar-collapse aria-label="折叠侧栏"><?= admin_icon('collapse') ?></button>
        <div class="admin-page-heading">
          <div class="admin-page-breadcrumb"><span>Artdon Web</span><i>/</i><span><?= web_e($groupLabel) ?></span></div>
          <h1><?= web_e($title) ?></h1>
        </div>
      </div>
      <div class="admin-topbar-actions">
        <span class="admin-environment"><i></i>香港官网</span>
        <button class="admin-command-trigger" type="button" data-command-open><?= admin_icon('search') ?><span>搜索 / 快速跳转</span><kbd>⌘ K</kbd></button>
        <a class="admin-button-secondary admin-preview-button" href="../index.php" target="_blank" rel="noopener">预览官网 <?= admin_icon('external') ?></a>
        <div class="admin-user-menu-wrap">
          <button class="admin-user-menu-trigger" type="button" data-user-menu-toggle aria-expanded="false">
            <span class="admin-user-avatar is-small"><?= web_e(admin_user_initial($user)) ?></span><span class="admin-user-menu-name"><?= web_e($displayName) ?></span><?= admin_icon('chevron') ?>
          </button>
          <div class="admin-user-menu" data-user-menu hidden>
            <div><strong><?= web_e($displayName) ?></strong><small><?= web_e($username) ?> · <?= web_e($roleLabel) ?></small></div>
            <a href="profile.php"><?= admin_icon('user') ?>我的账号</a>
            <a href="../index.php" target="_blank" rel="noopener"><?= admin_icon('external') ?>打开官网</a>
            <a href="logout.php"><?= admin_icon('logout') ?>退出登录</a>
          </div>
        </div>
      </div>
    </header>
    <main class="admin-main" id="adminMain">
<?php
}

function admin_page_end(): void
{
    $items = is_array($GLOBALS['admin_v7_command_items'] ?? null) ? $GLOBALS['admin_v7_command_items'] : [];
    ?>
    </main>
  </div>
</div>

<div class="admin-command" data-command hidden aria-hidden="true">
  <button class="admin-command-backdrop" type="button" data-command-close aria-label="关闭快速跳转"></button>
  <section class="admin-command-panel" role="dialog" aria-modal="true" aria-label="搜索和快速跳转">
    <div class="admin-command-search"><?= admin_icon('search') ?><input type="search" placeholder="输入模块名称，例如：产品、询盘、同步" data-command-input autocomplete="off"><kbd>ESC</kbd></div>
    <div class="admin-command-results" data-command-results>
      <?php foreach ($items as $item): ?>
      <a href="<?= web_e((string)$item['href']) ?>" data-command-item data-command-text="<?= web_e(strtolower((string)$item['group_label'].' '.(string)$item['label'])) ?>">
        <span class="admin-nav-icon"><?= admin_icon((string)$item['icon']) ?></span>
        <span><strong><?= web_e((string)$item['label']) ?></strong><small><?= web_e((string)$item['group_label']) ?></small></span>
        <?= admin_icon('chevron') ?>
      </a>
      <?php endforeach; ?>
      <div class="admin-command-empty" data-command-empty hidden>没有找到对应模块。</div>
    </div>
  </section>
</div>

<script src="assets/admin.js?v=6.12.2" defer></script>
<script src="assets/admin_v7.js?v=7.0.1" defer></script>
<script src="assets/admin_security.js?v=7.0.3" defer></script>
</body>
</html>
<?php
}

function admin_notice(): void
{
    if (!empty($_SESSION['admin_success'])) {
        echo '<div class="notice notice-success"><strong>操作成功</strong><span>'.web_e((string)$_SESSION['admin_success']).'</span></div>';
        unset($_SESSION['admin_success']);
    }
    if (!empty($_SESSION['admin_error'])) {
        echo '<div class="notice notice-error"><strong>操作失败</strong><span>'.web_e((string)$_SESSION['admin_error']).'</span></div>';
        unset($_SESSION['admin_error']);
    }
}

function admin_section_tabs(string $active): void
{
    $tabs = [
        'layout'=>'版块排序',
        'hero'=>'首页轮播',
        'why'=>'关于我们',
        'reasons'=>'合作优势',
        'products'=>'首页产品',
        'featured_system'=>'重点系统',
        'projects'=>'项目案例',
        'solutions'=>'应用方案',
        'downloads'=>'下载中心',
        'insights'=>'知识文章',
        'inquiry'=>'询盘表单',
    ];
    echo '<nav class="section-tabs" aria-label="首页编辑章节">';
    foreach($tabs as $key=>$label){
        echo '<a class="'.($active===$key?'is-active':'').'" href="homepage.php?section='.web_e($key).'">'.web_e($label).'</a>';
    }
    echo '</nav>';
}

function admin_status_label(string $status): string
{
    return [
        'new'=>'新询盘','assigned'=>'已分配','replied'=>'已回复','closed'=>'已关闭',
        'not_queued'=>'未加入队列','pending'=>'待同步','processing'=>'同步中','synced'=>'已同步','failed'=>'失败','cancelled'=>'已取消',
        'success'=>'成功','error'=>'错误','ignored'=>'已忽略','inbound'=>'广州→香港','outbound'=>'香港→广州',
        'completed'=>'已完成','disabled'=>'未启用','draft'=>'草稿','published'=>'已发布',
    ][$status] ?? $status;
}

function admin_event_label(string $event): string
{
    return [
        'inquiry.created'=>'新建官网询盘',
        'system.ping'=>'连接测试',
        'record.published'=>'发布业务资料',
        'content.home_block.published'=>'发布首页内容',
    ][$event] ?? $event;
}

function admin_action_label(string $action): string
{
    return [
        'login'=>'登录后台','update_content'=>'修改内容','upload_media'=>'上传媒体','move_media'=>'移动媒体分类','crop_media'=>'裁切媒体','delete_media'=>'删除媒体',
        'update_inquiry'=>'修改询盘状态','update_inquiry_routing'=>'修改询盘派工规则','update_site_settings'=>'修改网站设置',
        'update_footer'=>'修改页脚','update_sync_settings'=>'修改同步设置','generate_sync_tokens'=>'生成同步令牌','update_storage_settings'=>'修改存储设置',
        'create_product'=>'新增产品','update_product'=>'修改产品','delete_product'=>'删除产品',
        'create_product_category'=>'新增产品分类','update_product_category'=>'修改产品分类',
        'create_product_variant'=>'新增具体产品','update_product_variant'=>'修改具体产品','delete_product_variant'=>'删除具体产品',
        'create_product_filter_group'=>'新增筛选组','update_product_filter_group'=>'修改筛选组','delete_product_filter_group'=>'删除筛选组',
        'create_product_filter_option'=>'新增筛选选项','update_product_filter_option'=>'修改筛选选项','delete_product_filter_option'=>'删除筛选选项',
        'create_solution_icon'=>'新增应用图标','update_solution_icon'=>'修改应用图标','delete_solution_icon'=>'删除应用图标','reorder_solution_icons'=>'应用图标排序',
        'create_home_product_publication'=>'新增首页产品发布','update_home_product_publication'=>'修改首页产品发布','delete_home_product_publication'=>'取消首页产品发布',
        'bulk_create_home_product_publications'=>'批量加入首页产品','reorder_home_products'=>'首页产品排序',
        'user.create'=>'新增后台账号','user.update'=>'修改后台账号','user.disable'=>'停用后台账号','user.enable'=>'启用后台账号','user.revoke_sessions'=>'强制账号下线',
        'user.password'=>'重置账号密码','user.permissions'=>'修改账号角色权限','role.create'=>'新增权限角色','role.update'=>'修改权限角色','role.delete'=>'删除权限角色',
        'login.success'=>'登录成功','login.failure'=>'登录失败','login.locked'=>'锁定账号登录','login.blocked'=>'阻止频繁登录','logout'=>'退出登录','permission.denied'=>'权限拦截','session.expired'=>'会话失效','request.post'=>'后台提交操作','user.unlock'=>'解除账号锁定',
        'logs.export'=>'导出日志','logs.cleanup'=>'清理历史日志','profile.update'=>'修改个人资料','profile.password'=>'修改个人密码',
        'update_home_product_tab'=>'修改首页产品选项卡','delete_home_product_tab'=>'删除首页产品选项卡','reorder_home_product_tabs'=>'首页产品选项卡排序',
    ][$action] ?? $action;
}
