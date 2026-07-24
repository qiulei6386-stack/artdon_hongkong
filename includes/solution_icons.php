<?php

declare(strict_types=1);

/**
 * Homepage application-solution SVG icon library.
 *
 * SVG is stored inline after strict allow-list sanitisation. The library does
 * not accept scripts, styles, event handlers, references or external assets.
 */

function web_solution_icon_defaults(): array
{
    return [
        [
            'icon_key' => 'retail',
            'label' => 'Retail / 零售',
            'is_system' => 1,
            'sort_order' => 10,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M4 7h16l-1 13H5L4 7Z"/><path d="M8 7a4 4 0 0 1 8 0"/><path d="M8 11h.01M16 11h.01"/></svg>',
        ],
        [
            'icon_key' => 'hospitality',
            'label' => 'Hospitality / 酒店',
            'is_system' => 1,
            'sort_order' => 20,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M4 21V5h16v16M8 9h2M14 9h2M8 13h2M14 13h2M9 21v-4h6v4"/></svg>',
        ],
        [
            'icon_key' => 'museum',
            'label' => 'Museum / 博物馆',
            'is_system' => 1,
            'sort_order' => 30,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="m3 9 9-5 9 5M5 10h14M6 10v8M10 10v8M14 10v8M18 10v8M3 20h18"/></svg>',
        ],
        [
            'icon_key' => 'office',
            'label' => 'Office / 办公',
            'is_system' => 1,
            'sort_order' => 40,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M4 21V6h10v15M14 10h6v11M8 10h2M8 14h2M8 18h2M17 14h1M17 18h1"/></svg>',
        ],
        [
            'icon_key' => 'airport',
            'label' => 'Airport / 机场',
            'is_system' => 0,
            'sort_order' => 50,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="m2 16 20-6-1-2-8 1-4-6-2 1 3 6-5 1-2-2-1 1 3 4-3 1v1Z"/><path d="M4 20h16"/></svg>',
        ],
        [
            'icon_key' => 'restaurant',
            'label' => 'Restaurant / 餐厅',
            'is_system' => 0,
            'sort_order' => 60,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M6 3v8M3 3v5a3 3 0 0 0 6 0V3M6 11v10M15 3v18M15 3c4 1 5 4 5 7h-5"/></svg>',
        ],
        [
            'icon_key' => 'showroom',
            'label' => 'Showroom / 展厅',
            'is_system' => 0,
            'sort_order' => 70,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M4 21V8l8-5 8 5v13H4Z"/><path d="M8 21v-7h8v7M8 10h.01M12 10h.01M16 10h.01"/></svg>',
        ],
        [
            'icon_key' => 'residential',
            'label' => 'Residential / 住宅',
            'is_system' => 0,
            'sort_order' => 80,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8M5 10v11h14V10M9 21v-7h6v7"/></svg>',
        ],
        [
            'icon_key' => 'facade',
            'label' => 'Facade / 建筑立面',
            'is_system' => 0,
            'sort_order' => 90,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M5 21V4h14v17M3 21h18M8 8h2M14 8h2M8 12h2M14 12h2M8 16h2M14 16h2"/></svg>',
        ],
        [
            'icon_key' => 'landscape',
            'label' => 'Landscape / 景观',
            'is_system' => 0,
            'sort_order' => 100,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M4 21h16M12 21v-7M7 14h10l-2-3h2l-3-4h2l-4-5-4 5h2l-3 4h2l-2 3Z"/></svg>',
        ],
        [
            'icon_key' => 'warehouse',
            'label' => 'Warehouse / 仓库',
            'is_system' => 0,
            'sort_order' => 110,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="m3 9 9-5 9 5v12H3V9Z"/><path d="M7 13h10v8H7zM7 16h10"/></svg>',
        ],
        [
            'icon_key' => 'school',
            'label' => 'School / 学校',
            'is_system' => 0,
            'sort_order' => 120,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="m3 10 9-5 9 5-9 5-9-5Z"/><path d="M7 13v4c3 2 7 2 10 0v-4M21 10v6"/></svg>',
        ],
        [
            'icon_key' => 'hospital',
            'label' => 'Hospital / 医院',
            'is_system' => 0,
            'sort_order' => 130,
            'svg_code' => '<svg viewBox="0 0 24 24"><path d="M4 21V5h16v16M9 8h6M12 5v6M8 15h2M14 15h2M9 21v-3h6v3"/></svg>',
        ],
    ];
}

