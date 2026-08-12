(function (window) {
  'use strict';

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
    });
  }

  function n(value, digits) {
    return Number(value || 0).toFixed(digits == null ? 2 : digits);
  }

  function colorFor(value, min, max) {
    if (!Number.isFinite(value)) return '#e8e8e8';
    var t = max > min ? (value - min) / (max - min) : 0.5;
    t = Math.max(0, Math.min(1, t));
    var stops = [
      [0, 47, 111, 187],
      [0.3, 116, 185, 255],
      [0.55, 246, 229, 141],
      [0.78, 240, 147, 43],
      [1, 215, 25, 32]
    ];
    for (var i = 0; i < stops.length - 1; i += 1) {
      if (t >= stops[i][0] && t <= stops[i + 1][0]) {
        var a = stops[i], b = stops[i + 1];
        var k = (t - a[0]) / (b[0] - a[0]);
        return 'rgb(' + Math.round(a[1] + (b[1] - a[1]) * k) + ',' + Math.round(a[2] + (b[2] - a[2]) * k) + ',' + Math.round(a[3] + (b[3] - a[3]) * k) + ')';
      }
    }
    return '#d71920';
  }

  function dimensions(layout) {
    var room = layout.room;
    var pad = 54;
    var width = 760;
    var innerW = width - pad * 2;
    var innerH = Math.max(300, Math.round(innerW * room.width / room.length));
    var height = innerH + pad * 2;
    return { pad: pad, width: width, height: height, innerW: innerW, innerH: innerH, sx: innerW / room.length, sy: innerH / room.width };
  }

  function roomToSvg(d, x, y) {
    return { x: d.pad + x * d.sx, y: d.pad + y * d.sy };
  }

  function svgToRoom(svg, clientX, clientY) {
    var rect = svg.getBoundingClientRect();
    var viewBox = svg.viewBox.baseVal;
    var sx = viewBox.width / rect.width;
    var sy = viewBox.height / rect.height;
    var px = (clientX - rect.left) * sx;
    var py = (clientY - rect.top) * sy;
    var roomLength = Number(svg.dataset.roomLength || 1);
    var roomWidth = Number(svg.dataset.roomWidth || 1);
    var pad = Number(svg.dataset.pad || 0);
    var innerW = Number(svg.dataset.innerW || 1);
    var innerH = Number(svg.dataset.innerH || 1);
    return {
      x: Math.max(0, Math.min(roomLength, (px - pad) / innerW * roomLength)),
      y: Math.max(0, Math.min(roomWidth, (py - pad) / innerH * roomWidth))
    };
  }

  function renderGridLines(d) {
    var html = '';
    for (var i = 1; i < 10; i += 1) {
      var x = d.pad + d.innerW * i / 10;
      var y = d.pad + d.innerH * i / 10;
      html += '<path class="lc-room-grid" d="M' + n(x, 1) + ' ' + d.pad + 'V' + n(d.pad + d.innerH, 1) + '"></path>';
      html += '<path class="lc-room-grid" d="M' + d.pad + ' ' + n(y, 1) + 'H' + n(d.pad + d.innerW, 1) + '"></path>';
    }
    return html;
  }

  function renderDimensions(layout, d) {
    var m = layout.meta || {};
    var topY = d.pad - 25;
    var leftX = d.pad - 25;
    var midX = d.pad + d.innerW / 2;
    var midY = d.pad + d.innerH / 2;
    var html = '';
    html += '<path class="lc-room-dim" d="M' + d.pad + ' ' + topY + 'H' + (d.pad + d.innerW) + '"></path>';
    html += '<text class="lc-room-dim-text" x="' + midX + '" y="' + (topY - 7) + '" text-anchor="middle">' + n(layout.room.length, 1) + ' m length</text>';
    html += '<path class="lc-room-dim" d="M' + leftX + ' ' + d.pad + 'V' + (d.pad + d.innerH) + '"></path>';
    html += '<text class="lc-room-dim-text" x="' + (leftX - 8) + '" y="' + midY + '" text-anchor="middle" transform="rotate(-90 ' + (leftX - 8) + ' ' + midY + ')">' + n(layout.room.width, 1) + ' m width</text>';
    if (m.actualXSpacing) html += '<text class="lc-room-dim-text" x="' + midX + '" y="' + (d.pad + d.innerH + 32) + '" text-anchor="middle">X spacing ' + n(m.actualXSpacing, 2) + ' m</text>';
    if (m.actualYSpacing) html += '<text class="lc-room-dim-text" x="' + (d.pad + d.innerW + 34) + '" y="' + midY + '" text-anchor="middle" transform="rotate(90 ' + (d.pad + d.innerW + 34) + ' ' + midY + ')">Y spacing ' + n(m.actualYSpacing, 2) + ' m</text>';
    html += '<text class="lc-room-offset-text" x="' + (d.pad + 8) + '" y="' + (d.pad + 14) + '">L ' + n(m.leftOffset || 0, 1) + ' / F ' + n(m.frontOffset || 0, 1) + '</text>';
    html += '<text class="lc-room-offset-text" x="' + (d.pad + d.innerW - 8) + '" y="' + (d.pad + d.innerH - 8) + '" text-anchor="end">R ' + n(m.rightOffset || 0, 1) + ' / B ' + n(m.backOffset || 0, 1) + '</text>';
    return html;
  }

  function renderLuminaires(layout, d, selectedId) {
    return (layout.luminaires || []).map(function (lum) {
      var p = roomToSvg(d, lum.x, lum.y);
      var cls = 'lc-room-luminaire' + (String(lum.id) === String(selectedId) ? ' is-selected' : '');
      return '<rect class="' + cls + '" data-lum-id="' + esc(lum.id) + '" x="' + n(p.x - 4, 1) + '" y="' + n(p.y - 4, 1) + '" width="8" height="8" rx="1"></rect>';
    }).join('');
  }

  function renderPlacementGuide(layout, d, point) {
    if (!point) return '';
    var p = roomToSvg(d, point.x, point.y);
    var right = Math.max(0, layout.room.length - point.x);
    var back = Math.max(0, layout.room.width - point.y);
    var labelY = p.y > d.pad + 42 ? p.y - 12 : p.y + 24;
    var html = '';
    html += '<path class="lc-place-line" d="M' + d.pad + ' ' + n(p.y, 1) + 'H' + n(p.x, 1) + '"></path>';
    html += '<path class="lc-place-line" d="M' + n(p.x, 1) + ' ' + d.pad + 'V' + n(p.y, 1) + '"></path>';
    html += '<circle class="lc-place-preview" cx="' + n(p.x, 1) + '" cy="' + n(p.y, 1) + '" r="5"></circle>';
    html += '<text class="lc-place-text" x="' + n(p.x + 10, 1) + '" y="' + n(labelY, 1) + '">X ' + n(point.x, 2) + ' m / Y ' + n(point.y, 2) + ' m</text>';
    html += '<text class="lc-place-text lc-place-sub" x="' + n(p.x + 10, 1) + '" y="' + n(labelY + 14, 1) + '">L ' + n(point.x, 2) + ' R ' + n(right, 2) + ' F ' + n(point.y, 2) + ' B ' + n(back, 2) + '</text>';
    return html;
  }

  function renderHeat(result, d) {
    if (!result || !result.points || !result.points.length) return '';
    if (window.ArtdonHeatmapRenderer && typeof window.ArtdonHeatmapRenderer.render === 'function') {
      var smoothHeatmap = window.ArtdonHeatmapRenderer.render(result, d);
      if (smoothHeatmap) return smoothHeatmap;
    }
    var min = result.metrics.emin;
    var max = result.metrics.emax;
    var cellW = d.innerW / Math.max(1, result.grid.xCount - 1);
    var cellH = d.innerH / Math.max(1, result.grid.yCount - 1);
    return result.points.map(function (point) {
      var p = roomToSvg(d, point.x, point.y);
      return '<rect class="lc-heat-cell" data-lux="' + n(point.lux, 1) + '" x="' + n(p.x - cellW / 2, 1) + '" y="' + n(p.y - cellH / 2, 1) + '" width="' + n(cellW, 1) + '" height="' + n(cellH, 1) + '" fill="' + colorFor(point.lux, min, max) + '"></rect>';
    }).join('');
  }

  function renderLuxGrid(result, d) {
    if (!result || !result.points || !result.points.length) return '';
    var step = Math.max(1, Math.ceil(result.points.length / 520));
    var html = '';
    for (var i = 0; i < result.points.length; i += step) {
      var point = result.points[i];
      var p = roomToSvg(d, point.x, point.y);
      html += '<text class="lc-lux-text" data-lux="' + n(point.lux, 1) + '" x="' + n(p.x, 1) + '" y="' + n(p.y, 1) + '">' + Math.round(point.lux) + '</text>';
    }
    return html;
  }

  function render(container, layout, options) {
    options = options || {};
    if (!container) return;
    if (!layout || !layout.room) {
      container.innerHTML = '<span>Set room dimensions and upload an IES file to calculate illuminance.</span>';
      return;
    }
    var d = dimensions(layout);
    var view = options.view || 'layout';
    var result = options.result || null;
    var svgClass = 'lc-room-svg' + (view === 'heatmap' ? ' is-heatmap' : '');
    var html = '<svg class="' + svgClass + '" viewBox="0 0 ' + d.width + ' ' + d.height + '" data-pad="' + d.pad + '" data-inner-w="' + d.innerW + '" data-inner-h="' + d.innerH + '" data-room-length="' + layout.room.length + '" data-room-width="' + layout.room.width + '" role="img" aria-label="Room layout">';
    html += renderGridLines(d);
    if (view === 'heatmap' && result) html += renderHeat(result, d);
    html += '<rect class="lc-room-border" x="' + d.pad + '" y="' + d.pad + '" width="' + d.innerW + '" height="' + d.innerH + '"></rect>';
    html += renderDimensions(layout, d);
    if (view === 'luxgrid' && result) html += renderLuxGrid(result, d);
    html += renderLuminaires(layout, d, options.selectedId);
    html += renderPlacementGuide(layout, d, options.placementPoint || null);
    html += '</svg>';
    if (view === 'heatmap' && result) {
      html += '<div class="lc-legend"><span>' + Math.round(result.metrics.emin) + ' lx</span><i class="lc-legend-bar"></i><span>' + Math.round(result.metrics.emax) + ' lx</span></div>';
    }
    container.innerHTML = html;
  }

  window.ArtdonRoomLayout = {
    render: render,
    svgToRoom: svgToRoom,
    colorFor: colorFor
  };
})(window);
