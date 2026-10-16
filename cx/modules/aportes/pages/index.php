<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Aportes';
$activeNav = 'aportes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="portal-card portal-card-glowing-border h-100">
      <div class="card-header-brand mb-3">
        <i class="fa-solid fa-chart-column fa-lg"></i>
        <div><div class="fw-semibold">Resumen de aportes</div><div class="crumb">Saldos y componentes</div></div>
      </div>
      <div id="aportesBox" class="text-center text-muted py-4">Cargando…</div>
      <div id="aportesBody"></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="portal-card portal-card-glowing-border h-100">
      <div class="card-header-brand mb-3">
        <i class="fa-solid fa-scale-balanced fa-lg"></i>
        <div><div class="fw-semibold">Total saldos a favor</div></div>
      </div>
      <div id="totalBox" class="fs-3 fw-bold" style="color:var(--cx-dark)">$0</div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <a class="btn btn-portal-outline btn-sm" href="<?= $CX ?>/modules/aportes/pages/extractos"><i class="fa-solid fa-receipt me-1"></i> Ver extractos</a>
        <a class="btn btn-portal-outline btn-sm" href="<?= $CX ?>/modules/aportes/pages/movimientos"><i class="fa-solid fa-list me-1"></i> Ver movimientos</a>
        <a class="btn btn-portal-gradient btn-sm" href="<?= $CX ?>/modules/aportes/pages/retiro_aporte"><i class="fa-solid fa-hand-holding-dollar me-1"></i> Retiro de aportes</a>
      </div>
    </div>
  </div>
</div>
<script>
(async function(){
  var box=document.getElementById('aportesBox'), body=document.getElementById('aportesBody');
  var r=await fetch(CX_API+'/aportes.php'); var j=await r.json();
  if(!j.ok){ box.textContent=j.error||'Error'; return; }
  var d=j.data||{};
  var rows=[
    ['Aportes ordinarios', d.aportes_ordinarios],
    ['Aportes sociales', d.aportes_sociales],
    ['Aportes totales', d.aportes_totales],
    ['Revalorizaciones', d.aportes_revalorizaciones],
    ['Plan futuro', d.plan_futuro],
    ['Bolsillos', d.bolsillos],
    ['Comisiones', d.comisiones],
    ['Incentivos', d.total_incentivos],
  ].map(function(x){return {c:x[0], v:x[1]};});
  window.cxTabla({
    el:'#aportesBody',
    defaultSort:{col:1, dir:-1},
    cols:[
      {label:'Componente', val:function(r){return r.c;}},
      {label:'Saldo', val:function(r){return money(r.v);}, class:'money'},
    ],
    filas:rows
  });
  document.getElementById('totalBox').textContent=moneyAbs(d.total_saldos_favor);
  box.classList.add('d-none');
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>