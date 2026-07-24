<?php

declare(strict_types=1);

require_once __DIR__ . '/product_hierarchy.php';

function web_home_product_publish_ready(PDO $pdo): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('web_home_product_tabs','web_home_product_publications','web_home_product_publication_tabs')");
    $stmt->execute();
    return (int)$stmt->fetchColumn() === 3;
}

/**
 * V6.12 homepage product publishing.
 *
 * This layer stores only homepage presentation relationships. It never alters
 * the product/series records, uploaded files, sync tokens or internal APIs.
 */
function web_home_product_publish_migrate(PDO $pdo): void
{
    web_product_hierarchy_migrate($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_home_product_tabs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        tab_key VARCHAR(120) NOT NULL UNIQUE,
        label VARCHAR(190) NOT NULL,
        category_slug VARCHAR(160) NOT NULL DEFAULT '',
        is_all TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_home_product_tabs_sort (is_active, sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_home_product_publications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        source_type VARCHAR(20) NOT NULL,
        source_id BIGINT UNSIGNED NOT NULL,
        publish_status VARCHAR(20) NOT NULL DEFAULT 'draft',
        show_in_all TINYINT(1) NOT NULL DEFAULT 1,
        override_title VARCHAR(255) NOT NULL DEFAULT '',
        override_tag VARCHAR(160) NOT NULL DEFAULT '',
        override_text VARCHAR(700) NOT NULL DEFAULT '',
        override_image VARCHAR(500) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_home_product_source (source_type, source_id),
        INDEX idx_home_product_publish_sort (publish_status, sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_home_product_publication_tabs (
        publication_id BIGINT UNSIGNED NOT NULL,
        tab_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (publication_id, tab_id),
        INDEX idx_home_product_publication_tab (tab_id, publication_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $count = (int)$pdo->query('SELECT COUNT(*) FROM web_home_product_tabs')->fetchColumn();
    if ($count === 0) {
        $tabs = [];
        try {
            $stmt = $pdo->prepare("SELECT content_json FROM web_content_blocks WHERE content_key='products' LIMIT 1");
            $stmt->execute();
            $json = $stmt->fetchColumn();
            $block = is_string($json) ? json_decode($json, true) : null;
            if (is_array($block) && is_array($block['tabs'] ?? null)) {
                foreach ($block['tabs'] as $row) {
                    if (!is_array($row)) continue;
                    $key = web_home_product_key((string)($row['key'] ?? ''));
                    $label = trim((string)($row['label'] ?? ''));
                    if ($key !== '' && $label !== '') $tabs[] = [$key, $label];
                }
            }
        } catch (Throwable $e) {
            // Fall through to safe defaults.
        }
        if (!$tabs) {
            $tabs = [
                ['all','ALL'],
                ['track-light','TRACK LIGHT'],
                ['recessed-downlight','RECESSED DOWNLIGHT'],
                ['surface-mounted-lighting','SURFACE MOUNTED LIGHTING'],
                ['magnetic-lighting','MAGNETIC LIGHTING'],
                ['linear','LINEAR'],
                ['pendant','PENDANT'],
                ['led-strip-profile','LED STRIP & PROFILE'],
                ['outdoor','OUTDOOR'],
            ];
        }
        $seen = [];
        $categoryMap = [
            'track'=>'track-lights','track-light'=>'track-lights','track-lights'=>'track-lights',
            'downlight'=>'downlights','downlights'=>'downlights','recessed-downlight'=>'downlights','surface-mounted-lighting'=>'downlights',
            'magnetic'=>'magnetic-systems','magnetic-lighting'=>'magnetic-systems','magnetic-systems'=>'magnetic-systems',
            'linear'=>'linear-lighting','linear-lighting'=>'linear-lighting',
            'outdoor'=>'outdoor-lighting','outdoor-lighting'=>'outdoor-lighting',
        ];
        $insert = $pdo->prepare('INSERT IGNORE INTO web_home_product_tabs (tab_key,label,category_slug,is_all,is_active,sort_order) VALUES (?,?,?,?,?,?)');
        $order = 0;
        foreach ($tabs as [$key,$label]) {
            $key = web_home_product_key((string)$key);
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $insert->execute([$key,(string)$label,$categoryMap[$key] ?? '',$key === 'all' ? 1 : 0,1,$order]);
            $order += 10;
        }
        if (!isset($seen['all'])) {
            $pdo->prepare('INSERT IGNORE INTO web_home_product_tabs (tab_key,label,category_slug,is_all,is_active,sort_order) VALUES (?,?,?,?,?,?)')->execute(['all','ALL','',1,1,-10]);
        }
    }
}

function web_home_product_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function web_home_product_tabs(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT t.*, (SELECT COUNT(*) FROM web_home_product_publication_tabs pt WHERE pt.tab_id=t.id) AS usage_count FROM web_home_product_tabs t';
    if ($activeOnly) $sql .= ' WHERE t.is_active=1';
    $sql .= ' ORDER BY t.is_all DESC,t.sort_order ASC,t.id ASC';
    return $pdo->query($sql)->fetchAll() ?: [];
}

function web_home_product_tab(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM web_home_product_tabs WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function web_home_product_save_tab(PDO $pdo, array $data, int $id = 0): int
{
    $label = trim((string)($data['label'] ?? ''));
    if ($label === '') throw new RuntimeException('请填写选项卡名称。');
    $key = web_home_product_key((string)($data['tab_key'] ?? ''));
    if ($key === '') $key = web_home_product_key($label);
    if ($key === '') throw new RuntimeException('选项卡键无效。');
    $isAll = !empty($data['is_all']) || $key === 'all';
    if ($isAll) $key = 'all';
    $values = [
        $key,
        $label,
        trim((string)($data['category_slug'] ?? '')),
        $isAll ? 1 : 0,
        !empty($data['is_active']) ? 1 : 0,
        (int)($data['sort_order'] ?? 0),
    ];
    try {
        if ($id > 0) {
            $values[] = $id;
            $pdo->prepare('UPDATE web_home_product_tabs SET tab_key=?,label=?,category_slug=?,is_all=?,is_active=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($values);
        } else {
            $pdo->prepare('INSERT INTO web_home_product_tabs (tab_key,label,category_slug,is_all,is_active,sort_order) VALUES (?,?,?,?,?,?)')->execute($values);
            $id = (int)$pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') throw new RuntimeException('选项卡键已存在，请换一个键。');
        throw $e;
    }
    if ($isAll) {
        $pdo->prepare('UPDATE web_home_product_tabs SET is_all=0 WHERE id<>?')->execute([$id]);
    }
    return $id;
}

function web_home_product_delete_tab(PDO $pdo, int $id): void
{
    $tab = web_home_product_tab($pdo, $id);
    if (!$tab) throw new RuntimeException('选项卡不存在。');
    if (!empty($tab['is_all']) || (string)$tab['tab_key'] === 'all') throw new RuntimeException('ALL 选项卡不能删除。');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM web_home_product_publication_tabs WHERE tab_id=?');
    $stmt->execute([$id]);
    $count = (int)$stmt->fetchColumn();
    if ($count > 0) throw new RuntimeException('这个选项卡正在被 '.$count.' 个首页项目使用，请先移动或取消这些项目。');
    $pdo->prepare('DELETE FROM web_home_product_tabs WHERE id=?')->execute([$id]);
}

function web_home_product_save_tab_order(PDO $pdo, array $ids): void
{
    $stmt = $pdo->prepare('UPDATE web_home_product_tabs SET sort_order=? WHERE id=?');
    $order = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) continue;
        $stmt->execute([$order,$id]);
        $order += 10;
    }
}

function web_home_product_source(PDO $pdo, string $type, int $id, bool $publishedOnly = false): ?array
{
    if ($type === 'series') {
        $row = web_product_series_find($pdo, $id, $publishedOnly);
        if (!$row) return null;
        return [
            'source_type'=>'series','source_id'=>(int)$row['id'],'name'=>(string)$row['name'],
            'series_name'=>(string)($row['series_name'] ?? ''),'model_code'=>(string)($row['model_code'] ?? ''),
            'slug'=>(string)$row['slug'],'is_published'=>(int)$row['is_published'],
            'title'=>(string)$row['name'],
            'tag'=>trim((string)($row['family_label'] ?? '')) ?: (trim((string)($row['series_name'] ?? '')) ?: 'Series'),
            'text'=>trim((string)($row['card_subtitle'] ?? '')) ?: trim((string)($row['short_description'] ?? '')),
            'image'=>(string)($row['cover_image'] ?? ''),
            'url'=>'series.php?slug='.rawurlencode((string)$row['slug']),
            'category_slug'=>(string)($row['category_slug'] ?? ''),
        ];
    }
    if ($type === 'variant') {
        $row = web_product_variant_find($pdo, $id, $publishedOnly);
        if (!$row) return null;
        $series = web_product_series_find($pdo, (int)$row['series_id'], $publishedOnly);
        if (!$series) return null;
        return [
            'source_type'=>'variant','source_id'=>(int)$row['id'],'name'=>(string)$row['name'],
            'series_name'=>(string)($series['series_name'] ?: $series['name']),'model_code'=>(string)($row['model_code'] ?? ''),
            'slug'=>(string)$row['slug'],'is_published'=>(int)$row['is_published'] && (int)$series['is_published'],
            'title'=>(string)$row['name'],
            'tag'=>trim((string)($row['model_code'] ?? '')) ?: (trim((string)($series['series_name'] ?? '')) ?: 'Product'),
            'text'=>trim((string)($row['short_description'] ?? '')) ?: trim((string)($series['short_description'] ?? '')),
            'image'=>trim((string)($row['cover_image'] ?? '')) ?: (string)($series['cover_image'] ?? ''),
            'url'=>'product.php?slug='.rawurlencode((string)$row['slug']),
            'category_slug'=>(string)($series['category_slug'] ?? ''),
        ];
    }
    return null;
}

function web_home_product_publication(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM web_home_product_publications WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $tabs = $pdo->prepare('SELECT tab_id FROM web_home_product_publication_tabs WHERE publication_id=? ORDER BY tab_id');
    $tabs->execute([$id]);
    $row['tab_ids'] = array_map('intval', $tabs->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $row['source'] = web_home_product_source($pdo, (string)$row['source_type'], (int)$row['source_id'], false);
    return $row;
}

function web_home_product_publication_by_source(PDO $pdo, string $type, int $sourceId): ?array
{
    $stmt = $pdo->prepare('SELECT id FROM web_home_product_publications WHERE source_type=? AND source_id=? LIMIT 1');
    $stmt->execute([$type,$sourceId]);
    $id = (int)$stmt->fetchColumn();
    return $id > 0 ? web_home_product_publication($pdo, $id) : null;
}

function web_home_product_save_publication(PDO $pdo, array $data, int $id = 0): int
{
    $sourceType = (string)($data['source_type'] ?? 'series');
    if (!in_array($sourceType, ['series','variant'], true)) throw new RuntimeException('首页来源类型无效。');
    $sourceId = (int)($data['source_id'] ?? 0);
    $source = web_home_product_source($pdo, $sourceType, $sourceId, false);
    if (!$source) throw new RuntimeException('对应的系列或具体产品不存在。');
    $status = (string)($data['publish_status'] ?? 'draft');
    if (!in_array($status, ['draft','published','disabled'], true)) $status = 'draft';
    if ($status === 'published' && empty($source['is_published'])) {
        throw new RuntimeException('产品本身尚未发布，不能发布到首页。请先发布产品，或先保存为首页草稿。');
    }
    if ($status === 'published' && trim((string)($data['override_image'] ?? '')) === '' && trim((string)($source['image'] ?? '')) === '') {
        throw new RuntimeException('该产品没有主图，请先补产品主图或上传首页专用封面图。');
    }
    $tabIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['tab_ids'] ?? [])), static fn(int $v): bool => $v > 0)));
    if ($status === 'published' && !$tabIds && empty($data['show_in_all'])) {
        throw new RuntimeException('请至少选择一个首页选项卡，或允许显示在 ALL。');
    }
    if ($tabIds) {
        $marks = implode(',', array_fill(0, count($tabIds), '?'));
        $stmt = $pdo->prepare('SELECT id FROM web_home_product_tabs WHERE id IN ('.$marks.') AND is_all=0');
        $stmt->execute($tabIds);
        $tabIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    $values = [
        $sourceType,$sourceId,$status,!empty($data['show_in_all'])?1:0,
        trim((string)($data['override_title'] ?? '')),
        trim((string)($data['override_tag'] ?? '')),
        trim((string)($data['override_text'] ?? '')),
        trim((string)($data['override_image'] ?? '')),
        (int)($data['sort_order'] ?? 0),
    ];
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $values[] = $id;
            $pdo->prepare('UPDATE web_home_product_publications SET source_type=?,source_id=?,publish_status=?,show_in_all=?,override_title=?,override_tag=?,override_text=?,override_image=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($values);
        } else {
            $existing = web_home_product_publication_by_source($pdo,$sourceType,$sourceId);
            if ($existing) throw new RuntimeException('这个系列或具体产品已经加入首页，请直接编辑已有项目。');
            $pdo->prepare('INSERT INTO web_home_product_publications (source_type,source_id,publish_status,show_in_all,override_title,override_tag,override_text,override_image,sort_order) VALUES (?,?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM web_home_product_publication_tabs WHERE publication_id=?')->execute([$id]);
        if ($tabIds) {
            $insert = $pdo->prepare('INSERT IGNORE INTO web_home_product_publication_tabs (publication_id,tab_id) VALUES (?,?)');
            foreach ($tabIds as $tabId) $insert->execute([$id,$tabId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && (string)$e->getCode() === '23000') throw new RuntimeException('这个系列或具体产品已经加入首页。');
        throw $e;
    }
    return $id;
}

function web_home_product_delete_publication(PDO $pdo, int $id): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM web_home_product_publication_tabs WHERE publication_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM web_home_product_publications WHERE id=?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function web_home_product_save_item_order(PDO $pdo, array $ids): void
{
    $stmt = $pdo->prepare('UPDATE web_home_product_publications SET sort_order=? WHERE id=?');
    $order = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) continue;
        $stmt->execute([$order,$id]);
        $order += 10;
    }
}

function web_home_product_admin_rows(PDO $pdo): array
{
    $rows = $pdo->query('SELECT * FROM web_home_product_publications ORDER BY sort_order,id')->fetchAll() ?: [];
    $tabsByPublication = [];
    $stmt = $pdo->query('SELECT pt.publication_id,t.id,t.label,t.tab_key,t.is_active FROM web_home_product_publication_tabs pt JOIN web_home_product_tabs t ON t.id=pt.tab_id ORDER BY t.sort_order,t.id');
    foreach (($stmt->fetchAll() ?: []) as $row) $tabsByPublication[(int)$row['publication_id']][] = $row;
    $result = [];
    foreach ($rows as $row) {
        $source = web_home_product_source($pdo,(string)$row['source_type'],(int)$row['source_id'],false);
        $row['source'] = $source;
        $row['tabs'] = $tabsByPublication[(int)$row['id']] ?? [];
        $result[] = $row;
    }
    return $result;
}

function web_home_product_public_data(PDO $pdo): array
{
    $tabs = web_home_product_tabs($pdo, true);
    $rows = web_home_product_admin_rows($pdo);
    $items = [];
    $usedTabKeys = [];
    foreach ($rows as $row) {
        if ((string)$row['publish_status'] !== 'published') continue;
        $source = $row['source'] ?? null;
        if (!is_array($source) || empty($source['is_published'])) continue;
        $keys = [];
        foreach (($row['tabs'] ?? []) as $tab) {
            if (empty($tab['is_active']) && array_key_exists('is_active',$tab)) continue;
            $key = web_home_product_key((string)($tab['tab_key'] ?? ''));
            if ($key === '' || $key === 'all') continue;
            $keys[] = $key;
            $usedTabKeys[$key] = true;
        }
        $title = trim((string)$row['override_title']) ?: (string)$source['title'];
        $tag = trim((string)$row['override_tag']) ?: (string)$source['tag'];
        $text = trim((string)$row['override_text']) ?: (string)$source['text'];
        $image = trim((string)$row['override_image']) ?: (string)$source['image'];
        if ($title === '' || $image === '') continue;
        $items[] = [
            'active'=>1,
            'featured'=>!empty($row['show_in_all']) ? 1 : 0,
            'title'=>$title,
            'type'=>$tag,
            'text'=>$text,
            'image'=>$image,
            'url'=>(string)$source['url'],
            'categories'=>implode(' ',array_values(array_unique($keys))),
            'source_type'=>(string)$row['source_type'],
            'source_id'=>(int)$row['source_id'],
        ];
    }
    if (!$items) return ['tabs'=>[],'items'=>[],'dynamic'=>false];
    $publicTabs = [];
    foreach ($tabs as $tab) {
        $key = web_home_product_key((string)$tab['tab_key']);
        if ($key === 'all' || !empty($usedTabKeys[$key])) {
            $publicTabs[] = ['key'=>$key,'label'=>(string)$tab['label']];
        }
    }
    if (!$publicTabs || ($publicTabs[0]['key'] ?? '') !== 'all') array_unshift($publicTabs,['key'=>'all','label'=>'ALL']);
    return ['tabs'=>$publicTabs,'items'=>$items,'dynamic'=>true];
}

function web_home_product_bulk_add_published_series(PDO $pdo): int
{
    $tabs = web_home_product_tabs($pdo, true);
    $tabByCategory = [];
    foreach ($tabs as $tab) {
        if (!empty($tab['is_all'])) continue;
        $category = trim((string)$tab['category_slug']);
        if ($category !== '') $tabByCategory[$category] = (int)$tab['id'];
    }
    $seriesRows = $pdo->query('SELECT id,category_slug FROM web_products WHERE is_published=1 ORDER BY sort_order,id')->fetchAll() ?: [];
    $count = 0;
    $nextOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),-10)+10 FROM web_home_product_publications')->fetchColumn();
    foreach ($seriesRows as $series) {
        if (web_home_product_publication_by_source($pdo,'series',(int)$series['id'])) continue;
        $tabIds = [];
        if (isset($tabByCategory[(string)$series['category_slug']])) $tabIds[] = $tabByCategory[(string)$series['category_slug']];
        web_home_product_save_publication($pdo,[
            'source_type'=>'series','source_id'=>(int)$series['id'],'publish_status'=>'draft','show_in_all'=>1,
            'tab_ids'=>$tabIds,'sort_order'=>$nextOrder + ($count * 10),
        ]);
        $count++;
    }
    return $count;
}
