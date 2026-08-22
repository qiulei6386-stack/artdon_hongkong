<?php

declare(strict_types=1);

/**
 * Session-backed CAPTCHA for public website inquiry forms.
 *
 * Each form owns a separate random token so pages with several inquiry forms
 * cannot overwrite one another's challenge. Challenges expire after ten
 * minutes and are removed after five failed attempts or a successful inquiry.
 */

function web_inquiry_captcha_token_valid(string $token): bool
{
    return preg_match('/^[a-f0-9]{32}$/', strtolower($token)) === 1;
}

function web_inquiry_captcha_cleanup(): void
{
    web_ensure_session();
    $now = time();
    $items = is_array($_SESSION['web_inquiry_captchas'] ?? null)
        ? $_SESSION['web_inquiry_captchas']
        : [];

    foreach ($items as $token => $item) {
        if (!is_array($item) || (int)($item['expires_at'] ?? 0) < $now) {
            unset($items[$token]);
        }
    }

    if (count($items) > 12) {
        uasort($items, static fn(array $a, array $b): int => ((int)($a['created_at'] ?? 0)) <=> ((int)($b['created_at'] ?? 0)));
        $items = array_slice($items, -12, null, true);
    }

    $_SESSION['web_inquiry_captchas'] = $items;
}

function web_inquiry_captcha_create(string $token): ?string
{
    $token = strtolower(trim($token));
    if (!web_inquiry_captcha_token_valid($token)) return null;

    web_inquiry_captcha_cleanup();
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < 5; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }

    $_SESSION['web_inquiry_captchas'][$token] = [
        'hash' => hash('sha256', $code),
        'created_at' => time(),
        'expires_at' => time() + 600,
        'attempts' => 0,
    ];

    return $code;
}

function web_inquiry_captcha_verify(string $token, string $answer): bool
{
    $token = strtolower(trim($token));
    $answer = strtoupper(preg_replace('/\s+/', '', trim($answer)) ?? '');
    if (!web_inquiry_captcha_token_valid($token) || preg_match('/^[2-9A-HJ-NP-Z]{5}$/', $answer) !== 1) {
        return false;
    }

    web_inquiry_captcha_cleanup();
    $item = $_SESSION['web_inquiry_captchas'][$token] ?? null;
    if (!is_array($item) || (int)($item['expires_at'] ?? 0) < time()) {
        unset($_SESSION['web_inquiry_captchas'][$token]);
        return false;
    }

    $valid = hash_equals((string)($item['hash'] ?? ''), hash('sha256', $answer));
    if ($valid) {
        $_SESSION['web_inquiry_captchas'][$token]['verified_at'] = time();
        return true;
    }

    $attempts = (int)($item['attempts'] ?? 0) + 1;
    if ($attempts >= 5) {
        unset($_SESSION['web_inquiry_captchas'][$token]);
    } else {
        $_SESSION['web_inquiry_captchas'][$token]['attempts'] = $attempts;
    }
    return false;
}

function web_inquiry_captcha_forget(string $token): void
{
    $token = strtolower(trim($token));
    if (!web_inquiry_captcha_token_valid($token)) return;
    web_ensure_session();
    unset($_SESSION['web_inquiry_captchas'][$token]);
}

