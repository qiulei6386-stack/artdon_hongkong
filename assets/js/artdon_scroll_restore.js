(function () {
  'use strict';

  var STORAGE_PREFIX = 'artdon-scroll-v1:';
  var MAX_AGE = 12 * 60 * 60 * 1000;
  var RESTORE_DELAYS = [0, 40, 120, 260, 480, 800, 1250, 1900];
  var userInterrupted = false;
  var saveQueued = false;
  var restoring = false;

  function pageKey() {
    return STORAGE_PREFIX + window.location.pathname + window.location.search;
  }

  function navigationType() {
    try {
      var entries = window.performance && performance.getEntriesByType
        ? performance.getEntriesByType('navigation')
        : [];
      if (entries && entries[0] && entries[0].type) {
        return entries[0].type;
      }
      if (window.performance && performance.navigation) {
        if (performance.navigation.type === 1) return 'reload';
        if (performance.navigation.type === 2) return 'back_forward';
      }
    } catch (error) {
      // Ignore unsupported Performance APIs.
    }
    return 'navigate';
  }

  function getScrollableState() {
    var state = {};
    var selectors = [
      '[data-preserve-scroll]',
      '.admin-side',
      '.section-tabs',
      '.table-scroll',
      '.clean-filter',
      '.catalog-filter'
    ];
    var nodes = document.querySelectorAll(selectors.join(','));
    Array.prototype.forEach.call(nodes, function (node, index) {
      if (!node || (node.scrollTop === 0 && node.scrollLeft === 0)) return;
      var identity = node.getAttribute('data-scroll-key') || node.id || '';
      if (!identity) {
        var className = typeof node.className === 'string'
          ? node.className.trim().replace(/\s+/g, '.')
          : '';
        identity = (node.tagName || 'node').toLowerCase() + '.' + className + ':' + index;
      }
      state[identity] = { x: node.scrollLeft || 0, y: node.scrollTop || 0 };
    });
    return state;
  }

  function savePosition(force) {
    saveQueued = false;
    if (restoring && force !== true) return;
    try {
      var root = document.documentElement;
      var body = document.body;
      var maxY = Math.max(
        0,
        Math.max(root ? root.scrollHeight : 0, body ? body.scrollHeight : 0) - window.innerHeight
      );
      var y = window.pageYOffset || (root && root.scrollTop) || 0;
      var payload = {
        x: window.pageXOffset || (root && root.scrollLeft) || 0,
        y: y,
        ratio: maxY > 0 ? y / maxY : 0,
        height: Math.max(root ? root.scrollHeight : 0, body ? body.scrollHeight : 0),
        elements: getScrollableState(),
        savedAt: Date.now()
      };
      sessionStorage.setItem(pageKey(), JSON.stringify(payload));
    } catch (error) {
      // Storage can be blocked in private browsing; the page should still work.
    }
  }

  function queueSave() {
    if (restoring || saveQueued) return;
    saveQueued = true;
    window.requestAnimationFrame(savePosition);
  }

  function readPosition() {
    try {
      var raw = sessionStorage.getItem(pageKey());
      if (!raw) return null;
      var payload = JSON.parse(raw);
      if (!payload || typeof payload.y !== 'number') return null;
      if (!payload.savedAt || Date.now() - payload.savedAt > MAX_AGE) {
        sessionStorage.removeItem(pageKey());
        return null;
      }
      return payload;
    } catch (error) {
      return null;
    }
  }

  function findScrollable(identity) {
    if (!identity) return null;
    if (identity.charAt(0) !== '#' && identity.indexOf(':') === -1 && identity.indexOf('.') === -1) {
      return document.getElementById(identity);
    }
    if (document.getElementById(identity)) return document.getElementById(identity);
    return null;
  }

  function restoreElementPositions(elements) {
    if (!elements || typeof elements !== 'object') return;
    var selectors = [
      '[data-preserve-scroll]',
      '.admin-side',
      '.section-tabs',
      '.table-scroll',
      '.clean-filter',
      '.catalog-filter'
    ];
    var nodes = document.querySelectorAll(selectors.join(','));
    Array.prototype.forEach.call(nodes, function (node, index) {
      var identity = node.getAttribute('data-scroll-key') || node.id || '';
      if (!identity) {
        var className = typeof node.className === 'string'
          ? node.className.trim().replace(/\s+/g, '.')
          : '';
        identity = (node.tagName || 'node').toLowerCase() + '.' + className + ':' + index;
      }
      var point = elements[identity];
      if (!point) return;
      node.scrollLeft = Number(point.x) || 0;
      node.scrollTop = Number(point.y) || 0;
    });
  }

  function restorePosition(payload) {
    if (!payload || userInterrupted) return;
    var root = document.documentElement;
    var body = document.body;
    var documentHeight = Math.max(root ? root.scrollHeight : 0, body ? body.scrollHeight : 0);
    var maxY = Math.max(0, documentHeight - window.innerHeight);
    var targetY = Math.min(Math.max(0, payload.y), maxY);

    // If responsive content changed height significantly, retain the same visual progress.
    if (payload.height && documentHeight > 0 && Math.abs(documentHeight - payload.height) > 300 && payload.ratio > 0) {
      targetY = Math.min(maxY, Math.max(0, maxY * payload.ratio));
    }

    var previousBehavior = root ? root.style.scrollBehavior : '';
    if (root) root.style.scrollBehavior = 'auto';
    window.scrollTo(Number(payload.x) || 0, targetY);
    restoreElementPositions(payload.elements);
    if (root) root.style.scrollBehavior = previousBehavior;
  }

  function markUserInterrupted() {
    userInterrupted = true;
    restoring = false;
    queueSave();
  }

  try {
    if ('scrollRestoration' in window.history) {
      window.history.scrollRestoration = 'manual';
    }
  } catch (error) {
    // Ignore old browsers.
  }

  var type = navigationType();
  var payload = (type === 'reload' || type === 'back_forward') ? readPosition() : null;

  if (payload) {
    restoring = true;
    ['wheel', 'touchstart', 'pointerdown', 'keydown'].forEach(function (eventName) {
      window.addEventListener(eventName, markUserInterrupted, { once: true, passive: true });
    });

    RESTORE_DELAYS.forEach(function (delay) {
      window.setTimeout(function () { restorePosition(payload); }, delay);
    });

    document.addEventListener('DOMContentLoaded', function () { restorePosition(payload); }, { once: true });
    window.addEventListener('load', function () { restorePosition(payload); }, { once: true });
    window.setTimeout(function () {
      restoring = false;
      savePosition();
    }, RESTORE_DELAYS[RESTORE_DELAYS.length - 1] + 120);
  }

  window.addEventListener('scroll', queueSave, { passive: true });
  document.addEventListener('scroll', queueSave, true);
  window.addEventListener('pagehide', function () { savePosition(true); });
  window.addEventListener('beforeunload', function () { savePosition(true); });
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') savePosition(true);
  });

  // Capture the initial position as well, so an immediate refresh is stable.
  if (!payload) window.setTimeout(savePosition, 250);
})();
