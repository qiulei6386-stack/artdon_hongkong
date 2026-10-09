<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/google_analytics.php';

function lead_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

foreach (['ok', 'invalid', 'captcha', 'slow', 'limit', 'db', 'error'] as $status) {
    lead_check(web_google_analytics_lead_receipt($status, false) === null, 'Unconfirmed response must not create a receipt');
    if ($status !== 'ok') lead_check(web_google_analytics_lead_receipt($status, true) === null, 'Failed response must not create a receipt');
}
$first = web_google_analytics_lead_receipt('ok', true);
$second = web_google_analytics_lead_receipt('ok', true);
lead_check($first['ok'] === true && $first['lead_created'] === true, 'Confirmed receipt must identify a saved lead');
lead_check(preg_match('/^[a-f0-9]{32}$/D', $first['lead_event_id']) === 1, 'Receipt must be opaque');
lead_check($first['lead_event_id'] !== $second['lead_event_id'], 'Separate inquiries need distinct receipts');

$html = '<html><head><title>Fixture</title></head><body></body></html>';
$tagged = web_inject_google_analytics($html);
lead_check(substr_count($tagged, 'window.artdonTrackInquiryLead =') === 1, 'Tracker must be injected once');
lead_check(web_inject_google_analytics($tagged) === $tagged, 'Tracker injection must be idempotent');
$existing = str_replace('<title>', "<script>gtag('config', 'G-C63RTCJ402');</script><title>", $html);
$existingTagged = web_inject_google_analytics($existing);
lead_check(substr_count($existingTagged, "gtag('config', 'G-C63RTCJ402')") === 1, 'Existing tag must not be duplicated');
lead_check(substr_count($existingTagged, 'window.artdonTrackInquiryLead =') === 1, 'Existing tag must still receive tracker');

// Execute only the real responder functions with mocked headers/cookies. No DB,
// uploads, captcha, CRM dispatch or public web requests are involved.
$source = file_get_contents(dirname(__DIR__) . '/submit_inquiry.php');
$start = strpos($source, 'function inquiry_is_ajax');
$end = strpos($source, 'function inquiry_release_rate_lock');
$functions = substr($source, $start, $end - $start);
lead_check(substr_count($source, "inquiry_respond('ok', \$returnUrl, true)") === 1, 'Only final success may confirm a lead');
lead_check(strpos($source, "inquiry_respond('ok', \$returnUrl, true)") > strpos($source, '$pdo->commit();'), 'Lead confirmation must follow transaction commit');
lead_check(str_contains($functions, 'bool $leadCreated = false'), 'Fake successes must default to unconfirmed');
$runner = tempnam(sys_get_temp_dir(), 'artdon-lead-contract-');
try {
    $fixture = <<<'PHP'
<?php
namespace ArtdonLeadFixture;
function header($value): void { $GLOBALS['fixture_headers'][] = $value; }
function setcookie($name, $value, $options): bool { $GLOBALS['fixture_cookies'][$name] = ['value'=>$value, 'options'=>$options]; return true; }
ob_start();
register_shutdown_function(static function(): void {
    $body = ob_get_clean();
    echo json_encode(['body'=>$body, 'headers'=>$GLOBALS['fixture_headers'] ?? [], 'cookies'=>$GLOBALS['fixture_cookies'] ?? [], 'http_code'=>http_response_code()]);
});
PHP;
    $fixture .= "\n" . 'require ' . var_export(dirname(__DIR__) . '/includes/google_analytics.php', true) . ';';
    $fixture .= "\n" . 'eval(' . var_export('namespace ArtdonLeadFixture; ' . $functions, true) . ');';
    $fixture .= "\n" . '$_SERVER["HTTPS"] = "on"; $_SERVER["HTTP_X_REQUESTED_WITH"] = $argv[1] === "ajax" ? "XMLHttpRequest" : "";';
    $fixture .= "\n" . 'inquiry_respond($argv[2], "contact.php#inquiry", $argv[3] === "1");';
    file_put_contents($runner, $fixture);
    foreach ([['ajax', 'ok', false], ['ajax', 'ok', true], ['ajax', 'captcha', true], ['native', 'ok', false], ['native', 'ok', true], ['native', 'error', false]] as [$mode, $status, $confirmed]) {
        $lines = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' ' . escapeshellarg($mode) . ' ' . escapeshellarg($status) . ' ' . ($confirmed ? '1' : '0'), $lines, $exitCode);
        $result = json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
        lead_check($exitCode === 0, 'Responder fixture must exit cleanly');
        $saved = $confirmed && $status === 'ok';
        if ($mode === 'ajax') {
            $json = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
            lead_check($json['lead_created'] === $saved, 'AJAX receipt must distinguish fake success and saved lead');
            lead_check($saved ? preg_match('/^[a-f0-9]{32}$/D', $json['lead_event_id']) === 1 : $json['lead_event_id'] === null, 'AJAX receipt must be valid only for saved lead');
            lead_check($result['cookies'] === [], 'AJAX must not leave native conversion cookies');
            lead_check(in_array('Cache-Control: no-store, max-age=0', $result['headers'], true), 'AJAX metadata must not be cached');
        } else {
            $cookie = $result['cookies']['artdon_ga4_inquiry_lead'];
            lead_check($saved ? preg_match('/^[a-f0-9]{32}$/D', $cookie['value']) === 1 : $cookie['value'] === '', 'Native cookie must exist only for saved lead');
            lead_check($cookie['options']['path'] === '/' && $cookie['options']['secure'] === true && $cookie['options']['samesite'] === 'Lax', 'Native receipt cookie scope must survive internal redirects');
            lead_check($saved ? $cookie['options']['expires'] <= time() + 600 && $cookie['options']['expires'] > time() : $cookie['options']['expires'] < time(), 'Native receipt must expire promptly or be cleared');
            lead_check(in_array('Location: contact.php?inquiry=' . $status . '#inquiry', $result['headers'], true), 'Native redirect feedback must remain unchanged');
        }
    }
} finally {
    unlink($runner);
}
echo "Inquiry lead receipt, response, native cookie and injection checks passed.\n";
