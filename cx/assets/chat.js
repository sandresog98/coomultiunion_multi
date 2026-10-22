/* =====================================================================
 * cx/assets/chat.js — Asistente IA del portal de asociados.
 *
 * Sin streaming real: el backend responde el mensaje completo y aquí se
 * representa con etiquetas de estado rotativas ("Pensando…", "Investigando…",
 * "Redactando mensaje…") y un revelado progresivo (typewriter) del texto.
 *
 * Seguridad: todo el texto del modelo se inserta como NODO DE TEXTO
 * (textContent), nunca como HTML.
 * ===================================================================== */
(function () {
  'use strict';

  var API = window.CX_API;
  var elSesiones = document.getElementById('chat-sesiones');
  var elMensajes = document.getElementById('chat-mensajes');
  var form       = document.getElementById('chat-form');
  var input      = document.getElementById('chat-input');
  var btnNueva   = document.getElementById('chat-nueva');
  if (!elMensajes || !form || !input) return;

  var reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var state = { sesionId: null, sesiones: [], enviando: false, sesionAbierta: true };

  // ── Utilidades ────────────────────────────────────────────────────────
  function req(url, opts) {
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Respuesta inválida.' }; })
        .then(function (j) { return { status: r.status, body: j }; });
    });
  }
  function scrollAbajo() { elMensajes.scrollTop = elMensajes.scrollHeight; }
  function clear(el) { while (el.firstChild) el.removeChild(el.firstChild); }

  var TTL_AVISO = 'Esta conversación se cerró por inactividad. Abre una nueva conversación para seguir.';

  function habilitarInput(on) {
    input.disabled = !on;
    var send = document.getElementById('chat-send');
    if (send) send.disabled = !on;
    input.placeholder = on
      ? 'Escribe tu duda…'
      : 'Conversación cerrada — abre una nueva';
    // En una conversación cerrada se oculta toda el área de envío (caja + avisos).
    var main = document.querySelector('.chat-main');
    if (main) main.classList.toggle('es-cerrada', !on);
  }

  // Ventana de alerta reutilizable (modal Bootstrap). Devuelve Promise<boolean>.
  function alerta(titulo, texto, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var mEl = document.getElementById('chat-alerta');
      if (!mEl || !window.bootstrap) { resolve(window.confirm(texto)); return; }
      document.getElementById('chat-alerta-titulo').textContent = titulo || 'Aviso';
      document.getElementById('chat-alerta-texto').textContent = texto || '';
      var okBtn = document.getElementById('chat-alerta-ok');
      var cancelBtn = document.getElementById('chat-alerta-cancelar');
      okBtn.textContent = opts.ok || 'Aceptar';
      if (opts.cancelar) { cancelBtn.style.display = ''; cancelBtn.textContent = opts.cancelar; }
      else { cancelBtn.style.display = 'none'; }
      var modal = bootstrap.Modal.getOrCreateInstance(mEl);
      var resuelto = false;
      function cerrar(v) {
        if (resuelto) return;
        resuelto = true;
        okBtn.removeEventListener('click', onOk);
        cancelBtn.removeEventListener('click', onCancel);
        modal.hide();
        resolve(v);
      }
      function onOk() { cerrar(true); }
      function onCancel() { cerrar(false); }
      okBtn.addEventListener('click', onOk);
      cancelBtn.addEventListener('click', onCancel);
      mEl.addEventListener('hidden.bs.modal', function () {
        if (!resuelto) { resuelto = true; resolve(false); }
      }, { once: true });
      modal.show();
    });
  }

  function nuevaConversacion() {
    cerrarHistorial();
    state.sesionId = null;
    state.sesionAbierta = true;
    habilitarInput(true);
    bienvenida();
    cargarSesiones();
    input.focus();
  }

  function avisoCerrada() {
    var n = document.createElement('div');
    n.className = 'chat-cerrada';
    var span = document.createElement('span');
    span.textContent = TTL_AVISO;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chat-chip';
    btn.textContent = 'Nueva conversación';
    btn.addEventListener('click', function () { nuevaConversacion(); });
    n.appendChild(span);
    n.appendChild(btn);
    elMensajes.appendChild(n);
    scrollAbajo();
  }

  // Formatea fecha/hora. Acepta Date, timestamp numérico o "YYYY-MM-DD HH:MM:SS".
  function hora(fecha) {
    var d;
    if (fecha instanceof Date) {
      d = fecha;
    } else if (fecha === null || fecha === undefined || fecha === '') {
      return '';
    } else {
      var s = String(fecha);
      d = /^\d+$/.test(s) ? new Date(+s) : new Date(s.replace(' ', 'T'));
    }
    if (isNaN(d.getTime())) return '';
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var hoy = new Date();
    var mismoDia = d.toDateString() === hoy.toDateString();
    return mismoDia
      ? pad(d.getHours()) + ':' + pad(d.getMinutes())
      : pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  function avatar(rol) {
    var a = document.createElement('span');
    a.className = 'chat-avatar';
    a.setAttribute('aria-hidden', 'true');
    var img = (rol === 'assistant') ? (window.CX_CHAT_AVATAR || '') : '';
    if (img) {
      var im = document.createElement('img');
      im.src = img;
      im.alt = '';
      im.className = 'chat-avatar-img';
      a.appendChild(im);
    } else {
      a.innerHTML = rol === 'assistant'
        ? '<i class="fa-solid fa-robot"></i>'
        : '<i class="fa-solid fa-user"></i>';
    }
    return a;
  }

  function escapar(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Markdown-lite SEGURO: escapa HTML primero, luego **negrita**, [texto](url) y URLs sueltas.
  // Los enlaces solo admiten http(s):// o rutas relativas "/" (bloquea javascript:/data:).
  function anchor(u, txt) {
    return '<a class="chat-link" href="' + u + '">' + txt + '</a>';
  }

  function mdHtml(texto) {
    var s = escapar(texto);
    var anchors = [];
    // 1) Enlaces markdown [texto](url)
    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, txt, url) {
      var u = String(url).trim();
      if (!/^(https?:\/\/|\/)/i.test(u)) return m;
      anchors.push(anchor(u, txt));
      return '\u0000' + (anchors.length - 1) + '\u0000';
    });
    // 2) URLs sueltas (http/https), guardando los anclajes en marcadores.
    s = s.replace(/(https?:\/\/[^\s<]+)/gi, function (u) {
      var limpio = u.replace(/[.,;:!?]+$/, '');
      var cola = u.slice(limpio.length);
      anchors.push(anchor(limpio, limpio));
      return '\u0000' + (anchors.length - 1) + '\u0000' + cola;
    });
    // 3) Negritas
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    // 4) Restaurar anclajes
    s = s.replace(/\u0000(\d+)\u0000/g, function (m, i) { return anchors[+i] || m; });
    return s;
  }

  function tieneMarkdown(texto) {
    return /\*\*|\[[^\]]+\]\([^)\s]+\)|https?:\/\//i.test(String(texto == null ? '' : texto));
  }

  // ── Render de burbujas ────────────────────────────────────────────────
  function burbuja(rol, texto, fecha) {
    var wrap = document.createElement('div');
    wrap.className = 'chat-msg ' + rol;
    wrap.appendChild(avatar(rol));

    var b = document.createElement('div');
    b.className = 'chat-bubble';
    var span = document.createElement('span');
    span.className = 'chat-text';
    if (rol === 'assistant') {
      span.innerHTML = mdHtml(texto);
    } else {
      span.textContent = texto == null ? '' : String(texto);
    }
    b.appendChild(span);

    var t = hora(fecha);
    if (t) {
      var tm = document.createElement('div');
      tm.className = 'chat-time';
      tm.textContent = t;
      b.appendChild(tm);
    }

    wrap.appendChild(b);
    elMensajes.appendChild(wrap);
    scrollAbajo();
    return { wrap: wrap, bubble: b, span: span };
  }

  function enviarFeedback(mid, util, comentario) {
    var payload = { mensaje_id: mid, util: util ? 1 : 0 };
    var c = comentario == null ? '' : String(comentario).trim();
    if (c !== '') payload.comentario = c;
    return req(API + '/chat_feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
  }

  function marcar(bar, sel) {
    var btns = bar.querySelectorAll('.chat-fb-btn');
    for (var i = 0; i < btns.length; i++) btns[i].disabled = true;
    if (sel) sel.classList.add('sel');
  }

  function adjuntarFeedback(bubble, mensajeId, util) {
    if (!mensajeId) return;
    var bar = document.createElement('div');
    bar.className = 'chat-fb';

    var up = document.createElement('button');
    up.type = 'button';
    up.className = 'chat-fb-btn';
    up.title = 'Útil';
    up.innerHTML = '<i class="fa-regular fa-thumbs-up"></i>';
    up.addEventListener('click', function () {
      if (up.dataset.done) return;
      up.dataset.done = '1';
      enviarFeedback(mensajeId, true).then(function () { marcar(bar, up); });
    });

    var down = document.createElement('button');
    down.type = 'button';
    down.className = 'chat-fb-btn';
    down.title = 'No útil';
    down.innerHTML = '<i class="fa-regular fa-thumbs-down"></i>';
    down.addEventListener('click', function () {
      if (down.dataset.done) return;
      down.dataset.done = '1';

      // Caja de comentario opcional para explicar qué falló.
      var box = document.createElement('div');
      box.className = 'chat-fb-comment';
      var inp = document.createElement('input');
      inp.type = 'text';
      inp.className = 'portal-input';
      inp.maxLength = 500;
      inp.placeholder = '¿Qué salió mal? (opcional)';
      var send = document.createElement('button');
      send.type = 'button';
      send.className = 'chat-fb-send';
      send.textContent = 'Enviar';
      send.addEventListener('click', function () {
        enviarFeedback(mensajeId, false, inp.value).then(function () {
          marcar(bar, down);
          box.remove();
        });
      });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); send.click(); }
      });
      box.appendChild(inp);
      box.appendChild(send);
      bar.appendChild(box);
      inp.focus();
    });

    bar.appendChild(up);
    bar.appendChild(down);
    bubble.appendChild(bar);

    // Restaurar la valoración guardada (si existe): marca el pulgar correspondiente.
    if (util === 1 || util === '1') { up.classList.add('sel'); }
    else if (util === 0 || util === '0') { down.classList.add('sel'); }
  }

  // ── Bienvenida ────────────────────────────────────────────────────────
  function bienvenida() {
    state.sesionAbierta = true;
    habilitarInput(true);
    clear(elMensajes);
    var nom = (typeof window.CX_CHAT_NOMBRE === 'string' ? window.CX_CHAT_NOMBRE.trim() : '');
    var quien = nom ? ('Soy **' + nom + '**, el asistente virtual de Coomultiunion.') : 'Soy el asistente virtual de Coomultiunion.';
    var b = burbuja('assistant',
      '¡Hola! ' + quien + ' Puedo ayudarte con dudas sobre la cooperativa ' +
      'y sobre el uso de la aplicación (aportes, créditos, solicitudes, convenios, contraseña, etc.).\n\n' +
      'Por seguridad, no compartas por este chat contraseñas, códigos de verificación ni datos financieros.\n\n' +
      '¿En qué te puedo ayudar?', new Date());
    var chips = document.createElement('div');
    chips.className = 'chat-chips';
    ['¿Cómo solicito un crédito?', '¿Qué son los aportes ordinarios?', '¿Cómo cambio mi contraseña?', '¿Cómo pago una boleta de convenio?']
      .forEach(function (t) {
        var c = document.createElement('button');
        c.type = 'button';
        c.className = 'chat-chip';
        c.textContent = t;
        c.addEventListener('click', function () {
          input.value = t;
          autoGrow();
          input.focus();
        });
        chips.appendChild(c);
      });
    b.bubble.appendChild(chips);
    scrollAbajo();
  }

  // ── Sesiones ──────────────────────────────────────────────────────────
  function cargarSesiones() {
    return req(API + '/chat_sesiones.php', { headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        state.sesiones = (res.body && res.body.ok && Array.isArray(res.body.data)) ? res.body.data : [];
        pintarSesiones();
      });
  }

  function pintarSesiones() {
    clear(elSesiones);
    if (!state.sesiones.length) {
      var v = document.createElement('div');
      v.className = 'chat-side-empty';
      v.textContent = 'Sin conversaciones todavía.';
      elSesiones.appendChild(v);
      return;
    }
    state.sesiones.forEach(function (s) {
      var row = document.createElement('div');
      row.className = 'chat-sesion' + (String(s.id) === String(state.sesionId) ? ' active' : '') + (s.abierta ? '' : ' cerrada');
      var a = document.createElement('button');
      a.type = 'button';
      a.className = 'chat-sesion-btn';
      var t = document.createElement('span');
      t.className = 'chat-sesion-title';
      t.textContent = s.titulo || 'Conversación';
      a.appendChild(t);
      if (!s.abierta) {
        var lock = document.createElement('i');
        lock.className = 'fa-solid fa-lock chat-sesion-lock';
        lock.title = 'Conversación cerrada';
        a.appendChild(lock);
      }
      a.addEventListener('click', function () { abrirSesion(s.id); });
      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'chat-sesion-del';
      del.title = 'Archivar';
      del.innerHTML = '<i class="fa-regular fa-trash-can"></i>';
      del.addEventListener('click', function (e) {
        e.stopPropagation();
        archivar(s.id);
      });
      row.appendChild(a);
      row.appendChild(del);
      elSesiones.appendChild(row);
    });
  }

  function abrirSesion(id) {
    cerrarHistorial();
    state.sesionId = id;
    var ses = null;
    for (var k = 0; k < state.sesiones.length; k++) {
      if (String(state.sesiones[k].id) === String(id)) { ses = state.sesiones[k]; break; }
    }
    state.sesionAbierta = !!(ses && ses.abierta);
    habilitarInput(state.sesionAbierta);
    pintarSesiones();
    clear(elMensajes);
    req(API + '/chat_mensajes.php?sesion_id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
      .then(function (res) {
        var rows = (res.body && res.body.ok && Array.isArray(res.body.data)) ? res.body.data : [];
        clear(elMensajes);
        if (!rows.length) {
          if (state.sesionAbierta) { bienvenida(); } else { avisoCerrada(); }
          return;
        }
        rows.forEach(function (m) {
          if (m.rol !== 'user' && m.rol !== 'assistant') return;
          var b = burbuja(m.rol, m.contenido, m.fecha_creacion);
          if (m.rol === 'assistant' && m.estado === 'ok') adjuntarFeedback(b.bubble, m.id, m.util);
        });
        if (!state.sesionAbierta) avisoCerrada();
        scrollAbajo();
      });
  }

  function archivar(id) {
    req(API + '/chat_eliminar.php', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sesion_id: id })
    }).then(function () {
      if (String(state.sesionId) === String(id)) {
        nuevaConversacion();
      } else {
        cargarSesiones();
      }
    });
  }

  // ── Animación "escribiendo" ───────────────────────────────────────────
  function burbujaEstado() {
    var wrap = document.createElement('div');
    wrap.className = 'chat-msg assistant';
    wrap.appendChild(avatar('assistant'));

    var b = document.createElement('div');
    b.className = 'chat-bubble';
    var st = document.createElement('span');
    st.className = 'chat-status';
    b.appendChild(st);
    wrap.appendChild(b);
    elMensajes.appendChild(wrap);
    scrollAbajo();

    var labels = ['Pensando', 'Investigando', 'Redactando mensaje'];
    var li = 0, dots = 1;
    function dotsTxt(n) { return n === 1 ? '.' : (n === 2 ? '..' : '...'); }
    st.textContent = labels[0] + '.';
    var timer = setInterval(function () {
      dots = (dots % 3) + 1;
      if (dots === 1) li = (li + 1) % labels.length;
      st.textContent = labels[li] + dotsTxt(dots);
    }, 1000);

    return {
      wrap: wrap, bubble: b,
      stop: function () { clearInterval(timer); }
    };
  }

  function revelar(estado, texto, mensajeId) {
    return new Promise(function (resolve) {
      estado.stop();
      var span = document.createElement('span');
      span.className = 'chat-text';
      estado.bubble.textContent = '';
      estado.bubble.appendChild(span);
      texto = texto == null ? '' : String(texto);

      var tm = document.createElement('div');
      tm.className = 'chat-time';
      tm.textContent = hora(new Date());
      estado.bubble.appendChild(tm);

      function terminar() {
        adjuntarFeedback(estado.bubble, mensajeId);
        scrollAbajo();
        resolve();
      }

      if (reduced || texto.length === 0 || tieneMarkdown(texto)) {
        span.innerHTML = mdHtml(texto);
        terminar();
        return;
      }

      var total = texto.length;
      // Velocidad adaptativa: respuestas largas no tardan más de ~3.5 s.
      var delay = Math.max(5, Math.min(24, Math.round(3500 / total)));
      var i = 0;
      (function tick() {
        i++;
        span.textContent = texto.slice(0, i);
        scrollAbajo();
        if (i < total) { setTimeout(tick, delay); } else { terminar(); }
      })();
    });
  }

  // ── Envío ─────────────────────────────────────────────────────────────
  function enviar() {
    if (state.enviando || !state.sesionAbierta) return;
    var texto = input.value.trim();
    if (!texto) return;
    input.value = '';
    autoGrow();

    var userB = burbuja('user', texto, new Date());
    var estado = burbujaEstado();
    state.enviando = true;

    var payload = { mensaje: texto };
    if (state.sesionId) payload.sesion_id = state.sesionId;

    req(API + '/chat_enviar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (res) {
      if (!res.body || !res.body.ok) {
        estado.stop();
        if (res.body && res.body.code === 'sesion_cerrada') {
          // Se cerró por inactividad: se descarta el turno y se ofrece abrir una nueva.
          userB.wrap.remove();
          estado.wrap.remove();
          state.sesionAbierta = false;
          habilitarInput(false);
          cargarSesiones();
          alerta('Conversación cerrada', (res.body.error || TTL_AVISO),
            { ok: 'Nueva conversación', cancelar: 'Cerrar' }
          ).then(function (ok) {
            if (ok) { nuevaConversacion(); input.value = texto; autoGrow(); input.focus(); }
          });
          return null;
        }
        estado.bubble.textContent = (res.body && res.body.error) || 'No se pudo obtener respuesta. Intenta de nuevo.';
        estado.wrap.classList.add('error');
        if (state.sesionId) cargarSesiones();
        return null;
      }
      var d = res.body.data;
      state.sesionId = d.sesion_id;
      return revelar(estado, d.respuesta, d.mensaje_id).then(function () {
        return cargarSesiones();
      });
    }).catch(function () {
      estado.stop();
      estado.bubble.textContent = 'Sin conexión con el asistente. Revisa tu red e inténtalo de nuevo.';
      estado.wrap.classList.add('error');
    }).then(function () {
      state.enviando = false;
    });
  }

  // ── Input (auto-alto + Enter envía) ───────────────────────────────────
  function autoGrow() {
    input.style.height = 'auto';
    input.style.height = Math.min(140, input.scrollHeight) + 'px';
  }
  input.addEventListener('input', autoGrow);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      enviar();
    }
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); enviar(); });
  btnNueva.addEventListener('click', function () {
    var hayAbierta = false;
    for (var i = 0; i < state.sesiones.length; i++) {
      if (state.sesiones[i].abierta) { hayAbierta = true; break; }
    }
    if (hayAbierta) {
      alerta('Nueva conversación',
        'Solo puedes tener una conversación abierta a la vez. Si inicias una nueva, la conversación actual se cerrará.',
        { ok: 'Iniciar nueva', cancelar: 'Cancelar' }
      ).then(function (ok) { if (ok) nuevaConversacion(); });
    } else {
      nuevaConversacion();
    }
  });

  // ── Cajón de historial (móvil): entra desde la derecha ────────────────
  var btnHistorial = document.getElementById('chat-historial-toggle');
  var backdropHist = document.getElementById('chat-historial-backdrop');
  function cerrarHistorial() { document.body.classList.remove('chat-historial-open'); }
  function toggleHistorial() { document.body.classList.toggle('chat-historial-open'); }
  if (btnHistorial) btnHistorial.addEventListener('click', toggleHistorial);
  if (backdropHist) backdropHist.addEventListener('click', cerrarHistorial);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarHistorial(); });

  // ── Enlaces (respuestas del bot y "Radicar PQRS"): confirmar antes de salir ──
  // Se navega en la MISMA ventana, previa validación del asociado.
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a.chat-link') : null;
    if (!a) return;
    var url = a.getAttribute('href');
    if (!url || url.charAt(0) === '#') return;
    e.preventDefault();
    var destino = (a.getAttribute('data-destino') || a.textContent || url).replace(/\s+/g, ' ').trim();
    alerta('Salir del chat',
      'Esto te llevará a «' + destino + '». ¿Quieres dirigirte allá o seguir en el chat?',
      { ok: 'Ir ahora', cancelar: 'Seguir en el chat' }
    ).then(function (ok) { if (ok) window.location.href = url; });
  });

  // ── Arranque ──────────────────────────────────────────────────────────
  cargarSesiones().then(function () {
    var abierta = null;
    for (var i = 0; i < state.sesiones.length; i++) {
      if (state.sesiones[i].abierta) { abierta = state.sesiones[i]; break; }
    }
    if (abierta) abrirSesion(abierta.id);
    else bienvenida();
    input.focus();
  });
})();
