<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/product_hierarchy.php';

$dbError = null;
$pdo = web_db($dbError);
$slug = trim((string)($_GET['slug'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
$variant = $series = null;

if ($pdo) {
    try {
        web_product_hierarchy_migrate($pdo);
        $variant = web_product_variant_find($pdo, $slug !== '' ? $slug : $id, true);
        if ($variant) {
            $series = web_product_series_find($pdo, (int)$variant['series_id'], true);
        }
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}

if (!$variant || !$series) {
    http_response_code(404);
    exit('Product not found');
}

$site = web_get_block('site');

// V7.1.8.35: PDF / Print keeps the gray frame on the dimension drawing, while product images and photometric curves stay frameless.
// V7.1.8.31: PDF / Print footer LOGO uses the black main LOGO first.
// Do not use the white/red footer logo on the A4 white page.
$logoMode = strtolower(trim((string)($_GET['logo'] ?? '0')));
$showFooterLogo = in_array($logoMode, ['1', 'yes', 'true', 'on'], true);
$footerLogo = '';
foreach ([
    'assets/img/logo-artdon-black.png',
    'assets/img/logo-black.png',
    'assets/img/logo-artdon.png',
    'assets/img/logo-artdon-footer.png',
] as $logoCandidate) {
    if (is_file(__DIR__ . '/' . $logoCandidate)) {
        $footerLogo = $logoCandidate;
        break;
    }
}
if ($footerLogo === '') {
    $showFooterLogo = false;
}
$logoQuery = $_GET;
$logoQuery['logo'] = '1';
$withLogoUrl = basename(__FILE__) . '?' . http_build_query($logoQuery);
$logoQuery['logo'] = '0';
$noLogoUrl = basename(__FILE__) . '?' . http_build_query($logoQuery);

$mainImage = trim((string)($variant['cover_image'] ?? '')) ?: trim((string)($series['cover_image'] ?? ''));
$dimensionImage = trim((string)($variant['dimension_image'] ?? ''));
$dimensionAlt = trim((string)($variant['dimension_alt'] ?? '')) ?: 'Dimension drawing';

$photometricImages = array_values(array_filter(array_slice($variant['photometric_images'] ?? [], 0, 4), static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== ''));
$photometricDisplayImages = count($photometricImages) === 2 ? array_merge($photometricImages, $photometricImages) : $photometricImages;
$photometricDisplayImages = array_slice($photometricDisplayImages, 0, 4);

$accessoryItems = array_values(array_filter(array_slice($variant['accessory_items'] ?? [], 0, 12), static fn($item): bool => is_array($item) && trim((string)($item['image'] ?? '')) !== ''));
$firstPageAccessories = array_slice($accessoryItems, 0, 4);
$extraAccessoryPages = []; // V7.1.8.28: PDF/Print must stay on one A4 page; show first 4 accessories only.

$specRows = [
    'Product family' => $series['series_name'] ?: $series['name'],
    'Model' => $variant['model_code'],
    'Dimensions' => $variant['dimensions'],
    'Cut-out' => $variant['cutout_text'],
    'Power' => $variant['power_text'],
    'Luminous flux' => $variant['lumen_text'],
    'Efficacy' => $variant['efficacy_text'],
    'Voltage' => implode(' / ', $variant['voltage'] ?? []),
    'CCT' => implode(' / ', $variant['cct'] ?? []),
    'CRI' => implode(' / ', $variant['cri'] ?? []),
    'Beam angle' => implode(' / ', $variant['beam_angle'] ?? []),
    'IP rating' => $variant['ip_rating'],
    'Finish' => implode(' / ', $variant['finish'] ?? []),
    'Mounting' => implode(' / ', $variant['mounting'] ?? []),
    'Dimming' => implode(' / ', $variant['dimming'] ?? []),
];
foreach (($variant['extra_specs'] ?? []) as $extra) {
    $label = trim((string)($extra['label'] ?? ''));
    $value = trim((string)($extra['value'] ?? ''));
    if ($label !== '' || $value !== '') {
        $specRows[$label !== '' ? $label : 'Specification'] = $value;
    }
}
if (!empty($variant['spec_rows'])) {
    $customRows = [];
    foreach (($variant['spec_rows'] ?? []) as $row) {
        if (!is_array($row) || (isset($row['active']) && empty($row['active']))) continue;
        $label = trim((string)($row['label'] ?? ''));
        $value = trim((string)($row['value'] ?? ''));
        if ($label !== '' || $value !== '') {
            $customRows[$label !== '' ? $label : 'Specification'] = $value;
        }
    }
    if ($customRows) {
        $specRows = $customRows;
    }
}

$specRows = array_filter($specRows, static fn($value): bool => trim((string)$value) !== '');
$productTitle = trim((string)($variant['name'] ?? 'Product'));
$familyName = trim((string)($series['series_name'] ?: $series['name']));
// V7.1.8.24: same backend intro also appears in PDF / Print under the title.
$detailIntroText = trim((string)($variant['detail_intro'] ?? ''));
if ($detailIntroText === '') $detailIntroText = trim((string)($variant['full_description'] ?? ''));
if ($detailIntroText === '') $detailIntroText = trim((string)($variant['short_description'] ?? ''));
// V7.1.8.25: keep PDF/Print consistent with the product page when old Spectrum items have no intro yet.
if ($detailIntroText === '') {
    $detailIntroKey = strtoupper(trim((string)($variant['name'] ?? '') . ' ' . (string)($series['series_name'] ?? '') . ' ' . (string)($series['name'] ?? '')));
    if (strpos($detailIntroKey, 'SPECTRUM') !== false) {
        $detailIntroText = "Compact architectural track light designed for retail displays, hospitality environments and museum applications.\n\nDeep anti-glare optics\nHigh CRI 90 colour rendering\nMultiple beam angles\nCompatible with 1-circuit and 3-circuit tracks\nDALI / TRIAC dimming available";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= web_e($productTitle) ?> - PDF / Print</title>
<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
html,body{margin:0;background:#e9e9e9;color:#101014;font-family:Arial,"Helvetica Neue",Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.toolbar{position:fixed;right:18px;top:14px;z-index:30;display:flex;gap:8px;align-items:center}.toolbar a,.toolbar button{border:1px solid #111;background:#111;color:#fff;padding:10px 15px;font-size:12px;line-height:1;font-weight:800;letter-spacing:.04em;text-transform:uppercase;cursor:pointer;text-decoration:none;font-family:Arial,"Helvetica Neue",Helvetica,sans-serif}.toolbar a.is-active{background:#fff;color:#111}.toolbar a.is-disabled{opacity:.4;pointer-events:none}
.pdf-page{width:210mm;height:297mm;margin:10px auto;background:#fff;border:1px solid #cfcfcf;position:relative;overflow:hidden;page-break-after:always;padding:12mm 14mm 8mm}
.pdf-page:last-of-type{page-break-after:auto}
.sheet-title{margin:0 0 3.2mm 0;font-size:22pt;line-height:.92;font-weight:950;letter-spacing:-.055em;color:#0b0c0f}
.sheet-intro{width:100%;max-width:182mm;margin:0 auto 5mm;padding:3mm 0 3.1mm;border-top:.22mm solid #d8d8d8;border-bottom:.22mm solid #d8d8d8;color:#202020;font-size:7pt;line-height:1.22;font-weight:400;white-space:pre-line;overflow-wrap:break-word}
.main-grid{display:grid;grid-template-columns:54mm minmax(0,1fr);gap:5mm;align-items:start;width:100%;max-width:182mm;margin:0 auto}
.media-stack{display:grid;gap:3.1mm}.image-box{margin:0;width:54mm;height:54mm;border:0;background:transparent;display:flex;align-items:center;justify-content:center;overflow:hidden}.image-box.main-image{background:transparent}.image-box.dimension-image{background:transparent}.image-box img{display:block;width:100%;height:100%;object-fit:contain}.placeholder{color:#999;font-size:7pt;letter-spacing:.08em;text-transform:uppercase}
.spec-table{width:100%;border-collapse:collapse;table-layout:fixed}.spec-table tr:nth-child(odd){background:#f3f3f3}.spec-table td{height:5.15mm;padding:0 3mm;border:0;font-size:6.35pt;line-height:1.08;vertical-align:middle}.spec-table td:first-child{width:43%;color:#333;font-weight:400}.spec-table td:last-child{width:57%;color:#161616;font-weight:800;overflow-wrap:anywhere}
.section-line{height:0;border:0;border-top:.2mm solid #dddddd;margin:6.2mm auto 0;width:100%;max-width:182mm}
.info-section{display:grid;grid-template-columns:54mm minmax(0,1fr);gap:5mm;align-items:start;width:100%;max-width:182mm;margin:0 auto;padding-top:5.2mm}.kicker{margin:0 0 1.5mm 0;color:#c92b32;font-size:5.8pt;line-height:1;font-weight:900;letter-spacing:.24em;text-transform:uppercase}.section-title{margin:0;color:#0b0c0f;font-size:19pt;line-height:.9;font-weight:950;letter-spacing:-.055em}.right-grid{min-width:0}
.curve-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:3.2mm;align-items:start}.curve-card{margin:0;min-width:0;text-align:center;break-inside:avoid}.curve-frame{width:100%;aspect-ratio:1/1;border:0;background:transparent;display:flex;align-items:center;justify-content:center;overflow:hidden}.curve-frame img{display:block;width:100%;height:100%;object-fit:contain}.curve-card figcaption{margin-top:2mm;color:#858585;font-size:5.7pt;line-height:1.1;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.accessory-section{padding-top:5.2mm}.accessory-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:3.2mm;align-items:start}.accessory-spacer{min-height:1px}.accessory-card{min-width:0;break-inside:avoid}.accessory-card figure{margin:0;width:100%;aspect-ratio:1/1;border:.18mm solid #d8d8d8;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}.accessory-card img{display:block;width:100%;height:100%;object-fit:contain;padding:3.2mm}.accessory-code{margin:2mm 0 .7mm 0;color:#df232b;font-size:5pt;line-height:1;font-weight:900;letter-spacing:.2em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.accessory-name{margin:0;color:#0b0c0f;font-size:6.5pt;line-height:1.05;font-weight:950;letter-spacing:-.025em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.extra-accessory-page{display:none}.extra-accessory-page .sheet-title{margin-bottom:10mm}.extra-accessory-page .extra-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7mm 5mm;width:100%;max-width:182mm;margin:0 auto}.extra-accessory-page .accessory-card figure{background:#fff}

/* V7.1.8.31 PDF/Print: optional black footer LOGO, centered at the bottom of A4. */
.pdf-page.has-footer-logo{padding-bottom:12mm}
.pdf-footer-logo{position:absolute;left:14mm;right:14mm;bottom:3.4mm;height:6mm;display:flex;align-items:center;justify-content:center;pointer-events:none}
.pdf-footer-logo img{display:block;max-width:32mm;max-height:6mm;width:auto;height:auto;object-fit:contain}

/* V7.1.8.29 PDF/Print: dimension drawing enlarged 150%, keep frame unchanged; prevent horizontal stretching in curve/accessory area. */
.image-box.dimension-image img{transform:none!important;transform-origin:center center!important;width:100%!important;height:100%!important;object-fit:contain!important;object-position:center center!important}
.curve-frame img{width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain;transform:none;font-stretch:normal}
.accessory-card img{width:auto;height:auto;max-width:82%;max-height:82%;object-fit:contain;padding:0;transform:none;font-stretch:normal}
.sheet-title,.section-title,.curve-card figcaption,.accessory-code,.accessory-name{font-stretch:normal;transform:none}
.sheet-title{letter-spacing:-.025em}
.section-title{letter-spacing:-.02em}
.kicker{letter-spacing:.18em}
.curve-card figcaption{letter-spacing:0;font-weight:500}
.accessory-code{letter-spacing:.09em}
.accessory-name{letter-spacing:0;font-weight:900}


/* V7.1.8.33 PDF/Print: remove gray frames/backgrounds from product images and photometric curves; keep layout and accessories unchanged. */
.image-box.main-image,.curve-frame{border:0!important;background:transparent!important;box-shadow:none!important}
/* V7.1.8.35 PDF/Print: dimension drawing keeps a thin gray frame, while the drawing image remains enlarged 150%. */
.image-box.dimension-image{border:.18mm solid #d8d8d8!important;background:#fff!important;box-shadow:none!important}


/* V7.1.8.115 PDF/Print: accessory title baseline follows web page. Missing code keeps an invisible code row. */
.accessory-code{display:block;box-sizing:border-box;min-height:5pt;height:5pt;line-height:5pt;margin:2mm 0 .7mm 0}
.accessory-code.is-placeholder{visibility:hidden;color:transparent;opacity:0;overflow:hidden}
.accessory-name{margin:0}


/* V7.1.8.131 PDF/Print dimension WYSIWYG */
.image-box.dimension-image img{
  transform:none!important;
  width:100%!important;
  height:100%!important;
  max-width:100%!important;
  max-height:100%!important;
  object-fit:contain!important;
  object-position:center center!important;
  padding:0!important;
  margin:0!important;
}

@media print{html,body{background:#fff}.toolbar{display:none}.pdf-page{margin:0;border:0;box-shadow:none}}
</style>
</head>
<body>
<div class="toolbar">
  <a class="<?= !$showFooterLogo ? 'is-active' : '' ?>" href="<?= web_e($noLogoUrl) ?>">No LOGO</a>
  <a class="<?= $showFooterLogo ? 'is-active' : '' ?><?= $footerLogo === '' ? ' is-disabled' : '' ?>" href="<?= web_e($withLogoUrl) ?>">With black LOGO</a>
  <button type="button" onclick="window.print()">Print / Save PDF</button>
</div>

<section class="pdf-page product-sheet<?= $showFooterLogo ? ' has-footer-logo' : '' ?>">
  <h1 class="sheet-title"><?= web_e($productTitle) ?></h1>
  <?php if ($detailIntroText !== ''): ?><div class="sheet-intro"><?= web_e($detailIntroText) ?></div><?php endif; ?>

  <div class="main-grid">
    <div class="media-stack">
      <?php if ($mainImage !== ''): ?>
        <figure class="image-box main-image"><img src="<?= web_e(web_public_path($mainImage)) ?>" alt="<?= web_e($productTitle) ?>"></figure>
      <?php else: ?>
        <div class="image-box main-image"><span class="placeholder">Product image</span></div>
      <?php endif; ?>
      <?php if ($dimensionImage !== ''): ?>
        <figure class="image-box dimension-image"><img src="<?= web_e(web_public_path($dimensionImage)) ?>" alt="<?= web_e($dimensionAlt) ?>"></figure>
      <?php else: ?>
        <div class="image-box dimension-image"><span class="placeholder">Dimension drawing</span></div>
      <?php endif; ?>
    </div>
    <table class="spec-table" aria-label="Product specifications">
      <tbody>
      <?php foreach ($specRows as $label => $value): ?>
        <tr><td><?= web_e((string)$label) ?></td><td><?= web_e((string)$value) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($photometricDisplayImages): ?>
    <hr class="section-line">
    <section class="info-section photometric-section">
      <div class="left-copy">
        <p class="kicker">Product overview</p>
        <h2 class="section-title">Technical<br>product<br>information</h2>
      </div>
      <div class="right-grid curve-grid">
        <?php foreach ($photometricDisplayImages as $photo): ?>
          <figure class="curve-card">
            <div class="curve-frame"><img src="<?= web_e(web_public_path((string)$photo['image'])) ?>" alt="<?= web_e((string)($photo['alt'] ?? $photo['label'] ?? 'Photometric curve')) ?>"></div>
            <?php if (trim((string)($photo['label'] ?? '')) !== ''): ?><figcaption><?= web_e((string)$photo['label']) ?></figcaption><?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($firstPageAccessories): ?>
    <hr class="section-line">
    <section class="info-section accessory-section">
      <div class="left-copy">
        <p class="kicker">Accessories</p>
        <h2 class="section-title">Compatible<br>accessories</h2>
      </div>
      <div class="right-grid accessory-grid">
        <?php for ($i = 0, $spacers = max(0, 4 - count($firstPageAccessories)); $i < $spacers; $i++): ?><div class="accessory-spacer"></div><?php endfor; ?>
        <?php foreach ($firstPageAccessories as $accessory): ?>
          <article class="accessory-card">
            <figure><img src="<?= web_e(web_public_path((string)$accessory['image'])) ?>" alt="<?= web_e((string)($accessory['alt'] ?? $accessory['title'] ?? 'Accessory')) ?>"></figure>
            <?php $accessoryModelV718115 = trim((string)($accessory['model'] ?? '')); ?><?php if ($accessoryModelV718115 !== ''): ?><p class="accessory-code"><?= web_e($accessoryModelV718115) ?></p><?php else: ?><p class="accessory-code is-placeholder" aria-hidden="true">&nbsp;</p><?php endif; ?>
            <?php if (trim((string)($accessory['title'] ?? '')) !== ''): ?><h3 class="accessory-name"><?= web_e((string)$accessory['title']) ?></h3><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
  <?php if ($showFooterLogo): ?>
    <div class="pdf-footer-logo"><img src="<?= web_e(web_public_path($footerLogo)) ?>" alt="Artdon Lighting"></div>
  <?php endif; ?>
</section>

<?php foreach ($extraAccessoryPages as $pageAccessories): ?>
<section class="pdf-page extra-accessory-page">
  <h1 class="sheet-title"><?= web_e($productTitle) ?> Accessories</h1>
  <div class="extra-grid">
    <?php foreach ($pageAccessories as $accessory): ?>
      <article class="accessory-card">
        <figure><img src="<?= web_e(web_public_path((string)$accessory['image'])) ?>" alt="<?= web_e((string)($accessory['alt'] ?? $accessory['title'] ?? 'Accessory')) ?>"></figure>
        <?php $accessoryModelV718115 = trim((string)($accessory['model'] ?? '')); ?><?php if ($accessoryModelV718115 !== ''): ?><p class="accessory-code"><?= web_e($accessoryModelV718115) ?></p><?php else: ?><p class="accessory-code is-placeholder" aria-hidden="true">&nbsp;</p><?php endif; ?>
        <?php if (trim((string)($accessory['title'] ?? '')) !== ''): ?><h3 class="accessory-name"><?= web_e((string)$accessory['title']) ?></h3><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ($showFooterLogo): ?>
    <div class="pdf-footer-logo"><img src="<?= web_e(web_public_path($footerLogo)) ?>" alt="Artdon Lighting"></div>
  <?php endif; ?>
</section>
<?php endforeach; ?>

<?php if (in_array(strtolower(trim((string)($_GET['autoprint'] ?? ''))), ['1','yes','true','on'], true)): ?>
<script>
// V7.1.8.34: from Datasheet Download, open the current product PDF/Print template and immediately call the browser Save PDF / Print dialog.
window.addEventListener('load', function(){
  setTimeout(function(){ window.print(); }, 450);
});
</script>
<?php endif; ?>
</body>
</html>
