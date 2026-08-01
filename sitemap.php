<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
if (is_file(__DIR__ . '/includes/product_hierarchy.php')) require_once __DIR__ . '/includes/product_hierarchy.php';
if (is_file(__DIR__ . '/includes/pretty_urls_v71868.php')) require_once __DIR__ . '/includes/pretty_urls_v71868.php';
if (is_file(__DIR__ . '/includes/project_detail_data.php')) require_once __DIR__ . '/includes/project_detail_data.php';
if (is_file(__DIR__ . '/includes/resources_blog_data.php')) require_once __DIR__ . '/includes/resources_blog_data.php';
if (is_file(__DIR__ . '/includes/retail_application_data.php')) require_once __DIR__ . '/includes/retail_application_data.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$pdo = artdon_v710_db();

header('Content-Type: application/xml; charset=UTF-8');
echo artdon_v710_sitemap_xml(artdon_v710_sitemap_urls($site, $pdo, $content));
