<?php
/**
 * Artdon Lighting Website V7.1.1
 * Product category unified naming + homepage product publish helper.
 */
declare(strict_types=1);

if (!function_exists('artdon_v711_e')) {
function artdon_v711_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

function artdon_v711_root(): string { return dirname(__DIR__); }

function artdon_v711_include_core(): void {
    $root = artdon_v711_root();
    foreach ([$root.'/includes/bootstrap.php', $root.'/includes/product_hierarchy.php'] as $file) {
        if (is_file($file)) {
            try { require_once $file; } catch (Throwable $e) {}
        }
    }
}

function artdon_v711_pdo(): ?PDO {
    artdon_v711_include_core();
    $err = null;
    if (function_exists('web_db')) {
        try {
            $pdo = web_db($err);
            return $pdo instanceof PDO ? $pdo : null;
        } catch (Throwable $e) { return null; }
    }
    return null;
}

function artdon_v711_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function artdon_v711_columns(PDO $pdo, string $table): array {
    static $cache = [];
    $key = spl_object_id($pdo).'|'.$table;
    if (isset($cache[$key])) return $cache[$key];
    $cols = [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `'.str_replace('`','``',$table).'`');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $cols[(string)$row['Field']] = $row;
    } catch (Throwable $e) {}
    return $cache[$key] = $cols;
}

function artdon_v711_has_cols(PDO $pdo, string $table, array $needed): bool {
    $cols = artdon_v711_columns($pdo, $table);
    foreach ($needed as $col) if (!isset($cols[$col])) return false;
    return true;
}

function artdon_v711_slug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?: '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'category';
}

function artdon_v711_norm(string $text): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($text)) ?: '');
}

function artdon_v711_default_categories(): array {
    return [
        ['slug'=>'all','display_name'=>'All Products','nav_label'=>'All Products','home_tab_label'=>'All Products','family_title'=>'All Products','page_title'=>'Architectural lighting products','subtitle'=>'Commercial LED luminaires','description'=>'Browse Artdon commercial architectural lighting product families and technical luminaires.','seo_title'=>'Architectural lighting products | Artdon Lighting','seo_description'=>'Commercial architectural lighting product catalogue from Artdon Lighting.','sort_order'=>0,'is_active'=>1],
        ['slug'=>'downlights','display_name'=>'Recessed Downlight','nav_label'=>'Recessed Downlight','home_tab_label'=>'Recessed Downlight','family_title'=>'Recessed Downlight','page_title'=>'Recessed Downlight','subtitle'=>'Low-glare recessed luminaires','description'=>'Recessed downlight families for commercial ceilings, retail, hospitality and architectural projects.','seo_title'=>'Recessed Downlight | Artdon Lighting','seo_description'=>'Low-glare recessed downlight families from Artdon Lighting for commercial architectural projects.','sort_order'=>10,'is_active'=>1],
        ['slug'=>'track-lights','display_name'=>'Track Lights','nav_label'=>'Track Lights','home_tab_label'=>'Track Lights','family_title'=>'Spotlights for track','page_title'=>'Track Lights','subtitle'=>'Flexible accent lighting for commercial spaces','description'=>'Track spotlights, wallwashers and linear modules for retail, museum and hospitality applications.','seo_title'=>'Track Lights | Artdon Lighting','seo_description'=>'Track lighting systems and spotlights from Artdon Lighting.','sort_order'=>20,'is_active'=>1],
        ['slug'=>'magnetic-systems','display_name'=>'Magnetic Systems','nav_label'=>'Magnetic Systems','home_tab_label'=>'Magnetic Systems','family_title'=>'Magnetic Systems','page_title'=>'Magnetic Systems','subtitle'=>'One track, multiple lighting tools','description'=>'Modular magnetic systems with spot, linear and grille luminaires.','seo_title'=>'Magnetic Systems | Artdon Lighting','seo_description'=>'48V magnetic lighting systems from Artdon Lighting.','sort_order'=>30,'is_active'=>1],
        ['slug'=>'surface-pendant-lights','display_name'=>'Surface & Pendant Lights','nav_label'=>'Surface & Pendant Lights','home_tab_label'=>'Surface & Pendant Lights','family_title'=>'Surface & Pendant Lights','page_title'=>'Surface & Pendant Lights','subtitle'=>'Ceiling-mounted and suspended luminaires','description'=>'Surface mounted and pendant luminaires for commercial architectural lighting projects.','seo_title'=>'Surface & Pendant Lights | Artdon Lighting','seo_description'=>'Surface and pendant lighting families from Artdon Lighting.','sort_order'=>35,'is_active'=>1],
        ['slug'=>'linear-lighting','display_name'=>'Linear Lighting','nav_label'=>'Linear Lighting','home_tab_label'=>'Linear Lighting','family_title'=>'Linear Lighting','page_title'=>'Linear Lighting','subtitle'=>'Continuous, precise and integrated light','description'=>'Linear luminaires for shelves, coves, circulation and architectural details.','seo_title'=>'Linear Lighting | Artdon Lighting','seo_description'=>'Architectural linear lighting from Artdon Lighting.','sort_order'=>40,'is_active'=>1],
        ['slug'=>'outdoor-lighting','display_name'=>'Outdoor Lighting','nav_label'=>'Outdoor Lighting','home_tab_label'=>'Outdoor Lighting','family_title'=>'Outdoor Lighting','page_title'=>'Outdoor Lighting','subtitle'=>'Exterior projectors and linear luminaires','description'=>'IP-rated projectors and linear systems for facades and landscape.','seo_title'=>'Outdoor Lighting | Artdon Lighting','seo_description'=>'Outdoor architectural lighting from Artdon Lighting.','sort_order'=>50,'is_active'=>1],
    ];
}

function artdon_v711_native_category_rows(PDO $pdo): array {
    artdon_v711_include_core();
    if (function_exists('web_product_hierarchy_migrate')) {
        try { web_product_hierarchy_migrate($pdo); } catch (Throwable $e) {}
    }
    if (function_exists('web_product_categories')) {
        try {
            $rows = web_product_categories($pdo, true);
            if (is_array($rows) && $rows) return array_values($rows);
        } catch (Throwable $e) {}
    }
    foreach (['web_product_categories','product_categories','artdon_product_categories','products_categories'] as $table) {
        if (!artdon_v711_table_exists($pdo, $table) || !artdon_v711_has_cols($pdo, $table, ['slug'])) continue;
        $cols = artdon_v711_columns($pdo, $table);
        $select = [];
        foreach (['id','slug','name','page_title','subtitle','description','seo_title','seo_description','sort_order','is_active'] as $c) if (isset($cols[$c])) $select[]='`'.$c.'`';
        if (!$select) continue;
        try { return $pdo->query('SELECT '.implode(',', $select).' FROM `'.$table.'` ORDER BY '.(isset($cols['sort_order'])?'sort_order':'slug'))->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) {}
    }
    return [];
}

function artdon_v711_ensure(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS artdon_product_categories_v711 (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL,
        display_name VARCHAR(255) NOT NULL DEFAULT '',
        nav_label VARCHAR(255) NOT NULL DEFAULT '',
        home_tab_label VARCHAR(255) NOT NULL DEFAULT '',
        family_title VARCHAR(255) NOT NULL DEFAULT '',
        page_title VARCHAR(255) NOT NULL DEFAULT '',
        subtitle VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NULL,
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description TEXT NULL,
        sort_order INT NOT NULL DEFAULT 100,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS artdon_home_product_slots_v711 (
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

    $defaults = artdon_v711_default_categories();
    $native = artdon_v711_native_category_rows($pdo);
    foreach ($native as $idx=>$row) {
        $slug = (string)($row['slug'] ?? '');
        if ($slug === '') continue;
        $exists = false;
        foreach ($defaults as $d) if ($d['slug'] === $slug) { $exists = true; break; }
        if (!$exists) {
            $name = (string)($row['name'] ?? ucwords(str_replace('-', ' ', $slug)));
            $defaults[] = [
                'slug'=>$slug,
                'display_name'=>$slug === 'downlights' ? 'Recessed Downlight' : $name,
                'nav_label'=>$slug === 'downlights' ? 'Recessed Downlight' : $name,
                'home_tab_label'=>$slug === 'downlights' ? 'Recessed Downlight' : $name,
                'family_title'=>$slug === 'downlights' ? 'Recessed Downlight' : $name,
                'page_title'=>$slug === 'downlights' ? 'Recessed Downlight' : ((string)($row['page_title'] ?? $name)),
                'subtitle'=>(string)($row['subtitle'] ?? ''),
                'description'=>(string)($row['description'] ?? ''),
                'seo_title'=>(string)($row['seo_title'] ?? ($name.' | Artdon Lighting')),
                'seo_description'=>(string)($row['seo_description'] ?? ''),
                'sort_order'=>(int)($row['sort_order'] ?? (($idx+1)*10)),
                'is_active'=>isset($row['is_active']) ? (int)$row['is_active'] : 1,
            ];
        }
    }

    $stmt = $pdo->prepare("INSERT INTO artdon_product_categories_v711
        (slug,display_name,nav_label,home_tab_label,family_title,page_title,subtitle,description,seo_title,seo_description,sort_order,is_active)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          display_name=IF(display_name='',VALUES(display_name),display_name),
          nav_label=IF(nav_label='',VALUES(nav_label),nav_label),
          home_tab_label=IF(home_tab_label='',VALUES(home_tab_label),home_tab_label),
          family_title=IF(family_title='',VALUES(family_title),family_title),
          page_title=IF(page_title='',VALUES(page_title),page_title),
          subtitle=IF(subtitle='',VALUES(subtitle),subtitle),
          description=IF(description IS NULL OR description='',VALUES(description),description),
          seo_title=IF(seo_title='',VALUES(seo_title),seo_title),
          seo_description=IF(seo_description IS NULL OR seo_description='',VALUES(seo_description),seo_description),
          sort_order=IF(sort_order=100,VALUES(sort_order),sort_order),
          is_active=is_active");
    foreach ($defaults as $d) {
        $stmt->execute([$d['slug'],$d['display_name'],$d['nav_label'],$d['home_tab_label'],$d['family_title'],$d['page_title'],$d['subtitle'],$d['description'],$d['seo_title'],$d['seo_description'],(int)$d['sort_order'],(int)$d['is_active']]);
    }

    // V7.1.1 default decision: the old Downlights naming is unified to Recessed Downlight immediately.
    $pdo->prepare("UPDATE artdon_product_categories_v711 SET display_name='Recessed Downlight', nav_label='Recessed Downlight', home_tab_label='Recessed Downlight', family_title='Recessed Downlight', page_title='Recessed Downlight', seo_title='Recessed Downlight | Artdon Lighting' WHERE slug='downlights'")->execute();
    artdon_v711_sync_native_categories($pdo);
}

function artdon_v711_categories(?PDO $pdo = null, bool $activeOnly = false): array {
    if (!$pdo) $pdo = artdon_v711_pdo();
    if (!$pdo) return artdon_v711_default_categories();
    try { artdon_v711_ensure($pdo); } catch (Throwable $e) {}
    try {
        $sql = 'SELECT * FROM artdon_product_categories_v711 '.($activeOnly?'WHERE is_active=1 ':'').'ORDER BY sort_order ASC, id ASC';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $rows ?: artdon_v711_default_categories();
    } catch (Throwable $e) { return artdon_v711_default_categories(); }
}

function artdon_v711_category_map(?PDO $pdo = null): array {
    $map = [];
    foreach (artdon_v711_categories($pdo, false) as $row) $map[(string)$row['slug']] = $row;
    return $map;
}

function artdon_v711_apply_categories(?PDO $pdo, array $categories): array {
    $map = artdon_v711_category_map($pdo);
    $seen = [];
    foreach ($categories as &$cat) {
        $slug = (string)($cat['slug'] ?? '');
        if ($slug === '' || !isset($map[$slug])) continue;
        $u = $map[$slug];
        $cat['name'] = (string)($u['display_name'] ?: ($cat['name'] ?? ''));
        $cat['page_title'] = (string)($u['page_title'] ?: $cat['name']);
        $cat['subtitle'] = (string)($u['subtitle'] ?? ($cat['subtitle'] ?? ''));
        $cat['description'] = (string)($u['description'] ?? ($cat['description'] ?? ''));
        $cat['seo_title'] = (string)($u['seo_title'] ?? ($cat['seo_title'] ?? ''));
        $cat['seo_description'] = (string)($u['seo_description'] ?? ($cat['seo_description'] ?? ''));
        $cat['sort_order'] = (int)($u['sort_order'] ?? ($cat['sort_order'] ?? 100));
        $cat['is_active'] = (int)($u['is_active'] ?? ($cat['is_active'] ?? 1));
        $seen[$slug] = true;
    }
    unset($cat);
    foreach ($map as $slug=>$u) {
        if (isset($seen[$slug])) continue;
        $categories[] = [
            'name'=>(string)$u['display_name'], 'slug'=>$slug, 'page_title'=>(string)$u['page_title'], 'subtitle'=>(string)$u['subtitle'],
            'description'=>(string)$u['description'], 'seo_title'=>(string)$u['seo_title'], 'seo_description'=>(string)$u['seo_description'],
            'sort_order'=>(int)$u['sort_order'], 'is_active'=>(int)$u['is_active'],
        ];
    }
    usort($categories, static fn($a,$b): int => ((int)($a['sort_order']??100)) <=> ((int)($b['sort_order']??100)));
    return $categories;
}

function artdon_v711_family_titles(?PDO $pdo, array $categories = []): array {
    $out = [];
    foreach (artdon_v711_categories($pdo, true) as $row) {
        $slug = (string)$row['slug'];
        if ($slug === 'all') continue;
        $out[$slug] = (string)($row['family_title'] ?: $row['display_name']);
    }
    foreach ($categories as $cat) {
        $slug = (string)($cat['slug'] ?? '');
        if ($slug && $slug !== 'all' && !isset($out[$slug])) $out[$slug] = (string)($cat['name'] ?? ucwords(str_replace('-', ' ', $slug)));
    }
    return $out;
}

function artdon_v711_label_text($label, ?PDO $pdo = null): string {
    $label = (string)$label;
    $norm = artdon_v711_norm($label);
    if (in_array($norm, ['downlights','downlight'], true)) return 'Recessed Downlight';
    foreach (artdon_v711_categories($pdo, true) as $row) {
        $names = [(string)$row['slug'], (string)$row['display_name'], (string)$row['nav_label'], (string)$row['home_tab_label'], (string)$row['family_title']];
        foreach ($names as $n) {
            if ($n !== '' && artdon_v711_norm($n) === $norm) return (string)($row['nav_label'] ?: $row['display_name']);
        }
    }
    return $label;
}

function artdon_v711_sync_native_categories(PDO $pdo): void {
    $rows = artdon_v711_categories($pdo, false);
    foreach (['web_product_categories','product_categories','artdon_product_categories','products_categories'] as $table) {
        if (!artdon_v711_table_exists($pdo, $table) || !artdon_v711_has_cols($pdo, $table, ['slug'])) continue;
        $cols = artdon_v711_columns($pdo, $table);
        foreach ($rows as $r) {
            $slug = (string)$r['slug'];
            if ($slug === '') continue;
            $exists = false;
            try { $s=$pdo->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE slug=?'); $s->execute([$slug]); $exists=(int)$s->fetchColumn()>0; } catch (Throwable $e) {}
            if ($exists) {
                $set=[]; $vals=[];
                $pairs = ['name'=>'display_name','page_title'=>'page_title','subtitle'=>'subtitle','description'=>'description','seo_title'=>'seo_title','seo_description'=>'seo_description','sort_order'=>'sort_order','is_active'=>'is_active'];
                foreach ($pairs as $col=>$src) if (isset($cols[$col])) { $set[]='`'.$col.'`=?'; $vals[]=$r[$src] ?? ''; }
                if ($set) { $vals[]=$slug; try { $pdo->prepare('UPDATE `'.$table.'` SET '.implode(',', $set).' WHERE slug=?')->execute($vals); } catch (Throwable $e) {} }
            } else {
                $insertCols=['slug']; $vals=[$slug];
                $pairs = ['name'=>'display_name','page_title'=>'page_title','subtitle'=>'subtitle','description'=>'description','seo_title'=>'seo_title','seo_description'=>'seo_description','sort_order'=>'sort_order','is_active'=>'is_active'];
                foreach ($pairs as $col=>$src) if (isset($cols[$col])) { $insertCols[]=$col; $vals[]=$r[$src] ?? ''; }
                try { $pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$insertCols).'`) VALUES ('.implode(',', array_fill(0,count($insertCols),'?')).')')->execute($vals); } catch (Throwable $e) {}
            }
        }
    }
}

