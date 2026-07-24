<?php
/**
 * Artdon V7.1.7.6
 * Product / series card badge style library.
 *
 * Stores badge text/style/position/animation in artdon_card_flags so all front-end card renderers share one source.
 */
declare(strict_types=1);

require_once __DIR__ . '/artdon_card_simple_v7093.php';

if (!function_exists('artdon_badge_v7176_e')) {
function artdon_badge_v7176_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('artdon_badge_v7176_options')) {
function artdon_badge_v7176_options(): array
{
    return [
        'text_types' => [
            'none' => '关闭',
            'new' => 'NEW',
            'hot' => 'HOT',
            'featured' => 'FEATURED',
            'pro' => 'PRO',
            'custom' => '自定义文字',
        ],
        'styles' => [
            'rect' => '红色矩形',
            'capsule' => '红色胶囊',
            'polygon16' => '16 边形徽章',
            'circle' => '圆形徽章',
            'outline' => '空心描边',
            'black' => '黑底白字',
            'corner' => '左上斜角标',
            'ribbon' => '右上丝带',
            'breathing-dot' => '呼吸红点',
            'topline' => '顶部细线',
        ],
        'positions' => [
            'top-left' => '左上',
            'top-right' => '右上',
            'bottom-left' => '左下',
            'bottom-right' => '右下',
        ],
        'animations' => [
            'none' => '关闭',
            'breathe' => '呼吸',
            'pulse' => '轻微闪动',
        ],
    ];
}
}

if (!function_exists('artdon_badge_v7176_norm_key')) {
function artdon_badge_v7176_norm_key($value, array $allowed, string $default): string
{
    $value = strtolower(trim((string)$value));
    return isset($allowed[$value]) ? $value : $default;
}
}

if (!function_exists('artdon_badge_v7176_label_text')) {
function artdon_badge_v7176_label_text(string $textType, string $customText = ''): string
{
    $customText = trim($customText);
    if ($textType === 'custom') return $customText !== '' ? (function_exists('mb_substr') ? mb_substr($customText, 0, 24, 'UTF-8') : substr($customText, 0, 24)) : 'NEW';
    if ($textType === 'none') return '';
    return strtoupper($textType);
}
}

if (!function_exists('artdon_badge_v7176_column_exists')) {
function artdon_badge_v7176_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) { return false; }
}
}

if (!function_exists('artdon_badge_v7176_ensure')) {
function artdon_badge_v7176_ensure(PDO $pdo): void
{
    static $done = [];
    $key = function_exists('spl_object_id') ? (string)spl_object_id($pdo) : 'default';
    if (!empty($done[$key])) return;
    artdon_card_v7093_ensure($pdo);
    $adds = [
        'badge_style' => "ALTER TABLE `artdon_card_flags` ADD COLUMN `badge_style` varchar(40) NOT NULL DEFAULT 'capsule' AFTER `badge_text`",
        'badge_position' => "ALTER TABLE `artdon_card_flags` ADD COLUMN `badge_position` varchar(20) NOT NULL DEFAULT 'top-left' AFTER `badge_style`",
        'badge_animation' => "ALTER TABLE `artdon_card_flags` ADD COLUMN `badge_animation` varchar(20) NOT NULL DEFAULT 'none' AFTER `badge_position`",
    ];
    foreach ($adds as $col => $sql) {
        if (!artdon_badge_v7176_column_exists($pdo, 'artdon_card_flags', $col)) {
            try { $pdo->exec($sql); } catch (Throwable $e) {}
        }
    }
    try {
        $pdo->exec("UPDATE artdon_card_flags SET badge_text='NEW' WHERE badge_type='new' AND TRIM(COALESCE(badge_text,''))=''");
        $pdo->exec("UPDATE artdon_card_flags SET badge_style='capsule' WHERE TRIM(COALESCE(badge_style,''))='' OR badge_style='star'");
        $pdo->exec("UPDATE artdon_card_flags SET badge_position='top-left' WHERE TRIM(COALESCE(badge_position,''))=''");
        $pdo->exec("UPDATE artdon_card_flags SET badge_animation='none' WHERE TRIM(COALESCE(badge_animation,''))=''");
    } catch (Throwable $e) {}
    $done[$key] = true;
}
}

if (!function_exists('artdon_badge_v7176_blank')) {
function artdon_badge_v7176_blank(): array
{
    return [
        'enabled' => 0,
        'text_type' => 'none',
        'custom_text' => '',
        'text' => '',
        'style' => 'capsule',
        'position' => 'top-left',
        'animation' => 'none',
    ];
}
}

