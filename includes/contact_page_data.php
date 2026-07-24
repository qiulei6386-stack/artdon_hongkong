<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function artdon_contact_default_content(): array
{
    return [
        'hero' => [
            'breadcrumb' => 'Home > Contact',
            'title' => "Talk to Our\nLighting Team.",
            'description' => 'Tell us about your project and our team will get back to you within one working day.',
            'image' => 'assets/img/hero/hero-track-systems.webp',
            'image_alt' => 'Track lighting and wall illumination project scene',
            'is_active' => 1,
        ],
        'form' => [
            'title' => 'Send Us a Message',
            'description' => "Please provide the details below and we'll get back to you as soon as possible.",
            'button_text' => 'SEND MESSAGE →',
            'success_message' => 'Thank you. Your inquiry has been received.',
            'error_message' => 'The inquiry could not be submitted. Please try again or contact us by email.',
            'upload_max_mb' => 10,
            'allowed_file_types' => 'PDF,DWG,JPG,PNG',
            'fields' => [
                ['key'=>'name','label'=>'Name','show'=>1,'required'=>1,'sort_order'=>10],
                ['key'=>'email','label'=>'Work Email','show'=>1,'required'=>1,'sort_order'=>20],
                ['key'=>'company','label'=>'Company','show'=>1,'required'=>0,'sort_order'=>30],
                ['key'=>'country','label'=>'Country / Region','show'=>1,'required'=>1,'sort_order'=>40],
                ['key'=>'subject','label'=>'Subject','show'=>1,'required'=>1,'sort_order'=>50],
                ['key'=>'message','label'=>'Project Requirements','show'=>1,'required'=>1,'sort_order'=>60],
                ['key'=>'upload','label'=>'Upload Files Optional','show'=>1,'required'=>0,'sort_order'=>70],
                ['key'=>'privacy','label'=>'Privacy Policy','show'=>1,'required'=>1,'sort_order'=>80],
            ],
            'is_active' => 1,
        ],
        'contact_info' => [
            'title' => 'Contact Information',
            'items' => [
                ['icon'=>'mail','title'=>'Email','text'=>'sales@artdon.cn','url'=>'mailto:sales@artdon.cn','sort_order'=>10,'is_active'=>1],
                ['icon'=>'phone','title'=>'WhatsApp','text'=>'+86 139 2533 2972','url'=>'https://wa.me/8613925332972','sort_order'=>20,'is_active'=>1],
                ['icon'=>'pin','title'=>'Address','text'=>"No.15 Zhibie 3rd Street,\nYunin Dongsheng, Xiaolan Town,\nZhongshan City, Guangdong,\nChina (Post Code: 528414)",'url'=>'','sort_order'=>30,'is_active'=>1],
                ['icon'=>'clock','title'=>'Business Hours','text'=>"Monday - Friday\n9:00 AM - 6:00 PM (GMT+8)",'url'=>'','sort_order'=>40,'is_active'=>1],
            ],
            'is_active' => 1,
        ],
        'benefits' => [
            'items' => [
                ['icon'=>'quality','title'=>'Reliable Quality','text'=>'Strict quality control and full certifications.','sort_order'=>10,'is_active'=>1],
                ['icon'=>'team','title'=>'Expert Team','text'=>'Professional lighting experts at your service.','sort_order'=>20,'is_active'=>1],
                ['icon'=>'response','title'=>'Quick Response','text'=>'We respond within one working day.','sort_order'=>30,'is_active'=>1],
                ['icon'=>'global','title'=>'Global Experience','text'=>'Serving clients in 80+ countries worldwide.','sort_order'=>40,'is_active'=>1],
            ],
            'is_active' => 1,
        ],
        'cta' => [
            'title' => 'Need help choosing lighting products?',
            'description' => 'Share your requirements and our team will recommend the right lighting solution.',
            'button_text' => 'GET A QUOTE →',
            'button_url' => 'contact.php',
            'image' => 'assets/img/hero/hero-technical-downloads.webp',
            'image_alt' => 'Artdon lighting support',
            'is_active' => 0,
        ],
    ];
}

