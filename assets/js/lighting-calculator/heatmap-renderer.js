(function (window) {
  'use strict';

  var STOPS = [
    [0.00, 23, 58, 143],
    [0.18, 31, 126, 208],
    [0.38, 53, 197, 193],
    [0.58, 121, 211, 91],
    [0.76, 243, 223, 67],
    [0.90, 243, 154, 50],
    [1.00, 215, 25, 32]
  ];

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function normalize(value, min, max) {
    if (!Number.isFinite(value)) return 0;
    if (!(max > min)) return 0.5;
    var linear = clamp((value - min) / (max - min), 0, 1);
    // Keep mid-level illuminance visible without changing or clipping lux data.
    return Math.pow(linear, 0.45);
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
    var min = Number(result.metrics && result.metrics.emin);
    var max = Number(result.metrics && result.metrics.emax);
    if (!Number.isFinite(min)) min = 0;
    if (!Number.isFinite(max)) max = min;

    for (var py = 0; py < rasterHeight; py += 1) {
      var gy = rasterHeight > 1 ? py / (rasterHeight - 1) * (yCount - 1) : 0;
      for (var px = 0; px < rasterWidth; px += 1) {
        var gx = rasterWidth > 1 ? px / (rasterWidth - 1) * (xCount - 1) : 0;
        var lux = bilinear(result.points, xCount, yCount, gx, gy);
        var rgb = rgbFor(normalize(lux, min, max));
        var offset = (py * rasterWidth + px) * 4;
        pixels[offset] = rgb[0];
        pixels[offset + 1] = rgb[1];
        pixels[offset + 2] = rgb[2];
        pixels[offset + 3] = 255;
      }
    }
    context.putImageData(image, 0, 0);
    var url = canvas.toDataURL('image/png');
    return '<image class="lc-heat-image" x="' + dimensions.pad + '" y="' + dimensions.pad + '" width="' + dimensions.innerW + '" height="' + dimensions.innerH + '" preserveAspectRatio="none" href="' + url + '"></image>';
  }

  window.ArtdonHeatmapRenderer = {
    render: render,
    normalize: normalize,
    rgbFor: rgbFor,
    bilinear: bilinear
  };
})(window);
