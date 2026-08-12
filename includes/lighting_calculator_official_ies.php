<?php

declare(strict_types=1);

/**
 * Read-only bridge between published product photometric ZIPs and the calculator.
 * No filesystem path or ZIP member name is exposed to the browser.
 */

function artdon_lc_official_safe_file(string $storedPath): ?string
{
    $storedPath = trim(str_replace('\\', '/', $storedPath));
    if ($storedPath === '' || str_contains($storedPath, "\0") || str_contains($storedPath, '..') || preg_match('~^[a-z][a-z0-9+.-]*://~i', $storedPath)) {
        return null;
    }
    $siteRoot = defined('ARTDON_LC_SITE_ROOT') ? (string)ARTDON_LC_SITE_ROOT : dirname(__DIR__);
    $root = realpath($siteRoot);
    $file = realpath($siteRoot . '/' . ltrim($storedPath, '/'));
    if (!$root || !$file || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) return null;
    return $file;
}

function artdon_lc_official_member_id(int $variantId, int $index, array $stat): string
{
    return substr(hash('sha256', $variantId . '|' . $index . '|' . (string)($stat['crc'] ?? '') . '|' . (string)($stat['size'] ?? '')), 0, 24);
}

function artdon_lc_official_numbers(string $text): ?array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $tiltIndex = null;
    foreach ($lines as $index => $line) {
        if (preg_match('/^\s*TILT\s*=\s*NONE\s*$/i', $line)) { $tiltIndex = $index; break; }
    }
    if ($tiltIndex === null) return null;
    $body = implode(' ', array_slice($lines, $tiltIndex + 1));
    preg_match_all('/[+-]?(?:\d+\.?\d*|\.\d+)(?:[Ee][+-]?\d+)?/', $body, $matches);
    $numbers = array_map('floatval', $matches[0] ?? []);
    if (count($numbers) < 13) return null;
    return $numbers;
}

function artdon_lc_official_crossing(array $a, array $b, float $target): float
{
    $delta = $b['intensity'] - $a['intensity'];
    if (abs($delta) < 1.0e-9) return ($a['angle'] + $b['angle']) / 2;
    $ratio = max(0.0, min(1.0, ($target - $a['intensity']) / $delta));
    return $a['angle'] + ($b['angle'] - $a['angle']) * $ratio;
}

function artdon_lc_official_beam_angle(array $vertical, array $right, array $left): ?float
{
    $profile = [];
    for ($i = count($vertical) - 1; $i >= 0; $i--) {
        $angle = (float)$vertical[$i];
        if ($angle <= 0.000001 || $angle > 90.000001) continue;
        $profile[] = ['angle'=>-$angle, 'intensity'=>(float)($left[$i] ?? 0)];
    }
    $profile[] = ['angle'=>0.0, 'intensity'=>((float)($right[0] ?? 0) + (float)($left[0] ?? 0)) / 2];
    foreach ($vertical as $i => $angle) {
        $angle = (float)$angle;
        if ($angle <= 0.000001 || $angle > 90.000001) continue;
        $profile[] = ['angle'=>$angle, 'intensity'=>(float)($right[$i] ?? 0)];
    }
    if (count($profile) < 3) return null;
    $peakIndex = 0;
    foreach ($profile as $i => $point) if ($point['intensity'] > $profile[$peakIndex]['intensity']) $peakIndex = $i;
    $peak = (float)$profile[$peakIndex]['intensity'];
    if ($peak <= 0) return null;
    $half = $peak * 0.5;
    $leftCross = null;
    $rightCross = null;
    for ($i = $peakIndex; $i > 0; $i--) {
        if ($profile[$i - 1]['intensity'] <= $half && $profile[$i]['intensity'] >= $half) {
            $leftCross = artdon_lc_official_crossing($profile[$i - 1], $profile[$i], $half);
            break;
        }
    }
    for ($i = $peakIndex; $i < count($profile) - 1; $i++) {
        if ($profile[$i]['intensity'] >= $half && $profile[$i + 1]['intensity'] <= $half) {
            $rightCross = artdon_lc_official_crossing($profile[$i], $profile[$i + 1], $half);
            break;
        }
    }
    return $leftCross !== null && $rightCross !== null ? max(0.0, $rightCross - $leftCross) : null;
}