function artdon_v711_series_table(PDO $pdo): string {
    foreach (['web_product_series','product_series','artdon_product_series','web_products'] as $t) {
        if (artdon_v711_table_exists($pdo,$t) && artdon_v711_has_cols($pdo,$t,['id'])) return $t;
    }
    return '';
}

function artdon_v711_variant_table(PDO $pdo): string {
    foreach (['web_product_variants','product_variants','artdon_product_variants','web_product_models'] as $t) {
        if (artdon_v711_table_exists($pdo,$t) && artdon_v711_has_cols($pdo,$t,['id'])) return $t;
    }
    return '';
}

function artdon_v711_public_path(string $path): string {
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (function_exists('web_public_path')) {
        try { return web_public_path($path); } catch (Throwable $e) {}
    }
    return ltrim($path, '/');
}

function artdon_v711_catalog_items(?PDO $pdo = null): array {
    if (!$pdo) $pdo = artdon_v711_pdo();
    if (!$pdo) return [];
    artdon_v711_include_core();
    try { artdon_v711_ensure($pdo); } catch (Throwable $e) {}
    $catMap = artdon_v711_category_map($pdo);
    $items = [];
    if (function_exists('web_product_fetch_all')) {
        try {
            $seriesRows = web_product_fetch_all($pdo, true);
            foreach ((array)$seriesRows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;
                $cat = (string)($r['category_slug'] ?? '');
                $title = trim((string)($r['series_name'] ?? '')) ?: (string)($r['name'] ?? '');
                $slug = (string)($r['slug'] ?? $id);
                $items[] = [
                    'type'=>'series','id'=>$id,'slug'=>$slug,'title'=>$title,'category_slug'=>$cat,
                    'category_name'=>(string)($catMap[$cat]['display_name'] ?? $cat),
                    'image'=>(string)($r['cover_image'] ?? ''),
                    'url'=>'series.php?slug='.rawurlencode($slug),
                    'text'=>trim((string)($r['subtitle'] ?? '')) ?: trim((string)($r['short_description'] ?? ($r['description'] ?? ''))),
                    'type_label'=>'Series'
                ];
            }
        } catch (Throwable $e) {}
    }
    if (!$items) {
        $seriesTable = artdon_v711_series_table($pdo);
        if ($seriesTable !== '') {
            $cols = artdon_v711_columns($pdo, $seriesTable);
            $sel=[]; foreach (['id','slug','name','series_name','category_slug','cover_image','subtitle','short_description','description','is_published','sort_order'] as $c) if (isset($cols[$c])) $sel[]='`'.$c.'`';
            try {
                $where = isset($cols['is_published']) ? ' WHERE is_published=1' : '';
                $order = isset($cols['sort_order']) ? ' sort_order ASC, id DESC' : ' id DESC';
                $rs = $pdo->query('SELECT '.implode(',',$sel).' FROM `'.$seriesTable.'`'.$where.' ORDER BY '.$order)->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rs as $r) {
                    $id=(int)($r['id']??0); if($id<=0)continue;
                    $cat=(string)($r['category_slug']??''); $title=trim((string)($r['series_name']??''))?:trim((string)($r['name']??'')); $slug=(string)($r['slug']??$id);
                    $items[]=['type'=>'series','id'=>$id,'slug'=>$slug,'title'=>$title,'category_slug'=>$cat,'category_name'=>(string)($catMap[$cat]['display_name']??$cat),'image'=>(string)($r['cover_image']??''),'url'=>'series.php?slug='.rawurlencode($slug),'text'=>trim((string)($r['subtitle']??''))?:trim((string)($r['short_description']??($r['description']??''))),'type_label'=>'Series'];
                }
            } catch (Throwable $e) {}
        }
    }
    $variantTable = artdon_v711_variant_table($pdo);
    $seriesTable = artdon_v711_series_table($pdo);
    if ($variantTable !== '') {
        $vc = artdon_v711_columns($pdo, $variantTable);
        $sc = $seriesTable!=='' ? artdon_v711_columns($pdo,$seriesTable) : [];
        $select = ['v.id AS id'];
        foreach (['slug','name','size_name','cover_image','short_description','power_text','dimensions','series_id','is_published','sort_order'] as $c) if (isset($vc[$c])) $select[]='v.`'.$c.'` AS `'.$c.'`';
        if ($seriesTable!=='' && isset($vc['series_id'])) {
            foreach (['name','series_name','category_slug','slug'] as $c) if (isset($sc[$c])) $select[]='s.`'.$c.'` AS s_'.$c;
        }
        try {
            $join = ($seriesTable!=='' && isset($vc['series_id'])) ? ' LEFT JOIN `'.$seriesTable.'` s ON s.id=v.series_id ' : '';
            $where = isset($vc['is_published']) ? ' WHERE v.is_published=1' : '';
            $order = isset($vc['sort_order']) ? ' v.sort_order ASC, v.id DESC' : ' v.id DESC';
            $rs = $pdo->query('SELECT '.implode(',',$select).' FROM `'.$variantTable.'` v '.$join.$where.' ORDER BY '.$order.' LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rs as $r) {
                $id=(int)($r['id']??0); if($id<=0)continue;
                $cat=(string)($r['s_category_slug']??($r['category_slug']??''));
                $title=trim((string)($r['name']??'')); if($title==='') $title=trim((string)($r['size_name']??('Product '.$id)));
                $seriesName=trim((string)($r['s_series_name']??'')) ?: trim((string)($r['s_name']??''));
                $slug=(string)($r['slug']??$id);
                $text=trim((string)($r['short_description']??'')); if($text==='' && $seriesName!=='') $text=$seriesName;
                $items[]=['type'=>'product','id'=>$id,'slug'=>$slug,'title'=>$title,'category_slug'=>$cat,'category_name'=>(string)($catMap[$cat]['display_name']??$cat),'image'=>(string)($r['cover_image']??''),'url'=>'product.php?slug='.rawurlencode($slug),'text'=>$text,'type_label'=>'Product'];
            }
        } catch (Throwable $e) {}
    }
    usort($items, static function($a,$b){ return strcmp(($a['category_slug']??'').' '.($a['title']??''), ($b['category_slug']??'').' '.($b['title']??'')); });
    return $items;
}

