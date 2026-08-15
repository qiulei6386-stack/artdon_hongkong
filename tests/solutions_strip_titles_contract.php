<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$css = file_get_contents($root . '/assets/css/solutions.css');
$page = file_get_contents($root . '/solutions.php');
if (!is_string($css) || !is_string($page)) {
    fwrite(STDERR, "Unable to read Solutions title sources.\n");
    exit(1);
}

$titleRuleStart = strpos($css, '.sol-solutions-card-title h2{');
$copyRuleStart = strpos($css, '.sol-solutions-card-copy{', $titleRuleStart ?: 0);
$titleRule = $titleRuleStart !== false && $copyRuleStart !== false
    ? substr($css, $titleRuleStart, $copyRuleStart - $titleRuleStart)
    : '';

foreach (['display:block', 'overflow:visible', 'font-size:clamp(20px,1.15vw,22px)'] as $needle) {
    if (!str_contains($titleRule, $needle)) {
        fwrite(STDERR, "Missing complete-title rule: {$needle}\n");
        exit(1);
    }
}
foreach (['-webkit-line-clamp', 'text-overflow:ellipsis'] as $forbidden) {
    if (str_contains($titleRule, $forbidden)) {
        fwrite(STDERR, "Solutions strip titles must not be truncated by {$forbidden}.\n");
        exit(1);
    }
}
if (!str_contains($css, 'grid-template-rows:minmax(78px,auto) 98px auto auto')
    || !str_contains($css, '.sol-solutions-card-title{')
    || !str_contains($css, 'min-height:78px')) {
    fwrite(STDERR, "Solutions strip title rows must reserve aligned multi-line space.\n");
    exit(1);
}
if (!str_contains($page, 'solutions.css?v=1.0.45')) {
    fwrite(STDERR, "Solutions stylesheet cache version was not updated.\n");
    exit(1);
}

echo "solutions strip titles contract passed\n";
