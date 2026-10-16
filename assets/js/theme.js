/* =====================================================================
 * assets/js/theme.js — Tema claro/oscuro (opt-in) de Multi App v2.
 *
 * Carga: ui/views/layouts/footer.php y cx/views/layouts/footer.php.
 *
 * Qué hace:
 *   - Lee/escribe la preferencia en localStorage['multi-theme'].
 *   - Aplica el atributo data-bs-theme en <html> (Bootstrap 5.3 nativo).
 *     El valor inicial lo fija el snippet anti-FOUC del <head>; aquí
 *     solo se mantiene y se re-sincroniza.
 *   - Alterna el tema al hacer clic en cualquier [data-theme-toggle]
 *     (delegación de eventos: sirve para el sidebar desktop y el
 *     offcanvas/drawer móvil con un solo listener).
 *   - Sincroniza icono (sol/luna), etiqueta y aria-pressed del botón.
 *
 * Por defecto: CLARO. Nunca sigue prefers-color-scheme.
 * ===================================================================== */
(function () {
  'use strict';
  var KEY = 'multi-theme';
  var root = document.documentElement;

  function current() {
    return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
  }

  /** Refresca TODOS los botones de tema con el estado actual. */
  function sync(theme) {
    var dark = theme === 'dark';
    var btns = document.querySelectorAll('[data-theme-toggle]');
    for (var i = 0; i < btns.length; i++) {
      var b = btns[i];
      b.setAttribute('aria-pressed', dark ? 'true' : 'false');
      b.setAttribute('title', dark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
      var ic = b.querySelector('[data-theme-icon]');
      if (ic) {
        ic.classList.toggle('fa-moon', !dark);
        ic.classList.toggle('fa-sun', dark);
      }
      var lb = b.querySelector('[data-theme-label]');
      if (lb) lb.textContent = dark ? 'Tema claro' : 'Tema oscuro';
    }
  }

  function set(theme) {
    var t = theme === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-bs-theme', t);
    try { localStorage.setItem(KEY, t); } catch (e) { /* modo privado: se ignora */ }
    sync(t);
  }

  function toggle() { set(current() === 'dark' ? 'light' : 'dark'); }

  document.addEventListener('click', function (e) {
    var el = e.target;
    while (el && el !== document) {
      if (el.hasAttribute && el.hasAttribute('data-theme-toggle')) {
        e.preventDefault();
        toggle();
        return;
      }
      el = el.parentNode;
    }
  });

  window.MultiTheme = { key: KEY, get: current, set: set, toggle: toggle };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { sync(current()); });
  } else {
    sync(current());
  }
})();
