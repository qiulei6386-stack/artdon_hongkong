<?php

declare(strict_types=1);

// Public HTML only. The same output filter also runs on micro-cache HITs.
function web_google_analytics_allowed(): bool
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'HEAD'], true)) return false;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $uri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '');
    if (preg_match('~/(?:admin|api|cron)(?:/|$)~i', $script . '|' . $path)) return false;
    if (preg_match('~^(?:submit_|sync_|bridge_|repair_|artdon_emergency_)~i', basename($script))) return false;
    return true;
}

function web_inject_google_analytics(string $html): string
{
    if (!preg_match('~<head\b[^>]*>~i', $html) || str_contains($html, 'G-C63RTCJ402')) {
        return $html;
    }
    $tag = <<<'HTML'

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-C63RTCJ402"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-C63RTCJ402');
</script>

HTML;
    return preg_replace_callback('~<head\b[^>]*>~i', static fn(array $match): string => $match[0] . $tag, $html, 1) ?? $html;
}

function web_google_analytics_start(): void
{
    if (!web_google_analytics_allowed() || defined('WEB_GOOGLE_ANALYTICS_OUTPUT_BUFFER')) return;
    define('WEB_GOOGLE_ANALYTICS_OUTPUT_BUFFER', true);
    ob_start('web_inject_google_analytics');
}
