<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/footer.php';
require_once __DIR__ . '/_layout.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
$site = web_get_block('site');
$footerRaw = web_get_block('footer');
$footer = web_footer_normalize($footerRaw, $site);
$needsVisibilityRepair = (int)($footerRaw['visibility_guard_version'] ?? 0) < 6128;

function footer_admin_lines(array $items, bool $social = false): string
{
    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $label = trim((string)($item['label'] ?? ''));
        $href = trim((string)($item['href'] ?? ''));
        if ($label === '' || $href === '') continue;
        $newTab = web_footer_bool($item['new_tab'] ?? false, false) ? 'new' : '';
        if ($social) {
            $lines[] = implode('|', [$label, trim((string)($item['short'] ?? $label)), $href, $newTab]);
        } else {
            $lines[] = implode('|', [$label, $href, $newTab]);
        }
    }
    return implode("\n", $lines);
}


function footer_admin_limit(string $value, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
}

function footer_admin_parse_links(string $text, bool $social = false): array
{
    $items = [];
    foreach (preg_split('/\R+/', trim($text)) ?: [] as $line) {
        if (trim($line) === '') continue;
        $parts = array_map('trim', explode('|', $line));
        if ($social) {
            [$label, $short, $href, $target] = array_pad($parts, 4, '');
            if ($label === '' || $href === '') continue;
            $items[] = [
                'label' => footer_admin_limit($label, 80),
                'short' => footer_admin_limit($short !== '' ? $short : $label, 24),
                'href' => web_footer_safe_url($href),
                'new_tab' => in_array(strtolower($target), ['new','1','yes','blank','_blank'], true) ? 1 : 0,
            ];
        } else {
            [$label, $href, $target] = array_pad($parts, 3, '');
            if ($label === '' || $href === '') continue;
            $items[] = [
                'label' => footer_admin_limit($label, 120),
                'href' => web_footer_safe_url($href),
                'new_tab' => in_array(strtolower($target), ['new','1','yes','blank','_blank'], true) ? 1 : 0,
            ];
        }
        if (count($items) >= 20) break;
    }
    return $items;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!web_verify_csrf($_POST['csrf'] ?? null)) {
        $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
        header('Location: footer.php'); exit;
    }
    try {
        /* Colour-only save: never rewrite brand, menus, contact or visibility flags. */
        if (isset($_POST['save_theme_only'])) {
            $savedThemeOnly = $footer;
            $savedThemeOnly['schema_version'] = 6128;
            $savedThemeOnly['visibility_confirmed'] = 1;
            $savedThemeOnly['visibility_guard_version'] = 6128;
            if ($needsVisibilityRepair) {
                $savedThemeOnly['brand']['active'] = 1;
                $savedThemeOnly['contact']['active'] = 1;
                $savedThemeOnly['newsletter']['active'] = 1;
                $savedThemeOnly['bottom']['social_active'] = 1;
            }
            $savedThemeOnly['theme']['background'] = web_footer_safe_hex((string)($_POST['theme_background'] ?? '#101010'));
            web_save_block($pdo, 'footer', $savedThemeOnly, (int)$user['id']);
            web_log($pdo, (int)$user['id'], 'update_footer_theme', 'content_block', 'footer', ['background'=>$savedThemeOnly['theme']['background']]);
            $_SESSION['admin_success'] = '页脚颜色已保存，其他页脚内容和显示状态未改动。';
            header('Location: footer.php'); exit;
        }

        $brandLogo = trim((string)($_POST['brand_logo'] ?? $footer['brand']['logo'] ?? ''));
        $brandLightLogo = trim((string)($_POST['brand_light_logo'] ?? $footer['brand']['light_logo'] ?? 'assets/img/logo-artdon.png'));
        if (!empty($_FILES['brand_logo_upload']) && ($_FILES['brand_logo_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $brandLogo = web_upload_file($_FILES['brand_logo_upload'], 'image', $pdo, (int)$user['id'], '官网页脚深色背景 LOGO', 'Artdon Lighting footer dark-background logo', 'images');
        }
        if (!empty($_FILES['brand_light_logo_upload']) && ($_FILES['brand_light_logo_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $brandLightLogo = web_upload_file($_FILES['brand_light_logo_upload'], 'image', $pdo, (int)$user['id'], '官网页脚浅色背景 LOGO', 'Artdon Lighting footer light-background logo', 'images');
        }

        $columns = [];
        foreach ((array)($_POST['columns'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') continue;
            $columns[] = [
                'active' => !empty($row['active']) ? 1 : 0,
                'title' => footer_admin_limit($title, 100),
                'links' => footer_admin_parse_links((string)($row['links_text'] ?? '')),
            ];
            if (count($columns) >= 8) break;
        }
        if (!$columns) throw new RuntimeException('至少需要保留一个页脚栏目。');

        $saved = [
            'schema_version' => 6128,
            'navigation_version' => web_footer_navigation_version(),
            'visibility_confirmed' => 1,
            'visibility_guard_version' => 6128,
            'theme' => [
                'background' => web_footer_safe_hex((string)($_POST['theme_background'] ?? '#101010')),
            ],
            'brand' => [
                'active' => !empty($_POST['brand_active']) ? 1 : 0,
                'logo' => $brandLogo,
                'light_logo' => $brandLightLogo !== '' ? $brandLightLogo : 'assets/img/logo-artdon.png',
                'logo_alt' => trim((string)($_POST['brand_logo_alt'] ?? '')),
                'home_url' => web_footer_safe_url((string)($_POST['brand_home_url'] ?? 'index.php'), 'index.php'),
                'tagline' => trim((string)($_POST['brand_tagline'] ?? '')),
            ],
            'columns' => $columns,
            'contact' => [
                'active' => !empty($_POST['contact_active']) ? 1 : 0,
                'heading' => trim((string)($_POST['contact_heading'] ?? 'Contact')),
                'company' => trim((string)($_POST['contact_company'] ?? '')),
                'address' => trim((string)($_POST['contact_address'] ?? '')),
                'contact_label' => trim((string)($_POST['contact_label'] ?? 'Contact')),
                'contact_value' => trim((string)($_POST['contact_value'] ?? '')),
                'email_label' => trim((string)($_POST['email_label'] ?? 'Email')),
                'email_value' => trim((string)($_POST['email_value'] ?? '')),
                'telephone_label' => trim((string)($_POST['telephone_label'] ?? 'Tel')),
                'telephone_value' => trim((string)($_POST['telephone_value'] ?? '')),
                'mobile_label' => trim((string)($_POST['mobile_label'] ?? 'Mobile / WhatsApp')),
                'mobile_value' => trim((string)($_POST['mobile_value'] ?? '')),
                'whatsapp_number' => preg_replace('/\D+/', '', (string)($_POST['whatsapp_number'] ?? '')),
                'button_label' => trim((string)($_POST['contact_button_label'] ?? '')),
                'button_url' => web_footer_safe_url((string)($_POST['contact_button_url'] ?? '#')),
            ],
            'newsletter' => [
                'active' => !empty($_POST['newsletter_active']) ? 1 : 0,
                'title' => trim((string)($_POST['newsletter_title'] ?? '')),
                'text' => trim((string)($_POST['newsletter_text'] ?? '')),
                'placeholder' => trim((string)($_POST['newsletter_placeholder'] ?? '')),
                'button' => trim((string)($_POST['newsletter_button'] ?? '')),
            ],
            'bottom' => [
                'copyright' => trim((string)($_POST['copyright'] ?? '')),
                'legal_active' => !empty($_POST['legal_active']) ? 1 : 0,
                'legal' => footer_admin_parse_links((string)($_POST['legal_text'] ?? '')),
                'social_active' => !empty($_POST['social_active']) ? 1 : 0,
                'social' => footer_admin_parse_links((string)($_POST['social_text'] ?? ''), true),
            ],
        ];

        web_save_block($pdo, 'footer', $saved, (int)$user['id']);
        web_log($pdo, (int)$user['id'], 'update_footer', 'content_block', 'footer', ['columns'=>count($columns)]);
        $_SESSION['admin_success'] = '页脚内容和排版设置已保存。';
        header('Location: footer.php'); exit;
    } catch (Throwable $e) {
        $_SESSION['admin_error'] = '保存失败：'.$e->getMessage();
        header('Location: footer.php'); exit;
    }
}

admin_page_start('页脚管理', 'footer', $user);
admin_notice();
$brandPreview = trim((string)($footer['brand']['logo'] ?? ''));
if ($brandPreview !== '' && !preg_match('/^(?:https?:)?\/\//i', $brandPreview) && !str_starts_with($brandPreview, '/')) { $brandPreview = '../'.ltrim($brandPreview, './'); }
$brandLightPreview = trim((string)($footer['brand']['light_logo'] ?? 'assets/img/logo-artdon.png'));
if ($brandLightPreview !== '' && !preg_match('/^(?:https?:)?\/\//i', $brandLightPreview) && !str_starts_with($brandLightPreview, '/')) { $brandLightPreview = '../'.ltrim($brandLightPreview, './'); }
$footerThemeBackground = web_footer_safe_hex((string)($footer['theme']['background'] ?? '#101010'));
$footerPalettes = [
    ['name'=>'经典黑', 'hex'=>'#101010'],
    ['name'=>'石墨灰', 'hex'=>'#17191C'],
    ['name'=>'炭灰', 'hex'=>'#222426'],
    ['name'=>'午夜蓝', 'hex'=>'#111827'],
    ['name'=>'深海蓝', 'hex'=>'#0B1B2B'],
    ['name'=>'森林绿', 'hex'=>'#13251C'],
    ['name'=>'深橄榄', 'hex'=>'#252618'],
    ['name'=>'咖啡棕', 'hex'=>'#281C18'],
    ['name'=>'酒红', 'hex'=>'#2A1418'],
];
$activeColumnCount = count(array_filter((array)($footer['columns'] ?? []), static fn($item) => is_array($item) && web_footer_bool($item['active'] ?? true)));
$activeSocialCount = count(array_filter((array)($footer['bottom']['social'] ?? []), static fn($item) => is_array($item) && trim((string)($item['href'] ?? '')) !== ''));
?>
<form method="post" enctype="multipart/form-data" class="footer-editor-form" data-homepage-form>
  <input type="hidden" name="csrf" value="<?= web_e(web_csrf_token()) ?>">

  <section class="footer-editor-toolbar">
    <div class="footer-editor-title">
      <span class="footer-editor-kicker">GLOBAL FOOTER</span>
      <h2>全站页脚编辑</h2>
      <p>按区域管理内容。常用字段直接显示，低频设置已折叠，保存后全站同步。</p>
    </div>
    <div class="footer-editor-summary" aria-label="页脚状态摘要">
      <span><b><?= $activeColumnCount ?></b> 个导航栏目</span>
      <span><b><?= $activeSocialCount ?></b> 个社交入口</span>
      <span><i style="--summary-color:<?= web_e($footerThemeBackground) ?>"></i><?= web_e($footerThemeBackground) ?></span>
    </div>
    <div class="footer-editor-toolbar-actions">
      <a class="admin-button-secondary" href="../index.php#footer" target="_blank">预览官网 ↗</a>
      <button class="admin-button" type="submit">保存全部页脚</button>
    </div>
  </section>

  <div class="footer-editor-layout">
    <aside class="footer-editor-nav" aria-label="页脚编辑区域">
      <strong>编辑区域</strong>
      <a href="#footerAppearance" class="is-active"><span>01</span>外观颜色</a>
      <a href="#footerBrand"><span>02</span>品牌与 LOGO</a>
      <a href="#footerNavigation"><span>03</span>导航栏目</a>
      <a href="#footerContact"><span>04</span>联系资料</a>
      <a href="#footerNewsletter"><span>05</span>邮件订阅</a>
      <a href="#footerBottom"><span>06</span>底部与社媒</a>
      <small>点击可快速定位。每个区域都可独立折叠。</small>
    </aside>

    <main class="footer-editor-content">
      <details class="footer-editor-panel" id="footerAppearance" open>
        <summary>
          <span class="footer-panel-number">01</span>
          <span><b>外观颜色</b><small>页脚背景及自动对比色</small></span>
          <em><?= web_e($footerThemeBackground) ?></em>
        </summary>
        <div class="footer-panel-body">
          <div class="footer-theme-compact-head">
            <p>选择预设颜色，或输入自定义 HEX。只保存颜色不会改动 LOGO、栏目和显示状态。</p>
            <button class="admin-button-secondary" type="submit" name="save_theme_only" value="1">只保存颜色</button>
          </div>
          <div class="footer-theme-palette" role="group" aria-label="页脚背景色板">
            <?php foreach ($footerPalettes as $palette): ?>
              <button type="button" class="footer-theme-swatch<?= strtoupper($palette['hex']) === $footerThemeBackground ? ' is-active' : '' ?>" data-footer-theme-swatch="<?= web_e($palette['hex']) ?>" aria-pressed="<?= strtoupper($palette['hex']) === $footerThemeBackground ? 'true' : 'false' ?>">
                <span style="--swatch:<?= web_e($palette['hex']) ?>"></span>
                <b><?= web_e($palette['name']) ?></b>
                <small><?= web_e($palette['hex']) ?></small>
              </button>
            <?php endforeach; ?>
          </div>
          <div class="footer-theme-custom">
            <label class="field"><span>自定义取色</span><input type="color" value="<?= web_e($footerThemeBackground) ?>" data-footer-theme-picker></label>
            <label class="field"><span>HEX 色值</span><input name="theme_background" value="<?= web_e($footerThemeBackground) ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-footer-theme-input></label>
            <div class="footer-theme-preview" data-footer-theme-preview style="--preview-bg:<?= web_e($footerThemeBackground) ?>">
              <strong>ARTDON FOOTER</strong><span>Products · Solutions · Contact</span>
            </div>
          </div>
        </div>
      </details>

      <details class="footer-editor-panel" id="footerBrand" open>
        <summary>
          <span class="footer-panel-number">02</span>
          <span><b>品牌与 LOGO</b><small>深浅背景 LOGO、品牌说明及跳转</small></span>
          <label class="footer-panel-switch" onclick="event.stopPropagation()"><input type="hidden" name="brand_active" value="0"><input type="checkbox" name="brand_active" value="1"<?= web_footer_bool($footer['brand']['active'] ?? true) ? ' checked' : '' ?>><i></i><em>显示</em></label>
        </summary>
        <div class="footer-panel-body">
          <div class="footer-logo-editor-grid">
            <div class="footer-logo-editor-card is-dark">
              <div class="footer-logo-preview-box"><?php if (!empty($footer['brand']['logo'])): ?><img src="<?= web_e($brandPreview) ?>" alt="深色背景 LOGO 预览"><?php else: ?><span>未设置</span><?php endif; ?></div>
              <div class="footer-logo-fields">
                <strong>深色背景 LOGO</strong><small>建议使用白色或浅色透明 PNG / SVG</small>
                <input name="brand_logo" value="<?= web_e($footer['brand']['logo'] ?? '') ?>" placeholder="assets/img/logo-artdon-footer.png">
                <input type="file" name="brand_logo_upload" accept="image/*">
              </div>
            </div>
            <div class="footer-logo-editor-card is-light">
              <div class="footer-logo-preview-box"><?php if (!empty($footer['brand']['light_logo'])): ?><img src="<?= web_e($brandLightPreview) ?>" alt="浅色背景 LOGO 预览"><?php else: ?><span>未设置</span><?php endif; ?></div>
              <div class="footer-logo-fields">
                <strong>浅色背景 LOGO</strong><small>建议使用黑色或深色透明 PNG / SVG</small>
                <input name="brand_light_logo" value="<?= web_e($footer['brand']['light_logo'] ?? 'assets/img/logo-artdon.png') ?>" placeholder="assets/img/logo-artdon.png">
                <input type="file" name="brand_light_logo_upload" accept="image/*">
              </div>
            </div>
          </div>
          <div class="footer-brand-primary-grid">
            <label class="field footer-field-wide"><span>品牌说明</span><textarea name="brand_tagline" rows="2" placeholder="Architectural Lighting for Commercial Spaces"><?= web_e($footer['brand']['tagline'] ?? '') ?></textarea></label>
          </div>
          <details class="footer-subpanel">
            <summary>高级设置 <span>ALT 文字与 LOGO 点击地址</span></summary>
            <div class="footer-subpanel-grid">
              <label class="field"><span>LOGO ALT 文字</span><input name="brand_logo_alt" value="<?= web_e($footer['brand']['logo_alt'] ?? '') ?>"></label>
              <label class="field"><span>点击 LOGO 地址</span><input name="brand_home_url" value="<?= web_e($footer['brand']['home_url'] ?? 'index.php') ?>"></label>
            </div>
          </details>
        </div>
      </details>

      <details class="footer-editor-panel" id="footerNavigation" open>
        <summary>
          <span class="footer-panel-number">03</span>
          <span><b>导航栏目</b><small>栏目标题、链接和显示状态</small></span>
          <em><?= count((array)($footer['columns'] ?? [])) ?> 栏</em>
        </summary>
        <div class="footer-panel-body">
          <div class="footer-section-tools">
            <p>链接格式：<code>名称|地址|new</code>。不需要新窗口时省略 <code>|new</code>。</p>
            <div class="admin-actions"><button type="button" class="admin-button-secondary" data-collapse-all>全部折叠</button><button type="button" class="admin-button-secondary" data-expand-all>全部展开</button><button type="button" class="admin-button" data-add-repeater="#footerColumns" data-template="#footerColumnTemplate">新增栏目</button></div>
          </div>
          <div class="repeater footer-column-repeater" id="footerColumns">
            <?php foreach (($footer['columns'] ?? []) as $i => $column): ?>
              <article class="repeat-item footer-column-card">
                <div class="repeat-head">
                  <strong><?= web_e($column['title'] ?? ('栏目 '.($i + 1))) ?></strong>
                  <label class="footer-mini-switch"><input type="checkbox" name="columns[<?= $i ?>][active]" value="1"<?= web_footer_bool($column['active'] ?? true) ? ' checked' : '' ?>><i></i><span>显示</span></label>
                  <button class="repeat-remove" type="button" data-remove-repeat>删除</button>
                </div>
                <div class="footer-column-fields">
                  <label class="field"><span>栏目标题</span><input name="columns[<?= $i ?>][title]" value="<?= web_e($column['title'] ?? '') ?>" required data-footer-column-title></label>
                  <label class="field"><span>栏目链接</span><textarea name="columns[<?= $i ?>][links_text]" rows="6" spellcheck="false"><?= web_e(footer_admin_lines($column['links'] ?? [])) ?></textarea></label>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <template id="footerColumnTemplate">
            <article class="repeat-item footer-column-card"><div class="repeat-head"><strong>新页脚栏目</strong><label class="footer-mini-switch"><input type="checkbox" name="columns[__INDEX__][active]" value="1" checked><i></i><span>显示</span></label><button class="repeat-remove" type="button" data-remove-repeat>删除</button></div><div class="footer-column-fields"><label class="field"><span>栏目标题</span><input name="columns[__INDEX__][title]" required data-footer-column-title></label><label class="field"><span>栏目链接</span><textarea name="columns[__INDEX__][links_text]" rows="6" spellcheck="false" placeholder="All Products|products.php&#10;Downlights|products.php?type=downlight"></textarea></label></div></article>
          </template>
        </div>
      </details>

      <details class="footer-editor-panel" id="footerContact" open>
        <summary>
          <span class="footer-panel-number">04</span>
          <span><b>联系资料</b><small>公司、联系人、邮箱、电话和 WhatsApp</small></span>
          <label class="footer-panel-switch" onclick="event.stopPropagation()"><input type="hidden" name="contact_active" value="0"><input type="checkbox" name="contact_active" value="1"<?= web_footer_bool($footer['contact']['active'] ?? true) ? ' checked' : '' ?>><i></i><em>显示</em></label>
        </summary>
        <div class="footer-panel-body">
          <div class="footer-contact-main-grid">
            <label class="field"><span>栏目标题</span><input name="contact_heading" value="<?= web_e($footer['contact']['heading'] ?? '') ?>"></label>
            <label class="field"><span>公司名称</span><input name="contact_company" value="<?= web_e($footer['contact']['company'] ?? '') ?>"></label>
            <label class="field footer-field-wide"><span>地址</span><textarea name="contact_address" rows="2"><?= web_e($footer['contact']['address'] ?? '') ?></textarea></label>
          </div>
          <div class="footer-contact-table">
            <div class="footer-contact-row"><input name="contact_label" value="<?= web_e($footer['contact']['contact_label'] ?? '') ?>" aria-label="联系人标签"><input name="contact_value" value="<?= web_e($footer['contact']['contact_value'] ?? '') ?>" aria-label="联系人内容" placeholder="Sukie"></div>
            <div class="footer-contact-row"><input name="email_label" value="<?= web_e($footer['contact']['email_label'] ?? '') ?>" aria-label="邮箱标签"><input name="email_value" value="<?= web_e($footer['contact']['email_value'] ?? '') ?>" aria-label="邮箱地址" placeholder="sales@artdon.cn"></div>
            <div class="footer-contact-row"><input name="telephone_label" value="<?= web_e($footer['contact']['telephone_label'] ?? '') ?>" aria-label="电话标签"><input name="telephone_value" value="<?= web_e($footer['contact']['telephone_value'] ?? '') ?>" aria-label="电话号码"></div>
            <div class="footer-contact-row"><input name="mobile_label" value="<?= web_e($footer['contact']['mobile_label'] ?? '') ?>" aria-label="手机标签"><input name="mobile_value" value="<?= web_e($footer['contact']['mobile_value'] ?? '') ?>" aria-label="手机号码"></div>
          </div>
          <details class="footer-subpanel">
            <summary>WhatsApp 与联系按钮 <span>低频设置</span></summary>
            <div class="footer-subpanel-grid footer-subpanel-grid-3">
              <label class="field"><span>WhatsApp 数字号码</span><input name="whatsapp_number" value="<?= web_e($footer['contact']['whatsapp_number'] ?? '') ?>" placeholder="8613925332972"></label>
              <label class="field"><span>按钮文字</span><input name="contact_button_label" value="<?= web_e($footer['contact']['button_label'] ?? '') ?>"></label>
              <label class="field"><span>按钮地址</span><input name="contact_button_url" value="<?= web_e($footer['contact']['button_url'] ?? '') ?>"></label>
            </div>
          </details>
        </div>
      </details>

      <details class="footer-editor-panel" id="footerNewsletter">
        <summary>
          <span class="footer-panel-number">05</span>
          <span><b>邮件订阅</b><small>订阅标题、说明、输入提示和按钮</small></span>
          <label class="footer-panel-switch" onclick="event.stopPropagation()"><input type="hidden" name="newsletter_active" value="0"><input type="checkbox" name="newsletter_active" value="1"<?= web_footer_bool($footer['newsletter']['active'] ?? true) ? ' checked' : '' ?>><i></i><em>显示</em></label>
        </summary>
        <div class="footer-panel-body">
          <div class="footer-newsletter-grid">
            <label class="field"><span>订阅标题</span><input name="newsletter_title" value="<?= web_e($footer['newsletter']['title'] ?? '') ?>"></label>
            <label class="field"><span>按钮文字</span><input name="newsletter_button" value="<?= web_e($footer['newsletter']['button'] ?? '') ?>"></label>
            <label class="field footer-field-wide"><span>订阅说明</span><textarea name="newsletter_text" rows="2"><?= web_e($footer['newsletter']['text'] ?? '') ?></textarea></label>
            <label class="field footer-field-wide"><span>邮箱输入提示</span><input name="newsletter_placeholder" value="<?= web_e($footer['newsletter']['placeholder'] ?? '') ?>"></label>
          </div>
        </div>
      </details>

      <details class="footer-editor-panel" id="footerBottom" open>
        <summary>
          <span class="footer-panel-number">06</span>
          <span><b>底部、政策与社交入口</b><small>版权文字、法律链接及社交平台</small></span>
          <em><?= $activeSocialCount ?> 社交入口</em>
        </summary>
        <div class="footer-panel-body">
          <label class="field footer-copyright-field"><span>版权文字</span><input name="copyright" value="<?= web_e($footer['bottom']['copyright'] ?? '') ?>"></label>
          <div class="footer-bottom-grid">
            <section class="footer-bottom-box">
              <header><div><b>政策与法律链接</b><small>名称|地址|new</small></div><label class="footer-mini-switch"><input type="hidden" name="legal_active" value="0"><input type="checkbox" name="legal_active" value="1"<?= web_footer_bool($footer['bottom']['legal_active'] ?? true) ? ' checked' : '' ?>><i></i><span>显示</span></label></header>
              <textarea name="legal_text" rows="7" spellcheck="false"><?= web_e(footer_admin_lines($footer['bottom']['legal'] ?? [])) ?></textarea>
            </section>
            <section class="footer-bottom-box">
              <header><div><b>社交入口</b><small>平台|图标类型|地址|new</small></div><label class="footer-mini-switch"><input type="hidden" name="social_active" value="0"><input type="checkbox" name="social_active" value="1"<?= web_footer_bool($footer['bottom']['social_active'] ?? true) ? ' checked' : '' ?>><i></i><span>显示</span></label></header>
              <textarea name="social_text" rows="7" spellcheck="false"><?= web_e(footer_admin_lines($footer['bottom']['social'] ?? [], true)) ?></textarea>
              <small class="footer-format-help">图标：instagram、linkedin、youtube、whatsapp、facebook、x、tiktok、pinterest</small>
            </section>
          </div>
        </div>
      </details>
    </main>
  </div>

  <div class="footer-admin-savebar"><div><strong>全站统一页脚</strong><span>保存后首页、产品页、项目页、下载页等页面同时更新。</span></div><div class="admin-actions"><a class="admin-button-secondary" href="../index.php#footer" target="_blank">预览官网</a><button class="admin-button" type="submit">保存全部页脚</button></div></div>
</form>

<style>
.footer-editor-form{--footer-editor-line:#e3e5e8;--footer-editor-soft:#f7f8f9;--footer-editor-dark:#171717;padding-bottom:10px}.footer-editor-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) auto auto;gap:24px;align-items:center;margin-bottom:16px;padding:20px 22px;border:1px solid var(--footer-editor-line);border-radius:12px;background:#fff}.footer-editor-title{min-width:0}.footer-editor-kicker{display:block;margin-bottom:5px;color:#c9252d;font-size:9px;font-weight:850;letter-spacing:.18em}.footer-editor-title h2{margin:0;font-size:22px;line-height:1.1}.footer-editor-title p{margin:6px 0 0;color:#74787f;font-size:11px;line-height:1.55}.footer-editor-summary{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.footer-editor-summary>span{display:flex;align-items:center;gap:6px;height:30px;padding:0 10px;border:1px solid var(--footer-editor-line);border-radius:999px;background:#fafafa;color:#666;font-size:10px;white-space:nowrap}.footer-editor-summary b{color:#111;font-size:11px}.footer-editor-summary i{width:12px;height:12px;border:1px solid rgba(0,0,0,.18);border-radius:50%;background:var(--summary-color)}.footer-editor-toolbar-actions{display:flex;gap:8px;justify-content:flex-end}.footer-editor-layout{display:grid;grid-template-columns:190px minmax(0,1fr);gap:16px;align-items:start}.footer-editor-nav{position:sticky;top:18px;display:grid;gap:4px;padding:14px;border:1px solid var(--footer-editor-line);border-radius:12px;background:#fff}.footer-editor-nav>strong{padding:4px 8px 9px;font-size:11px}.footer-editor-nav>a{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:7px;color:#555;font-size:11px;font-weight:720;text-decoration:none}.footer-editor-nav>a span{color:#a0a4aa;font-size:9px}.footer-editor-nav>a:hover,.footer-editor-nav>a.is-active{background:#171717;color:#fff}.footer-editor-nav>a.is-active span,.footer-editor-nav>a:hover span{color:#c9ccd1}.footer-editor-nav>small{margin-top:8px;padding:10px 8px 2px;border-top:1px solid var(--footer-editor-line);color:#8b8f96;font-size:9.5px;line-height:1.55}.footer-editor-content{display:grid;gap:10px;min-width:0}.footer-editor-panel{scroll-margin-top:16px;border:1px solid var(--footer-editor-line);border-radius:12px;background:#fff;overflow:hidden}.footer-editor-panel>summary{display:grid;grid-template-columns:32px minmax(0,1fr) auto;gap:12px;align-items:center;min-height:60px;padding:12px 16px;cursor:pointer;list-style:none}.footer-editor-panel>summary::-webkit-details-marker{display:none}.footer-editor-panel>summary:hover{background:#fbfbfc}.footer-editor-panel[open]>summary{border-bottom:1px solid var(--footer-editor-line);background:#fcfcfd}.footer-panel-number{display:grid;place-items:center;width:30px;height:30px;border-radius:8px;background:#111;color:#fff;font-size:9px;font-weight:800}.footer-editor-panel>summary>span:nth-child(2){display:grid;gap:3px}.footer-editor-panel>summary b{font-size:14px}.footer-editor-panel>summary small{color:#858990;font-size:10px;font-weight:500}.footer-editor-panel>summary>em{color:#6f747b;font-size:10px;font-style:normal;font-weight:750}.footer-panel-body{padding:16px}.footer-theme-compact-head,.footer-section-tools{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:13px}.footer-theme-compact-head p,.footer-section-tools p{margin:0;color:#73777e;font-size:10.5px;line-height:1.55}.footer-theme-compact-head code,.footer-section-tools code{padding:2px 5px;border:1px solid var(--footer-editor-line);border-radius:4px;background:#f5f5f6;font-size:9px}.footer-theme-palette{display:grid;grid-template-columns:repeat(9,minmax(0,1fr));gap:7px}.footer-theme-swatch{display:grid;grid-template-columns:26px minmax(0,1fr);grid-template-rows:auto auto;column-gap:7px;align-items:center;min-width:0;padding:7px;border:1px solid #dfe1e4;border-radius:8px;background:#fff;text-align:left;cursor:pointer;transition:.15s}.footer-theme-swatch:hover{border-color:#777}.footer-theme-swatch.is-active{border-color:#111;box-shadow:0 0 0 1px #111 inset}.footer-theme-swatch>span{grid-row:1/3;width:26px;height:26px;border:1px solid rgba(0,0,0,.16);border-radius:6px;background:var(--swatch)}.footer-theme-swatch b{overflow:hidden;color:#111;font-size:9.5px;text-overflow:ellipsis;white-space:nowrap}.footer-theme-swatch small{color:#8a8f96;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:8px}.footer-theme-custom{display:grid;grid-template-columns:118px 150px minmax(240px,1fr);gap:10px;align-items:end;margin-top:12px}.footer-theme-custom .field{gap:4px}.footer-theme-custom input[type=color]{height:37px;padding:3px;cursor:pointer}.footer-theme-preview{display:flex;align-items:center;justify-content:space-between;min-height:39px;padding:8px 12px;border-radius:7px;background:var(--preview-bg);color:#fff}.footer-theme-preview strong{font-size:9px;letter-spacing:.14em}.footer-theme-preview span{font-size:9px;opacity:.72}.footer-panel-switch,.footer-mini-switch{display:flex;align-items:center;gap:6px;color:#676b72;font-size:9.5px;font-style:normal;font-weight:720;cursor:pointer}.footer-panel-switch input,.footer-mini-switch input{position:absolute;opacity:0;pointer-events:none}.footer-panel-switch i,.footer-mini-switch i{position:relative;width:30px;height:17px;border-radius:999px;background:#cfd2d6;transition:.18s}.footer-panel-switch i:after,.footer-mini-switch i:after{content:"";position:absolute;left:2px;top:2px;width:13px;height:13px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:.18s}.footer-panel-switch input:checked+i,.footer-mini-switch input:checked+i{background:#171717}.footer-panel-switch input:checked+i:after,.footer-mini-switch input:checked+i:after{transform:translateX(13px)}.footer-panel-switch em{font-style:normal}.footer-logo-editor-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.footer-logo-editor-card{display:grid;grid-template-columns:170px minmax(0,1fr);gap:13px;align-items:center;padding:10px;border:1px solid var(--footer-editor-line);border-radius:9px}.footer-logo-editor-card.is-dark{background:#161616}.footer-logo-editor-card.is-light{background:#f7f7f7}.footer-logo-preview-box{display:grid;place-items:center;min-height:78px;padding:10px;border:1px solid rgba(127,127,127,.25);border-radius:7px;background:rgba(255,255,255,.06)}.footer-logo-editor-card.is-light .footer-logo-preview-box{background:#fff}.footer-logo-preview-box img{display:block;max-width:145px;max-height:58px;object-fit:contain}.footer-logo-preview-box span{color:#8b8f96;font-size:10px}.footer-logo-fields{display:grid;gap:7px}.footer-logo-fields strong{color:#fff;font-size:11px}.footer-logo-editor-card.is-light .footer-logo-fields strong{color:#111}.footer-logo-fields small{color:#9da1a8;font-size:9px}.footer-logo-fields input{width:100%;border:1px solid #d6d9dd;border-radius:6px;padding:8px 9px;background:#fff;font-size:10px}.footer-brand-primary-grid,.footer-contact-main-grid,.footer-newsletter-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:11px}.footer-field-wide{grid-column:1/-1}.footer-subpanel{margin-top:10px;border:1px solid var(--footer-editor-line);border-radius:8px;background:#fafafa}.footer-subpanel>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;cursor:pointer;list-style:none;font-size:10.5px;font-weight:750}.footer-subpanel>summary::-webkit-details-marker{display:none}.footer-subpanel>summary span{color:#999;font-size:9px;font-weight:500}.footer-subpanel-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 12px 12px}.footer-subpanel-grid-3{grid-template-columns:repeat(3,1fr)}.footer-column-repeater{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.footer-column-card{min-width:0;margin:0;padding:10px;background:#fbfbfc}.footer-column-card .repeat-head{gap:8px;margin-bottom:8px}.footer-column-card .repeat-head strong{min-width:0;overflow:hidden;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.footer-column-card .repeat-remove{padding:3px 0;font-size:9.5px}.footer-column-fields{display:grid;gap:8px}.footer-column-fields .field{gap:4px}.footer-column-fields textarea{min-height:112px;padding:8px 9px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:9.5px;line-height:1.5}.footer-contact-table{display:grid;grid-template-columns:1fr 1fr;gap:8px 10px;margin-top:10px}.footer-contact-row{display:grid;grid-template-columns:145px minmax(0,1fr);gap:0;border:1px solid #d7d9dc;border-radius:7px;overflow:hidden;background:#fff}.footer-contact-row input{min-width:0;border:0;border-radius:0;padding:9px 10px;font-size:11px}.footer-contact-row input:first-child{border-right:1px solid #e1e3e6;background:#f7f7f8;color:#696d73;font-weight:750}.footer-contact-row input:focus{position:relative;z-index:1;outline:2px solid rgba(201,37,45,.14)}.footer-bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}.footer-bottom-box{padding:11px;border:1px solid var(--footer-editor-line);border-radius:9px;background:#fafafa}.footer-bottom-box header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:9px}.footer-bottom-box header div{display:grid;gap:2px}.footer-bottom-box header b{font-size:11px}.footer-bottom-box header small{color:#969aa0;font-size:8.5px}.footer-bottom-box textarea{width:100%;min-height:126px;border:1px solid #d7d9dc;border-radius:7px;padding:9px 10px;background:#fff;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:9.5px;line-height:1.5;resize:vertical}.footer-format-help{display:block;margin-top:7px;color:#858990;font-size:8.5px}.footer-copyright-field{margin-bottom:0}.footer-admin-savebar{position:sticky;bottom:0;z-index:30;display:flex;align-items:center;justify-content:space-between;gap:20px;margin:14px 0 0;padding:11px 14px;border:1px solid #cfd2d6;border-radius:10px;background:rgba(255,255,255,.96);box-shadow:0 -8px 24px rgba(0,0,0,.06);backdrop-filter:blur(12px)}.footer-admin-savebar>div:first-child{display:grid;gap:2px}.footer-admin-savebar strong{font-size:11px}.footer-admin-savebar span{color:#777b82;font-size:9.5px}.footer-editor-form .field{gap:5px}.footer-editor-form .field>span{color:#52565d;font-size:9.5px;font-weight:800;letter-spacing:.03em}.footer-editor-form .field input,.footer-editor-form .field textarea{padding:9px 10px;border-radius:7px;font-size:11px}.footer-editor-form .field textarea{min-height:64px}.footer-editor-form .admin-button,.footer-editor-form .admin-button-secondary{padding:8px 11px;font-size:10px}.footer-editor-form .admin-actions{gap:6px}
@media(max-width:1320px){.footer-theme-palette{grid-template-columns:repeat(5,minmax(0,1fr))}.footer-column-repeater{grid-template-columns:repeat(2,minmax(0,1fr))}.footer-logo-editor-card{grid-template-columns:130px minmax(0,1fr)}}
@media(max-width:1050px){.footer-editor-toolbar{grid-template-columns:1fr auto}.footer-editor-summary{grid-column:1/-1;grid-row:2}.footer-editor-layout{grid-template-columns:1fr}.footer-editor-nav{position:static;display:flex;overflow:auto}.footer-editor-nav>strong,.footer-editor-nav>small{display:none}.footer-editor-nav>a{white-space:nowrap}.footer-contact-table{grid-template-columns:1fr}.footer-subpanel-grid-3{grid-template-columns:1fr 1fr}}
@media(max-width:760px){.footer-editor-toolbar{grid-template-columns:1fr}.footer-editor-toolbar-actions{justify-content:flex-start}.footer-editor-summary{grid-column:auto;grid-row:auto}.footer-theme-palette{grid-template-columns:repeat(3,minmax(0,1fr))}.footer-theme-custom,.footer-logo-editor-grid,.footer-brand-primary-grid,.footer-contact-main-grid,.footer-newsletter-grid,.footer-bottom-grid,.footer-subpanel-grid,.footer-subpanel-grid-3,.footer-column-repeater{grid-template-columns:1fr}.footer-logo-editor-card{grid-template-columns:1fr}.footer-logo-preview-box{min-height:70px}.footer-field-wide{grid-column:auto}.footer-theme-compact-head,.footer-section-tools,.footer-admin-savebar{align-items:flex-start;flex-direction:column}.footer-contact-row{grid-template-columns:118px minmax(0,1fr)}.footer-admin-savebar .admin-actions{width:100%}.footer-admin-savebar .admin-button{flex:1;justify-content:center}}
</style>
<script>
(() => {
  const input = document.querySelector('[data-footer-theme-input]');
  const picker = document.querySelector('[data-footer-theme-picker]');
  const preview = document.querySelector('[data-footer-theme-preview]');
  const swatches = [...document.querySelectorAll('[data-footer-theme-swatch]')];
  const panelColor = document.querySelector('#footerAppearance > summary > em');
  const summaryColor = document.querySelector('.footer-editor-summary i');
  const summaryHex = summaryColor ? summaryColor.parentElement : null;
  const normalize = value => {
    let v = String(value || '').trim().toUpperCase();
    if (v && !v.startsWith('#')) v = '#' + v;
    return /^#[0-9A-F]{6}$/.test(v) ? v : null;
  };
  const apply = (value, fromPicker = false) => {
    const hex = normalize(value);
    if (!hex || !input || !picker || !preview) return;
    input.value = hex;
    if (!fromPicker) picker.value = hex;
    preview.style.setProperty('--preview-bg', hex);
    if (panelColor) panelColor.textContent = hex;
    if (summaryColor) summaryColor.style.setProperty('--summary-color', hex);
    if (summaryHex) summaryHex.lastChild.textContent = hex;
    swatches.forEach(btn => {
      const active = String(btn.dataset.footerThemeSwatch || '').toUpperCase() === hex;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };
  swatches.forEach(btn => btn.addEventListener('click', () => apply(btn.dataset.footerThemeSwatch)));
  picker?.addEventListener('input', () => apply(picker.value, true));
  input?.addEventListener('input', () => { const hex = normalize(input.value); if (hex) apply(hex); });
  input?.addEventListener('blur', () => apply(input.value || '#101010'));

  const updateColumnTitle = inputEl => {
    const card = inputEl.closest('.footer-column-card');
    const title = card?.querySelector('.repeat-head strong');
    if (title) title.textContent = inputEl.value.trim() || '新页脚栏目';
  };
  document.addEventListener('input', event => {
    if (event.target.matches('[data-footer-column-title]')) updateColumnTitle(event.target);
  });

  const navLinks = [...document.querySelectorAll('.footer-editor-nav a')];
  navLinks.forEach(link => link.addEventListener('click', () => {
    const target = document.querySelector(link.getAttribute('href'));
    if (target && target.tagName === 'DETAILS') target.open = true;
  }));
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      const visible = entries.filter(entry => entry.isIntersecting).sort((a,b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (!visible) return;
      navLinks.forEach(link => link.classList.toggle('is-active', link.getAttribute('href') === '#' + visible.target.id));
    }, {rootMargin:'-20% 0px -65% 0px', threshold:[0,.2,.5]});
    document.querySelectorAll('.footer-editor-panel').forEach(panel => observer.observe(panel));
  }
})();
</script>
<?php admin_page_end(); ?>