if (!function_exists('artdon_badge_v7176_details_from_row')) {
function artdon_badge_v7176_details_from_row(array $row): array
{
    $opts = artdon_badge_v7176_options();
    $text = trim((string)($row['badge_text'] ?? ''));
    $style = artdon_badge_v7176_norm_key($row['badge_style'] ?? '', $opts['styles'], 'capsule');
    $position = artdon_badge_v7176_norm_key($row['badge_position'] ?? '', $opts['positions'], 'top-left');
    $animation = artdon_badge_v7176_norm_key($row['badge_animation'] ?? '', $opts['animations'], 'none');

    $low = strtolower($text);
    $textType = isset($opts['text_types'][$low]) && $low !== 'custom' && $low !== 'none' ? $low : 'custom';
    if ($text === '' || strtolower((string)($row['badge_type'] ?? '')) === 'none') {
        $textType = 'none';
    }
    return [
        'enabled' => !empty($row['enabled']) && $textType !== 'none' ? 1 : 0,
        'text_type' => $textType,
        'custom_text' => $textType === 'custom' ? $text : '',
        'text' => $text,
        'style' => $style,
        'position' => $position,
        'animation' => $animation,
    ];
}
}

if (!function_exists('artdon_badge_v7176_current')) {
function artdon_badge_v7176_current(PDO $pdo, string $type, $id = '', string $slug = '', string $name = ''): array
{
    artdon_badge_v7176_ensure($pdo);
    $type = $type === 'product' ? 'product' : 'series';
    $ids = [];
    foreach ([$slug, $id, 'slug:' . $slug, 'id:' . $id] as $value) {
        $value = trim((string)$value);
        if ($value !== '' && $value !== 'slug:' && $value !== 'id:') $ids[] = $value;
    }
    $ids = array_values(array_unique($ids));
    $name = trim($name);

    try {
        $cols = 'id,item_type,item_id,item_name,badge_type,badge_text,badge_style,badge_position,badge_animation,enabled';
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT $cols FROM artdon_card_flags WHERE item_type=? AND item_id IN ($ph) ORDER BY updated_at DESC,id DESC LIMIT 1");
            $stmt->execute(array_merge([$type], $ids));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) return artdon_badge_v7176_details_from_row($row);
        }
        if ($name !== '') {
            $stmt = $pdo->prepare("SELECT $cols FROM artdon_card_flags WHERE item_type=? AND item_name=? ORDER BY updated_at DESC,id DESC LIMIT 1");
            $stmt->execute([$type, $name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) return artdon_badge_v7176_details_from_row($row);
        }
    } catch (Throwable $e) {}
    return artdon_badge_v7176_blank();
}
}

if (!function_exists('artdon_badge_v7176_from_post')) {
function artdon_badge_v7176_from_post(array $post): array
{
    $opts = artdon_badge_v7176_options();
    $textType = artdon_badge_v7176_norm_key($post['artdon_badge_text_type'] ?? 'none', $opts['text_types'], 'none');
    $custom = trim((string)($post['artdon_badge_custom_text'] ?? ''));
    $style = artdon_badge_v7176_norm_key($post['artdon_badge_style'] ?? 'capsule', $opts['styles'], 'capsule');
    $position = artdon_badge_v7176_norm_key($post['artdon_badge_position'] ?? 'top-left', $opts['positions'], 'top-left');
    $animation = artdon_badge_v7176_norm_key($post['artdon_badge_animation'] ?? 'none', $opts['animations'], 'none');
    $enabled = $textType !== 'none' && !empty($post['artdon_badge_enabled']);
    if ($style === 'breathing-dot' && $animation === 'none') $animation = 'breathe';
    $text = $enabled ? artdon_badge_v7176_label_text($textType, $custom) : '';
    return [
        'enabled' => $enabled ? 1 : 0,
        'text_type' => $enabled ? $textType : 'none',
        'custom_text' => $custom,
        'text' => $text,
        'style' => $style,
        'position' => $position,
        'animation' => $animation,
    ];
}
}

