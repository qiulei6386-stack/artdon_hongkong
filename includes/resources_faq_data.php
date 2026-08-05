<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function artdon_resource_faq_categories(): array
{
    return [
        'product' => 'Product',
        'technical' => 'Technical',
        'installation' => 'Installation',
        'orders-delivery' => 'Orders & Delivery',
        'oem-customization' => 'OEM & Customization',
        'warranty-support' => 'Warranty & Support',
        'about-artdon' => 'About Artdon',
    ];
}

function artdon_resource_faq_default_items(): array
{
    return [
        ['question'=>'What is the typical lead time for orders?','answer'=>'Standard order lead time depends on product type, quantity and customization requirements. For most regular products, our team will confirm the latest production schedule after receiving your order details.','category'=>'orders-delivery','sort_order'=>10],
        ['question'=>'Can I get a sample before placing a bulk order?','answer'=>'Yes. Samples can be arranged for product evaluation, lighting tests and project mockups before bulk order confirmation.','category'=>'orders-delivery','sort_order'=>20],
        ['question'=>'What is your warranty policy?','answer'=>'Warranty terms depend on the product series and driver configuration. Our team can provide the applicable warranty information together with the quotation and datasheet.','category'=>'warranty-support','sort_order'=>30],
        ['question'=>'Do you provide custom lighting solutions?','answer'=>'Yes. We support OEM and ODM customization including housing design, beam angle, finishes, branding, packaging, driver options, CCT, dimming and accessories.','category'=>'oem-customization','sort_order'=>40],
        ['question'=>'What are the payment terms?','answer'=>'Payment terms are confirmed according to order type, customer profile and project requirements. Please contact our sales team for the latest terms.','category'=>'orders-delivery','sort_order'=>50],
        ['question'=>'What certifications do your products have?','answer'=>'Available certifications vary by product family. We can provide certificates, test reports and technical documents for suitable products when required.','category'=>'technical','sort_order'=>60],
        ['question'=>'How do I choose the right beam angle?','answer'=>'Beam angle should be selected according to ceiling height, target area, desired contrast and application. Narrow beams are useful for accent lighting, while wider beams are better for ambient lighting.','category'=>'technical','sort_order'=>70],
        ['question'=>'What is the difference between track lighting and magnetic track lighting?','answer'=>'Traditional track lighting uses a standard track and adapter system. Magnetic track lighting uses low-voltage magnetic modules that can be repositioned easily for flexible layouts.','category'=>'product','sort_order'=>80],
        ['question'=>'Do your lights support dimming? What dimming options are available?','answer'=>'Many products support dimming. Common options include 0-10V, DALI, TRIAC and smart control systems depending on the driver and product configuration.','category'=>'technical','sort_order'=>90],
        ['question'=>'Can your products be used in outdoor environments?','answer'=>'Outdoor use requires suitable IP rating, material, finish and installation conditions. Please choose products designed for outdoor or damp locations.','category'=>'product','sort_order'=>100],
        ['question'=>'How do I install your track lighting system?','answer'=>'Installation methods depend on track type and ceiling condition. We can provide installation guides and technical support for the selected system.','category'=>'installation','sort_order'=>110],
        ['question'=>'Who is Artdon Lighting Limited?','answer'=>'Artdon Lighting Limited is a professional architectural lighting manufacturer based in Zhongshan, China. We focus on LED downlights, track lights, magnetic systems, linear lighting and project-oriented lighting solutions for retail, hospitality, museum, office and commercial spaces.','category'=>'about-artdon','sort_order'=>10],
        ['question'=>'Where is Artdon located?','answer'=>'Artdon is located in Zhongshan, Guangdong, China, one of the major lighting manufacturing regions. Our team supports international projects with product selection, technical documents, customization and export coordination.','category'=>'about-artdon','sort_order'=>20],
        ['question'=>'What types of projects does Artdon support?','answer'=>'Artdon supports retail stores, hotels, museums, galleries, offices, residential spaces and commercial projects. Our product range is designed for architectural lighting applications where visual comfort, beam control and reliable performance are important.','category'=>'about-artdon','sort_order'=>30],
        ['question'=>'Does Artdon provide OEM or ODM services?','answer'=>'Yes. Artdon provides OEM and ODM support for architectural lighting products, including product customization, finish options, optical configuration, branding, packaging and project-specific requirements.','category'=>'about-artdon','sort_order'=>40],
        ['question'=>'How can I contact Artdon for a project?','answer'=>'You can contact Artdon through the inquiry form, email or WhatsApp. Share your project requirements, product interests or technical questions, and our team will route the inquiry to the appropriate sales or technical contact.','category'=>'about-artdon','sort_order'=>50],
    ];
}

