<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) {
    header('Location: login.php');
    exit;
}
web_migrate($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: solutions_page.php');
    exit;
}
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: solutions_page.php');
    exit;
}

function sol_save_clean(mixed $value): string { return trim((string)$value); }
function sol_save_rows(mixed $rows): array { return is_array($rows) ? array_filter($rows, 'is_array') : []; }
function sol_save_text_rows(mixed $value, int $limit = 8): array
{
    if (is_array($value)) {
        return array_slice(array_map(static fn($item) => trim((string)$item), $value), 0, $limit);
    }
    $lines = preg_split('/\R/', (string)$value) ?: [];
    return array_slice(array_map('trim', $lines), 0, $limit);
}
function sol_save_lines(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_filter(array_map(static fn($line) => trim((string)$line), $value), static fn($line) => $line !== ''));
    }
    $lines = preg_split('/\R+/', trim((string)$value)) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
}
function sol_save_upload(string $field, string $usage, PDO $pdo, int $userId, string $title, string $alt = ''): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, $userId, $title, $alt, $usage);
}
function sol_save_defaults(): array
{
    return [
        'hero'=>[
            'eyebrow'=>'SOLUTIONS','title'=>"Lighting Solutions\nfor Every Space",
            'intro'=>'From retail to hospitality, office to residential, we provide professional lighting solutions that solve challenges, create value and elevate every space.',
            'image'=>'assets/img/hero/hero-track-systems.webp','alt'=>'Architectural lighting solution background','show_cards'=>1,
            'cards'=>[
                ['active'=>1,'key'=>'retail','label'=>'Retail','tab'=>'retail','icon'=>'shop','image'=>'assets/img/projects/featured-retail.webp'],
                ['active'=>1,'key'=>'hospitality','label'=>'Hospitality','tab'=>'hospitality','icon'=>'hotel','image'=>'assets/img/projects/featured-hospitality.webp'],
                ['active'=>1,'key'=>'office','label'=>'Office','tab'=>'office','icon'=>'office','image'=>'assets/img/projects/featured-office.webp'],
                ['active'=>1,'key'=>'residential','label'=>'Residential','tab'=>'residential','icon'=>'home','image'=>'assets/img/project-hotel.svg'],
                ['active'=>1,'key'=>'museum-gallery','label'=>'Museum & Gallery','tab'=>'museum-gallery','icon'=>'optics','image'=>'assets/img/projects/featured-museum.webp'],
                ['active'=>1,'key'=>'commercial','label'=>'Commercial','tab'=>'commercial','icon'=>'office','image'=>'assets/img/project-retail.svg'],
            ],
        ],
        'strip'=>[
            'title'=>"Lighting\nSolutions",'intro'=>'Tailored lighting solutions for every space. From retail to hospitality, office to museum, we deliver professional lighting that enhances architecture, elevates experience and creates lasting value.',
            'button_label'=>'VIEW ALL SOLUTIONS','button_target'=>'retail','socials'=>['Instagram','LinkedIn','YouTube','Email'],
            'items'=>[
                ['active'=>1,'key'=>'retail','label'=>'Retail','seo_title'=>'Retail Lighting Solutions','text'=>'Highlight products, enhance ambience and drive sales.','image'=>'assets/img/projects/featured-retail.webp'],
                ['active'=>1,'key'=>'hospitality','label'=>'Hospitality','seo_title'=>'Hospitality Lighting Solutions','text'=>'Create welcoming atmospheres that guests remember.','image'=>'assets/img/projects/featured-hospitality.webp'],
                ['active'=>1,'key'=>'office','label'=>'Office','seo_title'=>'Office Lighting Solutions','text'=>'Improve focus, comfort and productivity at work.','image'=>'assets/img/projects/featured-office.webp'],
                ['active'=>1,'key'=>'residential','label'=>'Residential','seo_title'=>'Residential Lighting Solutions','text'=>'Bring light into everyday life with comfort and style.','image'=>'assets/img/project-hotel.svg'],
                ['active'=>1,'key'=>'museum-gallery','label'=>'Museum & Gallery','seo_title'=>'Museum & Gallery Lighting Solutions','text'=>'Accentuate art and exhibits with precise, glare-free light.','image'=>'assets/img/projects/featured-museum.webp'],
                ['active'=>1,'key'=>'commercial','label'=>'Commercial','seo_title'=>'Commercial Lighting Solutions','text'=>'Reliable lighting for public and large-scale spaces.','image'=>'assets/img/project-retail.svg'],
            ],
        ],
        'applications'=>[
            'eyebrow'=>'RECOMMENDED PRODUCT FAMILIES','title'=>'Solutions for Every Application',
            'subtitle'=>'Carefully selected product families designed to meet the specific lighting requirements of this application.',
            'view_label'=>'VIEW PRODUCT',
            'default_card_text'=>'Professional architectural lighting family for project applications.',
            'tabs'=>[
                ['active'=>1,'key'=>'retail','label'=>'Retail','recommend'=>['Spectrum','BeamX','ArcWash','Mini Pro'],'descriptions'=>[]],
                ['active'=>1,'key'=>'hospitality','label'=>'Hospitality','recommend'=>['Emma','Mini Pro','Flexi','Soft'],'descriptions'=>[]],
                ['active'=>1,'key'=>'office','label'=>'Office','recommend'=>['Magentra','Slim','Intero','Optimax'],'descriptions'=>[]],
                ['active'=>1,'key'=>'residential','label'=>'Residential','recommend'=>['Optimax','Magfit','Voli','Mini'],'descriptions'=>[]],
                ['active'=>1,'key'=>'museum-gallery','label'=>'Museum & Gallery','recommend'=>['Artax','BeamX','Mini Pro','Spectrum'],'descriptions'=>[]],
                ['active'=>1,'key'=>'commercial','label'=>'Commercial','recommend'=>['Spectrum','Magentra','BeamX','Delta'],'descriptions'=>[]],
            ],
        ],
        'projects'=>[
            'eyebrow'=>'PROJECT REFERENCE','title'=>'Proven in Real Projects','view_all_label'=>'View More Projects','view_all_url'=>'project.php',
            'items'=>[
                ['active'=>1,'title'=>'Fashion Boutique','place'=>'Seoul, Korea','image'=>'assets/img/projects/featured-retail.webp','url'=>'project.php?type=retail'],
                ['active'=>1,'title'=>'Shopping Mall','place'=>'Manila, Philippines','image'=>'assets/img/project-retail.svg','url'=>'project.php?type=retail'],
                ['active'=>1,'title'=>'Corporate Office','place'=>'Shenzhen, China','image'=>'assets/img/projects/featured-office.webp','url'=>'project.php?type=office'],
                ['active'=>1,'title'=>'Luxury Hotel','place'=>'Dubai, UAE','image'=>'assets/img/projects/featured-hospitality.webp','url'=>'project.php?type=hospitality'],
            ],
        ],
        'support'=>[
            'eyebrow'=>'LIGHTING DESIGN SUPPORT',
            'title'=>"Professional Support\nThroughout Your Project",
            'intro'=>'From concept to completion, we provide expert lighting design support to help you choose the right products, optics and solutions for every space.',
            'image'=>'assets/img/projects/featured-office.webp',
            'alt'=>'Lighting design support for project planning',
            'items'=>[
                ['active'=>1,'icon'=>'layout','title'=>'Lighting Layout','text'=>'We assist in creating efficient lighting layouts that balance functionality and aesthetics.'],
                ['active'=>1,'icon'=>'beam','title'=>'Beam Angle Recommendation','text'=>'Our team helps you select the most suitable beam angles for the best lighting effects.'],
                ['active'=>1,'icon'=>'optical','title'=>'Optical Design Support','text'=>'Professional optical design and simulation ensure precise light distribution.'],
                ['active'=>1,'icon'=>'finish','title'=>'Custom Finish & OEM','text'=>'Custom colors, materials and branding to meet your project and market requirements.'],
                ['active'=>1,'icon'=>'files','title'=>'IES / Photometric Files','text'=>'We provide IES files and photometric data to support your design and calculations.'],
                ['active'=>1,'icon'=>'consult','title'=>'Project Consultation','text'=>'Our experts are here to support you at every stage of your project.'],
            ],
        ],
        'why'=>[
            'eyebrow'=>'WHY CHOOSE ARTDON','title'=>'Built for Professional Projects',
            'items'=>[
                ['active'=>1,'title'=>'19+ Years Experience','text'=>'Deep know-how in architectural and commercial lighting.','icon'=>'years'],
                ['active'=>1,'title'=>'OEM & ODM','text'=>'Flexible product development for brand and project needs.','icon'=>'oem'],
                ['active'=>1,'title'=>'Fast Sample','text'=>'Efficient sampling to keep projects moving.','icon'=>'sample'],
                ['active'=>1,'title'=>'Optical Design','text'=>'Precise beams, low glare and high visual comfort.','icon'=>'optics'],
                ['active'=>1,'title'=>'Custom Finishes','text'=>'Surface colors and details for project interiors.','icon'=>'finish'],
                ['active'=>1,'title'=>'Project Support','text'=>'Technical guidance from concept to delivery.','icon'=>'support'],
            ],
        ],
        'cta'=>[
            'eyebrow'=>'PROJECT SUPPORT','title'=>'Have a Lighting Project?','intro'=>'Let our lighting experts help you create the perfect solution for your space.',
            'image'=>'assets/img/hero/hero-outdoor-projector.webp','alt'=>'Lighting project support',
            'primary_label'=>'Talk to Our Engineers','primary_url'=>'contact.php?topic=lighting-project','secondary_label'=>'Download Catalog','secondary_url'=>'downloads.php',
        ],
    ];
}
function sol_save_merge_existing(array $saved): array
{
    $defaults = sol_save_defaults();
    if (!isset($saved['hero']) && (isset($saved['hero_image']) || isset($saved['cards']))) {
        $saved = ['hero'=>[
            'image'=>$saved['hero_image'] ?? $defaults['hero']['image'],
            'alt'=>$saved['hero_alt'] ?? $defaults['hero']['alt'],
            'show_cards'=>$saved['show_hero_cards'] ?? 1,
            'cards'=>$saved['cards'] ?? $defaults['hero']['cards'],
        ]];
    }
    foreach ($defaults as $section => $sectionDefault) {
        if (!isset($saved[$section]) || !is_array($saved[$section])) continue;
        $defaults[$section] = array_replace($sectionDefault, array_intersect_key($saved[$section], $sectionDefault));
    }
    return $defaults;
}

