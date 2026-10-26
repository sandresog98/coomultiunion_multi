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

/* ── Selectores del paso 1 ── */
.sel-row, .pago-row { cursor:pointer; transition:background .15s ease; border-left:3px solid transparent; }
.sel-row:hover, .pago-row:hover { background:#eef5fb !important; border-left-color:var(--brand-blue,#1882CD); }
.sel-row.selected, .pago-row.selected { background:#d9ecfa !important; border-left-color:var(--brand-blue-dark,#11468A); }
.pago-row .pago-disp { white-space:nowrap; }
.sel-summary .badge { font-size:.78rem; padding:.5rem .7rem; font-weight:500; }

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

/* ── Panel bloqueado del paso 2 ── */
.locked-box { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:1rem; }

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
    <div class="step-label">Pago y asociado</div>
  </div>
  <div class="step-connector" id="conn1"></div>
  <div class="step-item" id="step2">
    <div class="step-circle">2</div>
    <div class="step-label">Asignar rubros</div>
  </div>
  <div class="step-connector" id="conn2"></div>
  <div class="step-item" id="step3">
    <div class="step-circle">3</div>
    <div class="step-label">Confirmar</div>
  </div>
</div>

<!-- ══════════ PASO 1: selección de pago + asociado ══════════ -->
<div id="paso1" class="row g-4">
  <!-- Pagos con saldo disponible -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-money-check-dollar me-2"></i>Pagos con saldo disponible</span>
        <span class="badge text-bg-light" id="pgCount">—</span>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col">
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
              <input class="form-control" id="pgQ" placeholder="Buscar por ID, cédula, nombre o descripción…" autocomplete="off">
              <button class="btn btn-outline-secondary btn-sm" type="button" onclick="limpiarBusquedaPagos()" title="Limpiar"><i class="fas fa-times"></i></button>
            </div>
          </div>
          <div class="col-auto">
            <select class="form-select form-select-sm" id="pgOrigen" style="width:130px">
              <option value="">Todos</option>
              <option value="pse">PSE</option>
              <option value="confiar">Confiar</option>
            </select>
          </div>
        </div>
        <small class="text-muted d-block mb-1">Solo se muestran Confiar asignados y PSE con cruce Confiar.</small>
        <small class="text-muted" id="pgInfoTotal"></small>
        <div class="mt-2" id="pgList" style="max-height:540px;overflow-y:auto">
          <div class="text-center text-muted small py-3">Cargando…</div>
        </div>
        <nav id="pgPaginacion" class="d-flex justify-content-center mt-2"></nav>
      </div>
    </div>
  </div>

  <!-- Asociados -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header brand"><i class="fas fa-user me-2"></i>Seleccionar asociado</div>
      <div class="card-body">
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="selQ" placeholder="Buscar por cédula o nombre…" autocomplete="off">
          <button class="btn btn-outline-secondary btn-sm" type="button" onclick="document.getElementById('selQ').value='';selState.q='';selState.page=0;cargarSelAsociados();"><i class="fas fa-times"></i></button>
        </div>
        <small class="text-muted" id="selInfoTotal"></small>
        <div class="mt-2" id="selList" style="max-height:540px;overflow-y:auto">
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
</div>

<!-- Barra de resumen de selección -->
<div id="selBar" class="card mt-3 sel-summary">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge text-bg-light" id="sumPago">Pago: sin seleccionar</span>
        <span class="badge text-bg-light" id="sumAsoc">Asociado: sin seleccionar</span>
        <small class="text-warning d-none" id="selHint"></small>
      </div>
      <button class="btn btn-brand" id="btnContinuar" onclick="continuarPaso2()" disabled>
        Continuar al paso 2 <i class="fas fa-arrow-right ms-1"></i>
      </button>
    </div>
  </div>
</div>

<!-- ══════════ PASO 2: asignación de rubros (selección bloqueada) ══════════ -->
<div id="paso2" class="d-none">
  <!-- Resumen bloqueado -->
  <div class="card mb-3">
    <div class="card-header brand d-flex justify-content-between align-items-center">
      <span><i class="fas fa-lock me-2"></i>Pago y asociado seleccionados</span>
      <button class="btn btn-sm btn-outline-light" onclick="cambiarSeleccion()">
        <i class="fas fa-rotate-left me-1"></i>Cambiar selección
      </button>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-7">
          <div class="locked-box h-100">
            <div class="form-section-title mb-1">Pago</div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge text-bg-primary" id="lkPagoOrigen">—</span>
              <b>#<span id="lkPagoId"></span></b>
            </div>
            <div class="small text-muted mt-1">
              Valor: <b id="lkPagoValor">—</b> ·
              Usado: <b id="lkPagoUsado">—</b> ·
              Disponible: <b class="text-success" id="lkPagoRestante">—</b>
            </div>
            <div class="small text-muted" id="lkPagoExtra"></div>
          </div>
        </div>
        <div class="col-md-5">
          <div class="locked-box h-100">
            <div class="form-section-title mb-1">Asociado</div>
            <div class="fw-semibold" id="lkAsocNombre">—</div>
            <div class="small text-muted">CC <span id="lkAsocCedula">—</span></div>
            <div class="small text-muted">Saldos a favor: <b class="text-success" id="lkAsocSaldos">—</b></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card card-reveal d-none" id="cardRubros">
        <div class="card-header brand d-flex justify-content-between align-items-center">
          <span><i class="fas fa-list-check me-2"></i>Rubros a pagar</span>
          <span class="badge text-bg-light" id="rubrosCount"></span>
        </div>
        <div class="card-body p-2" id="rubrosList"></div>
      </div>

      <div class="card card-reveal mt-3 d-none" id="cardPago">
        <div class="card-header brand"><i class="fas fa-cash-register me-2"></i>Datos de la transacción</div>
        <div class="card-body">
          <div id="txAlert" class="alert d-none py-2 mb-3"></div>

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Recibo caja</label>
              <input class="form-control form-control-sm" id="txRecibo" placeholder="Nº recibo">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Disponible del pago</label>
              <div class="form-control form-control-sm bg-light fw-bold" id="txDisponible">—</div>
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
                <div class="form-text mb-0">Suma de los rubros asignados.</div>
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
                <span class="text-muted">Disponible del pago: <b id="txTotalRubros">$0</b></span>
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

          <div id="txSuccess" class="d-none mt-2">
            <button class="btn btn-brand w-100" onclick="resetSeleccion()">
              <i class="fas fa-plus me-2"></i>Nueva transacción
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card card-reveal d-none" id="cardHistorial">
        <div class="card-header brand"><i class="fas fa-history me-2"></i>Historial de transacciones</div>
        <div class="card-body p-0" id="histContent">
          <div class="text-center text-muted small py-3">Sin transacciones</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;

// ── Estado general ──
let asociadoCedula = '';
let rubros = {creditos:[],mora:[],productos:[]};
let detallesTx = [];
let pagoRestante = 0;
let selectedPago = null;
let selectedAsociado = null;
let lockedPago = null;
let lockedAsociado = null;

const PAGOS_PAGE_SIZE = 10;
const SEL_PAGE_SIZE = 10;
const HIST_PAGE_SIZE = 10;

let pagosState = {q:'', origen:'', page:0};
let pagosCache = {};
let pagosDebounce;

let selState = {q:'',page:0,order:'nombre',dir:'ASC'};
let selDebounce;
let selCache = {};

let histState = {page:0};

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function normCed(v){return String(v??'').replace(/^0+/,'');}
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
function estadoTxt(v){const s=String(v||'');return s?s.charAt(0).toUpperCase()+s.slice(1):'';}
async function apicall(p,o){let r=await fetch(API+p,o),j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

function revealCard(el){
  if(!el||!el.classList.contains('d-none')) return;
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

// ── Ficha del asociado ──
async function cargarFicha(cedula){
  let d=await apicall('asociados/ficha?cedula='+encodeURIComponent(cedula));
  let m=d.productos||{},p=d.persona||{};
  document.getElementById('aNombre').textContent=p.nombre||'';
  document.getElementById('aCedula').textContent=cedula;
  document.getElementById('aSaldos').textContent=money(m.total_saldos_favor||0);
  document.getElementById('aCreditos').textContent=(m.total_creditos||0);
  document.getElementById('aProdMensual').textContent=money(m.producto_mensual_total||0);
  document.getElementById('aMora').textContent=money(m.total_mora||0);
  revealCard(document.getElementById('aInfo'));
  return d;
}

// ══════════ PASO 1: pagos con saldo disponible ══════════
async function cargarPagosDisponibles(){
  const o=pagosState.page*PAGOS_PAGE_SIZE;
  let bs='tipo=todos&limit='+PAGOS_PAGE_SIZE+'&offset='+o;
  if(pagosState.q) bs+='&q='+encodeURIComponent(pagosState.q);
  if(pagosState.origen) bs+='&origen='+encodeURIComponent(pagosState.origen);
  try{
    let d=await apicall('transacciones/listar_pagos?'+bs);
    let rows=d.rows||[], total=Number(d.total||0);
    pagosCache={};
    rows.forEach(r=>{pagosCache[r.origen+'|'+r.id]=r;});
    if(selectedPago && pagosCache[selectedPago.key]){
      selectedPago={...pagosCache[selectedPago.key],key:selectedPago.key};
    }
    document.getElementById('pgCount').textContent=total+' pago'+(total===1?'':'s');
    document.getElementById('pgInfoTotal').textContent=total>0?`Mostrando ${o+1}-${Math.min(o+PAGOS_PAGE_SIZE,total)} de ${total}`:'';
    document.getElementById('pgList').innerHTML=rows.map(pagoRowHtml).join('')||'<div class="text-center text-muted small py-3">No hay pagos con saldo disponible</div>';
    renderPagoPagination(total);
    updateSelSummary();
  }catch(e){
    document.getElementById('pgList').innerHTML='<div class="text-center text-danger small py-3">'+esc(e.message)+'</div>';
    document.getElementById('pgInfoTotal').textContent='';
    document.getElementById('pgPaginacion').innerHTML='';
  }
}

function pagoRowHtml(r){
  const key=r.origen+'|'+r.id;
  const sel=selectedPago && selectedPago.key===key ? ' selected' : '';
  const esPse=r.origen==='pse';
  const badge=esPse?'text-bg-primary':'text-bg-success';
  const ced=r.asociado_cedula?String(r.asociado_cedula):(r.cedula_pago?String(r.cedula_pago):'');
  const nombre=r.asociado_nombre||r.nombre_pago||'';
  let cruz;
  if(Number(r.pse_cedulas_count)>1){
    cruz=`<span class="text-warning"><i class="fas fa-users me-1"></i>Varias cédulas (${Number(r.pse_cedulas_count)})</span>`;
  }else if(!ced){
    cruz='<span class="text-warning"><i class="fas fa-circle-question me-1"></i>Sin cédula asociada</span>';
  }else{
    cruz=`<i class="fas fa-user me-1"></i>${esc(nombre||'—')} · CC ${esc(ced)}`;
  }
  const pseInfo=(!esPse && Number(r.pse_count)>0)
    ? `<span class="badge text-bg-info">PSE ${Number(r.pse_count)} · ${money(r.pse_total)}</span>`
    : '';
  const confiarInfo=(esPse && Number(r.confiar_count)>0)
    ? `<span class="badge text-bg-info" title="Cruce Confiar: ${esc(r.confiar_ids||'')}">Confiar ${Number(r.confiar_count)}</span>`
    : '';
  return `<div class="pago-row${sel} px-3 py-2 border-bottom" data-key="${esc(key)}">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div class="flex-grow-1" style="min-width:0">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="badge ${badge}">${esc(String(r.origen).toUpperCase())}</span>
          <b class="small">#${esc(r.id)}</b>
          ${confiarInfo}
          ${pseInfo}
        </div>
        <div class="small text-muted mt-1">${cruz}</div>
        <div class="small text-muted text-truncate">${esc(r.descripcion||'—')}</div>
      </div>
      <div class="text-end flex-shrink-0 pago-disp">
        <div class="fw-bold text-success">${money(r.restante)}</div>
        <small class="text-muted">disp. de ${money(r.valor)}</small>
      </div>
    </div>
    <div class="d-flex justify-content-between small text-muted mt-1">
      <span>${esc(estadoTxt(r.estado))}</span>
      <span>${fmtDt(r.fecha)}</span>
    </div>
  </div>`;
}

function renderPagoPagination(t){
  const N=Math.ceil(t/PAGOS_PAGE_SIZE);
  if(N<=1){document.getElementById('pgPaginacion').innerHTML='';return;}
  let C=pagosState.page,pages=[];
  if(N<=10){for(let i=0;i<N;i++)pages.push(i);}
  else{
    pages.push(0);
    if(C>3)pages.push('...');
    for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);
    if(C<N-4)pages.push('...');
    pages.push(N-1);
  }
  let h=[];
  if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="pgGoPage(${C-1});return false;">&laquo;</a></li>`);
  pages.forEach(p=>{
    if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">&hellip;</span></li>');
    else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="pgGoPage(${p});return false;">${p+1}</a></li>`);
  });
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="pgGoPage(${C+1});return false;">&raquo;</a></li>`);
  document.getElementById('pgPaginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}

function pgGoPage(p){pagosState.page=p;cargarPagosDisponibles();}

function limpiarBusquedaPagos(){
  document.getElementById('pgQ').value='';
  pagosState.q='';pagosState.page=0;cargarPagosDisponibles();
}

function seleccionarPago(key){
  const p=pagosCache[key]||(selectedPago&&selectedPago.key===key?selectedPago:null);
  if(!p)return;
  selectedPago={...p,key};
  document.querySelectorAll('#pgList .pago-row').forEach(el=>el.classList.toggle('selected',el.dataset.key===key));
  updateSelSummary();
}

// ══════════ PASO 1: asociados ══════════
async function cargarSelAsociados(){
  let o=selState.page*SEL_PAGE_SIZE;
  let bs='q='+encodeURIComponent(selState.q)+'&limit='+SEL_PAGE_SIZE+'&offset='+o+'&order='+selState.order+'&dir='+selState.dir;
  try{
    let d=await apicall('asociados/listar?'+bs);
    (d.rows||[]).forEach(a=>{selCache[a.cedula]=a;});
    document.getElementById('selInfoTotal').textContent=d.total>0?`Mostrando ${o+1}-${Math.min(o+SEL_PAGE_SIZE,d.total)} de ${d.total}`:'';
    const selectedCed=selectedAsociado?String(selectedAsociado.cedula):'';
    document.getElementById('selList').innerHTML=(d.rows||[]).map(a=>{
      const selClass=selectedCed===String(a.cedula)?' selected':'';
      return `<div class="sel-row${selClass} d-flex align-items-center justify-content-between px-3 py-2 border-bottom" data-cedula="${esc(a.cedula)}" onclick="seleccionarAsociado('${esc(a.cedula)}')">
        <div class="flex-grow-1">
          <div class="fw-semibold">${esc(a.nombre)}</div>
          <small class="text-muted">CC ${esc(a.cedula)}</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge text-bg-light">${money(a.total_saldos_favor)}</span>
          ${Number(a.creditos)>0?`<span class="badge text-bg-info">${a.creditos} créd.</span>`:''}
          <i class="fas fa-chevron-right text-muted small ms-1"></i>
        </div>
      </div>`;
    }).join('')||'<div class="text-center text-muted small py-3">Sin resultados</div>';
    renderSelPagination(d.total);
  }catch(e){
    document.getElementById('selList').innerHTML='<div class="text-center text-danger small py-3">'+esc(e.message)+'</div>';
  }
}

function seleccionarAsociado(cedula){
  const a=selCache[cedula]||{cedula,nombre:''};
  selectedAsociado=a;
  document.querySelectorAll('#selList .sel-row').forEach(el=>el.classList.toggle('selected',el.dataset.cedula===String(cedula)));
  updateSelSummary();
  cargarFicha(String(cedula)).catch(e=>console.error(e));
}

function renderSelPagination(t){
  let N=Math.ceil(t/SEL_PAGE_SIZE);if(N<=1){document.getElementById('selPaginacion').innerHTML='';return;}
  let C=selState.page,pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="selGoPage(${C-1});return false;">&laquo;</a></li>`);
  pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">&hellip;</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="selGoPage(${p});return false;">${p+1}</a></li>`);});
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="selGoPage(${C+1});return false;">&raquo;</a></li>`);
  document.getElementById('selPaginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}

function selGoPage(p){selState.page=p;cargarSelAsociados();}

// ══════════ RESUMEN DE SELECCIÓN ══════════
function updateSelSummary(){
  const sp=document.getElementById('sumPago');
  const sa=document.getElementById('sumAsoc');
  if(selectedPago){
    sp.className='badge text-bg-primary';
    sp.innerHTML=`<i class="fas fa-money-check-dollar me-1"></i>${esc(String(selectedPago.origen).toUpperCase())} #${esc(selectedPago.id)} · ${money(selectedPago.restante)} disp.`;
  }else{
    sp.className='badge text-bg-light';
    sp.textContent='Pago: sin seleccionar';
  }
  if(selectedAsociado){
    sa.className='badge text-bg-success';
    sa.innerHTML=`<i class="fas fa-user me-1"></i>${esc(selectedAsociado.nombre||'Asociado')} · CC ${esc(selectedAsociado.cedula)}`;
  }else{
    sa.className='badge text-bg-light';
    sa.textContent='Asociado: sin seleccionar';
  }
  const ok=!!(selectedPago&&selectedAsociado);
  document.getElementById('btnContinuar').disabled=!ok;
  const hint=document.getElementById('selHint');
  if(!ok){
    hint.className='d-none';
  }else if(selectedPago.cedula_pago && normCed(selectedPago.cedula_pago)!==normCed(selectedAsociado.cedula)){
    hint.className='text-warning small';
    hint.innerHTML='<i class="fas fa-triangle-exclamation me-1"></i>La cédula del pago no coincide con el asociado.';
  }else if(Number(selectedPago.pse_cedulas_count)>1){
    hint.className='text-warning small';
    hint.innerHTML='<i class="fas fa-users me-1"></i>El pago tiene varias cédulas PSE; verifica el asociado.';
  }else if(!selectedPago.cedula_pago){
    hint.className='text-muted small';
    hint.innerHTML='<i class="fas fa-circle-info me-1"></i>El pago no tiene cédula asociada; se cruzará con el asociado elegido.';
  }else{
    hint.className='d-none';
  }
}

