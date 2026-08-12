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
  <link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.18">
  <link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4">
  <link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.0">
  <link rel="stylesheet" href="assets/css/lighting-calculator.css?v=4.0.0">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="artdon-page lighting-calculator-page">
  <section class="lc-workbench-intro" aria-labelledby="lighting-calculator-title">
    <div>
      <p class="ap-kicker">Technical resources</p>
      <h1 id="lighting-calculator-title">IES Lighting Calculator</h1>
    </div>
    <p>Upload an IES file, configure the room and luminaire layout, and calculate the illuminance distribution.</p>
  </section>

  <section class="lc-summary-strip" aria-label="Current photometric calculation summary">
    <div class="lc-summary-file">
      <span class="lc-summary-icon lc-summary-file-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2.8h8l4 4V21H6z"></path><path d="M14 2.8V7h4M9 12h6M9 15h6"></path></svg></span>
      <div class="lc-summary-file-copy">
        <strong id="lcSummaryFile">No IES file loaded</strong>
        <span>IES Photometric File</span>
        <small id="lcSummaryMeta">Upload an LM-63 .ies file to begin</small>
      </div>
      <button type="button" id="lcSummaryReplace">UPLOAD IES</button>
    </div>
    <div class="lc-summary-metrics">
      <div class="lc-summary-metric"><span class="lc-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M8 7V4h8v3M12 7v11M8.5 18h7M6 12h3M15 12h3"></path></svg></span><div><strong id="lcSummaryFixtures">15</strong><small>Fixtures</small></div></div>
      <div class="lc-summary-metric"><span class="lc-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"></path></svg></span><div><strong id="lcSummaryAverage">—</strong><small>Average Lux</small></div></div>
      <div class="lc-summary-metric"><span class="lc-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"></circle><path d="M12 4a8 8 0 0 0 0 16M12 4a8 8 0 0 1 0 16"></path></svg></span><div><strong id="lcSummaryUniformity">—</strong><small>Uniformity</small></div></div>
      <div class="lc-summary-metric"><span class="lc-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m13 2-7 11h5l-1 9 8-12h-5z"></path></svg></span><div><strong id="lcSummaryPower">—</strong><small>Total Power</small></div></div>
      <div class="lc-summary-metric lc-summary-assessment"><span class="lc-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16.5 8.5"></path></svg></span><div><strong id="lcSummaryStatus">Upload IES</strong><small>Lighting level</small></div></div>
    </div>
  </section>

  <section class="lighting-calculator-shell" aria-label="Lighting calculator workspace">
    <section class="lighting-calculator-panel" aria-labelledby="calculator-settings-title">
      <header class="lc-panel-heading">
        <span class="lc-step-badge">1</span>
        <h2 id="calculator-settings-title">CALCULATION SETTINGS</h2>
      </header>
      <div class="lighting-calculator-body">
        <section class="lc-group lc-group-card lc-group-file">
          <h3><span class="lc-group-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6zM14 3v5h4"></path></svg></span>IES FILE</h3>
          <div class="lc-upload" id="lcDropzone">
            <input id="lcIesFile" type="file" accept=".ies" disabled>
            <label for="lcIesFile">
              <strong>Upload IES</strong>
              <span>Drag & drop an LM-63 .ies file here. Maximum 2 MB.</span>
            </label>
          </div>
          <div class="lc-filebar" id="lcFilebar" hidden>
            <span id="lcFileName"></span>
          </div>
          <dl class="lc-info lc-info-primary" id="lcIesInfo" aria-label="Key IES file information">
            <div><dt>Input power</dt><dd>N/A</dd></div>
            <div><dt>Total rated lumens</dt><dd>N/A</dd></div>
            <div><dt>Beam angle</dt><dd>N/A</dd></div>
          </dl>
          <details class="lc-info-details" id="lcIesDetails" hidden></details>
          <div class="lc-row-actions">
            <button type="button" id="lcReplaceFile">Replace</button>
            <button type="button" id="lcClearFile">Remove</button>
          </div>
          <div class="lc-message" id="lcMessage" role="status" aria-live="polite"></div>
        </section>

        <section class="lc-group lc-group-card lc-group-room">
          <h3><span class="lc-group-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4zM4 7v10l8 4 8-4V7M12 11v10"></path></svg></span>ROOM DIMENSIONS</h3>
          <div class="lc-form-grid">
            <label>Room Length (m)<input id="lcRoomLength" data-recalc type="number" min="1" max="100" step="0.1" value="10"></label>
            <label>Room Width (m)<input id="lcRoomWidth" data-recalc type="number" min="1" max="100" step="0.1" value="6"></label>
            <label>Room Height (m)<input id="lcRoomHeight" data-recalc type="number" min="1" max="30" step="0.1" value="3"></label>
          </div>
        </section>

        <section class="lc-group lc-group-card lc-group-mount">
          <h3><span class="lc-group-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h14M12 4v5M8 13h8M7 20h10M9 9h6l2 4H7z"></path></svg></span>MOUNTING</h3>
          <div class="lc-form-grid">
            <label>Luminaire Mounting Height (m)<input id="lcMountHeight" data-recalc type="number" min="0.5" max="30" step="0.1" value="3"></label>
            <label>Work Plane Height (m)<input id="lcWorkHeight" data-recalc type="number" min="0" max="10" step="0.1" value="0.8"></label>
          </div>
          <div class="lc-effective">Effective Height <strong id="lcEffectiveHeight">2.20 m</strong></div>
          <p class="lc-field-error" id="lcMountError"></p>
        </section>

        <section class="lc-group lc-group-card lc-group-layout">
          <h3><span class="lc-group-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="6" cy="6" r="1.5"></circle><circle cx="12" cy="6" r="1.5"></circle><circle cx="18" cy="6" r="1.5"></circle><circle cx="6" cy="12" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="18" cy="12" r="1.5"></circle><circle cx="6" cy="18" r="1.5"></circle><circle cx="12" cy="18" r="1.5"></circle><circle cx="18" cy="18" r="1.5"></circle></svg></span>LUMINAIRE LAYOUT</h3>
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
              <label>Columns<input id="lcCols" data-recalc type="number" min="1" max="20" step="1" value="5"></label>
              <label>Rows<input id="lcRows" data-recalc type="number" min="1" max="20" step="1" value="3"></label>
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

        <section class="lc-group lc-group-card lc-group-grid">
          <h3><span class="lc-group-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM4 10h16M4 15h16M10 4v16M15 4v16"></path></svg></span>CALCULATION GRID</h3>
          <div class="lc-quick-grid" aria-label="Grid spacing shortcuts">
            <button type="button" data-grid="0.25">0.25</button>
            <button type="button" data-grid="0.50" class="is-active">0.50</button>
            <button type="button" data-grid="1.00">1.00</button>
          </div>
          <div class="lc-form-grid">
            <label>Grid Spacing (m)<input id="lcGridSpacing" data-recalc type="number" min="0.25" max="5" step="0.05" value="0.5"></label>
            <label>Maintenance Factor<input id="lcMaintenance" data-recalc type="number" min="0.1" max="1" step="0.01" value="0.8"></label>
            <label>Target Average Illuminance (lux)<input id="lcTargetLux" data-target-input type="number" min="1" max="5000" step="10" value="500"></label>
          </div>
          <div class="lc-effective"><span id="lcCalculationEstimate">15 luminaires × 273 grid points</span></div>
          <p class="lc-field-error" id="lcComplexityError"></p>
          <p class="lc-field-error" id="lcTargetError"></p>
        </section>

        <div class="lc-actions">
          <button class="lc-primary" type="button" id="lcCalculate">CALCULATE</button>
          <button type="button" id="lcReset">RESET</button>
          <button type="button" id="lcCancel" disabled>Cancel</button>
        </div>
      </div>
    </section>

    <section class="lighting-calculator-panel lighting-calculator-panel-main" aria-labelledby="room-layout-title">
      <header class="lc-panel-heading">
        <span class="lc-step-badge">2</span>
        <h2 id="room-layout-title">ROOM LAYOUT</h2>
      </header>
      <div class="lighting-calculator-body lc-canvas-body">
        <div class="lc-canvas-toolbar">
          <div class="lc-tabs" role="tablist" aria-label="Result view">
            <button type="button" class="is-active" data-view="layout">LAYOUT</button>
            <button type="button" data-view="heatmap">HEATMAP</button>
            <button type="button" data-view="luxgrid">LUX GRID</button>
          </div>
          <label class="lc-check lc-heat-label-option" id="lcHeatLabelOption" hidden><input id="lcShowHeatLabels" type="checkbox"> Sample lux values</label>
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
      <header class="lc-panel-heading">
        <span class="lc-step-badge">3</span>
        <h2 id="calculation-results-title">CALCULATION RESULTS</h2>
      </header>
      <div class="lighting-calculator-body">
        <section class="lc-result-hero">
          <span>Average Illuminance</span>
          <strong id="lcResultAverage">—</strong>
          <div class="lc-status is-neutral" id="lcTargetStatus">NEEDS RECALCULATION</div>
        </section>
        <p class="lc-status-detail" id="lcStatusDetail">Upload an IES file and calculate to compare the average illuminance with your target.</p>
        <div class="lc-loading" id="lcLoading" hidden>
          <span></span>
          <strong>Calculating illuminance...</strong>
        </div>
        <dl class="lc-results" id="lcResults">
          <div><dt>Target Average</dt><dd>-</dd></div>
          <div><dt>Average vs Target</dt><dd>-</dd></div>
          <div><dt>Minimum / Maximum</dt><dd>-</dd></div>
          <div><dt>Uniformity</dt><dd>-</dd></div>
          <div><dt>Total Luminaires</dt><dd>-</dd></div>
          <div><dt>Total Power</dt><dd>-</dd></div>
          <div><dt>Grid Points</dt><dd>-</dd></div>
          <div><dt>Calculation Time</dt><dd>-</dd></div>
        </dl>
        <section class="lc-result-mini" id="lcMiniHeatmapCard" hidden>
          <header><strong>Illuminance Heatmap</strong><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M2.5 12s3.6-6 9.5-6 9.5 6 9.5 6-3.6 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.7"></circle></svg></span></header>
          <div id="lcMiniHeatmap" aria-label="Illuminance heatmap preview"></div>
        </section>
        <a class="lc-quote-button" href="index.php#contact">REQUEST QUOTATION</a>
        <p class="lc-note">This calculator provides a direct illuminance estimate based on IES photometric data. Room interreflection, obstructions and complex geometry are not included in this version.</p>
      </div>
    </section>
  </section>
