/* =====================================================================
 * assets/js/common.js — Guard de envío + Idempotency-Key (ROLL anti duplicidad)
 *
 * CARGA: ui/views/layouts/footer.php y cx/views/layouts/footer.php (global).
 *
 * Qué hace (automático, sin tocar onclick de las páginas):
 *   1. uuid() — v4 con crypto.randomUUID() y fallback.
 *   2. Parche a window.fetch: todo POST/PUT/DELETE lleva X-Idempotency-Key
 *      y, si se disparó por un clic, pone el botón en busy
 *      (disabled + spinner) hasta que su(s) request(s) terminen. Por ACCIÓN,
 *      no por página → flujos de "agregar varios productos" siguen libres.
 *   3. Enter: si el submitter del form ya está busy → preventDefault (no doble).
 *   4. Exports: guardarSubmit(btn, fn) para casos especiales.
 *
 * Contrato: no pisa esc()/money()/post()/api()/CX_API ni otros helpers.
 * ===================================================================== */
(function () {
  'use strict';
  if (window.__MultiAppSubmitGuard) return;   // idempotente: no se encierra doble
  window.__MultiAppSubmitGuard = true;

  function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    var b = new Uint8Array(16);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(b);
    } else {
      for (var i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256);
    }
    b[6] = (b[6] & 15) | 64;  // versión 4
    b[8] = (b[8] & 63) | 128; // variante RFC 4122
    var hex = Array.prototype.map.call(b, function (x) {
      return (x < 16 ? '0' : '') + x.toString(16);
    }).join('');
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) +
           '-' + hex.slice(16, 20) + '-' + hex.slice(20);
  }

  var busy = new WeakMap();  // control -> nº de mutaciones en vuelo
  var keys = new WeakMap();  // control -> clave idempotente del intento en curso
  var lastClicked = null;    // control que disparó el penúltimo clic

  function closer(el) {
    var t = el;
    while (t && t !== document) {
      var tag = t.tagName;
      if (tag === 'BUTTON' || tag === 'INPUT' || tag === 'SELECT' || tag === 'A') return t;
      t = t.parentNode;
    }
    return null;
  }
  document.addEventListener('click', function (e) {
    lastClicked = closer(e.target);
    clearTimeout(lastClicked._idemT);
    lastClicked._idemT = setTimeout(function () { lastClicked = null; }, 1500);
  }, true);

  function guardOn(ctl) {
    if (!ctl) return;
    var n = busy.get(ctl) || 0;
    if (n === 0) {
      if (ctl.tagName === 'BUTTON') {
        if (!ctl.dataset.loading) ctl.dataset.loading = ctl.innerHTML;
        ctl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando…';
      }
      ctl.disabled = true;
    }
    busy.set(ctl, n + 1);
  }
  function guardOff(ctl) {
    if (!ctl) return;
    var n = (busy.get(ctl) || 0) - 1;
    if (n <= 0) {
      busy.delete(ctl);
      keys.delete(ctl);
      ctl.disabled = false;
      if (ctl.tagName === 'BUTTON' && ctl.dataset.loading) {
        ctl.innerHTML = ctl.dataset.loading;
        delete ctl.dataset.loading;
      }
    } else {
      busy.set(ctl, n);
    }
  }
  function keyFor(ctl) {
    if (ctl && busy.has(ctl)) {
      if (keys.has(ctl)) return keys.get(ctl);  // reintento del mismo clic → misma clave
      var k = uuid();
      keys.set(ctl, k);
      return k;
    }
    return uuid();
  }

  var origFetch = window.fetch;
  if (typeof origFetch !== 'function') return;

  window.fetch = function (input, init) {
    init = init || {};
    var method = String(
      (init.method || (input && typeof input.method === 'string' ? input.method : '') || 'GET')
    ).toUpperCase();
    if (method === 'GET') {
      return origFetch.call(this, input, init);
    }
    var ctl = lastClicked && lastClicked.tagName ? lastClicked : null;
    if (ctl) guardOn(ctl);
    var headers = init.headers || (input && input.headers) || {};
    var h = new Headers(headers);
    if (!h.has('X-Idempotency-Key')) {
      h.set('X-Idempotency-Key', keyFor(ctl));
    }
    if (!h.has('X-CSRF-Token')) {
      var m = document.querySelector('meta[name="csrf-token"]');
      if (m && m.getAttribute('content')) {
        h.set('X-CSRF-Token', m.getAttribute('content'));
      }
    }
    init.headers = h;
    var self = this;
    return origFetch.call(this, input, init).then(
      function (r) { if (ctl) guardOff(ctl); return r; },
      function (err) { if (ctl) guardOff(ctl); throw err; }
    );
  };

  /* Enter: no re-enviar si el submit ya está en curso. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || f.tagName !== 'FORM') return;
    var s = e.submitter || f.querySelector('[type="submit"]');
    if ((s && busy.has(s)) || (lastClicked && busy.has(lastClicked))) {
      e.preventDefault();
    }
  }, true);

  /* Guard explícito para casos especiales: guardarSubmit(btn, fn)
   * → devuelve un handler que bloquea, muestra carga y restaura en finally. */
  function guardarSubmit(btn, fn) {
    if (!btn) return function () {};
    if (busy.has(btn)) return function () {};   // idempotente
    return function (ev) {
      if (busy.has(btn)) return;
      if (ev && typeof ev.preventDefault === 'function') ev.preventDefault();
      lastClicked = btn;
      guardOn(btn);
      var out;
      try {
        out = fn();
      } catch (err) {
        guardOff(btn);
        throw err;
      }
      if (out && typeof out.then === 'function') {
        out.then(
          function () { guardOff(btn); },
          function () { guardOff(btn); }       // .catch: restaura igual
        );
      } else {
        guardOff(btn);
      }
    };
  }

  window.uuid = uuid;
  window.guardarSubmit = guardarSubmit;
  window.MultiAppFetch = origFetch;
})();