/* =====================================================================
 * assets/js/solicitud_detalle.js — helpers compartidos del DETALLE de
 * solicitudes para la gestión (ui/) y el portal (cx/), de modo que ambos
 * lados muestren la misma información con las mismas etiquetas.
 *
 * Expone window.SolDetalle. No depende de Listado ni de jQuery.
 * ===================================================================== */
(function () {
  'use strict';
  if (window.SolDetalle) return;

  var TIPOS = {
    pqrs: 'PQRS', actualizar_info: 'Actualización de información', redencion: 'Redención de saldos',
    nuevo_producto: 'Nuevo producto', boleta: 'Solicitud de boletas', retiro_incentivos: 'Retiro de incentivos',
    certificado: 'Certificado', fondo: 'Fondo de Bienestar', ticket: 'Ticket interno', retiro_aporte: 'Retiro de aportes'
  };

  var ESTADO = {
    pendiente: ['Pendiente', 'secondary'], recibido: ['Recibido', 'info'], asignado: ['Asignado', 'info'],
    backlog: ['Backlog', 'secondary'], en_curso: ['En curso', 'warning'], en_espera: ['En espera', 'warning'],
    resuelto: ['Resuelto', 'primary'], aceptado: ['Aceptado', 'success'], aprobada: ['Aprobada', 'success'],
    rechazada: ['Rechazada', 'danger'], rechazado: ['Rechazado', 'danger'], cancelado: ['Cancelado', 'dark'],
    desembolsado: ['Desembolsado', 'success'], anulado: ['Anulado', 'dark']
  };

  var FASE = {
    CREADA: ['Creada', 'secondary'], EN_GESTION: ['En gestión', 'info'], PRE_RESUELTA: ['Pre-resuelta', 'warning'],
    RESUELTA_POSITIVA: ['Positiva', 'success'], RESUELTA_NEGATIVA: ['Negativa', 'danger'], ANULADA: ['Anulada', 'dark']
  };

  var EVENTO = {
    creacion: 'Creación', asignacion: 'Asignación', reasignacion: 'Reasignación',
    cambio_estado: 'Cambio de estado', respuesta: 'Respuesta', comentario: 'Comentario', adjunto: 'Adjunto'
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function money(v) {
    var n = Number(v) || 0;
    return (n < 0 ? '-' : '') + '$' + Math.abs(n).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  }
  function fmtDtHora(v) {
    if (!v) return '—';
    var d = new Date(v);
    return isNaN(d.getTime()) ? '—' : d.toLocaleString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
  function fmtDt(v) {
    if (!v) return '—';
    var d = new Date(v);
    return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('es-CO');
  }

  function tipoLabel(c) { return TIPOS[c] || c; }
  function estadoLabel(c) { return (ESTADO[c] || [c])[0]; }
  function estadoBadge(c) { return ESTADO[c] || [c, 'secondary']; }
  function faseBadge(f) { return FASE[f] || [f, 'secondary']; }
  function eventoLabel(c) { return EVENTO[c] || c; }

  /** Estrellas de solo lectura (0-5). */
  function estrellasHtml(p) {
    p = Math.max(0, Math.min(5, Math.round(Number(p) || 0)));
    var s = '';
    for (var i = 1; i <= 5; i++) {
      s += i <= p ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
    }
    return '<span class="sd-stars" title="' + p + ' de 5">' + s + '</span>';
  }

  /** Bloque de una calificación existente (estrellas + comentario + fecha). */
  function calificacionHtml(cal) {
    if (!cal || cal.puntuacion == null) return '';
    return '<div class="mt-1">' + estrellasHtml(cal.puntuacion) + ' <span class="text-muted small">' + cal.puntuacion + '/5</span>'
      + (cal.comentarios ? '<div class="small mt-1">' + esc(cal.comentarios) + '</div>' : '')
      + (cal.fecha_calificacion ? '<div class="text-muted" style="font-size:.75rem">' + esc(fmtDtHora(cal.fecha_calificacion)) + '</div>' : '')
      + '</div>';
  }

  function adjuntosDeEvento(adjuntos, eventoId) {
    return (adjuntos || []).filter(function (a) { return String(a.evento_id || '') === String(eventoId); });
  }
  function adjuntosSolicitud(adjuntos) {
    return (adjuntos || []).filter(function (a) { return !a.evento_id; });
  }
  function adjuntoLink(a, apiBase) {
    return '<a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="' + apiBase + 'solicitudes/serve_adjunto.php?id=' + a.id + '" target="_blank">'
      + '<i class="fas fa-file me-1"></i>' + esc(a.nombre_original || a.ruta_archivo) + '</a>';
  }

  /**
   * Detalle tipado con etiquetas amigables. Oculta ids técnicos (solicitud_id…).
   * opts.catName(id) resuelve el nombre de categoría de ticket (opcional).
   */
  function renderDetalleTipo(tipo, det, opts) {
    det = det || {}; opts = opts || {};
    var cat = opts.catName || function (id) { return id == null ? '—' : ('#' + id); };
    var L = {
      pqrs: function () { return '<b>Tipo:</b> ' + esc(det.tipo_pqrs) + '<br><b>Resumen:</b> ' + esc(det.resumen) + '<br><b>Descripción:</b> ' + esc(det.descripcion); },
      actualizar_info: function () {
        var rows = [], f = [['nombre', 'Nombre'], ['celular', 'Celular'], ['email', 'Email'], ['direccion', 'Dirección'], ['ciudad', 'Ciudad']];
        f.forEach(function (p) { if (det[p[0]]) rows.push('<div><b>' + p[1] + ':</b> ' + esc(det[p[0]]) + '</div>'); });
        return rows.join('');
      },
      redencion: function () { return '<table class="table table-sm w-auto mb-0"><tr><td>Bolsillos</td><td>' + money(det.val_bolsillos) + '</td></tr><tr><td>Incentivos</td><td>' + money(det.val_incentivos) + '</td></tr><tr><td>Comisiones</td><td>' + money(det.val_comisiones) + '</td></tr><tr><td><b>Total</b></td><td><b>' + money(det.total_redimir) + '</b></td></tr><tr><td>Medio</td><td>' + esc(det.medio_redencion || '—') + '</td></tr></table>'; },
      retiro_incentivos: function () { return '<table class="table table-sm w-auto mb-0"><tr><td>Incentivos</td><td>' + money(det.val_incentivos) + '</td></tr><tr><td>Comisiones</td><td>' + money(det.val_comisiones) + '</td></tr><tr><td><b>Total</b></td><td><b>' + money(det.total_retiro) + '</b></td></tr><tr><td>Medio</td><td>' + esc(det.medio || '—') + '</td></tr></table>'; },
      ticket: function () { return '<b>Categoría:</b> ' + esc(cat(det.categoria_id)) + '<br><b>Resumen:</b> ' + esc(det.resumen) + '<br><b>Descripción:</b> ' + esc(det.descripcion) + '<br><b>Vencimiento:</b> ' + fmtDtHora(det.fecha_vencimiento); },
      boleta: function () { var m = { qr: 'Pago por QR', credito: 'Crédito cooperativa' }[det.metodo_pago] || det.metodo_pago; return '<b>Motivo:</b> ' + esc(det.motivo || '') + (det.direccion_entrega ? '<br><b>Entrega:</b> ' + esc(det.direccion_entrega) : '') + (m ? '<br><b>Método de pago:</b> ' + esc(m) : ''); },
      certificado: function () { return '<b>Especie:</b> ' + esc(det.especie) + '<br><b>Año:</b> ' + esc(det.anio || '—'); },
      fondo: function () { return '<b>Convenio:</b> ' + esc(det.convenio_nombre || det.convenio_id || '—') + '<br><b>Opción:</b> ' + esc(det.opcion_elegida || '—') + '<br><b>Observaciones:</b> ' + esc(det.observaciones || '—') + '<br><b>Uso:</b> ' + fmtDtHora(det.fecha_uso) + '<br><b>Bloqueo hasta:</b> ' + fmtDtHora(det.bloqueado_hasta); },
      nuevo_producto: function () { return '<b>Producto:</b> ' + esc(det.producto_nombre || det.producto_id || '—') + '<br><b>Día pago:</b> ' + esc(det.dia_pago || '—') + '<br><b>Valor:</b> ' + money(det.valor); }
    };
    var fn = L[tipo];
    if (!fn) {
      var skip = { solicitud_id: 1 }, out = [];
      Object.keys(det).forEach(function (k) {
        if (skip[k]) return;
        var v = det[k];
        if (v == null || v === '') return;
        out.push('<div><b>' + esc(k) + ':</b> ' + esc(v) + '</div>');
      });
      return out.join('') || '<span class="text-muted">Sin datos.</span>';
    }
    return fn();
  }

  /**
   * Etiqueta del actor de un evento.
   * - mode 'portal': asociado -> "Tú"; staff -> "Multi"; sistema -> "Sistema".
   * - mode 'staff' : nombres[id] o "Usuario #id"; asociado -> "Asociado <id>".
   */
  function actorLabel(actorTipo, actorId, opts) {
    opts = opts || {};
    if (actorTipo === 'sistema') return 'Sistema';
    if (opts.mode === 'portal') {
      return actorTipo === 'asociado' ? 'Tú' : 'Multi';
    }
    if (!actorId) return 'Sistema';
    if (actorTipo === 'interno') return (opts.nombres && opts.nombres[actorId]) || ('Usuario #' + actorId);
    return 'Asociado ' + actorId;
  }

  /** Feed unificado desde eventos (los comentarios ya son eventos) con adjuntos por evento_id. */
  function buildActividad(eventos, adjuntos, opts) {
    opts = opts || {};
    var items = (eventos || []).map(function (e) {
      return {
        id: e.id, fecha: e.fecha, kind: e.tipo_evento, label: eventoLabel(e.tipo_evento),
        actor: actorLabel(e.actor_tipo, e.actor_id, opts),
        extra: (e.estado_anterior || e.estado_nuevo)
          ? ((e.estado_anterior ? estadoLabel(e.estado_anterior) + ' \u2192 ' : '') + (e.estado_nuevo ? estadoLabel(e.estado_nuevo) : ''))
          : '',
        texto: e.mensaje,
        adjuntos: adjuntosDeEvento(adjuntos, e.id)
      };
    }).sort(function (a, b) { return new Date(a.fecha || 0).getTime() - new Date(b.fecha || 0).getTime(); });

    // Respuesta legacy: si no hay evento 'respuesta' pero la cabecera tiene comentario_respuesta.
    var rf = opts.respuestaFallback;
    if (rf && rf.texto && !items.some(function (x) { return x.kind === 'respuesta'; })) {
      items.push({
        id: 'resp', fecha: rf.fecha, kind: 'respuesta', label: eventoLabel('respuesta'),
        actor: rf.actor || (opts.mode === 'portal' ? 'Multi' : 'Sistema'),
        extra: '', texto: rf.texto, adjuntos: []
      });
    }
    return items;
  }

  function feedHtml(items, apiBase) {
    if (!items || !items.length) return '<span class="text-muted">Sin actividad</span>';
    return '<div class="sd-feed">' + items.map(function (x) {
      return '<div class="sd-item' + (x.kind === 'comentario' ? ' sd-comentario' : '') + '">'
        + '<div class="fw-semibold small">' + esc(x.label) + (x.actor ? ' <span class="text-muted">· ' + esc(x.actor) + '</span>' : '') + '</div>'
        + '<div class="text-muted" style="font-size:.78rem">' + esc(fmtDtHora(x.fecha)) + '</div>'
        + (x.extra ? '<div class="small text-muted">' + esc(x.extra) + '</div>' : '')
        + (x.texto ? '<div class="mt-1">' + esc(x.texto) + '</div>' : '')
        + (x.adjuntos && x.adjuntos.length ? '<div class="mt-1 d-flex flex-wrap">' + x.adjuntos.map(function (a) { return adjuntoLink(a, apiBase); }).join('') + '</div>' : '')
        + '</div>';
    }).join('') + '</div>';
  }

  // Estilos del feed (inyectados una vez, idénticos en ui/ y cx/).
  (function () {
    var css = '.sd-feed{position:relative;padding-left:1.4rem}'
      + '.sd-feed::before{content:\'\';position:absolute;left:6px;top:4px;bottom:4px;width:2px;background:#e1e8f0}'
      + '.sd-item{position:relative;margin-bottom:.9rem}'
      + '.sd-item::before{content:\'\';position:absolute;left:-1.25rem;top:.35rem;width:12px;height:12px;border-radius:50%;background:var(--brand-blue,#1882CD)}'
      + '.sd-item.sd-comentario::before{background:var(--brand-yellow,#FCD329)}'
      + '.sd-stars{color:#FCD329;letter-spacing:2px}'
      + '.sd-stars i{color:#FCD329}';
    var s = document.createElement('style');
    s.textContent = css;
    document.head.appendChild(s);
  })();

  window.SolDetalle = {
    TIPOS: TIPOS, ESTADO: ESTADO, FASE: FASE, EVENTO: EVENTO,
    esc: esc, money: money, fmtDtHora: fmtDtHora, fmtDt: fmtDt,
    tipoLabel: tipoLabel, estadoLabel: estadoLabel, estadoBadge: estadoBadge, faseBadge: faseBadge, eventoLabel: eventoLabel,
    adjuntosDeEvento: adjuntosDeEvento, adjuntosSolicitud: adjuntosSolicitud, adjuntoLink: adjuntoLink,
    estrellasHtml: estrellasHtml, calificacionHtml: calificacionHtml,
    renderDetalleTipo: renderDetalleTipo, actorLabel: actorLabel, buildActividad: buildActividad, feedHtml: feedHtml
  };
})();