$section = (string)($_POST['section'] ?? 'hero');
$allowedSections = ['hero','strip','applications','support','projects','why','cta'];
if (!in_array($section, $allowedSections, true)) {
    $_SESSION['admin_error'] = '未知的 Solutions 页面模块。';
    header('Location: solutions_page.php');
    exit;
}

try {
    $data = sol_save_merge_existing(web_get_block('solutions_page'));
    $allowedTabs = ['retail','hospitality','office','residential','museum-gallery','commercial'];
    $allowedIcons = ['shop','hotel','office','home','optics','years','oem','sample','finish','support'];
    $allowedSupportIcons = ['layout','beam','optical','finish','files','consult'];

    if ($section === 'hero') {
        $image = sol_save_clean($_POST['image'] ?? '');
        $uploaded = sol_save_upload('hero_image_upload', 'banners', $pdo, (int)$user['id'], 'Solutions hero image', sol_save_clean($_POST['alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $cards = [];
        foreach (sol_save_rows($_POST['cards'] ?? []) as $i => $row) {
            $label = sol_save_clean($row['label'] ?? '');
            if ($label === '') continue;
            $cardImage = sol_save_clean($row['image'] ?? '');
            $uploadedCard = sol_save_upload('card_image_upload_' . $i, 'projects', $pdo, (int)$user['id'], 'Solutions ' . $label, $label);
            if ($uploadedCard !== '') $cardImage = $uploadedCard;
            $tab = sol_save_clean($row['tab'] ?? 'retail');
            $icon = sol_save_clean($row['icon'] ?? 'shop');
            $cards[] = ['active'=>!empty($row['active'])?1:0,'key'=>sol_save_clean($row['key'] ?? $tab),'label'=>$label,'tab'=>in_array($tab,$allowedTabs,true)?$tab:'retail','icon'=>in_array($icon,$allowedIcons,true)?$icon:'shop','image'=>$cardImage];
        }
        $data['hero'] = ['eyebrow'=>sol_save_clean($_POST['eyebrow'] ?? ''),'title'=>sol_save_clean($_POST['title'] ?? ''),'intro'=>sol_save_clean($_POST['intro'] ?? ''),'image'=>$image ?: 'assets/img/hero/hero-track-systems.webp','alt'=>sol_save_clean($_POST['alt'] ?? ''),'show_cards'=>!empty($_POST['show_cards'])?1:0,'cards'=>$cards];
    } elseif ($section === 'strip') {
        $items = [];
        foreach (sol_save_rows($_POST['items'] ?? []) as $i => $row) {
            $label = sol_save_clean($row['label'] ?? '');
            if ($label === '') continue;
            $image = sol_save_clean($row['image'] ?? '');
            $uploaded = sol_save_upload('strip_image_upload_' . $i, 'projects', $pdo, (int)$user['id'], 'Solutions strip ' . $label, $label);
            if ($uploaded !== '') $image = $uploaded;
            $seoTitle = sol_save_clean($row['seo_title'] ?? '');
            $items[] = ['active'=>!empty($row['active'])?1:0,'key'=>sol_save_clean($row['key'] ?? ''),'label'=>$label,'seo_title'=>$seoTitle ?: ($label . ' Lighting Solutions'),'text'=>sol_save_clean($row['text'] ?? ''),'image'=>$image];
        }
        $target = sol_save_clean($_POST['button_target'] ?? 'retail');
        $data['strip'] = ['title'=>sol_save_clean($_POST['title'] ?? ''),'intro'=>sol_save_clean($_POST['intro'] ?? ''),'button_label'=>sol_save_clean($_POST['button_label'] ?? ''),'button_target'=>in_array($target,$allowedTabs,true)?$target:'retail','socials'=>sol_save_lines($_POST['socials'] ?? ''),'items'=>$items];
    } elseif ($section === 'applications') {
        $tabs = [];
        foreach (sol_save_rows($_POST['tabs'] ?? []) as $row) {
            $key = sol_save_clean($row['key'] ?? '');
            $label = sol_save_clean($row['label'] ?? '');
            if ($key === '' || $label === '') continue;
            $tabs[] = [
                'active'=>!empty($row['active'])?1:0,
                'key'=>$key,
                'label'=>$label,
                'recommend'=>array_slice(sol_save_lines($row['recommend'] ?? ''),0,8),
                'descriptions'=>sol_save_text_rows($row['descriptions'] ?? [], 8),
            ];
        }
        $data['applications'] = [
            'eyebrow'=>sol_save_clean($_POST['eyebrow'] ?? ''),
            'title'=>sol_save_clean($_POST['title'] ?? ''),
            'subtitle'=>sol_save_clean($_POST['subtitle'] ?? ''),
            'view_label'=>sol_save_clean($_POST['view_label'] ?? '') ?: 'VIEW PRODUCT',
            'default_card_text'=>sol_save_clean($_POST['default_card_text'] ?? '') ?: 'Professional architectural lighting family for project applications.',
            'tabs'=>$tabs,
        ];
    } elseif ($section === 'projects') {
        $items = [];
        foreach (sol_save_rows($_POST['items'] ?? []) as $i => $row) {
            $title = sol_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $image = sol_save_clean($row['image'] ?? '');
            $uploaded = sol_save_upload('project_image_upload_' . $i, 'projects', $pdo, (int)$user['id'], 'Solutions project ' . $title, $title);
            if ($uploaded !== '') $image = $uploaded;
            $items[] = ['active'=>!empty($row['active'])?1:0,'title'=>$title,'place'=>sol_save_clean($row['place'] ?? ''),'image'=>$image,'url'=>sol_save_clean($row['url'] ?? '')];
        }
        $data['projects'] = ['eyebrow'=>sol_save_clean($_POST['eyebrow'] ?? ''),'title'=>sol_save_clean($_POST['title'] ?? ''),'view_all_label'=>sol_save_clean($_POST['view_all_label'] ?? ''),'view_all_url'=>sol_save_clean($_POST['view_all_url'] ?? ''),'items'=>$items];
    } elseif ($section === 'support') {
        $image = sol_save_clean($_POST['image'] ?? '');
        $uploaded = sol_save_upload('support_image_upload', 'projects', $pdo, (int)$user['id'], 'Solutions support image', sol_save_clean($_POST['alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $items = [];
        foreach (sol_save_rows($_POST['items'] ?? []) as $row) {
            $title = sol_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $icon = sol_save_clean($row['icon'] ?? 'layout');
            $items[] = [
                'active'=>!empty($row['active'])?1:0,
                'icon'=>in_array($icon,$allowedSupportIcons,true)?$icon:'layout',
                'title'=>$title,
                'text'=>sol_save_clean($row['text'] ?? ''),
            ];
        }
        $data['support'] = [
            'eyebrow'=>sol_save_clean($_POST['eyebrow'] ?? ''),
            'title'=>sol_save_clean($_POST['title'] ?? ''),
            'intro'=>sol_save_clean($_POST['intro'] ?? ''),
            'image'=>$image ?: 'assets/img/projects/featured-office.webp',
            'alt'=>sol_save_clean($_POST['alt'] ?? ''),
            'items'=>$items,
        ];
    } elseif ($section === 'why') {
        $items = [];
        foreach (sol_save_rows($_POST['items'] ?? []) as $row) {
            $title = sol_save_clean($row['title'] ?? '');
            if ($title === '') continue;
            $icon = sol_save_clean($row['icon'] ?? 'support');
            $items[] = ['active'=>!empty($row['active'])?1:0,'title'=>$title,'text'=>sol_save_clean($row['text'] ?? ''),'icon'=>in_array($icon,$allowedIcons,true)?$icon:'support'];
        }
        $data['why'] = ['eyebrow'=>sol_save_clean($_POST['eyebrow'] ?? ''),'title'=>sol_save_clean($_POST['title'] ?? ''),'items'=>$items];
    } else {
        $image = sol_save_clean($_POST['image'] ?? '');
        $uploaded = sol_save_upload('cta_image_upload', 'banners', $pdo, (int)$user['id'], 'Solutions CTA image', sol_save_clean($_POST['alt'] ?? ''));
        if ($uploaded !== '') $image = $uploaded;
        $data['cta'] = ['eyebrow'=>sol_save_clean($_POST['eyebrow'] ?? ''),'title'=>sol_save_clean($_POST['title'] ?? ''),'intro'=>sol_save_clean($_POST['intro'] ?? ''),'image'=>$image,'alt'=>sol_save_clean($_POST['alt'] ?? ''),'primary_label'=>sol_save_clean($_POST['primary_label'] ?? ''),'primary_url'=>sol_save_clean($_POST['primary_url'] ?? ''),'secondary_label'=>sol_save_clean($_POST['secondary_label'] ?? ''),'secondary_url'=>sol_save_clean($_POST['secondary_url'] ?? '')];
    }

    web_save_block($pdo, 'solutions_page', $data, (int)$user['id']);
    web_public_cache_clear('solutions_v2');
    web_log($pdo, (int)$user['id'], 'update_content', 'solutions_page', $section, ['section'=>$section]);
    $_SESSION['admin_success'] = 'Solutions 页面模块已保存并发布。';
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: solutions_page.php?section=' . rawurlencode($section));
