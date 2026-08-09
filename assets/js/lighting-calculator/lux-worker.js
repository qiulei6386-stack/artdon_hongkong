(function (root) {
  'use strict';

  importScripts('/assets/js/lighting-calculator/ies-parser.js?v=3.2.0', '/assets/js/lighting-calculator/lux-engine.js?v=3.1.0');

  var activeJobId = null;

  root.onmessage = function (event) {
    var data = event.data || {};
    if (data.type === 'cancel') {
      activeJobId = null;
      root.postMessage({ type: 'cancelled', jobId: data.jobId || null });
      return;
    }
    if (data.type !== 'calculate') return;
    var jobId = data.jobId;
    activeJobId = jobId;
    try {
      var startedAt = Date.now();
      var result = root.ArtdonLuxEngine.calculate(data.ies, data.input, function (done, total) {
        if (activeJobId !== jobId) throw new Error('Calculation cancelled.');
        root.postMessage({ type: 'progress', jobId: jobId, done: done, total: total });
      });
      result.metrics = result.metrics || {};
      result.metrics.calculationTimeMs = Date.now() - startedAt;
      if (activeJobId !== jobId) {
        root.postMessage({ type: 'cancelled', jobId: jobId });
        return;
      }
      activeJobId = null;
      root.postMessage({ type: 'result', jobId: jobId, result: result });
    } catch (error) {
      activeJobId = null;
      root.postMessage({ type: 'error', jobId: jobId, message: error && error.message ? error.message : 'Calculation failed.' });
    }
  };
})(self);
