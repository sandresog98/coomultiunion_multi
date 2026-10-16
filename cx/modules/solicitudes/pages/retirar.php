<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Retiros';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card">
  <div class="card-header-brand"><i class="fa-solid fa-money-bill-transfer"></i><div class="fw-semibold">Retiros de saldos</div></div>
  <div class="p-3"><div id="saldos" class="text-center text-muted py-3">Cargando saldos…</div></div>
</div>

<div class="row g-3 mt-0">
  <div class="col-lg-6">
    <div class="portal-card h-100">
      <div class="card-header-brand"><i class="fa-solid fa-piggy-bank"></i><div class="fw-semibold">Redención de bolsillos</div></div>
      <div class="p-3">
        <div class="text-muted small mb-2">El movimiento lo registra el sistema externo (Sifone). El área valida la disponibilidad.</div>
        <div class="mb-2"><label class="form-label small text-muted mb-0">Monto a redimir</label>
          <input type="number" class="portal-input form-control" id="rBols" min="0"></div>
        <div class="mb-2"><label class="form-label small text-muted mb-0">Medio de redención</label>
          <select class="portal-select form-select" id="rMedio">
            <option value="retiro_efectivo">Retiro efectivo</option><option value="cruce_cartera">Cruce cartera</option>
            <option value="abono_aportes">Abono aportes</option><option value="convenio_recreacion">Convenio recreación</option>
            <option value="convenio_tecnologia">Convenio tecnología</option></select></div>
        <button class="btn btn-portal-gradient" onclick="enviarBolsillos()">Solicitar redención</button><div id="msgB" class="mt-2 small"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="portal-card h-100">
      <div class="card-header-brand"><i class="fa-solid fa-coins"></i><div class="fw-semibold">Retiro de incentivos</div></div>
      <div class="p-3">
        <div class="text-muted small mb-2">Incluye incentivos y comisiones acumuladas.</div>
        <div class="row g-2 mb-2"><div class="col-6"><label class="form-label small text-muted mb-0">Incentivos</label><input type="number" id="rInc" class="portal-input form-control" min="0"></div>
        <div class="col-6"><label class="form-label small text-muted mb-0">Comisiones</label><input type="number" id="rCom" class="portal-input form-control" min="0"></div></div>
        <div class="mb-2"><label class="form-label small text-muted mb-0">Medio</label><select class="portal-select form-select" id="rMedioI"><option value="abono_aportes">Abono aportes</option><option value="retiro_efectivo">Retiro efectivo</option><option value="transferencia">Transferencia</option></select></div>
        <button class="btn btn-portal-gradient" onclick="enviarIncentivos()">Solicitar retiro</button><div id="msgI" class="mt-2 small"></div>
      </div>
    </div>
  </div>
</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>,XAPI=<?php echo json_encode($API); ?>;
function money(v){return '$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
fetch(XAPI+'/aportes.php').then(r=>r.json()).then(j=>{
  if(!j.ok||!j.data){document.getElementById('saldos').textContent='Sin saldos disponibles';return;}
  var s=j.data;
  document.getElementById('saldos').innerHTML=
    '<div class="row text-center"><div class="col-4"><div class="text-muted small">Bolsillos</div><b>'+money(s.bolsillos)+'</b></div>'+
    '<div class="col-4"><div class="text-muted small">Incentivos</div><b>'+money(s.aportes_incentivos)+'</b></div>'+
    '<div class="col-4"><div class="text-muted small">Comisiones</div><b>'+money(s.comisiones)+'</b></div></div>';
  document.getElementById('rInc').max=s.aportes_incentivos||0;
  document.getElementById('rCom').max=s.comisiones||0;
});
function post(tipo,detalle,msg){msg.className='text-muted small';msg.textContent='Enviando…';fetch(PAPI+'/crear.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tipo:tipo,detalle:detalle})}).then(r=>r.json()).then(j=>{if(!j.ok){msg.className='text-danger small';msg.textContent=j.error;return;}msg.className='text-success small';msg.textContent='Solicitud '+j.data.solicitud.numero_radicado+' enviada.';});}
function enviarBolsillos(){var m=document.getElementById('msgB');var v=parseFloat(document.getElementById('rBols').value||0);if(v<=0){m.className='text-danger small';m.textContent='Ingrese un monto.';return;}post('redencion',{val_bolsillos:v,val_incentivos:0,val_comisiones:0,total_redimir:v,medio_redencion:document.getElementById('rMedio').value},m);}
function enviarIncentivos(){var m=document.getElementById('msgI');var i=parseFloat(document.getElementById('rInc').value||0),c=parseFloat(document.getElementById('rCom').value||0);if(i<=0&&c<=0){m.className='text-danger small';m.textContent='Ingrese al menos un monto.';return;}post('retiro_incentivos',{val_incentivos:i,val_comisiones:c,total_retiro:i+c,medio:document.getElementById('rMedioI').value},m);}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>