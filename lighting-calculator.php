<?php
/** Artdon Lighting IES calculator shell */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';

$content = function_exists('artdon_v710_content') ? artdon_v710_content() : (function_exists('web_get_all_content') ? web_get_all_content() : []);
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$footerBlock = is_array($content['footer'] ?? null) ? $content['footer'] : (function_exists('web_get_block') ? (array)web_get_block('footer') : []);
$siteUrl = function_exists('artdon_v710_site_url') ? artdon_v710_site_url($site) : rtrim((string)($site['site_url'] ?? 'https://artdonlighting.com'), '/');
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$pageTitle = 'IES Lighting Calculator | Artdon Lighting';
$pageDescription = 'Upload an IES file, configure your room and luminaire layout, and calculate the illuminance distribution.';
$canonical = $siteUrl . '/lighting-calculator.php';
$ogImage = function_exists('artdon_v710_absolute_url')
    ? artdon_v710_absolute_url($siteUrl, (string)($content['seo']['og_image'] ?? $site['header_logo'] ?? 'assets/img/logo-artdon.png'))
    : $siteUrl . '/assets/img/logo-artdon.png';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= web_e($pageTitle) ?></title>
  <meta name="description" content="<?= web_e($pageDescription) ?>">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="<?= web_e($canonical) ?>">
  <meta property="og:site_name" content="<?= web_e($company) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= web_e($canonical) ?>">
  <meta property="og:title" content="<?= web_e($pageTitle) ?>">
  <meta property="og:description" content="<?= web_e($pageDescription) ?>">
  <meta property="og:image" content="<?= web_e($ogImage) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= web_e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= web_e($pageDescription) ?>">
  <meta name="twitter:image" content="<?= web_e($ogImage) ?>">
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.16">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.0">
  <link rel="stylesheet" href="assets/css/lighting-calculator.css?v=3.1.0">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="artdon-page lighting-calculator-page">
  <nav class="ap-breadcrumb" aria-label="Breadcrumb">
    <a href="index.php">Home</a><span>/</span><strong>IES Lighting Calculator</strong>
  </nav>

  <section class="ap-hero lighting-calculator-hero" aria-labelledby="lighting-calculator-title">
    <div>
      <p class="ap-kicker">Technical resources</p>
      <h1 id="lighting-calculator-title">IES Lighting Calculator</h1>
    </div>
    <p class="ap-hero-copy">Upload an IES file, configure your room and luminaire layout, and calculate the illuminance distribution.</p>
  </section>

  <section class="lighting-calculator-shell" aria-label="Lighting calculator workspace">
    <section class="lighting-calculator-panel" aria-labelledby="calculator-settings-title">
      <header>
        <p>Step 01</p>
        <h2 id="calculator-settings-title">CALCULATION SETTINGS</h2>
      </header>
      <div class="lighting-calculator-body">
        <section class="lc-group">
          <h3>IES FILE</h3>
          <div class="lc-upload" id="lcDropzone">
            <input id="lcIesFile" type="file" accept=".ies">
            <label for="lcIesFile">
              <strong>Upload IES</strong>
              <span>Drag & drop an LM-63 .ies file here. Maximum 2 MB.</span>
            </label>
          </div>
          <div class="lc-filebar" id="lcFilebar" hidden>
            <span id="lcFileName"></span>
          </div>
          <dl class="lc-info" id="lcIesInfo" aria-label="IES file information">
            <div><dt>Power</dt><dd>N/A</dd></div>
            <div><dt>Vertical angles</dt><dd>-</dd></div>
            <div><dt>Horizontal angles</dt><dd>-</dd></div>
          </dl>
          <div class="lc-row-actions">
            <button type="button" id="lcReplaceFile">Replace</button>
            <button type="button" id="lcClearFile">Remove</button>
          </div>
          <div class="lc-message" id="lcMessage" role="status" aria-live="polite"></div>
        </section>

        <section class="lc-group">
          <h3>ROOM DIMENSIONS</h3>
          <div class="lc-form-grid">
            <label>Room Length (m)<input id="lcRoomLength" data-recalc type="number" min="1" max="100" step="0.1" value="10"></label>
            <label>Room Width (m)<input id="lcRoomWidth" data-recalc type="number" min="1" max="100" step="0.1" value="6"></label>
            <label>Room Height (m)<input id="lcRoomHeight" data-recalc type="number" min="1" max="30" step="0.1" value="3"></label>
          </div>
        </section>

        <section class="lc-group">
          <h3>MOUNTING</h3>
          <div class="lc-form-grid">
            <label>Luminaire Mounting Height (m)<input id="lcMountHeight" data-recalc type="number" min="0.5" max="30" step="0.1" value="3"></label>
            <label>Work Plane Height (m)<input id="lcWorkHeight" data-recalc type="number" min="0" max="10" step="0.1" value="0.8"></label>
          </div>
          <div class="lc-effective">Effective Height <strong id="lcEffectiveHeight">2.20 m</strong></div>
          <p class="lc-field-error" id="lcMountError"></p>
        </section>

        <section class="lc-group">
          <h3>LUMINAIRE LAYOUT</h3>
          <div class="lc-segmented" role="tablist" aria-label="Luminaire layout mode">
            <button type="button" class="is-active" id="lcModeSpacing" data-layout-mode="spacing">BY SPACING</button>
            <button type="button" id="lcModeQuantity" data-layout-mode="quantity">BY QUANTITY</button>
            <button type="button" id="lcModeManual" data-layout-mode="manual">MANUAL PLACEMENT</button>
          </div>
          <div class="lc-mode-panel" id="lcSpacingPanel">
            <div class="lc-form-grid">
              <label>X Spacing (m)<input id="lcXSpacing" data-recalc type="number" min="0.1" max="50" step="0.1" value="2"></label>
              <label>Y Spacing (m)<input id="lcYSpacing" data-recalc type="number" min="0.1" max="50" step="0.1" value="2"></label>
            </div>
          </div>
          <div class="lc-mode-panel" id="lcQuantityPanel" hidden>
            <div class="lc-form-grid">
              <label>Columns<input id="lcCols" data-recalc type="number" min="1" max="50" step="1" value="5"></label>
              <label>Rows<input id="lcRows" data-recalc type="number" min="1" max="50" step="1" value="3"></label>
            </div>
          </div>
          <div class="lc-mode-panel" id="lcManualPanel" hidden>
            <div class="lc-row-actions">
              <button type="button" id="lcAddLuminaire">Add Luminaire</button>
              <button type="button" id="lcClearManual">Clear Manual Layout</button>
            </div>
            <label class="lc-check"><input id="lcSnapEnabled" type="checkbox" checked> Snap to grid</label>
            <div class="lc-quick-grid" aria-label="Manual snap spacing">
              <button type="button" data-snap="0.10">0.10</button>
              <button type="button" data-snap="0.25" class="is-active">0.25</button>
              <button type="button" data-snap="0.50">0.50</button>
            </div>
            <div class="lc-form-grid">
              <label>Selected X (m)<input id="lcSelectedX" type="number" min="0" max="100" step="0.01" disabled></label>
              <label>Selected Y (m)<input id="lcSelectedY" type="number" min="0" max="100" step="0.01" disabled></label>
            </div>
            <p class="lc-manual-hint" id="lcManualHint">Click Add Luminaire, then click inside the room to place it.</p>
          </div>
          <div class="lc-form-grid">
            <label>Left Offset (m)<input id="lcLeftOffset" data-recalc type="number" min="0" max="50" step="0.1" value="1"></label>
            <label>Right Offset (m)<input id="lcRightOffset" data-recalc type="number" min="0" max="50" step="0.1" value="1"></label>
            <label>Front Offset (m)<input id="lcFrontOffset" data-recalc type="number" min="0" max="50" step="0.1" value="1"></label>
            <label>Back Offset (m)<input id="lcBackOffset" data-recalc type="number" min="0" max="50" step="0.1" value="1"></label>
          </div>
          <div class="lc-effective"><span id="lcLayoutSummary">5 × 3 = 15 Total Luminaires</span></div>
          <p class="lc-field-error" id="lcLayoutError"></p>
        </section>

        <section class="lc-group">
          <h3>CALCULATION GRID</h3>
          <div class="lc-quick-grid" aria-label="Grid spacing shortcuts">
            <button type="button" data-grid="0.25">0.25</button>
            <button type="button" data-grid="0.50" class="is-active">0.50</button>
            <button type="button" data-grid="1.00">1.00</button>
          </div>
          <div class="lc-form-grid">
            <label>Grid Spacing (m)<input id="lcGridSpacing" data-recalc type="number" min="0.25" max="5" step="0.05" value="0.5"></label>
            <label>Maintenance Factor<input id="lcMaintenance" data-recalc type="number" min="0.1" max="1" step="0.01" value="0.8"></label>
            <label>Target Illuminance (lux)<input id="lcTargetLux" data-recalc type="number" min="1" max="5000" step="10" value="500"></label>
          </div>
        </section>

        <div class="lc-actions">
          <button class="lc-primary" type="button" id="lcCalculate">CALCULATE</button>
          <button type="button" id="lcReset">RESET</button>
          <button type="button" id="lcCancel" disabled>Cancel</button>
        </div>
      </div>
    </section>

    <section class="lighting-calculator-panel lighting-calculator-panel-main" aria-labelledby="room-layout-title">
      <header>
        <p>Step 02</p>
        <h2 id="room-layout-title">ROOM LAYOUT</h2>
      </header>
      <div class="lighting-calculator-body lc-canvas-body">
        <div class="lc-canvas-toolbar">
          <div class="lc-tabs" role="tablist" aria-label="Result view">
            <button type="button" class="is-active" data-view="layout">LAYOUT</button>
            <button type="button" data-view="heatmap">HEATMAP</button>
            <button type="button" data-view="luxgrid">LUX GRID</button>
          </div>
          <div class="lc-layout-state" id="lcLayoutState">AUTO LAYOUT</div>
        </div>
        <div class="lc-layout-tools">
          <button type="button" id="lcRestoreAuto">Restore auto arrangement</button>
          <button type="button" id="lcCopyLuminaire" disabled>Copy selected</button>
          <button type="button" id="lcDeleteLuminaire" disabled>Delete selected</button>
        </div>
        <div class="lc-layout-preview" id="lcLayoutPreview" aria-label="Room layout preview">
          <span>Set room dimensions and upload an IES file to calculate illuminance.</span>
        </div>
        <div class="lc-hover-readout" id="lcHoverReadout">X 0.00 m, Y 0.00 m</div>
        <div class="lc-layout-meta" id="lcLayoutMeta"></div>
      </div>
    </section>

    <section class="lighting-calculator-panel" aria-labelledby="calculation-results-title">
      <header>
        <p>Step 03</p>
        <h2 id="calculation-results-title">CALCULATION RESULTS</h2>
      </header>
      <div class="lighting-calculator-body">
        <div class="lc-status is-neutral" id="lcTargetStatus">NEEDS RECALCULATION</div>
        <div class="lc-loading" id="lcLoading" hidden>
          <span></span>
          <strong>Calculating illuminance...</strong>
        </div>
        <dl class="lc-results" id="lcResults">
          <div><dt>Average Illuminance</dt><dd>-</dd></div>
          <div><dt>Minimum Illuminance</dt><dd>-</dd></div>
          <div><dt>Maximum Illuminance</dt><dd>-</dd></div>
          <div><dt>Uniformity</dt><dd>-</dd></div>
          <div><dt>Total Luminaires</dt><dd>-</dd></div>
          <div><dt>Total Power</dt><dd>-</dd></div>
          <div><dt>Grid Points</dt><dd>-</dd></div>
          <div><dt>Calculation Time</dt><dd>-</dd></div>
        </dl>
        <p class="lc-note">This calculator provides a direct illuminance estimate based on IES photometric data. Room interreflection, obstructions and complex geometry are not included in this version.</p>
      </div>
    </section>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.14" defer></script>
<script src="assets/js/lighting-calculator/ies-parser.js?v=3.1.0" defer></script>
<script src="assets/js/lighting-calculator/lux-engine.js?v=3.1.0" defer></script>
<script src="assets/js/lighting-calculator/room-layout.js?v=3.1.0" defer></script>
<script src="assets/js/lighting-calculator/heatmap-renderer.js?v=3.0.1" defer></script>
<script src="assets/js/lighting-calculator/calculator-app.js?v=3.1.0" defer></script>
</body>
</html>