// ══════════ PASO 2 ══════════
async function continuarPaso2(){
  if(!selectedPago||!selectedAsociado)return;
  if(selectedPago.cedula_pago && normCed(selectedPago.cedula_pago)!==normCed(selectedAsociado.cedula)){
    if(!confirm(`El pago ${selectedPago.id} está asociado a la cédula ${selectedPago.asociado_cedula||selectedPago.cedula_pago}, pero seleccionaste la cédula ${selectedAsociado.cedula}. ¿Deseas continuar de todas formas?`))return;
  }
  lockedPago={...selectedPago};
  lockedAsociado={...selectedAsociado};
  asociadoCedula=String(lockedAsociado.cedula);
  pagoRestante=Number(lockedPago.restante)||0;

  const btn=document.getElementById('btnContinuar');
  btn.disabled=true;
  try{
    const ficha=await cargarFicha(asociadoCedula);
    if(ficha&&ficha.productos){
      lockedAsociado.total_saldos_favor=ficha.productos.total_saldos_favor;
      lockedAsociado.nombre=(ficha.persona&&ficha.persona.nombre)||lockedAsociado.nombre;
    }
    rubros=await apicall('transacciones/crear?cedula='+encodeURIComponent(asociadoCedula));
    renderRubros();
    renderLockedSummary();
    document.getElementById('paso1').classList.add('d-none');
    document.getElementById('selBar').classList.add('d-none');
    document.getElementById('paso2').classList.remove('d-none');
    revealCard(document.getElementById('cardRubros'));
    revealCard(document.getElementById('cardPago'));
    revealCard(document.getElementById('cardHistorial'));
    const alert=document.getElementById('txAlert');
    alert.className='alert d-none py-2 mb-3';
    document.getElementById('txSuccess').classList.add('d-none');
    const b=document.getElementById('btnCrear');
    if(b){b.classList.remove('d-none');b.disabled=false;}
    setStep(2);
    await cargarHistorial(0);
    window.scrollTo({top:0,behavior:'smooth'});
  }catch(e){
    alert(e.message);
  }finally{
    btn.disabled=!(selectedPago&&selectedAsociado);
  }
}