</main>
<div class="lc-access-modal" id="lcAccessModal" hidden>
  <div class="lc-access-backdrop" data-lc-access-close></div>
  <section class="lc-access-dialog" role="dialog" aria-modal="true" aria-labelledby="lcAccessTitle" aria-describedby="lcAccessDescription">
    <button class="lc-access-close" type="button" data-lc-access-close aria-label="Close authorization dialog">×</button>
    <p class="lc-access-kicker">Professional calculation access</p>
    <h2 id="lcAccessTitle">Enter authorization code</h2>
    <p id="lcAccessDescription">An authorization code is required before selecting or dropping an IES file. Your photometric file remains in this browser and is not uploaded to our server.</p>
    <form id="lcAccessForm">
      <label for="lcAccessCode">Authorization code</label>
      <input id="lcAccessCode" name="code" type="text" maxlength="96" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" placeholder="ARTDON-XXXX-XXXX-XXXX" required>
      <p class="lc-access-message" id="lcAccessMessage" role="status" aria-live="polite"></p>
      <button class="lc-primary" type="submit" id="lcAccessSubmit">UNLOCK IES UPLOAD</button>
    </form>
    <p class="lc-access-help">Need access? Contact your Artdon Lighting representative.</p>
  </section>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.19" defer></script>
<script src="assets/js/lighting-calculator/ies-parser.js?v=3.3.0" defer></script>
<script src="assets/js/lighting-calculator/lux-engine.js?v=3.4.0" defer></script>
<script src="assets/js/lighting-calculator/room-layout.js?v=4.0.0" defer></script>
<script src="assets/js/lighting-calculator/heatmap-renderer.js?v=4.0.0" defer></script>
<script src="assets/js/lighting-calculator/calculator-app.js?v=4.0.0" defer></script>
</body>
</html>
