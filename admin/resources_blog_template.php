<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/public_cache.php';
require_once dirname(__DIR__) . '/includes/resources_blog_data.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
artdon_resource_blog_seed($pdo);
$user = web_require_admin($pdo);

function rb_template_clean(mixed $value): string { return trim((string)$value); }

function rb_template_article_payload(array $article): array
{
    return [
        'template_version' => 1,
        'type' => 'artdon_resource_blog_article',
        'article' => [
            'title' => (string)($article['title'] ?? ''),
            'slug' => (string)($article['slug'] ?? ''),
            'category' => (string)($article['category'] ?? 'lighting-knowledge'),
            'cover_image' => (string)($article['image'] ?? ''),
            'cover_alt' => (string)($article['alt'] ?? ''),
            'summary' => (string)($article['summary'] ?? ''),
            'author' => (string)($article['author'] ?? 'Artdon Lighting Team'),
            'publish_date' => (string)($article['date'] ?? ''),
            'read_time' => (string)($article['read_time'] ?? ''),
            'sort_order' => (int)($article['sort_order'] ?? 10),
            'is_published' => !empty($article['is_published']) ? 1 : 0,
            'seo_title' => (string)($article['seo_title'] ?? ''),
            'seo_description' => (string)($article['seo_description'] ?? ''),
            'seo_keywords' => (string)($article['seo_keywords'] ?? ''),
            'content' => is_array($article['blocks'] ?? null) ? $article['blocks'] : [],
        ],
    ];
}

function rb_template_excel_cell(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rb_template_join_lines(array $items): string
{
    $out = [];
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item !== '') $out[] = $item;
    }
    return implode("\n", $out);
}