function renderLockedSummary(){
  if(!lockedPago||!lockedAsociado)return;
  document.getElementById('lkPagoOrigen').textContent=String(lockedPago.origen).toUpperCase();
  document.getElementById('lkPagoId').textContent=lockedPago.id;
  document.getElementById('lkPagoValor').textContent=money(lockedPago.valor);
  document.getElementById('lkPagoUsado').textContent=money(lockedPago.usado);
  document.getElementById('lkPagoRestante').textContent=money(lockedPago.restante);
  let extra=[];
  if(lockedPago.descripcion)extra.push(esc(lockedPago.descripcion));
  if(lockedPago.fecha)extra.push(fmtDt(lockedPago.fecha));
  if(lockedPago.cedula_pago)extra.push('Cédula del pago: '+esc(lockedPago.cedula_pago));
  if(Number(lockedPago.pse_count)>0)extra.push('Cruce PSE: '+Number(lockedPago.pse_count)+' ('+money(lockedPago.pse_total)+')');
  if(Number(lockedPago.confiar_count)>0)extra.push('Cruce Confiar: '+Number(lockedPago.confiar_count)+(lockedPago.confiar_ids?' ('+esc(lockedPago.confiar_ids)+')':''));
  document.getElementById('lkPagoExtra').innerHTML=extra.join(' · ');
  document.getElementById('lkAsocNombre').textContent=lockedAsociado.nombre||'—';
  document.getElementById('lkAsocCedula').textContent=lockedAsociado.cedula;
  document.getElementById('lkAsocSaldos').textContent=money(lockedAsociado.total_saldos_favor||0);
  document.getElementById('txDisponible').textContent=money(pagoRestante);
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
  document.getElementById('rubrosCount').textContent=detallesTx.length+' rubro'+(detallesTx.length!==1?'s':'');
  syncDetalles();
}

