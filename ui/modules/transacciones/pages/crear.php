<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.transacciones');
$canCrearTrx = ui_accion('transacciones', 'crear', $staff['rol'] ?? '');
$pageTitle = 'Crear transacción';
$currentPage = 'transacciones_crear';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>

<style>
/* ── Step indicator ── */
.step-indicator { display:flex; align-items:center; justify-content:center; gap:0; user-select:none; }
.step-item { display:flex; flex-direction:column; align-items:center; gap:0.35rem; min-width:90px; }
.step-circle { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem; background:#e9ecef; color:#adb5bd; border:2px solid #dee2e6; transition:all .35s ease; }
.step-connector { width:56px; height:2px; background:#dee2e6; margin:0 0.25rem 1.2rem; transition:background .35s ease; }
.step-label { font-size:.72rem; color:#adb5bd; font-weight:500; text-align:center; transition:color .35s ease; }
.step-item.active .step-circle { background:var(--brand-blue,#1882CD); color:#fff; border-color:var(--brand-blue,#1882CD); box-shadow:0 0 0 4px rgba(24,130,205,.18); }
.step-item.active .step-label { color:var(--brand-blue-dark,#11468A); font-weight:700; }
.step-item.done .step-circle { background:#198754; color:#fff; border-color:#198754; }
.step-item.done .step-label { color:#198754; }
.step-connector.done { background:#198754; }

/* ── Card reveal animation ── */
.card-reveal { animation: fadeSlideIn .4s ease; }
@keyframes fadeSlideIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

/* ── Asociado selector ── */
.sel-row { cursor:pointer; transition:background .15s ease; border-left:3px solid transparent; }
.sel-row:hover { background:#eef5fb !important; border-left-color:var(--brand-blue,#1882CD); }
.sel-row.selected { background:#d9ecfa !important; border-left-color:var(--brand-blue-dark,#11468A); }

/* ── Asociado info metric cards ── */
.metric-card { background:#fff; border-radius:10px; padding:.85rem; border:1px solid #e9ecef; transition:box-shadow .2s; }
.metric-card:hover { box-shadow:0 2px 12px rgba(0,0,0,.06); }
.metric-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }

/* ── Rubros ── */
.rubro-row { border-left:4px solid; border-radius:6px; background:#fff; transition:box-shadow .15s; }
.rubro-row:hover { box-shadow:0 2px 8px rgba(0,0,0,.06); }
.rubro-row.mora { border-left-color:var(--brand-red,#EB3031); }
.rubro-row.credito { border-left-color:#f59e0b; }
.rubro-row.producto { border-left-color:var(--brand-blue,#1882CD); }
.rubro-input { width:130px !important; text-align:right; font-weight:600; }

/* ── Progress bar ── */
.tx-progress { height:10px; border-radius:5px; transition:width .3s ease; }

/* ── Form section divider ── */
.form-section { border-top:1px solid #e9ecef; padding-top:1rem; margin-top:1rem; }
.form-section-title { font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; color:#6c757d; font-weight:600; margin-bottom:.75rem; }

/* ── Empty state ── */
.empty-state { border:2px dashed #dee2e6; border-radius:14px; }
.empty-state i { opacity:.12; }

/* ── Historial accordion ── */
.hist-item { border-bottom:1px solid #e9ecef; transition:background .15s; }
.hist-item:hover { background:#f8f9fa; }
.hist-header { cursor:pointer; padding:.55rem .75rem; }
.hist-header[aria-expanded="true"] { background:#eef5fb; }
.hist-chevron { transition:transform .25s ease; }
.hist-header[aria-expanded="true"] .hist-chevron { transform:rotate(180deg); }
</style>

<!-- ── Step indicator ── -->
<div class="step-indicator mb-4">
  <div class="step-item active" id="step1">
    <div class="step-circle">1</div>
    <div class="step-label">Buscar asociado</div>
  </div>
  <div class="step-connector" id="conn1"></div>
  <div class="step-item" id="step2">
    <div class="step-circle">2</div>
    <div class="step-label">Revisar y asignar</div>
  </div>
  <div class="step-connector" id="conn2"></div>
  <div class="step-item" id="step3">
    <div class="step-circle">3</div>
    <div class="step-label">Confirmar</div>
  </div>
</div>

<div class="row g-4">
  <!-- ── Columna izquierda ── -->
  <div class="col-md-5">
    <div class="card">
      <div class="card-header brand"><i class="fas fa-search me-2"></i>Seleccionar asociado</div>
      <div class="card-body">
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="selQ" placeholder="Buscar por cédula o nombre…">
          <button class="btn btn-outline-secondary btn-sm" type="button" onclick="document.getElementById('selQ').value='';selState.q='';selState.page=0;cargarSelAsociados();"><i class="fas fa-times"></i></button>
        </div>
        <small class="text-muted" id="selInfoTotal"></small>
        <div class="mt-2" id="selList">
          <div class="text-center text-muted small py-3">Cargando…</div>
        </div>
        <nav id="selPaginacion" class="d-flex justify-content-center mt-2"></nav>
      </div>
    </div>

    <div id="aInfo" class="card card-reveal mt-3 d-none">
      <div class="card-header brand"><i class="fas fa-user me-2"></i><span id="aNombre"></span></div>
      <div class="card-body p-3">
        <div class="row g-2">
          <div class="col-6">
            <div class="metric-card d-flex align-items-center gap-2">
              <div class="metric-icon bg-success bg-opacity-10 text-success"><i class="fas fa-coins"></i></div>
              <div><small class="text-muted d-block">Saldos a favor</small><strong class="text-success" id="aSaldos">—</strong></div>
            </div>
          </div>
          <div class="col-6">
            <div class="metric-card d-flex align-items-center gap-2">
              <div class="metric-icon bg-info bg-opacity-10 text-info"><i class="fas fa-file-invoice"></i></div>
              <div><small class="text-muted d-block">Créditos activos</small><strong id="aCreditos">—</strong></div>
            </div>
          </div>
          <div class="col-6">
            <div class="metric-card d-flex align-items-center gap-2">
              <div class="metric-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-calendar-check"></i></div>
              <div><small class="text-muted d-block">Producto mensual</small><strong id="aProdMensual">—</strong></div>
            </div>
          </div>
          <div class="col-6">
            <div class="metric-card d-flex align-items-center gap-2">
              <div class="metric-icon bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-triangle"></i></div>
              <div><small class="text-muted d-block">Total mora</small><strong class="text-danger" id="aMora">—</strong></div>
            </div>
          </div>
        </div>
        <div class="text-muted small mt-2">Cédula: <b id="aCedula"></b></div>
      </div>
    </div>

  </div>

  <!-- ── Columna derecha ── -->
  <div class="col-md-7">
    <div id="emptyState" class="empty-state d-flex flex-column align-items-center justify-content-center py-5 text-muted">
      <i class="fas fa-hand-pointer display-2 mb-3"></i>
      <span class="fw-semibold">Seleccione un asociado para comenzar</span>
      <small>Busque y elija un asociado en el panel izquierdo</small>
    </div>

    <div class="card card-reveal mt-3 d-none" id="cardRubros">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-check me-2"></i>Rubros a pagar</span>
        <span class="badge text-bg-light" id="rubrosCount"></span>
      </div>
      <div class="card-body p-2" id="rubrosList"></div>
    </div>

    <div class="card card-reveal mt-3 d-none" id="cardPago">
      <div class="card-header brand"><i class="fas fa-cash-register me-2"></i>Nueva transacción</div>
      <div class="card-body">
        <div id="txAlert" class="alert d-none py-2 mb-3"></div>

        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small fw-semibold">Origen</label>
            <select class="form-select form-select-sm" id="txOrigen" onchange="cargarPagos()">
              <option value="pse">PSE</option><option value="confiar">Confiar</option>
            </select>
          </div>
          <div class="col-md-5"><label class="form-label small fw-semibold">Pago</label>
            <select class="form-select form-select-sm" id="txPagoId" onchange="onPagoChange()"><option value="">Seleccione…</option></select>
          </div>
          <div class="col-md-3"><label class="form-label small fw-semibold">Recibo caja</label>
            <input class="form-control form-control-sm" id="txRecibo" placeholder="Nº recibo">
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-title">Valor</div>
          <div class="row g-2 align-items-end">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Valor total <span class="text-danger">*</span></label>
              <input type="text" inputmode="numeric" pattern="[0-9.]*" autocomplete="off" class="form-control form-control-sm fw-bold" id="txValorTotal" placeholder="0" readonly>
            </div>
            <div class="col-md-6">
              <div class="form-text mb-0">Disponible: <b id="txDisponible">—</b></div>
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-title">Asignado / Disponible</div>
          <div class="bg-light rounded-3 p-3 mb-2">
            <div class="d-flex justify-content-between small mb-1">
              <span>Asignado</span><b id="txTotalAsignado">$0</b>
            </div>
            <div class="progress mb-1" style="height:10px">
              <div class="progress-bar tx-progress" id="txProgressBar" style="width:0%"></div>
            </div>
            <div class="d-flex justify-content-between small">
              <span class="text-muted">Total rubros: <b id="txTotalRubros">$0</b></span>
              <span id="txRestanteText"></span>
            </div>
          </div>
        </div>

        <?php if ($canCrearTrx): ?>
        <button class="btn btn-brand w-100" id="btnCrear" onclick="crearTransaccion()">
          <i class="fas fa-save me-2"></i><span id="btnCrearText">Crear transacción</span>
          <span class="spinner-border spinner-border-sm d-none" id="btnCrearSpinner"></span>
        </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="card card-reveal mt-3 d-none" id="cardHistorial">
      <div class="card-header brand"><i class="fas fa-history me-2"></i>Historial de transacciones</div>
      <div class="card-body p-0" id="histContent">
        <div class="text-center text-muted small py-3">Sin transacciones</div>
      </div>
    </div>
  </div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
let asociadoCedula = '', rubros = {creditos:[],mora:[],productos:[]}, detallesTx = [], pagosDisponibles = [], pagoRestante = 0;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtNum(v){let n=Math.round(parseFloat(v)||0);return n.toLocaleString('es-CO',{maximumFractionDigits:0});}
function parseNum(v){return parseInt(String(v??'').replace(/\D/g,''))||0;}
function fmtNumInput(el){
  let val=el.value.replace(/\D/g,'');
  let num=parseInt(val)||0;
  let pos=el.selectionStart;
  let digBefore=el.value.substring(0,pos).replace(/\D/g,'').length;
  el.value=num.toLocaleString('es-CO',{maximumFractionDigits:0});
  let d=0, np=0;
  for(let i=0;i<el.value.length;i++){
    if(/\d/.test(el.value[i])){d++;if(d>digBefore)break;}
    np=i+1;
  }
  el.setSelectionRange(np,np);
}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleDateString('es-CO');}
async function apicall(p,o){let r=await fetch(API+p,o),j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

function revealCard(el){
  if(!el.classList.contains('d-none')) return;
  el.classList.remove('d-none');
  el.classList.add('card-reveal');
  el.addEventListener('animationend',function h(){el.classList.remove('card-reveal');el.removeEventListener('animationend',h);});
}

function setStep(n){
  for(let i=1;i<=3;i++){
    let s=document.getElementById('step'+i);
    s.classList.remove('active','done');
    if(i<n) s.classList.add('done');
    if(i===n) s.classList.add('active');
  }
  if(n>=2){document.getElementById('conn1').classList.add('done');}
  if(n>=3){document.getElementById('conn2').classList.add('done');}
  if(n<=1){document.getElementById('conn1').classList.remove('done');document.getElementById('conn2').classList.remove('done');}
  if(n<=2){document.getElementById('conn2').classList.remove('done');}
}

async function buscarAsociado(){
  let c=asociadoCedula;if(!c)return;
  try{
    let d=await apicall('asociados/ficha?cedula='+encodeURIComponent(c));
    let m=d.productos||{},p=d.persona||{};asociadoCedula=c;
    document.getElementById('aNombre').textContent=p.nombre||'';
    document.getElementById('aCedula').textContent=c;
    document.getElementById('aSaldos').textContent=money(m.total_saldos_favor||0);
    document.getElementById('aCreditos').textContent=(m.total_creditos||0);
    document.getElementById('aProdMensual').textContent=money(m.producto_mensual_total||0);
    document.getElementById('aMora').textContent=money(m.total_mora||0);
    revealCard(document.getElementById('aInfo'));
    rubros=await apicall('transacciones/crear?cedula='+encodeURIComponent(c));renderRubros();cargarPagos();
    revealCard(document.getElementById('cardRubros'));
    revealCard(document.getElementById('cardPago'));
    revealCard(document.getElementById('cardHistorial'));
    document.getElementById('emptyState').classList.add('d-none');
    setStep(2);
    await cargarHistorial(0);
  }catch(e){alert(e.message);}
}

function renderRubros(){
  let h='';detallesTx=[];
  if(rubros.mora.length){
    h+='<div class="mb-3"><span class="badge text-bg-danger mb-2">Mora</span>';
    rubros.mora.forEach(m=>{
      h+=`<div class="rubro-row mora d-flex align-items-center gap-2 px-3 py-2 mb-1"><div class="flex-grow-1"><small class="fw-semibold">Crédito ${esc(m.presta)}</small><small class="text-muted ms-2">${money(m.sdomor)} · ${esc(m.diav)} días</small></div><input type="text" inputmode="numeric" pattern="[0-9.]*" autocomplete="off" class="form-control form-control-sm rubro-input" value="${fmtNum(m.sdomor)}" data-tipo="credito_mora" data-ref="${esc(m.presta)}" data-recom="${m.sdomor||0}" oninput="fmtNumInput(this);syncDetalles()"></div>`;
      detallesTx.push({tipo_rubro:'credito_mora',referencia_credito:m.presta,descripcion:'Mora crédito '+m.presta,valor_recomendado:parseFloat(m.sdomor||0),valor_asignado:parseFloat(m.sdomor||0)});
    });
    h+='</div>';
  }
  if(rubros.creditos.length){
    h+='<div class="mb-3"><span class="badge text-bg-warning mb-2">Cuotas crédito</span>';
    rubros.creditos.forEach(c=>{
      if(!rubros.mora.some(m=>m.presta===c.numero)){
        h+=`<div class="rubro-row credito d-flex align-items-center gap-2 px-3 py-2 mb-1"><div class="flex-grow-1"><small class="fw-semibold">Crédito ${esc(c.numero)}</small><small class="text-muted ms-2">${esc(c.tipopr)}</small></div><input type="text" inputmode="numeric" pattern="[0-9.]*" autocomplete="off" class="form-control form-control-sm rubro-input" value="${fmtNum(c.cuota)}" data-tipo="credito" data-ref="${esc(c.numero)}" data-recom="${c.cuota||0}" oninput="fmtNumInput(this);syncDetalles()"></div>`;
        detallesTx.push({tipo_rubro:'credito',referencia_credito:c.numero,descripcion:'Cuota crédito '+c.numero,valor_recomendado:parseFloat(c.cuota||0),valor_asignado:parseFloat(c.cuota||0)});
      }
    });
    h+='</div>';
  }
  if(rubros.productos.length){
    h+='<div class="mb-3"><span class="badge text-bg-info mb-2">Productos</span>';
    rubros.productos.forEach(p=>{
      h+=`<div class="rubro-row producto d-flex align-items-center gap-2 px-3 py-2 mb-1"><div class="flex-grow-1"><small class="fw-semibold">${esc(p.nombre)}</small></div><input type="text" inputmode="numeric" pattern="[0-9.]*" autocomplete="off" class="form-control form-control-sm rubro-input" value="${fmtNum(p.monto)}" data-tipo="producto" data-ref="${p.id}" data-recom="${p.monto||0}" oninput="fmtNumInput(this);syncDetalles()"></div>`;
      detallesTx.push({tipo_rubro:'producto',producto_id:p.id,descripcion:p.nombre,valor_recomendado:parseFloat(p.monto||0),valor_asignado:parseFloat(p.monto||0)});
    });
    h+='</div>';
  }
  document.getElementById('rubrosList').innerHTML=h||'<div class="text-center text-muted small py-3">Sin rubros pendientes</div>';
  let total=detallesTx.reduce((s,d)=>s+d.valor_recomendado,0);
  document.getElementById('rubrosCount').textContent=detallesTx.length+' rubro'+(detallesTx.length!==1?'s':'');
  document.getElementById('txValorTotal').value=fmtNum(total);
  syncDetalles();
}

function syncDetalles(){
  document.querySelectorAll('#rubrosList input').forEach(inp=>{
    let idx=detallesTx.findIndex(d=>inp.dataset.tipo==='producto'?d.producto_id==parseInt(inp.dataset.ref):d.tipo_rubro===inp.dataset.tipo&&d.referencia_credito===inp.dataset.ref);
    if(idx>=0) detallesTx[idx].valor_asignado=parseNum(inp.value);
  });
  updateProgress();
}

function updateProgress(){
  let asignado=detallesTx.reduce((s,d)=>s+d.valor_asignado,0);
  let totalRubros=detallesTx.reduce((s,d)=>s+d.valor_recomendado,0);
  let restante=pagoRestante-asignado;
  let pct=pagoRestante>0?Math.min(100,(asignado/pagoRestante)*100):(totalRubros>0?Math.min(100,(asignado/totalRubros)*100):0);

  document.getElementById('txTotalAsignado').textContent=money(asignado);
  document.getElementById('txTotalRubros').textContent=money(pagoRestante);
  document.getElementById('txProgressBar').style.width=pct+'%';

  let bar=document.getElementById('txProgressBar');
  bar.className='progress-bar tx-progress';
  if(restante<0){bar.classList.add('bg-danger');}
  else if(restante===0){bar.classList.add('bg-success');}
  else if(pct>=90){bar.classList.add('bg-info');}
  else{bar.classList.add('bg-primary');}

  let rtEl=document.getElementById('txRestanteText');
  if(!pagoRestante){rtEl.textContent='';}
  else if(restante<0){rtEl.innerHTML='<span class="text-danger fw-bold">Excede en '+money(Math.abs(restante))+'</span>';}
  else if(restante===0){rtEl.innerHTML='<span class="text-success fw-bold">Completo</span>';}
  else{rtEl.innerHTML='<span class="text-muted">Disponible: '+money(restante)+'</span>';}
}

async function cargarPagos(){
  try{
    let t=document.getElementById('txOrigen').value;pagosDisponibles=await apicall('transacciones/listar_pagos?tipo='+t);
    let sel=document.getElementById('txPagoId');sel.innerHTML='<option value="">Seleccione…</option>'+pagosDisponibles.map(p=>`<option value="${esc(p.id)}" data-restante="${p.restante}">${esc(p.label)} · ${money(p.restante)} disp.</option>`).join('');sel.selectedIndex=0;
    document.getElementById('txDisponible').textContent='—';if(!asociadoCedula)return;syncDetalles();
  }catch(e){alert(e.message);}
}

function onPagoChange(){
  let sel=document.getElementById('txPagoId'),opt=sel.options[sel.selectedIndex];
  if(opt&&opt.dataset.restante){
    pagoRestante=parseFloat(opt.dataset.restante);
    document.getElementById('txDisponible').textContent=money(pagoRestante);
    document.getElementById('txValorTotal').value=fmtNum(Math.min(pagoRestante,detallesTx.reduce((s,d)=>s+d.valor_recomendado,0)));
    syncDetalles();
  }else{
    pagoRestante=0;
    document.getElementById('txDisponible').textContent='—';
  }
}

async function crearTransaccion(){
  if(!asociadoCedula)return alert('Busque un asociado primero');
  let pagoId=document.getElementById('txPagoId').value;if(!pagoId)return alert('Seleccione un pago');
  let origen=document.getElementById('txOrigen').value;
  let data={cedula:asociadoCedula,origen,recibo_caja:document.getElementById('txRecibo').value.trim(),valor_total:parseNum(document.getElementById('txValorTotal').value),detalles:detallesTx.filter(d=>d.valor_asignado>0)};
  if(origen==='pse')data.pse_id=pagoId;else data.confiar_id=pagoId;
  if(!data.valor_total||!data.detalles.length)return alert('Asigne valores a los rubros');

  let btn=document.getElementById('btnCrear');
  let btnText=document.getElementById('btnCrearText');
  let btnSpinner=document.getElementById('btnCrearSpinner');
  btn.disabled=true;btnText.textContent='Creando…';btnSpinner.classList.remove('d-none');

  try{
    await apicall('transacciones/crear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    let alert=document.getElementById('txAlert');alert.className='alert alert-success py-2';alert.textContent='Transacción creada exitosamente';alert.classList.remove('d-none');
    setStep(3);
    await cargarHistorial(0);cargarPagos();
    setTimeout(()=>{alert.classList.add('d-none');},4000);
  }catch(e){
    let alert=document.getElementById('txAlert');alert.className='alert alert-danger py-2';alert.textContent=e.message;alert.classList.remove('d-none');
  }finally{
    btn.disabled=false;btnText.textContent='Crear transacción';btnSpinner.classList.add('d-none');
  }
}

async function cargarHistorial(page){
  if(!asociadoCedula)return;
  if(page===undefined) page=histState.page;
  else histState.page=page;
  let offset=page*HIST_PAGE_SIZE;
  try{
    let d=await apicall('transacciones/listar?cedula='+encodeURIComponent(asociadoCedula)+'&limit='+HIST_PAGE_SIZE+'&offset='+offset);
    let rows=d.rows||[], total=d.total||0;
    if(!rows.length){
      if(page>0){histState.page--;return cargarHistorial();}
      document.getElementById('histContent').innerHTML='<div class="text-center text-muted small py-3">Sin transacciones</div>';
      return;
    }
    let h='<div class="accordion" id="histAccordion">';
    rows.forEach((tx,i)=>{
      let det=tx.detalles.map(d=>`<tr><td><span class="badge text-bg-light">${esc(d.tipo_rubro)}</span></td><td>${esc(d.producto_nombre||d.descripcion||d.referencia_credito||d.producto_id||'—')}</td><td class="text-end fw-semibold">${money(d.valor_asignado)}</td></tr>`).join('');
      h+=`<div class="hist-item">
        <div class="hist-header d-flex align-items-center justify-content-between" data-bs-toggle="collapse" data-bs-target="#histCollapse${i}" aria-expanded="false">
          <div class="d-flex align-items-center gap-3">
            <span class="badge text-bg-secondary fw-normal">#${tx.id}</span>
            <span class="badge text-bg-light">${esc(tx.origen)}</span>
            <small class="text-muted">${esc(tx.pse_id||tx.confiar_id||'')}</small>
          </div>
          <div class="d-flex align-items-center gap-2">
            <small class="fw-semibold">${money(tx.valor_total)}</small>
            <small class="text-muted">${fmtDt(tx.fecha_creacion)}</small>
            <button class="btn btn-sm btn-outline-danger ms-1" onclick="event.stopPropagation();eliminarTx(${tx.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
            <i class="fas fa-chevron-down hist-chevron text-muted small"></i>
          </div>
        </div>
        <div class="collapse" id="histCollapse${i}" data-bs-parent="#histAccordion">
          <div class="px-3 pb-2">
            ${tx.recibo_caja?`<div class="small text-muted mb-2">Recibo caja: <b>${esc(tx.recibo_caja)}</b></div>`:''}
            <table class="table table-sm small mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Referencia</th><th class="text-end">Valor</th></tr></thead><tbody>${det}</tbody></table>
          </div>
        </div>
      </div>`;
    });
    h+='</div>';
    let N=Math.ceil(total/HIST_PAGE_SIZE);
    if(N>1){
      h+='<nav class="d-flex justify-content-between align-items-center mt-2 px-2 pb-2"><small class="text-muted">'+`Mostrando ${offset+1}-${Math.min(offset+HIST_PAGE_SIZE,total)} de ${total}`+'</small><ul class="pagination pagination-sm mb-0">';
      if(page>0) h+=`<li class="page-item"><a class="page-link" href="#" onclick="histGoPage(${page-1});return false;">&laquo;</a></li>`;
      for(let p=0;p<N;p++) h+=p===page?`<li class="page-item active"><span class="page-link">${p+1}</span></li>`:`<li class="page-item"><a class="page-link" href="#" onclick="histGoPage(${p});return false;">${p+1}</a></li>`;
      if(page<N-1) h+=`<li class="page-item"><a class="page-link" href="#" onclick="histGoPage(${page+1});return false;">&raquo;</a></li>`;
      h+='</ul></nav>';
    }
    document.getElementById('histContent').innerHTML=h;
  }catch(e){console.error(e);}
}

function histGoPage(p){histState.page=p;cargarHistorial(p);}

async function eliminarTx(id){
  if(!confirm('¿Eliminar transacción #'+id+'?'))return;
  try{
    await apicall('transacciones/crear',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    let currentRows=document.querySelectorAll('#histAccordion .hist-item').length;
    if(currentRows<=1 && histState.page>0) histState.page--;
    await cargarHistorial();
  }catch(e){alert(e.message);}
}

// ── Lista de asociados ──
let selState={q:'',page:0,order:'nombre',dir:'ASC'},selDebounce;
let selCache={}, selSelectedCedula=null;

// ── Historial ──
const HIST_PAGE_SIZE=10;
let histState={page:0};

function seleccionarAsociado(cedula){
  asociadoCedula=cedula;
  selSelectedCedula=cedula;
  document.getElementById('selQ').value='';
  selState.q='';selState.page=0;
  let a=selCache[cedula];
  if(a) renderSelSelected(a);
  buscarAsociado();
}

function renderSelSelected(a){
  document.getElementById('selList').innerHTML=`<div class="sel-row selected d-flex align-items-center justify-content-between px-3 py-2" data-cedula="${esc(a.cedula)}">
    <div>
      <div class="fw-semibold">${esc(a.nombre)}</div>
      <small class="text-muted">CC ${esc(a.cedula)}</small>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge text-bg-light">${money(a.total_saldos_favor)}</span>
      ${a.creditos>0?`<span class="badge text-bg-info">${a.creditos} créd.</span>`:''}
      <span class="badge text-bg-success"><i class="fas fa-check me-1"></i>Seleccionado</span>
    </div>
  </div>`;
  document.getElementById('selInfoTotal').innerHTML='<i class="fas fa-check-circle text-success me-1"></i>Asociado seleccionado · <a href="#" class="text-decoration-none" onclick="selSelectedCedula=null;cargarSelAsociados();return false;">Cambiar</a>';
  document.getElementById('selPaginacion').innerHTML='';
}

async function cargarSelAsociados(){
  selSelectedCedula=null;
  let o=selState.page*20,bs='q='+encodeURIComponent(selState.q)+'&limit=20&offset='+o+'&order='+selState.order+'&dir='+selState.dir;
  try{
    let d=await apicall('asociados/listar?'+bs);
    (d.rows||[]).forEach(a=>{selCache[a.cedula]=a;});
    document.getElementById('selInfoTotal').textContent=d.total>0?`Mostrando ${o+1}-${Math.min(o+20,d.total)} de ${d.total}`:'';
    let listHtml=(d.rows||[]).map(a=>{
      let selClass=asociadoCedula===a.cedula?' selected':'';
      return `<div class="sel-row${selClass} d-flex align-items-center justify-content-between px-3 py-2 border-bottom" data-cedula="${esc(a.cedula)}" onclick="seleccionarAsociado('${esc(a.cedula)}')">
        <div class="flex-grow-1">
          <div class="fw-semibold">${esc(a.nombre)}</div>
          <small class="text-muted">CC ${esc(a.cedula)}</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge text-bg-light">${money(a.total_saldos_favor)}</span>
          ${a.creditos>0?`<span class="badge text-bg-info">${a.creditos} créd.</span>`:''}
          <i class="fas fa-chevron-right text-muted small ms-1"></i>
        </div>
      </div>`;
    }).join('')||'<div class="text-center text-muted small py-3">Sin resultados</div>';
    document.getElementById('selList').innerHTML=listHtml;
    if(asociadoCedula){
      let row=document.querySelector('.sel-row[data-cedula="'+asociadoCedula+'"]');
      if(row) row.classList.add('selected');
    }
    renderSelPagination(d.total);
  }catch(e){alert(e.message);}
}

function renderSelPagination(t){
  let N=Math.ceil(t/20);if(N<=1){document.getElementById('selPaginacion').innerHTML='';return;}
  let C=selState.page,pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="selGoPage(${C-1})">&laquo;</a></li>`);
  pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">&hellip;</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="selGoPage(${p})">${p+1}</a></li>`);});
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="selGoPage(${C+1})">&raquo;</a></li>`);
  document.getElementById('selPaginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}

function selGoPage(p){selState.page=p;cargarSelAsociados();}

cargarPagos();
cargarSelAsociados();
document.getElementById('selQ').addEventListener('input',function(){
  clearTimeout(selDebounce);
  selDebounce=setTimeout(()=>{selState.q=this.value.trim();selState.page=0;cargarSelAsociados();},300);
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
