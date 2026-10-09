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

// Called only for a committed inquiry. The opaque receipt stays out of GA4 parameters.
function web_google_analytics_lead_receipt(string $status, bool $leadCreated): ?array
{
    if ($status !== 'ok' || !$leadCreated) return null;
    try {
        return ['ok' => true, 'lead_created' => true, 'lead_event_id' => bin2hex(random_bytes(16))];
    } catch (Throwable $ignored) {
        // Analytics must never turn a saved inquiry into a failed submission.
        return null;
    }
}

function web_inject_google_analytics(string $html): string
{
    if (!preg_match('~<head\b[^>]*>~i', $html)) return $html;
    $loader = str_contains($html, 'G-C63RTCJ402') ? '' : <<<'HTML'

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-C63RTCJ402"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-C63RTCJ402');
</script>

HTML;
    $tag = '';
    if (!str_contains($html, 'window.artdonTrackInquiryLead =')) {
        $tag .= <<<'HTML'
<script>
  (function(){
    var seen = Object.create(null);
    window.artdonTrackInquiryLead = function(result){
      if (!result || result.ok !== true || result.lead_created !== true
          || typeof result.lead_event_id !== 'string'
          || !/^[a-f0-9]{32}$/.test(result.lead_event_id || '')
          || typeof window.gtag !== 'function') return;
      var key = 'artdon_ga4_lead_' + result.lead_event_id;
      if (seen[key]) return;
      try { if (window.sessionStorage.getItem(key)) return; } catch (ignored) {}
      try {
        window.gtag('event', 'generate_lead', {form_name: 'contact_inquiry'});
        seen[key] = true;
        try { window.sessionStorage.setItem(key, '1'); } catch (ignored) {}
      } catch (ignored) {}
    };
    // Native POST receipts survive canonical redirects and are consumed once.
    function nativeLead(){
      try {
        var receipt = document.cookie.match(/(?:^|;\s*)artdon_ga4_inquiry_lead=([a-f0-9]{32})(?:;|$)/);
        if (receipt) {
          document.cookie = 'artdon_ga4_inquiry_lead=; Max-Age=0; Path=/; SameSite=Lax';
          window.artdonTrackInquiryLead({ok: true, lead_created: true, lead_event_id: receipt[1]});
        }
      } catch (ignored) {}
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', nativeLead, {once: true});
    else nativeLead();
  })();
</script>

HTML;
    }
    if ($loader !== '') {
        $html = preg_replace_callback('~<head\b[^>]*>~i', static fn(array $match): string => $match[0] . $loader, $html, 1) ?? $html;
    }
    if ($tag === '') return $html;
    // Keep the original charset declaration near the start of the document.
    if (preg_match('~</head\s*>~i', $html)) {
        return preg_replace_callback('~</head\s*>~i', static fn(array $match): string => $tag . $match[0], $html, 1) ?? $html;
    }
    return preg_replace_callback('~<head\b[^>]*>~i', static fn(array $match): string => $match[0] . $tag, $html, 1) ?? $html;
}

function web_google_analytics_start(): void
{
    if (!web_google_analytics_allowed() || defined('WEB_GOOGLE_ANALYTICS_OUTPUT_BUFFER')) return;
    define('WEB_GOOGLE_ANALYTICS_OUTPUT_BUFFER', true);
    ob_start('web_inject_google_analytics');
}
