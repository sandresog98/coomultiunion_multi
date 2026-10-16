/* =====================================================================
 * assets/js/ui_helpers.js — utilidades compartidas de los LISTADOS del staff.
 * Centraliza lo que cada página repetía: esc, money, api, debounce,
 * formato de fechas, paginación y ordenamiento de columnas.
 *
 * Se expone como window.Listado (namespace) para no pisar helpers locales.
 * Carga: ui/views/layouts/footer.php (después de common.js).
 * ===================================================================== */
(function () {
  'use strict';
  if (window.Listado) return;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function money(v) {
    var n = Number(v) || 0;
    return (n < 0 ? '-' : '') + '$' + Math.abs(n).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  }

  function debounce(fn, ms) {
    var t;
    return function () { clearTimeout(t); t = setTimeout(fn, ms); };
  }

  function fmtDt(v) {
    if (!v) return '—';
    var d = new Date(v);
    return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('es-CO');
  }

  function fmtDtHora(v) {
    if (!v) return '—';
    var d = new Date(v);
    return isNaN(d.getTime()) ? '—' : d.toLocaleString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  /** fetch + JSON; usa window.API como base. Lanza Error con j.error si !ok. */
  function api(path, opts) {
    var base = window.API || '';
    return fetch(base + path, opts)
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) throw new Error(j.error || 'Error'); return j.data; });
  }

  /** HTML de paginación (mismo estilo que usaban las páginas). Los enlaces llaman goPage(p). */
  function paginacion(total, page, pageSize) {
    var N = Math.ceil(total / pageSize);
    if (N <= 1) return '';
    var C = page, pages = [];
    if (N <= 10) { for (var i = 0; i < N; i++) pages.push(i); }
    else {
      pages.push(0);
      if (C > 3) pages.push('...');
      for (var i = Math.max(1, C - 2); i <= Math.min(N - 2, C + 2); i++) pages.push(i);
      if (C < N - 4) pages.push('...');
      pages.push(N - 1);
    }
    var h = [];
    if (C > 0) h.push('<li class="page-item"><a class="page-link" href="#" onclick="goPage(' + (C - 1) + ')">«</a></li>');
    pages.forEach(function (p) {
      if (p === '...') h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
      else h.push('<li class="page-item ' + (p === C ? 'active' : '') + '"><a class="page-link" href="#" onclick="goPage(' + p + ')">' + (p + 1) + '</a></li>');
    });
    if (C < N - 1) h.push('<li class="page-item"><a class="page-link" href="#" onclick="goPage(' + (C + 1) + ')">»</a></li>');
    return '<ul class="pagination pagination-sm justify-content-center mb-0">' + h.join('') + '</ul>';
  }

  /** Pinta los iconos ▲/▼ en las cabeceras ordenables. state = {order, dir}. */
  function renderSortIcons(root, state) {
    root.querySelectorAll('th.sortable').forEach(function (th) {
      var col = th.dataset.col, active = state.order === col;
      th.classList.toggle('active', active);
      var txt = th.textContent.trim().replace(/[▲▼]/g, '');
      th.innerHTML = txt + (active ? ' <span class="sort-icon">' + (state.dir === 'ASC' ? '▲' : '▼') + '</span>' : '');
    });
  }

  /** Vincula clics en th.sortable: alterna order/dir, resetea página y llama onChange. */
  function bindSort(root, state, onChange) {
    root.querySelectorAll('th.sortable').forEach(function (th) {
      th.addEventListener('click', function () {
        var col = th.dataset.col;
        if (state.order === col) state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
        else { state.order = col; state.dir = 'ASC'; }
        state.page = 0;
        onChange();
      });
    });
  }

  window.Listado = {
    esc: esc,
    money: money,
    debounce: debounce,
    fmtDt: fmtDt,
    fmtDtHora: fmtDtHora,
    api: api,
    paginacion: paginacion,
    renderSortIcons: renderSortIcons,
    bindSort: bindSort
  };
})();