function artdon_v711_resolve_item(PDO $pdo, string $type, int $id): ?array {
    foreach (artdon_v711_catalog_items($pdo) as $item) {
        if ($item['type'] === $type && (int)$item['id'] === $id) return $item;
    }
    return null;
}

function artdon_v711_home_tabs(?PDO $pdo = null): array {
    $tabs=[];
    foreach (artdon_v711_categories($pdo, true) as $row) {
        $slug=(string)$row['slug'];
        $tabs[]=['key'=>$slug,'label'=>(string)($row['home_tab_label'] ?: $row['display_name']),'active'=>1];
    }
    return $tabs;
}

function artdon_v711_home_public_data(?PDO $pdo = null): array {
    if (!$pdo) $pdo = artdon_v711_pdo();
    if (!$pdo) return ['dynamic'=>false,'items'=>[],'tabs'=>[]];
    try { artdon_v711_ensure($pdo); } catch (Throwable $e) { return ['dynamic'=>false,'items'=>[],'tabs'=>[]]; }
    try { $slots = $pdo->query('SELECT * FROM artdon_home_product_slots_v711 WHERE is_active=1 ORDER BY board_key ASC, sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { $slots=[]; }
    if (!$slots) return ['dynamic'=>false,'items'=>[],'tabs'=>artdon_v711_home_tabs($pdo)];
    $catalog = [];
    foreach (artdon_v711_catalog_items($pdo) as $item) $catalog[$item['type'].'|'.$item['id']] = $item;
    $merged=[];
    foreach ($slots as $slot) {
        $key=(string)$slot['item_type'].'|'.(int)$slot['item_id'];
        if (!isset($catalog[$key])) continue;
        $item=$catalog[$key];
        $mk=$key;
        if (!isset($merged[$mk])) {
            $merged[$mk] = $item;
            $merged[$mk]['_boards'] = [];
            $merged[$mk]['_sort'] = (int)$slot['sort_order'];
        }
        $board=(string)$slot['board_key'];
        if ($board==='') $board='all';
        $merged[$mk]['_boards'][$board] = true;
        if (!empty($item['category_slug'])) $merged[$mk]['_boards'][(string)$item['category_slug']] = true;
    }
    uasort($merged, static fn($a,$b): int => ($a['_sort'] <=> $b['_sort']) ?: strcmp($a['title'],$b['title']));
    $out=[];
    foreach ($merged as $item) {
        $boards = array_keys($item['_boards'] ?? ['all'=>true]);
        $out[] = [
            'active'=>1,
            'featured'=>1,
            'source_type'=>'v711_'.$item['type'],
            'url'=>$item['url'],
            'image'=>artdon_v711_public_path((string)$item['image']),
            'title'=>(string)$item['title'],
            'type'=>(string)($item['category_name'] ?: $item['type_label']),
            'text'=>(string)$item['text'],
            'categories'=>implode(' ', array_unique(array_filter(array_merge(['all'], $boards)))),
        ];
    }
    return ['dynamic'=>!empty($out),'items'=>$out,'tabs'=>artdon_v711_home_tabs($pdo)];
}