function web_solution_icons_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_solution_icons (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        icon_key VARCHAR(80) NOT NULL UNIQUE,
        label VARCHAR(120) NOT NULL,
        svg_code MEDIUMTEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        created_by BIGINT NULL,
        updated_by BIGINT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_web_solution_icons_sort (sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $existingCount = (int)$pdo->query('SELECT COUNT(*) FROM web_solution_icons')->fetchColumn();
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_solution_icons (icon_key, label, svg_code, sort_order, is_system) VALUES (?, ?, ?, ?, ?)');
    foreach (web_solution_icon_defaults() as $item) {
        // Seed the full V6.9 library only once. Afterwards only the four protected
        // system icons are restored if someone removes them directly in MySQL.
        if ($existingCount > 0 && empty($item['is_system'])) {
            continue;
        }
        $stmt->execute([
            $item['icon_key'],
            $item['label'],
            web_solution_icon_sanitize((string)$item['svg_code']),
            (int)$item['sort_order'],
            (int)$item['is_system'],
        ]);
    }
}

function web_solution_icon_normalize_key(string $key): string
{
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9_-]+/', '-', $key) ?? '';
    $key = trim($key, '-_');
    return substr($key, 0, 80);
}

/**
 * Strict SVG allow-list sanitizer.
 *
 * The icon subset intentionally excludes defs/use/image/style/foreignObject,
 * hyperlinks and all URL-capable paint/filter attributes.
 */
