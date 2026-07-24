<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/product_hierarchy.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/_product_center_nav.php';

$dbError = null;
$pdo = web_db($dbError);
if (!$pdo) { header('Location: login.php'); exit; }
web_migrate($pdo);
$user = web_require_admin($pdo);
web_product_hierarchy_migrate($pdo);

function aio_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function aio_has(array $row, string $key): bool { return array_key_exists($key, $row); }
function aio_val(array $row, string $key, mixed $default = ''): string { return aio_has($row, $key) ? trim((string)$row[$key]) : (string)$default; }
function aio_int(array $row, string $key, int $default, int $min, int $max): int
{
    if (!aio_has($row, $key) || trim((string)$row[$key]) === '') return $default;
    return max($min, min($max, (int)$row[$key]));
}
function aio_normalize_newlines(string $v): string
{
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    return trim($v);
}
function aio_lines_to_text(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return implode("\n", array_map('strval', $decoded));
        return $value;
    }
    if (is_array($value)) return implode("\n", array_map('strval', $value));
    return '';
}
function aio_text_to_lines(string $value): array
{
    $value = str_replace(["\r\n", "\r", '|', '；', ';'], "\n", $value);
    $lines = array_map('trim', explode("\n", $value));
    return array_values(array_filter($lines, static fn($v) => $v !== ''));
}
function aio_pairs_to_text(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) $value = $decoded;
    }
    if (!is_array($value)) return '';
    $out = [];
    foreach ($value as $item) {
        if (!is_array($item)) continue;
        $label = trim((string)($item['label'] ?? ''));
        $val = trim((string)($item['value'] ?? ''));
        if ($label !== '' || $val !== '') $out[] = $label . '=' . $val;
    }
    return implode("\n", $out);
}
function aio_text_to_pairs(string $value): array
{
    $value = str_replace(["\r\n", "\r", '|', '；'], "\n", $value);
    $rows = array_map('trim', explode("\n", $value));
    $out = [];
    foreach ($rows as $line) {
        if ($line === '') continue;
        if (str_contains($line, '=')) {
            [$label, $val] = explode('=', $line, 2);
        } elseif (str_contains($line, ':')) {
            [$label, $val] = explode(':', $line, 2);
        } else {
            $label = $line; $val = '';
        }
        $out[] = ['label'=>trim((string)$label), 'value'=>trim((string)$val)];
    }
    return $out;
}
function aio_csv_download(string $filename, array $headers, array $rows): void
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $h) $line[] = (string)($row[$h] ?? '');
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}
function aio_read_csv_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('请选择 CSV 文件。');
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('CSV 上传失败。');
    if (!is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new RuntimeException('CSV 文件无效。');
    $fh = fopen((string)$file['tmp_name'], 'r');
    if (!$fh) throw new RuntimeException('无法读取 CSV。');
    $headers = fgetcsv($fh);
    if (!$headers || !is_array($headers)) throw new RuntimeException('CSV 第一行必须是字段标题。');
    $headers = array_map(static function($h) { return trim((string)$h, "\xEF\xBB\xBF\t \n\r\0\x0B"); }, $headers);
    $rows = [];
    while (($cols = fgetcsv($fh)) !== false) {
        if (!is_array($cols)) continue;
        $row = [];
        $allBlank = true;
        foreach ($headers as $i => $h) {
            $v = (string)($cols[$i] ?? '');
            if (trim($v) !== '') $allBlank = false;
            $row[$h] = $v;
        }
        if (!$allBlank) $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}
function aio_json(array $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'; }
function aio_all_series(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id,name,slug,series_name,is_published FROM web_products ORDER BY sort_order ASC, id ASC');
    return $stmt->fetchAll() ?: [];
}
function aio_series_by_id(PDO $pdo, int $id): ?array
{
    return web_product_series_find($pdo, $id, false);
}
function aio_series_headers(): array
{
    $headers = [
        'series_id','series_slug','series_name','family_label','family_title','family_subtitle','family_intro',
        'hero_title_size','hero_title_weight','hero_subtitle_size','hero_body_size','hero_body_line_height','hero_body_width',
        'hero_primary_label','hero_primary_url','hero_secondary_label','hero_secondary_url',
        'why_title','why_text','characteristics_kicker','characteristics_title',
    ];
    for ($i=1;$i<=4;$i++) array_push($headers, "feature_{$i}_title", "feature_{$i}_text", "feature_{$i}_image_alt");
    array_push($headers, 'application_kicker','application_title','application_intro');
    for ($i=1;$i<=4;$i++) array_push($headers, "application_{$i}_icon", "application_{$i}_title", "application_{$i}_text", "application_{$i}_points", "application_{$i}_image_alt");
    array_push($headers, 'projects_kicker','projects_title','projects_intro');
    for ($i=1;$i<=4;$i++) array_push($headers, "project_{$i}_category", "project_{$i}_title", "project_{$i}_location", "project_{$i}_type", "project_{$i}_year", "project_{$i}_text", "project_{$i}_product_used", "project_{$i}_beam_angle", "project_{$i}_control", "project_{$i}_image_alt");
    array_push($headers, 'catalog_kicker','catalog_title','catalog_text','catalog_button_label','catalog_button_url','support_kicker','support_title','support_text','support_button1_label','support_button1_url','support_button2_label','support_button2_url','support_button3_label','support_button3_url','structure_title','structure_text','structure_points');
    return $headers;
}
function aio_series_export_row(array $series): array
{
    $c = web_product_series_content($series);
    $row = [
        'series_id'=>(string)$series['id'],
        'series_slug'=>(string)$series['slug'],
        'series_name'=>(string)($series['name'] ?? $series['series_name'] ?? ''),
        'family_label'=>$c['label'] ?? '',
        'family_title'=>$c['title'] ?? '',
        'family_subtitle'=>$c['subtitle'] ?? '',
        'family_intro'=>$c['intro'] ?? '',
        'hero_title_size'=>(string)($c['hero_title_size'] ?? ''),
        'hero_title_weight'=>(string)($c['hero_title_weight'] ?? ''),
        'hero_subtitle_size'=>(string)($c['hero_subtitle_size'] ?? ''),
        'hero_body_size'=>(string)($c['hero_body_size'] ?? ''),
        'hero_body_line_height'=>(string)($c['hero_body_line_height'] ?? ''),
        'hero_body_width'=>(string)($c['hero_body_width'] ?? ''),
        'hero_primary_label'=>$c['hero_primary_label'] ?? '',
        'hero_primary_url'=>$c['hero_primary_url'] ?? '',
        'hero_secondary_label'=>$c['hero_secondary_label'] ?? '',
        'hero_secondary_url'=>$c['hero_secondary_url'] ?? '',
        'why_title'=>$c['why_title'] ?? '',
        'why_text'=>$c['why_text'] ?? '',
        'characteristics_kicker'=>$c['characteristics_kicker'] ?? '',
        'characteristics_title'=>$c['characteristics_title'] ?? '',
        'application_kicker'=>$c['application_kicker'] ?? '',
        'application_title'=>$c['application_title'] ?? '',
        'application_intro'=>$c['application_intro'] ?? '',
        'projects_kicker'=>$c['projects_kicker'] ?? '',
        'projects_title'=>$c['projects_title'] ?? '',
        'projects_intro'=>$c['projects_intro'] ?? '',
        'catalog_kicker'=>$c['catalog_kicker'] ?? '',
        'catalog_title'=>$c['catalog_title'] ?? '',
        'catalog_text'=>$c['catalog_text'] ?? '',
        'catalog_button_label'=>$c['catalog_button_label'] ?? '',
        'catalog_button_url'=>$c['catalog_button_url'] ?? '',
        'support_kicker'=>$c['support_kicker'] ?? '',
        'support_title'=>$c['support_title'] ?? '',
        'support_text'=>$c['support_text'] ?? '',
        'support_button1_label'=>$c['support_button1_label'] ?? '',
        'support_button1_url'=>$c['support_button1_url'] ?? '',
        'support_button2_label'=>$c['support_button2_label'] ?? '',
        'support_button2_url'=>$c['support_button2_url'] ?? '',
        'support_button3_label'=>$c['support_button3_label'] ?? '',
        'support_button3_url'=>$c['support_button3_url'] ?? '',
        'structure_title'=>$c['structure_title'] ?? '',
        'structure_text'=>$c['structure_text'] ?? '',
        'structure_points'=>aio_lines_to_text($c['structure_points'] ?? []),
    ];
    $features = array_values(array_filter($c['features'] ?? [], 'is_array'));
    for ($i=1;$i<=4;$i++) { $f=$features[$i-1] ?? []; $row["feature_{$i}_title"]=$f['title'] ?? ''; $row["feature_{$i}_text"]=$f['text'] ?? ''; $row["feature_{$i}_image_alt"]=$f['image_alt'] ?? ($f['alt'] ?? ''); }
    $apps = array_values(array_filter($c['applications'] ?? [], 'is_array'));
    for ($i=1;$i<=4;$i++) { $a=$apps[$i-1] ?? []; $row["application_{$i}_icon"]=$a['icon'] ?? ''; $row["application_{$i}_title"]=$a['title'] ?? ''; $row["application_{$i}_text"]=$a['text'] ?? ''; $row["application_{$i}_points"]=aio_lines_to_text($a['points'] ?? []); $row["application_{$i}_image_alt"]=$a['image_alt'] ?? ($a['alt'] ?? ''); }
    $projects = array_values(array_filter($c['projects'] ?? [], 'is_array'));
    for ($i=1;$i<=4;$i++) { $p=$projects[$i-1] ?? []; foreach(['category','title','location','type','year','text','product_used','beam_angle','control'] as $k){ $row["project_{$i}_{$k}"]=$p[$k] ?? ''; } $row["project_{$i}_image_alt"]=$p['image_alt'] ?? ($p['alt'] ?? ''); }
    return $row;
}
function aio_import_series_rows(PDO $pdo, array $rows): int
{
    $count = 0;
    foreach ($rows as $row) {
        $sid = (int)aio_val($row, 'series_id', '0');
        $series = $sid > 0 ? web_product_series_find($pdo, $sid, false) : null;
        if (!$series && aio_val($row, 'series_slug') !== '') $series = web_product_series_find($pdo, aio_val($row, 'series_slug'), false);
        if (!$series) continue;
        $sid = (int)$series['id'];
        $current = web_product_series_content($series);
        $features = array_values(array_filter($current['features'] ?? [], 'is_array'));
        while (count($features) < 4) $features[] = ['title'=>'','text'=>'','image'=>'','image_alt'=>''];
        for ($i=1;$i<=4;$i++) {
            foreach(['title','text','image_alt'] as $k) if (aio_has($row, "feature_{$i}_{$k}")) $features[$i-1][$k] = aio_val($row, "feature_{$i}_{$k}");
        }
        $apps = array_values(array_filter($current['applications'] ?? [], 'is_array'));
        while (count($apps) < 4) $apps[] = ['icon'=>'retail','title'=>'','text'=>'','image'=>'','image_alt'=>'','points'=>[]];
        for ($i=1;$i<=4;$i++) {
            foreach(['icon','title','text','image_alt'] as $k) if (aio_has($row, "application_{$i}_{$k}")) $apps[$i-1][$k] = aio_val($row, "application_{$i}_{$k}");
            if (aio_has($row, "application_{$i}_points")) $apps[$i-1]['points'] = aio_text_to_lines(aio_val($row, "application_{$i}_points"));
        }
        $projects = array_values(array_filter($current['projects'] ?? [], 'is_array'));
        while (count($projects) < 4) $projects[] = ['category'=>'','title'=>'','location'=>'','type'=>'','year'=>'','image'=>'','image_alt'=>'','text'=>'','product_used'=>'','beam_angle'=>'','control'=>''];
        for ($i=1;$i<=4;$i++) foreach(['category','title','location','type','year','text','product_used','beam_angle','control','image_alt'] as $k) if (aio_has($row, "project_{$i}_{$k}")) $projects[$i-1][$k] = aio_val($row, "project_{$i}_{$k}");
        $fields = [
            'family_label'=>aio_val($row,'family_label',$current['label'] ?? ''),
            'family_title'=>aio_val($row,'family_title',$current['title'] ?? ''),
            'family_subtitle'=>aio_val($row,'family_subtitle',$current['subtitle'] ?? ''),
            'family_intro'=>aio_normalize_newlines(aio_val($row,'family_intro',$current['intro'] ?? '')),
            'family_hero_title_size'=>aio_int($row,'hero_title_size',(int)($current['hero_title_size'] ?? 32),22,110),
            'family_hero_title_weight'=>aio_int($row,'hero_title_weight',(int)($current['hero_title_weight'] ?? 950),650,950),
            'family_hero_subtitle_size'=>aio_int($row,'hero_subtitle_size',(int)($current['hero_subtitle_size'] ?? 28),11,46),
            'family_hero_body_size'=>aio_int($row,'hero_body_size',(int)($current['hero_body_size'] ?? 18),11,36),
            'family_hero_body_line_height'=>aio_val($row,'hero_body_line_height',$current['hero_body_line_height'] ?? '1.72'),
            'family_hero_body_width'=>aio_int($row,'hero_body_width',(int)($current['hero_body_width'] ?? 720),360,1100),
            'family_hero_primary_label'=>aio_val($row,'hero_primary_label',$current['hero_primary_label'] ?? ''),
            'family_hero_primary_url'=>aio_val($row,'hero_primary_url',$current['hero_primary_url'] ?? ''),
            'family_hero_secondary_label'=>aio_val($row,'hero_secondary_label',$current['hero_secondary_label'] ?? ''),
            'family_hero_secondary_url'=>aio_val($row,'hero_secondary_url',$current['hero_secondary_url'] ?? ''),
            'family_why_title'=>aio_val($row,'why_title',$current['why_title'] ?? ''),
            'family_why_text'=>aio_normalize_newlines(aio_val($row,'why_text',$current['why_text'] ?? '')),
            'family_characteristics_kicker'=>aio_val($row,'characteristics_kicker',$current['characteristics_kicker'] ?? ''),
            'family_characteristics_title'=>aio_val($row,'characteristics_title',$current['characteristics_title'] ?? ''),
            'family_application_kicker'=>aio_val($row,'application_kicker',$current['application_kicker'] ?? ''),
            'family_application_title'=>aio_val($row,'application_title',$current['application_title'] ?? ''),
            'family_application_intro'=>aio_normalize_newlines(aio_val($row,'application_intro',$current['application_intro'] ?? '')),
            'family_projects_kicker'=>aio_val($row,'projects_kicker',$current['projects_kicker'] ?? ''),
            'family_projects_title'=>aio_val($row,'projects_title',$current['projects_title'] ?? ''),
            'family_projects_intro'=>aio_normalize_newlines(aio_val($row,'projects_intro',$current['projects_intro'] ?? '')),
            'family_catalog_kicker'=>aio_val($row,'catalog_kicker',$current['catalog_kicker'] ?? ''),
            'family_catalog_title'=>aio_val($row,'catalog_title',$current['catalog_title'] ?? ''),
            'family_catalog_text'=>aio_normalize_newlines(aio_val($row,'catalog_text',$current['catalog_text'] ?? '')),
            'family_catalog_button_label'=>aio_val($row,'catalog_button_label',$current['catalog_button_label'] ?? ''),
            'family_catalog_button_url'=>aio_val($row,'catalog_button_url',$current['catalog_button_url'] ?? ''),
            'family_support_kicker'=>aio_val($row,'support_kicker',$current['support_kicker'] ?? ''),
            'family_support_title'=>aio_val($row,'support_title',$current['support_title'] ?? ''),
            'family_support_text'=>aio_normalize_newlines(aio_val($row,'support_text',$current['support_text'] ?? '')),
            'family_support_button1_label'=>aio_val($row,'support_button1_label',$current['support_button1_label'] ?? ''),
            'family_support_button1_url'=>aio_val($row,'support_button1_url',$current['support_button1_url'] ?? ''),
            'family_support_button2_label'=>aio_val($row,'support_button2_label',$current['support_button2_label'] ?? ''),
            'family_support_button2_url'=>aio_val($row,'support_button2_url',$current['support_button2_url'] ?? ''),
            'family_support_button3_label'=>aio_val($row,'support_button3_label',$current['support_button3_label'] ?? ''),
            'family_support_button3_url'=>aio_val($row,'support_button3_url',$current['support_button3_url'] ?? ''),
            'family_structure_title'=>aio_val($row,'structure_title',$current['structure_title'] ?? ''),
            'family_structure_text'=>aio_normalize_newlines(aio_val($row,'structure_text',$current['structure_text'] ?? '')),
            'family_structure_points_json'=>aio_json(aio_text_to_lines(aio_val($row,'structure_points',aio_lines_to_text($current['structure_points'] ?? [])))),
            'family_features_json'=>aio_json($features),
            'family_applications_json'=>aio_json($apps),
            'family_projects_json'=>aio_json($projects),
        ];
        $sql = 'UPDATE web_products SET ' . implode('=?, ', array_keys($fields)) . '=?, updated_at=CURRENT_TIMESTAMP WHERE id=?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([...array_values($fields), $sid]);
        $count++;
    }
    return $count;
}
function aio_variant_headers(): array
{
    return ['variant_id','series_id','series_slug','series_name','variant_slug','name','model_code','size_name','short_description','full_description','detail_intro','dimensions','cutout_text','power_text','lumen_text','efficacy_text','voltage','cct','cri','beam_angle','ip_rating','finish','mounting','dimming','tags','extra_specs','spec_rows','datasheet_path','installation_path','photometric_path','cad_path','bim_path','video_url','is_published','sort_order','seo_title','seo_description'];
}
function aio_variant_export_rows(PDO $pdo, int $seriesId): array
{
    $series = web_product_series_find($pdo, $seriesId, false);
    $rows = [];
    foreach (web_product_variants($pdo, $seriesId, false) as $v) {
        $rows[] = [
            'variant_id'=>(string)$v['id'],
            'series_id'=>(string)$seriesId,
            'series_slug'=>(string)($series['slug'] ?? ''),
            'series_name'=>(string)($series['name'] ?? ''),
            'variant_slug'=>(string)($v['slug'] ?? ''),
            'name'=>(string)($v['name'] ?? ''),
            'model_code'=>(string)($v['model_code'] ?? ''),
            'size_name'=>(string)($v['size_name'] ?? ''),
            'short_description'=>(string)($v['short_description'] ?? ''),
            'full_description'=>(string)($v['full_description'] ?? ''),
            'detail_intro'=>(string)($v['detail_intro'] ?? ''),
            'dimensions'=>(string)($v['dimensions'] ?? ''),
            'cutout_text'=>(string)($v['cutout_text'] ?? ''),
            'power_text'=>(string)($v['power_text'] ?? ''),
            'lumen_text'=>(string)($v['lumen_text'] ?? ''),
            'efficacy_text'=>(string)($v['efficacy_text'] ?? ''),
            'voltage'=>aio_lines_to_text($v['voltage'] ?? $v['voltage_json'] ?? []),
            'cct'=>aio_lines_to_text($v['cct'] ?? $v['cct_json'] ?? []),
            'cri'=>aio_lines_to_text($v['cri'] ?? $v['cri_json'] ?? []),
            'beam_angle'=>aio_lines_to_text($v['beam_angle'] ?? $v['beam_angle_json'] ?? []),
            'ip_rating'=>(string)($v['ip_rating'] ?? ''),
            'finish'=>aio_lines_to_text($v['finish'] ?? $v['finish_json'] ?? []),
            'mounting'=>aio_lines_to_text($v['mounting'] ?? $v['mounting_json'] ?? []),
            'dimming'=>aio_lines_to_text($v['dimming'] ?? $v['dimming_json'] ?? []),
            'tags'=>aio_lines_to_text($v['tags'] ?? $v['tags_json'] ?? []),
            'extra_specs'=>aio_pairs_to_text($v['extra_specs_json'] ?? []),
            'spec_rows'=>aio_pairs_to_text($v['spec_rows_json'] ?? []),
            'datasheet_path'=>(string)($v['datasheet_path'] ?? ''),
            'installation_path'=>(string)($v['installation_path'] ?? ''),
            'photometric_path'=>(string)($v['photometric_path'] ?? ''),
            'cad_path'=>(string)($v['cad_path'] ?? ''),
            'bim_path'=>(string)($v['bim_path'] ?? ''),
            'video_url'=>(string)($v['video_url'] ?? ''),
            'is_published'=>!empty($v['is_published']) ? '1' : '0',
            'sort_order'=>(string)($v['sort_order'] ?? '0'),
            'seo_title'=>(string)($v['seo_title'] ?? ''),
            'seo_description'=>(string)($v['seo_description'] ?? ''),
        ];
    }
    return $rows;
}
function aio_find_variant_for_row(PDO $pdo, array $row, int $fallbackSeriesId): ?array
{
    $vid = (int)aio_val($row, 'variant_id', '0');
    if ($vid > 0) return web_product_variant_find($pdo, $vid, false);
    $slug = aio_val($row, 'variant_slug');
    if ($slug !== '') return web_product_variant_find($pdo, $slug, false);
    $seriesId = (int)aio_val($row, 'series_id', (string)$fallbackSeriesId);
    $model = aio_val($row, 'model_code');
    if ($seriesId > 0 && $model !== '') {
        $stmt = $pdo->prepare('SELECT * FROM web_product_variants WHERE series_id=? AND model_code=? LIMIT 1');
        $stmt->execute([$seriesId, $model]);
        $found = $stmt->fetch();
        return $found ? web_product_variant_hydrate($found) : null;
    }
    return null;
}
function aio_import_variant_rows(PDO $pdo, array $rows, int $fallbackSeriesId): int
{
    $count = 0;
    foreach ($rows as $row) {
        $v = aio_find_variant_for_row($pdo, $row, $fallbackSeriesId);
        if (!$v) continue;
        $fields = [];
        foreach (['name','model_code','size_name','short_description','full_description','detail_intro','dimensions','cutout_text','power_text','lumen_text','efficacy_text','ip_rating','datasheet_path','installation_path','photometric_path','cad_path','bim_path','video_url','seo_title','seo_description'] as $k) {
            if (aio_has($row, $k)) $fields[$k] = aio_normalize_newlines(aio_val($row, $k));
        }
        foreach (['voltage','cct','cri','beam_angle','finish','mounting','dimming','tags'] as $k) {
            if (aio_has($row, $k)) $fields[$k . '_json'] = aio_json(aio_text_to_lines(aio_val($row, $k)));
        }
        if (aio_has($row, 'extra_specs')) $fields['extra_specs_json'] = aio_json(aio_text_to_pairs(aio_val($row, 'extra_specs')));
        if (aio_has($row, 'spec_rows')) $fields['spec_rows_json'] = aio_json(aio_text_to_pairs(aio_val($row, 'spec_rows')));
        if (aio_has($row, 'is_published')) $fields['is_published'] = in_array(strtolower(aio_val($row, 'is_published')), ['1','yes','y','true','发布','已发布'], true) ? 1 : 0;
        if (aio_has($row, 'sort_order')) $fields['sort_order'] = (int)aio_val($row, 'sort_order', '0');
        if (!$fields) continue;
        $sql = 'UPDATE web_product_variants SET ' . implode('=?, ', array_keys($fields)) . '=?, updated_at=CURRENT_TIMESTAMP WHERE id=?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([...array_values($fields), (int)$v['id']]);
        $count++;
    }
    return $count;
}

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'series');
$seriesId = (int)($_GET['series_id'] ?? $_POST['series_id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);
$series = $seriesId > 0 ? aio_series_by_id($pdo, $seriesId) : null;
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

try {
    if ($action === 'export_series') {
        if (!$series) throw new RuntimeException('请选择系列。');
        aio_csv_download('artdon_series_page_' . preg_replace('/[^a-z0-9_-]+/i','-', (string)$series['slug']) . '.csv', aio_series_headers(), [aio_series_export_row($series)]);
    }
    if ($action === 'export_all_series') {
        $rows = [];
        foreach (aio_all_series($pdo) as $s) $rows[] = aio_series_export_row($s);
        aio_csv_download('artdon_all_series_page_text.csv', aio_series_headers(), $rows);
    }
    if ($action === 'export_variants') {
        if (!$series) throw new RuntimeException('请选择系列。');
        aio_csv_download('artdon_variants_' . preg_replace('/[^a-z0-9_-]+/i','-', (string)$series['slug']) . '.csv', aio_variant_headers(), aio_variant_export_rows($pdo, $seriesId));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['import_series','import_variants'], true)) {
        if (!web_verify_csrf($_POST['csrf'] ?? null)) throw new RuntimeException('页面已过期，请刷新后重试。');
        $rows = aio_read_csv_upload($_FILES['csv_file'] ?? []);
        if ($action === 'import_series') {
            $done = aio_import_series_rows($pdo, $rows);
            web_log($pdo, (int)$user['id'], 'import_series_page_csv_v71858', 'product_series', (string)$seriesId, ['rows'=>$done]);
            $_SESSION['admin_success'] = '系列页文字导入完成：更新 ' . $done . ' 个系列。图片、尺寸图未改动。';
            header('Location: product_bulk_io.php?mode=series&id=' . $seriesId); exit;
        }
        if ($action === 'import_variants') {
            if (!$series) throw new RuntimeException('请选择系列。');
            $done = aio_import_variant_rows($pdo, $rows, $seriesId);
            web_log($pdo, (int)$user['id'], 'import_product_variants_csv_v71858', 'product_variant', (string)$seriesId, ['rows'=>$done]);
            $_SESSION['admin_success'] = '尺寸 / 型号参数导入完成：更新 ' . $done . ' 条。图片、尺寸图、配光曲线图、配件图未改动。';
            header('Location: product_bulk_io.php?mode=variants&series_id=' . $seriesId); exit;
        }
    }
} catch (Throwable $e) {
    $_SESSION['admin_error'] = $e->getMessage();
    header('Location: product_bulk_io.php?mode=' . rawurlencode($mode) . '&series_id=' . $seriesId . '&id=' . $seriesId); exit;
}

$allSeries = aio_all_series($pdo);
if (!$series && $seriesId <= 0 && $allSeries) { $seriesId = (int)$allSeries[0]['id']; $series = aio_series_by_id($pdo, $seriesId); }
admin_page_start('产品批量导入导出 V7.1.8.58', 'bulk_io', $user);
admin_notice();
if (function_exists('admin_product_center_tabs')) admin_product_center_tabs('bulk');
?>
<style>
.aio-wrap{max-width:1280px;margin:0 auto 80px;padding:0 18px;color:#101828}.aio-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin:14px 0;padding:18px;border:1px solid #dce3ed;border-radius:18px;background:#fff;box-shadow:0 10px 24px rgba(16,24,40,.045)}.aio-head h1{margin:0 0 6px;font-size:22px;letter-spacing:-.04em}.aio-head p{margin:0;color:#667085;font-size:13px;line-height:1.6}.aio-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.aio-btn{height:36px;display:inline-flex;align-items:center;justify-content:center;padding:0 13px;border:1px solid #d7dde7;border-radius:10px;background:#fff;color:#111;text-decoration:none;font-weight:900;font-size:12px;cursor:pointer}.aio-btn.primary{background:#111;color:#fff;border-color:#111}.aio-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.aio-card{background:#fff;border:1px solid #dce3ed;border-radius:18px;padding:18px;box-shadow:0 10px 24px rgba(16,24,40,.035)}.aio-card h2{margin:0 0 8px;font-size:17px}.aio-card p,.aio-card li{color:#667085;font-size:13px;line-height:1.65}.aio-card form{display:grid;gap:10px;margin-top:12px}.aio-card select,.aio-card input[type=file]{width:100%;border:1px solid #d7dde7;border-radius:10px;background:#fff;padding:9px 10px}.aio-warn{background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:12px 14px;color:#9a3412;font-size:13px;line-height:1.6;margin:12px 0}.aio-small{font-size:12px;color:#98a2b3;line-height:1.6;margin-top:8px}@media(max-width:900px){.aio-grid{grid-template-columns:1fr}.aio-head{display:block}.aio-actions{justify-content:flex-start;margin-top:12px}}
</style>
<div class="aio-wrap">
  <div class="aio-head">
    <div><h1>产品批量导入 / 导出</h1><p>只处理文字、描述、参数、按钮、SEO、下载路径。不会导入图片、尺寸图、配光曲线图、配件图，也不会改图片路径。</p></div>
    <div class="aio-actions">
      <a class="aio-btn" href="products.php">返回产品中心</a>
      <?php if($series): ?><a class="aio-btn" href="product_series_page.php?id=<?= (int)$seriesId ?>">系列页编辑</a><a class="aio-btn" href="product_variants.php?series_id=<?= (int)$seriesId ?>">尺寸 / 型号</a><a class="aio-btn primary" target="_blank" href="../series.php?slug=<?= rawurlencode((string)$series['slug']) ?>">预览系列页 ↗</a><?php endif; ?>
    </div>
  </div>

  <div class="aio-card" style="margin-bottom:14px">
    <form method="get" style="display:grid;grid-template-columns:180px 1fr auto;gap:10px;align-items:end">
      <input type="hidden" name="mode" value="<?= aio_e($mode === 'variants' ? 'variants' : 'series') ?>">
      <div><label style="display:block;font-size:12px;font-weight:900;margin-bottom:5px">选择系列</label><select name="<?= $mode === 'variants' ? 'series_id' : 'id' ?>"><?php foreach($allSeries as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']===$seriesId?'selected':'' ?>><?= aio_e($s['name'] ?: $s['series_name'] ?: $s['slug']) ?></option><?php endforeach; ?></select></div>
      <div class="aio-small">先导出现有 CSV，在表格里改文字和参数，再导入。第一行字段名不要改。</div>
      <button class="aio-btn primary" type="submit">切换</button>
    </form>
  </div>

  <div class="aio-grid">
    <div class="aio-card">
      <h2>系列页文字导入 / 导出</h2>
      <p>适合批量维护首屏文字、按钮、Why Choose、Characteristics、应用场景、案例、目录下载、项目支持等。图片和尺寸图不处理。</p>
      <div class="aio-actions">
        <?php if($series): ?><a class="aio-btn primary" href="product_bulk_io.php?action=export_series&mode=series&id=<?= (int)$seriesId ?>">导出当前系列 CSV</a><?php endif; ?>
        <a class="aio-btn" href="product_bulk_io.php?action=export_all_series&mode=series">导出全部系列 CSV</a>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= aio_e(web_csrf_token()) ?>"><input type="hidden" name="mode" value="series"><input type="hidden" name="id" value="<?= (int)$seriesId ?>"><input type="hidden" name="action" value="import_series">
        <input type="file" name="csv_file" accept=".csv,text/csv">
        <button class="aio-btn primary" type="submit" onclick="return confirm('确认导入系列页文字？图片、尺寸图不会变。');">导入系列页 CSV</button>
      </form>
    </div>

    <div class="aio-card">
      <h2>尺寸 / 型号参数导入 / 导出</h2>
      <p>适合批量维护具体产品的型号、尺寸、功率、流明、角度、CRI、CCT、IP、描述、SEO 等。不会改产品图、尺寸图、配光曲线图、配件图。</p>
      <?php if($series): ?><div class="aio-actions"><a class="aio-btn primary" href="product_bulk_io.php?action=export_variants&mode=variants&series_id=<?= (int)$seriesId ?>">导出当前系列尺寸 CSV</a></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= aio_e(web_csrf_token()) ?>"><input type="hidden" name="mode" value="variants"><input type="hidden" name="series_id" value="<?= (int)$seriesId ?>"><input type="hidden" name="action" value="import_variants">
        <input type="file" name="csv_file" accept=".csv,text/csv">
        <button class="aio-btn primary" type="submit" onclick="return confirm('确认导入尺寸 / 型号参数？图片、尺寸图、配光曲线图、配件图不会变。');">导入尺寸参数 CSV</button>
      </form>
    </div>
  </div>

  <div class="aio-warn">
    使用方式：先导出 CSV → 用 Excel / WPS 修改 → 另存为 CSV UTF-8 → 回到这里导入。不要改第一行字段名；不要删除 id / slug 列。导入前建议先备份数据库。
  </div>
</div>
<?php admin_page_end(); ?>
