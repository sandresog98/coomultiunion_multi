<?php
declare(strict_types=1);
/** Simulador de crédito (B14) — página pública (fuera de ui/). Consume api/simulacion. */
require_once __DIR__ . '/../core/database.php';
$db = db();
// Ruta base a los endpoints (robusto bajo Apache o php -S).
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$appBase = rtrim(dirname($dir), '/'); // '' si la app está en la raíz, o '/multi_app_v2'
$API = $appBase . '/api/simulacion.php';
$APIPDF = $appBase . '/api/simulacion_pdf.php';
$lines = $db->query("SELECT id, nombre_credito, tasa, seguro_vida, seguro_deudores, limite_meses FROM credito_lineas WHERE estado_activo=1 AND mostrar_en_fabrica=1 ORDER BY nombre_credito")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simulador de Crédito — Coomultiunion</title>
<link rel="icon" href="<?= $appBase ?>/assets/favicons/favicon.ico" sizes="any">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $appBase ?>/assets/css/front.css">
<style>
:root{--prim:var(--brand-blue);--prim-dark:var(--brand-blue-dark);--amar:var(--brand-yellow);--rojo:var(--brand-red);}
body{background:var(--front-bg);font-family:var(--front-font)}
.sim-header{background:linear-gradient(135deg,var(--prim),var(--prim-dark));padding:14px 26px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 12px rgba(0,0,0,.15)}
.sim-header img{height:42px}
.sim-header h1{color:#fff;font-size:1.25em;font-weight:700;margin:0}
.sim-container{max-width:1400px;margin:0 auto;padding:22px 18px}
.card{border:none;border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,.07);margin-bottom:18px}
.card-header{background:linear-gradient(135deg,var(--prim),var(--prim-dark));color:#fff;font-weight:600;padding:12px 18px;border-radius:12px 12px 0 0}
.form-label{font-size:.82em;font-weight:600;color:#555;margin-bottom:4px}
.btn-calc{background:linear-gradient(135deg,var(--prim),var(--prim-dark));border:none;color:#fff;font-weight:600;padding:9px 26px;border-radius:8px}
.btn-calc:hover{color:#fff;transform:translateY(-1px);box-shadow:0 5px 14px rgba(24,130,205,.35)}
.money-input{text-align:right}
.table-amort{font-size:.86em}
.table-amort thead th{background:var(--prim-dark);color:#fff;text-align:center;padding:8px}
.table-amort tbody td{text-align:right;padding:6px 8px}
.table-amort tr.row-last{font-weight:700;background:#d4edda}
@media print{.no-print{display:none!important}}
</style>
</head>
<body class="front-body-falling">
<header class="sim-header no-print">
  <img src="<?= $appBase ?>/assets/img/logo.png" alt="Logo">
  <h1><i class="fas fa-calculator me-2"></i>Simulador de Crédito Coomultiunion</h1>
</header>
<div class="sim-container">
  <div class="card no-print">
    <div class="card-header"><i class="fas fa-sliders-h me-1"></i>Parámetros del crédito</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label"><i class="fas fa-list me-1"></i>Línea de crédito</label>
          <select class="form-select" id="selLinea"></select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Monto del crédito</label>
          <div class="input-group"><span class="input-group-text">$</span><input type="text" class="form-control money-input" id="inpMontoTxt" value="2.000.000" inputmode="numeric"></div>
        </div>
        <div class="col-md-2">
          <label class="form-label">Aportes fijos</label>
          <div class="input-group"><span class="input-group-text">$</span><input type="text" class="form-control money-input" id="inpAportesTxt" value="65.000" inputmode="numeric"></div>
        </div>
        <div class="col-md-2">
          <label class="form-label" id="lblCuotas">Número de cuotas</label>
          <input type="number" class="form-control" id="inpCuotas" value="12" min="1" max="120">
        </div>
        <div class="col-md-2">
          <label class="form-label">Tasa interés (mensual %)</label>
          <input type="number" class="form-control" id="inpTasaInt" value="—" readonly>
        </div>
        <div class="col-md-2">
          <label class="form-label">Tasa seguro (mensual %)</label>
          <input type="number" class="form-control" id="inpTasaSeg" value="—" readonly>
        </div>
        <div class="col-md-2">
          <label class="form-label">Fecha inicio</label>
          <input type="date" class="form-control" id="inpInicio">
        </div>
        <div class="col-md-2">
          <label class="form-label">Primer pago</label>
          <input type="date" class="form-control" id="inpPrimer">
        </div>
        <div class="col-md-9 d-flex align-items-end">
          <button class="btn btn-calc" onclick="calcular()"><i class="fas fa-play me-2"></i>Calcular</button>
        </div>
      </div>
      <div id="alerta" class="alert d-none mt-3 mb-0"></div>
    </div>
  </div>

  <div id="resultado" class="d-none no-print text-end mb-2">
    <button class="btn btn-outline-secondary" onclick="descargarPDF()"><i class="fas fa-file-pdf me-1"></i>Descargar PDF</button>
  </div>
  <div id="resumen" class="mt-2"></div>
  <div id="tabla"></div>
</div>

<script>
var API=<?= json_encode($API) ?>, API_PDF=<?= json_encode($APIPDF) ?>;
var LINEAS=<?= json_encode($lines, JSON_UNESCAPED_UNICODE) ?>;
var planActual=null, limiteMax=120;

function moneyFromTxt(s){return parseInt(String(s).replace(/[^\d]/g,''),10)||0;}
function money(v){return '$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function hoy(){ return new Date().toISOString().slice(0,10); }
function mes(){ const d=new Date(); d.setMonth(d.getMonth()+1); return d.toISOString().slice(0,10); }
function nextDay(iso){ const d=new Date(iso+'T00:00:00'); d.setDate(d.getDate()+1); return d.toISOString().slice(0,10); }

function initLineas(){
  const sel=document.getElementById('selLinea');
  (LINEAS||[]).forEach(l=>{
    const o=document.createElement('option');o.value=l.id;o.textContent=l.nombre_credito+' ('+Number(l.tasa).toFixed(2)+'%)';
    o.dataset.tasa=((parseFloat(l.tasa)||0));o.dataset.seguro=((parseFloat(l.seguro_vida)||0)+(parseFloat(l.seguro_deudores)||0));o.dataset.limite=l.limite_meses;
    sel.appendChild(o);
  });
  sel.addEventListener('change',()=>{ if(sel.value) aplicarLinea(); });
  if(LINEAS&&LINEAS.length){ sel.value=LINEAS[0].id; aplicarLinea(); }
}
function aplicarLinea(){
  const sel=document.getElementById('selLinea'); const o=sel.selectedOptions[0];
  if(!o){ limiteMax=120; document.getElementById('lblCuotas').textContent='Número de cuotas'; return; }
  document.getElementById('inpTasaInt').value=Number(o.dataset.tasa).toFixed(2);
  document.getElementById('inpTasaSeg').value=Number(o.dataset.seguro).toFixed(2);
  limiteMax=parseInt(o.dataset.limite,10)||120;
  document.getElementById('inpCuotas').max=limiteMax;
  document.getElementById('lblCuotas').textContent='Número de cuotas (Máx. '+limiteMax+')';
  if(parseInt(document.getElementById('inpCuotas').value,10)>limiteMax)document.getElementById('inpCuotas').value=limiteMax;
}
document.getElementById('inpMontoTxt').addEventListener('input',function(){const d=String(this.value).replace(/[^\d]/g,'');this.value=d?Number(d).toLocaleString('es-CO'):'';});
document.getElementById('inpAportesTxt').addEventListener('input',function(){const d=String(this.value).replace(/[^\d]/g,'');this.value=d?Number(d).toLocaleString('es-CO'):'';});

async function calcular(){
  const alerta=document.getElementById('alerta');alerta.classList.add('d-none');
  const monto=moneyFromTxt(document.getElementById('inpMontoTxt').value);
  const cuotas=parseInt(document.getElementById('inpCuotas').value,10)||0;
  const fechaInicio=document.getElementById('inpInicio').value||hoy();
  const primerPago=document.getElementById('inpPrimer').value||mes();
  const sel=document.getElementById('selLinea');
  const showErr=(msg)=>{alerta.className='alert alert-danger mt-3';alerta.textContent=msg;alerta.classList.remove('d-none');document.getElementById('resultado').classList.add('d-none');};
  if(!sel.value){showErr('Selecciona una línea de crédito.');return;}
  if(cuotas>limiteMax){showErr('El número de cuotas no puede superar el máximo de '+limiteMax+'.');return;}
  if(primerPago<=fechaInicio){showErr('El primer pago debe ser mayor a la fecha de inicio.');return;}
  const body={monto,aportes_fijos:moneyFromTxt(document.getElementById('inpAportesTxt').value),num_cuotas:cuotas,fecha_inicio:fechaInicio,primer_pago:primerPago,linea_credito_id:parseInt(sel.value,10)};
  try{
    const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(!j.ok){showErr(j.error||'Error');return;}
    planActual=j;
    const p=j.plan;
    document.getElementById('resumen').innerHTML=`
      <div class="row g-2 mb-2">
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><b>Cuota fija:</b> ${money(j.cuota_fija)}</div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><b>Tasa interés:</b> ${(j.tasa_interes*100).toFixed(2)}%</div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><b>Tasa seguro:</b> ${(j.tasa_seguro*100).toFixed(2)}%</div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body py-2"><b>Total:</b> ${money(p.total_general)}</div></div></div>
      </div>`;
    document.getElementById('tabla').innerHTML=`<div class="card"><div class="card-header"><i class="fas fa-table me-1"></i>Tabla de amortización</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-amort mb-0"><thead><tr><th>#</th><th>Fecha</th><th>Días</th><th>Saldo inicial</th><th>Interés</th><th>Seguro</th><th>Abono</th><th>Cuota</th><th>Aportes</th><th>Cuota total</th><th>Saldo final</th></tr></thead><tbody>`+
      p.tabla.map(f=>`<tr class="${f.saldo_final===0?'row-last':''}"><td>${f.cuota}</td><td>${f.fecha_pago}</td><td>${f.dias}</td><td>${money(f.saldo_inicial)}</td><td>${money(f.interes)}</td><td>${money(f.seguro)}</td><td>${money(f.abono_capital)}</td><td>${money(f.cuota_fija)}</td><td>${money(f.aportes)}</td><td>${money(f.cuota_total)}</td><td>${money(f.saldo_final)}</td></tr>`).join('')+
      `</tbody></table></div></div></div>`;
    document.getElementById('resultado').classList.remove('d-none');
  }catch(e){alerta.className='alert alert-danger mt-3';alerta.textContent=e.message;alerta.classList.remove('d-none');}
}
function descargarPDF(){
  if(!planActual)return;
  const f=document.createElement('form');f.method='POST';f.action=API_PDF;f.target='_blank';
  const add=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i);};
  add('monto',moneyFromTxt(document.getElementById('inpMontoTxt').value));
  add('num_cuotas',parseInt(document.getElementById('inpCuotas').value,10)||0);
  add('tasa_interes',planActual.tasa_interes);
  add('tasa_seguro',planActual.tasa_seguro);
  add('aportes_fijos',moneyFromTxt(document.getElementById('inpAportesTxt').value));
  add('fecha_inicio',document.getElementById('inpInicio').value||hoy());
  add('primer_pago',document.getElementById('inpPrimer').value||mes());
  const sel=document.getElementById('selLinea');if(sel.value)add('linea_credito_nombre',(sel.selectedOptions[0]||{}).textContent&&(sel.selectedOptions[0].textContent||'').replace(/\s*\(.*/,''));
  document.body.appendChild(f);f.submit();f.remove();
}

document.addEventListener('DOMContentLoaded',()=>{
  const ini=document.getElementById('inpInicio'), pri=document.getElementById('inpPrimer');
  ini.value=hoy(); pri.value=mes();
  pri.min=nextDay(ini.value);
  ini.addEventListener('change',()=>{ if(pri.value<=ini.value){pri.value=nextDay(ini.value);} pri.min=nextDay(ini.value); });
  initLineas();
});
</script>
</body>
</html>