function rb_template_article_excel_row(array $article): array
{
    $content = is_array($article['blocks'] ?? null) ? $article['blocks'] : [];
    $sections = is_array($content['sections'] ?? null) ? array_values($content['sections']) : [];
    $cards = is_array($content['beam_cards'] ?? null) ? array_values($content['beam_cards']) : [];
    $tableRows = is_array($content['mounting_table'] ?? null) ? array_values($content['mounting_table']) : [];
    $tableHeaders = is_array($content['table_headers'] ?? null) ? array_values($content['table_headers']) : ['Column 1', 'Column 2', 'Column 3'];
    $tableHeaders = array_pad(array_slice(array_map('strval', $tableHeaders), 0, 3), 3, '');
    $midCta = is_array($content['mid_cta'] ?? null) ? $content['mid_cta'] : [];
    $project = is_array($content['project_example'] ?? null) ? $content['project_example'] : [];

    $row = [
        'category' => (string)($article['category'] ?? ''),
        'title' => (string)($article['title'] ?? ''),
        'slug' => (string)($article['slug'] ?? ''),
        'summary' => (string)($article['summary'] ?? ''),
        'author' => (string)($article['author'] ?? ''),
        'publish_date' => (string)($article['date'] ?? ''),
        'read_time' => (string)($article['read_time'] ?? ''),
        'cover_image' => (string)($article['image'] ?? ''),
        'cover_alt' => (string)($article['alt'] ?? ''),
        'seo_title' => (string)($article['seo_title'] ?? ''),
        'seo_description' => (string)($article['seo_description'] ?? ''),
        'seo_keywords' => (string)($article['seo_keywords'] ?? ''),
        'key_takeaways' => rb_template_join_lines((array)($content['key_takeaways'] ?? [])),
    ];
    for ($i = 0; $i < 5; $i++) {
        $section = is_array($sections[$i] ?? null) ? $sections[$i] : [];
        $n = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
        $row['section_' . $n . '_title'] = (string)($section['title'] ?? '');
        $row['section_' . $n . '_content'] = rb_template_join_lines((array)($section['paragraphs'] ?? []));
    }
    $row['card_grid_title'] = (string)($content['card_grid_title'] ?? '');
    $row['cards_after_section'] = (string)($content['cards_after_section'] ?? '');
    for ($i = 0; $i < 4; $i++) {
        $card = is_array($cards[$i] ?? null) ? $cards[$i] : [];
        $n = (string)($i + 1);
        $row['card_' . $n . '_angle'] = (string)($card['angle'] ?? '');
        $row['card_' . $n . '_title'] = (string)($card['title'] ?? '');
        $row['card_' . $n . '_text'] = (string)($card['text'] ?? '');
        $row['card_' . $n . '_image'] = (string)($card['image'] ?? '');
    }
    $row['table_title'] = (string)($content['table_title'] ?? '');
    $row['table_after_section'] = (string)($content['table_after_section'] ?? '');
    $row['table_header_1'] = $tableHeaders[0];
    $row['table_header_2'] = $tableHeaders[1];
    $row['table_header_3'] = $tableHeaders[2];
    for ($i = 0; $i < 3; $i++) {
        $tableRow = is_array($tableRows[$i] ?? null) ? $tableRows[$i] : [];
        $n = (string)($i + 1);
        $row['table_row_' . $n . '_col_1'] = (string)($tableRow['height'] ?? '');
        $row['table_row_' . $n . '_col_2'] = (string)($tableRow['beam'] ?? '');
        $row['table_row_' . $n . '_col_3'] = (string)($tableRow['use'] ?? '');
    }
    $row['mid_cta_title'] = (string)($midCta['title'] ?? '');
    $row['mid_cta_text'] = (string)($midCta['text'] ?? '');
    $row['mid_cta_button_text'] = (string)($midCta['button_text'] ?? '');
    $row['mid_cta_button_url'] = (string)($midCta['button_url'] ?? '');
    $row['cta_after_section'] = (string)($content['cta_after_section'] ?? '');
    $row['project_example_title'] = (string)($project['title'] ?? '');
    $row['project_example_text'] = (string)($project['text'] ?? '');
    $row['project_example_params'] = rb_template_join_lines((array)($project['params'] ?? []));
    $row['project_example_image'] = (string)($project['image'] ?? '');
    $row['project_example_image_alt'] = (string)($project['image_alt'] ?? '');
    $row['project_example_url'] = (string)($project['url'] ?? '');
    $row['is_published'] = !empty($article['is_published']) ? '1' : '0';
    $row['sort_order'] = (string)($article['sort_order'] ?? 10);
    return $row;
}

function rb_template_value(array $article, string $key, mixed $fallback = ''): mixed
{
    return array_key_exists($key, $article) ? $article[$key] : $fallback;
}

function rb_template_is_media_path(string $value): bool
{
    $value = trim($value);
    if ($value === '') return false;
    if (preg_match('#^(?:https?:)?//#i', $value)) return true;
    return (bool)preg_match('#^(?:uploads/|assets/|images/|/uploads/|/assets/).+\.(?:jpe?g|png|webp|gif|svg|avif|bmp)(?:[?#].*)?$#i', $value);
}

function rb_template_keep_existing_media(string $incoming, string $existing = ''): string
{
    $incoming = rb_template_clean($incoming);
    if ($incoming === '') return $existing;
    return rb_template_is_media_path($incoming) ? $incoming : $existing;
}

function rb_template_section_ref(string $value, string $fallback = ''): string
{
    $value = rb_template_clean($value);
    if (preg_match('/^[1-9]$/', $value)) return '0' . $value;
    return $value !== '' ? $value : $fallback;
}

