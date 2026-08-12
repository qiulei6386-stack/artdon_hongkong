(function () {
  'use strict';

  var MAX_FILE_SIZE = 2 * 1024 * 1024;
  var state = {
    ies: null,
    worker: null,
    jobId: 0,
    activeJobId: null,
    mode: 'spacing',
    view: 'layout',
    showHeatLabels: false,
    customLayout: false,
    luminaires: [],
    selectedId: null,
    result: null,
    dragId: null,
    watchdog: null,
    pendingInput: null,
    placingManual: false,
    hoverPoint: null,
    snapSpacing: 0.25,
    isBusy: false,
    lastEstimate: null,
    blockedReason: '',
    accessAuthorized: false,
    pendingAccessFile: null,
    pendingAccessAction: ''
  };

  function $(id) { return document.getElementById(id); }
  function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function readNumber(id) { return Number($(id).value); }
  function engineLimits() {
    return window.ArtdonLuxEngine && window.ArtdonLuxEngine.limits ? window.ArtdonLuxEngine.limits : { maxLuminaires: 400, maxGridPoints: 4000, maxCalculationWorkItems: 1000000 };
  }
  function assertLuminaireLimit(count) {
    var max = Number(engineLimits().maxLuminaires || 400);
    if (count > max) throw new Error('This layout would create ' + fmt(count, 0) + ' luminaires; the supported maximum is ' + fmt(max, 0) + '. Increase spacing or reduce rows and columns.');
  }
  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }
  function fmt(value, digits) {
    if (value == null || !Number.isFinite(Number(value))) return 'N/A';
    return Number(value).toLocaleString('en-US', { maximumFractionDigits: digits == null ? 1 : digits, minimumFractionDigits: 0 });
  }
  function setMessage(text, type) {
    var el = $('lcMessage');
    if (!el) return;
    el.textContent = text || '';
    el.className = 'lc-message' + (type ? ' is-' + type : '');
  }
  function accessModal(open) {
    var modal = $('lcAccessModal');
    if (!modal) return;
    modal.hidden = !open;
    modal.classList.toggle('is-open', open);
    document.body.classList.toggle('lc-access-open', open);
    if (open) {
      $('lcAccessMessage').textContent = '';
      window.setTimeout(function () { $('lcAccessCode').focus(); }, 30);
    }
  }
  function requestAccess(action, file) {
    if (state.accessAuthorized) {
      if (file) parseFile(file);
      else if (action === 'picker') $('lcIesFile').click();
      return;
    }
    state.pendingAccessAction = action || '';
    state.pendingAccessFile = file || null;
    accessModal(true);
  }
  function finishAccess() {
    var file = state.pendingAccessFile;
    var action = state.pendingAccessAction;
    state.pendingAccessFile = null;
    state.pendingAccessAction = '';
    accessModal(false);
    setMessage('Authorization confirmed. IES upload is unlocked for this browser.', 'ok');
    if (file) parseFile(file);
    else if (action === 'picker') window.setTimeout(function () { $('lcIesFile').click(); }, 30);
  }
  function checkAccessStatus() {
    fetch('/api/lighting-calculator-access.php', { credentials:'same-origin', headers:{'Accept':'application/json'} })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        state.accessAuthorized = !!(data && data.authorized);
        if (state.accessAuthorized && !$('lcAccessModal').hidden) finishAccess();
      })
      .catch(function () { state.accessAuthorized = false; });
  }
  function submitAccess(event) {
    event.preventDefault();
    var code = String($('lcAccessCode').value || '').trim();
    var message = $('lcAccessMessage');
    var button = $('lcAccessSubmit');
    if (!code) { message.textContent = 'Enter your authorization code.'; return; }
    button.disabled = true;
    message.textContent = 'Checking authorization…';
    fetch('/api/lighting-calculator-access.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Accept':'application/json','Content-Type':'application/json'},
      body:JSON.stringify({ code:code })
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) { return { response:response, data:data }; });
    }).then(function (result) {
      if (!result.response.ok || !result.data.authorized) throw new Error(result.data.message || 'Authorization failed.');
      state.accessAuthorized = true;
      $('lcAccessCode').value = '';
      finishAccess();
    }).catch(function (error) {
      message.textContent = error && error.message ? error.message : 'Authorization service is temporarily unavailable.';
    }).finally(function () { button.disabled = false; });
  }
  function setFieldError(id, text) {
    var el = $(id);
    if (el) el.textContent = text || '';
  }
  function setStatus(label, type, detail) {
    var el = $('lcTargetStatus');
    el.textContent = label;
    el.className = 'lc-status ' + (type || 'is-neutral');
    var summary = $('lcSummaryStatus');
    if (summary) {
      summary.textContent = label;
      summary.closest('.lc-summary-assessment').className = 'lc-summary-metric lc-summary-assessment ' + (type || 'is-neutral');
    }
    var detailEl = $('lcStatusDetail');
    if (detailEl) detailEl.textContent = detail || '';
  }
  function hasValidTarget() {
    var target = readNumber('lcTargetLux');
    return Number.isFinite(target) && target >= 1 && target <= 5000;
  }
  function syncCalculateAvailability() {
    var button = $('lcCalculate');
    if (button) button.disabled = state.isBusy || !state.ies || !state.lastEstimate || !hasValidTarget();
  }
  function markRecalculation() {
    var hadResult = !!state.result;
    state.result = null;
    clearResults();
    updateLayout();
    if (!state.ies) setStatus('UPLOAD IES TO START', 'is-neutral', 'Upload a valid IES file before calculating.');
    else if (!hasValidTarget()) setStatus('CHECK TARGET VALUE', 'is-low', 'Enter a valid target average illuminance between 1 and 5,000 lux.');
    else if (!state.lastEstimate) setStatus('CALCULATION BLOCKED', 'is-low', state.blockedReason || 'Correct the highlighted settings before calculating.');
    else if (hadResult) setStatus('NEEDS RECALCULATION', 'is-neutral', 'Settings changed. Run the calculation again before using the results.');
    else setStatus('READY TO CALCULATE', 'is-neutral', 'Settings are valid. Calculate to compare average illuminance with the target.');
    syncCalculateAvailability();
  }
  function roomInput() {
    return {
      length: readNumber('lcRoomLength'),
      width: readNumber('lcRoomWidth'),
      height: readNumber('lcRoomHeight'),
      mountingHeight: readNumber('lcMountHeight'),
      workplaneHeight: readNumber('lcWorkHeight')
    };
  }
  function validateRoom(room) {
    if (!(room.length > 0) || !(room.width > 0) || !(room.height > 0)) throw new Error('Room dimensions must be greater than zero.');
    if (room.mountingHeight > room.height) throw new Error('Luminaire mounting height cannot exceed room height.');
    if (room.workplaneHeight >= room.mountingHeight) throw new Error('Work plane height must be lower than luminaire mounting height.');
    return {
      length: room.length,
      width: room.width,
      height: room.height,
      mountingHeight: room.mountingHeight,
      workplaneHeight: room.workplaneHeight,
      effectiveHeight: room.mountingHeight - room.workplaneHeight
    };
  }
  function offsetsInput() {
    return {
      leftOffset: readNumber('lcLeftOffset'),
      rightOffset: readNumber('lcRightOffset'),
      frontOffset: readNumber('lcFrontOffset'),
      backOffset: readNumber('lcBackOffset')
    };
  }
  function assertOffsets(room, offsets) {
    ['leftOffset', 'rightOffset', 'frontOffset', 'backOffset'].forEach(function (key) {
      if (!Number.isFinite(offsets[key]) || offsets[key] < 0) throw new Error('Wall offsets cannot be negative.');
    });
    if (offsets.leftOffset + offsets.rightOffset >= room.length) throw new Error('Left and right offsets exceed the room length.');
    if (offsets.frontOffset + offsets.backOffset >= room.width) throw new Error('Front and back offsets exceed the room width.');
  }
  function makeLuminaire(id, x, y) {
    return { id: id, x: x, y: y, rotation: 0 };
  }
  function generateBySpacing(room, offsets) {
    var xSpacing = readNumber('lcXSpacing');
    var ySpacing = readNumber('lcYSpacing');
    if (!(xSpacing > 0) || !(ySpacing > 0)) throw new Error('Luminaire spacing must be greater than zero.');
    var usableX = room.length - offsets.leftOffset - offsets.rightOffset;
    var usableY = room.width - offsets.frontOffset - offsets.backOffset;
    var cols = Math.floor(usableX / xSpacing) + 1;
    var rows = Math.floor(usableY / ySpacing) + 1;
    if (cols < 1 || rows < 1) throw new Error('The layout must contain at least one luminaire.');
    assertLuminaireLimit(cols * rows);
    var luminaires = [];
    for (var r = 0; r < rows; r += 1) {
      for (var c = 0; c < cols; c += 1) {
        luminaires.push(makeLuminaire('L' + (luminaires.length + 1), offsets.leftOffset + c * xSpacing, offsets.frontOffset + r * ySpacing));
      }
    }
    return { room: room, luminaires: luminaires, meta: Object.assign({}, offsets, { rows: rows, cols: cols, total: luminaires.length, actualXSpacing: cols > 1 ? xSpacing : null, actualYSpacing: rows > 1 ? ySpacing : null }) };
  }
  function readInteger(id, label) {
    var value = readNumber(id);
    if (!Number.isInteger(value) || value < 1) throw new Error(label + ' must be a positive integer.');
    return value;
  }
  function generateByQuantity(room, offsets) {
    var cols = readInteger('lcCols', 'Columns');
    var rows = readInteger('lcRows', 'Rows');
    assertLuminaireLimit(cols * rows);
    var usableX = room.length - offsets.leftOffset - offsets.rightOffset;
    var usableY = room.width - offsets.frontOffset - offsets.backOffset;
    var xSpacing = cols > 1 ? usableX / (cols - 1) : 0;
    var ySpacing = rows > 1 ? usableY / (rows - 1) : 0;
    if (cols > 1 && xSpacing <= 0) throw new Error('X spacing must be greater than zero.');
    if (rows > 1 && ySpacing <= 0) throw new Error('Y spacing must be greater than zero.');
    var luminaires = [];
    for (var r = 0; r < rows; r += 1) {
      for (var c = 0; c < cols; c += 1) {
        var x = cols === 1 ? offsets.leftOffset + usableX / 2 : offsets.leftOffset + c * xSpacing;
        var y = rows === 1 ? offsets.frontOffset + usableY / 2 : offsets.frontOffset + r * ySpacing;
        luminaires.push(makeLuminaire('L' + (luminaires.length + 1), x, y));
      }
    }
    return { room: room, luminaires: luminaires, meta: Object.assign({}, offsets, { rows: rows, cols: cols, total: luminaires.length, actualXSpacing: cols > 1 ? xSpacing : null, actualYSpacing: rows > 1 ? ySpacing : null }) };
  }
  function autoLayout() {
    var room = validateRoom(roomInput());
    var offsets = offsetsInput();
    assertOffsets(room, offsets);
    if (state.mode === 'manual') {
      return { room: room, luminaires: [], meta: Object.assign({}, offsets, { rows: null, cols: null, total: 0, actualXSpacing: null, actualYSpacing: null }) };
    }
    return state.mode === 'spacing' ? generateBySpacing(room, offsets) : generateByQuantity(room, offsets);
  }
  function currentLayout() {
    var layout = autoLayout();
    if (state.customLayout || state.mode === 'manual') {
      layout.luminaires = state.luminaires.map(function (lum, index) {
        return makeLuminaire(lum.id || ('L' + (index + 1)), Math.max(0, Math.min(layout.room.length, lum.x)), Math.max(0, Math.min(layout.room.width, lum.y)));
      });
      layout.meta.total = layout.luminaires.length;
      layout.meta.cols = null;
      layout.meta.rows = null;
    } else {
      state.luminaires = layout.luminaires.map(function (lum) { return Object.assign({}, lum); });
    }
    return layout;
  }
  function updateEffectiveHeight() {
    var room = roomInput();
    var effective = room.mountingHeight - room.workplaneHeight;
    $('lcEffectiveHeight').textContent = Number.isFinite(effective) ? fmt(effective, 2) + ' m' : '-';
    setFieldError('lcMountError', effective > 0 ? '' : 'Work plane height must be lower than luminaire mounting height.');
  }
  function updateLayoutSummary(layout) {
    var meta = layout && layout.meta;
    var text = meta && meta.cols && meta.rows ? meta.cols + ' × ' + meta.rows + ' = ' + meta.total + ' Total Luminaires' : (layout ? layout.luminaires.length + ' Total Luminaires' : '-');
    $('lcLayoutSummary').textContent = text;
    $('lcLayoutState').textContent = state.customLayout ? 'CUSTOM LAYOUT' : 'AUTO LAYOUT';
    $('lcLayoutState').classList.toggle('is-custom', state.customLayout);
  }
  function updateLayout() {
    updateEffectiveHeight();
    syncTabs();
    try {
      var layout = currentLayout();
      setFieldError('lcLayoutError', '');
      updateLayoutSummary(layout);
      if ($('lcSummaryFixtures')) $('lcSummaryFixtures').textContent = fmt(layout.luminaires.length, 0);
      window.ArtdonRoomLayout.render($('lcLayoutPreview'), layout, { view: state.view, result: state.result, selectedId: state.selectedId, placementPoint: state.hoverPoint, showHeatLabels: state.showHeatLabels });
      if (window.ArtdonRoomLayout.renderMini) window.ArtdonRoomLayout.renderMini($('lcMiniHeatmap'), layout, state.result);
      if ($('lcMiniHeatmapCard')) $('lcMiniHeatmapCard').hidden = !state.result;
      updateSelectionButtons();
      $('lcLayoutMeta').textContent = metaText(layout);
      updateCalculationEstimate(layout);
      attachCanvasEvents();
    } catch (error) {
      state.blockedReason = error.message || 'Layout is invalid.';
      setFieldError('lcLayoutError', state.blockedReason);
      setFieldError('lcComplexityError', '');
      state.lastEstimate = null;
      if ($('lcCalculationEstimate')) $('lcCalculationEstimate').textContent = 'Calculation unavailable';
      $('lcLayoutMeta').textContent = '';
      updateLayoutSummary(null);
      syncCalculateAvailability();
    }
  }
  function estimateCalculation(layout) {
    if (!window.ArtdonLuxEngine || typeof window.ArtdonLuxEngine.estimate !== 'function') throw new Error('The calculation engine is not ready.');
    return window.ArtdonLuxEngine.estimate(payload(layout));
  }
  function updateCalculationEstimate(layout) {
    try {
      var estimate = estimateCalculation(layout);
      state.lastEstimate = estimate;
      state.blockedReason = '';
      setFieldError('lcComplexityError', '');
      if ($('lcCalculationEstimate')) $('lcCalculationEstimate').textContent = fmt(estimate.luminaireCount, 0) + ' luminaires × ' + fmt(estimate.gridPointCount, 0) + ' grid points · ' + fmt(estimate.workItems, 0) + ' evaluations';
    } catch (error) {
      state.lastEstimate = null;
      state.blockedReason = error.message || 'The calculation is too large.';
      setFieldError('lcComplexityError', state.blockedReason);
      if ($('lcCalculationEstimate')) $('lcCalculationEstimate').textContent = 'Reduce calculation size';
    }
    syncCalculateAvailability();
  }
  function metaText(layout) {
    var meta = layout.meta || {};
    var parts = [layout.luminaires.length + ' luminaires'];
    if (meta.actualXSpacing) parts.push('X spacing ' + fmt(meta.actualXSpacing, 2) + ' m');
    if (meta.actualYSpacing) parts.push('Y spacing ' + fmt(meta.actualYSpacing, 2) + ' m');
    if (state.result && state.result.points) parts.push(state.result.points.length + ' grid points');
    return parts.join(', ') + '.';
  }
  function metadataValue(ies, keys) {
    var metadata = ies && ies.metadata ? ies.metadata : {};
    for (var i = 0; i < keys.length; i += 1) {
      var value = String(metadata[keys[i]] || '').trim();
      if (value) return value.replace(/\n+/g, ' / ');
    }
    return '';
  }
  function formatBytes(bytes) {
    var value = Number(bytes || 0);
    if (!(value > 0)) return '';
    if (value < 1024) return fmt(value, 0) + ' B';
    if (value < 1024 * 1024) return fmt(value / 1024, 1) + ' KB';
    return fmt(value / (1024 * 1024), 2) + ' MB';
  }
  function degree(value) {
    return Number.isFinite(Number(value)) ? fmt(Number(value), 3) + '°' : 'N/A';
  }
  function angleRange(data) {
    if (!data || !data.count) return 'N/A';
    if (data.count === 1) return degree(data.min);
    return degree(data.min) + '–' + degree(data.max);
  }
  function angleSamples(data, noun) {
    if (!data || !data.count) return 'N/A';
    noun = noun || 'samples';
    if (data.count === 1) return '1 ' + noun.replace(/s$/, '');
    return fmt(data.count, 0) + ' ' + noun + ' · ' + (data.isRegular && data.regularStep != null ? degree(data.regularStep) + ' regular step' : 'variable steps');
  }
  function horizontalCoverage(ies) {
    var symmetry = String(ies && ies.horizontalSymmetry || '');
    if (symmetry === 'axial') return '0°–360° (axial symmetry)';
    if (symmetry === '0-90 quadrant mirrored') return '0°–360° (0°–90° mirrored)';
    if (symmetry === '0-180 mirrored') return '0°–360° (0°–180° mirrored)';
    if (symmetry === 'full-360') return '0°–360° measured';
    return angleRange(ies && ies.horizontalAngleData);
  }
  function dimensionsLabel(ies) {
    var d = ies && ies.dimensions;
    if (!d) return '';
    var unit = ies.unitsType === 1 ? 'ft' : (ies.unitsType === 2 ? 'm' : 'units');
    return fmt(d.width, 3) + ' × ' + fmt(d.length, 3) + ' × ' + fmt(d.height, 3) + ' ' + unit + ' (W × L × H)';
  }
  function beamPlaneLabel(axis) {
    var start = ((Number(axis) % 360) + 360) % 360;
    var opposite = (start + 180) % 360;
    return 'C ' + fmt(start, 1) + '°–' + fmt(opposite, 1) + '°';
  }
  function beamAngleLabel(ies) {
    var beam = ies && ies.beamAngleData;
    if (!beam || !beam.planes || !beam.planes.length) return 'N/A';
    if (beam.primaryAngle != null) return degree(beam.primaryAngle);
    var plane0 = beam.planes.find(function (plane) { return Math.abs(Number(plane.axis)) < 0.01; });
    var plane90 = beam.planes.find(function (plane) { return Math.abs(Number(plane.axis) - 90) < 0.01; });
    if (plane0 && plane90) return degree(plane0.angle) + ' × ' + degree(plane90.angle);
    return degree(beam.minAngle) + '–' + degree(beam.maxAngle);
  }
  function infoRow(label, value) {
    if (value == null || String(value).trim() === '') return '';
    return '<div><dt>' + esc(label) + '</dt><dd>' + esc(value) + '</dd></div>';
  }
  function renderInfoDetails(ies) {
    var details = $('lcIesDetails');
    if (!details) return;
    if (!ies) {
      details.hidden = true;
      details.innerHTML = '';
      return;
    }
    details.open = false;
    var peak = ies.candelaStats || {};
    var parsedRows = [
      ['File size', formatBytes(ies.sourceSizeBytes)],
      ['IES standard', ies.version || 'LM-63'],
      ['Manufacturer', metadataValue(ies, ['MANUFAC', 'MANUFACTURER'])],
      ['Catalog number', metadataValue(ies, ['LUMCAT', 'CATALOG', 'CATALOGNUMBER'])],
      ['Luminaire', metadataValue(ies, ['LUMINAIRE', 'LUMDESC'])],
      ['Lamp catalog', metadataValue(ies, ['LAMPCAT'])],
      ['Lamp', metadataValue(ies, ['LAMP'])],
      ['Test ID', metadataValue(ies, ['TEST', 'TESTNO'])],
      ['Test laboratory', metadataValue(ies, ['TESTLAB', 'LABORATORY'])],
      ['Issue date', metadataValue(ies, ['ISSUEDATE', 'DATE'])],
      ['Photometric type', ies.photometricTypeName || ('Type ' + ies.photometricType)],
      ['Measurement units', ies.unitsName || 'Unknown'],
      ['Photometry', ies.isAbsolutePhotometry ? 'Absolute' : 'Relative'],
      ['Input power', ies.inputWatts ? fmt(ies.inputWatts, 3) + ' W' : 'N/A'],
      ['Lamp count', fmt(ies.lampCount, 0)],
      ['Lumens per lamp', ies.lumensPerLamp < 0 ? 'N/A (absolute photometry)' : fmt(ies.lumensPerLamp, 1) + ' lm'],
      ['Total rated lumens', ies.totalRatedLumens ? fmt(ies.totalRatedLumens, 1) + ' lm' : 'N/A'],
      ['Luminaire dimensions', dimensionsLabel(ies)],
      ['Candela multiplier', fmt(ies.candelaMultiplier, 6)],
      ['Peak intensity', peak.max != null ? fmt(peak.max, 3) + ' cd' : 'N/A'],
      ['Peak direction', peak.peakHorizontalAngle != null && peak.peakVerticalAngle != null ? 'C ' + degree(peak.peakHorizontalAngle) + ' / γ ' + degree(peak.peakVerticalAngle) : 'N/A'],
      ['Beam angle', beamAngleLabel(ies)],
      ['Vertical angle range', angleRange(ies.verticalAngleData)],
      ['Vertical samples', angleSamples(ies.verticalAngleData, 'samples')],
      ['Measured C-plane range', angleRange(ies.horizontalAngleData)],
      ['Measured C-planes', angleSamples(ies.horizontalAngleData, 'planes')],
      ['Effective horizontal coverage', horizontalCoverage(ies)],
      ['Horizontal symmetry', ies.horizontalSymmetry || 'N/A'],
      ['TILT', ies.tilt || 'NONE'],
      ['Ballast factor', Number.isFinite(Number(ies.ballastFactor)) ? fmt(ies.ballastFactor, 6) : 'N/A']
    ].map(function (row) { return infoRow(row[0], row[1]); }).join('');
    var beamRows = ((ies.beamAngleData && ies.beamAngleData.planes) || []).map(function (plane) {
      return infoRow(beamPlaneLabel(plane.axis), degree(plane.angle) + ' at 50% peak intensity');
    }).join('');
    var metadataRows = (ies.metadataEntries || []).map(function (entry) {
      return infoRow('[' + entry.key + ']', entry.value || '—');
    }).join('');
    var headerRows = [
      ['Lamp count', fmt(ies.lampCount, 0)],
      ['Lumens per lamp', ies.lumensPerLamp < 0 ? 'Absolute photometry (-1)' : fmt(ies.lumensPerLamp, 1) + ' lm'],
      ['Candela multiplier', fmt(ies.candelaMultiplier, 6)],
      ['Photometric type code', fmt(ies.photometricType, 0)],
      ['Units type code', fmt(ies.unitsType, 0)],
      ['Ballast factor', fmt(ies.ballastFactor, 6)],
      ['Header factor 2', fmt(ies.futureUse, 6)],
      ['Input watts', ies.inputWatts ? fmt(ies.inputWatts, 3) + ' W' : 'N/A'],
      ['Candela values', ies.candelaStats ? fmt(ies.candelaStats.count, 0) : 'N/A'],
      ['Minimum candela', ies.candelaStats && ies.candelaStats.min != null ? fmt(ies.candelaStats.min, 3) + ' cd' : 'N/A'],
      ['Average candela sample', ies.candelaStats && ies.candelaStats.average != null ? fmt(ies.candelaStats.average, 3) + ' cd' : 'N/A']
    ].map(function (row) { return infoRow(row[0], row[1]); }).join('');
    var verticalValues = (ies.verticalAngles || []).map(degree).join(', ');
    var horizontalValues = (ies.horizontalAngles || []).map(degree).join(', ');
    details.hidden = false;
    details.innerHTML = '<summary>Complete IES file data</summary>'
      + '<div class="lc-info-details-body">'
      + '<h4>Parsed photometry</h4><dl class="lc-info lc-info-compact">' + parsedRows + '</dl>'
      + (beamRows ? '<h4>Beam angle (FWHM)</h4><dl class="lc-info lc-info-compact">' + beamRows + '</dl>' : '<p class="lc-angle-values">A complete 50% intensity crossing was not available in this file, so a beam angle could not be calculated.</p>')
      + '<h4>Photometric header</h4><dl class="lc-info lc-info-compact">' + headerRows + '</dl>'
      + (metadataRows ? '<h4>IES metadata</h4><dl class="lc-info lc-info-compact">' + metadataRows + '</dl>' : '')
      + '<h4>Vertical angles (' + esc(fmt((ies.verticalAngles || []).length, 0)) + ')</h4><p class="lc-angle-values">' + esc(verticalValues || 'N/A') + '</p>'
      + '<h4>Horizontal C-planes (' + esc(fmt((ies.horizontalAngles || []).length, 0)) + ')</h4><p class="lc-angle-values">' + esc(horizontalValues || 'N/A') + '</p>'
      + '</div>';
  }
  function renderInfo(ies) {
    var info = $('lcIesInfo');
    if (!ies) {
      info.innerHTML = [
        ['Input power', 'N/A'],
        ['Total rated lumens', 'N/A'],
        ['Beam angle', 'N/A']
      ].map(function (row) { return infoRow(row[0], row[1]); }).join('');
      if ($('lcSummaryFile')) $('lcSummaryFile').textContent = 'No IES file loaded';
      if ($('lcSummaryMeta')) $('lcSummaryMeta').textContent = 'Upload an LM-63 .ies file to begin';
      if ($('lcSummaryReplace')) $('lcSummaryReplace').textContent = 'UPLOAD IES';
      renderInfoDetails(null);
      return;
    }
    var rows = [
      ['Input power', ies.inputWatts ? fmt(ies.inputWatts, 3) + ' W' : 'N/A'],
      ['Total rated lumens', ies.totalRatedLumens ? fmt(ies.totalRatedLumens, 1) + ' lm' : 'N/A'],
      ['Beam angle', beamAngleLabel(ies)]
    ];
    info.innerHTML = rows.map(function (row) { return infoRow(row[0], row[1]); }).join('');
    if ($('lcSummaryFile')) $('lcSummaryFile').textContent = ies.sourceName || 'IES photometric file';
    if ($('lcSummaryMeta')) $('lcSummaryMeta').textContent = (ies.inputWatts ? fmt(ies.inputWatts, 1) + ' W' : 'Power N/A') + ' · ' + (ies.totalRatedLumens ? fmt(ies.totalRatedLumens, 0) + ' lm' : 'Lumens N/A') + ' · ' + beamAngleLabel(ies) + ' Beam';
    if ($('lcSummaryReplace')) $('lcSummaryReplace').textContent = 'REPLACE IES';
    renderInfoDetails(ies);
  }
  function clearFile() {
    state.ies = null;
    $('lcIesFile').value = '';
    $('lcFilebar').hidden = true;
    $('lcFileName').textContent = '';
    renderInfo(null);
    setMessage('', '');
    markRecalculation();
  }
  function parseFile(file) {
    if (!file) return;
    if (state.isBusy) { setMessage('Cancel the active calculation before replacing the IES file.', 'error'); return; }
    if (!/\.ies$/i.test(file.name)) { setMessage('Only .ies files are supported.', 'error'); return; }
    if (file.size > MAX_FILE_SIZE) { setMessage('The IES file is too large. Maximum size is 2 MB.', 'error'); return; }
    var reader = new FileReader();
    reader.onload = function () {
      try {
        state.ies = window.ArtdonIesParser.parse(String(reader.result || ''), { fileName: file.name, fileSizeBytes: file.size });
        $('lcFileName').textContent = file.name;
        $('lcFilebar').hidden = false;
        renderInfo(state.ies);
        setMessage('IES file loaded successfully.', 'ok');
        markRecalculation();
      } catch (error) {
        clearFile();
        setMessage(error && error.message ? error.message : 'The IES file could not be parsed.', 'error');
      }
    };
    reader.onerror = function () { setMessage('The IES file could not be read.', 'error'); };
    reader.readAsText(file);
  }
  function payload(layout) {
    return {
      room: {
        length: layout.room.length,
        width: layout.room.width,
        mountingHeight: layout.room.mountingHeight,
        workplaneHeight: layout.room.workplaneHeight
      },
      layout: { luminaires: layout.luminaires },
      gridSpacing: readNumber('lcGridSpacing'),
      maintenanceFactor: readNumber('lcMaintenance'),
      timeoutMs: 8000
    };
  }
  function ensureWorker() {
    if (state.worker) return state.worker;
    state.worker = new Worker('/assets/js/lighting-calculator/lux-worker.js?v=3.4.0');
    state.worker.onmessage = onWorkerMessage;
    state.worker.onerror = function (event) {
      event.preventDefault();
      failCalculation('The background calculator stopped unexpectedly. Check the settings and try again.');
    };
    state.worker.onmessageerror = function () {
      failCalculation('The background calculator returned an unreadable response. Please try again.');
    };
    return state.worker;
  }
  function setBusy(busy) {
    state.isBusy = !!busy;
    syncCalculateAvailability();
    $('lcCancel').disabled = !busy;
    $('lcLoading').hidden = !busy;
    $('lcCalculate').textContent = busy ? 'CALCULATING' : 'CALCULATE';
    all('#lcIesFile,#lcReplaceFile,#lcSummaryReplace,#lcClearFile,#lcReset,[data-recalc],[data-target-input],[data-layout-mode],[data-grid],[data-snap],#lcAddLuminaire,#lcClearManual,#lcRestoreAuto').forEach(function (control) {
      control.disabled = !!busy;
    });
    updateSelectionButtons();
  }
  function calculate() {
    if (state.isBusy) return;
    if (!state.ies) { setMessage('Upload a valid IES file before calculating.', 'error'); return; }
    if (!hasValidTarget()) {
      updateTargetAssessment();
      setMessage('Enter a valid target average illuminance between 1 and 5,000 lux.', 'error');
      return;
    }
    var layout;
    try { layout = currentLayout(); } catch (error) { setMessage(error.message || 'Layout is invalid.', 'error'); return; }
    if (!layout.luminaires.length) { setMessage('Add at least one luminaire before calculating.', 'error'); return; }
    var input;
    var estimate;
    try {
      input = payload(layout);
      estimate = window.ArtdonLuxEngine.estimate(input);
      setFieldError('lcComplexityError', '');
    } catch (error) {
      var blockedMessage = error && error.message ? error.message : 'The calculation is too large.';
      setFieldError('lcComplexityError', blockedMessage);
      setStatus('CALCULATION BLOCKED', 'is-low', blockedMessage);
      setMessage(blockedMessage, 'error');
      return;
    }
    state.result = null;
    clearResults();
    setBusy(true);
    state.activeJobId = 'job-' + (++state.jobId);
    state.pendingInput = input;
    setStatus('CALCULATING', 'is-running', fmt(estimate.luminaireCount, 0) + ' luminaires × ' + fmt(estimate.gridPointCount, 0) + ' grid points · ' + fmt(estimate.workItems, 0) + ' evaluations.');
    setMessage('Calculation started.', '');
    clearWatchdog();
    state.watchdog = window.setTimeout(function () {
      failCalculation('The calculation exceeded 12 seconds and was stopped. Increase grid spacing or reduce the luminaire count.', 'CALCULATION TIMED OUT');
    }, 12000);
    try {
      ensureWorker().postMessage({ type: 'calculate', jobId: state.activeJobId, ies: state.ies, input: state.pendingInput });
    } catch (error) {
      failCalculation(error && error.message ? error.message : 'The background calculator could not be started.');
    }
  }
  function cancel() {
    if (!state.activeJobId) return;
    resetWorker();
    state.activeJobId = null;
    state.pendingInput = null;
    clearWatchdog();
    setBusy(false);
    setMessage('Calculation cancelled.', '');
    setStatus('CALCULATION CANCELLED', 'is-neutral', 'The Worker was terminated and no partial result was used. You can adjust settings and calculate again.');
  }
  function clearWatchdog() {
    if (state.watchdog) window.clearTimeout(state.watchdog);
    state.watchdog = null;
  }
  function resetWorker() {
    if (state.worker) {
      try { state.worker.terminate(); } catch (error) {}
    }
    state.worker = null;
  }
  function failCalculation(message, label) {
    clearWatchdog();
    resetWorker();
    state.activeJobId = null;
    state.pendingInput = null;
    state.result = null;
    clearResults();
    setBusy(false);
    setMessage(message || 'Calculation failed.', 'error');
    setStatus(label || 'CALCULATION FAILED', 'is-low', message || 'No result was produced.');
  }
  function targetAssessment(result) {
    var target = readNumber('lcTargetLux');
    if (!Number.isFinite(target) || target < 1 || target > 5000) return null;
    var average = Number(result && result.metrics && result.metrics.eavg);
    if (!Number.isFinite(average)) return null;
    var difference = average - target;
    return { target: target, average: average, difference: difference, ratio: target > 0 ? average / target : 0, achieved: average >= target };
  }
  function resultLabels() {
    return ['Target Average','Average vs Target','Minimum / Maximum','Uniformity','Total Luminaires','Total Power','Grid Points','Calculation Time'];
  }
  function clearResults() {
    var results = $('lcResults');
    if (!results) return;
    results.innerHTML = resultLabels().map(function (label) { return '<div><dt>' + label + '</dt><dd>-</dd></div>'; }).join('');
    if ($('lcResultAverage')) $('lcResultAverage').textContent = '—';
    if ($('lcSummaryAverage')) $('lcSummaryAverage').textContent = '—';
    if ($('lcSummaryUniformity')) $('lcSummaryUniformity').textContent = '—';
    if ($('lcSummaryPower')) $('lcSummaryPower').textContent = '—';
    if ($('lcMiniHeatmapCard')) $('lcMiniHeatmapCard').hidden = true;
    if ($('lcMiniHeatmap')) $('lcMiniHeatmap').innerHTML = '';
    if ($('lcDownloadPdf')) $('lcDownloadPdf').disabled = true;
    if ($('lcExportPng')) $('lcExportPng').disabled = true;
  }
  function resultRows(result) {
    var m = result.metrics || {};
    var assessment = targetAssessment(result);
    var difference = assessment ? (assessment.difference >= 0 ? '+' : '') + fmt(assessment.difference, 0) + ' lx · ' + fmt(assessment.ratio * 100, 0) + '%' : 'N/A';
    return [
      ['Target Average', assessment ? fmt(assessment.target, 0) + ' lx' : 'N/A'],
      ['Average vs Target', difference],
      ['Minimum / Maximum', fmt(m.emin, 0) + ' / ' + fmt(m.emax, 0) + ' lx'],
      ['Uniformity', fmt(m.uniformity, 2)],
      ['Total Luminaires', fmt(m.luminaireCount, 0)],
      ['Total Power', m.totalPower ? fmt(m.totalPower, 1) + ' W' : 'N/A'],
      ['Grid Points', fmt(result.points ? result.points.length : 0, 0)],
      ['Calculation Time', fmt(m.calculationTimeMs || 0, 0) + ' ms']
    ];
  }
  function renderResults(result) {
    var metrics = result.metrics || {};
    if ($('lcResultAverage')) $('lcResultAverage').textContent = fmt(metrics.eavg, 0) + ' lx';
    if ($('lcSummaryAverage')) $('lcSummaryAverage').textContent = fmt(metrics.eavg, 0) + ' lx';
    if ($('lcSummaryUniformity')) $('lcSummaryUniformity').textContent = fmt(metrics.uniformity, 2);
    if ($('lcSummaryPower')) $('lcSummaryPower').textContent = metrics.totalPower ? fmt(metrics.totalPower, 1) + ' W' : 'N/A';
    if ($('lcDownloadPdf')) $('lcDownloadPdf').disabled = false;
    if ($('lcExportPng')) $('lcExportPng').disabled = false;
    $('lcResults').innerHTML = resultRows(result).map(function (row) {
      return '<div><dt>' + row[0] + '</dt><dd>' + row[1] + '</dd></div>';
    }).join('');
    var assessment = targetAssessment(result);
    if (!assessment) {
      setFieldError('lcTargetError', 'Target average illuminance must be between 1 and 5,000 lux.');
      setStatus('CHECK TARGET VALUE', 'is-low', 'Enter a valid target between 1 and 5,000 lux. The calculated illuminance result remains available.');
      return;
    }
    setFieldError('lcTargetError', '');
    if (assessment.achieved) {
      setStatus('TARGET ACHIEVED', 'is-ok', 'Average illuminance ' + fmt(assessment.average, 0) + ' lx meets the ' + fmt(assessment.target, 0) + ' lx target by ' + fmt(assessment.difference, 0) + ' lx.');
    } else if (assessment.ratio >= 0.9) {
      setStatus('NEAR TARGET', 'is-close', 'Average illuminance ' + fmt(assessment.average, 0) + ' lx is ' + fmt(Math.abs(assessment.difference), 0) + ' lx below the ' + fmt(assessment.target, 0) + ' lx target.');
    } else {
      setStatus('BELOW TARGET', 'is-low', 'Average illuminance ' + fmt(assessment.average, 0) + ' lx is ' + fmt(Math.abs(assessment.difference), 0) + ' lx below the ' + fmt(assessment.target, 0) + ' lx target.');
    }
  }
  function updateTargetAssessment() {
    var target = readNumber('lcTargetLux');
    if (!Number.isFinite(target) || target < 1 || target > 5000) {
      setFieldError('lcTargetError', 'Target average illuminance must be between 1 and 5,000 lux.');
      if (state.result) renderResults(state.result);
      else setStatus('CHECK TARGET VALUE', 'is-low', 'Enter a valid target average illuminance between 1 and 5,000 lux.');
      syncCalculateAvailability();
      return;
    }
    setFieldError('lcTargetError', '');
    if (state.result) renderResults(state.result);
    else if (state.ies && !state.lastEstimate) setStatus('CALCULATION BLOCKED', 'is-low', state.blockedReason || 'Correct the highlighted settings before calculating.');
    else if (state.ies) setStatus('READY TO CALCULATE', 'is-neutral', 'Settings are valid. Calculate to compare average illuminance with the target.');
    else setStatus('UPLOAD IES TO START', 'is-neutral', 'Upload a valid IES file before calculating.');
    syncCalculateAvailability();
  }
  function onWorkerMessage(event) {
    var data = event.data || {};
    if (!state.activeJobId || data.jobId !== state.activeJobId) return;
    if (data.type === 'progress') {
      setMessage('Calculating ' + data.done + ' of ' + data.total + ' grid points...', '');
      setStatus('CALCULATING', 'is-running', fmt(data.done, 0) + ' of ' + fmt(data.total, 0) + ' grid points completed.');
      return;
    }
    if (data.type === 'result') {
      clearWatchdog();
      state.activeJobId = null;
      state.pendingInput = null;
      state.result = data.result;
      setBusy(false);
      state.view = 'heatmap';
      syncTabs();
      renderResults(data.result);
      updateLayout();
      setMessage('Calculation completed. The parsed IES data remains loaded for recalculation.', 'ok');
      return;
    }
    if (data.type === 'cancelled') { cancel(); return; }
    if (data.type === 'error') failCalculation(data.message || 'Calculation failed.');
  }
  function updateSelectionButtons() {
    var hasSelection = !!state.selectedId;
    $('lcCopyLuminaire').disabled = state.isBusy || !hasSelection;
    $('lcDeleteLuminaire').disabled = state.isBusy || !hasSelection || state.luminaires.length <= 1;
    var xInput = $('lcSelectedX');
    var yInput = $('lcSelectedY');
    if (!xInput || !yInput) return;
    var lum = state.luminaires.find(function (item) { return String(item.id) === String(state.selectedId); });
    xInput.disabled = state.isBusy || !lum;
    yInput.disabled = state.isBusy || !lum;
    if (lum) {
      xInput.value = Number(lum.x).toFixed(2);
      yInput.value = Number(lum.y).toFixed(2);
    } else {
      xInput.value = '';
      yInput.value = '';
    }
  }
  function selectLuminaire(id) {
    state.selectedId = id;
    updateLayout();
  }
  function copySelected() {
    var lum = state.luminaires.find(function (item) { return String(item.id) === String(state.selectedId); });
    if (!lum) return;
    try { assertLuminaireLimit(state.luminaires.length + 1); } catch (error) { setMessage(error.message, 'error'); return; }
    var room = validateRoom(roomInput());
    var copy = makeCopy(lum, room);
    state.customLayout = true;
    state.luminaires.push(copy);
    state.selectedId = copy.id;
    markRecalculation();
  }
  function makeCopy(lum, room) {
    var id = 'L' + (Date.now() % 100000);
    return { id: id, x: Math.min(room.length, lum.x + 0.3), y: Math.min(room.width, lum.y + 0.3), rotation: lum.rotation || 0 };
  }
  function deleteSelected() {
    if (state.luminaires.length <= 1) return;
    state.luminaires = state.luminaires.filter(function (item) { return String(item.id) !== String(state.selectedId); });
    state.selectedId = null;
    state.customLayout = true;
    markRecalculation();
  }
  function restoreAuto() {
    state.mode = 'spacing';
    syncMode();
    state.customLayout = false;
    state.selectedId = null;
    state.placingManual = false;
    state.hoverPoint = null;
    markRecalculation();
  }
  function snapPoint(point) {
    if (!$('lcSnapEnabled') || !$('lcSnapEnabled').checked) return point;
    var step = Number(state.snapSpacing || 0.25);
    if (!(step > 0)) return point;
    var room = validateRoom(roomInput());
    return {
      x: Math.max(0, Math.min(room.length, Math.round(point.x / step) * step)),
      y: Math.max(0, Math.min(room.width, Math.round(point.y / step) * step))
    };
  }
  function nextLuminaireId() {
    var max = 0;
    state.luminaires.forEach(function (lum) {
      var match = String(lum.id || '').match(/^L(\d+)$/);
      if (match) max = Math.max(max, Number(match[1]));
    });
    return 'L' + (max + 1);
  }
  function startManualPlacement() {
    try { assertLuminaireLimit(state.luminaires.length + 1); } catch (error) { setMessage(error.message, 'error'); return; }
    state.mode = 'manual';
    state.customLayout = true;
    state.placingManual = true;
    syncMode();
    $('lcManualHint').textContent = 'Move over the room and click to place the luminaire.';
    updateLayout();
  }
  function clearManualLayout() {
    state.mode = 'manual';
    state.customLayout = true;
    state.luminaires = [];
    state.selectedId = null;
    state.placingManual = false;
    state.hoverPoint = null;
    syncMode();
    $('lcManualHint').textContent = 'Click Add Luminaire, then click inside the room to place it.';
    markRecalculation();
  }
  function placeManualLuminaire(point) {
    try { assertLuminaireLimit(state.luminaires.length + 1); } catch (error) { setMessage(error.message, 'error'); return; }
    var p = snapPoint(point);
    var lum = makeLuminaire(nextLuminaireId(), p.x, p.y);
    state.luminaires.push(lum);
    state.selectedId = lum.id;
    state.customLayout = true;
    state.placingManual = false;
    state.hoverPoint = null;
    $('lcManualHint').textContent = 'Luminaire placed. Select or drag it to adjust position.';
    markRecalculation();
  }
  function moveSelectedFromInputs() {
    var lum = state.luminaires.find(function (item) { return String(item.id) === String(state.selectedId); });
    if (!lum) return;
    var room = validateRoom(roomInput());
    var x = readNumber('lcSelectedX');
    var y = readNumber('lcSelectedY');
    if (!Number.isFinite(x) || !Number.isFinite(y)) return;
    lum.x = Math.max(0, Math.min(room.length, x));
    lum.y = Math.max(0, Math.min(room.width, y));
    state.customLayout = true;
    markRecalculation();
  }
  function attachCanvasEvents() {
    var preview = $('lcLayoutPreview');
    var svg = preview.querySelector('svg');
    if (!svg) return;
    all('[data-lum-id]', svg).forEach(function (node) {
      node.addEventListener('pointerdown', function (event) {
        if (state.isBusy) return;
        event.preventDefault();
        state.dragId = node.getAttribute('data-lum-id');
        state.selectedId = state.dragId;
        svg.setPointerCapture(event.pointerId);
        updateSelectionButtons();
      });
    });
    svg.onpointermove = function (event) {
      if (state.isBusy) return;
      var p = window.ArtdonRoomLayout.svgToRoom(svg, event.clientX, event.clientY);
      if (state.placingManual) p = snapPoint(p);
      var lux = nearestLux(p);
      var room = validateRoom(roomInput());
      $('lcHoverReadout').textContent = 'X ' + fmt(p.x, 2) + ' m, Y ' + fmt(p.y, 2) + ' m, Left ' + fmt(p.x, 2) + ' m, Right ' + fmt(room.length - p.x, 2) + ' m, Front ' + fmt(p.y, 2) + ' m, Back ' + fmt(room.width - p.y, 2) + ' m' + (lux == null ? '' : ', ' + fmt(lux, 1) + ' lx');
      if (state.placingManual && !state.dragId) {
        state.hoverPoint = p;
        window.ArtdonRoomLayout.render(preview, currentLayout(), { view: state.view, result: state.result, selectedId: state.selectedId, placementPoint: p });
        attachCanvasEvents();
        return;
      }
      if (!state.dragId) return;
      var lum = state.luminaires.find(function (item) { return String(item.id) === String(state.dragId); });
      if (!lum) return;
      p = snapPoint(p);
      state.hoverPoint = p;
      lum.x = p.x;
      lum.y = p.y;
      state.customLayout = true;
      setStatus('NEEDS RECALCULATION', 'is-neutral', 'Luminaire positions changed. Release the pointer, then calculate again.');
      window.ArtdonRoomLayout.render(preview, currentLayout(), { view: state.view, result: state.result, selectedId: state.selectedId, placementPoint: p });
      attachCanvasEvents();
    };
    svg.onpointerup = function () {
      if (state.dragId) {
        state.dragId = null;
        state.hoverPoint = null;
        markRecalculation();
      }
    };
    svg.onclick = function (event) {
      if (state.isBusy) return;
      var target = event.target.closest && event.target.closest('[data-lum-id]');
      if (target) {
        selectLuminaire(target.getAttribute('data-lum-id'));
        return;
      }
      if (state.placingManual) {
        placeManualLuminaire(window.ArtdonRoomLayout.svgToRoom(svg, event.clientX, event.clientY));
      }
    };
    svg.onpointerleave = function () {
      if (!state.dragId && state.hoverPoint) {
        state.hoverPoint = null;
        updateLayout();
      }
    };
  }
  function nearestLux(point) {
    if (!state.result || !state.result.points || !state.result.points.length) return null;
    var best = state.result.points[0];
    for (var i = 1; i < state.result.points.length; i += 1) {
      var p = state.result.points[i];
      if (Math.hypot(p.x - point.x, p.y - point.y) < Math.hypot(best.x - point.x, best.y - point.y)) best = p;
    }
    return best.lux;
  }
  function syncTabs() {
    all('.lc-tabs [data-view]').forEach(function (button) { button.classList.toggle('is-active', button.dataset.view === state.view); });
    var heatLabelOption = $('lcHeatLabelOption');
    if (heatLabelOption) heatLabelOption.hidden = state.view !== 'heatmap' || !state.result;
  }
  function resetAll() {
    ['lcRoomLength','lcRoomWidth','lcRoomHeight','lcMountHeight','lcWorkHeight','lcXSpacing','lcYSpacing','lcLeftOffset','lcRightOffset','lcFrontOffset','lcBackOffset','lcGridSpacing','lcMaintenance','lcTargetLux'].forEach(function (id) {
      var defaults = { lcRoomLength:10, lcRoomWidth:6, lcRoomHeight:3, lcMountHeight:3, lcWorkHeight:0.8, lcXSpacing:2, lcYSpacing:2, lcLeftOffset:1, lcRightOffset:1, lcFrontOffset:1, lcBackOffset:1, lcGridSpacing:0.5, lcMaintenance:0.8, lcTargetLux:500 };
      $(id).value = defaults[id];
    });
    $('lcCols').value = 5;
    $('lcRows').value = 3;
    state.mode = 'spacing';
    state.customLayout = false;
    state.selectedId = null;
    state.placingManual = false;
    state.hoverPoint = null;
    state.result = null;
    state.showHeatLabels = false;
    if ($('lcShowHeatLabels')) $('lcShowHeatLabels').checked = false;
    syncMode();
    markRecalculation();
  }
  function syncMode() {
    $('lcSpacingPanel').hidden = state.mode !== 'spacing';
    $('lcQuantityPanel').hidden = state.mode !== 'quantity';
    $('lcManualPanel').hidden = state.mode !== 'manual';
    $('lcModeSpacing').classList.toggle('is-active', state.mode === 'spacing');
    $('lcModeQuantity').classList.toggle('is-active', state.mode === 'quantity');
    $('lcModeManual').classList.toggle('is-active', state.mode === 'manual');
  }

  function reportSnapshot() {
    if (!state.ies || !state.result) return null;
    var layout = currentLayout();
    var assessment = targetAssessment(state.result);
    return {
      generatedAt: new Date().toISOString(),
      fileName: state.ies.sourceName || $('lcFileName').textContent || 'photometric-file.ies',
      logoUrl: (document.querySelector('.site-header img, header img') || {}).src || '/assets/img/logo-artdon.png',
      showBranding: !$('lcReportBranding') || $('lcReportBranding').checked,
      ies: {
        version: state.ies.version || 'IES LM-63',
        manufacturer: metadataValue(state.ies, ['MANUFAC', 'MANUFACTURER']) || 'N/A',
        catalogNumber: metadataValue(state.ies, ['LUMCAT', 'CATALOG', 'CATALOGNUMBER']) || 'N/A',
        testId: metadataValue(state.ies, ['TEST', 'TESTNO']) || 'N/A',
        inputWatts: Number(state.ies.inputWatts) || 0,
        totalRatedLumens: Number(state.ies.totalRatedLumens) || 0,
        beamAngle: beamAngleLabel(state.ies),
        photometricType: state.ies.photometricTypeName || ('Type ' + state.ies.photometricType),
        units: state.ies.unitsName || 'Unknown',
        peakIntensity: state.ies.candelaStats && Number(state.ies.candelaStats.max) || 0
      },
      room: {
        length: layout.room.length,
        width: layout.room.width,
        height: readNumber('lcRoomHeight'),
        mountingHeight: layout.room.mountingHeight,
        workplaneHeight: layout.room.workplaneHeight
      },
      layout: layout,
      settings: {
        gridSpacing: readNumber('lcGridSpacing'),
        maintenanceFactor: readNumber('lcMaintenance'),
        targetLux: readNumber('lcTargetLux')
      },
      result: state.result,
      assessment: assessment,
      status: $('lcTargetStatus').textContent || 'CALCULATED'
    };
  }

  function runReportExport(format) {
    var snapshot = reportSnapshot();
    var exporter = window.ArtdonReportExporter;
    if (!snapshot || !exporter) {
      setMessage('Calculate a valid result before exporting the report.', 'error');
      return;
    }
    var pdfButton = $('lcDownloadPdf');
    var pngButton = $('lcExportPng');
    var activeButton = format === 'pdf' ? pdfButton : pngButton;
    var originalLabel = activeButton.textContent;
    pdfButton.disabled = true;
    pngButton.disabled = true;
    activeButton.textContent = format === 'pdf' ? 'GENERATING PDF...' : 'GENERATING IMAGE...';
    setMessage('Preparing the report in this browser. The IES file is not uploaded.', '');
    var task = format === 'pdf' ? exporter.downloadPdf(snapshot) : exporter.downloadPng(snapshot);
    Promise.resolve(task).then(function () {
      setMessage(format === 'pdf' ? 'PDF report downloaded.' : 'Result image downloaded.', 'ok');
    }).catch(function (error) {
      setMessage(error && error.message ? error.message : 'The report could not be generated.', 'error');
    }).finally(function () {
      activeButton.textContent = originalLabel;
      pdfButton.disabled = !state.result;
      pngButton.disabled = !state.result;
    });
  }
  function bind() {
    var dropzone = $('lcDropzone');
    $('lcIesFile').disabled = false;
    $('lcIesFile').addEventListener('click', function (event) {
      if (!state.accessAuthorized) { event.preventDefault(); requestAccess('picker'); }
    });
    $('lcIesFile').addEventListener('change', function (event) {
      var file = event.target.files && event.target.files[0];
      if (!state.accessAuthorized) { event.target.value = ''; requestAccess('', file); return; }
      parseFile(file);
    });
    $('lcReplaceFile').addEventListener('click', function () { requestAccess('picker'); });
    $('lcClearFile').addEventListener('click', clearFile);
    $('lcCalculate').addEventListener('click', calculate);
    $('lcCancel').addEventListener('click', cancel);
    $('lcReset').addEventListener('click', resetAll);
    $('lcRestoreAuto').addEventListener('click', restoreAuto);
    $('lcSummaryReplace').addEventListener('click', function () { requestAccess('picker'); });
    $('lcCopyLuminaire').addEventListener('click', copySelected);
    $('lcDeleteLuminaire').addEventListener('click', deleteSelected);
    all('[data-layout-mode]').forEach(function (button) {
      button.addEventListener('click', function () {
        var nextMode = button.dataset.layoutMode;
        if (nextMode === 'manual') {
          if (!state.luminaires.length) {
            try { state.luminaires = currentLayout().luminaires.map(function (lum) { return Object.assign({}, lum); }); } catch (error) { state.luminaires = []; }
          }
          state.customLayout = true;
        } else {
          state.customLayout = false;
          state.placingManual = false;
          state.hoverPoint = null;
        }
        state.mode = nextMode;
        syncMode();
        markRecalculation();
      });
    });
    $('lcAddLuminaire').addEventListener('click', startManualPlacement);
    $('lcClearManual').addEventListener('click', clearManualLayout);
    $('lcSelectedX').addEventListener('change', moveSelectedFromInputs);
    $('lcSelectedY').addEventListener('change', moveSelectedFromInputs);
    all('[data-snap]').forEach(function (button) {
      button.addEventListener('click', function () {
        state.snapSpacing = Number(button.dataset.snap || 0.25);
        all('[data-snap]').forEach(function (snapButton) { snapButton.classList.toggle('is-active', snapButton === button); });
      });
    });
    all('.lc-tabs [data-view]').forEach(function (button) {
      button.addEventListener('click', function () { state.view = button.dataset.view; syncTabs(); updateLayout(); });
    });
    $('lcShowHeatLabels').addEventListener('change', function () {
      state.showHeatLabels = this.checked;
      updateLayout();
    });
    $('lcDownloadPdf').addEventListener('click', function () { runReportExport('pdf'); });
    $('lcExportPng').addEventListener('click', function () { runReportExport('png'); });
    all('[data-grid]').forEach(function (button) {
      button.addEventListener('click', function () {
        $('lcGridSpacing').value = button.dataset.grid;
        all('[data-grid]').forEach(function (b) { b.classList.toggle('is-active', b === button); });
        markRecalculation();
      });
    });
    all('[data-recalc]').forEach(function (input) {
      input.addEventListener('input', function () {
        if (!state.customLayout && ['lcRoomLength','lcRoomWidth','lcLeftOffset','lcRightOffset','lcFrontOffset','lcBackOffset','lcXSpacing','lcYSpacing','lcCols','lcRows'].indexOf(input.id) >= 0) state.selectedId = null;
        markRecalculation();
      });
    });
    $('lcTargetLux').addEventListener('input', updateTargetAssessment);
    ['dragenter', 'dragover'].forEach(function (type) {
      dropzone.addEventListener(type, function (event) { event.preventDefault(); dropzone.classList.add('is-dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (type) {
      dropzone.addEventListener(type, function (event) { event.preventDefault(); dropzone.classList.remove('is-dragover'); });
    });
    dropzone.addEventListener('drop', function (event) {
      var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
      if (!state.accessAuthorized) { requestAccess('', file); return; }
      parseFile(file);
    });
    $('lcAccessForm').addEventListener('submit', submitAccess);
    all('[data-lc-access-close]').forEach(function (button) {
      button.addEventListener('click', function () {
        state.pendingAccessFile = null;
        state.pendingAccessAction = '';
        accessModal(false);
      });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !$('lcAccessModal').hidden) {
        state.pendingAccessFile = null;
        state.pendingAccessAction = '';
        accessModal(false);
      }
    });
    checkAccessStatus();
    renderInfo(null);
    syncMode();
    markRecalculation();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();

  window.ArtdonCalculatorApp = {
    generateBySpacing: generateBySpacing,
    generateByQuantity: generateByQuantity,
    validateRoom: validateRoom,
    reportSnapshot: reportSnapshot
  };
})();