function artdon_lc_official_parse_summary(string $text, string $displayName): ?array
{
    $numbers = artdon_lc_official_numbers($text);
    if (!$numbers) return null;
    $at = 0;
    $lampCount = $numbers[$at++];
    $lumensPerLamp = $numbers[$at++];
    $multiplier = $numbers[$at++];
    $verticalCount = (int)round($numbers[$at++]);
    $horizontalCount = (int)round($numbers[$at++]);
    $photometricType = (int)round($numbers[$at++]);
    $at += 6;
    $watts = (float)($numbers[$at++] ?? 0);
    if ($photometricType !== 1 || $verticalCount < 2 || $horizontalCount < 1) return null;
    if ($at + $verticalCount + $horizontalCount + $verticalCount * $horizontalCount > count($numbers)) return null;
    $vertical = array_slice($numbers, $at, $verticalCount); $at += $verticalCount;
    $horizontal = array_slice($numbers, $at, $horizontalCount); $at += $horizontalCount;
    $planes = [];
    for ($h = 0; $h < $horizontalCount; $h++) {
        $planes[$h] = array_map(static fn($value): float => (float)$value * (float)$multiplier, array_slice($numbers, $at, $verticalCount));
        $at += $verticalCount;
    }
    $rightIndex = 0;
    $leftIndex = 0;
    $bestRight = INF;
    $bestLeft = INF;
    foreach ($horizontal as $i => $angle) {
        $normalized = fmod((float)$angle + 360.0, 360.0);
        $rightDistance = min(abs($normalized), abs(360.0 - $normalized));
        $leftDistance = abs($normalized - 180.0);
        if ($rightDistance < $bestRight) { $bestRight = $rightDistance; $rightIndex = $i; }
        if ($leftDistance < $bestLeft) { $bestLeft = $leftDistance; $leftIndex = $i; }
    }
    if ($horizontalCount === 1) $leftIndex = $rightIndex;
    $beam = artdon_lc_official_beam_angle($vertical, $planes[$rightIndex] ?? [], $planes[$leftIndex] ?? []);
    $nominal = null;
    if (preg_match('/(?:^|[^0-9])([0-9]{1,3}(?:\.[0-9]+)?)\s*(?:D|DEG(?:REE)?S?|°)(?:[^A-Z0-9]|$)/i', $displayName, $match)) {
        $candidate = (float)$match[1];
        if ($candidate > 0 && $candidate <= 180) $nominal = $candidate;
    }
    return [
        'beam_angle'=>$beam !== null ? round($beam, 1) : null,
        'nominal_angle'=>$nominal,
        'watts'=>$watts > 0 ? round($watts, 3) : null,
        'lumens'=>$lampCount > 0 && $lumensPerLamp > 0 ? round($lampCount * $lumensPerLamp, 1) : null,
    ];
}

function artdon_lc_official_zip_members(int $variantId, string $file, bool $includeText = false): array
{
    if (!class_exists('ZipArchive') || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'zip') return [];
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) return [];
    $members = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        if (!is_array($stat) || !preg_match('/\.ies$/i', (string)($stat['name'] ?? '')) || (int)($stat['size'] ?? 0) <= 0 || (int)($stat['size'] ?? 0) > 2 * 1024 * 1024) continue;
        $text = $zip->getFromIndex($index);
        if (!is_string($text) || $text === '') continue;
        $displayName = basename(str_replace('\\', '/', (string)$stat['name']));
        $summary = artdon_lc_official_parse_summary($text, $displayName);
        if (!$summary || $summary['beam_angle'] === null) continue;
        $member = [
            'id'=>artdon_lc_official_member_id($variantId, $index, $stat),
            'display_name'=>$displayName,
            'size'=>(int)$stat['size'],
        ] + $summary;
        if ($includeText) $member['text'] = $text;
        $members[] = $member;
    }
    $zip->close();
    usort($members, static fn(array $a, array $b): int => ($a['beam_angle'] <=> $b['beam_angle']) ?: strcmp($a['display_name'], $b['display_name']));
    return $members;
}

