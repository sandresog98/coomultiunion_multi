<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Extractos';
$activeNav = 'extractos';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-12">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3 d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2"><i class="fa-solid fa-receipt fa-lg"></i><div class="fw-semibold">Extractos</div></div>
        <div class="d-flex gap-2 flex-wrap">
          <select class="form-select form-select-sm" id="fCuenta" style="width:auto"><option value="">Todas las cuentas</option></select>
          <input type="month" id="fMes" class="form-control form-control-sm" style="width:auto">
        </div>
      </div>
      <div id="box" class="text-center text-muted py-4">Cargando…</div>
      <div id="body"></div>
      <div id="empty" class="d-none text-center text-muted py-4">No hay movimientos.</div>
    </div>
  </div>
</div>
<script>
var API=CX_API; var estado={cuenta:'',mes:''};
var fMes=document.getElementById('fMes');
(function prevMonth(){ var d=new Date(), y=d.getFullYear(), m=d.getMonth()-1; if(m<0){ y--; m=11; } var v=y+'-'+String(m+1).padStart(2,'0'); fMes.value=v; estado.mes=v; })();
fMes.addEventListener('change',function(){estado.mes=fMes.value||'';cargar();});
document.getElementById('fCuenta').addEventListener('change',function(){estado.cuenta=this.value;cargar();});
async function cargar(){
  var body=document.getElementById('body'), box=document.getElementById('box'), empty=document.getElementById('empty');
  var sp=new URLSearchParams(); if(estado.cuenta)sp.set('cuenta',estado.cuenta); if(estado.mes)sp.set('mes',estado.mes);
  var r=await fetch(API+'/extractos.php?'+sp.toString()); var j=await r.json();
  if(!j.ok){ box.textContent=j.error||'Error'; return; }
  var d=j.data;
  if(!estado.cuenta){
    document.getElementById('fCuenta').innerHTML='<option value="">Todas las cuentas</option>'+d.cuentas.map(function(c){return '<option value="'+c.cuenta+'">'+esc(c.label)+'</option>';}).join('');
  }
  box.classList.add('d-none');
  if(!d.rows.length){ body.innerHTML=''; empty.classList.remove('d-none'); return; }
  empty.classList.add('d-none');
  window.cxTabla({
    el:'#body',
    cols:[
      {label:'Fecha', val:function(r){return cxFecha(r.fecha);}},
      {label:'Cuenta', val:function(r){return r.cuenta_label;}},
      {label:'Detalle', val:function(r){return r.detall;}},
      {label:'Valor', val:function(r){return money(r.valor);}, class:function(r){return r.valor<0?'money money-neg':'money';}},
    ],
    filas:d.rows
  });
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>