function rb_template_existing_article(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM web_resource_blog_articles WHERE slug=? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function rb_template_merge_content_media(array $content, array $existingContent): array
{
    if (isset($content['cards_after_section'])) {
        $content['cards_after_section'] = rb_template_section_ref((string)$content['cards_after_section'], (string)($existingContent['cards_after_section'] ?? '02'));
    }
    if (isset($content['table_after_section'])) {
        $content['table_after_section'] = rb_template_section_ref((string)$content['table_after_section'], (string)($existingContent['table_after_section'] ?? '04'));
    }
    if (isset($content['cta_after_section'])) {
        $content['cta_after_section'] = rb_template_section_ref((string)$content['cta_after_section'], (string)($existingContent['cta_after_section'] ?? '03'));
    }
    if (isset($content['beam_cards']) && is_array($content['beam_cards'])) {
        $existingCards = is_array($existingContent['beam_cards'] ?? null) ? array_values($existingContent['beam_cards']) : [];
        foreach ($content['beam_cards'] as $i => $card) {
            if (!is_array($card)) continue;
            $existingImage = (string)($existingCards[$i]['image'] ?? '');
            $content['beam_cards'][$i]['image'] = rb_template_keep_existing_media((string)($card['image'] ?? ''), $existingImage);
        }
    }
    if (isset($content['project_example']) && is_array($content['project_example'])) {
        $existingProject = is_array($existingContent['project_example'] ?? null) ? $existingContent['project_example'] : [];
        $content['project_example']['image'] = rb_template_keep_existing_media((string)($content['project_example']['image'] ?? ''), (string)($existingProject['image'] ?? ''));
        $incomingAlt = rb_template_clean($content['project_example']['image_alt'] ?? '');
        if ($incomingAlt === '' && !empty($existingProject['image_alt'])) $content['project_example']['image_alt'] = (string)$existingProject['image_alt'];
    }
    return $content;
}

function rb_template_import(PDO $pdo, array $payload, string $fallbackCategory): string
{
    $article = is_array($payload['article'] ?? null) ? $payload['article'] : $payload;
    if (!is_array($article)) throw new RuntimeException('模板格式不正确。');

    $categories = artdon_resource_blog_categories($pdo, false);
    $title = rb_template_clean(rb_template_value($article, 'title'));
    if ($title === '') throw new RuntimeException('模板缺少文章标题。');
    $slug = artdon_resource_blog_slug(rb_template_clean(rb_template_value($article, 'slug', $title)));
    if ($slug === '') $slug = artdon_resource_blog_slug($title);
    $existingRow = rb_template_existing_article($pdo, $slug);
    $existingContent = artdon_resource_blog_decode((string)($existingRow['content_json'] ?? ''));
    $category = artdon_resource_blog_slug(rb_template_clean(rb_template_value($article, 'category', $fallbackCategory)));
    if (!isset($categories[$category])) $category = isset($categories[$fallbackCategory]) ? $fallbackCategory : 'lighting-knowledge';

    $content = rb_template_value($article, 'content', rb_template_value($article, 'content_json', []));
    if (is_string($content)) $content = json_decode($content, true);
    if (!is_array($content)) $content = artdon_resource_blog_default_body([
        'title' => $title,
        'summary' => rb_template_clean(rb_template_value($article, 'summary')),
        'image' => rb_template_keep_existing_media((string)rb_template_value($article, 'cover_image', rb_template_value($article, 'image')), (string)($existingRow['cover_image'] ?? '')),
    ]);
    $content = rb_template_merge_content_media($content, $existingContent);

    $coverImage = rb_template_keep_existing_media(
        (string)rb_template_value($article, 'cover_image', rb_template_value($article, 'image')),
        (string)($existingRow['cover_image'] ?? '')
    );
    $coverAlt = rb_template_clean(rb_template_value($article, 'cover_alt', rb_template_value($article, 'alt', '')));
    if ($coverAlt === '') $coverAlt = (string)($existingRow['cover_alt'] ?? $title);

    $values = [
        $slug,
        $title,
        $category,
        $coverImage,
        $coverAlt ?: $title,
        rb_template_clean(rb_template_value($article, 'summary')),
        json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        rb_template_clean(rb_template_value($article, 'author', 'Artdon Lighting Team')) ?: 'Artdon Lighting Team',
        rb_template_clean(rb_template_value($article, 'publish_date', rb_template_value($article, 'date'))),
        rb_template_clean(rb_template_value($article, 'read_time')),
        rb_template_clean(rb_template_value($article, 'seo_title')) ?: ($title . ' | Artdon Lighting'),
        rb_template_clean(rb_template_value($article, 'seo_description')) ?: rb_template_clean(rb_template_value($article, 'summary')),
        rb_template_clean(rb_template_value($article, 'seo_keywords')),
        (int)rb_template_value($article, 'sort_order', 10),
        array_key_exists('is_published', $article) ? (int)!empty($article['is_published']) : 1,
    ];

    $existingId = (int)($existingRow['id'] ?? 0);
    if ($existingId > 0) {
        $update = $pdo->prepare('UPDATE web_resource_blog_articles SET slug=?, title=?, category=?, cover_image=?, cover_alt=?, summary=?, content_json=?, author=?, publish_date=?, read_time=?, seo_title=?, seo_description=?, seo_keywords=?, sort_order=?, is_published=? WHERE id=?');
        $update->execute([...$values, $existingId]);
    } else {
        $insert = $pdo->prepare('INSERT INTO web_resource_blog_articles (slug,title,category,cover_image,cover_alt,summary,content_json,author,publish_date,read_time,seo_title,seo_description,seo_keywords,sort_order,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute($values);
    }
    web_public_cache_clear('');
    return $slug;
}

$action = (string)($_REQUEST['action'] ?? 'export');

try {
    if ($action === 'export') {
        $slug = artdon_resource_blog_slug((string)($_GET['slug'] ?? ''));
        if ($slug === '') throw new RuntimeException('缺少 slug。');
        $article = artdon_resource_blog_find($pdo, $slug, false);
        if (!$article) throw new RuntimeException('文章不存在。');
        $payload = rb_template_article_payload($article);
        $filename = 'blog-template-' . $slug . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'export_excel') {
        $slug = artdon_resource_blog_slug((string)($_GET['slug'] ?? ''));
        if ($slug === '') throw new RuntimeException('缺少 slug。');
        $article = artdon_resource_blog_find($pdo, $slug, false);
        if (!$article) throw new RuntimeException('文章不存在。');
        $row = rb_template_article_excel_row($article);
        $filename = 'blog-template-' . $slug . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="utf-8"><style>td{mso-number-format:"\@";white-space:pre-wrap;vertical-align:top;}</style></head><body><table border="1"><tr>';
        foreach (array_keys($row) as $header) {
            echo '<th>' . rb_template_excel_cell($header) . '</th>';
        }
        echo '</tr><tr>';
        foreach ($row as $value) {
            echo '<td>' . rb_template_excel_cell($value) . '</td>';
        }
        echo '</tr></table></body></html>';
        exit;
    }

    if ($action === 'import') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('请求方式不正确。');
        if (!web_verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('页面已过期，请刷新后重试。');
        if (empty($_FILES['template_file']) || ($_FILES['template_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('请选择 JSON 模板文件。');
        }
        $name = (string)($_FILES['template_file']['name'] ?? '');
        if (!preg_match('/\.json$/i', $name)) throw new RuntimeException('只支持 JSON 模板文件。');
        $raw = file_get_contents((string)$_FILES['template_file']['tmp_name']);
        $payload = json_decode((string)$raw, true);
        if (!is_array($payload)) throw new RuntimeException('JSON 无法解析。');
        $fallbackCategory = artdon_resource_blog_slug((string)($_POST['category'] ?? 'lighting-knowledge'));
        $slug = rb_template_import($pdo, $payload, $fallbackCategory);
        web_log($pdo, (int)$user['id'], 'import_content', 'resources_blog_template', $slug, ['filename'=>$name]);
        $_SESSION['admin_success'] = '文章模板已导入：' . $slug;
        header('Location: resources_blog.php?slug=' . rawurlencode($slug) . '&edit=1');
        exit;
    }
} catch (Throwable $e) {
    $_SESSION['admin_error'] = $e->getMessage();
}

header('Location: resources_blog.php');
exit;