function artdon_lc_official_catalog(PDO $pdo): array
{
    $sql = "SELECT v.id AS variant_id,v.name AS variant_name,v.model_code,v.power_text,v.lumen_text,v.cover_image AS variant_image,v.photometric_path,
            p.id AS series_id,p.slug AS series_slug,p.name AS series_title,p.series_name,p.category_slug,p.cover_image AS series_image,
            COALESCE(NULLIF(c.name,''),p.category_slug) AS category_name
        FROM web_product_variants v
        INNER JOIN web_products p ON p.id=v.series_id
        LEFT JOIN web_product_categories c ON c.slug=p.category_slug
        WHERE v.is_published=1 AND p.is_published=1 AND v.photometric_path<>''
        ORDER BY COALESCE(c.sort_order,9999),p.sort_order,p.id,v.sort_order,v.id";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $categories = [];
    $series = [];
    $variantCount = 0;
    $iesCount = 0;
    foreach ($rows as $row) {
        $variantId = (int)$row['variant_id'];
        $file = artdon_lc_official_safe_file((string)$row['photometric_path']);
        if (!$file) continue;
        $members = artdon_lc_official_zip_members($variantId, $file, false);
        if (!$members) continue;
        $categorySlug = trim((string)$row['category_slug']) ?: 'products';
        $categoryName = trim((string)$row['category_name']) ?: ucwords(str_replace('-', ' ', $categorySlug));
        $seriesId = (int)$row['series_id'];
        $seriesName = trim((string)$row['series_name']) ?: trim((string)$row['series_title']);
        if (!isset($categories[$categorySlug])) $categories[$categorySlug] = ['slug'=>$categorySlug,'name'=>$categoryName,'series_ids'=>[]];
        if (!in_array($seriesId, $categories[$categorySlug]['series_ids'], true)) $categories[$categorySlug]['series_ids'][] = $seriesId;
        if (!isset($series[$seriesId])) {
            $series[$seriesId] = [
                'id'=>$seriesId,
                'slug'=>(string)$row['series_slug'],
                'name'=>$seriesName,
                'category_slug'=>$categorySlug,
                'image'=>(string)($row['series_image'] ?? ''),
                'variants'=>[],
            ];
        }
        $series[$seriesId]['variants'][] = [
            'id'=>$variantId,
            'name'=>(string)$row['variant_name'],
            'model_code'=>(string)$row['model_code'],
            'image'=>(string)($row['variant_image'] ?: $row['series_image']),
            'power_text'=>(string)$row['power_text'],
            'lumen_text'=>(string)$row['lumen_text'],
            'ies_options'=>$members,
        ];
        $variantCount++;
        $iesCount += count($members);
    }
    return [
        'categories'=>array_values($categories),
        'series'=>array_values($series),
        'stats'=>['series_count'=>count($series),'variant_count'=>$variantCount,'ies_count'=>$iesCount],
    ];
}

function artdon_lc_official_find(PDO $pdo, int $variantId, string $memberId): ?array
{
    if ($variantId < 1 || !preg_match('/^[a-f0-9]{24}$/', $memberId)) return null;
    $stmt = $pdo->prepare("SELECT v.id AS variant_id,v.name AS variant_name,v.model_code,v.photometric_path,p.series_name,p.name AS series_title,p.category_slug
        FROM web_product_variants v INNER JOIN web_products p ON p.id=v.series_id
        WHERE v.id=? AND v.is_published=1 AND p.is_published=1 LIMIT 1");
    $stmt->execute([$variantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $file = artdon_lc_official_safe_file((string)$row['photometric_path']);
    if (!$file) return null;
    foreach (artdon_lc_official_zip_members($variantId, $file, true) as $member) {
        if (!hash_equals($member['id'], $memberId)) continue;
        return [
            'name'=>$member['display_name'],
            'size'=>$member['size'],
            'text'=>$member['text'],
            'official'=>[
                'variant_id'=>$variantId,
                'variant_name'=>(string)$row['variant_name'],
                'model_code'=>(string)$row['model_code'],
                'series_name'=>trim((string)$row['series_name']) ?: trim((string)$row['series_title']),
                'category_slug'=>(string)$row['category_slug'],
                'beam_angle'=>$member['beam_angle'],
                'nominal_angle'=>$member['nominal_angle'],
                'watts'=>$member['watts'],
                'lumens'=>$member['lumens'],
            ],
        ];
    }
    return null;
}
