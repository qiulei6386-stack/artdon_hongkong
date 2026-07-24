<?php
/**
 * Artdon Lighting Website V7.1.3
 * Safe lightweight product category + homepage publish helper.
 * No front template modification by default.
 */
declare(strict_types=1);

require_once __DIR__ . '/pretty_urls_v71868.php';

if (!function_exists('artdon_v713_e')) {
function artdon_v713_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

function artdon_v713_root(): string { return dirname(__DIR__); }

function artdon_v713_include_core(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $root = artdon_v713_root();
    foreach ([$root.'/includes/bootstrap.php', $root.'/includes/product_hierarchy.php', $root.'/config.php', $root.'/includes/config.php'] as $file) {
        if (is_file($file)) {
            try { require_once $file; } catch (Throwable $e) {}
        }
    }
}

function artdon_v713_pdo(): ?PDO {
    artdon_v713_include_core();
    $err = null;
    if (function_exists('web_db')) {
        try {
            $pdo = web_db($err);
            if ($pdo instanceof PDO) {
                try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}
                return $pdo;
            }
        } catch (Throwable $e) { return null; }
    }
    return null;
}

function artdon_v713_table_exists(PDO $pdo, string $table): bool {
    try { $s=$pdo->prepare('SHOW TABLES LIKE ?'); $s->execute([$table]); return (bool)$s->fetchColumn(); } catch (Throwable $e) { return false; }
}

function artdon_v713_columns(PDO $pdo, string $table, bool $refresh = false): array {
    static $cache = [];
    $key = spl_object_id($pdo).'|'.$table;
    if (!$refresh && isset($cache[$key])) return $cache[$key];
    $cols = [];
    try {
        $q = $pdo->query('SHOW COLUMNS FROM `'.str_replace('`','``',$table).'`');
        foreach (($q ? $q->fetchAll(PDO::FETCH_ASSOC) : []) as $r) $cols[(string)$r['Field']] = $r;
    } catch (Throwable $e) {}
    return $cache[$key] = $cols;
}

function artdon_v713_has_cols(PDO $pdo, string $table, array $needed): bool {
    $cols = artdon_v713_columns($pdo, $table);
    foreach ($needed as $c) if (!isset($cols[$c])) return false;
    return true;
}


/* V7.1.8.129: one canonical product-category system.
 * These eight categories are the only real category source used by the front menu,
 * products.php left filter, product grouped sections, and series edit dropdown.
 */
if (!function_exists('artdon_v718129_canonical_category_rows')) {
function artdon_v718129_canonical_category_rows(bool $includeAll = true): array {
    $rows = [];
    if ($includeAll) {
        $rows[] = ['slug'=>'all','display_name'=>'All Products','nav_label'=>'All Products','home_tab_label'=>'All Products','family_title'=>'All Products','page_title'=>'Architectural lighting products','seo_title'=>'Architectural lighting products | Artdon Lighting','sort_order'=>0,'is_active'=>1,'family_intro'=>''];
    }
    $rows = array_merge($rows, [
        ['slug'=>'track-lights','display_name'=>'Track Lights','nav_label'=>'Track Lights','home_tab_label'=>'Track Lights','family_title'=>'Track Lights','page_title'=>'Track Lights','seo_title'=>'Track Lights | Artdon Lighting','sort_order'=>10,'is_active'=>1,'family_intro'=>''],
        ['slug'=>'downlights','display_name'=>'Downlights','nav_label'=>'Downlights','home_tab_label'=>'Downlights','family_title'=>'Downlights','page_title'=>'Downlights','seo_title'=>'Downlights | Artdon Lighting','sort_order'=>20,'is_active'=>1,'family_intro'=>''],
        ['slug'=>'magnetic-systems','display_name'=>'Magnetic Systems','nav_label'=>'Magnetic Systems','home_tab_label'=>'Magnetic Systems','family_title'=>'Magnetic Systems','page_title'=>'Magnetic Systems','seo_title'=>'Magnetic Systems | Artdon Lighting','sort_order'=>30,'is_active'=>1,'family_intro'=>''],
        ['slug'=>'surface-pendant-lights','display_name'=>'Surface & Pendant Lights','nav_label'=>'Surface & Pendant Lights','home_tab_label'=>'Surface & Pendant Lights','family_title'=>'Surface & Pendant Lights','page_title'=>'Surface & Pendant Lights','seo_title'=>'Surface & Pendant Lights | Artdon Lighting','sort_order'=>40,'is_active'=>1,'family_intro'=>''],
        ['slug'=>'linear-lighting','display_name'=>'Linear Lighting','nav_label'=>'Linear Lighting','home_tab_label'=>'Linear Lighting','family_title'=>'Linear Lighting','page_title'=>'Linear Lighting','seo_title'=>'Linear Lighting | Artdon Lighting','sort_order'=>50,'is_active'=>1,'family_intro'=>''],
        ['slug'=>'outdoor-lighting','display_name'=>'Outdoor Lighting','nav_label'=>'Outdoor Lighting','home_tab_label'=>'Outdoor Lighting','family_title'=>'Outdoor Lighting','page_title'=>'Outdoor Lighting','seo_title'=>'Outdoor Lighting | Artdon Lighting','sort_order'=>60,'is_active'=>1,'family_intro'=>''],
        ['slug'=>'led-strips-profiles','display_name'=>'LED Strips & Profiles','nav_label'=>'LED Strips & Profiles','home_tab_label'=>'LED Strips & Profiles','family_title'=>'LED Strips & Profiles','page_title'=>'LED Strips & Profiles','seo_title'=>'LED Strips & Profiles | Artdon Lighting','sort_order'=>70,'is_active'=>1,'family_intro'=>''],
        ['slug'=>'track-systems-accessories','display_name'=>'Track Systems & Accessories','nav_label'=>'Track Systems & Accessories','home_tab_label'=>'Track Systems & Accessories','family_title'=>'Track Systems & Accessories','page_title'=>'Track Systems & Accessories','seo_title'=>'Track Systems & Accessories | Artdon Lighting','sort_order'=>80,'is_active'=>1,'family_intro'=>''],
    ]);
    return $rows;
}
}

if (!function_exists('artdon_v718129_canonical_slugs')) {
function artdon_v718129_canonical_slugs(bool $includeAll = true): array {
    return array_values(array_map(static fn(array $r): string => (string)$r['slug'], artdon_v718129_canonical_category_rows($includeAll)));
}
}

if (!function_exists('artdon_v718129_norm_key')) {
function artdon_v718129_norm_key(string $value): string {
    $value = trim($value);
    $value = str_replace(['&','＋','+'], ' and ', $value);
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '-', $value) ?: $value;
    return trim($value, '-');
}
}

