'use strict';

const { performance } = require('node:perf_hooks');

global.self = {};
require('../assets/js/lighting-calculator/ies-parser.js');
require('../assets/js/lighting-calculator/lux-engine.js');

const parser = global.self.ArtdonIesParser;
const engine = global.self.ArtdonLuxEngine;

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function expectThrow(fn, pattern, label) {
  let error = null;
  try { fn(); } catch (caught) { error = caught; }
  assert(error, label + ' should throw.');
  assert(pattern.test(String(error.message || error)), label + ' returned an unexpected error: ' + error.message);
}

function buildIes(verticalAngles, horizontalAngles, rows, options) {
  options = options || {};
  const header = [
    1,
    options.lumens || 1000,
    1,
    verticalAngles.length,
    horizontalAngles.length,
    1,
    2,
    0, 0, 0,
    1, 1,
    options.watts || 20
  ].join(' ');
  return [
    'IESNA:LM-63-1995',
    '[TEST] Artdon calculator benchmark',
    'TILT=NONE',
    header,
    verticalAngles.join(' '),
    horizontalAngles.join(' ')
  ].concat(rows.map((row) => row.join(' '))).join('\n');
}

function makeHighResolutionIes() {
  const verticalAngles = Array.from({ length: 91 }, (_, index) => index);
  const horizontalAngles = Array.from({ length: 37 }, (_, index) => index * 5);
  const rows = horizontalAngles.map((_, horizontalIndex) => verticalAngles.map((angle) => {
    const base = 1200 * Math.pow(Math.max(0, Math.cos(angle * Math.PI / 180)), 4);
    return base * (1 - horizontalIndex * 0.002);
  }));
  return parser.parse(buildIes(verticalAngles, horizontalAngles, rows, { lumens: 4000, watts: 36 }));
}

function makeLuminaires(count, roomLength, roomWidth) {
  const columns = 20;
  return Array.from({ length: count }, (_, index) => ({
    id: 'L' + (index + 1),
    x: Math.min(roomLength, (index % columns) + 0.5),
    y: Math.min(roomWidth, Math.floor(index / columns) + 0.5),
    rotation: 0
  }));
}

function run() {
  const simpleIes = parser.parse(buildIes(
    [0, 30, 60, 90],
    [0],
    [[1000, 500, 100, 0]],
    { lumens: 1000, watts: 10 }
  ));
  const room = engine.buildRoom({ length: 4, width: 4, mountingHeight: 2.8, workplaneHeight: 0.8 });
  const luminaire = engine.buildLuminaireArray(room, { luminaires: [{ id: 'L1', x: 2, y: 2, rotation: 0 }] });
  const centreLux = engine.calculatePoint(simpleIes, luminaire, { x: 2, y: 2, z: 0.8 });
  assert(Math.abs(centreLux - 250) < 1e-9, 'Inverse-square baseline failed: expected 250 lx, received ' + centreLux + ' lx.');

  expectThrow(() => engine.estimate({
    room: { length: 100, width: 100, mountingHeight: 3, workplaneHeight: 0.8 },
    layout: { luminaires: [{ x: 1, y: 1 }] },
    gridSpacing: 0.25,
    maintenanceFactor: 0.8
  }), /grid contains .* supported maximum/i, 'Grid-point guard');

  expectThrow(() => engine.estimate({
    room: { length: 30, width: 30, mountingHeight: 3, workplaneHeight: 0.8 },
    layout: { luminaires: makeLuminaires(401, 30, 30) },
    gridSpacing: 1,
    maintenanceFactor: 0.8
  }), /401 luminaires.*maximum is 400/i, 'Luminaire-count guard');

  expectThrow(() => engine.estimate({
    room: { length: 49.5, width: 19.5, mountingHeight: 3, workplaneHeight: 0.8 },
    layout: { luminaires: makeLuminaires(400, 49.5, 19.5) },
    gridSpacing: 0.5,
    maintenanceFactor: 0.8
  }), /1,600,000 intensity evaluations.*maximum is 1,000,000/i, 'Combined-workload guard');

  const ies = makeHighResolutionIes();
  const input = {
    room: { length: 49.5, width: 19.5, mountingHeight: 3, workplaneHeight: 0.8 },
    layout: { luminaires: makeLuminaires(200, 49.5, 19.5) },
    gridSpacing: 0.5,
    maintenanceFactor: 0.8,
    timeoutMs: 8000
  };
  const estimate = engine.estimate(input);
  assert(estimate.gridPointCount === 4000, 'Benchmark grid should contain 4,000 points.');
  assert(estimate.workItems === 800000, 'Benchmark should contain 800,000 evaluations.');

  engine.calculate(ies, Object.assign({}, input, {
    layout: { luminaires: makeLuminaires(5, 49.5, 19.5) }
  }));
  const startedAt = performance.now();
  const result = engine.calculate(ies, input);
  const elapsedMs = performance.now() - startedAt;
  assert(result.points.length === 4000, 'Benchmark result should contain 4,000 grid points.');
  assert(result.metrics.calculationWorkItems === 800000, 'Result workload metric is incorrect.');
  assert(elapsedMs < 2500, 'Benchmark exceeded 2,500 ms: ' + elapsedMs.toFixed(1) + ' ms.');

  console.log(JSON.stringify({
    status: 'PASS',
    inverseSquareBaselineLux: centreLux,
    benchmark: {
      verticalAngles: ies.verticalAngles.length,
      horizontalPlanes: ies.horizontalAngles.length,
      luminaires: estimate.luminaireCount,
      gridPoints: estimate.gridPointCount,
      evaluations: estimate.workItems,
      elapsedMs: Number(elapsedMs.toFixed(1)),
      thresholdMs: 2500
    },
    limits: engine.limits
  }, null, 2));
}

run();
