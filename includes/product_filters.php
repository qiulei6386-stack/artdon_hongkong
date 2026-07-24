<?php

declare(strict_types=1);

require_once __DIR__ . '/product_hierarchy.php';

/**
 * V6.10 dynamic product filter library.
 *
 * Catalogue cards (web_products) remain product series. Concrete products are
 * stored in web_product_variants. Filter values are attached only to concrete
 * products, so selecting a filter can return exact models instead of an entire
 * series that may contain mixed specifications.
 */

function web_product_filters_migrate(PDO $pdo): void
{
    // The hierarchy and filter seed flags use web_system_settings. Create only
    // the missing table here so the public catalogue can migrate safely even
    // before an administrator has opened the general settings area.
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_system_settings (
        setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
        setting_value LONGTEXT NOT NULL,
        is_secret TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    web_product_hierarchy_migrate($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_product_filter_groups (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        slug VARCHAR(160) NOT NULL UNIQUE,
        description VARCHAR(500) NOT NULL DEFAULT '',
        input_type VARCHAR(20) NOT NULL DEFAULT 'checkbox',
        category_slugs_json LONGTEXT NULL,
        is_frontend TINYINT(1) NOT NULL DEFAULT 1,
        is_default_open TINYINT(1) NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_filter_group_front (is_active, is_frontend, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_product_filter_options (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        group_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        slug VARCHAR(190) NOT NULL,
        description VARCHAR(500) NOT NULL DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_filter_option_slug (group_id, slug),
        INDEX idx_filter_option_sort (group_id, is_active, sort_order),
        CONSTRAINT fk_filter_option_group FOREIGN KEY (group_id) REFERENCES web_product_filter_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_product_variant_filter_values (
        variant_id BIGINT UNSIGNED NOT NULL,
        option_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (variant_id, option_id),
        INDEX idx_variant_filter_option (option_id, variant_id),
        CONSTRAINT fk_variant_filter_variant FOREIGN KEY (variant_id) REFERENCES web_product_variants(id) ON DELETE CASCADE,
        CONSTRAINT fk_variant_filter_option FOREIGN KEY (option_id) REFERENCES web_product_filter_options(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    web_product_filters_seed($pdo);
    web_product_filters_import_legacy_once($pdo);
}

function web_product_filter_group_slug(string $value): string
{
    $slug = web_product_slug($value);
    return substr($slug, 0, 160);
}

function web_product_filter_option_slug(string $value): string
{
    $slug = web_product_slug($value);
    return substr($slug, 0, 190);
}

function web_product_filter_decode_categories(mixed $value): array
{
    return web_product_decode($value);
}

function web_product_filters_seed(PDO $pdo): void
{
    if ((string)web_setting_get($pdo, 'v610_filter_library_seeded', '') === '1') return;

    $groups = [
        ['Application', 'application', 'Where the product is commonly specified.', 'checkbox', 1, 1, 10],
        ['Mounting', 'mounting', 'Installation or mounting method.', 'checkbox', 1, 1, 20],
        ['Product Type', 'product-type', 'Concrete luminaire type.', 'checkbox', 1, 1, 30],
        ['Optics', 'optics', 'Optical function and visual comfort.', 'checkbox', 1, 1, 40],
        ['Beam Angle', 'beam-angle', 'Available light distribution.', 'checkbox', 1, 1, 50],
        ['IP Rating', 'ip-rating', 'Ingress protection rating.', 'checkbox', 1, 1, 60],
        ['Control', 'control', 'Dimming and control protocol.', 'checkbox', 0, 0, 70],
        ['CCT', 'cct', 'Correlated colour temperature.', 'checkbox', 0, 0, 80],
        ['CRI', 'cri', 'Colour rendering index.', 'checkbox', 0, 0, 90],
        ['Finish', 'finish', 'Standard finish or colour.', 'checkbox', 0, 0, 100],
        ['Power', 'power', 'Nominal power.', 'checkbox', 0, 0, 110],
        ['Voltage', 'voltage', 'Input voltage.', 'checkbox', 0, 0, 120],
        ['Shape', 'shape', 'Product shape.', 'checkbox', 0, 0, 130],
    ];
    $insertGroup = $pdo->prepare('INSERT IGNORE INTO web_product_filter_groups (name,slug,description,input_type,is_frontend,is_default_open,is_active,sort_order,category_slugs_json) VALUES (?,?,?,?,?,?,1,?,?)');
    foreach ($groups as [$name,$slug,$description,$inputType,$frontend,$open,$sort]) {
        $insertGroup->execute([$name,$slug,$description,$inputType,$frontend,$open,$sort,'[]']);
    }

    $options = [
        'application' => ['Retail','Hospitality','Museum','Office','Residential','Facade','Landscape','Outdoor'],
        'mounting' => ['Recessed','Surface','Track mounted','Magnetic track','Pendant','Wall mounted','Ground mounted','Bracket'],
        'product-type' => ['Downlight','Spotlight','Track Light','Linear Light','Wallwasher','Grille','Projector','In-ground Light','Bollard','Floodlight'],
        'optics' => ['Fixed','Adjustable','Zoomable','Wallwash','Dark light','Low glare','Anti-glare'],
        'beam-angle' => ['3°','8°','12°','15°','24°','36°','60°','Asymmetric'],
        'ip-rating' => ['IP20','IP44','IP54','IP65','IP67'],
        'control' => ['On/Off','TRIAC','0–10V','DALI','Casambi','Bluetooth','Tunable White'],
        'cct' => ['2700K','3000K','3500K','4000K','5000K'],
        'cri' => ['CRI80','CRI90','CRI95'],
        'finish' => ['White','Black','Grey','Custom'],
        'voltage' => ['220-240V','110-240V','48V DC','24V DC'],
        'shape' => ['Round','Square','Linear','Rectangular'],
    ];
    $groupStmt = $pdo->prepare('SELECT id FROM web_product_filter_groups WHERE slug=? LIMIT 1');
    $insertOption = $pdo->prepare('INSERT IGNORE INTO web_product_filter_options (group_id,name,slug,sort_order,is_active) VALUES (?,?,?,?,1)');
    foreach ($options as $groupSlug => $values) {
        $groupStmt->execute([$groupSlug]);
        $groupId = (int)$groupStmt->fetchColumn();
        if ($groupId <= 0) continue;
        foreach ($values as $index => $name) {
            $insertOption->execute([$groupId,$name,web_product_filter_option_slug($name),($index+1)*10]);
        }
    }
    web_setting_set($pdo, 'v610_filter_library_seeded', '1');
}

function web_product_filter_group_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare("SELECT g.*, (SELECT COUNT(*) FROM web_product_filter_options o WHERE o.group_id=g.id) AS option_count, (SELECT COUNT(*) FROM web_product_variant_filter_values fv JOIN web_product_filter_options o2 ON o2.id=fv.option_id WHERE o2.group_id=g.id) AS usage_count FROM web_product_filter_groups g WHERE g.slug=? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['category_slugs'] = web_product_filter_decode_categories($row['category_slugs_json'] ?? '[]');
    return $row;
}

function web_product_filter_group(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT g.*, (SELECT COUNT(*) FROM web_product_filter_options o WHERE o.group_id=g.id) AS option_count, (SELECT COUNT(*) FROM web_product_variant_filter_values fv JOIN web_product_filter_options o2 ON o2.id=fv.option_id WHERE o2.group_id=g.id) AS usage_count FROM web_product_filter_groups g WHERE g.id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    $row['category_slugs'] = web_product_filter_decode_categories($row['category_slugs_json'] ?? '[]');
    return $row;
}

function web_product_filter_option(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT o.*,g.name AS group_name,g.slug AS group_slug FROM web_product_filter_options o JOIN web_product_filter_groups g ON g.id=o.group_id WHERE o.id=? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function web_product_filter_groups(PDO $pdo, bool $activeOnly = false, bool $frontendOnly = false): array
{
    $sql = "SELECT g.*,
        (SELECT COUNT(*) FROM web_product_filter_options o WHERE o.group_id=g.id) AS option_count,
        (SELECT COUNT(*) FROM web_product_variant_filter_values fv JOIN web_product_filter_options o2 ON o2.id=fv.option_id WHERE o2.group_id=g.id) AS usage_count
        FROM web_product_filter_groups g WHERE 1=1";
    if ($activeOnly) $sql .= ' AND g.is_active=1';
    if ($frontendOnly) $sql .= ' AND g.is_frontend=1';
    $sql .= ' ORDER BY g.sort_order ASC,g.id ASC';
    $rows = $pdo->query($sql)->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $row['category_slugs'] = web_product_filter_decode_categories($row['category_slugs_json'] ?? '[]');
    }
    unset($row);
    return $rows;
}

function web_product_filter_option_rows(PDO $pdo, int $groupId, bool $activeOnly = false): array
{
    $sql = "SELECT o.*,
        (SELECT COUNT(*) FROM web_product_variant_filter_values fv WHERE fv.option_id=o.id) AS usage_count
        FROM web_product_filter_options o WHERE o.group_id=?";
    if ($activeOnly) $sql .= ' AND o.is_active=1';
    $sql .= ' ORDER BY o.sort_order ASC,o.id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$groupId]);
    return $stmt->fetchAll() ?: [];
}

function web_product_filter_save_group(PDO $pdo, array $data, int $id = 0): int
{
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') throw new RuntimeException('筛选组名称不能为空。');
    $slug = web_product_filter_group_slug(trim((string)($data['slug'] ?? '')) ?: $name);
    $inputType = in_array((string)($data['input_type'] ?? ''), ['checkbox','radio'], true) ? (string)$data['input_type'] : 'checkbox';
    $categories = web_product_lines($data['category_slugs'] ?? []);
    $categoryJson = web_product_json($categories);
    $values = [
        $name,$slug,trim((string)($data['description'] ?? '')),$inputType,$categoryJson,
        !empty($data['is_frontend'])?1:0,!empty($data['is_default_open'])?1:0,!empty($data['is_active'])?1:0,(int)($data['sort_order'] ?? 0),
    ];
    try {
        if ($id > 0) {
            $values[] = $id;
            $pdo->prepare('UPDATE web_product_filter_groups SET name=?,slug=?,description=?,input_type=?,category_slugs_json=?,is_frontend=?,is_default_open=?,is_active=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($values);
            return $id;
        }
        $pdo->prepare('INSERT INTO web_product_filter_groups (name,slug,description,input_type,category_slugs_json,is_frontend,is_default_open,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?)')->execute($values);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') throw new RuntimeException('筛选组标识已存在，请更换名称或 Slug。');
        throw $e;
    }
}

function web_product_filter_save_option(PDO $pdo, array $data, int $id = 0): int
{
    $groupId = (int)($data['group_id'] ?? 0);
    if ($groupId <= 0 || !web_product_filter_group($pdo, $groupId)) throw new RuntimeException('请选择有效的筛选组。');
    $name = trim((string)($data['name'] ?? ''));
    if ($name === '') throw new RuntimeException('筛选选项名称不能为空。');
    $slug = web_product_filter_option_slug(trim((string)($data['slug'] ?? '')) ?: $name);
    $values = [$groupId,$name,$slug,trim((string)($data['description'] ?? '')),!empty($data['is_active'])?1:0,(int)($data['sort_order'] ?? 0)];
    try {
        if ($id > 0) {
            $values[] = $id;
            $pdo->prepare('UPDATE web_product_filter_options SET group_id=?,name=?,slug=?,description=?,is_active=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($values);
            return $id;
        }
        $pdo->prepare('INSERT INTO web_product_filter_options (group_id,name,slug,description,is_active,sort_order) VALUES (?,?,?,?,?,?)')->execute($values);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') throw new RuntimeException('同一筛选组内不能有重复选项。');
        throw $e;
    }
}

function web_product_filter_delete_group(PDO $pdo, int $id): void
{
    $group = web_product_filter_group($pdo, $id);
    if (!$group) throw new RuntimeException('筛选组不存在。');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM web_product_variant_filter_values fv JOIN web_product_filter_options o ON o.id=fv.option_id WHERE o.group_id=?');
    $stmt->execute([$id]);
    $usage = (int)$stmt->fetchColumn();
    if ($usage > 0) throw new RuntimeException('该筛选组仍被 '.$usage.' 个产品选项关联使用，请先停用或清除关联。');
    $pdo->prepare('DELETE FROM web_product_filter_groups WHERE id=?')->execute([$id]);
}

function web_product_filter_delete_option(PDO $pdo, int $id): void
{
    $option = web_product_filter_option($pdo, $id);
    if (!$option) throw new RuntimeException('筛选选项不存在。');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM web_product_variant_filter_values WHERE option_id=?');
    $stmt->execute([$id]);
    $usage = (int)$stmt->fetchColumn();
    if ($usage > 0) throw new RuntimeException('“'.$option['name'].'”正在被 '.$usage.' 个具体产品使用，不能直接删除。可以先停用。');
    $pdo->prepare('DELETE FROM web_product_filter_options WHERE id=?')->execute([$id]);
}

function web_product_filter_move(PDO $pdo, string $table, int $id, string $direction, ?int $groupId = null): void
{
    if (!in_array($table, ['web_product_filter_groups','web_product_filter_options'], true)) throw new RuntimeException('排序对象无效。');
    $currentStmt = $pdo->prepare('SELECT id,sort_order'.($table==='web_product_filter_options'?',group_id':'').' FROM '.$table.' WHERE id=? LIMIT 1');
    $currentStmt->execute([$id]);
    $current = $currentStmt->fetch();
    if (!$current) throw new RuntimeException('排序记录不存在。');
    $operator = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';
    $params = [(int)$current['sort_order']];
    $sql = 'SELECT id,sort_order FROM '.$table.' WHERE sort_order '.$operator.' ?';
    if ($table === 'web_product_filter_options') {
        $sql .= ' AND group_id=?';
        $params[] = (int)($groupId ?: $current['group_id']);
    }
    $sql .= ' ORDER BY sort_order '.$order.',id '.$order.' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $other = $stmt->fetch();
    if (!$other) return;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE '.$table.' SET sort_order=? WHERE id=?')->execute([(int)$other['sort_order'],$id]);
        $pdo->prepare('UPDATE '.$table.' SET sort_order=? WHERE id=?')->execute([(int)$current['sort_order'],(int)$other['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function web_product_variant_filter_ids(PDO $pdo, int $variantId): array
{
    $stmt = $pdo->prepare('SELECT o.group_id,fv.option_id FROM web_product_variant_filter_values fv JOIN web_product_filter_options o ON o.id=fv.option_id WHERE fv.variant_id=? ORDER BY o.group_id,o.sort_order,o.id');
    $stmt->execute([$variantId]);
    $result = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $result[(int)$row['group_id']][] = (int)$row['option_id'];
    }
    return $result;
}

function web_product_variant_filter_save(PDO $pdo, int $variantId, mixed $optionIds): void
{
    if ($variantId <= 0 || !web_product_variant_find($pdo, $variantId, false)) throw new RuntimeException('具体产品不存在。');
    $flat = [];
    $walk = static function (mixed $value) use (&$flat, &$walk): void {
        if (is_array($value)) {
            foreach ($value as $item) $walk($item);
            return;
        }
        $id = (int)$value;
        if ($id > 0 && !in_array($id, $flat, true)) $flat[] = $id;
    };
    $walk($optionIds);

    $valid = [];
    if ($flat) {
        $marks = implode(',', array_fill(0, count($flat), '?'));
        $stmt = $pdo->prepare('SELECT id FROM web_product_filter_options WHERE id IN ('.$marks.')');
        $stmt->execute($flat);
        $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM web_product_variant_filter_values WHERE variant_id=?')->execute([$variantId]);
        if ($valid) {
            $insert = $pdo->prepare('INSERT IGNORE INTO web_product_variant_filter_values (variant_id,option_id) VALUES (?,?)');
            foreach ($valid as $optionId) $insert->execute([$variantId,$optionId]);
        }
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function web_product_variant_filter_summary(PDO $pdo, int $variantId, int $limit = 12): array
{
    $stmt = $pdo->prepare('SELECT g.name AS group_name,o.name AS option_name FROM web_product_variant_filter_values fv JOIN web_product_filter_options o ON o.id=fv.option_id JOIN web_product_filter_groups g ON g.id=o.group_id WHERE fv.variant_id=? ORDER BY g.sort_order,o.sort_order,o.id LIMIT '.max(1,min(50,$limit)));
    $stmt->execute([$variantId]);
    return $stmt->fetchAll() ?: [];
}

function web_product_filter_tree(PDO $pdo, bool $activeOnly = true, bool $frontendOnly = true, string $categorySlug = '', bool $onlyUsed = false): array
{
    $groups = web_product_filter_groups($pdo, $activeOnly, $frontendOnly);
    $counts = [];
    if ($onlyUsed) {
        $sql = 'SELECT o.id,COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN v.id END) AS usage_count FROM web_product_filter_options o LEFT JOIN web_product_variant_filter_values fv ON fv.option_id=o.id LEFT JOIN web_product_variants v ON v.id=fv.variant_id AND v.is_published=1 LEFT JOIN web_products s ON s.id=v.series_id AND s.is_published=1 WHERE 1=1';
        $params = [];
        if ($categorySlug !== '' && $categorySlug !== 'all') {
            $sql .= ' AND s.category_slug=?';
            $params[] = $categorySlug;
        }
        $sql .= ' GROUP BY o.id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() ?: [] as $row) $counts[(int)$row['id']] = (int)$row['usage_count'];
    }

    $tree = [];
    foreach ($groups as $group) {
        $scopes = $group['category_slugs'] ?? [];
        if ($categorySlug !== '' && $categorySlug !== 'all' && $scopes && !in_array($categorySlug, $scopes, true)) continue;
        $options = web_product_filter_option_rows($pdo, (int)$group['id'], $activeOnly);
        if ($onlyUsed) {
            foreach ($options as &$option) $option['usage_count'] = $counts[(int)$option['id']] ?? 0;
            unset($option);
            $options = array_values(array_filter($options, static fn(array $o): bool => (int)($o['usage_count'] ?? 0) > 0));
        }
        if ($frontendOnly && !$options) continue;
        $group['options'] = $options;
        $tree[] = $group;
    }
    return $tree;
}

function web_product_filter_request(mixed $raw): array
{
    if (!is_array($raw)) return [];
    $result = [];
    foreach ($raw as $groupSlug => $values) {
        $rawGroupSlug = trim((string)$groupSlug);
        if ($rawGroupSlug === '') continue;
        $groupSlug = web_product_filter_group_slug($rawGroupSlug);
        if ($groupSlug === '') continue;
        $clean = [];
        foreach ((array)$values as $value) {
            $rawValue = trim((string)$value);
            if ($rawValue === '') continue;
            $slug = web_product_filter_option_slug($rawValue);
            if ($slug !== '' && !in_array($slug, $clean, true)) $clean[] = $slug;
        }
        if ($clean) $result[$groupSlug] = $clean;
    }
    return $result;
}

function web_product_filter_resolve(PDO $pdo, array $selected): array
{
    $resolved = [];
    foreach ($selected as $groupSlug => $optionSlugs) {
        $group = web_product_filter_group_by_slug($pdo, (string)$groupSlug);
        if (!$group || empty($group['is_active']) || empty($group['is_frontend'])) continue;
        $optionSlugs = array_values(array_filter(array_map('strval',(array)$optionSlugs)));
        if (!$optionSlugs) continue;
        $marks = implode(',', array_fill(0, count($optionSlugs), '?'));
        $stmt = $pdo->prepare('SELECT id,slug,name FROM web_product_filter_options WHERE group_id=? AND is_active=1 AND slug IN ('.$marks.') ORDER BY sort_order,id');
        $stmt->execute(array_merge([(int)$group['id']], $optionSlugs));
        $options = $stmt->fetchAll() ?: [];
        if ($options) $resolved[(int)$group['id']] = ['group'=>$group,'options'=>$options];
    }
    return $resolved;
}

function web_product_filtered_variants(PDO $pdo, string $categorySlug, string $query, array $selected, string $sort = 'recommended'): array
{
    $sql = "SELECT v.*,
        s.name AS series_record_name,s.series_name AS series_display_name,s.slug AS series_slug,s.category_slug,
        s.cover_image AS series_cover_image,s.short_description AS series_short_description,
        s.is_featured AS series_is_featured,s.sort_order AS series_sort_order,
        c.name AS category_name
        FROM web_product_variants v
        JOIN web_products s ON s.id=v.series_id
        LEFT JOIN web_product_categories c ON c.slug=s.category_slug
        WHERE v.is_published=1 AND s.is_published=1";
    $params = [];
    if ($categorySlug !== '' && $categorySlug !== 'all') {
        $sql .= ' AND s.category_slug=?';
        $params[] = $categorySlug;
    }
    $query = trim($query);
    if ($query !== '') {
        $sql .= ' AND (v.name LIKE ? OR v.model_code LIKE ? OR v.size_name LIKE ? OR s.name LIKE ? OR s.series_name LIKE ? OR v.power_text LIKE ? OR v.ip_rating LIKE ?)';
        $like = '%'.$query.'%';
        array_push($params,$like,$like,$like,$like,$like,$like,$like);
    }
    $resolved = web_product_filter_resolve($pdo, $selected);
    $filterIndex = 0;
    foreach ($resolved as $groupId => $selection) {
        $optionIds = array_map(static fn(array $row): int => (int)$row['id'], $selection['options']);
        if (!$optionIds) continue;
        $marks = implode(',', array_fill(0, count($optionIds), '?'));
        $alias = 'fv'.$filterIndex++;
        $sql .= ' AND EXISTS (SELECT 1 FROM web_product_variant_filter_values '.$alias.' WHERE '.$alias.'.variant_id=v.id AND '.$alias.'.option_id IN ('.$marks.'))';
        array_push($params, ...$optionIds);
    }
    $order = match ($sort) {
        'featured' => 's.is_featured DESC,s.sort_order ASC,v.sort_order ASC,v.id DESC',
        'newest' => 'v.created_at DESC,v.id DESC',
        'name-asc' => 'v.name ASC,v.id ASC',
        'name-desc' => 'v.name DESC,v.id DESC',
        default => 's.sort_order ASC,v.sort_order ASC,v.id ASC',
    };
    $sql .= ' ORDER BY '.$order;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('web_product_variant_hydrate', $stmt->fetchAll() ?: []);
}

function web_product_variant_catalog_card(array $variant): array
{
    $seriesName = trim((string)($variant['series_display_name'] ?? '')) ?: trim((string)($variant['series_record_name'] ?? ''));
    $image = trim((string)($variant['cover_image'] ?? '')) ?: trim((string)($variant['series_cover_image'] ?? ''));
    $size = trim((string)($variant['dimensions'] ?? '')) ?: trim((string)($variant['size_name'] ?? ''));
    $output = trim((string)($variant['lumen_text'] ?? ''));
    if ($output === '') $output = trim((string)($variant['power_text'] ?? ''));
    $tags = array_slice(web_product_decode($variant['tags'] ?? ($variant['tags_json'] ?? '[]')),0,4);
    return [
        'name'=>(string)($variant['name'] ?? ''),
        'series_name'=>$seriesName,
        'model_code'=>trim((string)($variant['model_code'] ?? '')),
        'subtitle'=>trim((string)($variant['short_description'] ?? '')),
        'image'=>$image,
        'size_label'=>'Size',
        'size_value'=>$size,
        'output_label'=>'Output',
        'output_value'=>$output,
        'tags'=>$tags,
        'url'=>'product.php?slug='.rawurlencode((string)($variant['slug'] ?? '')),
    ];
}

function web_product_filters_import_legacy_once(PDO $pdo): void
{
    if ((string)web_setting_get($pdo, 'v610_filter_values_imported', '') === '1') return;

    $groupRows = web_product_filter_groups($pdo, false, false);
    $groupIds = [];
    foreach ($groupRows as $group) $groupIds[(string)$group['slug']] = (int)$group['id'];
    if (!$groupIds) return;

    $sql = "SELECT v.*,s.applications_json AS series_applications_json,s.shape_json AS series_shape_json,
        s.tags_json AS series_tags_json,s.category_slug,s.sub_category
        FROM web_product_variants v JOIN web_products s ON s.id=v.series_id";
    $variants = $pdo->query($sql)->fetchAll() ?: [];
    if (!$variants) {
        web_setting_set($pdo, 'v610_filter_values_imported', '1');
        return;
    }

    $optionLookup = [];
    $findOption = $pdo->prepare('SELECT id FROM web_product_filter_options WHERE group_id=? AND slug=? LIMIT 1');
    $insertOption = $pdo->prepare('INSERT INTO web_product_filter_options (group_id,name,slug,sort_order,is_active) VALUES (?,?,?,?,1)');
    $insertValue = $pdo->prepare('INSERT IGNORE INTO web_product_variant_filter_values (variant_id,option_id) VALUES (?,?)');

    $ensureOption = static function (string $groupSlug, string $name) use ($pdo,$groupIds,&$optionLookup,$findOption,$insertOption): int {
        $name = trim($name);
        if ($name === '' || empty($groupIds[$groupSlug])) return 0;
        $groupId = $groupIds[$groupSlug];
        $slug = web_product_filter_option_slug($name);
        $key = $groupId.':'.$slug;
        if (isset($optionLookup[$key])) return $optionLookup[$key];
        $findOption->execute([$groupId,$slug]);
        $id = (int)$findOption->fetchColumn();
        if ($id <= 0) {
            $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM web_product_filter_options WHERE group_id=?');
            $sortStmt->execute([$groupId]);
            $sort = (int)$sortStmt->fetchColumn();
            try {
                $insertOption->execute([$groupId,$name,$slug,$sort]);
                $id = (int)$pdo->lastInsertId();
            } catch (PDOException) {
                $findOption->execute([$groupId,$slug]);
                $id = (int)$findOption->fetchColumn();
            }
        }
        return $optionLookup[$key] = $id;
    };

    foreach ($variants as $variant) {
        $values = [
            'application'=>web_product_decode($variant['series_applications_json'] ?? '[]'),
            'mounting'=>web_product_decode($variant['mounting_json'] ?? '[]'),
            'beam-angle'=>web_product_decode($variant['beam_angle_json'] ?? '[]'),
            'control'=>web_product_decode($variant['dimming_json'] ?? '[]'),
            'cct'=>web_product_decode($variant['cct_json'] ?? '[]'),
            'cri'=>web_product_decode($variant['cri_json'] ?? '[]'),
            'finish'=>web_product_decode($variant['finish_json'] ?? '[]'),
            'voltage'=>web_product_decode($variant['voltage_json'] ?? '[]'),
            'shape'=>web_product_decode($variant['series_shape_json'] ?? '[]'),
            'power'=>web_product_lines($variant['power_text'] ?? ''),
            'ip-rating'=>web_product_lines($variant['ip_rating'] ?? ''),
        ];
        $categoryTypeMap = [
            'downlights'=>'Downlight','track-lights'=>'Track Light','magnetic-systems'=>'Magnetic Light',
            'linear-lighting'=>'Linear Light','outdoor-lighting'=>'Outdoor Light',
        ];
        $typeName = trim((string)($variant['sub_category'] ?? '')) ?: ($categoryTypeMap[(string)($variant['category_slug'] ?? '')] ?? '');
        if ($typeName !== '') $values['product-type'] = [$typeName];
        $optics = [];
        foreach (array_merge(web_product_decode($variant['tags_json'] ?? '[]'), web_product_decode($variant['series_tags_json'] ?? '[]')) as $tag) {
            if (preg_match('/adjust|fixed|zoom|wallwash|glare|optic|dark\s*light/i', $tag)) $optics[] = $tag;
        }
        if ($optics) $values['optics'] = array_values(array_unique($optics));

        foreach ($values as $groupSlug => $names) {
            foreach ($names as $name) {
                $optionId = $ensureOption($groupSlug, (string)$name);
                if ($optionId > 0) $insertValue->execute([(int)$variant['id'],$optionId]);
            }
        }
    }

    web_setting_set($pdo, 'v610_filter_values_imported', '1');
}