if (!function_exists('artdon_v718129_alias_map')) {
function artdon_v718129_alias_map(): array {
    $raw = [
        'all'=>['','all','all-products','all categories','all-categories','products'],
        'track-lights'=>['track','track-light','track-lights','track lighting','track-lighting','track spotlights','track spotlight','spotlights for track'],
        'downlights'=>['downlight','downlights','recessed','recessed-downlight','recessed-downlights','recessed downlight','recessed downlights','recessed lighting'],
        'magnetic-systems'=>['magnetic','magnetic-system','magnetic-systems','magnetic systems','magnetic lighting','48v magnetic','48v magnetic systems'],
        'surface-pendant-lights'=>['surface-pendant-lights','surface and pendant lights','surface-pendant','surface pendant','surface lights','surface-lighting','surface mounted','pendant','pendant lights','surface & pendant lights'],
        'linear-lighting'=>['linear','linear-light','linear-lighting','linear lighting','linear lights','linear luminaire','linear luminaires'],
        'outdoor-lighting'=>['outdoor','outdoor-light','outdoor-lighting','outdoor lighting','outdoor lights','exterior','exterior lighting'],
        'led-strips-profiles'=>['led-strips-profiles','led strips profiles','led-strips-and-profiles','led strips and profiles','led strip','led strips','led profiles','profiles','strips profiles'],
        'track-systems-accessories'=>['track-systems-accessories','track systems accessories','track systems and accessories','track-system-accessories','track system accessories','track systems','track-system','track accessories','track-accessories','accessories'],
    ];
    $map = [];
    foreach ($raw as $slug => $items) {
        $map[artdon_v718129_norm_key($slug)] = $slug;
        foreach ($items as $item) $map[artdon_v718129_norm_key((string)$item)] = $slug;
    }
    return $map;
}
}

if (!function_exists('artdon_v718129_category_slug')) {
function artdon_v718129_category_slug(string $value, string $fallback = 'downlights'): string {
    $raw = trim($value);
    if ($raw === '') return $fallback;
    $key = artdon_v718129_norm_key($raw);
    $map = artdon_v718129_alias_map();
    if (isset($map[$key])) return $map[$key];
    $slug = function_exists('artdon_v713_slug') ? artdon_v713_slug($raw) : $key;
    if ($slug === '') return $fallback;
    // V7.1.8.130：允许后台新增自定义分类。未知但有效的 slug 不再强行回落到 downlights。
    return $slug;
}
}

if (!function_exists('artdon_v718129_category_by_slug')) {
function artdon_v718129_category_by_slug(string $slug, ?PDO $pdo = null): ?array {
    $slug = artdon_v718129_category_slug($slug, 'all');
    if ($slug === '') return null;
    $canonical = [];
    foreach (artdon_v718129_canonical_category_rows(true) as $row) $canonical[(string)$row['slug']] = $row;
    $row = $canonical[$slug] ?? null;

    if ($pdo instanceof PDO && artdon_v713_table_exists($pdo, 'artdon_product_categories_v713')) {
        try {
            $st = $pdo->prepare('SELECT * FROM artdon_product_categories_v713 WHERE slug=? LIMIT 1');
            $st->execute([$slug]);
            $db = $st->fetch(PDO::FETCH_ASSOC);
            if ($db) {
                if (!$row) {
                    $row = [
                        'slug'=>(string)$db['slug'],
                        'display_name'=>(string)($db['display_name'] ?? $db['slug']),
                        'nav_label'=>(string)($db['nav_label'] ?? ($db['display_name'] ?? $db['slug'])),
                        'home_tab_label'=>(string)($db['home_tab_label'] ?? ($db['display_name'] ?? $db['slug'])),
                        'family_title'=>(string)($db['family_title'] ?? ($db['display_name'] ?? $db['slug'])),
                        'page_title'=>(string)($db['page_title'] ?? ($db['display_name'] ?? $db['slug'])),
                        'seo_title'=>(string)($db['seo_title'] ?? (($db['display_name'] ?? $db['slug']).' | Artdon Lighting')),
                        'sort_order'=>(int)($db['sort_order'] ?? 100),
                        'is_active'=>(int)($db['is_active'] ?? 1),
                        'family_intro'=>'',
                    ];
                }
                foreach (['display_name','nav_label','home_tab_label','family_title','family_intro','family_title_font_size','family_intro_font_size','family_intro_line_height','family_intro_gap','page_title','seo_title','seo_description','sort_order','is_active','is_deleted'] as $k) {
                    if (array_key_exists($k, $db) && ($k === 'family_intro' || trim((string)$db[$k]) !== '')) $row[$k] = $db[$k];
                }
                $row['updated_at'] = $db['updated_at'] ?? null;
            }
        } catch (Throwable $e) {}
    }
    if (!$row) return null;
    $row['display_name'] = trim((string)($row['display_name'] ?? '')) ?: ucwords(str_replace('-', ' ', $slug));
    $row['name'] = $row['display_name'];
    $row['nav_label'] = trim((string)($row['nav_label'] ?? '')) ?: $row['display_name'];
    $row['home_tab_label'] = trim((string)($row['home_tab_label'] ?? '')) ?: $row['display_name'];
    $row['family_title'] = trim((string)($row['family_title'] ?? '')) ?: $row['display_name'];
    $row['page_title'] = trim((string)($row['page_title'] ?? '')) ?: $row['display_name'];
    $row['seo_title'] = trim((string)($row['seo_title'] ?? '')) ?: ($row['page_title'].' | Artdon Lighting');
    $row['subtitle'] = $row['subtitle'] ?? '';
    $row['description'] = $row['description'] ?? '';
    return $row;
}
}

if (!function_exists('artdon_v718129_front_categories')) {
function artdon_v718129_front_categories(?PDO $pdo = null, bool $includeAll = true): array {
    // V7.1.8.130：前台分类直接读取统一分类表，默认分类 + 用户新增分类全部联动。
    if ($pdo instanceof PDO) {
        try { return artdon_v713_categories($pdo, true, $includeAll); } catch (Throwable $e) {}
    }
    return artdon_v718129_canonical_category_rows($includeAll);
}
}

if (!function_exists('artdon_v718129_normalize_series_list')) {
function artdon_v718129_normalize_series_list(array $seriesList): array {
    foreach ($seriesList as &$series) {
        if (!is_array($series)) continue;
        $series['category_slug'] = artdon_v718129_category_slug((string)($series['category_slug'] ?? ''), 'downlights');
    }
    unset($series);
    return $seriesList;
}
}