function syncDetalles(){
  document.querySelectorAll('#rubrosList input').forEach(inp=>{
    let idx=detallesTx.findIndex(d=>inp.dataset.tipo==='producto'?d.producto_id==parseInt(inp.dataset.ref):d.tipo_rubro===inp.dataset.tipo&&d.referencia_credito===inp.dataset.ref);
    if(idx>=0) detallesTx[idx].valor_asignado=parseNum(inp.value);
  });
  const asignado=detallesTx.reduce((s,d)=>s+d.valor_asignado,0);
  document.getElementById('txValorTotal').value=fmtNum(asignado);
  updateProgress();
}

function updateProgress(){
  let asignado=detallesTx.reduce((s,d)=>s+d.valor_asignado,0);
  let restante=pagoRestante-asignado;
  let pct=pagoRestante>0?Math.min(100,(asignado/pagoRestante)*100):0;

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
  else{rtEl.innerHTML='<span class="text-muted">Por asignar: '+money(restante)+'</span>';}
}

async function crearTransaccion(){
  if(!lockedPago||!lockedAsociado)return alert('Seleccione un pago y un asociado en el paso 1.');
  const asignado=detallesTx.reduce((s,d)=>s+d.valor_asignado,0);
  if(!asignado)return alert('Asigne valores a los rubros.');
  if(asignado>pagoRestante+0.01)return alert('El valor asignado supera el saldo disponible del pago.');

  let data={
    cedula:String(lockedAsociado.cedula),
    origen:lockedPago.origen,
    recibo_caja:document.getElementById('txRecibo').value.trim(),
    valor_total:asignado,
    detalles:detallesTx.filter(d=>d.valor_asignado>0)
  };
  if(lockedPago.origen==='pse')data.pse_id=lockedPago.id;else data.confiar_id=lockedPago.id;

  let btn=document.getElementById('btnCrear');
  let btnText=document.getElementById('btnCrearText');
  let btnSpinner=document.getElementById('btnCrearSpinner');
  if(btn){btn.disabled=true;btnText.textContent='Creando…';btnSpinner.classList.remove('d-none');}

  try{
    await apicall('transacciones/crear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    let alert=document.getElementById('txAlert');alert.className='alert alert-success py-2 mb-3';alert.textContent='Transacción creada exitosamente';alert.classList.remove('d-none');
    setStep(3);
    if(btn)btn.classList.add('d-none');
    document.getElementById('txSuccess').classList.remove('d-none');
    try{
      await cargarHistorial(0);
      await cargarPagosDisponibles();
    }catch(refreshErr){
      console.error(refreshErr);
    }
  }catch(e){
    let alert=document.getElementById('txAlert');alert.className='alert alert-danger py-2 mb-3';alert.textContent=e.message;alert.classList.remove('d-none');
  }finally{
    if(btn){btn.disabled=false;btnText.textContent='Crear transacción';btnSpinner.classList.add('d-none');}
  }
}

function cambiarSeleccion(){
  if(!confirm('¿Cambiar el pago o el asociado? Se perderán los valores asignados.'))return;
  resetSeleccion();
}

function resetSeleccion(){
  asociadoCedula='';
  rubros={creditos:[],mora:[],productos:[]};
  detallesTx=[];
  pagoRestante=0;
  selectedPago=null;
  selectedAsociado=null;
  lockedPago=null;
  lockedAsociado=null;
  histState={page:0};
  pagosState={q:'',origen:'',page:0};
  selState={q:'',page:0,order:'nombre',dir:'ASC'};

  document.getElementById('pgQ').value='';
  document.getElementById('pgOrigen').value='';
  document.getElementById('selQ').value='';
  document.getElementById('paso2').classList.add('d-none');
  document.getElementById('paso1').classList.remove('d-none');
  document.getElementById('selBar').classList.remove('d-none');
  document.getElementById('aInfo').classList.add('d-none');
  document.getElementById('rubrosList').innerHTML='';
  document.getElementById('rubrosCount').textContent='';
  document.getElementById('txRecibo').value='';
  document.getElementById('txValorTotal').value='';
  document.getElementById('txDisponible').textContent='—';
  document.getElementById('txTotalAsignado').textContent='$0';
  document.getElementById('txTotalRubros').textContent='$0';
  document.getElementById('txProgressBar').style.width='0%';
  document.getElementById('txProgressBar').className='progress-bar tx-progress';
  document.getElementById('txRestanteText').textContent='';
  document.getElementById('txAlert').classList.add('d-none');
  document.getElementById('txSuccess').classList.add('d-none');
  const b=document.getElementById('btnCrear');
  if(b){b.classList.remove('d-none');b.disabled=false;}

  setStep(1);
  updateSelSummary();
  cargarPagosDisponibles();
  cargarSelAsociados();
}

// ══════════ HISTORIAL ══════════
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
    await cargarPagosDisponibles();
  }catch(e){alert(e.message);}
}

// ══════════ Eventos iniciales ══════════
document.getElementById('pgList').addEventListener('click',function(e){
  const row=e.target.closest('.pago-row');
  if(!row)return;
  seleccionarPago(row.dataset.key);
});

document.getElementById('pgQ').addEventListener('input',function(){
  clearTimeout(pagosDebounce);
  pagosDebounce=setTimeout(()=>{pagosState.q=this.value.trim();pagosState.page=0;cargarPagosDisponibles();},300);
});

document.getElementById('pgOrigen').addEventListener('change',function(){
  pagosState.origen=this.value;pagosState.page=0;cargarPagosDisponibles();
});

document.getElementById('selQ').addEventListener('input',function(){
  clearTimeout(selDebounce);
  selDebounce=setTimeout(()=>{selState.q=this.value.trim();selState.page=0;cargarSelAsociados();},300);
});

cargarPagosDisponibles();
cargarSelAsociados();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
