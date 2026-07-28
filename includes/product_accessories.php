<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function web_product_accessory_library_ensure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_product_accessory_library` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `image` VARCHAR(500) NOT NULL DEFAULT '',
        `title` VARCHAR(160) NOT NULL DEFAULT '',
        `model` VARCHAR(120) NOT NULL DEFAULT '',
        `description` TEXT NULL,
        `alt` VARCHAR(255) NOT NULL DEFAULT '',
        `sort_order` INT NOT NULL DEFAULT 100,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_web_accessory_active_sort` (`is_active`,`sort_order`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function web_product_accessory_library_list(PDO $pdo, bool $activeOnly = true): array
{
    web_product_accessory_library_ensure($pdo);
    $where = $activeOnly ? 'WHERE is_active=1' : '';
    $st = $pdo->query("SELECT * FROM `web_product_accessory_library` {$where} ORDER BY is_active DESC, sort_order ASC, id DESC");
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

function web_product_accessory_library_find(PDO $pdo, int $id): ?array
{
    web_product_accessory_library_ensure($pdo);
    if ($id <= 0) return null;
    $st = $pdo->prepare("SELECT * FROM `web_product_accessory_library` WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function web_product_accessory_library_payload(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'image' => (string)($row['image'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'model' => (string)($row['model'] ?? ''),
        'description' => (string)($row['description'] ?? ''),
        'alt' => (string)($row['alt'] ?? ''),
    ];
}

function web_product_accessory_library_save(PDO $pdo, array $data, int $id = 0): int
{
    web_product_accessory_library_ensure($pdo);
    $image = trim((string)($data['image'] ?? ''));
    $title = trim((string)($data['title'] ?? ''));
    $model = trim((string)($data['model'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $alt = trim((string)($data['alt'] ?? ''));
    $sort = (int)($data['sort_order'] ?? 100);
    $active = empty($data['is_active']) ? 0 : 1;

    if ($image === '' && $title === '' && $model === '') {
        throw new RuntimeException('共用配件至少要填写图片、名称或型号其中一项。');
    }

    if ($id > 0) {
        $st = $pdo->prepare("UPDATE `web_product_accessory_library` SET image=?, title=?, model=?, description=?, alt=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?");
        $st->execute([$image,$title,$model,$description,$alt,$sort,$active,$id]);
        return $id;
    }

    $st = $pdo->prepare("INSERT INTO `web_product_accessory_library`(image,title,model,description,alt,sort_order,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())");
    $st->execute([$image,$title,$model,$description,$alt,$sort,$active]);
    return (int)$pdo->lastInsertId();
}

/* V7.1.8.112 shared accessory push planner/apply: safe append only, no overwrite. */
function web_product_accessory_push_log_ensure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `web_product_accessory_push_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `accessory_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `variant_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `series_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        `result` VARCHAR(40) NOT NULL DEFAULT '',
        `message` VARCHAR(255) NOT NULL DEFAULT '',
        `old_json` LONGTEXT NULL,
        `new_json` LONGTEXT NULL,
        `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_accessory_push_acc` (`accessory_id`,`created_at`),
        KEY `idx_accessory_push_variant` (`variant_id`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function web_product_accessory_push_decode_items($json): array
{
    if (is_array($json)) $arr = $json;
    else {
        $raw = trim((string)$json);
        $arr = $raw === '' ? [] : json_decode($raw, true);
    }
    if (!is_array($arr)) $arr = [];
    $out = [];
    foreach ($arr as $item) {
        if (!is_array($item)) continue;
        $image = trim((string)($item['image'] ?? ''));
        $title = trim((string)($item['title'] ?? ''));
        $model = trim((string)($item['model'] ?? ''));
        $description = trim((string)($item['description'] ?? ''));
        $alt = trim((string)($item['alt'] ?? ''));
        if ($image === '' && $title === '' && $model === '' && $description === '' && $alt === '') continue;
        $out[] = [
            'image' => $image,
            'title' => $title,
            'model' => $model,
            'description' => $description,
            'alt' => $alt,
        ] + array_diff_key($item, ['image'=>1,'title'=>1,'model'=>1,'description'=>1,'alt'=>1]);
        if (count($out) >= 12) break;
    }
    return $out;
}

function web_product_accessory_push_key(string $v): string
{
    $v = trim($v);
    $v = preg_replace('/\s+/u', '', $v) ?: $v;
    return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
}

function web_product_accessory_push_payload(array $accessory): array
{
    return [
        'image' => trim((string)($accessory['image'] ?? '')),
        'title' => trim((string)($accessory['title'] ?? '')),
        'model' => trim((string)($accessory['model'] ?? '')),
        'description' => trim((string)($accessory['description'] ?? '')),
        'alt' => trim((string)($accessory['alt'] ?? '')),
        'shared_accessory_id' => (int)($accessory['id'] ?? 0),
        'shared_accessory_pushed_at' => date('Y-m-d H:i:s'),
    ];
}

function web_product_accessory_push_duplicate(array $items, array $accessory): bool
{
    $accModel = web_product_accessory_push_key((string)($accessory['model'] ?? ''));
    $accImage = trim((string)($accessory['image'] ?? ''));
    $accId = (int)($accessory['id'] ?? 0);
    foreach ($items as $item) {
        if ($accId > 0 && (int)($item['shared_accessory_id'] ?? 0) === $accId) return true;
        $model = web_product_accessory_push_key((string)($item['model'] ?? ''));
        $image = trim((string)($item['image'] ?? ''));
        if ($accModel !== '' && $model !== '' && $model === $accModel) return true;
        if ($accImage !== '' && $image !== '' && $image === $accImage) return true;
    }
    return false;
}

function web_product_accessory_push_status(array $items, array $accessory): array
{
    $count = count($items);
    $dup = web_product_accessory_push_duplicate($items, $accessory);
    $full = $count >= 12;
    $can = !$dup && !$full;
    $reason = $can ? '可新增' : ($dup ? '已存在，跳过' : '配件位已满');
    return ['count'=>$count,'duplicate'=>$dup?1:0,'full'=>$full?1:0,'can_add'=>$can?1:0,'reason'=>$reason];
}

function web_product_accessory_push_tree(PDO $pdo, array $accessory): array
{
    web_product_accessory_library_ensure($pdo);
    $sql = "SELECT
            COALESCE(NULLIF(c.name,''), p.category_slug, '未分类') AS category_name,
            COALESCE(NULLIF(p.category_slug,''), 'uncategorized') AS category_slug,
            COALESCE(c.sort_order,9999) AS category_sort,
            p.id AS series_id,
            p.name AS series_title,
            p.series_name,
            p.slug AS series_slug,
            p.is_published AS series_published,
            p.sort_order AS series_sort,
            v.id AS variant_id,
            v.name AS variant_name,
            v.model_code,
            v.slug AS variant_slug,
            v.cover_image,
            v.is_published AS variant_published,
            v.sort_order AS variant_sort,
            v.accessory_items_json
        FROM web_products p
        JOIN web_product_variants v ON v.series_id=p.id
        LEFT JOIN web_product_categories c ON c.slug=p.category_slug
        ORDER BY category_sort ASC, category_name ASC, p.sort_order ASC, p.id ASC, v.sort_order ASC, v.id ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $tree = [];
    foreach ($rows as $r) {
        $catSlug = trim((string)($r['category_slug'] ?? 'uncategorized')) ?: 'uncategorized';
        $seriesId = (int)($r['series_id'] ?? 0);
        $items = web_product_accessory_push_decode_items($r['accessory_items_json'] ?? '[]');
        $st = web_product_accessory_push_status($items, $accessory);
        if (!isset($tree[$catSlug])) {
            $tree[$catSlug] = [
                'slug'=>$catSlug,
                'name'=>(string)($r['category_name'] ?? $catSlug),
                'series'=>[],
                'total'=>0,
                'can_add'=>0,
                'duplicate'=>0,
                'full'=>0,
            ];
        }
        if (!isset($tree[$catSlug]['series'][$seriesId])) {
            $seriesName = trim((string)($r['series_name'] ?? '')) ?: trim((string)($r['series_title'] ?? ''));
            $tree[$catSlug]['series'][$seriesId] = [
                'id'=>$seriesId,
                'name'=>$seriesName !== '' ? $seriesName : ('系列 #'.$seriesId),
                'slug'=>(string)($r['series_slug'] ?? ''),
                'is_published'=>(int)($r['series_published'] ?? 0),
                'variants'=>[],
                'total'=>0,
                'can_add'=>0,
                'duplicate'=>0,
                'full'=>0,
            ];
        }
        $variant = [
            'id'=>(int)($r['variant_id'] ?? 0),
            'name'=>(string)($r['variant_name'] ?? ''),
            'model_code'=>(string)($r['model_code'] ?? ''),
            'slug'=>(string)($r['variant_slug'] ?? ''),
            'cover_image'=>(string)($r['cover_image'] ?? ''),
            'is_published'=>(int)($r['variant_published'] ?? 0),
            'accessory_count'=>(int)$st['count'],
            'duplicate'=>(int)$st['duplicate'],
            'full'=>(int)$st['full'],
            'can_add'=>(int)$st['can_add'],
            'reason'=>(string)$st['reason'],
        ];
        $tree[$catSlug]['series'][$seriesId]['variants'][] = $variant;
        foreach ([$catSlug, $seriesId] as $_) {}
        $tree[$catSlug]['total']++;
        $tree[$catSlug]['can_add'] += (int)$st['can_add'];
        $tree[$catSlug]['duplicate'] += (int)$st['duplicate'];
        $tree[$catSlug]['full'] += (int)$st['full'];
        $tree[$catSlug]['series'][$seriesId]['total']++;
        $tree[$catSlug]['series'][$seriesId]['can_add'] += (int)$st['can_add'];
        $tree[$catSlug]['series'][$seriesId]['duplicate'] += (int)$st['duplicate'];
        $tree[$catSlug]['series'][$seriesId]['full'] += (int)$st['full'];
    }
    foreach ($tree as &$cat) $cat['series'] = array_values($cat['series']);
    unset($cat);
    return array_values($tree);
}

function web_product_accessory_push_ids($raw): array
{
    if (!is_array($raw)) $raw = [$raw];
    $ids = [];
    foreach ($raw as $id) {
        $id = (int)$id;
        if ($id > 0) $ids[$id] = $id;
    }
    return array_values($ids);
}

function web_product_accessory_push_apply(PDO $pdo, array $accessory, array $variantIds, int $userId = 0): array
{
    web_product_accessory_push_log_ensure($pdo);
    $variantIds = web_product_accessory_push_ids($variantIds);
    $summary = ['selected'=>count($variantIds),'added'=>0,'skipped_duplicate'=>0,'skipped_full'=>0,'skipped_missing'=>0,'items'=>[]];
    if (!$variantIds) return $summary;
    $payload = web_product_accessory_push_payload($accessory);
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT id,series_id,name,model_code,accessory_items_json FROM web_product_variants WHERE id=? LIMIT 1 FOR UPDATE');
        $update = $pdo->prepare('UPDATE web_product_variants SET accessory_items_json=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
        $log = $pdo->prepare('INSERT INTO web_product_accessory_push_logs(accessory_id,variant_id,series_id,result,message,old_json,new_json,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())');
        foreach ($variantIds as $variantId) {
            $select->execute([$variantId]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $summary['skipped_missing']++;
                $summary['items'][] = ['variant_id'=>$variantId,'result'=>'missing','message'=>'产品不存在'];
                continue;
            }
            $oldJson = (string)($row['accessory_items_json'] ?? '[]');
            $items = web_product_accessory_push_decode_items($oldJson);
            $status = web_product_accessory_push_status($items, $accessory);
            if (!empty($status['duplicate'])) {
                $summary['skipped_duplicate']++;
                $message = '已存在同型号/同图片配件，跳过';
                $log->execute([(int)$accessory['id'],(int)$row['id'],(int)$row['series_id'],'skipped_duplicate',$message,$oldJson,$oldJson,$userId]);
                $summary['items'][] = ['variant_id'=>(int)$row['id'],'name'=>(string)$row['name'],'model'=>(string)$row['model_code'],'result'=>'skipped_duplicate','message'=>$message];
                continue;
            }
            if (!empty($status['full'])) {
                $summary['skipped_full']++;
                $message = '配件位已满，跳过';
                $log->execute([(int)$accessory['id'],(int)$row['id'],(int)$row['series_id'],'skipped_full',$message,$oldJson,$oldJson,$userId]);
                $summary['items'][] = ['variant_id'=>(int)$row['id'],'name'=>(string)$row['name'],'model'=>(string)$row['model_code'],'result'=>'skipped_full','message'=>$message];
                continue;
            }
            $items[] = $payload;
            $items = array_slice($items, 0, 12);
            $newJson = json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '[]';
            $update->execute([$newJson, (int)$row['id']]);
            $summary['added']++;
            $message = '已追加到空配件位';
            $log->execute([(int)$accessory['id'],(int)$row['id'],(int)$row['series_id'],'added',$message,$oldJson,$newJson,$userId]);
            $summary['items'][] = ['variant_id'=>(int)$row['id'],'name'=>(string)$row['name'],'model'=>(string)$row['model_code'],'result'=>'added','message'=>$message];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $summary;
}
