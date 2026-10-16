<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Mis créditos';
$activeNav = 'cred';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6">
    <div class="portal-card portal-card-glowing-border h-100 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="mini-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        <div>
          <div class="fs-3 fw-bold lh-1" id="statCreditos">—</div>
          <div class="small text-muted">Créditos activos</div>
        </div>
      </div>
      <div id="btnNuevaSol">
        <a class="text-decoration-none text-dark d-flex align-items-center gap-2" href="<?= cx_url('nueva-solicitud') ?>">
          <div class="mini-icon mini-icon-sm"><i class="fa-solid fa-plus"></i></div>
          <div>
            <div class="fw-semibold">Nueva solicitud</div>
            <div class="small text-muted">Solicita un nuevo crédito</div>
          </div>
        </a>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="portal-card portal-card-glowing-border h-100 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="mini-icon"><i class="fa-solid fa-list-check"></i></div>
        <div>
          <div class="fs-3 fw-bold lh-1" id="statSolicitudes">—</div>
          <div class="small text-muted">Solicitudes abiertas</div>
        </div>
      </div>
      <a class="text-decoration-none text-dark d-flex align-items-center gap-2" href="<?= cx_url('solicitar-credito') ?>">
        <div class="mini-icon mini-icon-sm"><i class="fa-solid fa-list"></i></div>
        <div>
          <div class="fw-semibold">Ver mis solicitudes</div>
          <div class="small text-muted">Consulta el estado</div>
        </div>
      </a>
    </div>
  </div>
</div>
<div id="openSol" class="d-none mb-3"></div>
<div class="row g-3">
  <div class="col-12">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3">
        <i class="fa-solid fa-file-invoice-dollar fa-lg"></i>
        <div><div class="fw-semibold">Créditos activos</div></div>
      </div>
      <div id="box" class="text-center text-muted py-4">Cargando…</div>
      <div id="body"></div>
      <div id="empty" class="d-none text-center text-muted py-4">No tienes créditos activos.</div>
    </div>
  </div>
</div>
<script>
(function init(){
  var box=document.getElementById('box'), body=document.getElementById('body'), empty=document.getElementById('empty');
  fetch(CX_API+'/creditos.php').then(function(r){return r.json();}).then(function(j){
    box.classList.add('d-none');
    if(!j.ok){ box.textContent=j.error||'Error'; return; }
    var rows=j.data||[];
    document.getElementById('statCreditos').textContent=rows.length;
    if(!rows.length){ empty.classList.remove('d-none'); }
    else { window.cxTabla({ el:'#body',
      cols:[
        {label:'Número', val:function(r){return r.numero_credito;}},
        {label:'Tipo', val:function(r){return r.tipo_prestamo;}},
        {label:'Saldo capital', val:function(r){return money(r.saldo_capital);}, class:'money'},
        {label:'Cuota', val:function(r){return moneyAbs(r.valor_cuota);}, class:'money'},
        {label:'Cuotas pend.', val:function(r){return r.cuotas_pendientes;}},
        {label:'Saldo mora', val:function(r){return money(r.saldo_mora);}, class:function(r){return r.saldo_mora>0?'money money-neg':'money';}},
        {label:'Días mora', val:function(r){return r.dias_mora;}},
        {label:'', sort:false, val:function(){return '';}, html:function(r){
          return '<a class="btn btn-sm btn-portal-outline" href="'+cxUrl('creditos/{numero}',{numero:r.numero_credito})+'">Ver</a>';
        }},
      ], filas:rows });
    }
    // Solicitud abierta -> banner + ocultar "Nueva solicitud"
    fetch(CX_API+'/solicitudes_listar.php?limit=200').then(function(r){return r.json();}).then(function(j){
      if(!j.ok) return;
      var abiertas=(j.data.rows||[]).filter(function(s){ return s.etapa_actual==='creacion'||s.etapa_actual==='revision'; });
      document.getElementById('statSolicitudes').textContent=abiertas.length;
      if(!abiertas.length) return;
      var abierta=abiertas[0];
      var os=document.getElementById('openSol');
      os.classList.remove('d-none');
      os.innerHTML='<div class="portal-card portal-card-glowing-border p-3"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2">'
        +'<div class="d-flex align-items-center gap-2"><span class="badge bg-info">Solicitud abierta</span><span class="small text-muted">'+esc(abierta.numero_solicitud)+'</span></div>'
        +'<a class="btn btn-sm btn-portal-accent" href="'+cxUrl('solicitar-credito/{id}',{id:abierta.id})+'">Ir a tu solicitud <i class="fa-solid fa-arrow-right ms-1"></i></a>'
        +'</div></div>';
      document.getElementById('btnNuevaSol').classList.add('d-none');
    });
  });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>