if (!function_exists('artdon_v718129_normalize_existing_product_categories')) {
function artdon_v718129_normalize_existing_product_categories(PDO $pdo): void {
    $alias = artdon_v718129_alias_map();
    $targets = ['web_products','web_product_variants','product_variants','artdon_product_variants'];
    foreach ($targets as $table) {
        if (!artdon_v713_table_exists($pdo, $table)) continue;
        $cols = artdon_v713_columns($pdo, $table, true);
        foreach (['category_slug','category','product_category','family_slug','type_slug'] as $col) {
            if (!isset($cols[$col])) continue;
            try {
                $rows = $pdo->query('SELECT DISTINCT `'.$col.'` AS slug FROM `'.str_replace('`','``',$table).'` WHERE `'.$col.'` IS NOT NULL AND TRIM(CAST(`'.$col.'` AS CHAR))<>""')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                foreach ($rows as $old) {
                    $old = trim((string)$old); if ($old === '') continue;
                    $new = artdon_v718129_category_slug($old, '');
                    if ($new === '' || $new === $old || $new === 'all') continue;
                    $st = $pdo->prepare('UPDATE `'.str_replace('`','``',$table).'` SET `'.$col.'`=? WHERE `'.$col.'`=?');
                    $st->execute([$new, $old]);
                }
            } catch (Throwable $e) {}
        }
    }
}
}


function artdon_v718128_category_key(string $name): string {
    $name = trim($name);
    $name = str_replace(['&','＋','+'], 'and', $name);
    $name = strtolower($name);
    $name = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '', $name) ?: $name;
    return $name !== '' ? $name : 'category';
}

function artdon_v713_deleted_category_slugs(PDO $pdo): array {
    if (!artdon_v713_table_exists($pdo, 'artdon_product_categories_v713')) return [];
    $cols = artdon_v713_columns($pdo, 'artdon_product_categories_v713', true);
    if (!isset($cols['is_deleted'])) return [];
    try {
        $rows = $pdo->query("SELECT slug FROM artdon_product_categories_v713 WHERE COALESCE(is_deleted,0)=1")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $out = [];
        foreach ($rows as $slug) { $slug = trim((string)$slug); if ($slug !== '') $out[$slug] = true; }
        return $out;
    } catch (Throwable $e) { return []; }
}

function artdon_v713_category_usage_counts(PDO $pdo): array {
    $counts = [];
    $tables = [];
    foreach (['web_products','web_product_series','products','product_series','artdon_product_series','web_series','series','web_product_variants','product_variants','artdon_product_variants','web_product_models','product_models','web_models','models','web_product_items','product_items','items'] as $t) {
        if (artdon_v713_table_exists($pdo, $t) && artdon_v713_has_cols($pdo, $t, ['id'])) $tables[$t] = true;
    }
    try {
        foreach (artdon_v713_catalog_candidate_tables($pdo) as $t) $tables[$t] = true;
    } catch (Throwable $e) {}
    foreach (array_keys($tables) as $table) {
        if (preg_match('/(category|filter|option|log|backup|slot|cache|session|token|tmp|temp)/i', $table)) continue;
        $cols = artdon_v713_columns($pdo, $table);
        $catCols = [];
        foreach (['category_slug','category','family_slug','type_slug','product_category','source_category'] as $c) if (isset($cols[$c])) $catCols[] = $c;
        if (!$catCols) continue;
        $publishedWhere = '';
        if (isset($cols['is_deleted'])) $publishedWhere .= ' AND (is_deleted=0 OR is_deleted IS NULL)';
        if (isset($cols['deleted_at'])) $publishedWhere .= ' AND (deleted_at IS NULL OR deleted_at="0000-00-00 00:00:00")';
        foreach ($catCols as $col) {
            try {
                $q = $pdo->query('SELECT `'.$col.'` AS slug, COUNT(*) AS c FROM `'.str_replace('`','``',$table).'` WHERE `'.$col.'` IS NOT NULL AND TRIM(CAST(`'.$col.'` AS CHAR))<>""'.$publishedWhere.' GROUP BY `'.$col.'`');
                foreach (($q ? $q->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
                    $slug = trim((string)($r['slug'] ?? ''));
                    if ($slug === '') continue;
                    $slug = artdon_v718129_category_slug($slug, $slug);
                    $counts[$slug] = ($counts[$slug] ?? 0) + (int)($r['c'] ?? 0);
                }
            } catch (Throwable $e) {}
        }
    }
    return $counts;
}

function artdon_v713_delete_unused_category(PDO $pdo, string $slug): array {
    $slug = trim($slug);
    if ($slug === '' || $slug === 'all') return ['ok'=>false, 'message'=>'All Products 不能删除。'];
    artdon_v713_ensure($pdo);
    $counts = artdon_v713_category_usage_counts($pdo);
    $used = (int)($counts[$slug] ?? 0);
    if ($used > 0) return ['ok'=>false, 'message'=>'这个分类下面还有 '.$used.' 个产品/系列，不能直接删除；请先把这些产品改到其它分类。'];
    try {
        $pdo->beginTransaction();
        artdon_v713_add_column($pdo, 'artdon_product_categories_v713', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
        $st = $pdo->prepare("UPDATE artdon_product_categories_v713 SET is_deleted=1,is_active=0,updated_at=CURRENT_TIMESTAMP WHERE slug=?");
        $st->execute([$slug]);
        $changed = $st->rowCount();
        foreach (['web_product_categories','product_categories','artdon_product_categories','products_categories'] as $table) {
            if (!artdon_v713_table_exists($pdo,$table) || !artdon_v713_has_cols($pdo,$table,['slug'])) continue;
            try { $pdo->prepare('DELETE FROM `'.str_replace('`','``',$table).'` WHERE slug=?')->execute([$slug]); } catch (Throwable $e) {
                try { if (isset(artdon_v713_columns($pdo,$table)['is_active'])) $pdo->prepare('UPDATE `'.str_replace('`','``',$table).'` SET is_active=0 WHERE slug=?')->execute([$slug]); } catch (Throwable $e2) {}
            }
        }
        $pdo->commit();
        return ['ok'=>true, 'message'=>$changed > 0 ? '分类已删除。' : '分类已从前台隐藏/删除。'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $ignore) {} }
        return ['ok'=>false, 'message'=>'删除失败：'.$e->getMessage()];
    }
}


function artdon_v713_add_column(PDO $pdo, string $table, string $column, string $definition): void {
    $cols = artdon_v713_columns($pdo, $table);
    if (isset($cols[$column])) return;
    try {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        artdon_v713_columns($pdo, $table, true);
    } catch (Throwable $e) {}
}

function artdon_v713_default_categories(): array {
    return artdon_v718129_canonical_category_rows(true);
}

function artdon_v713_slug(string $text): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text) ?: '', '-'));
    return $slug !== '' ? $slug : 'category';
}

