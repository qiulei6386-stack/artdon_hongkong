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

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$prettySlug = '';
if (preg_match('~^/projects/([^/]+)/?$~i', $requestPath, $prettyMatch)) {
    $prettySlug = artdon_project_slug((string)$prettyMatch[1]);
}
$slug = artdon_project_slug($prettySlug !== '' ? $prettySlug : (string)($_GET['slug'] ?? ($_GET['project'] ?? '')));
$isPrettyProjectUrl = $prettySlug !== '';
$project = null;
$redirectSlug = '';

try {
    $dbError = null;
    $pdo = web_db($dbError);
    if ($pdo instanceof PDO && $slug !== '') {
        $redirectSlug = artdon_project_redirect_target($pdo, $slug);
        if ($redirectSlug !== '') {
            $target = artdon_project_find($pdo, $redirectSlug);
            if ($target) {
                header('Location: ' . artdon_project_pretty_url((string)$target['slug']), true, 301);
                exit;
            }
        }
        $project = artdon_project_find($pdo, $slug);
        if ($project && !$isPrettyProjectUrl) {
            header('Location: ' . artdon_project_pretty_url((string)$project['slug']), true, 301);
            exit;
        }
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
