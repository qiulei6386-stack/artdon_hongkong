(function (window) {
  'use strict';

  var STOPS = [
    [0.00, 37, 92, 181],
    [0.18, 32, 151, 193],
    [0.36, 87, 190, 103],
    [0.56, 213, 220, 67],
    [0.72, 255, 210, 60],
    [0.88, 247, 137, 42],
    [1.00, 219, 39, 38]
  ];

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function normalize(value, min, max) {
    if (!Number.isFinite(value)) return 0;
    if (!(max > min)) return 0.5;
    return clamp((value - min) / (max - min), 0, 1);
  }

  function niceStep(value) {
    if (!(value > 0)) return 1;
    var magnitude = Math.pow(10, Math.floor(Math.log(value) / Math.LN10));
    var fraction = value / magnitude;
    var niceFraction = fraction <= 1 ? 1 : (fraction <= 2 ? 2 : (fraction <= 2.5 ? 2.5 : (fraction <= 5 ? 5 : 10)));
    return niceFraction * magnitude;
  }

  function scaleFor(result) {
    var measuredMin = Number(result && result.metrics && result.metrics.emin);
    var measuredMax = Number(result && result.metrics && result.metrics.emax);
    if (!Number.isFinite(measuredMin)) measuredMin = 0;
    if (!Number.isFinite(measuredMax)) measuredMax = measuredMin;
    if (measuredMax < measuredMin) {
      var swap = measuredMax;
      measuredMax = measuredMin;
      measuredMin = swap;
    }
    var step = niceStep(Math.max(1, measuredMax - measuredMin) / 5);
    var min = Math.max(0, Math.floor(measuredMin / step) * step);
    var max = Math.ceil(measuredMax / step) * step;
    if (!(max > min)) max = min + step * 5;
    var ticks = [];
    for (var tick = max; tick >= min - step * 0.001; tick -= step) ticks.push(Math.max(0, Math.round(tick * 100) / 100));
    return { min: min, max: max, step: step, ticks: ticks, measuredMin: measuredMin, measuredMax: measuredMax };
  }

  function rgbFor(t) {
    t = clamp(Number(t) || 0, 0, 1);
    for (var i = 0; i < STOPS.length - 1; i += 1) {
      var a = STOPS[i];
      var b = STOPS[i + 1];
      if (t < a[0] || t > b[0]) continue;
      var ratio = (t - a[0]) / Math.max(0.000001, b[0] - a[0]);
      return [
        Math.round(a[1] + (b[1] - a[1]) * ratio),
        Math.round(a[2] + (b[2] - a[2]) * ratio),
        Math.round(a[3] + (b[3] - a[3]) * ratio)
      ];
    }
    return [215, 25, 32];
  }

  function pointLux(points, xCount, x, y) {
    var point = points[y * xCount + x];
    var value = point ? Number(point.lux) : 0;
    return Number.isFinite(value) ? value : 0;
  }

  function bilinear(points, xCount, yCount, gx, gy) {
    var x0 = clamp(Math.floor(gx), 0, xCount - 1);
    var y0 = clamp(Math.floor(gy), 0, yCount - 1);
    var x1 = Math.min(xCount - 1, x0 + 1);
    var y1 = Math.min(yCount - 1, y0 + 1);
    var tx = clamp(gx - x0, 0, 1);
    var ty = clamp(gy - y0, 0, 1);
    var top = pointLux(points, xCount, x0, y0) * (1 - tx) + pointLux(points, xCount, x1, y0) * tx;
    var bottom = pointLux(points, xCount, x0, y1) * (1 - tx) + pointLux(points, xCount, x1, y1) * tx;
    return top * (1 - ty) + bottom * ty;
  }

  function render(result, dimensions) {
    if (!result || !result.points || !result.points.length || !result.grid || !dimensions || !window.document) return '';
    var xCount = Math.max(2, Number(result.grid.xCount) || 2);
    var yCount = Math.max(2, Number(result.grid.yCount) || 2);
    if (result.points.length < xCount * yCount) return '';

    var rasterWidth = Math.max(320, Math.min(720, Math.round(dimensions.innerW)));
    var rasterHeight = Math.max(180, Math.round(rasterWidth * dimensions.innerH / Math.max(1, dimensions.innerW)));
    var canvas = window.document.createElement('canvas');
    canvas.width = rasterWidth;
    canvas.height = rasterHeight;
    var context = canvas.getContext && canvas.getContext('2d');
    if (!context || !context.createImageData) return '';

    var image = context.createImageData(rasterWidth, rasterHeight);
    var pixels = image.data;
    var scale = scaleFor(result);

    for (var py = 0; py < rasterHeight; py += 1) {
      var gy = rasterHeight > 1 ? py / (rasterHeight - 1) * (yCount - 1) : 0;
      for (var px = 0; px < rasterWidth; px += 1) {
        var gx = rasterWidth > 1 ? px / (rasterWidth - 1) * (xCount - 1) : 0;
        var lux = bilinear(result.points, xCount, yCount, gx, gy);
        var rgb = rgbFor(normalize(lux, scale.min, scale.max));
        var offset = (py * rasterWidth + px) * 4;
        pixels[offset] = rgb[0];
        pixels[offset + 1] = rgb[1];
        pixels[offset + 2] = rgb[2];
        pixels[offset + 3] = 255;
      }
    }
    context.putImageData(image, 0, 0);
    var url = canvas.toDataURL('image/png');
    var radius = 11;
    return '<defs><clipPath id="lcHeatmapClip"><rect x="' + dimensions.pad + '" y="' + dimensions.pad + '" width="' + dimensions.innerW + '" height="' + dimensions.innerH + '" rx="' + radius + '"></rect></clipPath></defs>' +
      '<image class="lc-heat-image" x="' + dimensions.pad + '" y="' + dimensions.pad + '" width="' + dimensions.innerW + '" height="' + dimensions.innerH + '" preserveAspectRatio="none" clip-path="url(#lcHeatmapClip)" href="' + url + '"></image>';
  }

  window.ArtdonHeatmapRenderer = {
    render: render,
    normalize: normalize,
    rgbFor: rgbFor,
    bilinear: bilinear,
    scaleFor: scaleFor
  };
})(window);
