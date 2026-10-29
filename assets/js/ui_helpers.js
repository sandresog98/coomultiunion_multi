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
    opts = opts || {};
    var base = opts.base != null ? opts.base : (window.API || '');
    var method = String(opts.method || 'GET').toUpperCase();
    var timeoutMs = method === 'GET' ? 12000 : 30000;
    // Reintentar automáticamente solo lecturas: una mutación con error de red
    // puede haber sido procesada y no conviene duplicarla.
    var maxIntentos = method === 'GET' ? 2 : 1;

    if (!base) {
      throw new Error('Base de API no configurada (window.API o opts.base)');
    }

    function intento(n) {
      var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
      var timer = controller ? setTimeout(function () { controller.abort(); }, timeoutMs) : null;
      var init = Object.assign({}, opts);
      delete init.base;
      if (controller) init.signal = controller.signal;

      return fetch(base + path, init).then(function (r) {
        return r.json().catch(function () { return null; }).then(function (j) {
          return { r: r, j: j };
        });
      }).then(function (out) {
        if (timer) clearTimeout(timer);
        if (out.j && out.j.ok) return out.j.data;
        var retriable = out.r.status === 502 || out.r.status === 503 || out.r.status === 504;
        if (retriable && n < maxIntentos - 1) {
          var ra = parseInt(out.r.headers.get('Retry-After') || '0', 10);
          var espera = ra > 0 ? Math.min(ra, 3) * 1000 : 500;
          return new Promise(function (resolve) { setTimeout(resolve, espera); }).then(function () { return intento(n + 1); });
        }
        var mensaje = (out.j && out.j.error) || ('Error ' + out.r.status);
        if (!out.j) mensaje += ' en ' + out.r.url;
        throw new Error(mensaje);
      }, function (err) {
        if (timer) clearTimeout(timer);
        if (err && err.name === 'AbortError') {
          throw new Error('La solicitud tardó demasiado. Intenta de nuevo.');
        }
        if (n < maxIntentos - 1) {
          return new Promise(function (resolve) { setTimeout(resolve, 400); }).then(function () { return intento(n + 1); });
        }
        throw new Error('Sin conexión con el servidor');
      });
    }

    return intento(0);
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

  /* =====================================================================
   * Selects de filtro con búsqueda (Tom Select).
   * Marca un <select data-searchable> y se convierte en un control donde
   * se escribe para filtrar opciones. El <select> nativo sigue siendo la
   * fuente de verdad: el.value y los eventos 'change' siguen funcionando.
   * ===================================================================== */
  /* Firma insensible al orden de las <option> (valor + texto). Tom Select
   * reordena las opciones nativas al seleccionar; comparamos como conjunto
   * para reaccionar solo cuando la página repuebla de verdad la lista. */
  function optionSig(el) {
    var arr = [];
    for (var i = 0; i < el.options.length; i++) {
      arr.push(el.options[i].value + '\u0001' + el.options[i].text);
    }
    arr.sort();
    return arr.join('\u0002');
  }

  /* Recarga las opciones del control desde el <select> nativo. */
  function reloadSelect(el) {
    var obs = el.__tsObs;
    if (obs) obs.disconnect();
    try {
      el.__ts.clear(true);        // suelta los ítems para poder limpiar todo
      el.__ts.clearOptions();
      el.__ts.sync();             // relee las <option> nativas
      el.__tsSig = optionSig(el);
    } finally { if (obs) obs.observe(el, { childList: true }); }
  }

  function searchableSelect(el) {
    if (!el || el.__ts || typeof window.TomSelect !== 'function') return null;
    /* Clases/ancho originales ANTES de crear Tom Select: si se leen después,
     * el <select> ya trae las clases propias (tomselected / ts-hidden-accessible)
     * y copiarlas al wrapper lo saca del flujo (position:absolute) y rompe el layout. */
    var baseClasses = (el.className || '').split(/\s+/).filter(Boolean);
    var baseWidth = el.style && el.style.width ? el.style.width : '';
    var ts = new window.TomSelect(el, {
      create: false,
      allowEmptyOption: true,               // conserva la opción "Todos" (value="")
      maxItems: el.multiple ? null : 1,
      plugins: ['dropdown_input'],
      placeholder: el.dataset.placeholder || 'Buscar…',
      onInitialize: function () {
        var w = this.wrapper;
        if (!w) return;
        /* Conservamos el look/altura del tema Bootstrap (form-select[-sm]). */
        baseClasses.forEach(function (c) { w.classList.add(c); });
        w.classList.remove('tomselected', 'ts-hidden-accessible');
        /* Tom Select copia el width del <select> al wrapper; lo reemplazamos
         * por max-content para que crezca a la derecha con el texto largo
         * (nunca en alto) y mantenga el ancho base como mínimo. */
        w.style.width = 'max-content';
        if (baseWidth) w.style.minWidth = baseWidth;
      }
    });
    el.__ts = ts;
    el.__tsSig = optionSig(el);
    /* Reacciona si la página repuebla las <option> (innerHTML). */
    if (window.MutationObserver) {
      var obs = new MutationObserver(function () {
        obs.disconnect();
        if (optionSig(el) !== el.__tsSig) reloadSelect(el);
        else obs.observe(el, { childList: true });
      });
      obs.observe(el, { childList: true });
      el.__tsObs = obs;
    }
    return ts;
  }

  function searchableSelects(root) {
    (root || document).querySelectorAll('select[data-searchable]').forEach(searchableSelect);
  }

  /** Fija el valor aunque el select esté mejorado, sin disparar 'change'. */
  function setSelect(el, value) {
    if (typeof el === 'string') el = document.getElementById(el);
    if (!el) return;
    value = value == null ? '' : value;
    if (el.__ts) el.__ts.setValue(value, true);
    else el.value = value;
  }

  /** Relee las <option> del select mejorado (tras repoblarlo por código). */
  function refreshSelect(el) {
    if (typeof el === 'string') el = document.getElementById(el);
    if (el && el.__ts) reloadSelect(el);
  }

  /* =====================================================================
   * Selector "Mostrar N" para listados (paginación).
   * Se monta dentro de un contenedor, junto al "Mostrando X-Y de Z".
   * Uso:  Listado.mountSize('fSizeBox', state, cargar);
   *       Listado.mountSize('fSizeBoxP', stP, cargarP, {id:'fSizeP', class:'small text-muted'});
   * Requiere que `state` tenga { size, page }.
   * ===================================================================== */
  function mountSize(container, state, onChange, opts) {
    opts = opts || {};
    var id = opts.id || 'fSize';
    if (typeof container === 'string') container = document.getElementById(container);
    if (!container) return;
    if (state.size == null) state.size = 20;
    var labelCls = opts.class || 'small text-white-50';
    var sizes = opts.sizes || [20, 50, 100];
    container.innerHTML =
      '<span class="d-flex align-items-center gap-1">'
      + '<label class="' + labelCls + ' mb-0" for="' + id + '">Mostrar</label>'
      + '<select class="form-select form-select-sm" id="' + id + '" style="width:auto">'
      + sizes.map(function (s) {
        return '<option value="' + s + '"' + (Number(state.size) === s ? ' selected' : '') + '>' + s + '</option>';
      }).join('')
      + '</select></span>';
    var el = document.getElementById(id);
    el.value = String(state.size);
    el.addEventListener('change', function () {
      state.size = parseInt(this.value, 10) || 20;
      state.page = 0;
      onChange();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { searchableSelects(); });
  } else {
    searchableSelects();
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
    bindSort: bindSort,
    searchableSelect: searchableSelect,
    searchableSelects: searchableSelects,
    setSelect: setSelect,
    refreshSelect: refreshSelect,
    mountSize: mountSize
  };
})();
