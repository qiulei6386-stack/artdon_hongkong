<?php

declare(strict_types=1);

/**
 * Product catalogue helpers for the Hong Kong public website.
 * Product data is stored locally in artdon_web. Future Guangzhou publishing
 * can populate source_system/source_id without changing the public pages.
 */

if (!function_exists('artdon_v718129_category_slug')) {
    $unifyFileV718129 = __DIR__ . '/artdon_product_unify_v713.php';
    if (is_file($unifyFileV718129)) {
        try { require_once $unifyFileV718129; } catch (Throwable $e) {}
    }
}

function web_product_json(array $value): string
{
    return json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function web_product_decode(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map(static fn($v) => trim((string)$v), $value), static fn($v) => $v !== ''));
    }
    $decoded = json_decode((string)$value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map(static fn($v) => trim((string)$v), $decoded), static fn($v) => $v !== ''));
    }
    return [];
}

function web_product_lines(mixed $value): array
{
    if (is_array($value)) {
        $parts = $value;
    } else {
        $parts = preg_split('/[\r\n,;|]+/u', (string)$value) ?: [];
    }
    $result = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== '' && !in_array($part, $result, true)) {
            $result[] = $part;
        }
    }
    return $result;
}

function web_product_slug(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'product-' . bin2hex(random_bytes(4));
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'product-' . bin2hex(random_bytes(4));
}

function web_product_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_product_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (web_product_column_exists($pdo, $table, $column)) {
        return;
    }

    $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
    try {
        $pdo->exec($sql);
        return;
    } catch (Throwable $e) {
        $message = $e->getMessage();
        // V7.1.8.118: some old web_products tables already contain many VARCHAR fields.
        // Adding another VARCHAR can hit MySQL row-size 1118. Retry as TEXT, which is stored off-row.
        if (strpos($message, '1118') === false && stripos($message, 'Row size too large') === false) {
            throw $e;
        }
        if (stripos($definition, 'VARCHAR') === false) {
            throw $e;
        }
        $safeDefinition = preg_replace("/VARCHAR\\s*\\(\\s*\\d+\\s*\\)\\s+NOT\\s+NULL\\s+DEFAULT\\s+''/i", 'TEXT NULL', $definition) ?: $definition;
        $safeDefinition = preg_replace("/VARCHAR\\s*\\(\\s*\\d+\\s*\\)\\s+DEFAULT\\s+''/i", 'TEXT NULL', $safeDefinition) ?: $safeDefinition;
        $safeDefinition = preg_replace('/VARCHAR\s*\(\s*\d+\s*\)/i', 'TEXT', $safeDefinition) ?: $safeDefinition;
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$safeDefinition}");
    }
}

/**
 * Normalised product-card content used by catalogue and related-product grids.
 * The dedicated card fields are optional; older products automatically fall
 * back to the existing description, size and lumen fields.
 */
function web_product_card_data(array $product): array
{
    $subtitle = trim((string)($product['card_subtitle'] ?? ''));
    if ($subtitle === '') {
        $subtitle = trim((string)($product['short_description'] ?? ''));
    }

    $bestForValue = trim((string)($product['card_best_for'] ?? ($product['card_best_for_value'] ?? '')));

    $sizeLabel = trim((string)($product['card_size_label'] ?? ''));
    $sizeValue = trim((string)($product['card_size_value'] ?? ''));
    if ($sizeValue === '') {
        $sizeValue = trim((string)($product['size_text'] ?? ''));
    }
    if ($sizeLabel === '' && $sizeValue !== '') {
        $sizeParts = array_values(array_filter(preg_split('/\s*\/\s*/u', $sizeValue) ?: [], static fn($v) => trim((string)$v) !== ''));
        $sizeLabel = count($sizeParts) > 1 ? count($sizeParts) . ' Sizes' : 'Size';
    }

    $outputLabel = trim((string)($product['card_output_label'] ?? ''));
    $outputValue = trim((string)($product['card_output_value'] ?? ''));
    if ($outputValue === '') {
        $outputValue = trim((string)($product['lumen_text'] ?? ''));
    }
    if ($outputLabel === '' && $outputValue !== '') {
        $outputLabel = 'Lumen Output';
    }

    $powerLabel = trim((string)($product['card_power_label'] ?? ''));
    $powerValue = trim((string)($product['card_power_value'] ?? ''));
    if ($powerValue === '') {
        $powerValue = trim((string)($product['power_text'] ?? ''));
    }
    if ($powerLabel === '' && $powerValue !== '') {
        $powerLabel = 'Wattage';
    }

    $beamLabel = trim((string)($product['card_beam_label'] ?? ''));
    $beamValue = trim((string)($product['card_beam_value'] ?? ''));
    if ($beamValue === '') {
        $beamRaw = $product['beam_angle'] ?? ($product['beam_angle_json'] ?? []);
        $beamValues = is_array($beamRaw) ? $beamRaw : web_product_decode($beamRaw);
        $beamValue = implode(' / ', array_values(array_filter(array_map('strval', $beamValues))));
    }
    if ($beamLabel === '' && $beamValue !== '') {
        $beamLabel = 'Beam Angle';
    }

    return [
        'subtitle' => $subtitle,
        'best_for_label' => 'Best For',
        'best_for_value' => $bestForValue,
        'power_label' => $powerLabel,
        'power_value' => $powerValue,
        'size_label' => $sizeLabel,
        'size_value' => $sizeValue,
        'output_label' => $outputLabel,
        'output_value' => $outputValue,
        'beam_label' => $beamLabel,
        'beam_value' => $beamValue,
        'image_scale' => max(60, min(180, (int)($product['card_image_scale'] ?? 100))),
        'tags' => array_slice(web_product_decode($product['tags'] ?? ($product['tags_json'] ?? '[]')), 0, 5),
    ];
}

