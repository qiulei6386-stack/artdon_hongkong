(function (root) {
  'use strict';

  var EPSILON = 1e-9;

  function fail(message) {
    throw new Error(message);
  }

  function toLines(text) {
    return String(text || '').replace(/\r\n?/g, '\n').split('\n').map(function (line) {
      return line.trim();
    });
  }

  function parseNumbers(lines) {
    var joined = lines.join(' ');
    var matches = joined.match(/[+-]?(?:\d+\.?\d*|\.\d+)(?:[Ee][+-]?\d+)?/g);
    return matches ? matches.map(Number) : [];
  }

  function fileExtensionName(name) {
    return String(name || '').split(/[\\/]/).pop();
  }

  function parse(text, options) {
    options = options || {};
    var sourceName = fileExtensionName(options.fileName || '');
    var lines = toLines(text);
    if (!lines.length || !String(text || '').trim()) fail('The IES file is empty.');

    var version = '';
    var first = lines.find(function (line) { return line !== ''; }) || '';
    if (/^IESNA:LM-63/i.test(first)) version = first;
    else if (/^IESNA/i.test(first)) version = first;
    else fail('The file format is not recognized as LM-63 IES.');

    var tiltIndex = lines.findIndex(function (line) { return /^TILT\s*=/i.test(line); });
    if (tiltIndex < 0) fail('The IES file is incomplete: missing TILT declaration.');
    var tilt = lines[tiltIndex].replace(/\s+/g, '').toUpperCase();
    if (tilt !== 'TILT=NONE') {
      if (tilt !== 'TILT=INCLUDE') fail('External TILT files are not supported in this version.');
      fail('Embedded TILT data is not supported in this version.');
    }

    var metadata = {};
    for (var i = 0; i < tiltIndex; i += 1) {
      var m = lines[i].match(/^\[([^\]]+)\]\s*(.*)$/);
      if (m) metadata[m[1].trim().toUpperCase()] = m[2].trim();
    }

    var nums = parseNumbers(lines.slice(tiltIndex + 1));
    if (nums.length < 13) fail('The IES file is incomplete: missing photometric header values.');
    var at = 0;
    var lampCount = nums[at++];
    var lumensPerLamp = nums[at++];
    var candelaMultiplier = nums[at++];
    var verticalCount = nums[at++];
    var horizontalCount = nums[at++];
    var photometricType = nums[at++];
    var unitsType = nums[at++];
    var width = nums[at++];
    var length = nums[at++];
    var height = nums[at++];
    var ballastFactor = nums[at++];
    var futureUse = nums[at++];
    var inputWatts = nums[at++];

    verticalCount = Math.round(verticalCount);
    horizontalCount = Math.round(horizontalCount);
    photometricType = Math.round(photometricType);
    unitsType = Math.round(unitsType);
    if (photometricType !== 1) fail('Only Type C photometry is supported in this version.');
    if (verticalCount <= 0 || horizontalCount <= 0) fail('The IES file has invalid angle counts.');
    if (at + verticalCount + horizontalCount > nums.length) fail('The IES file is incomplete: missing angle arrays.');

    var verticalAngles = nums.slice(at, at + verticalCount); at += verticalCount;
    var horizontalAngles = nums.slice(at, at + horizontalCount); at += horizontalCount;
    var expectedCandela = verticalCount * horizontalCount;
    if (at + expectedCandela !== nums.length) {
      if (at + expectedCandela > nums.length) fail('The IES file is incomplete: candela data is shorter than the angle arrays require.');
      fail('The IES file has extra numeric data after the candela matrix.');
    }

    var candela = [];
    for (var h = 0; h < horizontalCount; h += 1) {
      candela[h] = nums.slice(at, at + verticalCount).map(function (value) {
        return value * candelaMultiplier;
      });
      at += verticalCount;
    }

    if (!isStrictAscending(verticalAngles) || !isStrictAscending(horizontalAngles)) {
      fail('The IES angle arrays must be strictly increasing.');
    }

    return {
      version: version,
      sourceName: sourceName,
      metadata: metadata,
      lampCount: lampCount,
      lumensPerLamp: lumensPerLamp,
      candelaMultiplier: candelaMultiplier,
      photometricType: photometricType,
      unitsType: unitsType,
      dimensions: { width: width, length: length, height: height },
      ballastFactor: ballastFactor,
      futureUse: futureUse,
      inputWatts: inputWatts > 0 ? inputWatts : null,
      verticalAngles: verticalAngles,
      horizontalAngles: horizontalAngles,
      candela: candela,
      horizontalSymmetry: describeHorizontalSymmetry(horizontalAngles)
    };
  }

  function isStrictAscending(values) {
    for (var i = 1; i < values.length; i += 1) {
      if (!(values[i] > values[i - 1])) return false;
    }
    return true;
  }

  function normalizeDegrees(angle) {
    var value = Number(angle) % 360;
    return value < 0 ? value + 360 : value;
  }

  function describeHorizontalSymmetry(angles) {
    if (angles.length <= 1) return 'axial';
    var min = angles[0];
    var max = angles[angles.length - 1];
    if (Math.abs(min) <= EPSILON && max >= 359 - EPSILON) return 'full-360';
    if (Math.abs(min) <= EPSILON && Math.abs(max - 180) <= EPSILON) return '0-180 mirrored';
    if (Math.abs(min) <= EPSILON && Math.abs(max - 90) <= EPSILON) return '0-90 quadrant mirrored';
    return min + '-' + max;
  }

  function mapHorizontalAngle(angle, angles) {
    if (angles.length <= 1) return angles[0] || 0;
    var min = angles[0];
    var max = angles[angles.length - 1];
    var gamma = normalizeDegrees(angle);
    if (Math.abs(min) <= EPSILON && max >= 359 - EPSILON) return gamma;
    if (Math.abs(min) <= EPSILON && Math.abs(max - 180) <= EPSILON) {
      return gamma > 180 ? 360 - gamma : gamma;
    }
    if (Math.abs(min) <= EPSILON && Math.abs(max - 90) <= EPSILON) {
      gamma %= 180;
      return gamma > 90 ? 180 - gamma : gamma;
    }
    while (gamma < min) gamma += 360;
    while (gamma > max && gamma - 360 >= min) gamma -= 360;
    return Math.max(min, Math.min(max, gamma));
  }

  function lowerIndex(values, value) {
    if (value <= values[0]) return 0;
    for (var i = 0; i < values.length - 1; i += 1) {
      if (value >= values[i] && value <= values[i + 1]) return i;
    }
    return values.length - 2;
  }

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function interpolate(ies, horizontalAngle, verticalAngle) {
    if (!ies || !ies.horizontalAngles || !ies.verticalAngles || !ies.candela) fail('IES data is not available.');
    var hAngles = ies.horizontalAngles;
    var vAngles = ies.verticalAngles;
    var gamma = mapHorizontalAngle(horizontalAngle, hAngles);
    var theta = Math.max(vAngles[0], Math.min(vAngles[vAngles.length - 1], Number(verticalAngle)));
    if (hAngles.length === 1 && vAngles.length === 1) return ies.candela[0][0];

    var hi = hAngles.length === 1 ? 0 : lowerIndex(hAngles, gamma);
    var vi = vAngles.length === 1 ? 0 : lowerIndex(vAngles, theta);
    var h0 = hAngles[hi], h1 = hAngles[Math.min(hi + 1, hAngles.length - 1)];
    var v0 = vAngles[vi], v1 = vAngles[Math.min(vi + 1, vAngles.length - 1)];
    var ht = Math.abs(h1 - h0) < EPSILON ? 0 : (gamma - h0) / (h1 - h0);
    var vt = Math.abs(v1 - v0) < EPSILON ? 0 : (theta - v0) / (v1 - v0);
    var c00 = ies.candela[hi][vi];
    var c10 = ies.candela[Math.min(hi + 1, hAngles.length - 1)][vi];
    var c01 = ies.candela[hi][Math.min(vi + 1, vAngles.length - 1)];
    var c11 = ies.candela[Math.min(hi + 1, hAngles.length - 1)][Math.min(vi + 1, vAngles.length - 1)];
    return lerp(lerp(c00, c10, ht), lerp(c01, c11, ht), vt);
  }

  root.ArtdonIesParser = {
    parse: parse,
    interpolate: interpolate,
    mapHorizontalAngle: mapHorizontalAngle,
    describeHorizontalSymmetry: describeHorizontalSymmetry
  };
})(typeof self !== 'undefined' ? self : window);
