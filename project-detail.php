<?php
/** Artdon Lighting Project Detail */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/project_detail_data.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$site = function_exists('web_get_block') ? (array)web_get_block('site') : [];
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$siteUrl = rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');

$slug = artdon_project_slug((string)($_GET['slug'] ?? ($_GET['project'] ?? '')));
$project = null;

try {
    $dbError = null;
    $pdo = web_db($dbError);
    if ($pdo instanceof PDO && $slug !== '') {
        $project = artdon_project_find($pdo, $slug);
    }
} catch (Throwable $e) {
    $project = null;
}

if (!$project && $slug !== '') {
    foreach (artdon_projects_list() as $candidate) {
        if ((string)($candidate['slug'] ?? '') === $slug) {
            $candidate['url'] = 'project-detail.php?slug=' . rawurlencode($slug);
            $candidate['detail_url'] = $candidate['url'];
            $project = $candidate;
            break;
        }
    }
}

if (!$project) {
    http_response_code(404);
    $fallback = artdon_projects_list()[0] ?? ['title'=>'Lighting Project','slug'=>'lighting-project'];
    $fallback['url'] = 'project-detail.php?slug=' . rawurlencode((string)($fallback['slug'] ?? 'lighting-project'));
    $fallback['detail_url'] = $fallback['url'];
    $project = $fallback;
}

$inquiryBlock = function_exists('web_get_block') ? (array)web_get_block('inquiry') : [];
require __DIR__ . '/includes/project_detail_template.php';