function web_products_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_product_categories (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        slug VARCHAR(160) NOT NULL UNIQUE,
        page_title VARCHAR(255) NOT NULL DEFAULT '',
        subtitle VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NULL,
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_web_product_categories_sort (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_products (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        source_system VARCHAR(100) NOT NULL DEFAULT 'website',
        source_id VARCHAR(190) NOT NULL DEFAULT '',
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(190) NOT NULL UNIQUE,
        series_name VARCHAR(190) NOT NULL DEFAULT '',
        model_code VARCHAR(190) NOT NULL DEFAULT '',
        category_slug VARCHAR(160) NOT NULL DEFAULT 'downlights',
        sub_category VARCHAR(190) NOT NULL DEFAULT '',
        short_description VARCHAR(700) NOT NULL DEFAULT '',
        card_subtitle TEXT NULL,
        card_best_for TEXT NULL,
        card_size_label VARCHAR(80) NOT NULL DEFAULT '',
        card_size_value VARCHAR(190) NOT NULL DEFAULT '',
        card_output_label VARCHAR(80) NOT NULL DEFAULT '',
        card_output_value VARCHAR(190) NOT NULL DEFAULT '',
        card_power_label VARCHAR(80) NOT NULL DEFAULT '',
        card_power_value VARCHAR(190) NOT NULL DEFAULT '',
        card_beam_label VARCHAR(80) NOT NULL DEFAULT '',
        card_beam_value VARCHAR(190) NOT NULL DEFAULT '',
        card_image_scale INT NOT NULL DEFAULT 100,
        full_description LONGTEXT NULL,
        cover_image VARCHAR(500) NOT NULL DEFAULT '',
        gallery_json LONGTEXT NULL,
        applications_json LONGTEXT NULL,
        mounting_json LONGTEXT NULL,
        shape_json LONGTEXT NULL,
        voltage_json LONGTEXT NULL,
        power_text VARCHAR(190) NOT NULL DEFAULT '',
        lumen_text VARCHAR(190) NOT NULL DEFAULT '',
        cct_json LONGTEXT NULL,
        cri_json LONGTEXT NULL,
        beam_angle_json LONGTEXT NULL,
        ip_rating VARCHAR(80) NOT NULL DEFAULT '',
        cutout_text VARCHAR(190) NOT NULL DEFAULT '',
        size_text VARCHAR(255) NOT NULL DEFAULT '',
        finish_json LONGTEXT NULL,
        light_source VARCHAR(255) NOT NULL DEFAULT '',
        dimming_json LONGTEXT NULL,
        tags_json LONGTEXT NULL,
        datasheet_path VARCHAR(500) NOT NULL DEFAULT '',
        installation_path VARCHAR(500) NOT NULL DEFAULT '',
        photometric_path VARCHAR(500) NOT NULL DEFAULT '',
        cad_path VARCHAR(500) NOT NULL DEFAULT '',
        bim_path VARCHAR(500) NOT NULL DEFAULT '',
        video_url VARCHAR(500) NOT NULL DEFAULT '',
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        is_new TINYINT(1) NOT NULL DEFAULT 0,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description VARCHAR(500) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_web_products_publish_sort (is_published, sort_order, id),
        INDEX idx_web_products_category (category_slug, is_published),
        INDEX idx_web_products_model (model_code),
        INDEX idx_web_products_source (source_system, source_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // V5.1 unified product-card fields. Existing products remain compatible
    // because the public card falls back to short_description/size/lumen.
    web_product_add_column($pdo, 'web_products', 'card_image_scale', 'INT NOT NULL DEFAULT 100 AFTER card_beam_value');
    web_product_add_column($pdo, 'web_products', 'card_subtitle', "VARCHAR(500) NOT NULL DEFAULT '' AFTER short_description");
    // V7.1.8.117: optional series-card Best For row. Label is fixed on the frontend; admin only fills the value.
    web_product_add_column($pdo, 'web_products', 'card_best_for', "TEXT NULL AFTER card_subtitle");
    web_product_add_column($pdo, 'web_products', 'card_size_label', "VARCHAR(80) NOT NULL DEFAULT '' AFTER card_best_for");
    web_product_add_column($pdo, 'web_products', 'card_size_value', "VARCHAR(190) NOT NULL DEFAULT '' AFTER card_size_label");
    web_product_add_column($pdo, 'web_products', 'card_output_label', "VARCHAR(80) NOT NULL DEFAULT '' AFTER card_size_value");
    web_product_add_column($pdo, 'web_products', 'card_output_value', "VARCHAR(190) NOT NULL DEFAULT '' AFTER card_output_label");
    web_product_add_column($pdo, 'web_products', 'card_power_label', "VARCHAR(80) NOT NULL DEFAULT '' AFTER card_output_value");
    web_product_add_column($pdo, 'web_products', 'card_power_value', "VARCHAR(190) NOT NULL DEFAULT '' AFTER card_power_label");
    web_product_add_column($pdo, 'web_products', 'card_beam_label', "VARCHAR(80) NOT NULL DEFAULT '' AFTER card_power_value");
    web_product_add_column($pdo, 'web_products', 'card_beam_value', "VARCHAR(190) NOT NULL DEFAULT '' AFTER card_beam_label");

    web_products_seed_categories($pdo);
    web_products_seed_demo($pdo);
}

function web_products_seed_categories(PDO $pdo): void
{
    $categories = [
        ['All Products', 'all', 'Architectural lighting products', 'Commercial LED luminaires', 'Browse Artdon downlights, track lights, magnetic systems, linear lighting and outdoor luminaires for retail, hospitality, museum, office and custom projects.', 0],
        ['Downlights', 'downlights', 'Architectural downlights', 'Recessed and surface-mounted luminaires', 'Compact, low-glare and adjustable downlights for commercial ceilings, display areas and general illumination.', 10],
        ['Track Lights', 'track-lights', 'Track lighting systems', 'Flexible accent lighting for commercial spaces', 'Track spotlights, wallwashers and linear modules for retail, museum, gallery and hospitality applications.', 20],
        ['Magnetic Systems', 'magnetic-systems', '48V magnetic lighting systems', 'One track, multiple lighting tools', 'Modular 48V magnetic track systems with spot, linear, grille and pendant luminaires.', 30],
        ['Linear Lighting', 'linear-lighting', 'Architectural linear lighting', 'Continuous, precise and integrated light', 'Linear luminaires for shelves, coves, circulation, workspaces and architectural details.', 40],
        ['Outdoor Lighting', 'outdoor-lighting', 'Outdoor architectural lighting', 'Exterior projectors and linear luminaires', 'IP-rated projectors, wall lights and linear systems for façades, landscape and exterior circulation.', 50],
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_product_categories (name, slug, page_title, subtitle, description, seo_title, seo_description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
    foreach ($categories as $cat) {
        $stmt->execute([$cat[0], $cat[1], $cat[2], $cat[3], $cat[4], $cat[2] . ' | Artdon Lighting', $cat[4], $cat[5]]);
    }
}

function web_products_seed_demo(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_products')->fetchColumn();
    if ($count > 0) {
        return;
    }
    foreach (web_products_demo_data() as $product) {
        $product['is_published'] = 1;
        web_product_save($pdo, $product, 0);
    }
}

function web_products_demo_data(): array
{
    return [
        [
            'name'=>'SPECTRUM Track Spotlight','slug'=>'spectrum-track-spotlight','series_name'=>'SPECTRUM','model_code'=>'SP-TR','category_slug'=>'track-lights','sub_category'=>'Track spotlight',
            'short_description'=>'Adjustable accent spotlight for retail, museum and gallery lighting.','full_description'=>'A compact architectural track spotlight designed for precise accent lighting, low visual impact and flexible aiming.',
            'cover_image'=>'assets/img/products/track-spot-module.webp','gallery'=>[],'applications'=>['Retail','Museum','Hospitality'],'mounting'=>['Track mounted'],'shape'=>['Round'],'voltage'=>['220-240V'],
            'power_text'=>'12W / 18W / 25W','lumen_text'=>'1100–2600 lm','cct'=>['2700K','3000K','4000K'],'cri'=>['CRI90','CRI95'],'beam_angle'=>['10°','15°','24°','36°'],'ip_rating'=>'IP20','cutout_text'=>'—','size_text'=>'Ø60 × 145 mm','finish'=>['Black','White'],'light_source'=>'High-CRI COB LED','dimming'=>['ON/OFF','TRIAC','DALI'],'tags'=>['Track','Accent','Low glare'],'is_featured'=>1,'is_new'=>1,'sort_order'=>10,
        ],
        [
            'name'=>'LINEA Track Linear','slug'=>'linea-track-linear','series_name'=>'LINEA','model_code'=>'LN-TR','category_slug'=>'track-lights','sub_category'=>'Linear track luminaire',
            'short_description'=>'Continuous linear output for shelves, circulation and general lighting.','full_description'=>'A slim track-mounted linear module that combines architectural integration with broad and comfortable light distribution.',
            'cover_image'=>'assets/img/products/track-linear-module.webp','gallery'=>[],'applications'=>['Retail','Office','Hospitality'],'mounting'=>['Track mounted'],'shape'=>['Linear'],'voltage'=>['220-240V'],
            'power_text'=>'18W / 30W','lumen_text'=>'1600–3200 lm','cct'=>['3000K','4000K'],'cri'=>['CRI90'],'beam_angle'=>['Wide'],'ip_rating'=>'IP20','cutout_text'=>'—','size_text'=>'600 / 1200 mm','finish'=>['Black','White'],'light_source'=>'Linear LED module','dimming'=>['ON/OFF','DALI'],'tags'=>['Track','Linear','General light'],'is_featured'=>1,'is_new'=>0,'sort_order'=>20,
        ],
        [
            'name'=>'GRID Track Grille','slug'=>'grid-track-grille','series_name'=>'GRID','model_code'=>'GR-TR','category_slug'=>'track-lights','sub_category'=>'Low-glare grille',
            'short_description'=>'Multi-cell dark-light optics for comfortable commercial lighting.','full_description'=>'A track-mounted grille luminaire with controlled cut-off, precise beam distribution and reduced direct glare.',
            'cover_image'=>'assets/img/products/track-grille-module.webp','gallery'=>[],'applications'=>['Retail','Office','Hospitality'],'mounting'=>['Track mounted'],'shape'=>['Rectangular'],'voltage'=>['220-240V'],
            'power_text'=>'12W / 24W','lumen_text'=>'900–2100 lm','cct'=>['3000K','4000K'],'cri'=>['CRI90'],'beam_angle'=>['24°','36°'],'ip_rating'=>'IP20','cutout_text'=>'—','size_text'=>'250 / 500 mm','finish'=>['Black'],'light_source'=>'Multi-cell LED','dimming'=>['ON/OFF','DALI'],'tags'=>['Track','Grille','Visual comfort'],'is_featured'=>0,'is_new'=>1,'sort_order'=>30,
        ],
        [
            'name'=>'MAGNETIC 48V Spot','slug'=>'magnetic-48v-spot','series_name'=>'MAGNETIC','model_code'=>'MG-SP','category_slug'=>'magnetic-systems','sub_category'=>'Magnetic spotlight',
            'short_description'=>'Compact adjustable spotlight for 48V magnetic track systems.','full_description'=>'A tool-free magnetic spotlight for fast repositioning, precise aiming and flexible project layouts.',
            'cover_image'=>'assets/img/products/track-spot-module.webp','gallery'=>[],'applications'=>['Retail','Residential','Hospitality'],'mounting'=>['Magnetic track'],'shape'=>['Round'],'voltage'=>['48V'],
            'power_text'=>'7W / 12W','lumen_text'=>'600–1200 lm','cct'=>['2700K','3000K','4000K'],'cri'=>['CRI90','CRI95'],'beam_angle'=>['15°','24°','36°'],'ip_rating'=>'IP20','cutout_text'=>'—','size_text'=>'Ø45 × 110 mm','finish'=>['Black','White'],'light_source'=>'High-CRI COB LED','dimming'=>['ON/OFF','DALI','Bluetooth'],'tags'=>['48V','Magnetic','Spotlight'],'is_featured'=>1,'is_new'=>1,'sort_order'=>40,
        ],
        [
            'name'=>'MAGNETIC 48V Linear','slug'=>'magnetic-48v-linear','series_name'=>'MAGNETIC','model_code'=>'MG-LN','category_slug'=>'magnetic-systems','sub_category'=>'Magnetic linear module',
            'short_description'=>'Slim linear light for circulation, shelving and ambient illumination.','full_description'=>'A low-voltage magnetic linear module that combines clean integration with uniform general lighting.',
            'cover_image'=>'assets/img/products/track-linear-module.webp','gallery'=>[],'applications'=>['Retail','Office','Residential'],'mounting'=>['Magnetic track'],'shape'=>['Linear'],'voltage'=>['48V'],
            'power_text'=>'10W / 20W','lumen_text'=>'800–1900 lm','cct'=>['2700K','3000K','4000K'],'cri'=>['CRI90'],'beam_angle'=>['Wide'],'ip_rating'=>'IP20','cutout_text'=>'—','size_text'=>'300 / 600 mm','finish'=>['Black','White'],'light_source'=>'Linear LED module','dimming'=>['ON/OFF','DALI','Bluetooth'],'tags'=>['48V','Magnetic','Linear'],'is_featured'=>1,'is_new'=>0,'sort_order'=>50,
        ],
        [
            'name'=>'MAGNETIC 48V Grille','slug'=>'magnetic-48v-grille','series_name'=>'MAGNETIC','model_code'=>'MG-GR','category_slug'=>'magnetic-systems','sub_category'=>'Magnetic grille module',
            'short_description'=>'Low-glare multi-cell module for visual comfort and controlled beams.','full_description'=>'A compact magnetic grille module with dark-light optics for workplaces, hospitality and residential interiors.',
            'cover_image'=>'assets/img/products/track-grille-module.webp','gallery'=>[],'applications'=>['Office','Hospitality','Residential'],'mounting'=>['Magnetic track'],'shape'=>['Rectangular'],'voltage'=>['48V'],
            'power_text'=>'6W / 12W','lumen_text'=>'450–980 lm','cct'=>['2700K','3000K','4000K'],'cri'=>['CRI90'],'beam_angle'=>['24°','36°'],'ip_rating'=>'IP20','cutout_text'=>'—','size_text'=>'120 / 240 mm','finish'=>['Black'],'light_source'=>'Multi-cell LED','dimming'=>['ON/OFF','DALI','Bluetooth'],'tags'=>['48V','Magnetic','Low glare'],'is_featured'=>0,'is_new'=>1,'sort_order'=>60,
        ],
        [
            'name'=>'MICRO Recessed Downlight','slug'=>'micro-recessed-downlight','series_name'=>'MICRO','model_code'=>'MC-DL','category_slug'=>'downlights','sub_category'=>'Miniature downlight',
            'short_description'=>'Minimal recessed aperture with controlled light and high visual comfort.','full_description'=>'A miniature recessed downlight for refined commercial ceilings, display lighting and compact architectural details.',
            'cover_image'=>'assets/img/product-micro.svg','gallery'=>[],'applications'=>['Retail','Hospitality','Residential'],'mounting'=>['Recessed'],'shape'=>['Round','Square'],'voltage'=>['220-240V'],'power_text'=>'5W / 8W','lumen_text'=>'400–750 lm',
            'cct'=>['2700K','3000K','4000K'],'cri'=>['CRI90','CRI95'],'beam_angle'=>['15°','24°','36°'],'ip_rating'=>'IP20 / IP44','cutout_text'=>'Ø45 / Ø55 mm','size_text'=>'Ø55 / Ø68 mm','finish'=>['White','Black'],'light_source'=>'High-CRI COB LED','dimming'=>['ON/OFF','TRIAC','DALI'],'tags'=>['Recessed','Miniature','Low glare'],'is_featured'=>1,'is_new'=>0,'sort_order'=>70,
        ],
        [
            'name'=>'AXIS Adjustable Downlight','slug'=>'axis-adjustable-downlight','series_name'=>'AXIS','model_code'=>'AX-DL','category_slug'=>'downlights','sub_category'=>'Adjustable downlight',
            'short_description'=>'Deep-recessed adjustable spotlight for precise ceiling-mounted accent light.','full_description'=>'An adjustable recessed luminaire with concealed aiming mechanism, deep optical position and professional beam control.',
            'cover_image'=>'assets/img/product-axis.svg','gallery'=>[],'applications'=>['Retail','Museum','Hospitality'],'mounting'=>['Recessed'],'shape'=>['Round','Square'],'voltage'=>['220-240V'],'power_text'=>'10W / 15W / 20W','lumen_text'=>'900–2100 lm',
            'cct'=>['2700K','3000K','4000K'],'cri'=>['CRI90','CRI95'],'beam_angle'=>['10°','15°','24°','36°'],'ip_rating'=>'IP20','cutout_text'=>'Ø75 / Ø95 mm','size_text'=>'Ø88 / Ø108 mm','finish'=>['White','Black'],'light_source'=>'High-CRI COB LED','dimming'=>['ON/OFF','TRIAC','DALI'],'tags'=>['Recessed','Adjustable','Accent'],'is_featured'=>1,'is_new'=>1,'sort_order'=>80,
        ],
        [
            'name'=>'LINEA Recessed Wallwasher','slug'=>'linea-recessed-wallwasher','series_name'=>'LINEA','model_code'=>'LN-WW','category_slug'=>'downlights','sub_category'=>'Recessed wallwasher',
            'short_description'=>'Uniform vertical illumination for retail walls, artwork and circulation.','full_description'=>'A recessed linear wallwasher engineered for smooth vertical brightness and controlled visual comfort.',
            'cover_image'=>'assets/img/product-linea.svg','gallery'=>[],'applications'=>['Retail','Museum','Hospitality'],'mounting'=>['Recessed'],'shape'=>['Linear'],'voltage'=>['220-240V'],'power_text'=>'18W / 30W','lumen_text'=>'1500–3000 lm',
            'cct'=>['3000K','4000K'],'cri'=>['CRI90'],'beam_angle'=>['Wallwasher'],'ip_rating'=>'IP20','cutout_text'=>'600 × 45 mm','size_text'=>'620 × 60 mm','finish'=>['White','Black'],'light_source'=>'Linear LED module','dimming'=>['ON/OFF','DALI'],'tags'=>['Recessed','Wallwasher','Linear'],'is_featured'=>0,'is_new'=>0,'sort_order'=>90,
        ],
        [
            'name'=>'OUTDOOR Compact Projector','slug'=>'outdoor-compact-projector','series_name'=>'OUTDOOR','model_code'=>'OD-PJ','category_slug'=>'outdoor-lighting','sub_category'=>'Exterior projector',
            'short_description'=>'Compact IP-rated projector for façade, landscape and architectural accents.','full_description'=>'A robust adjustable outdoor projector with sealed construction, precise optics and a stable mounting bracket.',
            'cover_image'=>'assets/img/products/outdoor-projector-full.webp','gallery'=>[],'applications'=>['Outdoor','Landscape','Facade'],'mounting'=>['Surface','Bracket'],'shape'=>['Round'],'voltage'=>['220-240V'],'power_text'=>'12W / 20W / 30W','lumen_text'=>'1000–3200 lm',
            'cct'=>['2700K','3000K','4000K'],'cri'=>['CRI80','CRI90'],'beam_angle'=>['10°','15°','24°','36°'],'ip_rating'=>'IP65','cutout_text'=>'—','size_text'=>'Ø95 × 180 mm','finish'=>['Black','Dark grey'],'light_source'=>'Outdoor COB LED','dimming'=>['ON/OFF','DALI'],'tags'=>['Outdoor','Projector','IP65'],'is_featured'=>1,'is_new'=>1,'sort_order'=>100,
        ],
        [
            'name'=>'OUTDOOR Linear Grazer','slug'=>'outdoor-linear-grazer','series_name'=>'OUTDOOR','model_code'=>'OD-LG','category_slug'=>'outdoor-lighting','sub_category'=>'Facade linear luminaire',
            'short_description'=>'Linear grazing light for texture, façade details and landscape elements.','full_description'=>'A compact exterior linear luminaire with narrow optical distributions for architectural grazing and façade illumination.',
            'cover_image'=>'assets/img/product-outdoor.svg','gallery'=>[],'applications'=>['Outdoor','Facade','Landscape'],'mounting'=>['Surface'],'shape'=>['Linear'],'voltage'=>['24V','220-240V'],'power_text'=>'18W / 36W','lumen_text'=>'1500–3600 lm',
            'cct'=>['2700K','3000K','4000K'],'cri'=>['CRI80','CRI90'],'beam_angle'=>['10°','15°','Wallwasher'],'ip_rating'=>'IP66','cutout_text'=>'—','size_text'=>'500 / 1000 mm','finish'=>['Dark grey','Black'],'light_source'=>'Linear outdoor LED','dimming'=>['ON/OFF','DALI','DMX'],'tags'=>['Outdoor','Linear','Facade'],'is_featured'=>1,'is_new'=>0,'sort_order'=>110,
        ],
        [
            'name'=>'OUTDOOR Precision Lens','slug'=>'outdoor-precision-lens','series_name'=>'OUTDOOR','model_code'=>'OD-LS','category_slug'=>'outdoor-lighting','sub_category'=>'Precision projector',
            'short_description'=>'High-precision optical projector for long-distance exterior accents.','full_description'=>'A durable exterior projector with faceted reflector and lens options for narrow, controlled beams.',
            'cover_image'=>'assets/img/products/outdoor-lens-detail.webp','gallery'=>[],'applications'=>['Outdoor','Facade','Landscape'],'mounting'=>['Surface','Bracket'],'shape'=>['Round'],'voltage'=>['220-240V'],'power_text'=>'20W / 35W','lumen_text'=>'1800–3800 lm',
            'cct'=>['3000K','4000K'],'cri'=>['CRI80','CRI90'],'beam_angle'=>['3°','7.8°','10°'],'ip_rating'=>'IP65','cutout_text'=>'—','size_text'=>'Ø120 × 220 mm','finish'=>['Black','Dark grey'],'light_source'=>'High-intensity COB LED','dimming'=>['ON/OFF','DALI'],'tags'=>['Outdoor','Narrow beam','Projector'],'is_featured'=>0,'is_new'=>1,'sort_order'=>120,
        ],
    ];
}

function web_product_hydrate(array $row): array
{
    foreach (['gallery_json','applications_json','mounting_json','shape_json','voltage_json','cct_json','cri_json','beam_angle_json','finish_json','dimming_json','tags_json'] as $field) {
        $row[str_replace('_json', '', $field)] = web_product_decode($row[$field] ?? '[]');
    }
    $gallery = json_decode((string)($row['gallery_json'] ?? '[]'), true);
    $row['gallery'] = is_array($gallery) ? $gallery : [];
    return $row;
}

function web_product_categories(PDO $pdo, bool $activeOnly = true): array
{
    if (function_exists('artdon_v718129_front_categories')) {
        try { return artdon_v718129_front_categories($pdo, true); } catch (Throwable $e) {}
    }
    $sql = 'SELECT * FROM web_product_categories';
    if ($activeOnly) $sql .= ' WHERE is_active=1';
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return $pdo->query($sql)->fetchAll() ?: [];
}

function web_product_category(PDO $pdo, string $slug): ?array
{
    $slug = web_product_legacy_category($slug);
    if (function_exists('artdon_v718129_category_by_slug')) {
        try { $row = artdon_v718129_category_by_slug($slug, $pdo); if ($row) return $row; } catch (Throwable $e) {}
    }
    $stmt = $pdo->prepare('SELECT * FROM web_product_categories WHERE slug=? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function web_product_legacy_category(string $value): string
{
    if (function_exists('artdon_v718129_category_slug')) {
        return artdon_v718129_category_slug($value, 'downlights');
    }
    $value = strtolower(trim($value));
    $map = [
        ''=>'all','all'=>'all','all-products'=>'all',
        'downlight'=>'downlights','downlights'=>'downlights','recessed'=>'downlights','recessed-downlight'=>'downlights','recessed downlight'=>'downlights',
        'track'=>'track-lights','track-light'=>'track-lights','track-lights'=>'track-lights','track spotlights'=>'track-lights',
        'magnetic'=>'magnetic-systems','magnetic-system'=>'magnetic-systems','magnetic systems'=>'magnetic-systems',
        'surface-pendant-lights'=>'surface-pendant-lights','surface and pendant lights'=>'surface-pendant-lights',
        'linear'=>'linear-lighting','linear-lighting'=>'linear-lighting','linear lighting'=>'linear-lighting',
        'outdoor'=>'outdoor-lighting','outdoor-lighting'=>'outdoor-lighting','outdoor lighting'=>'outdoor-lighting',
        'led-strips-profiles'=>'led-strips-profiles','led strips & profiles'=>'led-strips-profiles','led strips and profiles'=>'led-strips-profiles',
        'track-systems-accessories'=>'track-systems-accessories','track systems & accessories'=>'track-systems-accessories','track systems and accessories'=>'track-systems-accessories',
    ];
    return $map[$value] ?? web_product_slug($value);
}

function web_product_fetch_all(PDO $pdo, bool $publishedOnly = true): array
{
    $sql = 'SELECT * FROM web_products';
    if ($publishedOnly) {
        $sql .= ' WHERE is_published=1';
    }
    $sql .= ' ORDER BY sort_order ASC, id DESC';
    $rows = $pdo->query($sql)->fetchAll() ?: [];
    $items = array_map('web_product_hydrate', $rows);
    if (function_exists('artdon_v718129_normalize_series_list')) $items = artdon_v718129_normalize_series_list($items);
    return $items;
}

function web_product_filter_options(array $products): array
{
    $groups = [
        'applications'=>['label'=>'Application','values'=>[]],
        'mounting'=>['label'=>'Mounting','values'=>[]],
        'shape'=>['label'=>'Shape','values'=>[]],
        'voltage'=>['label'=>'Voltage','values'=>[]],
        'cct'=>['label'=>'CCT','values'=>[]],
        'cri'=>['label'=>'CRI','values'=>[]],
        'beam_angle'=>['label'=>'Beam angle','values'=>[]],
        'ip_rating'=>['label'=>'IP rating','values'=>[]],
    ];
    foreach ($products as $product) {
        foreach (['applications','mounting','shape','voltage','cct','cri','beam_angle'] as $key) {
            foreach (($product[$key] ?? []) as $value) {
                if ($value !== '' && !in_array($value, $groups[$key]['values'], true)) {
                    $groups[$key]['values'][] = $value;
                }
            }
        }
        $ip = trim((string)($product['ip_rating'] ?? ''));
        if ($ip !== '' && !in_array($ip, $groups['ip_rating']['values'], true)) {
            $groups['ip_rating']['values'][] = $ip;
        }
    }
    foreach ($groups as &$group) {
        natcasesort($group['values']);
        $group['values'] = array_values($group['values']);
    }
    unset($group);
    return $groups;
}

function web_product_matches(array $product, array $filters): bool
{
    $category = (string)($filters['category'] ?? 'all');
    if ($category !== '' && $category !== 'all' && ($product['category_slug'] ?? '') !== $category) {
        return false;
    }
    $lower = static fn(string $v): string => function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
    $q = $lower(trim((string)($filters['q'] ?? '')));
    if ($q !== '') {
        $haystack = $lower(implode(' ', [
            $product['name'] ?? '', $product['series_name'] ?? '', $product['model_code'] ?? '', $product['sub_category'] ?? '',
            $product['short_description'] ?? '', implode(' ', $product['tags'] ?? []),
        ]));
        if (!str_contains($haystack, $q)) {
            return false;
        }
    }
    $arrayKeys = ['applications','mounting','shape','voltage','cct','cri','beam_angle'];
    foreach ($arrayKeys as $key) {
        $selected = web_product_lines($filters[$key] ?? []);
        if (!$selected) {
            continue;
        }
        $values = $product[$key] ?? [];
        if (!array_intersect($selected, $values)) {
            return false;
        }
    }
    $selectedIp = web_product_lines($filters['ip_rating'] ?? []);
    if ($selectedIp && !in_array((string)($product['ip_rating'] ?? ''), $selectedIp, true)) {
        return false;
    }
    return true;
}

function web_product_sort(array &$products, string $sort): void
{
    usort($products, static function(array $a, array $b) use ($sort): int {
        return match ($sort) {
            'name-asc' => strcasecmp((string)$a['name'], (string)$b['name']),
            'name-desc' => strcasecmp((string)$b['name'], (string)$a['name']),
            'newest' => strcmp((string)$b['created_at'], (string)$a['created_at']),
            'featured' => ((int)$b['is_featured'] <=> (int)$a['is_featured']) ?: ((int)$a['sort_order'] <=> (int)$b['sort_order']),
            default => ((int)$a['sort_order'] <=> (int)$b['sort_order']) ?: ((int)$b['id'] <=> (int)$a['id']),
        };
    });
}

function web_product_find(PDO $pdo, string|int $identifier, bool $publishedOnly = true): ?array
{
    if (is_int($identifier) || ctype_digit((string)$identifier)) {
        $sql = 'SELECT * FROM web_products WHERE id=?';
        $params = [(int)$identifier];
    } else {
        $sql = 'SELECT * FROM web_products WHERE slug=?';
        $params = [(string)$identifier];
    }
    if ($publishedOnly) {
        $sql .= ' AND is_published=1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? web_product_hydrate($row) : null;
}

function web_product_related(PDO $pdo, array $product, int $limit = 4): array
{
    $stmt = $pdo->prepare('SELECT * FROM web_products WHERE is_published=1 AND category_slug=? AND id<>? ORDER BY is_featured DESC, sort_order ASC, id DESC LIMIT ' . max(1, min(12, $limit)));
    $stmt->execute([(string)$product['category_slug'], (int)$product['id']]);
    return array_map('web_product_hydrate', $stmt->fetchAll() ?: []);
}

function web_product_unique_slug(PDO $pdo, string $slug, int $excludeId = 0): string
{
    $base = web_product_slug($slug);
    $candidate = $base;
    $n = 2;
    while (true) {
        $sql = 'SELECT id FROM web_products WHERE slug=?';
        $params = [$candidate];
        if ($excludeId > 0) {
            $sql .= ' AND id<>?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = $base . '-' . $n++;
    }
}

function web_product_save(PDO $pdo, array $data, int $id = 0): int
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('产品名称不能为空。');
    }
    $slug = web_product_unique_slug($pdo, trim((string)($data['slug'] ?? '')) ?: $name, $id);
    $gallery = [];
    foreach (($data['gallery'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $image = trim((string)($item['image'] ?? ''));
        if ($image !== '') {
            $gallery[] = ['image'=>$image, 'alt'=>trim((string)($item['alt'] ?? ''))];
        }
    }
    $values = [
        'source_system'=>trim((string)($data['source_system'] ?? 'website')) ?: 'website',
        'source_id'=>trim((string)($data['source_id'] ?? '')),
        'name'=>$name,
        'slug'=>$slug,
        'series_name'=>trim((string)($data['series_name'] ?? '')),
        'model_code'=>trim((string)($data['model_code'] ?? '')),
        'category_slug'=>web_product_legacy_category((string)($data['category_slug'] ?? 'downlights')),
        'sub_category'=>trim((string)($data['sub_category'] ?? '')),
        'short_description'=>trim((string)($data['short_description'] ?? '')),
        'card_subtitle'=>trim((string)($data['card_subtitle'] ?? '')),
        'card_best_for'=>trim((string)($data['card_best_for'] ?? '')),
        'card_size_label'=>trim((string)($data['card_size_label'] ?? '')),
        'card_size_value'=>trim((string)($data['card_size_value'] ?? '')),
        'card_output_label'=>trim((string)($data['card_output_label'] ?? '')),
        'card_output_value'=>trim((string)($data['card_output_value'] ?? '')),
        'card_power_label'=>trim((string)($data['card_power_label'] ?? '')),
        'card_power_value'=>trim((string)($data['card_power_value'] ?? '')),
        'card_beam_label'=>trim((string)($data['card_beam_label'] ?? '')),
        'card_beam_value'=>trim((string)($data['card_beam_value'] ?? '')),
        'card_image_scale'=>max(60, min(180, (int)($data['card_image_scale'] ?? 100))),
        'full_description'=>trim((string)($data['full_description'] ?? '')),
        'cover_image'=>trim((string)($data['cover_image'] ?? '')),
        'gallery_json'=>web_product_json($gallery),
        'applications_json'=>web_product_json(web_product_lines($data['applications'] ?? [])),
        'mounting_json'=>web_product_json(web_product_lines($data['mounting'] ?? [])),
        'shape_json'=>web_product_json(web_product_lines($data['shape'] ?? [])),
        'voltage_json'=>web_product_json(web_product_lines($data['voltage'] ?? [])),
        'power_text'=>trim((string)($data['power_text'] ?? '')),
        'lumen_text'=>trim((string)($data['lumen_text'] ?? '')),
        'cct_json'=>web_product_json(web_product_lines($data['cct'] ?? [])),
        'cri_json'=>web_product_json(web_product_lines($data['cri'] ?? [])),
        'beam_angle_json'=>web_product_json(web_product_lines($data['beam_angle'] ?? [])),
        'ip_rating'=>trim((string)($data['ip_rating'] ?? '')),
        'cutout_text'=>trim((string)($data['cutout_text'] ?? '')),
        'size_text'=>trim((string)($data['size_text'] ?? '')),
        'finish_json'=>web_product_json(web_product_lines($data['finish'] ?? [])),
        'light_source'=>trim((string)($data['light_source'] ?? '')),
        'dimming_json'=>web_product_json(web_product_lines($data['dimming'] ?? [])),
        'tags_json'=>web_product_json(web_product_lines($data['tags'] ?? [])),
        'datasheet_path'=>trim((string)($data['datasheet_path'] ?? '')),
        'installation_path'=>trim((string)($data['installation_path'] ?? '')),
        'photometric_path'=>trim((string)($data['photometric_path'] ?? '')),
        'cad_path'=>trim((string)($data['cad_path'] ?? '')),
        'bim_path'=>trim((string)($data['bim_path'] ?? '')),
        'video_url'=>trim((string)($data['video_url'] ?? '')),
        'is_featured'=>!empty($data['is_featured']) ? 1 : 0,
        'is_new'=>!empty($data['is_new']) ? 1 : 0,
        'is_published'=>!empty($data['is_published']) ? 1 : 0,
        'sort_order'=>(int)($data['sort_order'] ?? 0),
        'seo_title'=>trim((string)($data['seo_title'] ?? '')),
        'seo_description'=>trim((string)($data['seo_description'] ?? '')),
    ];

    if ($id > 0) {
        $assignments = [];
        foreach (array_keys($values) as $key) {
            $assignments[] = "`{$key}`=?";
        }
        $stmt = $pdo->prepare('UPDATE web_products SET ' . implode(',', $assignments) . ', updated_at=CURRENT_TIMESTAMP WHERE id=?');
        $stmt->execute([...array_values($values), $id]);
        return $id;
    }

    $columns = array_keys($values);
    $stmt = $pdo->prepare('INSERT INTO web_products (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
    $stmt->execute(array_values($values));
    return (int)$pdo->lastInsertId();
}

function web_product_save_category(PDO $pdo, array $data, int $id = 0): int
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('分类名称不能为空。');
    }
    $slug = web_product_slug(trim((string)($data['slug'] ?? '')) ?: $name);
    if ($id > 0) {
        $oldStmt = $pdo->prepare('SELECT slug FROM web_product_categories WHERE id=? LIMIT 1');
        $oldStmt->execute([$id]);
        $oldSlug = (string)($oldStmt->fetchColumn() ?: '');
        $stmt = $pdo->prepare('UPDATE web_product_categories SET name=?, slug=?, page_title=?, subtitle=?, description=?, seo_title=?, seo_description=?, sort_order=?, is_active=? WHERE id=?');
        $stmt->execute([$name,$slug,trim((string)($data['page_title']??'')),trim((string)($data['subtitle']??'')),trim((string)($data['description']??'')),trim((string)($data['seo_title']??'')),trim((string)($data['seo_description']??'')),(int)($data['sort_order']??0),!empty($data['is_active'])?1:0,$id]);
        if ($oldSlug !== '' && $oldSlug !== $slug) {
            $updateProducts = $pdo->prepare('UPDATE web_products SET category_slug=? WHERE category_slug=?');
            $updateProducts->execute([$slug, $oldSlug]);
        }
        return $id;
    }
    $stmt = $pdo->prepare('INSERT INTO web_product_categories (name,slug,page_title,subtitle,description,seo_title,seo_description,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$name,$slug,trim((string)($data['page_title']??'')),trim((string)($data['subtitle']??'')),trim((string)($data['description']??'')),trim((string)($data['seo_title']??'')),trim((string)($data['seo_description']??'')),(int)($data['sort_order']??0),!empty($data['is_active'])?1:0]);
    return (int)$pdo->lastInsertId();
}
