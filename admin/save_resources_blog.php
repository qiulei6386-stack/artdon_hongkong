<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/upload.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/resources_blog_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_blog_seed($pdo);
$user = web_require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: resources_blog.php'); exit; }
if (!web_verify_csrf($_POST['csrf'] ?? null)) {
    $_SESSION['admin_error'] = '页面已过期，请刷新后重试。';
    header('Location: resources_blog.php');
    exit;
}

function rb_save_clean(mixed $value): string { return trim((string)$value); }
function rb_save_upload(PDO $pdo, array $user, string $title, string $alt, string $field = 'cover_upload', string $usage = 'articles'): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    return web_upload_file($_FILES[$field], 'image', $pdo, (int)$user['id'], $title, $alt, $usage);
}
function rb_save_paragraphs(string $text): array
{
    $parts = preg_split('/\R{1,}/u', trim($text)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== '') $out[] = $part;
    }
    return $out;
}
function rb_save_blocks(string $json, string $title, string $summary, string $image): string
{
    $data = json_decode($json, true);
    if (!is_array($data)) $data = artdon_resource_blog_default_body(['title'=>$title,'summary'=>$summary,'image'=>$image]);
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$action = (string)($_POST['action'] ?? 'save');
$id = (int)($_POST['id'] ?? 0);

try {
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM web_resource_blog_articles WHERE id=?')->execute([$id]);
        web_public_cache_clear('');
        $_SESSION['admin_success'] = '文章已删除。';
        header('Location: resources_blog.php');
        exit;
    }

    $title = rb_save_clean($_POST['title'] ?? '');
    if ($title === '') throw new RuntimeException('文章标题不能为空。');
    $slug = artdon_resource_blog_slug(rb_save_clean($_POST['slug'] ?? $title));
    $category = rb_save_clean($_POST['category'] ?? 'lighting-knowledge');
    if (!isset(artdon_resource_blog_categories()[$category])) $category = 'lighting-knowledge';
    $summary = rb_save_clean($_POST['summary'] ?? '');
    $image = rb_save_clean($_POST['cover_image'] ?? '');
    $alt = rb_save_clean($_POST['cover_alt'] ?? '') ?: $title;
    $uploaded = rb_save_upload($pdo, $user, $title, $alt);
    if ($uploaded !== '') $image = $uploaded;
    $data = json_decode((string)($_POST['content_json'] ?? ''), true);
    if (!is_array($data)) $data = artdon_resource_blog_default_body(['title'=>$title,'summary'=>$summary,'image'=>$image]);
    if (isset($_POST['sections']) && is_array($_POST['sections'])) {
        $sections = [];
        foreach ($_POST['sections'] as $i => $row) {
            if (!is_array($row)) continue;
            $sectionTitle = rb_save_clean($row['title'] ?? '');
            $paragraphs = rb_save_paragraphs((string)($row['paragraphs'] ?? ''));
            if ($sectionTitle === '' && !$paragraphs) continue;
            $number = rb_save_clean($row['number'] ?? '') ?: str_pad((string)(count($sections) + 1), 2, '0', STR_PAD_LEFT);
            $idText = rb_save_clean($row['id'] ?? '') ?: ($number . '-' . $sectionTitle);
            $idText = artdon_resource_blog_slug($idText) ?: ('section-' . $number);
            $sections[] = [
                'id' => $idText,
                'number' => $number,
                'title' => $sectionTitle,
                'paragraphs' => $paragraphs ?: [''],
            ];
        }
        if ($sections) $data['sections'] = $sections;
    }
    if (isset($_POST['key_takeaways_text'])) {
        $data['key_takeaways'] = rb_save_paragraphs((string)($_POST['key_takeaways_text'] ?? ''));
    }
    $data['card_grid_title'] = rb_save_clean($_POST['card_grid_title'] ?? ($data['card_grid_title'] ?? ''));
    $data['cards_after_section'] = rb_save_clean($_POST['cards_after_section'] ?? ($data['cards_after_section'] ?? '02')) ?: '02';
    $data['table_title'] = rb_save_clean($_POST['table_title'] ?? ($data['table_title'] ?? ''));
    $data['table_after_section'] = rb_save_clean($_POST['table_after_section'] ?? ($data['table_after_section'] ?? '04')) ?: '04';
    if (isset($_POST['table_headers']) && is_array($_POST['table_headers'])) {
        $headers = [];
        foreach ($_POST['table_headers'] as $header) {
            $headers[] = rb_save_clean($header);
        }
        $data['table_headers'] = array_pad(array_slice($headers, 0, 3), 3, '');
    }
    $data['cta_after_section'] = rb_save_clean($_POST['cta_after_section'] ?? ($data['cta_after_section'] ?? '03')) ?: '03';
    if (isset($_POST['beam_cards']) && is_array($_POST['beam_cards'])) {
        $cards = [];
        foreach ($_POST['beam_cards'] as $i => $row) {
            if (!is_array($row)) continue;
            $angle = rb_save_clean($row['angle'] ?? '');
            $cardTitle = rb_save_clean($row['title'] ?? '');
            $text = rb_save_clean($row['text'] ?? '');
            $cardImage = rb_save_clean($row['image'] ?? '');
            $cardAlt = rb_save_clean($row['image_alt'] ?? '');
            $field = 'beam_cards_file';
            if (isset($_FILES[$field]['name'][$i]) && ($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file = [
                    'name' => $_FILES[$field]['name'][$i],
                    'type' => $_FILES[$field]['type'][$i] ?? '',
                    'tmp_name' => $_FILES[$field]['tmp_name'][$i],
                    'error' => $_FILES[$field]['error'][$i],
                    'size' => $_FILES[$field]['size'][$i],
                ];
                $uploadedCard = web_upload_file($file, 'image', $pdo, (int)$user['id'], $title . ' beam card', trim($angle . ' ' . $cardTitle), 'articles');
                if ($uploadedCard !== '') $cardImage = $uploadedCard;
            }
            if ($cardAlt === '') $cardAlt = trim($angle . ' ' . $cardTitle);
            if ($angle === '' && $cardTitle === '' && $text === '' && $cardImage === '') continue;
            $cards[] = ['angle'=>$angle, 'title'=>$cardTitle, 'text'=>$text, 'image'=>$cardImage, 'image_alt'=>$cardAlt];
        }
        $data['beam_cards'] = $cards;
    }
    if (isset($_POST['mounting_table']) && is_array($_POST['mounting_table'])) {
        $rows = [];
        foreach ($_POST['mounting_table'] as $row) {
            if (!is_array($row)) continue;
            $height = rb_save_clean($row['height'] ?? '');
            $beam = rb_save_clean($row['beam'] ?? '');
            $use = rb_save_clean($row['use'] ?? '');
            if ($height === '' && $beam === '' && $use === '') continue;
            $rows[] = ['height'=>$height, 'beam'=>$beam, 'use'=>$use];
        }
        $data['mounting_table'] = $rows;
    }
    if (isset($_POST['mid_cta']) && is_array($_POST['mid_cta'])) {
        $data['mid_cta'] = [
            'title'=>rb_save_clean($_POST['mid_cta']['title'] ?? ''),
            'text'=>rb_save_clean($_POST['mid_cta']['text'] ?? ''),
            'button_text'=>rb_save_clean($_POST['mid_cta']['button_text'] ?? ''),
            'button_url'=>rb_save_clean($_POST['mid_cta']['button_url'] ?? ''),
        ];
    }
    if (isset($_POST['project_image']) || !empty($_FILES['project_upload'])) {
        if (!isset($data['project_example']) || !is_array($data['project_example'])) $data['project_example'] = [];
        $data['project_example']['title'] = rb_save_clean($_POST['project_title'] ?? ($data['project_example']['title'] ?? ''));
        $data['project_example']['text'] = rb_save_clean($_POST['project_text'] ?? ($data['project_example']['text'] ?? ''));
        $data['project_example']['params'] = rb_save_paragraphs((string)($_POST['project_params'] ?? implode("\n", (array)($data['project_example']['params'] ?? []))));
        $data['project_example']['url'] = rb_save_clean($_POST['project_url'] ?? ($data['project_example']['url'] ?? ''));
        $projectImage = rb_save_clean($_POST['project_image'] ?? ($data['project_example']['image'] ?? ''));
        $projectAlt = rb_save_clean($_POST['project_image_alt'] ?? ($data['project_example']['image_alt'] ?? ''));
        $projectUploaded = rb_save_upload($pdo, $user, $title . ' project example', $projectAlt ?: $title, 'project_upload', 'projects');
        if ($projectUploaded !== '') $projectImage = $projectUploaded;
        $data['project_example']['image'] = $projectImage;
        $data['project_example']['image_alt'] = $projectAlt;
    } elseif (isset($_POST['project_title']) || isset($_POST['project_text']) || isset($_POST['project_params']) || isset($_POST['project_url'])) {
        if (!isset($data['project_example']) || !is_array($data['project_example'])) $data['project_example'] = [];
        $data['project_example']['title'] = rb_save_clean($_POST['project_title'] ?? ($data['project_example']['title'] ?? ''));
        $data['project_example']['text'] = rb_save_clean($_POST['project_text'] ?? ($data['project_example']['text'] ?? ''));
        $data['project_example']['params'] = rb_save_paragraphs((string)($_POST['project_params'] ?? implode("\n", (array)($data['project_example']['params'] ?? []))));
        $data['project_example']['url'] = rb_save_clean($_POST['project_url'] ?? ($data['project_example']['url'] ?? ''));
    }
    $blocks = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $values = [
        $slug, $title, $category, $image, $alt, $summary, $blocks,
        rb_save_clean($_POST['author'] ?? 'Artdon Lighting Team') ?: 'Artdon Lighting Team',
        rb_save_clean($_POST['publish_date'] ?? ''),
        rb_save_clean($_POST['read_time'] ?? ''),
        rb_save_clean($_POST['seo_title'] ?? '') ?: ($title . ' | Artdon Lighting'),
        rb_save_clean($_POST['seo_description'] ?? '') ?: $summary,
        rb_save_clean($_POST['seo_keywords'] ?? ''),
        (int)($_POST['sort_order'] ?? 10),
        !empty($_POST['is_published']) ? 1 : 0,
    ];
    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE web_resource_blog_articles SET slug=?, title=?, category=?, cover_image=?, cover_alt=?, summary=?, content_json=?, author=?, publish_date=?, read_time=?, seo_title=?, seo_description=?, seo_keywords=?, sort_order=?, is_published=? WHERE id=?');
        $stmt->execute([...$values, $id]);
        $_SESSION['admin_success'] = 'Blog 文章已保存。';
    } else {
        $stmt = $pdo->prepare('INSERT INTO web_resource_blog_articles (slug,title,category,cover_image,cover_alt,summary,content_json,author,publish_date,read_time,seo_title,seo_description,seo_keywords,sort_order,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute($values);
        $_SESSION['admin_success'] = 'Blog 文章已新增。';
    }
    web_public_cache_clear('');
    web_log($pdo, (int)$user['id'], 'update_content', 'resources_blog', $slug, ['id'=>$id, 'category'=>$category]);
} catch (Throwable $e) {
    $_SESSION['admin_error'] = '保存失败：' . $e->getMessage();
}

header('Location: resources_blog.php?slug=' . rawurlencode($slug ?? ''));