function artdon_resource_faq_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_resource_faqs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        question VARCHAR(500) NOT NULL DEFAULT '',
        answer TEXT NULL,
        category VARCHAR(80) NOT NULL DEFAULT '',
        seo_tag VARCHAR(160) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_category_sort (category, is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE web_resource_faqs ADD UNIQUE KEY uniq_question (question(191))"); } catch (Throwable $e) {}
}

function artdon_resource_faq_seeded(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM web_system_settings WHERE setting_key='resources_faq_seeded' LIMIT 1");
        $stmt->execute();
        return trim((string)$stmt->fetchColumn()) === '1';
    } catch (Throwable $e) {
        return false;
    }
}

function artdon_resource_faq_mark_seeded(PDO $pdo): void
{
    try {
        $pdo->prepare("INSERT INTO web_system_settings (setting_key,setting_value,is_secret) VALUES ('resources_faq_seeded','1',0) ON DUPLICATE KEY UPDATE setting_value='1', updated_at=CURRENT_TIMESTAMP")->execute();
    } catch (Throwable $e) {}
}

function artdon_resource_faq_seed(PDO $pdo): void
{
    artdon_resource_faq_migrate($pdo);
    try { $pdo->query("SELECT GET_LOCK('artdon_resource_faq_seed', 8)")->fetchColumn(); } catch (Throwable $e) {}
    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_resource_faqs')->fetchColumn();
    if ($count > 0) {
        artdon_resource_faq_mark_seeded($pdo);
        try { $pdo->query("SELECT RELEASE_LOCK('artdon_resource_faq_seed')")->fetchColumn(); } catch (Throwable $e) {}
        return;
    }
    if (artdon_resource_faq_seeded($pdo)) {
        try { $pdo->query("SELECT RELEASE_LOCK('artdon_resource_faq_seed')")->fetchColumn(); } catch (Throwable $e) {}
        return;
    }
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_resource_faqs (question,answer,category,seo_tag,sort_order,is_active,is_featured) VALUES (?,?,?,?,?,1,0)');
    foreach (artdon_resource_faq_default_items() as $item) {
        $stmt->execute([$item['question'], $item['answer'], $item['category'], '', (int)$item['sort_order']]);
    }
    artdon_resource_faq_mark_seeded($pdo);
    try { $pdo->query("SELECT RELEASE_LOCK('artdon_resource_faq_seed')")->fetchColumn(); } catch (Throwable $e) {}
}

function artdon_resource_faq_items(PDO $pdo, bool $activeOnly = true): array
{
    artdon_resource_faq_seed($pdo);
    $sql = 'SELECT * FROM web_resource_faqs' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY sort_order ASC, id ASC';
    $rows = $pdo->query($sql)->fetchAll() ?: [];
    return array_map(static function(array $row): array {
        return [
            'id'=>(int)($row['id'] ?? 0),
            'question'=>(string)($row['question'] ?? ''),
            'answer'=>(string)($row['answer'] ?? ''),
            'category'=>(string)($row['category'] ?? 'product'),
            'category_label'=>artdon_resource_faq_categories()[(string)($row['category'] ?? '')] ?? 'Product',
            'seo_tag'=>(string)($row['seo_tag'] ?? ''),
            'sort_order'=>(int)($row['sort_order'] ?? 0),
            'is_active'=>(int)($row['is_active'] ?? 1) === 1,
            'is_featured'=>(int)($row['is_featured'] ?? 0) === 1,
        ];
    }, $rows);
}