function artdon_contact_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_contact_page_settings (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(80) NOT NULL,
        title VARCHAR(190) NOT NULL DEFAULT '',
        content_json TEXT NULL,
        seo_title VARCHAR(255) NOT NULL DEFAULT '',
        seo_description TEXT NULL,
        seo_keywords VARCHAR(500) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_inquiry_attachments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        inquiry_id INT UNSIGNED NOT NULL DEFAULT 0,
        original_name VARCHAR(255) NOT NULL DEFAULT '',
        file_path VARCHAR(500) NOT NULL DEFAULT '',
        file_size INT UNSIGNED NOT NULL DEFAULT 0,
        file_type VARCHAR(40) NOT NULL DEFAULT '',
        mime_type VARCHAR(120) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_inquiry (inquiry_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function artdon_contact_seed(PDO $pdo): void
{
    artdon_contact_migrate($pdo);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM web_contact_page_settings")->fetchColumn();
    if ($count > 0) return;
    $content = artdon_contact_default_content();
    $stmt = $pdo->prepare('INSERT INTO web_contact_page_settings (slug,title,content_json,seo_title,seo_description,seo_keywords) VALUES (?,?,?,?,?,?)');
    $stmt->execute([
        'contact',
        'Contact',
        json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'Contact Artdon Lighting | Project Support & Quotations',
        'Contact Artdon Lighting for commercial lighting quotations, custom luminaires, samples, IES files and technical project support.',
        'contact Artdon Lighting, lighting quotation, project support',
    ]);
}

function artdon_contact_decode(?string $json): array
{
    $data = $json ? json_decode($json, true) : [];
    return is_array($data) ? $data : [];
}

function artdon_contact_sort_items(array $items, bool $activeOnly = true): array
{
    $items = array_values(array_filter($items, static fn($item): bool => is_array($item) && (!$activeOnly || !array_key_exists('is_active', $item) || !empty($item['is_active']))));
    usort($items, static fn(array $a, array $b): int => ((int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0)));
    return $items;
}

function artdon_contact_merge_content(array $saved): array
{
    $defaults = artdon_contact_default_content();
    $content = array_replace_recursive($defaults, $saved);
    foreach (['form','contact_info','benefits'] as $module) {
        if (isset($saved[$module]) && is_array($saved[$module]) && array_key_exists('items', $saved[$module])) {
            $content[$module] = array_replace((array)$defaults[$module], $saved[$module]);
            $content[$module]['items'] = is_array($saved[$module]['items'] ?? null) ? $saved[$module]['items'] : [];
        }
    }
    if (isset($saved['form']['fields']) && is_array($saved['form']['fields'])) {
        $content['form']['fields'] = $saved['form']['fields'];
    }
    return $content;
}

function artdon_contact_page(PDO $pdo): array
{
    artdon_contact_seed($pdo);
    $stmt = $pdo->prepare("SELECT * FROM web_contact_page_settings WHERE slug='contact' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch() ?: [];
    $content = artdon_contact_merge_content(artdon_contact_decode((string)($row['content_json'] ?? '')));
    return [
        'id' => (int)($row['id'] ?? 0),
        'slug' => 'contact',
        'title' => (string)($row['title'] ?? 'Contact'),
        'content' => $content,
        'seo_title' => (string)($row['seo_title'] ?? 'Contact Artdon Lighting | Project Support & Quotations'),
        'seo_description' => (string)($row['seo_description'] ?? 'Contact Artdon Lighting for commercial lighting quotations, custom luminaires, samples, IES files and technical project support.'),
        'seo_keywords' => (string)($row['seo_keywords'] ?? ''),
    ];
}

function artdon_contact_update(PDO $pdo, array $content, string $seoTitle, string $seoDescription, string $seoKeywords): void
{
    artdon_contact_seed($pdo);
    $stmt = $pdo->prepare("UPDATE web_contact_page_settings SET content_json=?, seo_title=?, seo_description=?, seo_keywords=? WHERE slug='contact'");
    $stmt->execute([
        json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $seoTitle,
        $seoDescription,
        $seoKeywords,
    ]);
}