function web_solution_icon_sanitize(string $svg): string
{
    $svg = trim($svg);
    if ($svg === '') {
        throw new InvalidArgumentException('SVG 内容不能为空。');
    }
    if (strlen($svg) > 60000) {
        throw new InvalidArgumentException('SVG 内容不能超过 60KB。');
    }
    if (str_contains($svg, "\0") || preg_match('/<!DOCTYPE|<!ENTITY|<\?|\?>/i', $svg)) {
        throw new InvalidArgumentException('SVG 含有禁止的声明、实体或处理指令。');
    }
    if (str_contains($svg, '&')) {
        throw new InvalidArgumentException('SVG 不允许使用实体引用，请直接填写路径和基础图形。');
    }

    $svg = preg_replace('/<!--.*?-->/s', '', $svg) ?? $svg;
    if (str_contains($svg, '<!--') || str_contains($svg, '-->')) {
        throw new InvalidArgumentException('SVG 注释格式不完整。');
    }

    preg_match_all('/<[^<>]+>|[^<>]+/s', $svg, $matches);
    $tokens = $matches[0] ?? [];
    if (!$tokens || implode('', $tokens) !== $svg) {
        throw new InvalidArgumentException('SVG 标签格式不正确。');
    }

    $allowedElements = [
        'svg' => true, 'g' => true, 'path' => true, 'circle' => true,
        'ellipse' => true, 'line' => true, 'polyline' => true,
        'polygon' => true, 'rect' => true, 'title' => true, 'desc' => true,
    ];
    $generalAttributes = [
        'fill' => true, 'fill-rule' => true, 'fill-opacity' => true,
        'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true,
        'stroke-linejoin' => true, 'stroke-miterlimit' => true,
        'stroke-opacity' => true, 'opacity' => true, 'transform' => true,
        'vector-effect' => true,
    ];
    $elementAttributes = [
        'svg' => ['viewbox'=>true, 'width'=>true, 'height'=>true, 'preserveaspectratio'=>true],
        'g' => [],
        'path' => ['d'=>true],
        'circle' => ['cx'=>true, 'cy'=>true, 'r'=>true],
        'ellipse' => ['cx'=>true, 'cy'=>true, 'rx'=>true, 'ry'=>true],
        'line' => ['x1'=>true, 'y1'=>true, 'x2'=>true, 'y2'=>true],
        'polyline' => ['points'=>true],
        'polygon' => ['points'=>true],
        'rect' => ['x'=>true, 'y'=>true, 'width'=>true, 'height'=>true, 'rx'=>true, 'ry'=>true],
        'title' => [],
        'desc' => [],
    ];

    $parseAttributes = static function (string $raw): array {
        $attributes = [];
        $offset = 0;
        $length = strlen($raw);
        while ($offset < $length) {
            if (preg_match('/\G\s+\z/A', $raw, $space, 0, $offset)) {
                $offset = $length;
                break;
            }
            if (!preg_match('/\G\s+([A-Za-z_:][A-Za-z0-9_.:-]*)\s*=\s*(["\'])(.*?)\2/s', $raw, $match, 0, $offset)) {
                throw new InvalidArgumentException('SVG 属性必须使用引号，且不能包含不完整代码。');
            }
            $offset += strlen($match[0]);
            $name = strtolower($match[1]);
            if (isset($attributes[$name])) {
                throw new InvalidArgumentException('SVG 含有重复属性：'.$match[1]);
            }
            $attributes[$name] = $match[3];
        }
        return $attributes;
    };

    $safeNumber = static fn(string $value): bool => (bool)preg_match('/^-?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i', trim($value));
    $safeValue = static function (string $name, string $value) use ($safeNumber): bool {
        $value = trim($value);
        if ($value === '' || strlen($value) > 10000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            return false;
        }
        if (str_contains($value, '\\') || preg_match('/(?:javascript\s*:|data\s*:|vbscript\s*:|url\s*\(|expression\s*\(|@import|https?\s*:|\/\/)/i', $value)) {
            return false;
        }
        if ($name === 'viewbox') {
            return (bool)preg_match('/^-?(?:\d+(?:\.\d*)?|\.\d+)\s+-?(?:\d+(?:\.\d*)?|\.\d+)\s+(?:\d+(?:\.\d*)?|\.\d+)\s+(?:\d+(?:\.\d*)?|\.\d+)$/', $value);
        }
        if ($name === 'd') {
            return (bool)preg_match('/^[0-9eE.,+\-\sMmZzLlHhVvCcSsQqTtAa]+$/', $value);
        }
        if ($name === 'points') {
            return (bool)preg_match('/^[0-9eE.,+\-\s]+$/', $value);
        }
        if ($name === 'transform') {
            return (bool)preg_match('/^(?:(?:matrix|translate|scale|rotate|skewX|skewY)\s*\([0-9eE.,+\-\s]+\)\s*)+$/', $value);
        }
        if (in_array($name, ['fill', 'stroke'], true)) {
            return (bool)preg_match('/^(?:none|currentColor|transparent|#[0-9a-fA-F]{3,8}|[A-Za-z]{1,30})$/', $value);
        }
        if ($name === 'fill-rule') {
            return in_array(strtolower($value), ['nonzero', 'evenodd'], true);
        }
        if ($name === 'stroke-linecap') {
            return in_array(strtolower($value), ['butt', 'round', 'square'], true);
        }
        if ($name === 'stroke-linejoin') {
            return in_array(strtolower($value), ['miter', 'round', 'bevel'], true);
        }
        if ($name === 'vector-effect') {
            return in_array(strtolower($value), ['none', 'non-scaling-stroke'], true);
        }
        if ($name === 'preserveaspectratio') {
            return (bool)preg_match('/^(?:none|x(?:Min|Mid|Max)Y(?:Min|Mid|Max)(?:\s+(?:meet|slice))?)$/', $value);
        }
        return $safeNumber($value);
    };

    $stack = [];
    $output = '';
    $rootSeen = false;
    foreach ($tokens as $token) {
        if ($token !== '' && $token[0] === '<') {
            if (preg_match('/^<\/\s*([A-Za-z][A-Za-z0-9]*)\s*>$/', $token, $closing)) {
                $tag = strtolower($closing[1]);
                if (!$stack || end($stack) !== $tag) {
                    throw new InvalidArgumentException('SVG 标签闭合顺序不正确。');
                }
                array_pop($stack);
                $output .= '</'.$tag.'>';
                continue;
            }
            if (!preg_match('/^<\s*([A-Za-z][A-Za-z0-9]*)(.*?)\s*(\/?)>$/s', $token, $opening)) {
                throw new InvalidArgumentException('SVG 包含无法识别的标签。');
            }
            $tag = strtolower($opening[1]);
            $selfClosing = $opening[3] === '/';
            if (!isset($allowedElements[$tag])) {
                throw new InvalidArgumentException('SVG 标签不受支持：'.$opening[1]);
            }
            if (!$rootSeen) {
                if ($tag !== 'svg') {
                    throw new InvalidArgumentException('SVG 根标签必须是 svg。');
                }
                $rootSeen = true;
            } elseif (!$stack) {
                throw new InvalidArgumentException('SVG 只能包含一个根标签。');
            }
            if ($tag === 'svg' && $rootSeen && $stack) {
                throw new InvalidArgumentException('SVG 内不能嵌套另一个 svg。');
            }

            $attributes = $parseAttributes($opening[2]);
            $clean = [];
            foreach ($attributes as $name => $value) {
                if ($name === 'xmlns' || str_starts_with($name, 'xmlns:')) {
                    continue;
                }
                if (str_starts_with($name, 'on') || in_array($name, ['style', 'href', 'src', 'id', 'class'], true) || str_contains($name, ':')) {
                    continue;
                }
                $allowedForTag = isset($elementAttributes[$tag][$name]) || isset($generalAttributes[$name]);
                if (!$allowedForTag || !$safeValue($name, $value)) {
                    continue;
                }
                $clean[$name] = trim($value);
            }

            if ($tag === 'svg') {
                $viewBox = $clean['viewbox'] ?? '0 0 24 24';
                $output .= '<svg viewBox="'.htmlspecialchars($viewBox, ENT_QUOTES | ENT_XML1, 'UTF-8').'" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">';
                if ($selfClosing) {
                    $output .= '</svg>';
                } else {
                    $stack[] = 'svg';
                }
                continue;
            }

            $attributeOutput = '';
            foreach ($clean as $name => $value) {
                $canonical = $name === 'preserveaspectratio' ? 'preserveAspectRatio' : $name;
                $attributeOutput .= ' '.$canonical.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8').'"';
            }
            $output .= '<'.$tag.$attributeOutput.($selfClosing ? '/>' : '>');
            if (!$selfClosing) {
                $stack[] = $tag;
            }
        } else {
            if (trim($token) === '') {
                continue;
            }
            $parent = $stack ? end($stack) : '';
            if (!in_array($parent, ['title', 'desc'], true)) {
                throw new InvalidArgumentException('SVG 图形标签之间不能包含普通文字。');
            }
            $output .= htmlspecialchars($token, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        }
    }

    if (!$rootSeen || $stack) {
        throw new InvalidArgumentException('SVG 标签没有完整闭合。');
    }
    if (!str_starts_with($output, '<svg ') || !str_ends_with($output, '</svg>')) {
        throw new InvalidArgumentException('SVG 清理失败，请检查代码。');
    }
    return $output;
}

function web_solution_icons_all(PDO $pdo): array
{
    return $pdo->query('SELECT id, icon_key, label, svg_code, sort_order, is_system, created_at, updated_at FROM web_solution_icons ORDER BY sort_order ASC, id ASC')->fetchAll();
}

function web_solution_icon_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, icon_key, label, svg_code, sort_order, is_system, created_at, updated_at FROM web_solution_icons WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function web_solution_icon_key_exists(PDO $pdo, string $key): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM web_solution_icons WHERE icon_key=?');
    $stmt->execute([$key]);
    return (int)$stmt->fetchColumn() > 0;
}

