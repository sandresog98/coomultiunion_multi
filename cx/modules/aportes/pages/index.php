<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Aportes';
$activeNav = 'aportes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="portal-card portal-card-glowing-border mb-3">
      <div class="card-header-brand mb-3">
        <i class="fa-solid fa-scale-balanced fa-lg"></i>
        <div><div class="fw-semibold">Total saldos a favor</div></div>
      </div>
      <div id="totalBox" class="fs-3 fw-bold" style="color:var(--cx-dark)">$0</div>
    </div>
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3">
        <i class="fa-solid fa-chart-column fa-lg"></i>
        <div><div class="fw-semibold">Resumen de aportes</div><div class="crumb">Saldos y componentes</div></div>
      </div>
      <div id="aportesBox" class="text-center text-muted py-4">Cargando…</div>
      <div id="aportesBody"></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="row g-3">
      <div class="col-12 col-sm-6 col-lg-12">
        <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= cx_url('extractos') ?>">
          <div class="mini-icon"><i class="fa-solid fa-receipt"></i></div>
          <h6>Ver extractos</h6>
          <div class="small text-muted">Consulta tus extractos</div>
        </a>
      </div>
      <div class="col-12 col-sm-6 col-lg-12">
        <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= cx_url('movimientos') ?>">
          <div class="mini-icon"><i class="fa-solid fa-list"></i></div>
          <h6>Ver movimientos</h6>
          <div class="small text-muted">Pagos y transacciones</div>
        </a>
      </div>
      <div class="col-12 col-sm-6 col-lg-12">
        <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= cx_url('aportes/retiro') ?>">
          <div class="mini-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
          <h6>Retiro de aportes</h6>
          <div class="small text-muted">Solicita un retiro</div>
        </a>
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