function artdon_v713_native_categories(PDO $pdo): array {
    artdon_v713_include_core();
    if (function_exists('web_product_hierarchy_migrate')) { try { web_product_hierarchy_migrate($pdo); } catch (Throwable $e) {} }
    if (function_exists('web_product_categories')) {
        try { $rows=web_product_categories($pdo, true); if (is_array($rows) && $rows) return array_values($rows); } catch (Throwable $e) {}
    }
    foreach (['web_product_categories','product_categories','artdon_product_categories','products_categories'] as $table) {
        if (!artdon_v713_table_exists($pdo, $table) || !artdon_v713_has_cols($pdo, $table, ['slug'])) continue;
        $cols = artdon_v713_columns($pdo, $table);
        $sel=[]; foreach (['id','slug','name','page_title','seo_title','sort_order','is_active','family_intro'] as $c) if (isset($cols[$c])) $sel[]='`'.$c.'`';
        if (!$sel) continue;
        try { return $pdo->query('SELECT '.implode(',', $sel).' FROM `'.$table.'` ORDER BY '.(isset($cols['sort_order'])?'sort_order ASC,':'').' id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}
    }
    return [];
}

function artdon_v713_ensure(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS artdon_product_categories_v713 (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL,
        display_name VARCHAR(255) NOT NULL DEFAULT '',
        nav_label VARCHAR(255) NOT NULL DEFAULT '',
        home_tab_label VARCHAR(255) NOT NULL DEFAULT '',
        family_title VARCHAR(255) NOT NULL DEFAULT '',
        family_intro TEXT NULL,
        family_title_font_size SMALLINT UNSIGNED NOT NULL DEFAULT 64,
        family_intro_font_size SMALLINT UNSIGNED NOT NULL DEFAULT 24,
        family_intro_line_height SMALLINT UNSIGNED NOT NULL DEFAULT 34,
        family_intro_gap SMALLINT NOT NULL DEFAULT 18,
        page_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 100,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // V7.1.8.128: deleted categories stay deleted, so default/native sync will not recreate them.
    artdon_v713_add_column($pdo, 'artdon_product_categories_v713', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
    // V7.1.8.121: text under Products grouped family title, editable in category backend.
    artdon_v713_add_column($pdo, 'artdon_product_categories_v713', 'family_intro', 'TEXT NULL AFTER family_title');
    // V7.1.8.124: per-category family title / intro typography controls.
    artdon_v713_add_column($pdo, 'artdon_product_categories_v713', 'family_title_font_size', 'SMALLINT UNSIGNED NOT NULL DEFAULT 64 AFTER family_intro');
    artdon_v713_add_column($pdo, 'artdon_product_categories_v713', 'family_intro_font_size', 'SMALLINT UNSIGNED NOT NULL DEFAULT 24 AFTER family_title_font_size');
    artdon_v713_add_column($pdo, 'artdon_product_categories_v713', 'family_intro_line_height', 'SMALLINT UNSIGNED NOT NULL DEFAULT 34 AFTER family_intro_font_size');
    artdon_v713_add_column($pdo, 'artdon_product_categories_v713', 'family_intro_gap', 'SMALLINT NOT NULL DEFAULT 18 AFTER family_intro_line_height');
    try { $pdo->exec("UPDATE artdon_product_categories_v713 SET family_title_font_size=64 WHERE family_title_font_size IS NULL OR family_title_font_size=0"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE artdon_product_categories_v713 SET family_intro_font_size=24 WHERE family_intro_font_size IS NULL OR family_intro_font_size=0"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE artdon_product_categories_v713 SET family_intro_line_height=34 WHERE family_intro_line_height IS NULL OR family_intro_line_height=0"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE artdon_product_categories_v713 SET family_intro_gap=18 WHERE family_intro_gap IS NULL OR family_intro_gap<0"); } catch (Throwable $e) {}

    // V7.1.8.130：默认分类自动补齐，但不再删除/隐藏用户新增分类；
    // 已删除的默认分类也不会在每次迁移时被强制恢复。
    $canonRowsV718129 = artdon_v718129_canonical_category_rows(true);
    $stmtCanonV718129 = $pdo->prepare("INSERT INTO artdon_product_categories_v713(slug,display_name,nav_label,home_tab_label,family_title,family_intro,page_title,seo_title,sort_order,is_active,is_deleted) VALUES(?,?,?,?,?,?,?,?,?,1,0) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),nav_label=VALUES(nav_label),home_tab_label=VALUES(home_tab_label),page_title=VALUES(page_title),seo_title=VALUES(seo_title),sort_order=VALUES(sort_order),family_title=IF(family_title IS NULL OR family_title='',VALUES(family_title),family_title)");
    foreach ($canonRowsV718129 as $d) {
        $stmtCanonV718129->execute([(string)$d['slug'],(string)$d['display_name'],(string)$d['nav_label'],(string)$d['home_tab_label'],(string)$d['family_title'],(string)($d['family_intro'] ?? ''),(string)$d['page_title'],(string)$d['seo_title'],(int)$d['sort_order']]);
    }
    try { artdon_v718129_normalize_existing_product_categories($pdo); } catch (Throwable $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS artdon_home_product_slots_v713 (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        board_key VARCHAR(120) NOT NULL DEFAULT 'all',
        item_type VARCHAR(20) NOT NULL DEFAULT 'series',
        item_id INT UNSIGNED NOT NULL DEFAULT 0,
        item_slug VARCHAR(255) NOT NULL DEFAULT '',
        item_name VARCHAR(255) NOT NULL DEFAULT '',
        category_slug VARCHAR(120) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 100,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_board_item (board_key,item_type,item_id),
        KEY idx_board (board_key,sort_order),
        KEY idx_item (item_type,item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS artdon_feature_flags_v713 (
        flag_key VARCHAR(120) NOT NULL PRIMARY KEY,
        flag_value VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->prepare("INSERT IGNORE INTO artdon_feature_flags_v713(flag_key,flag_value) VALUES('home_dynamic_slots','0'),('category_unified_names','0')")->execute();

    // V7.1.8.129: old native category auto-import disabled.
    // Categories are fixed and canonical; native/legacy rows are normalized above.

}

function artdon_v713_categories(?PDO $pdo=null, bool $activeOnly=false, bool $includeAll=true): array {
    if (!$pdo) $pdo=artdon_v713_pdo();
    if (!$pdo) return artdon_v713_default_categories();
    try { artdon_v713_ensure($pdo); } catch (Throwable $e) {}
    $out = [];
    try {
        $where = 'COALESCE(is_deleted,0)=0';
        if ($activeOnly) $where .= ' AND COALESCE(is_active,1)=1';
        if (!$includeAll) $where .= " AND slug<>'all'";
        $rows = $pdo->query("SELECT * FROM artdon_product_categories_v713 WHERE {$where} ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $canonical = [];
        foreach (artdon_v718129_canonical_category_rows(true) as $r) $canonical[(string)$r['slug']] = $r;
        foreach ($rows as $db) {
            $slug = trim((string)($db['slug'] ?? ''));
            if ($slug === '') continue;
            $base = $canonical[$slug] ?? [];
            $row = array_merge($base, $db);
            $display = trim((string)($row['display_name'] ?? '')) ?: (trim((string)($row['name'] ?? '')) ?: ucwords(str_replace('-', ' ', $slug)));
            $row['slug'] = $slug;
            $row['display_name'] = $display;
            $row['name'] = $display;
            $row['nav_label'] = trim((string)($row['nav_label'] ?? '')) ?: $display;
            $row['home_tab_label'] = trim((string)($row['home_tab_label'] ?? '')) ?: $display;
            $row['family_title'] = trim((string)($row['family_title'] ?? '')) ?: $display;
            $row['page_title'] = trim((string)($row['page_title'] ?? '')) ?: $display;
            $row['seo_title'] = trim((string)($row['seo_title'] ?? '')) ?: ($row['page_title'].' | Artdon Lighting');
            $out[] = $row;
        }
    } catch (Throwable $e) {}
    if (!$out) return artdon_v718129_canonical_category_rows($includeAll);
    return $out;
}

function artdon_v713_category_map(?PDO $pdo=null): array {
    $out=[]; foreach (artdon_v713_categories($pdo,false) as $r) $out[(string)$r['slug']]=$r; return $out;
}

function artdon_v713_catalog_candidate_tables(PDO $pdo): array {
    $priority = [
        'web_product_series','product_series','artdon_product_series','web_products','products','product','web_series','series','catalog_series','web_catalog_series',
        'web_product_variants','product_variants','artdon_product_variants','web_product_models','product_models','web_models','models','web_product_items','product_items','items'
    ];
    $tables = [];
    foreach ($priority as $t) if (artdon_v713_table_exists($pdo, $t) && artdon_v713_has_cols($pdo, $t, ['id'])) $tables[$t] = true;
    try {
        $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' AND (TABLE_NAME LIKE '%product%' OR TABLE_NAME LIKE '%series%' OR TABLE_NAME LIKE '%catalog%' OR TABLE_NAME LIKE '%variant%' OR TABLE_NAME LIKE '%model%') ORDER BY TABLE_NAME ASC LIMIT 80");
        $stmt->execute([$db]);
        foreach (($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $t) {
            $t = (string)$t;
            if (preg_match('/(log|backup|slot|setting|category|filter|option|cache|session|token|tmp|temp)/i', $t)) continue;
            if (artdon_v713_has_cols($pdo, $t, ['id'])) $tables[$t] = true;
        }
    } catch (Throwable $e) {}
    return array_keys($tables);
}

function artdon_v713_series_table(PDO $pdo): string {
    foreach (['web_product_series','product_series','artdon_product_series','web_products','products','web_series','series','catalog_series','web_catalog_series'] as $t) {
        if (artdon_v713_table_exists($pdo,$t) && artdon_v713_has_cols($pdo,$t,['id'])) return $t;
    }
    return '';
}

function artdon_v713_variant_table(PDO $pdo): string {
    foreach (['web_product_variants','product_variants','artdon_product_variants','web_product_models','product_models','web_models','models','web_product_items','product_items','items'] as $t) {
        if (artdon_v713_table_exists($pdo,$t) && artdon_v713_has_cols($pdo,$t,['id'])) return $t;
    }
    return '';
}

function artdon_v713_col_expr(array $cols, array $names, string $alias): string {
    foreach ($names as $n) if (isset($cols[$n])) return '`'.$n.'` AS `'.$alias.'`';
    return "'' AS `".$alias."`";
}

function artdon_v713_pick_col(array $cols, array $names): string {
    foreach ($names as $n) if (isset($cols[$n])) return $n;
    return '';
}

function artdon_v713_text_cols(array $cols): array {
    $want = ['series_name','product_name','name','title','model','sku','code','slug','size_name','subtitle','short_description','description','category_slug','category','family','type'];
    $out = [];
    foreach ($want as $c) if (isset($cols[$c])) $out[] = $c;
    if ($out) return array_values(array_unique($out));
    foreach ($cols as $name=>$meta) {
        $type = strtolower((string)($meta['Type'] ?? ''));
        if (preg_match('/char|text|enum|set/i', $type)) $out[] = (string)$name;
        if (count($out) >= 8) break;
    }
    return array_values(array_unique($out));
}

function artdon_v713_guess_type(string $table, string $requested=''): string {
    if ($requested === 'series' || $requested === 'product') return $requested;
    if (preg_match('/variant|model|sku|item/i', $table)) return 'product';
    return 'series';
}

function artdon_v713_build_item_from_row(string $table, string $kind, array $cols, array $r, array $catMap): ?array {
    $id = (int)($r['id'] ?? 0);
    if ($id <= 0) return null;
    $titleCol = artdon_v713_pick_col($cols, ['series_name','product_name','name','title','model','sku','code','size_name','slug']);
    $slugCol  = artdon_v713_pick_col($cols, ['slug','model','sku','code']);
    $catCol   = artdon_v713_pick_col($cols, ['category_slug','category','family_slug','type_slug','product_category','source_category']);
    $textCol  = artdon_v713_pick_col($cols, ['subtitle','short_description','description','series_name','name']);
    $title = trim((string)($titleCol !== '' ? ($r[$titleCol] ?? '') : ''));
    if ($title === '') $title = ucfirst($kind).' '.$id;
    $slug = trim((string)($slugCol !== '' ? ($r[$slugCol] ?? '') : ''));
    if ($slug === '') $slug = (string)$id;
    $cs = trim((string)($catCol !== '' ? ($r[$catCol] ?? '') : ''));
    $text = trim((string)($textCol !== '' ? ($r[$textCol] ?? '') : ''));
    $url = $kind === 'product'
        ? artdon_pretty_product_url_v71868($cs, 'product', ['model_code' => $slug, 'slug' => $slug, 'name' => $title])
        : 'series.php?slug='.rawurlencode($slug);
    return [
        'type'=>$kind,
        'id'=>$id,
        'slug'=>$slug,
        'title'=>$title,
        'category_slug'=>$cs,
        'category_name'=>(string)($catMap[$cs]['display_name'] ?? $cs),
        'text'=>$text,
        'url'=>$url,
        '_source_table'=>$table,
    ];
}

function artdon_v713_catalog_search(PDO $pdo, string $q='', string $type='', string $cat='', int $limit=30): array {
    $limit = max(5, min(80, $limit));
    $q = trim($q);
    $catMap = artdon_v713_category_map($pdo);
    $out = [];
    $seen = [];
    $tables = artdon_v713_catalog_candidate_tables($pdo);
    if (!$tables) return [];

    foreach ($tables as $table) {
        if (count($out) >= $limit) break;
        $cols = artdon_v713_columns($pdo, $table);
        if (!isset($cols['id'])) continue;
        $kind = artdon_v713_guess_type($table, $type);
        if ($type !== '' && $kind !== $type) continue;
        $searchCols = artdon_v713_text_cols($cols);
        if (!$searchCols) continue;
        $selectCols = ['`id`'];
        foreach ($searchCols as $c) $selectCols[] = '`'.$c.'`';
        $selectCols = array_values(array_unique($selectCols));
        $where = [];
        $vals = [];
        if (isset($cols['is_published'])) $where[] = '(`is_published`=1 OR `is_published` IS NULL)';
        elseif (isset($cols['published'])) $where[] = '(`published`=1 OR `published` IS NULL)';
        elseif (isset($cols['status'])) $where[] = "(`status` IS NULL OR `status`='' OR `status` IN ('published','active','show','1'))";
        if ($cat !== '') {
            $catCols = [];
            foreach (['category_slug','category','family_slug','type_slug','product_category','source_category'] as $c) if (isset($cols[$c])) $catCols[]=$c;
            if ($catCols) {
                $or=[];
                foreach ($catCols as $c) { $or[]='`'.$c.'`=?'; $vals[]=$cat; }
                $where[]='('.implode(' OR ', $or).')';
            }
        }
        if ($q !== '') {
            $or=[];
            foreach ($searchCols as $c) { $or[]='`'.$c.'` LIKE ?'; $vals[]='%'.$q.'%'; }
            $where[]='('.implode(' OR ', $or).')';
        } else {
            // No keyword: keep page safe and do not load everything.
            continue;
        }
        $order = isset($cols['sort_order']) ? '`sort_order` ASC, `id` DESC' : '`id` DESC';
        $sql = 'SELECT '.implode(',', $selectCols).' FROM `'.str_replace('`','``',$table).'` WHERE '.implode(' AND ', $where).' ORDER BY '.$order.' LIMIT '.max(5, $limit - count($out));
        try {
            $st=$pdo->prepare($sql);
            $st->execute($vals);
            foreach (($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
                $item = artdon_v713_build_item_from_row($table, $kind, $cols, $r, $catMap);
                if (!$item) continue;
                $key = $item['type'].'|'.$item['id'].'|'.$item['slug'].'|'.$table;
                if (isset($seen[$key])) continue;
                $seen[$key]=true;
                $out[]=$item;
                if (count($out) >= $limit) break 2;
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    usort($out, static fn($a,$b): int => strcmp(strtolower((string)$a['title']), strtolower((string)$b['title'])));
    return array_slice($out,0,$limit);
}

function artdon_v713_catalog_debug(PDO $pdo): array {
    $rows = [];
    foreach (artdon_v713_catalog_candidate_tables($pdo) as $table) {
        $cols = artdon_v713_columns($pdo, $table);
        $count = '';
        try { $count = (string)$pdo->query('SELECT COUNT(*) FROM `'.str_replace('`','``',$table).'`')->fetchColumn(); } catch (Throwable $e) { $count = '?'; }
        $rows[] = ['table'=>$table, 'count'=>$count, 'columns'=>implode(', ', array_slice(array_keys($cols),0,18))];
        if (count($rows) >= 20) break;
    }
    return $rows;
}

function artdon_v713_resolve_item(PDO $pdo, string $type, int $id): ?array {
    foreach (artdon_v713_catalog_search($pdo,'',$type,'',80) as $item) if ($item['type']===$type && (int)$item['id']===$id) return $item;
    // fallback direct broad search not possible; keep name from posted fields if needed in admin.
    return null;
}

function artdon_v713_save_slot(PDO $pdo, string $board, string $type, int $id, string $slug, string $name, string $categorySlug, int $sort=100, int $active=1): void {
    $stmt=$pdo->prepare("INSERT INTO artdon_home_product_slots_v713(board_key,item_type,item_id,item_slug,item_name,category_slug,sort_order,is_active) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE item_slug=VALUES(item_slug),item_name=VALUES(item_name),category_slug=VALUES(category_slug),sort_order=VALUES(sort_order),is_active=VALUES(is_active)");
    $stmt->execute([$board,$type,$id,$slug,$name,$categorySlug,$sort,$active]);
}

function artdon_v713_item_boards(PDO $pdo, string $type, int $id): array {
    try { $s=$pdo->prepare('SELECT * FROM artdon_home_product_slots_v713 WHERE item_type=? AND item_id=? ORDER BY board_key ASC'); $s->execute([$type,$id]); $rows=$s->fetchAll(PDO::FETCH_ASSOC)?:[]; $out=[]; foreach($rows as $r)$out[(string)$r['board_key']]=$r; return $out; } catch (Throwable $e) { return []; }
}

function artdon_v713_board_slots(PDO $pdo, string $board): array {
    try { $s=$pdo->prepare('SELECT * FROM artdon_home_product_slots_v713 WHERE board_key=? ORDER BY sort_order ASC,id ASC LIMIT 200'); $s->execute([$board]); return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; } catch (Throwable $e) { return []; }
}

function artdon_v713_set_flag(PDO $pdo, string $key, string $value): void {
    $pdo->prepare('INSERT INTO artdon_feature_flags_v713(flag_key,flag_value) VALUES(?,?) ON DUPLICATE KEY UPDATE flag_value=VALUES(flag_value)')->execute([$key,$value]);
}

function artdon_v713_get_flags(PDO $pdo): array {
    try { $rows=$pdo->query('SELECT flag_key,flag_value FROM artdon_feature_flags_v713')->fetchAll(PDO::FETCH_KEY_PAIR); return is_array($rows)?$rows:[]; } catch (Throwable $e) { return []; }
}

function artdon_v713_sync_native_categories(PDO $pdo): int {
    $deletedSlugs = artdon_v713_deleted_category_slugs($pdo);
    $rows=artdon_v713_categories($pdo,false); $changed=0;
    if ($deletedSlugs) {
        foreach (['web_product_categories','product_categories','artdon_product_categories','products_categories'] as $table) {
            if (!artdon_v713_table_exists($pdo,$table) || !artdon_v713_has_cols($pdo,$table,['slug'])) continue;
            foreach (array_keys($deletedSlugs) as $deletedSlug) {
                try { $pdo->prepare('DELETE FROM `'.str_replace('`','``',$table).'` WHERE slug=?')->execute([$deletedSlug]); } catch (Throwable $e) {}
            }
        }
    }
    foreach (['web_product_categories','product_categories','artdon_product_categories','products_categories'] as $table) {
        if (!artdon_v713_table_exists($pdo,$table) || !artdon_v713_has_cols($pdo,$table,['slug'])) continue;
        if ($table === 'web_product_categories') artdon_v713_add_column($pdo, $table, 'family_intro', 'TEXT NULL AFTER description');
        $cols=artdon_v713_columns($pdo,$table,true);
        foreach($rows as $r) {
            $slug = (string)($r['slug'] ?? '');
            if ($slug === '') continue;

            // Native products page reads web_product_categories. Keep that table in sync too,
            // including the V7.1.8.121 family_intro field. Do not wait for another save.
            if ($table === 'web_product_categories' && isset($cols['name'], $cols['slug'])) {
                try {
                    $display = trim((string)($r['display_name'] ?? '')) ?: ucwords(str_replace('-', ' ', $slug));
                    $page = trim((string)($r['page_title'] ?? '')) ?: $display;
                    $seo = trim((string)($r['seo_title'] ?? '')) ?: ($page . ' | Artdon Lighting');
                    $familyIntro = trim((string)($r['family_intro'] ?? ''));
                    $sort = (int)($r['sort_order'] ?? 100);
                    $active = (int)($r['is_active'] ?? 1);
                    $stmt = $pdo->prepare("INSERT INTO web_product_categories (name, slug, page_title, subtitle, description, family_intro, seo_title, seo_description, sort_order, is_active)
                        VALUES (?, ?, ?, '', '', ?, ?, '', ?, ?)
                        ON DUPLICATE KEY UPDATE name=VALUES(name), page_title=VALUES(page_title), family_intro=VALUES(family_intro), seo_title=VALUES(seo_title), sort_order=VALUES(sort_order), is_active=VALUES(is_active)");
                    $stmt->execute([$display, $slug, $page, $familyIntro, $seo, $sort, $active]);
                    $changed += max(1, $stmt->rowCount());
                    continue;
                } catch (Throwable $e) {
                    // Fall back to the generic update below for unusual legacy table structures.
                }
            }

            $set=[]; $vals=[];
            $map=['name'=>'display_name','page_title'=>'page_title','seo_title'=>'seo_title','sort_order'=>'sort_order','is_active'=>'is_active','family_intro'=>'family_intro'];
            foreach($map as $col=>$src) if(isset($cols[$col])) { $set[]='`'.$col.'`=?'; $vals[]=$r[$src]??''; }
            if(!$set) continue;
            $vals[]=$slug;
            try { $st=$pdo->prepare('UPDATE `'.$table.'` SET '.implode(',',$set).' WHERE slug=?'); $st->execute($vals); $changed += $st->rowCount(); } catch (Throwable $e) {}
        }
    }
    return $changed;
}

// ===== V7.1.4 front homepage bridge helpers =====
if (!function_exists('artdon_v713_public_path')) {
function artdon_v713_public_path(string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (function_exists('web_public_path')) {
        try { return web_public_path($path); } catch (Throwable $e) {}
    }
    if ($path[0] === '/') return $path;
    return '/'.ltrim($path, '/');
}
}

if (!function_exists('artdon_v713_image_col')) {
function artdon_v713_image_col(array $cols): string {
    foreach (['cover_image','image','main_image','hero_image','product_image','thumb_image','thumbnail','photo','img','picture','image_url'] as $c) {
        if (isset($cols[$c])) return $c;
    }
    return '';
}
}

if (!function_exists('artdon_v713_desc_col')) {
function artdon_v713_desc_col(array $cols): string {
    foreach (['subtitle','short_description','description','summary','intro','product_desc','series_desc'] as $c) {
        if (isset($cols[$c])) return $c;
    }
    return '';
}
}

if (!function_exists('artdon_v713_lookup_catalog_item')) {
function artdon_v713_lookup_catalog_item(PDO $pdo, string $type, int $id, string $savedSlug='', string $savedName=''): ?array {
    $catMap = artdon_v713_category_map($pdo);
    foreach (artdon_v713_catalog_candidate_tables($pdo) as $table) {
        $cols = artdon_v713_columns($pdo, $table);
        if (!isset($cols['id'])) continue;
        $kind = artdon_v713_guess_type($table, $type);
        if ($kind !== $type) continue;
        $wanted = ['id'];
        foreach (['series_name','product_name','name','title','model','sku','code','slug','category_slug','category','family_slug','type_slug','product_category','source_category','cover_image','image','main_image','hero_image','product_image','thumb_image','thumbnail','photo','img','picture','image_url','subtitle','short_description','description','summary','intro','sort_order','is_published','published','status'] as $c) {
            if (isset($cols[$c])) $wanted[] = $c;
        }
        $wanted = array_values(array_unique($wanted));
        try {
            $st = $pdo->prepare('SELECT `'.implode('`,`', array_map(static fn($c)=>str_replace('`','``',$c), $wanted)).'` FROM `'.str_replace('`','``',$table).'` WHERE id=? LIMIT 1');
            $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) continue;
            $item = artdon_v713_build_item_from_row($table, $kind, $cols, $r, $catMap);
            if (!$item) continue;
            $imgCol = artdon_v713_image_col($cols);
            $descCol = artdon_v713_desc_col($cols);
            $item['image'] = $imgCol !== '' ? (string)($r[$imgCol] ?? '') : '';
            $item['text'] = $descCol !== '' ? trim((string)($r[$descCol] ?? '')) : (string)($item['text'] ?? '');
            if ($item['text'] === '' && $savedName !== '') $item['text'] = '';
            if ($savedSlug !== '' && (string)$item['slug'] === '') $item['slug'] = $savedSlug;
            if ($savedName !== '' && (string)$item['title'] === '') $item['title'] = $savedName;
            return $item;
        } catch (Throwable $e) { continue; }
    }
    return null;
}
}

if (!function_exists('artdon_v713_home_tabs_public')) {
function artdon_v713_home_tabs_public(PDO $pdo, array $boardKeys=[]): array {
    $cats = artdon_v713_categories($pdo, true);
    $catMap = [];
    foreach ($cats as $c) $catMap[(string)$c['slug']] = $c;
    $wanted = ['all'=>true];
    foreach ($boardKeys as $b) if ($b !== '') $wanted[(string)$b] = true;
    $tabs = [];
    foreach ($cats as $c) {
        $slug = (string)$c['slug'];
        if (!isset($wanted[$slug])) continue;
        $label = (string)($c['home_tab_label'] ?: $c['display_name'] ?: $slug);
        $tabs[] = ['key'=>$slug, 'label'=>$label, 'active'=>1];
    }
    if (!$tabs) $tabs[] = ['key'=>'all','label'=>'All Products','active'=>1];
    return $tabs;
}
}

if (!function_exists('artdon_v713_home_public_data')) {
function artdon_v713_home_public_data(?PDO $pdo = null): array {
    if (!$pdo) $pdo = artdon_v713_pdo();
    if (!$pdo) return ['dynamic'=>false,'items'=>[],'tabs'=>[],'slots'=>0];
    try { artdon_v713_ensure($pdo); } catch (Throwable $e) { return ['dynamic'=>false,'items'=>[],'tabs'=>[],'slots'=>0]; }
    try {
        $slots = $pdo->query("SELECT * FROM artdon_home_product_slots_v713 WHERE is_active=1 ORDER BY board_key ASC, sort_order ASC, id ASC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { $slots = []; }
    if (!$slots) return ['dynamic'=>false,'items'=>[],'tabs'=>artdon_v713_home_tabs_public($pdo, []),'slots'=>0];

    $catMap = artdon_v713_category_map($pdo);
    $merged = [];
    $boardKeys = [];
    foreach ($slots as $slot) {
        $type = (string)($slot['item_type'] ?? 'series');
        $id = (int)($slot['item_id'] ?? 0);
        if (!in_array($type, ['series','product'], true) || $id <= 0) continue;
        $board = (string)($slot['board_key'] ?? 'all');
        if ($board === '') $board = 'all';
        $boardKeys[$board] = true;
        $key = $type.'|'.$id;
        if (!isset($merged[$key])) {
            $savedSlug = (string)($slot['item_slug'] ?? '');
            $savedName = (string)($slot['item_name'] ?? '');
            $item = artdon_v713_lookup_catalog_item($pdo, $type, $id, $savedSlug, $savedName);
            if (!$item) {
                $item = [
                    'type'=>$type,
                    'id'=>$id,
                    'slug'=>$savedSlug !== '' ? $savedSlug : (string)$id,
                    'title'=>$savedName !== '' ? $savedName : ucfirst($type).' '.$id,
                    'category_slug'=>(string)($slot['category_slug'] ?? ''),
                    'category_name'=>(string)($catMap[(string)($slot['category_slug'] ?? '')]['display_name'] ?? (string)($slot['category_slug'] ?? '')),
                    'image'=>'',
                    'text'=>'',
                    'url'=>$type === 'product' ? artdon_pretty_product_url_v71868((string)($slot['category_slug'] ?? 'products'), 'product', ['model_code' => $savedSlug !== '' ? $savedSlug : (string)$id, 'slug' => $savedSlug !== '' ? $savedSlug : (string)$id, 'name' => $savedName]) : 'series.php?slug='.rawurlencode($savedSlug !== '' ? $savedSlug : (string)$id),
                ];
            }
            $merged[$key] = $item;
            $merged[$key]['_boards'] = [];
            $merged[$key]['_sort'] = (int)($slot['sort_order'] ?? 100);
        }
        $merged[$key]['_boards'][$board] = true;
        // V7.1.4: ALL is an independent board. Do not auto-add All or source category.
        $merged[$key]['_sort'] = min((int)$merged[$key]['_sort'], (int)($slot['sort_order'] ?? 100));
    }
    uasort($merged, static fn($a,$b): int => ((int)($a['_sort'] ?? 100) <=> (int)($b['_sort'] ?? 100)) ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
    $out = [];
    foreach ($merged as $item) {
        $boards = array_keys($item['_boards'] ?? ['all'=>true]);
        $catSlug = (string)($item['category_slug'] ?? '');
        $catName = (string)($catMap[$catSlug]['display_name'] ?? ($item['category_name'] ?? $catSlug));
        $title = (string)($item['title'] ?? '');
        $out[] = [
            'active'=>1,
            'featured'=>1,
            'source_type'=>'v713_'.$item['type'],
            'url'=>(string)($item['url'] ?? '#'),
            'image'=>artdon_v713_public_path((string)($item['image'] ?? '')),
            'title'=>$title,
            'type'=>$catName !== '' ? $catName : ucfirst((string)($item['type'] ?? 'Series')),
            'text'=>(string)($item['text'] ?? ''),
            'categories'=>implode(' ', array_values(array_unique(array_filter($boards)))),
            'boards'=>array_values(array_unique(array_filter($boards))),
            'slug'=>(string)($item['slug'] ?? ''),
        ];
    }
    $tabs = artdon_v713_home_tabs_public($pdo, array_keys($boardKeys));
    return ['dynamic'=>!empty($out),'items'=>$out,'tabs'=>$tabs,'slots'=>count($slots)];
}
}


// ===== V7.1.4 admin dashboard helpers =====
if (!function_exists('artdon_v714_all_slots')) {
function artdon_v714_all_slots(PDO $pdo, string $keyword=''): array {
    try { artdon_v713_ensure($pdo); } catch (Throwable $e) {}
    $where = '1=1';
    $vals = [];
    $keyword = trim($keyword);
    if ($keyword !== '') {
        $where .= " AND (item_name LIKE ? OR item_slug LIKE ? OR category_slug LIKE ? OR item_type LIKE ? OR board_key LIKE ?)";
        for ($i=0;$i<5;$i++) $vals[] = '%'.$keyword.'%';
    }
    try {
        $st = $pdo->prepare("SELECT * FROM artdon_home_product_slots_v713 WHERE $where ORDER BY board_key ASC, sort_order ASC, id ASC LIMIT 800");
        $st->execute($vals);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}
}

if (!function_exists('artdon_v714_slot_counts')) {
function artdon_v714_slot_counts(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT board_key, COUNT(*) AS c FROM artdon_home_product_slots_v713 WHERE is_active=1 GROUP BY board_key")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out=[]; foreach($rows as $r) $out[(string)$r['board_key']] = (int)$r['c']; return $out;
    } catch (Throwable $e) { return []; }
}
}
