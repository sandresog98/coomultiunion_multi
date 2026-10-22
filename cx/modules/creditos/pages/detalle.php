<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$numero = trim((string) ($_GET['numero'] ?? ''));
if ($numero === '') { header('Location: ' . cx_url('creditos')); exit; }
$pageTitle = 'Detalle crédito ' . $numero;
$activeNav = 'cred';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="mb-2"><a class="btn btn-sm btn-portal-outline" href="<?= cx_url('creditos') ?>"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a></div>
<div id="box" class="text-center text-muted py-5">Cargando…</div>
<script>
(function(){
  var box=document.getElementById('box');
  function dato(label,val){return '<div class="dato-item"><div class="dato-label">'+esc(label)+'</div><div class="dato-val">'+esc(val==null||val===''?'—':val)+'</div></div>';}
  function kpi(icon,label,val,cls){return '<div class="col-6 col-md-4"><div class="portal-card portal-card-glowing-border portal-mini h-100">'
    +'<div class="mini-icon"><i class="fa-solid '+icon+'"></i></div>'
    +'<h6 class="fs-4 fw-bold mb-0'+(cls?' '+cls:'')+'">'+val+'</h6>'
    +'<div class="small text-muted">'+esc(label)+'</div></div></div>';}
  fetch(CX_API+'/creditos_detalle.php?numero='+encodeURIComponent('<?= $numero ?>')).then(function(r){return r.json();}).then(function(j){
    if(!j.ok){ box.textContent=j.error||'Error'; return; }
    var d=j.data, enMora=d.saldo_mora>0;
    var pagadas=Number(d.cuotas_pagadas)||0, totales=Number(d.cuotas_totales)||0;
    var pct=totales>0?Math.round(pagadas/totales*100):0;
    var plazo=(Number(d.plazo)>0? d.plazo+' meses':'—');
    var tasa=(Number(d.tasa_interes)>0? d.tasa_interes+' %':'—');
    var estadoBadge='<span class="badge badge-etapa '+(enMora?'bg-danger':'bg-success')+'">'+(enMora?'En mora':'Al día')+'</span>';
    var h='';
    // Encabezado
    h+='<div class="portal-card portal-card-glowing-border d-flex flex-wrap align-items-center gap-2 mb-3">'
      +'<div class="card-header-brand me-auto"><i class="fa-solid fa-file-invoice-dollar fa-lg"></i>'
        +'<div><div class="fw-semibold fs-5">Crédito N.º '+esc(d.numero_credito)+'</div><div class="crumb">'+esc(d.tipo_prestamo||'')+'</div></div></div>'
      +estadoBadge
      +(d.estado_credito?'<span class="badge badge-etapa bg-secondary">'+esc(d.estado_credito)+'</span>':'')
      +'</div>';
    // Banda de KPIs
    h+='<div class="row g-3 mb-3">'
      +kpi('fa-coins','Saldo capital',money(d.saldo_capital))
      +kpi('fa-scale-balanced','Saldo total',money(d.saldo_total))
      +kpi('fa-triangle-exclamation','Días mora',Number(d.dias_mora)||0, enMora?'text-danger':'')
      +'</div>';
    // Detalle + panel lateral
    h+='<div class="row g-3">';
    h+='<div class="col-lg-8"><div class="portal-card portal-card-glowing-border h-100">'
      +'<div class="card-header-brand mb-2"><i class="fa-solid fa-circle-info fa-lg"></i><div class="fw-semibold">Información general</div></div>'
      +'<div class="portal-datos-grid">'
        +dato('Número',d.numero_credito)+dato('Tipo',d.tipo_prestamo)+dato('Fecha inicio',cxFecha(d.fecha_emision))
        +dato('Vencimiento',cxFecha(d.fecha_vencimiento))+dato('Tasa de interés',tasa)+dato('Plazo',plazo)
      +'</div>'
      +'<hr class="my-3">'
      +'<div class="card-header-brand mb-2"><i class="fa-solid fa-chart-pie fa-lg"></i><div class="fw-semibold">Estados financieros</div></div>'
      +'<div class="portal-datos-grid">'
        +dato('Desembolso inicial',money(d.desembolso_inicial))+dato('Saldo capital',money(d.saldo_capital))
        +dato('Saldo intereses',money(d.saldo_intereses))+dato('Saldo total',money(d.saldo_total))
        +dato('Pago mínimo',money(d.pago_minimo))
        +(Number(d.monto_cobranza)>0?dato('Monto cobranza',money(d.monto_cobranza)):'')
      +'</div>'
      +'<hr class="my-3">'
      +'<div class="card-header-brand mb-2"><i class="fa-solid fa-'+(enMora?'triangle-exclamation':'circle-check')+' fa-lg"></i><div class="fw-semibold">Estado de pago</div></div>'
      +'<div class="portal-datos-grid">'
        +dato('Estado',enMora?'En mora':'Al día')+dato('Días mora',Number(d.dias_mora)||0)
        +dato('Saldo mora',money(d.saldo_mora))+dato('Próximo pago',cxFecha(d.fecha_pago))
      +'</div>'
    +'</div></div>';
    h+='<div class="col-lg-4"><div class="portal-card portal-card-glowing-border h-100">'
      +'<div class="card-header-brand mb-3"><i class="fa-solid fa-gauge-high fa-lg"></i><div class="fw-semibold">Estado y avance</div></div>'
      +'<div class="mb-3">'+estadoBadge+'</div>'
      +'<div class="d-flex justify-content-between small mb-1"><span class="text-muted">Cuotas pagadas</span><span class="fw-semibold">'+pagadas+' / '+totales+'</span></div>'
      +'<div class="progress mb-3" style="height:10px"><div class="progress-bar '+(enMora?'bg-danger':'bg-success')+'" role="progressbar" style="width:'+pct+'%"></div></div>'
      +'<div class="d-flex justify-content-between border-bottom py-2 small"><span class="text-muted">Próximo pago</span><span class="fw-semibold">'+esc(cxFecha(d.fecha_pago))+'</span></div>'
      +'<div class="d-flex justify-content-between border-bottom py-2 small"><span class="text-muted">Tasa de interés</span><span class="fw-semibold">'+esc(tasa)+'</span></div>'
      +'<div class="d-flex justify-content-between py-2 small"><span class="text-muted">Plazo</span><span class="fw-semibold">'+esc(plazo)+'</span></div>'
    +'</div></div>';
    h+='</div>';
    box.className='';
    box.innerHTML=h;
  }).catch(function(){ box.innerHTML='<div class="alert alert-danger">Error de conexión.</div>'; });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
