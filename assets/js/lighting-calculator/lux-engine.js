(function (root) {
  'use strict';

  var MAX_GRID_POINTS = 4000;
  var MAX_LUMINAIRES = 400;
  var DEG = Math.PI / 180;

  function validateNumber(value, label, min, max) {
    var n = Number(value);
    if (!Number.isFinite(n) || n < min || n > max) throw new Error(label + ' is outside the supported range.');
    return n;
  }

  function buildLuminaireArray(room, layout) {
    if (Array.isArray(layout.luminaires)) {
      if (layout.luminaires.length < 1) throw new Error('At least one luminaire is required.');
      if (layout.luminaires.length > MAX_LUMINAIRES) throw new Error('Too many luminaires. Reduce the luminaire count.');
      return layout.luminaires.map(function (item, index) {
        var x = validateNumber(item.x, 'Luminaire X position', 0, room.length);
        var y = validateNumber(item.y, 'Luminaire Y position', 0, room.width);
        var rotation = validateNumber(item.rotation || 0, 'Luminaire rotation', -360, 360);
        return {
          id: item.id || ('L' + (index + 1)),
          x: x,
          y: y,
          z: room.mountingHeight,
          rotation: rotation
        };
      });
    }
    var rows = Math.round(validateNumber(layout.rows, 'Luminaire rows', 1, 20));
    var cols = Math.round(validateNumber(layout.cols, 'Luminaire columns', 1, 20));
    var count = rows * cols;
    if (count > MAX_LUMINAIRES) throw new Error('Too many luminaires. Reduce rows or columns.');
    var rotation = validateNumber(layout.rotation || 0, 'Luminaire rotation', -360, 360);
    var marginX = room.length / (cols + 1);
    var marginY = room.width / (rows + 1);
    var luminaires = [];
    for (var r = 0; r < rows; r += 1) {
      for (var c = 0; c < cols; c += 1) {
        luminaires.push({
          x: marginX * (c + 1),
          y: marginY * (r + 1),
          z: room.mountingHeight,
          rotation: rotation
        });
      }
    }
    return luminaires;
  }

  function buildGrid(room, spacing) {
    spacing = validateNumber(spacing, 'Grid spacing', 0.25, 5);
    var xCount = Math.max(2, Math.floor(room.length / spacing) + 1);
    var yCount = Math.max(2, Math.floor(room.width / spacing) + 1);
    var points = [];
    for (var yi = 0; yi < yCount; yi += 1) {
      var y = yCount === 1 ? room.width / 2 : room.width * yi / (yCount - 1);
      for (var xi = 0; xi < xCount; xi += 1) {
        var x = xCount === 1 ? room.length / 2 : room.length * xi / (xCount - 1);
        points.push({ x: x, y: y, z: room.workplaneHeight });
      }
    }
    if (points.length > MAX_GRID_POINTS) throw new Error('Too many grid points. Increase grid spacing or reduce room size.');
    return { points: points, xCount: xCount, yCount: yCount, spacing: spacing };
  }

  function buildRoom(input) {
    var room = {
      length: validateNumber(input.length, 'Room length', 1, 100),
      width: validateNumber(input.width, 'Room width', 1, 100),
      mountingHeight: validateNumber(input.mountingHeight, 'Mounting height', 0.5, 30),
      workplaneHeight: validateNumber(input.workplaneHeight, 'Workplane height', 0, 10)
    };
    room.effectiveHeight = room.mountingHeight - room.workplaneHeight;
    if (room.effectiveHeight <= 0) throw new Error('Workplane height must be lower than mounting height.');
    return room;
  }

  function localAngles(luminaire, point) {
    var dx = point.x - luminaire.x;
    var dy = point.y - luminaire.y;
    var dz = luminaire.z - point.z;
    var horizontal = Math.sqrt(dx * dx + dy * dy);
    var distance = Math.sqrt(horizontal * horizontal + dz * dz);
    if (distance <= 0 || dz <= 0) return null;
    var verticalAngle = Math.atan2(horizontal, dz) / DEG;
    var globalHorizontal = Math.atan2(dy, dx) / DEG;
    var horizontalAngle = globalHorizontal - (luminaire.rotation || 0);
    return {
      distance: distance,
      cosIncidence: dz / distance,
      horizontalAngle: horizontalAngle,
      verticalAngle: verticalAngle
    };
  }

  function calculatePoint(ies, luminaires, point) {
    var lux = 0;
    for (var i = 0; i < luminaires.length; i += 1) {
      var angles = localAngles(luminaires[i], point);
      if (!angles || angles.cosIncidence <= 0) continue;
      var candela = root.ArtdonIesParser.interpolate(ies, angles.horizontalAngle, angles.verticalAngle);
      lux += candela * angles.cosIncidence / (angles.distance * angles.distance);
    }
    return lux;
  }

  function calculate(ies, input, progress) {
    if (!ies) throw new Error('Upload a valid IES file before calculating.');
    var room = buildRoom(input.room || {});
    var luminaires = buildLuminaireArray(room, input.layout || {});
    var grid = buildGrid(room, input.gridSpacing || 0.5);
    var maintenanceFactor = validateNumber(input.maintenanceFactor == null ? 1 : input.maintenanceFactor, 'Maintenance factor', 0.1, 1);
    var points = [];
    var sum = 0;
    var min = Infinity;
    var max = -Infinity;
    var minPoint = null;
    var maxPoint = null;
    var startedAt = Date.now();
    var timeoutMs = Number(input.timeoutMs || 8000);

    for (var i = 0; i < grid.points.length; i += 1) {
      if (Date.now() - startedAt > timeoutMs) throw new Error('Calculation timed out. Reduce the grid density or luminaire count.');
      var p = grid.points[i];
      var lux = calculatePoint(ies, luminaires, p) * maintenanceFactor;
      var out = { x: p.x, y: p.y, z: p.z, lux: lux };
      points.push(out);
      sum += lux;
      if (lux < min) { min = lux; minPoint = out; }
      if (lux > max) { max = lux; maxPoint = out; }
      if (progress && (i % 150 === 0 || i === grid.points.length - 1)) progress(i + 1, grid.points.length);
    }

    var avg = points.length ? sum / points.length : 0;
    return {
      points: points,
      grid: { xCount: grid.xCount, yCount: grid.yCount, spacing: grid.spacing },
      room: room,
      luminaires: luminaires,
      metrics: {
        eavg: avg,
        emin: Number.isFinite(min) ? min : 0,
        emax: Number.isFinite(max) ? max : 0,
        uniformity: avg > 0 && Number.isFinite(min) ? min / avg : 0,
        luminaireCount: luminaires.length,
        totalPower: ies.inputWatts ? ies.inputWatts * luminaires.length : null,
        minPoint: minPoint,
        maxPoint: maxPoint
      }
    };
  }

  root.ArtdonLuxEngine = {
    calculate: calculate,
    calculatePoint: calculatePoint,
    buildRoom: buildRoom,
    buildGrid: buildGrid,
    buildLuminaireArray: buildLuminaireArray,
    localAngles: localAngles,
    limits: { maxGridPoints: MAX_GRID_POINTS, maxLuminaires: MAX_LUMINAIRES }
  };
})(typeof self !== 'undefined' ? self : window);
