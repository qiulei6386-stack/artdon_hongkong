<?php

declare(strict_types=1);

require_once __DIR__ . '/products.php';
require_once __DIR__ . '/solution_icons.php';

/**
 * V6.4 product hierarchy
 * Catalogue = product series/families (web_products)
 * Series page = marketing + available sizes
 * Product detail = one concrete size/model (web_product_variants)
 */

function web_product_hierarchy_migrate(PDO $pdo): void
{
    web_products_migrate($pdo);

    web_product_add_column($pdo, 'web_products', 'family_label', "VARCHAR(120) NOT NULL DEFAULT '' AFTER card_output_value");
    web_product_add_column($pdo, 'web_products', 'family_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_label");
    web_product_add_column($pdo, 'web_products', 'family_subtitle', "VARCHAR(500) NOT NULL DEFAULT '' AFTER family_title");
    web_product_add_column($pdo, 'web_products', 'family_intro', "LONGTEXT NULL AFTER family_subtitle");
    web_product_add_column($pdo, 'web_products', 'family_features_json', "LONGTEXT NULL AFTER family_intro");
    web_product_add_column($pdo, 'web_products', 'family_applications_json', "LONGTEXT NULL AFTER family_features_json");
    // V7.1.6.15: editable Why Choose text and project/case module for series pages.
    web_product_add_column($pdo, 'web_products', 'family_why_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_applications_json");
    web_product_add_column($pdo, 'web_products', 'family_why_text', "LONGTEXT NULL AFTER family_why_title");
    web_product_add_column($pdo, 'web_products', 'family_projects_json', "LONGTEXT NULL AFTER family_why_text");
    // V7.1.6.25: editable section headings so frontend exactly matches backend.
    web_product_add_column($pdo, 'web_products', 'family_characteristics_kicker', "VARCHAR(120) NOT NULL DEFAULT 'Characteristics' AFTER family_intro");
    web_product_add_column($pdo, 'web_products', 'family_characteristics_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_characteristics_kicker");
    web_product_add_column($pdo, 'web_products', 'family_application_kicker', "VARCHAR(120) NOT NULL DEFAULT 'Applications' AFTER family_applications_json");
    web_product_add_column($pdo, 'web_products', 'family_application_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_application_kicker");
    web_product_add_column($pdo, 'web_products', 'family_application_intro', "LONGTEXT NULL AFTER family_application_title");
    web_product_add_column($pdo, 'web_products', 'family_projects_kicker', "VARCHAR(120) NOT NULL DEFAULT 'Projects' AFTER family_projects_json");
    web_product_add_column($pdo, 'web_products', 'family_projects_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_projects_kicker");
    web_product_add_column($pdo, 'web_products', 'family_projects_intro', "LONGTEXT NULL AFTER family_projects_title");
    web_product_add_column($pdo, 'web_products', 'family_structure_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_projects_json");
    web_product_add_column($pdo, 'web_products', 'family_structure_text', "LONGTEXT NULL AFTER family_structure_title");
    web_product_add_column($pdo, 'web_products', 'family_structure_points_json', "LONGTEXT NULL AFTER family_structure_text");

    // V7.1.6.6: editable right-side hero text panel for series pages.
    web_product_add_column($pdo, 'web_products', 'family_hero_panel_text', "LONGTEXT NULL AFTER family_intro");
    web_product_add_column($pdo, 'web_products', 'family_hero_panel_font_size', "INT NOT NULL DEFAULT 20 AFTER family_hero_panel_text");
    web_product_add_column($pdo, 'web_products', 'family_hero_panel_bold', "TINYINT(1) NOT NULL DEFAULT 0 AFTER family_hero_panel_font_size");
    // V7.1.6.26: structured hero editor controls. These make the front title/body adjustable without painful rich-text editing.
    web_product_add_column($pdo, 'web_products', 'family_hero_title_size', "INT NOT NULL DEFAULT 32 AFTER family_hero_panel_bold");
    web_product_add_column($pdo, 'web_products', 'family_hero_title_weight', "INT NOT NULL DEFAULT 950 AFTER family_hero_title_size");
    web_product_add_column($pdo, 'web_products', 'family_hero_subtitle_size', "INT NOT NULL DEFAULT 28 AFTER family_hero_title_weight");
    web_product_add_column($pdo, 'web_products', 'family_hero_body_size', "INT NOT NULL DEFAULT 18 AFTER family_hero_subtitle_size");
    web_product_add_column($pdo, 'web_products', 'family_hero_body_line_height', "VARCHAR(10) NOT NULL DEFAULT '1.72' AFTER family_hero_body_size");
    web_product_add_column($pdo, 'web_products', 'family_hero_body_width', "INT NOT NULL DEFAULT 720 AFTER family_hero_body_line_height");
    web_product_add_column($pdo, 'web_products', 'family_hero_primary_label', "VARCHAR(120) NOT NULL DEFAULT 'Get a Quote' AFTER family_hero_panel_bold");
    web_product_add_column($pdo, 'web_products', 'family_hero_primary_url', "VARCHAR(500) NOT NULL DEFAULT '' AFTER family_hero_primary_label");
    web_product_add_column($pdo, 'web_products', 'family_hero_secondary_label', "VARCHAR(120) NOT NULL DEFAULT 'Download Datasheet' AFTER family_hero_primary_url");
    web_product_add_column($pdo, 'web_products', 'family_hero_secondary_url', "VARCHAR(500) NOT NULL DEFAULT '' AFTER family_hero_secondary_label");

    // V7.1.6.21: series hero can use 2-5 images with selectable display effects.
    web_product_add_column($pdo, 'web_products', 'family_hero_gallery_json', "LONGTEXT NULL AFTER family_hero_secondary_url");
    web_product_add_column($pdo, 'web_products', 'family_hero_gallery_effect', "VARCHAR(40) NOT NULL DEFAULT 'single' AFTER family_hero_gallery_json");
    web_product_add_column($pdo, 'web_products', 'family_hero_gallery_interval', "INT NOT NULL DEFAULT 4 AFTER family_hero_gallery_effect");

    // V7.1.6.18: editable bottom system catalogue download module.
    web_product_add_column($pdo, 'web_products', 'family_catalog_kicker', "VARCHAR(120) NOT NULL DEFAULT 'System catalogue' AFTER family_hero_secondary_url");
    web_product_add_column($pdo, 'web_products', 'family_catalog_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_catalog_kicker");
    web_product_add_column($pdo, 'web_products', 'family_catalog_text', "LONGTEXT NULL AFTER family_catalog_title");
    web_product_add_column($pdo, 'web_products', 'family_catalog_button_label', "VARCHAR(120) NOT NULL DEFAULT 'Download System Catalogue' AFTER family_catalog_text");
    web_product_add_column($pdo, 'web_products', 'family_catalog_button_url', "VARCHAR(500) NOT NULL DEFAULT '#downloads' AFTER family_catalog_button_label");

    // V7.1.6.23: editable project support CTA below the catalogue module.
    web_product_add_column($pdo, 'web_products', 'family_support_kicker', "VARCHAR(120) NOT NULL DEFAULT 'Project support' AFTER family_catalog_button_url");
    web_product_add_column($pdo, 'web_products', 'family_support_title', "VARCHAR(255) NOT NULL DEFAULT '' AFTER family_support_kicker");
    web_product_add_column($pdo, 'web_products', 'family_support_text', "LONGTEXT NULL AFTER family_support_title");
    web_product_add_column($pdo, 'web_products', 'family_support_button1_label', "VARCHAR(120) NOT NULL DEFAULT 'Get a Quote' AFTER family_support_text");
    web_product_add_column($pdo, 'web_products', 'family_support_button1_url', "VARCHAR(500) NOT NULL DEFAULT '' AFTER family_support_button1_label");
    web_product_add_column($pdo, 'web_products', 'family_support_button2_label', "VARCHAR(120) NOT NULL DEFAULT 'Request Sample' AFTER family_support_button1_url");
    web_product_add_column($pdo, 'web_products', 'family_support_button2_url', "VARCHAR(500) NOT NULL DEFAULT '' AFTER family_support_button2_label");
    web_product_add_column($pdo, 'web_products', 'family_support_button3_label', "VARCHAR(120) NOT NULL DEFAULT 'Download Datasheet' AFTER family_support_button2_url");
    web_product_add_column($pdo, 'web_products', 'family_support_button3_url', "VARCHAR(500) NOT NULL DEFAULT '' AFTER family_support_button3_label");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_product_variants (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        series_id BIGINT UNSIGNED NOT NULL,
        source_system VARCHAR(100) NOT NULL DEFAULT 'website',
        source_id VARCHAR(190) NOT NULL DEFAULT '',
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(190) NOT NULL UNIQUE,
        model_code VARCHAR(190) NOT NULL DEFAULT '',
        size_name VARCHAR(190) NOT NULL DEFAULT '',
        short_description VARCHAR(700) NOT NULL DEFAULT '',
        full_description LONGTEXT NULL,
        detail_intro LONGTEXT NULL,
        cover_image VARCHAR(500) NOT NULL DEFAULT '',
        card_image_scale INT NOT NULL DEFAULT 100,
        dimension_image VARCHAR(500) NOT NULL DEFAULT '',
        dimension_alt VARCHAR(255) NOT NULL DEFAULT '',
        dimension_image_scale INT NOT NULL DEFAULT 200,
        detail_layout VARCHAR(40) NOT NULL DEFAULT 'stacked',
        photometric_images_json LONGTEXT NULL,
        accessory_items_json LONGTEXT NULL,
        angle_images_json LONGTEXT NULL,
        gallery_json LONGTEXT NULL,
        dimensions VARCHAR(255) NOT NULL DEFAULT '',
        cutout_text VARCHAR(190) NOT NULL DEFAULT '',
        power_text VARCHAR(190) NOT NULL DEFAULT '',
        lumen_text VARCHAR(190) NOT NULL DEFAULT '',
        efficacy_text VARCHAR(190) NOT NULL DEFAULT '',
        voltage_json LONGTEXT NULL,
        cct_json LONGTEXT NULL,
        cri_json LONGTEXT NULL,
        beam_angle_json LONGTEXT NULL,
        ip_rating VARCHAR(80) NOT NULL DEFAULT '',
        finish_json LONGTEXT NULL,
        mounting_json LONGTEXT NULL,
        dimming_json LONGTEXT NULL,
        tags_json LONGTEXT NULL,
        extra_specs_json LONGTEXT NULL,
        spec_rows_json LONGTEXT NULL,
        datasheet_path VARCHAR(500) NOT NULL DEFAULT '',
        installation_path VARCHAR(500) NOT NULL DEFAULT '',
        photometric_path VARCHAR(500) NOT NULL DEFAULT '',
        cad_path VARCHAR(500) NOT NULL DEFAULT '',
        bim_path VARCHAR(500) NOT NULL DEFAULT '',
        video_url VARCHAR(500) NOT NULL DEFAULT '',
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description VARCHAR(500) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_variant_series (series_id, is_published, sort_order),
        INDEX idx_variant_model (model_code),
        INDEX idx_variant_source (source_system, source_id),
        CONSTRAINT fk_web_variant_series FOREIGN KEY (series_id) REFERENCES web_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // V7.1.8.24: editable first-screen intro under the product title.
    web_product_add_column($pdo, 'web_product_variants', 'detail_intro', "LONGTEXT NULL AFTER full_description");

    // V7.0.5: per-product catalogue-card image scale slider.
    web_product_add_column($pdo, 'web_product_variants', 'card_image_scale', 'INT NOT NULL DEFAULT 100 AFTER cover_image');

    // V6.12.14: product detail image layout + dedicated dimension drawing.
    web_product_add_column($pdo, 'web_product_variants', 'dimension_image', "VARCHAR(500) NOT NULL DEFAULT '' AFTER cover_image");
    web_product_add_column($pdo, 'web_product_variants', 'dimension_alt', "VARCHAR(255) NOT NULL DEFAULT '' AFTER dimension_image");
    // V7.1.7.9: adjustable dimension/structure drawing zoom percentage for product detail pages.
    web_product_add_column($pdo, 'web_product_variants', 'dimension_image_scale', "INT NOT NULL DEFAULT 200 AFTER dimension_alt");
    web_product_add_column($pdo, 'web_product_variants', 'detail_layout', "VARCHAR(40) NOT NULL DEFAULT 'stacked' AFTER dimension_alt");
    web_product_add_column($pdo, 'web_product_variants', 'photometric_images_json', "LONGTEXT NULL AFTER detail_layout");
    // V6.12.42: optional compatible accessories shown in a two-column module and exported to PDF.
    web_product_add_column($pdo, 'web_product_variants', 'accessory_items_json', "LONGTEXT NULL AFTER photometric_images_json");
    // V6.12.29: two optional product angle images under the dimension drawing.
    web_product_add_column($pdo, 'web_product_variants', 'angle_images_json', "LONGTEXT NULL AFTER photometric_images_json");
    web_product_add_column($pdo, 'web_product_variants', 'spec_rows_json', "LONGTEXT NULL AFTER extra_specs_json");

    web_product_hierarchy_seed_demo($pdo);
}

function web_product_hierarchy_decode_objects(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_filter($value, 'is_array'));
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

function web_product_series_hydrate(array $row): array
{
    $row = web_product_hydrate($row);
    $row['family_features'] = web_product_hierarchy_decode_objects($row['family_features_json'] ?? '[]');
    $row['family_applications'] = web_product_hierarchy_decode_objects($row['family_applications_json'] ?? '[]');
    $row['family_projects'] = web_product_hierarchy_decode_objects($row['family_projects_json'] ?? '[]');
    $row['family_hero_gallery'] = web_product_hierarchy_decode_objects($row['family_hero_gallery_json'] ?? '[]');
    $row['family_structure_points'] = web_product_decode($row['family_structure_points_json'] ?? '[]');
    return $row;
}

function web_product_series_find(PDO $pdo, string|int $identifier, bool $publishedOnly = true): ?array
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
    return $row ? web_product_series_hydrate($row) : null;
}


if (!function_exists('artdon_product_series_short_name')) {
    function artdon_product_series_short_name(array $series): string
    {
        $name = trim((string)($series['series_name'] ?? '')) ?: trim((string)($series['name'] ?? ''));
        $name = preg_replace('/\s+TRACK\s+LIGHT.*$/i', '', $name) ?? $name;
        $name = preg_replace('/\s+LIGHTING\s+SYSTEM.*$/i', '', $name) ?? $name;
        return trim($name) !== '' ? trim($name) : 'Product';
    }
}

if (!function_exists('artdon_series_solution_icon_labels_v7171')) {
    function artdon_series_solution_icon_labels_v7171(?PDO $pdo): array
    {
        $labels = [];
        if (function_exists('web_solution_icon_defaults')) {
            foreach (web_solution_icon_defaults() as $item) {
                $key = trim((string)($item['icon_key'] ?? ''));
                if ($key !== '') $labels[$key] = trim((string)($item['label'] ?? $key));
            }
        }
        if ($pdo && function_exists('web_solution_icons_migrate') && function_exists('web_solution_icons_all')) {
            try {
                web_solution_icons_migrate($pdo);
                foreach (web_solution_icons_all($pdo) as $item) {
                    $key = trim((string)($item['icon_key'] ?? ''));
                    if ($key !== '') $labels[$key] = trim((string)($item['label'] ?? $key));
                }
            } catch (Throwable $e) {}
        }
        return $labels;
    }
}


if (!function_exists('artdon_series_default_applications_v717')) {
    function artdon_series_default_applications_v717(array $series): array
    {
        return [
            [
                'icon'=>'retail',
                'title'=>'Retail Lighting',
                'image'=>'assets/img/projects/featured-retail.webp',
                'image_alt'=>'Retail lighting project application',
                'text'=>'Enhance merchandise presentation with precise beam control and high colour rendering.',
                'points'=>['High CRI for true colour','Narrow to wide beam options','Adjustable aiming for displays'],
            ],
            [
                'icon'=>'hospitality',
                'title'=>'Hospitality Lighting',
                'image'=>'assets/img/projects/featured-hospitality.webp',
                'image_alt'=>'Hospitality lighting project application',
                'text'=>'Create comfortable guest experiences with warm, low-glare illumination.',
                'points'=>['Low glare for visual comfort','Warm dimming options','Flexible ambience creation'],
            ],
            [
                'icon'=>'museum',
                'title'=>'Museum & Gallery Lighting',
                'image'=>'assets/img/projects/featured-museum.webp',
                'image_alt'=>'Museum and gallery lighting application',
                'text'=>'Reveal artwork details while protecting visual integrity.',
                'points'=>['Accurate colour rendering','UV-free, safe for artwork','Precise light control'],
            ],
            [
                'icon'=>'office',
                'title'=>'Office Lighting',
                'image'=>'assets/img/projects/featured-office.webp',
                'image_alt'=>'Office lighting application',
                'text'=>'Support visual comfort and productivity through balanced architectural lighting.',
                'points'=>['Uniform illumination','Reduced glare','Energy efficient performance'],
            ],
        ];
    }
}

function web_product_series_content(array $series): array
{
    $features = $series['family_features'] ?? [];
    if (!$features) {
        $features = [
            ['title'=>'Precise optics','text'=>'Controlled beams and carefully selected optical components for clear, consistent light.','image'=>'','image_alt'=>'Precise optics lighting detail'],
            ['title'=>'Multiple sizes','text'=>'One family covers different ceiling heights, outputs and project proportions.','image'=>'','image_alt'=>'Multiple product sizes'],
            ['title'=>'Visual comfort','text'=>'Deep anti-glare design and balanced luminance support demanding commercial spaces.','image'=>'','image_alt'=>'Visual comfort lighting'],
            ['title'=>'Project flexibility','text'=>'Multiple beam angles, finishes, control options and technical files for specification.','image'=>'','image_alt'=>'Project flexible lighting system'],
        ];
    }

    $applications = array_values(array_filter($series['family_applications'] ?? [], 'is_array'));
    $defaultApplications = artdon_series_default_applications_v717($series);
    // V7.1.7: frontend and backend use the same array. If saved data has fewer than 4 cards,
    // fill the rest with editable defaults so the backend and frontend never disagree.
    for ($i = 0; $i < 4; $i++) {
        if (!isset($applications[$i]) || !is_array($applications[$i])) {
            $applications[$i] = $defaultApplications[$i];
            continue;
        }
        $applications[$i] = array_merge($defaultApplications[$i], $applications[$i]);
    }
    $applications = array_slice($applications, 0, 4);

    $projects = $series['family_projects'] ?? [];
    if (!$projects) {
        $short = trim((string)($series['series_name'] ?? $series['name'] ?? 'SPECTRUM')) ?: 'SPECTRUM';
        $projects = [
            [
                'category'=>'Retail', 'title'=>'LOTTE Mall Korea', 'location'=>'Seoul, Korea', 'type'=>'Shopping Mall', 'year'=>'2024',
                'image'=>'assets/img/projects/featured-retail.webp',
                'image_alt'=>$short . ' retail lighting project',
                'text'=>$short . ' track lights were selected for precise beam control and high visual comfort, enhancing the shopping experience and product presentation.',
                'product_used'=>$short . ' 55\n' . $short . ' 75', 'beam_angle'=>'20° / 39°', 'control'=>'DALI'
            ],
            [
                'category'=>'Retail', 'title'=>'Luxury Fashion Boutique', 'location'=>'Milan, Italy', 'type'=>'Fashion Boutique', 'year'=>'2023',
                'image'=>'assets/img/projects/featured-hospitality.webp',
                'image_alt'=>$short . ' luxury boutique lighting project',
                'text'=>'Custom beam angles and high CRI lighting bring out the true texture and colour of fabrics and materials, creating an elegant and inviting atmosphere.',
                'product_used'=>$short . ' 45\n' . $short . ' 55', 'beam_angle'=>'15° / 25° / 51°', 'control'=>'TRIAC'
            ],
            [
                'category'=>'Commercial', 'title'=>'Commercial Office Project', 'location'=>'Singapore', 'type'=>'Office', 'year'=>'2023',
                'image'=>'assets/img/projects/featured-office.webp',
                'image_alt'=>$short . ' office lighting project',
                'text'=>$short . ' provides uniform and comfortable lighting, supporting productivity while maintaining a clean architectural aesthetic.',
                'product_used'=>$short . ' 75\n' . $short . ' 100', 'beam_angle'=>'25° / 39°', 'control'=>'DALI'
            ],
        ];
    }

    $structurePoints = $series['family_structure_points'] ?? [];
    if (!$structurePoints) {
        $structurePoints = array_values(array_filter([
            trim((string)($series['light_source'] ?? '')) !== '' ? 'Light source: ' . trim((string)$series['light_source']) : '',
            !empty($series['beam_angle']) ? 'Light distributions: ' . implode(' / ', $series['beam_angle']) : '',
            !empty($series['finish']) ? 'Finishes: ' . implode(' / ', $series['finish']) : '',
            !empty($series['dimming']) ? 'Control: ' . implode(' / ', $series['dimming']) : '',
        ]));
    }

    return [
        'label'=>trim((string)($series['family_label'] ?? '')) ?: trim((string)($series['series_name'] ?? '')) ?: 'Product family',
        'title'=>trim((string)($series['family_title'] ?? '')) ?: ((string)$series['name'] . '. Precision for every scale'),
        'subtitle'=>trim((string)($series['family_subtitle'] ?? '')) ?: trim((string)($series['card_subtitle'] ?? $series['short_description'] ?? '')),
        'intro'=>trim((string)($series['family_intro'] ?? '')) ?: trim((string)($series['full_description'] ?? $series['short_description'] ?? '')),
        'hero_panel_text'=>trim((string)($series['family_hero_panel_text'] ?? '')),
        'hero_panel_font_size'=>max(12, min(36, (int)($series['family_hero_panel_font_size'] ?? 20))),
        'hero_panel_bold'=>!empty($series['family_hero_panel_bold']),
        'hero_title_size'=>max(22, min(110, (int)($series['family_hero_title_size'] ?? 32))),
        'hero_title_weight'=>in_array((int)($series['family_hero_title_weight'] ?? 950), [650,700,800,900,950], true) ? (int)($series['family_hero_title_weight'] ?? 950) : 950,
        'hero_subtitle_size'=>max(11, min(46, (int)($series['family_hero_subtitle_size'] ?? 28))),
        'hero_body_size'=>max(11, min(36, (int)($series['family_hero_body_size'] ?? 18))),
        'hero_body_line_height'=>in_array((string)($series['family_hero_body_line_height'] ?? '1.72'), ['1.45','1.6','1.72','1.85','2.0'], true) ? (string)($series['family_hero_body_line_height'] ?? '1.72') : '1.72',
        'hero_body_width'=>max(360, min(1100, (int)($series['family_hero_body_width'] ?? 720))),
        'hero_primary_label'=>trim((string)($series['family_hero_primary_label'] ?? '')) ?: 'Get a Quote',
        'hero_primary_url'=>trim((string)($series['family_hero_primary_url'] ?? '')),
        'hero_secondary_label'=>trim((string)($series['family_hero_secondary_label'] ?? '')) ?: 'Download Datasheet',
        'hero_secondary_url'=>trim((string)($series['family_hero_secondary_url'] ?? '')),
        'hero_gallery'=>web_product_hierarchy_decode_objects($series['family_hero_gallery'] ?? ($series['family_hero_gallery_json'] ?? '[]')),
        'hero_gallery_effect'=>in_array((string)($series['family_hero_gallery_effect'] ?? 'single'), ['single','slider','collage','strip','stack'], true) ? (string)($series['family_hero_gallery_effect'] ?? 'single') : 'single',
        'hero_gallery_interval'=>max(2, min(12, (int)($series['family_hero_gallery_interval'] ?? 4))),
        'catalog_kicker'=>trim((string)($series['family_catalog_kicker'] ?? '')) ?: 'System catalogue',
        'catalog_title'=>trim((string)($series['family_catalog_title'] ?? '')) ?: ('Download ' . artdon_product_series_short_name($series) . ' system catalogue'),
        'catalog_text'=>trim((string)($series['family_catalog_text'] ?? '')) ?: 'Download the latest family catalogue, technical files and specification resources for this product system.',
        'catalog_button_label'=>trim((string)($series['family_catalog_button_label'] ?? '')) ?: 'Download System Catalogue',
        'catalog_button_url'=>trim((string)($series['family_catalog_button_url'] ?? '')) ?: '#downloads',
        'support_kicker'=>trim((string)($series['family_support_kicker'] ?? '')) ?: 'Project support',
        'support_title'=>trim((string)($series['family_support_title'] ?? '')) ?: ('Request ' . artdon_product_series_short_name($series) . ' for your next project'),
        'support_text'=>trim((string)($series['family_support_text'] ?? '')) ?: 'Get quotation support, samples and technical files for this product family.',
        'support_button1_label'=>trim((string)($series['family_support_button1_label'] ?? '')) ?: 'Get a Quote',
        'support_button1_url'=>trim((string)($series['family_support_button1_url'] ?? '')),
        'support_button2_label'=>trim((string)($series['family_support_button2_label'] ?? '')) ?: 'Request Sample',
        'support_button2_url'=>trim((string)($series['family_support_button2_url'] ?? '')),
        'support_button3_label'=>trim((string)($series['family_support_button3_label'] ?? '')) ?: 'Download Datasheet',
        'support_button3_url'=>trim((string)($series['family_support_button3_url'] ?? '')),
        'characteristics_kicker'=>trim((string)($series['family_characteristics_kicker'] ?? '')) ?: 'Characteristics',
        'characteristics_title'=>trim((string)($series['family_characteristics_title'] ?? '')) ?: ('What defines ' . artdon_product_series_short_name($series)),
        'application_kicker'=>trim((string)($series['family_application_kicker'] ?? '')) ?: 'Applications',
        'application_title'=>trim((string)($series['family_application_title'] ?? '')) ?: ('Where ' . artdon_product_series_short_name($series) . ' Performs Best'),
        'application_intro'=>trim((string)($series['family_application_intro'] ?? '')) ?: (artdon_product_series_short_name($series) . ' is designed for commercial and architectural environments where precise lighting, visual comfort and reliability are essential.'),
        'projects_kicker'=>trim((string)($series['family_projects_kicker'] ?? '')) ?: 'Projects',
        'projects_title'=>trim((string)($series['family_projects_title'] ?? '')) ?: ('Projects using ' . artdon_product_series_short_name($series)),
        'projects_intro'=>trim((string)($series['family_projects_intro'] ?? '')) ?: ('See how ' . artdon_product_series_short_name($series) . ' delivers precision and performance in real-world projects.'),
        'features'=>$features,
        'applications'=>$applications,
        'why_title'=>trim((string)($series['family_why_title'] ?? '')),
        'why_text'=>trim((string)($series['family_why_text'] ?? '')),
        'projects'=>$projects,
        'structure_title'=>trim((string)($series['family_structure_title'] ?? '')) ?: 'System structure and configuration',
        'structure_text'=>trim((string)($series['family_structure_text'] ?? '')) ?: 'The family combines a consistent design language with different dimensions, outputs and optical options. Select the size below to open the complete product data.',
        'structure_points'=>$structurePoints,
    ];
}

function web_product_series_save_content(PDO $pdo, int $seriesId, array $data): void
{
    $features = [];
    foreach (($data['features'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $title = trim((string)($item['title'] ?? ''));
        $text = trim((string)($item['text'] ?? ''));
        $image = trim((string)($item['image'] ?? ''));
        $imageAlt = trim((string)($item['image_alt'] ?? $item['alt'] ?? ''));
        if ($imageAlt === '' && $title !== '') $imageAlt = $title;
        if ($title !== '' || $text !== '' || $image !== '' || $imageAlt !== '') {
            $features[] = ['title'=>$title,'text'=>$text,'image'=>$image,'image_alt'=>$imageAlt];
        }
    }
    $applications = [];
    $iconLabels = artdon_series_solution_icon_labels_v7171($pdo);
    foreach (($data['applications'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $icon = trim((string)($item['icon'] ?? 'retail'));
        $title = trim((string)($item['title'] ?? ''));
        if ($title === '' && isset($iconLabels[$icon])) {
            $title = (string)$iconLabels[$icon];
        }
        $text = trim((string)($item['text'] ?? ''));
        $image = trim((string)($item['image'] ?? ''));
        $imageAlt = trim((string)($item['image_alt'] ?? $item['alt'] ?? ''));
        if ($imageAlt === '' && $title !== '') $imageAlt = $title;
        $points = web_product_lines($item['points'] ?? []);
        if ($title !== '' || $text !== '' || $image !== '' || $points || $imageAlt !== '') {
            $applications[] = ['title'=>$title,'text'=>$text,'image'=>$image,'image_alt'=>$imageAlt,'icon'=>$icon,'points'=>$points];
        }
        if (count($applications) >= 4) break;
    }
    $projects = [];
    foreach (($data['projects'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $title = trim((string)($item['title'] ?? ''));
        $image = trim((string)($item['image'] ?? ''));
        $text = trim((string)($item['text'] ?? ''));
        $imageAlt = trim((string)($item['image_alt'] ?? $item['alt'] ?? ''));
        if ($imageAlt === '' && $title !== '') $imageAlt = $title;
        if ($title === '' && $image === '' && $text === '' && $imageAlt === '') continue;
        $projects[] = [
            'category'=>trim((string)($item['category'] ?? '')),
            'title'=>$title,
            'location'=>trim((string)($item['location'] ?? '')),
            'type'=>trim((string)($item['type'] ?? '')),
            'year'=>trim((string)($item['year'] ?? '')),
            'image'=>$image,
            'image_alt'=>$imageAlt,
            'text'=>$text,
            'product_used'=>trim((string)($item['product_used'] ?? '')),
            'beam_angle'=>trim((string)($item['beam_angle'] ?? '')),
            'control'=>trim((string)($item['control'] ?? '')),
        ];
        if (count($projects) >= 4) break;
    }
    $heroGallery = [];
    foreach (($data['hero_gallery'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $image = trim((string)($item['image'] ?? ''));
        if ($image === '') continue;
        $alt = trim((string)($item['alt'] ?? $item['image_alt'] ?? ''));
        $heroGallery[] = [
            'image'=>$image,
            'alt'=>$alt,
        ];
        if (count($heroGallery) >= 5) break;
    }
    $heroGalleryEffect = trim((string)($data['family_hero_gallery_effect'] ?? 'single'));
    if (!in_array($heroGalleryEffect, ['single','slider','collage','strip','stack'], true)) $heroGalleryEffect = 'single';
    $heroGalleryInterval = max(2, min(12, (int)($data['family_hero_gallery_interval'] ?? 4)));

    $heroFontSize = max(12, min(36, (int)($data['family_hero_panel_font_size'] ?? 20)));
    $heroTitleSize = max(22, min(110, (int)($data['family_hero_title_size'] ?? 58)));
    $heroTitleWeight = (int)($data['family_hero_title_weight'] ?? 950);
    if (!in_array($heroTitleWeight, [650,700,800,900,950], true)) $heroTitleWeight = 950;
    $heroSubtitleSize = max(11, min(46, (int)($data['family_hero_subtitle_size'] ?? 18)));
    $heroBodySize = max(11, min(36, (int)($data['family_hero_body_size'] ?? 17)));
    $heroBodyLineHeight = (string)($data['family_hero_body_line_height'] ?? '1.72');
    if (!in_array($heroBodyLineHeight, ['1.45','1.6','1.72','1.85','2.0'], true)) $heroBodyLineHeight = '1.72';
    $heroBodyWidth = max(360, min(1100, (int)($data['family_hero_body_width'] ?? 720)));
    $stmt = $pdo->prepare('UPDATE web_products SET family_label=?, family_title=?, family_subtitle=?, family_intro=?, family_characteristics_kicker=?, family_characteristics_title=?, family_application_kicker=?, family_application_title=?, family_application_intro=?, family_projects_kicker=?, family_projects_title=?, family_projects_intro=?, family_hero_panel_text=?, family_hero_panel_font_size=?, family_hero_panel_bold=?, family_hero_title_size=?, family_hero_title_weight=?, family_hero_subtitle_size=?, family_hero_body_size=?, family_hero_body_line_height=?, family_hero_body_width=?, family_hero_primary_label=?, family_hero_primary_url=?, family_hero_secondary_label=?, family_hero_secondary_url=?, family_hero_gallery_json=?, family_hero_gallery_effect=?, family_hero_gallery_interval=?, family_catalog_kicker=?, family_catalog_title=?, family_catalog_text=?, family_catalog_button_label=?, family_catalog_button_url=?, family_support_kicker=?, family_support_title=?, family_support_text=?, family_support_button1_label=?, family_support_button1_url=?, family_support_button2_label=?, family_support_button2_url=?, family_support_button3_label=?, family_support_button3_url=?, family_features_json=?, family_applications_json=?, family_why_title=?, family_why_text=?, family_projects_json=?, family_structure_title=?, family_structure_text=?, family_structure_points_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $stmt->execute([
        trim((string)($data['family_label'] ?? '')),
        trim((string)($data['family_title'] ?? '')),
        trim((string)($data['family_subtitle'] ?? '')),
        trim((string)($data['family_intro'] ?? '')),
        trim((string)($data['family_characteristics_kicker'] ?? '')),
        trim((string)($data['family_characteristics_title'] ?? '')),
        trim((string)($data['family_application_kicker'] ?? '')),
        trim((string)($data['family_application_title'] ?? '')),
        trim((string)($data['family_application_intro'] ?? '')),
        trim((string)($data['family_projects_kicker'] ?? '')),
        trim((string)($data['family_projects_title'] ?? '')),
        trim((string)($data['family_projects_intro'] ?? '')),
        trim((string)($data['family_hero_panel_text'] ?? '')),
        $heroFontSize,
        !empty($data['family_hero_panel_bold']) ? 1 : 0,
        $heroTitleSize,
        $heroTitleWeight,
        $heroSubtitleSize,
        $heroBodySize,
        $heroBodyLineHeight,
        $heroBodyWidth,
        trim((string)($data['family_hero_primary_label'] ?? '')),
        trim((string)($data['family_hero_primary_url'] ?? '')),
        trim((string)($data['family_hero_secondary_label'] ?? '')),
        trim((string)($data['family_hero_secondary_url'] ?? '')),
        json_encode($heroGallery, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        $heroGalleryEffect,
        $heroGalleryInterval,
        trim((string)($data['family_catalog_kicker'] ?? '')),
        trim((string)($data['family_catalog_title'] ?? '')),
        trim((string)($data['family_catalog_text'] ?? '')),
        trim((string)($data['family_catalog_button_label'] ?? '')),
        trim((string)($data['family_catalog_button_url'] ?? '')),
        trim((string)($data['family_support_kicker'] ?? '')),
        trim((string)($data['family_support_title'] ?? '')),
        trim((string)($data['family_support_text'] ?? '')),
        trim((string)($data['family_support_button1_label'] ?? '')),
        trim((string)($data['family_support_button1_url'] ?? '')),
        trim((string)($data['family_support_button2_label'] ?? '')),
        trim((string)($data['family_support_button2_url'] ?? '')),
        trim((string)($data['family_support_button3_label'] ?? '')),
        trim((string)($data['family_support_button3_url'] ?? '')),
        json_encode($features, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        json_encode($applications, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        trim((string)($data['family_why_title'] ?? '')),
        trim((string)($data['family_why_text'] ?? '')),
        json_encode($projects, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        trim((string)($data['family_structure_title'] ?? '')),
        trim((string)($data['family_structure_text'] ?? '')),
        web_product_json(web_product_lines($data['family_structure_points'] ?? [])),
        $seriesId,
    ]);
}

function web_product_variant_hydrate(array $row): array
{
    $row['detail_intro'] = trim((string)($row['detail_intro'] ?? ''));
    foreach (['voltage_json','cct_json','cri_json','beam_angle_json','finish_json','mounting_json','dimming_json','tags_json'] as $field) {
        $row[str_replace('_json', '', $field)] = web_product_decode($row[$field] ?? '[]');
    }
    $gallery = json_decode((string)($row['gallery_json'] ?? '[]'), true);
    $row['gallery'] = is_array($gallery) ? $gallery : [];
    $extra = json_decode((string)($row['extra_specs_json'] ?? '[]'), true);
    $row['extra_specs'] = is_array($extra) ? $extra : [];
    $specRows = json_decode((string)($row['spec_rows_json'] ?? '[]'), true);
    $row['spec_rows'] = is_array($specRows) ? array_values(array_filter($specRows, static fn($item): bool => is_array($item) && (trim((string)($item['label'] ?? '')) !== '' || trim((string)($item['value'] ?? '')) !== ''))) : [];
    $allowedLayouts = ['stacked','split','strip','switcher','technical_below'];
    $layout = trim((string)($row['detail_layout'] ?? 'stacked'));
    $row['detail_layout'] = in_array($layout, $allowedLayouts, true) ? $layout : 'stacked';
    $row['dimension_image'] = trim((string)($row['dimension_image'] ?? ''));
    $row['dimension_alt'] = trim((string)($row['dimension_alt'] ?? ''));
    $dimensionScale = (int)($row['dimension_image_scale'] ?? 200);
    if ($dimensionScale <= 0) $dimensionScale = 200;
    $row['dimension_image_scale'] = max(50, min(500, $dimensionScale));
    $photometric = json_decode((string)($row['photometric_images_json'] ?? '[]'), true);
    $row['photometric_images'] = is_array($photometric) ? array_values(array_filter($photometric, static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== '')) : [];
    $accessories = json_decode((string)($row['accessory_items_json'] ?? '[]'), true);
    $row['accessory_items'] = is_array($accessories) ? array_values(array_filter($accessories, static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== '')) : [];
    $angles = json_decode((string)($row['angle_images_json'] ?? '[]'), true);
    $row['angle_images'] = is_array($angles) ? array_values(array_filter($angles, static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== '')) : [];
    return $row;
}

function web_product_variants(PDO $pdo, int $seriesId, bool $publishedOnly = true): array
{
    $sql = 'SELECT * FROM web_product_variants WHERE series_id=?';
    if ($publishedOnly) $sql .= ' AND is_published=1';
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$seriesId]);
    return array_map('web_product_variant_hydrate', $stmt->fetchAll() ?: []);
}

function web_product_variant_find(PDO $pdo, string|int $identifier, bool $publishedOnly = true): ?array
{
    if (is_int($identifier) || ctype_digit((string)$identifier)) {
        $sql = 'SELECT * FROM web_product_variants WHERE id=?';
        $params = [(int)$identifier];
    } else {
        $sql = 'SELECT * FROM web_product_variants WHERE slug=?';
        $params = [(string)$identifier];
    }
    if ($publishedOnly) $sql .= ' AND is_published=1';
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? web_product_variant_hydrate($row) : null;
}

function web_product_variant_unique_slug(PDO $pdo, string $slug, int $excludeId = 0): string
{
    $base = web_product_slug($slug);
    $candidate = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM web_product_variants WHERE slug=?';
        $params = [$candidate];
        if ($excludeId > 0) {
            $sql .= ' AND id<>?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) return $candidate;
        $candidate = $base . '-' . $i++;
    }
}

function web_product_variant_save(PDO $pdo, array $data, int $id = 0): int
{
    $seriesId = (int)($data['series_id'] ?? 0);
    if ($seriesId <= 0 || !web_product_series_find($pdo, $seriesId, false)) {
        throw new RuntimeException('请选择有效的产品系列。');
    }
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') throw new RuntimeException('产品尺寸名称不能为空。');
    $slug = web_product_variant_unique_slug($pdo, trim((string)($data['slug'] ?? '')) ?: $name, $id);

    $gallery = [];
    foreach (($data['gallery'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $image = trim((string)($item['image'] ?? ''));
        if ($image !== '') $gallery[] = ['image'=>$image,'alt'=>trim((string)($item['alt'] ?? ''))];
    }
    $photometricImages = [];
    foreach (($data['photometric_images'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $image = trim((string)($item['image'] ?? ''));
        if ($image === '') continue;
        $photometricImages[] = [
            'image'=>$image,
            'label'=>trim((string)($item['label'] ?? '')),
            'alt'=>trim((string)($item['alt'] ?? '')),
        ];
        if (count($photometricImages) >= 4) break;
    }
    $accessoryItems = [];
    foreach (($data['accessory_items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $image = trim((string)($item['image'] ?? ''));
        if ($image === '') continue;
        $accessoryItems[] = [
            'image'=>$image,
            'title'=>trim((string)($item['title'] ?? '')),
            'model'=>trim((string)($item['model'] ?? '')),
            'description'=>trim((string)($item['description'] ?? '')),
            'alt'=>trim((string)($item['alt'] ?? '')),
        ];
        if (count($accessoryItems) >= 12) break;
    }
    $angleImages = [];
    foreach (($data['angle_images'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $image = trim((string)($item['image'] ?? ''));
        if ($image === '') continue;
        $angleImages[] = [
            'image'=>$image,
            'label'=>trim((string)($item['label'] ?? '')),
            'alt'=>trim((string)($item['alt'] ?? '')),
        ];
        if (count($angleImages) >= 2) break;
    }
    $extraSpecs = [];
    foreach (($data['extra_specs'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $label = trim((string)($item['label'] ?? ''));
        $value = trim((string)($item['value'] ?? ''));
        if ($label !== '' || $value !== '') $extraSpecs[] = ['label'=>$label,'value'=>$value];
    }
    $specRows = [];
    foreach (($data['spec_rows'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $label = trim((string)($item['label'] ?? ''));
        $value = trim((string)($item['value'] ?? ''));
        $active = !isset($item['active']) || !empty($item['active']) ? 1 : 0;
        if ($label !== '' || $value !== '') $specRows[] = ['label'=>$label,'value'=>$value,'active'=>$active];
    }

    $values = [
        'series_id'=>$seriesId,
        'source_system'=>trim((string)($data['source_system'] ?? 'website')) ?: 'website',
        'source_id'=>trim((string)($data['source_id'] ?? '')),
        'name'=>$name,
        'slug'=>$slug,
        'model_code'=>trim((string)($data['model_code'] ?? '')),
        'size_name'=>trim((string)($data['size_name'] ?? '')),
        'short_description'=>trim((string)($data['short_description'] ?? '')),
        'full_description'=>trim((string)($data['full_description'] ?? '')),
        'detail_intro'=>trim((string)($data['detail_intro'] ?? '')),
        'cover_image'=>trim((string)($data['cover_image'] ?? '')),
        'card_image_scale'=>max(60, min(180, (int)($data['card_image_scale'] ?? 100))),
        'dimension_image'=>trim((string)($data['dimension_image'] ?? '')),
        'dimension_alt'=>trim((string)($data['dimension_alt'] ?? '')),
        'dimension_image_scale'=>100,
        'detail_layout'=>in_array(trim((string)($data['detail_layout'] ?? 'stacked')), ['stacked','split','strip','switcher','technical_below'], true) ? trim((string)$data['detail_layout']) : 'stacked',
        'photometric_images_json'=>json_encode($photometricImages, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        'accessory_items_json'=>json_encode($accessoryItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        'angle_images_json'=>json_encode($angleImages, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        'gallery_json'=>json_encode($gallery, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        'dimensions'=>trim((string)($data['dimensions'] ?? '')),
        'cutout_text'=>trim((string)($data['cutout_text'] ?? '')),
        'power_text'=>trim((string)($data['power_text'] ?? '')),
        'lumen_text'=>trim((string)($data['lumen_text'] ?? '')),
        'efficacy_text'=>trim((string)($data['efficacy_text'] ?? '')),
        'voltage_json'=>web_product_json(web_product_lines($data['voltage'] ?? [])),
        'cct_json'=>web_product_json(web_product_lines($data['cct'] ?? [])),
        'cri_json'=>web_product_json(web_product_lines($data['cri'] ?? [])),
        'beam_angle_json'=>web_product_json(web_product_lines($data['beam_angle'] ?? [])),
        'ip_rating'=>trim((string)($data['ip_rating'] ?? '')),
        'finish_json'=>web_product_json(web_product_lines($data['finish'] ?? [])),
        'mounting_json'=>web_product_json(web_product_lines($data['mounting'] ?? [])),
        'dimming_json'=>web_product_json(web_product_lines($data['dimming'] ?? [])),
        'tags_json'=>web_product_json(web_product_lines($data['tags'] ?? [])),
        'extra_specs_json'=>json_encode($extraSpecs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        'spec_rows_json'=>json_encode($specRows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]',
        'datasheet_path'=>trim((string)($data['datasheet_path'] ?? '')),
        'installation_path'=>trim((string)($data['installation_path'] ?? '')),
        'photometric_path'=>trim((string)($data['photometric_path'] ?? '')),
        'cad_path'=>trim((string)($data['cad_path'] ?? '')),
        'bim_path'=>trim((string)($data['bim_path'] ?? '')),
        'video_url'=>trim((string)($data['video_url'] ?? '')),
        'is_published'=>!empty($data['is_published']) ? 1 : 0,
        'sort_order'=>(int)($data['sort_order'] ?? 0),
        'seo_title'=>trim((string)($data['seo_title'] ?? '')),
        'seo_description'=>trim((string)($data['seo_description'] ?? '')),
    ];

    if ($id > 0) {
        $assign = [];
        foreach (array_keys($values) as $column) $assign[] = "`{$column}`=?";
        $params = array_values($values);
        $params[] = $id;
        $pdo->prepare('UPDATE web_product_variants SET ' . implode(',', $assign) . ', updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($params);
        return $id;
    }
    $columns = array_keys($values);
    $stmt = $pdo->prepare('INSERT INTO web_product_variants (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
    $stmt->execute(array_values($values));
    return (int)$pdo->lastInsertId();
}

function web_product_hierarchy_seed_demo(PDO $pdo): void
{
    if ((string)web_setting_get($pdo, 'v64_demo_variants_seeded', '') === '1') return;
    if ((int)$pdo->query('SELECT COUNT(*) FROM web_product_variants')->fetchColumn() > 0) {
        web_setting_set($pdo, 'v64_demo_variants_seeded', '1');
        return;
    }

    $stmt = $pdo->query("SELECT * FROM web_products WHERE is_published=1 AND (UPPER(series_name)='SPECTRUM' OR UPPER(name) LIKE 'SPECTRUM%') ORDER BY id ASC LIMIT 1");
    $series = $stmt->fetch();
    if (!$series) {
        $stmt = $pdo->query("SELECT * FROM web_products WHERE is_published=1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $series = $stmt->fetch();
    }
    if (!$series) { web_setting_set($pdo, 'v64_demo_variants_seeded', '1'); return; }

    $baseName = trim((string)($series['series_name'] ?: $series['name']));
    $baseSlug = trim((string)$series['slug']);
    $cover = trim((string)$series['cover_image']);
    $rows = [
        ['55','SP-55','Ø55 × H95 mm','Ø50 mm','8W','620 lm','78 lm/W',10],
        ['65','SP-65','Ø65 × H110 mm','Ø60 mm','12W','980 lm','82 lm/W',20],
        ['75','SP-75','Ø75 × H125 mm','Ø68 mm','18W','1550 lm','86 lm/W',30],
        ['90','SP-90','Ø90 × H145 mm','Ø82 mm','25W','2250 lm','90 lm/W',40],
    ];
    $insert = $pdo->prepare("INSERT INTO web_product_variants (series_id,name,slug,model_code,size_name,short_description,full_description,cover_image,gallery_json,dimensions,cutout_text,power_text,lumen_text,efficacy_text,voltage_json,cct_json,cri_json,beam_angle_json,ip_rating,finish_json,mounting_json,dimming_json,tags_json,extra_specs_json,is_published,sort_order,seo_title,seo_description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($rows as [$size,$model,$dimensions,$cutout,$power,$lumen,$efficacy,$sort]) {
        $name = $baseName . ' ' . $size;
        $insert->execute([
            (int)$series['id'], $name, web_product_slug($baseSlug . '-' . $size), $model, 'Size ' . $size,
            'A proportional size within the ' . $baseName . ' family for different ceiling heights and output requirements.',
            'This is demonstration content for the new series → size → detailed product structure. Replace the dimensions and technical values in the website backend with the confirmed product data.',
            $cover, '[]', $dimensions, $cutout, $power, $lumen, $efficacy,
            web_product_json(['220-240V']), web_product_json(['2700K','3000K','4000K']), web_product_json(['CRI90']), web_product_json(['15°','24°','36°']), 'IP20',
            web_product_json(['White','Black']), web_product_json(['Track mounted']), web_product_json(['ON/OFF','DALI']), web_product_json(['Spotlight','Low glare','Commercial']), '[]',
            1, $sort, $name . ' | Artdon Lighting', 'Technical information, dimensions and downloads for ' . $name . '.',
        ]);
    }
    web_setting_set($pdo, 'v64_demo_variants_seeded', '1');
}
