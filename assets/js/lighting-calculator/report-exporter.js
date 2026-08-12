(function (window, document) {
  'use strict';

  var PAGE_WIDTH = 1240;
  var PAGE_HEIGHT = 1754;
  var RED = '#d71920';
  var BLACK = '#111111';
  var TEXT = '#303030';
  var MUTED = '#6c6c6c';
  var LINE = '#dedede';
  var LIGHT = '#f7f7f7';

  function fmt(value, digits) {
    var number = Number(value);
    if (!Number.isFinite(number)) return 'N/A';
    return number.toLocaleString('en-US', { maximumFractionDigits: digits == null ? 1 : digits, minimumFractionDigits: 0 });
  }

  function canvasPage() {
    var canvas = document.createElement('canvas');
    canvas.width = PAGE_WIDTH;
    canvas.height = PAGE_HEIGHT;
    var context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, PAGE_WIDTH, PAGE_HEIGHT);
    context.textBaseline = 'alphabetic';
    return { canvas: canvas, context: context };
  }

  function roundedRect(context, x, y, width, height, radius, fill, stroke) {
    var r = Math.min(radius, width / 2, height / 2);
    context.beginPath();
    context.moveTo(x + r, y);
    context.arcTo(x + width, y, x + width, y + height, r);
    context.arcTo(x + width, y + height, x, y + height, r);
    context.arcTo(x, y + height, x, y, r);
    context.arcTo(x, y, x + width, y, r);
    context.closePath();
    if (fill) {
      context.fillStyle = fill;
      context.fill();
    }
    if (stroke) {
      context.strokeStyle = stroke;
      context.lineWidth = 1.5;
      context.stroke();
    }
  }

  function setFont(context, size, weight) {
    context.font = (weight || 500) + ' ' + size + 'px Arial, Helvetica, sans-serif';
  }

  function drawText(context, value, x, y, size, weight, color, align) {
    context.save();
    setFont(context, size, weight);
    context.fillStyle = color || TEXT;
    context.textAlign = align || 'left';
    context.fillText(String(value == null ? '' : value), x, y);
    context.restore();
  }

  function wrapText(context, value, x, y, maxWidth, lineHeight, maxLines) {
    var words = String(value || '').split(/\s+/);
    var lines = [];
    var line = '';
    for (var i = 0; i < words.length; i += 1) {
      var test = line ? line + ' ' + words[i] : words[i];
      if (context.measureText(test).width > maxWidth && line) {
        lines.push(line);
        line = words[i];
      } else {
        line = test;
      }
    }
    if (line) lines.push(line);
    if (maxLines && lines.length > maxLines) {
      lines = lines.slice(0, maxLines);
      while (context.measureText(lines[lines.length - 1] + '...').width > maxWidth) lines[lines.length - 1] = lines[lines.length - 1].slice(0, -1);
      lines[lines.length - 1] += '...';
    }
    lines.forEach(function (text, index) { context.fillText(text, x, y + index * lineHeight); });
    return lines.length * lineHeight;
  }

  function sectionTitle(context, title, y) {
    drawText(context, title.toUpperCase(), 70, y, 18, 760, BLACK);
    context.fillStyle = RED;
    context.fillRect(70, y + 12, 42, 3);
  }

  function metricCard(context, x, y, width, label, value, accent) {
    roundedRect(context, x, y, width, 100, 8, '#ffffff', LINE);
    drawText(context, label.toUpperCase(), x + 16, y + 27, 11, 700, MUTED);
    drawText(context, value, x + 16, y + 70, 25, 760, accent || BLACK);
  }

  function detailCard(context, x, y, width, title, rows) {
    var rowHeight = 35;
    var height = 48 + rows.length * rowHeight;
    roundedRect(context, x, y, width, height, 8, '#ffffff', LINE);
    drawText(context, title.toUpperCase(), x + 16, y + 29, 13, 760, BLACK);
    context.strokeStyle = LINE;
    context.beginPath();
    context.moveTo(x + 16, y + 42);
    context.lineTo(x + width - 16, y + 42);
    context.stroke();
    rows.forEach(function (row, index) {
      var rowY = y + 68 + index * rowHeight;
      drawText(context, row[0], x + 16, rowY, 11, 600, MUTED);
      drawText(context, row[1], x + width - 16, rowY, 12, 700, TEXT, 'right');
    });
    return height;
  }

  function loadImage(url) {
    return new Promise(function (resolve, reject) {
      var image = new Image();
      image.onload = function () { resolve(image); };
      image.onerror = function () { reject(new Error('A report image could not be loaded.')); };
      image.src = url;
    });
  }

  function drawImageContain(context, image, x, y, width, height) {
    var scale = Math.min(width / image.width, height / image.height);
    var drawWidth = image.width * scale;
    var drawHeight = image.height * scale;
    context.drawImage(image, x + (width - drawWidth) / 2, y + (height - drawHeight) / 2, drawWidth, drawHeight);
  }

  function viewSvgStyle() {
    return '.lc-room-border{fill:#fff;stroke:#111;stroke-width:1.4}.lc-room-grid{stroke:#e2e2e2;stroke-width:.7}.lc-room-dim{stroke:#aaa;stroke-width:1;fill:none}.lc-room-dim-text{fill:#111;font:700 10px Arial}.lc-room-offset-text{fill:#555;font:9px Arial}.lc-room-luminaire{fill:#111;stroke:#fff;stroke-width:1.2}.lc-lux-text{fill:#111;font:8.5px Arial;text-anchor:middle;dominant-baseline:middle}.lc-heat-image{image-rendering:auto}';
  }

  async function renderViewImage(snapshot, view) {
    var host = document.createElement('div');
    host.style.cssText = 'position:fixed;left:-10000px;top:0;width:1000px;height:auto;visibility:hidden;pointer-events:none';
    document.body.appendChild(host);
    try {
      window.ArtdonRoomLayout.render(host, snapshot.layout, { view: view, result: snapshot.result, showHeatLabels: false });
      var sourceSvg = host.querySelector('svg.lc-room-svg');
      if (!sourceSvg) throw new Error('The report view could not be rendered.');
      var svg = sourceSvg.cloneNode(true);
      svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
      var style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
      style.textContent = viewSvgStyle();
      svg.insertBefore(style, svg.firstChild);
      var text = new XMLSerializer().serializeToString(svg);
      var blob = new Blob([text], { type: 'image/svg+xml;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      try {
        return await loadImage(url);
      } finally {
        URL.revokeObjectURL(url);
      }
    } finally {
      host.remove();
    }
  }

  async function drawLogo(context, snapshot) {
    if (snapshot.showBranding === false) return;
    try {
      var logo = await loadImage(snapshot.logoUrl);
      drawImageContain(context, logo, 70, 38, 200, 72);
    } catch (error) {
      drawText(context, 'Artdon', 70, 86, 38, 800, BLACK);
      context.fillStyle = RED;
      context.fillRect(70, 96, 36, 3);
    }
  }

  function drawHeaderText(context, pageTitle, snapshot) {
    var unbranded = snapshot.showBranding === false;
    var titleX = unbranded ? 70 : 1170;
    var titleAlign = unbranded ? 'left' : 'right';
    drawText(context, pageTitle, titleX, 65, 20, 760, BLACK, titleAlign);
    drawText(context, 'IES LIGHTING CALCULATION REPORT', titleX, 91, 10, 700, MUTED, titleAlign);
    context.fillStyle = RED;
    context.fillRect(70, 126, 1100, 3);
    drawText(context, 'Generated ' + new Date(snapshot.generatedAt).toLocaleString('en-GB'), 1170, unbranded ? 91 : 114, 9, 500, MUTED, 'right');
  }

  function drawFooter(context, snapshot, pageNumber, pageTotal) {
    context.strokeStyle = LINE;
    context.beginPath();
    context.moveTo(70, 1685);
    context.lineTo(1170, 1685);
    context.stroke();
    if (snapshot.showBranding !== false) drawText(context, 'Artdon Lighting Limited | artdonlighting.com', 70, 1715, 10, 600, MUTED);
    drawText(context, pageTotal ? 'Page ' + pageNumber + ' of ' + pageTotal : 'RESULT SUMMARY', 1170, 1715, 10, 600, MUTED, 'right');
  }

  function drawLegend(context, x, y, height, result) {
    var gradient = context.createLinearGradient(0, y + height, 0, y);
    gradient.addColorStop(0, '#255cb5');
    gradient.addColorStop(.18, '#2097c1');
    gradient.addColorStop(.36, '#57be67');
    gradient.addColorStop(.56, '#d5dc43');
    gradient.addColorStop(.72, '#ffd23c');
    gradient.addColorStop(.88, '#f7892a');
    gradient.addColorStop(1, '#db2726');
    context.fillStyle = gradient;
    context.fillRect(x, y, 18, height);
    drawText(context, 'lx', x + 9, y - 12, 11, 700, BLACK, 'center');
    var scale = window.ArtdonHeatmapRenderer.scaleFor(result);
    (scale.ticks || []).forEach(function (tick, index, ticks) {
      var tickY = y + index / Math.max(1, ticks.length - 1) * height;
      context.fillStyle = '#888';
      context.fillRect(x + 18, tickY, 6, 1);
      drawText(context, fmt(tick, 0), x + 30, tickY + 4, 10, 600, MUTED);
    });
  }

  function nearestLuxPoint(points, targetX, targetY) {
    var nearest = points[0];
    var nearestDistance = Infinity;
    points.forEach(function (point) {
      var distance = Math.pow(Number(point.x) - targetX, 2) + Math.pow(Number(point.y) - targetY, 2);
      if (distance < nearestDistance) {
        nearest = point;
        nearestDistance = distance;
      }
    });
    return nearest;
  }

  function heatmapCanvas(snapshot, maxWidth, maxHeight) {
    var result = snapshot.result;
    var roomRatio = snapshot.room.length / Math.max(0.01, snapshot.room.width);
    var width = Math.min(maxWidth, Math.round(maxHeight * roomRatio));
    var height = Math.min(maxHeight, Math.round(width / roomRatio));
    var canvas = document.createElement('canvas');
    canvas.width = Math.max(320, width);
    canvas.height = Math.max(180, height);
    var context = canvas.getContext('2d');
    var image = context.createImageData(canvas.width, canvas.height);
    var pixels = image.data;
    var xCount = Math.max(2, Number(result.grid.xCount) || 2);
    var yCount = Math.max(2, Number(result.grid.yCount) || 2);
    var scale = window.ArtdonHeatmapRenderer.scaleFor(result);
    for (var py = 0; py < canvas.height; py += 1) {
      var gy = py / Math.max(1, canvas.height - 1) * (yCount - 1);
      for (var px = 0; px < canvas.width; px += 1) {
        var gx = px / Math.max(1, canvas.width - 1) * (xCount - 1);
        var lux = window.ArtdonHeatmapRenderer.bilinear(result.points, xCount, yCount, gx, gy);
        var rgb = window.ArtdonHeatmapRenderer.rgbFor(window.ArtdonHeatmapRenderer.normalize(lux, scale.min, scale.max));
        var offset = (py * canvas.width + px) * 4;
        pixels[offset] = rgb[0];
        pixels[offset + 1] = rgb[1];
        pixels[offset + 2] = rgb[2];
        pixels[offset + 3] = 255;
      }
    }
    context.putImageData(image, 0, 0);

    (snapshot.layout.luminaires || []).forEach(function (luminaire) {
      var x = Number(luminaire.x) / snapshot.room.length * canvas.width;
      var y = Number(luminaire.y) / snapshot.room.width * canvas.height;
      roundedRect(context, x - 5, y - 5, 10, 10, 2, BLACK, '#ffffff');
    });

    [[.22, .28], [.5, .28], [.78, .28], [.22, .72], [.5, .72], [.78, .72]].forEach(function (position) {
      var point = nearestLuxPoint(result.points, snapshot.room.length * position[0], snapshot.room.width * position[1]);
      var label = fmt(point.lux, 0) + ' lx';
      var x = Number(point.x) / snapshot.room.length * canvas.width;
      var y = Number(point.y) / snapshot.room.width * canvas.height;
      setFont(context, 11, 760);
      var labelWidth = context.measureText(label).width + 18;
      roundedRect(context, x - labelWidth / 2, y - 13, labelWidth, 26, 13, 'rgba(255,255,255,.9)', 'rgba(17,17,17,.2)');
      drawText(context, label, x, y + 4, 11, 760, BLACK, 'center');
    });
    return canvas;
  }

  function statusColor(status) {
    if (/ACHIEVED/i.test(status)) return '#18863a';
    if (/NEAR/i.test(status)) return '#8a6700';
    return '#a3152b';
  }

  async function firstPage(snapshot, pageTotal) {
    var page = canvasPage();
    var context = page.context;
    await drawLogo(context, snapshot);
    drawHeaderText(context, 'Calculation Summary', snapshot);

    roundedRect(context, 70, 155, 1100, 92, 8, LIGHT, LINE);
    drawText(context, snapshot.fileName, 90, 190, 20, 760, BLACK);
    drawText(context, snapshot.ies.version + ' | ' + snapshot.ies.manufacturer + ' | Catalog ' + snapshot.ies.catalogNumber, 90, 218, 11, 500, MUTED);
    drawText(context, fmt(snapshot.ies.inputWatts, 1) + ' W  |  ' + fmt(snapshot.ies.totalRatedLumens, 0) + ' lm  |  ' + snapshot.ies.beamAngle + ' beam', 1145, 205, 12, 700, TEXT, 'right');

    var metrics = snapshot.result.metrics || {};
    var cardWidth = 208;
    metricCard(context, 70, 270, cardWidth, 'Average Illuminance', fmt(metrics.eavg, 0) + ' lx');
    metricCard(context, 293, 270, cardWidth, 'Target Average', fmt(snapshot.settings.targetLux, 0) + ' lx');
    metricCard(context, 516, 270, cardWidth, 'Uniformity', fmt(metrics.uniformity, 2));
    metricCard(context, 739, 270, cardWidth, 'Total Luminaires', fmt(metrics.luminaireCount, 0));
    metricCard(context, 962, 270, cardWidth, 'Total Power', fmt(metrics.totalPower, 1) + ' W');

    var assessmentColor = statusColor(snapshot.status);
    roundedRect(context, 70, 390, 1100, 52, 6, '#ffffff', assessmentColor);
    drawText(context, snapshot.status, 90, 423, 14, 800, assessmentColor);
    var comparison = snapshot.assessment ? (snapshot.assessment.difference >= 0 ? '+' : '') + fmt(snapshot.assessment.difference, 0) + ' lx against target' : 'Target assessment unavailable';
    drawText(context, comparison, 1145, 423, 12, 650, TEXT, 'right');

    sectionTitle(context, 'Illuminance Heatmap', 486);
    roundedRect(context, 70, 520, 1100, 590, 10, '#ffffff', LINE);
    var heatmap = heatmapCanvas(snapshot, 950, 535);
    drawImageContain(context, heatmap, 90, 545, 950, 535);
    drawLegend(context, 1060, 615, 385, snapshot.result);

    sectionTitle(context, 'Project Settings', 1160);
    detailCard(context, 70, 1195, 535, 'Room and Mounting', [
      ['Room dimensions', fmt(snapshot.room.length, 2) + ' x ' + fmt(snapshot.room.width, 2) + ' x ' + fmt(snapshot.room.height, 2) + ' m'],
      ['Mounting height', fmt(snapshot.room.mountingHeight, 2) + ' m'],
      ['Work plane height', fmt(snapshot.room.workplaneHeight, 2) + ' m'],
      ['Effective height', fmt(snapshot.room.mountingHeight - snapshot.room.workplaneHeight, 2) + ' m']
    ]);
    detailCard(context, 635, 1195, 535, 'Calculation Basis', [
      ['Grid spacing', fmt(snapshot.settings.gridSpacing, 2) + ' m'],
      ['Maintenance factor', fmt(snapshot.settings.maintenanceFactor, 2)],
      ['Grid points', fmt(snapshot.result.points.length, 0)],
      ['Calculation time', fmt(metrics.calculationTimeMs || 0, 0) + ' ms']
    ]);

    drawText(context, 'Results are a design reference based on uploaded IES photometric data. Room interreflection, obstructions and complex geometry are not included.', 70, 1625, 10, 500, MUTED);
    drawFooter(context, snapshot, 1, pageTotal);
    return page.canvas;
  }

  async function secondPage(snapshot) {
    var page = canvasPage();
    var context = page.context;
    await drawLogo(context, snapshot);
    drawHeaderText(context, 'Technical Details', snapshot);

    sectionTitle(context, 'Luminaire Layout', 175);
    roundedRect(context, 70, 210, 1100, 475, 10, '#ffffff', LINE);
    var layoutImage = await renderViewImage(snapshot, 'layout');
    drawImageContain(context, layoutImage, 88, 228, 1064, 435);

    sectionTitle(context, 'Lux Grid', 735);
    roundedRect(context, 70, 770, 1100, 475, 10, '#ffffff', LINE);
    var luxImage = await renderViewImage(snapshot, 'luxgrid');
    drawImageContain(context, luxImage, 88, 788, 1064, 435);

    var metrics = snapshot.result.metrics || {};
    detailCard(context, 70, 1290, 535, 'Photometric File', [
      ['IES standard', snapshot.ies.version],
      ['Photometric type', snapshot.ies.photometricType],
      ['Peak intensity', snapshot.ies.peakIntensity ? fmt(snapshot.ies.peakIntensity, 1) + ' cd' : 'N/A'],
      ['Test ID', snapshot.ies.testId]
    ]);
    detailCard(context, 635, 1290, 535, 'Calculated Results', [
      ['Minimum / maximum', fmt(metrics.emin, 0) + ' / ' + fmt(metrics.emax, 0) + ' lx'],
      ['Average illuminance', fmt(metrics.eavg, 0) + ' lx'],
      ['Uniformity', fmt(metrics.uniformity, 2)],
      ['Total power', fmt(metrics.totalPower, 1) + ' W']
    ]);

    drawText(context, 'Calculation method: direct illuminance estimate from the uploaded IES candela distribution on the defined work plane.', 70, 1625, 10, 500, MUTED);
    drawFooter(context, snapshot, 2, 2);
    return page.canvas;
  }

  function canvasBlob(canvas, type, quality) {
    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (blob) {
        if (blob) resolve(blob);
        else reject(new Error('The report image could not be encoded.'));
      }, type, quality);
    });
  }

  function triggerDownload(blob, fileName) {
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(function () { URL.revokeObjectURL(url); }, 3000);
  }

  function safeBaseName(fileName) {
    return String(fileName || 'photometric-report').replace(/\.[^.]+$/, '').replace(/[^a-z0-9_-]+/gi, '-').replace(/^-+|-+$/g, '') || 'photometric-report';
  }

  function ascii(value) {
    return new TextEncoder().encode(value);
  }

  function joinBytes(parts) {
    var length = parts.reduce(function (total, part) { return total + part.length; }, 0);
    var output = new Uint8Array(length);
    var offset = 0;
    parts.forEach(function (part) {
      output.set(part, offset);
      offset += part.length;
    });
    return output;
  }

  async function jpegBytes(canvas) {
    var blob = await canvasBlob(canvas, 'image/jpeg', .92);
    return new Uint8Array(await blob.arrayBuffer());
  }

  async function buildPdf(canvases) {
    var images = [];
    for (var i = 0; i < canvases.length; i += 1) images.push(await jpegBytes(canvases[i]));
    var objectCount = 2 + canvases.length * 3;
    var objects = new Array(objectCount + 1);
    objects[1] = ascii('<< /Type /Catalog /Pages 2 0 R >>');
    var kids = canvases.map(function (_, index) { return (3 + index * 3) + ' 0 R'; }).join(' ');
    objects[2] = ascii('<< /Type /Pages /Count ' + canvases.length + ' /Kids [' + kids + '] >>');
    canvases.forEach(function (canvas, index) {
      var pageId = 3 + index * 3;
      var imageId = pageId + 1;
      var contentId = pageId + 2;
      objects[pageId] = ascii('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /XObject << /Im0 ' + imageId + ' 0 R >> >> /Contents ' + contentId + ' 0 R >>');
      objects[imageId] = joinBytes([
        ascii('<< /Type /XObject /Subtype /Image /Width ' + canvas.width + ' /Height ' + canvas.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + images[index].length + ' >>\nstream\n'),
        images[index],
        ascii('\nendstream')
      ]);
      var content = 'q\n595.28 0 0 841.89 0 0 cm\n/Im0 Do\nQ\n';
      objects[contentId] = ascii('<< /Length ' + content.length + ' >>\nstream\n' + content + 'endstream');
    });

    var chunks = [ascii('%PDF-1.4\n%Artdon\n')];
    var offsets = new Array(objectCount + 1).fill(0);
    var currentLength = chunks[0].length;
    for (var id = 1; id <= objectCount; id += 1) {
      offsets[id] = currentLength;
      var objectBytes = joinBytes([ascii(id + ' 0 obj\n'), objects[id], ascii('\nendobj\n')]);
      chunks.push(objectBytes);
      currentLength += objectBytes.length;
    }
    var xrefOffset = currentLength;
    var xref = 'xref\n0 ' + (objectCount + 1) + '\n0000000000 65535 f \n';
    for (var objectId = 1; objectId <= objectCount; objectId += 1) xref += String(offsets[objectId]).padStart(10, '0') + ' 00000 n \n';
    xref += 'trailer\n<< /Size ' + (objectCount + 1) + ' /Root 1 0 R >>\nstartxref\n' + xrefOffset + '\n%%EOF\n';
    chunks.push(ascii(xref));
    return new Blob([joinBytes(chunks)], { type: 'application/pdf' });
  }

  async function downloadPdf(snapshot) {
    var pages = [await firstPage(snapshot, 2), await secondPage(snapshot)];
    var pdf = await buildPdf(pages);
    triggerDownload(pdf, (snapshot.showBranding === false ? 'IES-Report-' : 'Artdon-IES-Report-') + safeBaseName(snapshot.fileName) + '.pdf');
  }

  async function downloadPng(snapshot) {
    var page = await firstPage(snapshot, 0);
    var png = await canvasBlob(page, 'image/png');
    triggerDownload(png, (snapshot.showBranding === false ? 'IES-Summary-' : 'Artdon-IES-Summary-') + safeBaseName(snapshot.fileName) + '.png');
  }

  window.ArtdonReportExporter = {
    downloadPdf: downloadPdf,
    downloadPng: downloadPng,
    buildPdf: buildPdf,
    firstPage: firstPage,
    secondPage: secondPage
  };
})(window, document);