function web_solution_icon_usage(PDO $pdo, string $key): array
{
    $stmt = $pdo->prepare("SELECT content_json FROM web_content_blocks WHERE content_key='solutions' LIMIT 1");
    $stmt->execute();
    $json = $stmt->fetchColumn();
    if (!is_string($json) || $json === '') {
        return [];
    }
    $content = json_decode($json, true);
    if (!is_array($content)) {
        return [];
    }
    $used = [];
    foreach (($content['items'] ?? []) as $index => $item) {
        if (!is_array($item) || (string)($item['icon'] ?? '') !== $key) {
            continue;
        }
        $title = trim((string)($item['title'] ?? ''));
        $used[] = $title !== '' ? $title : '应用方案 '.((int)$index + 1);
    }
    return $used;
}

function web_solution_icon_public_map(?PDO $pdo = null): array
{
    $fallback = [];
    foreach (web_solution_icon_defaults() as $item) {
        $fallback[(string)$item['icon_key']] = (string)$item['svg_code'];
    }

    try {
        if (!$pdo) {
            $error = null;
            $pdo = web_db($error);
        }
        if (!$pdo) {
            return $fallback;
        }
        $rows = $pdo->query('SELECT icon_key, svg_code FROM web_solution_icons ORDER BY sort_order ASC, id ASC')->fetchAll();
        if (!$rows) {
            return $fallback;
        }
        $result = [];
        foreach ($rows as $row) {
            $key = (string)($row['icon_key'] ?? '');
            if ($key === '') {
                continue;
            }
            try {
                $result[$key] = web_solution_icon_sanitize((string)($row['svg_code'] ?? ''));
            } catch (Throwable $e) {
                if (isset($fallback[$key])) {
                    $result[$key] = $fallback[$key];
                }
            }
        }
        return $result + $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function web_solution_icon_render(array $map, string $key): string
{
    if (isset($map[$key]) && is_string($map[$key])) {
        return $map[$key];
    }
    return (string)($map['retail'] ?? web_solution_icon_defaults()[0]['svg_code']);
}
