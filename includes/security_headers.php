<?php
/**
 * Artdon security headers.
 *
 * Keep this helper lightweight: it is used by normal PHP pages and by
 * public-cache HIT responses that may exit before the main bootstrap loads.
 */
declare(strict_types=1);

require_once __DIR__ . '/google_analytics.php';

if (!function_exists('artdon_security_headers_send')) {
    function artdon_security_headers_send(): void
    {
        if (headers_sent()) return;

        $analyticsScript = web_google_analytics_allowed() ? ' https://www.googletagmanager.com' : '';
        $analyticsConnect = web_google_analytics_allowed() ? ' https://www.googletagmanager.com https://*.google-analytics.com https://*.google.com' : '';
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://maps.googleapis.com",
            "script-src 'self' 'unsafe-inline' https://maps.googleapis.com https://maps.gstatic.com https://www.youtube.com https://s.ytimg.com https://player.vimeo.com" . $analyticsScript,
            "connect-src 'self' https://maps.googleapis.com https://www.googleapis.com" . $analyticsConnect,
            "media-src 'self' data: blob:",
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://www.openstreetmap.org https://maps.google.com https://www.google.com",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "upgrade-insecure-requests",
            "block-all-mixed-content",
        ];

        header('Content-Security-Policy: ' . implode('; ', $csp));
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: accelerometer=(), autoplay=(self), camera=(), clipboard-read=(), clipboard-write=(self), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()');
        header('X-Permitted-Cross-Domain-Policies: none');
    }
}