if (!function_exists('artdon_badge_v7176_save')) {
function artdon_badge_v7176_save(PDO $pdo, string $type, $itemId, string $itemName, array $badge): void
{
    artdon_badge_v7176_ensure($pdo);
    $type = $type === 'product' ? 'product' : 'series';
    $itemId = trim((string)$itemId);
    $itemName = trim($itemName);
    if ($itemId === '' && $itemName === '') return;

    $text = trim((string)($badge['text'] ?? ''));
    $enabled = !empty($badge['enabled']) && $text !== '';
    $style = artdon_badge_v7176_norm_key($badge['style'] ?? 'capsule', artdon_badge_v7176_options()['styles'], 'capsule');
    $position = artdon_badge_v7176_norm_key($badge['position'] ?? 'top-left', artdon_badge_v7176_options()['positions'], 'top-left');
    $animation = artdon_badge_v7176_norm_key($badge['animation'] ?? 'none', artdon_badge_v7176_options()['animations'], 'none');

    try {
        $pdo->prepare('DELETE FROM artdon_card_flags WHERE item_type=? AND (item_id=? OR item_name=?)')->execute([$type, $itemId, $itemName]);
        if ($enabled) {
            $stmt = $pdo->prepare('INSERT INTO artdon_card_flags(item_type,item_id,item_name,badge_type,badge_text,badge_style,badge_position,badge_animation,enabled,note) VALUES(?,?,?,?,?,?,?,?,1,?)');
            $stmt->execute([$type, $itemId, $itemName, 'new', $text, $style, $position, $animation, 'V7.1.7.6 标识样式库']);
        }
    } catch (Throwable $e) {
        throw new RuntimeException('卡片标识保存失败：' . $e->getMessage());
    }
}
}

if (!function_exists('artdon_badge_v7176_series_identity')) {
function artdon_badge_v7176_series_identity(array $row): array
{
    $id = $row['slug'] ?? ($row['id'] ?? '');
    $name = trim((string)($row['series_name'] ?? $row['name'] ?? $row['title'] ?? ''));
    return [(string)$id, $name];
}
}

if (!function_exists('artdon_badge_v7176_product_identity')) {
function artdon_badge_v7176_product_identity(array $row): array
{
    $id = $row['slug'] ?? ($row['id'] ?? '');
    $name = trim((string)($row['name'] ?? $row['model_code'] ?? $row['size_name'] ?? ''));
    return [(string)$id, $name];
}
}

if (!function_exists('artdon_badge_v7176_select')) {
function artdon_badge_v7176_select(string $name, array $options, string $current, string $class = ''): string
{
    $html = '<select name="' . artdon_badge_v7176_e($name) . '"' . ($class !== '' ? ' class="' . artdon_badge_v7176_e($class) . '"' : '') . '>';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . artdon_badge_v7176_e($value) . '"' . ($current === $value ? ' selected' : '') . '>' . artdon_badge_v7176_e($label) . '</option>';
    }
    $html .= '</select>';
    return $html;
}
}

if (!function_exists('artdon_badge_v7176_field')) {
function artdon_badge_v7176_field(array $current, string $label = '首页 / 列表标识'): string
{
    $opts = artdon_badge_v7176_options();
    $current = array_merge(artdon_badge_v7176_blank(), $current);
    $textType = artdon_badge_v7176_norm_key($current['text_type'] ?? 'none', $opts['text_types'], 'none');
    $style = artdon_badge_v7176_norm_key($current['style'] ?? 'capsule', $opts['styles'], 'capsule');
    $position = artdon_badge_v7176_norm_key($current['position'] ?? 'top-left', $opts['positions'], 'top-left');
    $animation = artdon_badge_v7176_norm_key($current['animation'] ?? 'none', $opts['animations'], 'none');
    $custom = (string)($current['custom_text'] ?? '');
    $enabled = !empty($current['enabled']);
    $previewText = artdon_badge_v7176_label_text($textType, $custom);
    if ($previewText === '') $previewText = 'NEW';

    $html = '<div class="field span-2 artdon-badge-field-v7176"><label>' . artdon_badge_v7176_e($label) . '</label>';
    $html .= '<div class="artdon-badge-v7176-panel">';
    $html .= '<label class="artdon-badge-v7176-switch"><input type="checkbox" name="artdon_badge_enabled" value="1"' . ($enabled ? ' checked' : '') . '> <span>启用标识</span></label>';
    $html .= '<div class="artdon-badge-v7176-grid">';
    $html .= '<div><small>标识文字</small>' . artdon_badge_v7176_select('artdon_badge_text_type', $opts['text_types'], $textType, 'js-artdon-badge-text-type') . '</div>';
    $html .= '<div><small>自定义文字</small><input name="artdon_badge_custom_text" value="' . artdon_badge_v7176_e($custom) . '" placeholder="例如 LIMITED / 2026" maxlength="24" class="js-artdon-badge-custom"></div>';
    $html .= '<div><small>标识样式</small>' . artdon_badge_v7176_select('artdon_badge_style', $opts['styles'], $style, 'js-artdon-badge-style') . '</div>';
    $html .= '<div><small>显示位置</small>' . artdon_badge_v7176_select('artdon_badge_position', $opts['positions'], $position, 'js-artdon-badge-position') . '</div>';
    $html .= '<div><small>动画</small>' . artdon_badge_v7176_select('artdon_badge_animation', $opts['animations'], $animation, 'js-artdon-badge-animation') . '</div>';
    $html .= '</div>';
    $html .= '<div class="artdon-badge-v7176-preview-wrap"><span>预览</span><div class="artdon-badge-v7176-card"><span class="artdon-badge-preview artdon-card-badge-v7093 style-' . artdon_badge_v7176_e($style) . ' pos-' . artdon_badge_v7176_e($position) . ' anim-' . artdon_badge_v7176_e($animation) . '">' . artdon_badge_v7176_e($previewText) . '</span></div></div>';
    $html .= '<p class="help">前台产品列表、首页卡片、系列页产品卡片共用这一套标识。星标已替换为可选样式库。</p>';
    $html .= '</div></div>';
    return $html;
}
}

