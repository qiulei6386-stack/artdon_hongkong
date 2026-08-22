<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/inquiry_captcha.php';

web_ensure_session();
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$token = strtolower(trim((string)($_GET['token'] ?? '')));
$code = web_inquiry_captcha_create($token);
if ($code === null || !function_exists('imagecreatetruecolor')) {
    http_response_code(400);
    $fallback = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    if (is_string($fallback)) echo $fallback;
    exit;
}

$width = 156;
$height = 50;
$image = imagecreatetruecolor($width, $height);
if ($image === false) {
    http_response_code(500);
    exit;
}
imageantialias($image, true);
$background = imagecolorallocate($image, 247, 247, 247);
$border = imagecolorallocate($image, 202, 202, 202);
imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $background);

for ($i = 0; $i < 7; $i++) {
    $shade = random_int(185, 225);
    $line = imagecolorallocate($image, $shade, $shade, $shade);
    imageline($image, random_int(0, $width - 1), random_int(2, $height - 3), random_int(0, $width - 1), random_int(2, $height - 3), $line);
}
for ($i = 0; $i < 32; $i++) {
    $shade = random_int(145, 220);
    $dot = imagecolorallocate($image, $shade, $shade, $shade);
    imagefilledellipse($image, random_int(2, $width - 3), random_int(2, $height - 3), random_int(1, 3), random_int(1, 3), $dot);
}

$fontCandidate = __DIR__ . '/assets/fonts/ARSMaqBolTr.otf';
$fontFile = is_file($fontCandidate) ? $fontCandidate : '';

for ($i = 0; $i < 5; $i++) {
    $color = random_int(0, 2) === 0
        ? imagecolorallocate($image, 194, 22, 29)
        : imagecolorallocate($image, random_int(12, 45), random_int(12, 45), random_int(12, 45));
    if ($fontFile !== '' && function_exists('imagettftext')) {
        imagettftext(
            $image,
            random_int(21, 24),
            random_int(-13, 13),
            9 + ($i * 29) + random_int(-1, 2),
            36 + random_int(-2, 2),
            $color,
            $fontFile,
            $code[$i]
        );
    } else {
        imagestring($image, 5, 12 + ($i * 28), 16 + random_int(-2, 2), $code[$i], $color);
    }
}

imagerectangle($image, 0, 0, $width - 1, $height - 1, $border);
imagepng($image, null, 7);
imagedestroy($image);
