<?php

declare(strict_types=1);

function web_ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

require_once dirname(__DIR__) . '/includes/inquiry_captcha.php';

web_ensure_session();
$_SESSION['web_inquiry_captchas'] = [];

$token = str_repeat('a', 32);
$code = web_inquiry_captcha_create($token);
if (!is_string($code) || preg_match('/^[2-9A-HJ-NP-Z]{5}$/', $code) !== 1) {
    fwrite(STDERR, "CAPTCHA must create a five-character unambiguous code.\n");
    exit(1);
}
if (web_inquiry_captcha_verify($token, 'WRONG')) {
    fwrite(STDERR, "Incorrect CAPTCHA answer must fail.\n");
    exit(1);
}
if (!web_inquiry_captcha_verify(strtoupper($token), strtolower($code))) {
    fwrite(STDERR, "Correct CAPTCHA answer must be case-insensitive.\n");
    exit(1);
}
web_inquiry_captcha_forget($token);
if (web_inquiry_captcha_verify($token, $code)) {
    fwrite(STDERR, "Forgotten CAPTCHA must not be reusable.\n");
    exit(1);
}
if (web_inquiry_captcha_create('bad-token') !== null) {
    fwrite(STDERR, "Invalid challenge token must be rejected.\n");
    exit(1);
}

$root = dirname(__DIR__);
$submit = file_get_contents($root . '/submit_inquiry.php');
$footer = file_get_contents($root . '/partials/footer.php');
$client = file_get_contents($root . '/assets/js/artdon_inquiry_captcha.js');
if (!is_string($submit) || !is_string($footer) || !is_string($client)) exit(1);

$verifyPos = strpos($submit, 'web_inquiry_captcha_verify(');
$attachmentPos = strpos($submit, '$attachments = inquiry_save_attachments(');
$insertPos = strpos($submit, 'INSERT INTO web_inquiries');
if ($verifyPos === false || $attachmentPos === false || $insertPos === false || $verifyPos > $attachmentPos || $verifyPos > $insertPos) {
    fwrite(STDERR, "CAPTCHA verification must happen before uploads and inquiry storage.\n");
    exit(1);
}
foreach (['artdon_inquiry_captcha.css', 'artdon_inquiry_captcha.js', 'captcha_token', 'captcha_code'] as $needle) {
    if (!str_contains($footer . $client, $needle)) {
        fwrite(STDERR, "Missing CAPTCHA integration: {$needle}\n");
        exit(1);
    }
}

echo "inquiry captcha contract passed\n";