if (!function_exists('artdon_badge_v7176_style')) {
function artdon_badge_v7176_style(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    echo <<<'HTML'
<style>
.artdon-badge-field-v7176{grid-column:span 2}.artdon-badge-v7176-panel{border:1px solid #dbe3ef;border-radius:14px;padding:14px;background:#fff}.artdon-badge-v7176-switch{display:inline-flex;align-items:center;gap:8px;font-weight:900;margin-bottom:10px}.artdon-badge-v7176-grid{display:grid;grid-template-columns:repeat(5,minmax(120px,1fr));gap:10px}.artdon-badge-v7176-grid small{display:block;color:#667085;font-weight:800;margin:0 0 5px}.artdon-badge-v7176-preview-wrap{margin-top:12px;display:flex;align-items:center;gap:12px;color:#667085;font-weight:800}.artdon-badge-v7176-card{position:relative;width:190px;height:126px;background:#e5e8eb;border:1px solid #d6dbe2;overflow:hidden}.artdon-badge-v7176-panel .help{display:block;margin-top:8px;color:#667085;font-size:12px;line-height:1.45}.artdon-badge-v7176-panel input,.artdon-badge-v7176-panel select{width:100%;box-sizing:border-box}.artdon-badge-v7176-panel .artdon-card-badge-v7093{position:absolute!important}.artdon-badge-v7176-panel .artdon-card-badge-v7093.style-topline{width:100%!important}.artdon-badge-v7176-panel .artdon-card-badge-v7093.style-corner,.artdon-badge-v7176-panel .artdon-card-badge-v7093.style-ribbon{position:absolute!important}@media(max-width:1100px){.artdon-badge-v7176-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.artdon-badge-field-v7176{grid-column:1/-1}}
</style>
<script>
(function(){
function q(root,s){return root.querySelector(s)}
function sync(box){var type=q(box,'.js-artdon-badge-text-type'),custom=q(box,'.js-artdon-badge-custom'),style=q(box,'.js-artdon-badge-style'),pos=q(box,'.js-artdon-badge-position'),anim=q(box,'.js-artdon-badge-animation'),prev=q(box,'.artdon-badge-preview');if(!prev)return;var text=(type&&type.value==='custom')?(custom&&custom.value?custom.value:'NEW'):(type&&type.value&&type.value!=='none'?type.value.toUpperCase():'NEW');prev.textContent=text;prev.className='artdon-badge-preview artdon-card-badge-v7093 style-'+(style?style.value:'capsule')+' pos-'+(pos?pos.value:'top-left')+' anim-'+(anim?anim.value:'none');}
document.querySelectorAll('.artdon-badge-v7176-panel').forEach(function(box){box.addEventListener('input',function(){sync(box)});box.addEventListener('change',function(){sync(box)});sync(box);});
})();
</script>
HTML;
}
}

// Backward-safe aliases for older files that already require v7175.
if (!function_exists('artdon_badge_v7175_ensure')) { function artdon_badge_v7175_ensure(PDO $pdo): void { artdon_badge_v7176_ensure($pdo); } }
if (!function_exists('artdon_badge_v7175_series_identity')) { function artdon_badge_v7175_series_identity(array $row): array { return artdon_badge_v7176_series_identity($row); } }
if (!function_exists('artdon_badge_v7175_product_identity')) { function artdon_badge_v7175_product_identity(array $row): array { return artdon_badge_v7176_product_identity($row); } }
if (!function_exists('artdon_badge_v7175_style')) { function artdon_badge_v7175_style(): void { artdon_badge_v7176_style(); } }
