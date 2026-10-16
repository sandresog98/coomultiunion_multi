<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('cobranza');
$rol = $staff['rol'] ?? '';
$acc = ui_acciones('cobranza.lista', $rol);
$canComunicar = ui_accion('cobranza.lista', 'comunicar',       $rol);
$canIndagar   = ui_accion('cobranza.lista', 'indagar',         $rol);
$canExportar  = ui_accion('cobranza.lista', 'exportar',        $rol);
$canRemJurid  = ui_accion('cobranza.lista', 'remitir_juridica',$rol);
$pageTitle = 'Cobranza';
$currentPage = 'cobranza';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<!-- KPIs -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-2"><div class="card KPI"><div class="card-body p-2 text-center"><small class="text-muted d-block">En mora</small><strong id="kTotal" class="fs-5">—</strong></div></div></div>
  <div class="col-6 col-md-2"><div class="card KPI"><div class="card-body p-2 text-center"><small class="text-muted d-block">Crédito</small><strong id="kCredito" class="fs-5">—</strong></div></div></div>
  <div class="col-6 col-md-2"><div class="card KPI"><div class="card-body p-2 text-center"><small class="text-muted d-block">Aportes</small><strong id="kAportes" class="fs-5">—</strong></div></div></div>
  <div class="col-6 col-md-2"><div class="card KPI"><div class="card-body p-2 text-center"><small class="text-muted d-block">Total mora</small><strong id="kMora" class="fs-5 text-danger">—</strong></div></div></div>
  <div class="col-6 col-md-2"><div class="card KPI"><div class="card-body p-2 text-center"><small class="text-muted d-block">Sin comunicación</small><strong id="kSin" class="fs-5">—</strong></div></div></div>
  <div class="col-6 col-md-2 d-flex align-items-center justify-content-center">
    <?php if ($canExportar): ?>
    <button class="btn btn-outline-success btn-sm" onclick="exportar()"><i class="fas fa-file-excel me-1"></i>Exportar</button>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-phone me-2"></i>Cobranza unificada</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto"><label class="form-label small text-muted mb-0">Tipo de mora</label>
        <select class="form-select" id="fTipoMora" data-searchable style="width:140px"><option value="">Ambos</option><option value="credito">Crédito</option><option value="aportes">Aportes</option></select>
      </div>
      <div class="col-auto" style="max-width:300px"><label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Cédula, nombre, tel, email…"></div>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Banda</label>
        <select class="form-select" id="fBanda" data-searchable style="width:150px"><option value="">Todas</option>
          <option value="persuasiva" data-tipo="credito">Persuasiva</option><option value="prejuridico" data-tipo="credito">Prejurídico</option><option value="juridico" data-tipo="credito">Jurídico</option>
          <option value="30_60" data-tipo="aportes">Aportes 30-60</option><option value="61_90" data-tipo="aportes">Aportes 61-90</option><option value="90_mas" data-tipo="aportes">Aportes 91+</option></select>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Última comunicación</label>
        <select class="form-select" id="fRango" data-searchable style="width:170px"><option value="">Todos</option><option value="sin">Sin comunicación</option><option value="muy_reciente">Muy reciente</option><option value="reciente">Reciente</option><option value="intermedia">Intermedia</option><option value="lejana">Lejana</option><option value="muy_lejana">Muy lejana</option></select>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Compromiso</label>
        <select class="form-select" id="fCompromiso" data-searchable style="width:150px"><option value="">Todos</option><option value="cumplido">Cumplido</option><option value="incumplido">Incumplido</option><option value="pendiente_tiempo">Pendiente</option><option value="sin">Sin compromiso</option></select>
      </div>
      <div class="col-auto" style="padding-bottom:6px"><div class="form-check"><input class="form-check-input" type="checkbox" id="fSoloRem"><label class="form-check-label" for="fSoloRem">Solo remitidos a jurídica</label></div></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="nombre">Asociado</th>
          <th>Tipo</th>
          <th class="sortable text-end" data-col="dias">Días</th>
          <th class="sortable text-end" data-col="total_mora">Total mora</th>
          <th>Banda</th>
          <th class="sortable" data-col="ultima_comunicacion">Últ. comunicación</th>
          <th class="sortable" data-col="compromiso">Compromiso</th>
          <th class="text-center">Remitido</th>
          <th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Modal: Comunicación -->
<div class="modal fade" id="mCom" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Registrar comunicación</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="comCedula"><input type="hidden" id="comId">
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" id="comTipo"></select></div>
      <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" id="comEstado"></select></div>
      <div class="col-md-4"><label class="form-label">Fecha</label><input type="datetime-local" class="form-control" id="comFecha"></div>
      <div class="col-12"><label class="form-label">Comentario</label><textarea class="form-control" id="comComent" rows="2"></textarea></div>
      <div class="col-md-6 d-none" id="wrapComp"><label class="form-label">Fecha compromiso</label><input type="date" class="form-control" id="comFecComp"></div>
      <div class="col-md-6 d-none" id="wrapCompM"><label class="form-label">Monto compromiso</label><input type="number" class="form-control" id="comMonto" step="1" min="0"></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarComunicacion()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<!-- Modal: Indagación -->
<div class="modal fade" id="mInd" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Registrar indagación</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="indCedula"><input type="hidden" id="indId">
    <div class="mb-2"><label class="form-label">Tipo de indagación</label><input class="form-control" id="indTipo"></div>
    <div class="mb-2"><label class="form-label">Fecha</label><input type="date" class="form-control" id="indFecha"></div>
    <div class="mb-1"><label class="form-label">Adjunto (opcional)</label><input type="file" class="form-control" id="indArchivo"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarIndagacion()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<style>
.KPI .card-body{padding:.6rem .4rem}
</style>
<script>
const API=<?php echo json_encode(apiUrl()); ?>, U=<?php echo json_encode(uiBaseUrl()); ?>, alertBox=document.getElementById('alert');
const SEC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_COMUNICAR=SEC['cobranza.lista.comunicar']===true;
const CAN_INDAGAR  =SEC['cobranza.lista.indagar']===true;
const CAN_REMJUR   =SEC['cobranza.lista.remitir_juridica']===true;
let state={tipoMora:'',q:'',banda:'',rango:'',compromiso:'',soloRem:false,page:0,size:20,order:'dias',dir:'DESC'};
let debounceTimer, modalCom, modalInd;
const BANDA={persuasiva:['Persuasiva','primary'],prejuridico:['Prejurídico','warning'],juridico:['Jurídico','danger'],'30_60':['30–60 días','primary'],'61_90':['61–90 días','warning'],'90_mas':['91+ días','danger']};
const COMP={pendiente_tiempo:['Pendiente','warning'],cumplido:['Cumplido','success'],incumplido:['Incumplido','danger']};
const RANGO={sin:['Sin','danger'],muy_reciente:['Muy reciente','success'],reciente:['Reciente','success'],intermedia:['Intermedia','warning'],lejana:['Lejana','warning'],muy_lejana:['Muy lejana','danger']};

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric'});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',()=>{
  const col=th.dataset.col;if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=col;state.dir='DESC';}
  state.page=0;cargar();
}));
function renderSortIcons(){document.querySelectorAll('.sortable').forEach(th=>{
  const col=th.dataset.col,a=state.order===col;th.classList.toggle('active',a);
  const txt=th.textContent.trim().replace(/[▲▼]/g,'');th.innerHTML=txt+(a?` <span class="sort-icon">${state.dir==='ASC'?'▲':'▼'}</span>`:'');
});}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
const FILTER_MAP={fTipoMora:'tipoMora',fBanda:'banda',fRango:'rango',fCompromiso:'compromiso'};
['fTipoMora','fBanda','fRango','fCompromiso'].forEach(id=>document.getElementById(id).addEventListener('change',function(){state[FILTER_MAP[id]]=this.value;state.page=0;cargar();}));
document.getElementById('fSoloRem').addEventListener('change',function(){state.soloRem=this.checked;state.page=0;cargar();});
document.getElementById('fTipoMora').addEventListener('change',function(){
  const t=this.value,sel=document.getElementById('fBanda');
  Array.from(sel.options).forEach(o=>{ if(o.value==='') return; o.disabled = (t!=='' && o.dataset.tipo!==t); });
  window.Listado.refreshSelect(sel);
  if(sel.value && sel.value!=='' && t!=='' && sel.options[sel.selectedIndex] && sel.options[sel.selectedIndex].dataset.tipo!==t){
    window.Listado.setSelect(sel,''); state.banda=''; cargar();
  }
});

async function cargarKPIs(){
  try{const k=await api('cobranza/kpis');
    document.getElementById('kTotal').textContent=k.total;document.getElementById('kCredito').textContent=k.credito;
    document.getElementById('kAportes').textContent=k.aportes;document.getElementById('kMora').textContent=money(k.total_mora);
    document.getElementById('kSin').textContent=k.sin_comunicacion;
  }catch(e){}
}

async function cargar(){
  const off=state.page*state.size;
  const bs=`tipo_mora=${state.tipoMora}&q=${encodeURIComponent(state.q)}&banda=${state.banda}&rango=${state.rango}&compromiso=${state.compromiso}&solo_remitidos=${state.soloRem}`+`&order=${state.order}&dir=${state.dir}&limit=${state.size}&offset=${off}`;
  try{
    alertBox.classList.add('d-none');
    const d=await api('cobranza/listar?'+bs);
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${off+1}-${Math.min(off+state.size,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>{
      const bd=BANDA[r.banda]||[r.banda,'secondary'];
      const comp=r.compromiso?(COMP[r.compromiso.estado_compromiso]||['—','secondary']):null;
      const rem=r.remitido_juridica==1?'<span class="badge text-bg-danger" data-tip="desde '+fmtDt(r.fecha_remision)+'">Sí</span>':'<span class="badge text-bg-secondary">No</span>';
      const tip=r.tipo_mora==='credito'?'<span class="badge text-bg-info">Crédito</span>':'<span class="badge text-bg-secondary">Aportes</span>';
      return `<tr class="fila-link" onclick="location.href='${U}modules/asociados/pages/asociados_detalle?cedula=${encodeURIComponent(r.cedula)}'">
        <td><span class="fw-semibold">${esc(r.nombre||r.cedula)}</span><br><small class="text-muted">CC ${esc(r.cedula)}</small></td>
        <td>${tip}</td>
        <td class="text-end"><strong>${r.dias}</strong>${r.tipo_mora==='credito'?'': '<small class="text-muted d-block">sin aporte</small>'}</td>
        <td class="text-end">${r.tipo_mora==='credito'?money(r.total_mora):'—'}</td>
        <td><span class="badge text-bg-${bd[1]}">${bd[0]}</span></td>
        <td>${r.ultima_comunicacion ? ((RANGO[r.rango]?`<span class="badge text-bg-${RANGO[r.rango][1]}">${RANGO[r.rango][0]}</span> `:'')+fmtDt(r.ultima_comunicacion)) : '—'}</td>
        <td>${comp? '<span class="badge text-bg-'+comp[1]+'">'+comp[0]+'</span>'+ (r.compromiso.suma_pagado>0?' <small class="text-muted">$'+Number(r.compromiso.suma_pagado).toLocaleString('es-CO')+'</small>':''):'—'}</td>
        <td class="text-center">${rem}</td>
        <td class="text-end text-nowrap">
          ${CAN_COMUNICAR?`<button class="btn btn-sm btn-outline-primary" title="Comunicar" onclick='event.stopPropagation();comunicar(${JSON.stringify(r)})'><i class="fas fa-phone"></i></button>`:''}
          ${CAN_INDAGAR?`<button class="btn btn-sm btn-outline-secondary" title="Indagación" onclick='event.stopPropagation();indagar(${JSON.stringify(r)})'><i class="fas fa-search"></i></button>`:''}
          ${CAN_REMJUR&&r.tipo_mora==='credito'?`<button class="btn btn-sm ${r.remitido_juridica==1?'btn-outline-success':'btn-outline-danger'}" title="${r.remitido_juridica==1?'Quitar remisión':'Remitir a jurídica'}" onclick='event.stopPropagation();toggleRemision(${JSON.stringify(r)})'><i class="fas ${r.remitido_juridica==1?'fa-ban':'fa-gavel'}"></i></button>`:''}
          <a class="btn btn-sm btn-outline-info" title="Ver detalle" onclick="event.stopPropagation()" href="${U}modules/asociados/pages/asociados_detalle?cedula=${encodeURIComponent(r.cedula)}"><i class="fas fa-eye"></i></a>
        </td></tr>`;
    }).join('')||'<tr><td colspan="9" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}
function renderPagination(t){const N=Math.ceil(t/state.size);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}
  const C=state.page;let pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p=>{if(p==='...')h.push(`<li class="page-item disabled"><span class="page-link">…</span></li>`);else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;}
function goPage(p){state.page=p;cargar();}

// ── Comunicación ──
let TIPOS_COMM={};
let comTipoOrigen='credito';
async function cargarTiposComm(){
  try{const rows=await api('cobranza/tipos?opciones=1');
    TIPOS_COMM={};
    rows.forEach(t=>{TIPOS_COMM[t.id]={tipo:t.tipo,estado:t.estado,label:t.label};});
    const tipos=[...new Set(rows.map(t=>t.tipo))], estados=[...new Set(rows.map(t=>t.estado))];
    document.getElementById('comTipo').innerHTML=tipos.map(t=>`<option value="${t}">${t}</option>`).join('');
    document.getElementById('comEstado').innerHTML=estados.map(s=>`<option value="${s}">${s}</option>`).join('');
  }catch(e){}
}
document.getElementById('comEstado').addEventListener('change',function(){toggleCompromiso(this.value==='comprometido');});
function toggleCompromiso(show){document.getElementById('wrapComp').classList.toggle('d-none',!show);document.getElementById('wrapCompM').classList.toggle('d-none',!show);}
function comunicar(r){
  comTipoOrigen=(r.tipo_mora==='aportes')?'aportes':'credito';
  document.getElementById('comCedula').value=r.cedula;document.getElementById('comId').value='';
  document.getElementById('comFecha').value=new Date().toISOString().slice(0,16);
  document.getElementById('comComent').value='';document.getElementById('comFecComp').value='';document.getElementById('comMonto').value='';
  toggleCompromiso(false);modalCom.show();
}
async function guardarComunicacion(){
  const d={asociado_cedula:document.getElementById('comCedula').value,tipo_origen:comTipoOrigen,
    tipo_comunicacion:document.getElementById('comTipo').value,estado:document.getElementById('comEstado').value,
    fecha:document.getElementById('comFecha').value.replace('T',' '),comentario:document.getElementById('comComent').value.trim(),
    fecha_compromiso:document.getElementById('comFecComp').value||null,monto_compromiso:document.getElementById('comMonto').value||null};
  if(!d.fecha){alert('La fecha es obligatoria.');return;}
  try{
    const c=await api('cobranza/comunicacion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});
    if(c&&c.id){ await api('cobranza/snapshot',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cedula:d.asociado_cedula,comunicacion_id:c.id})}); }
    modalCom.hide();cargar();cargarKPIs();
  }catch(e){alert(e.message);}
}

// ── Indagación ──
let indTipoOrigen='credito';
function indagar(r){
  indTipoOrigen=(r.tipo_mora==='aportes')?'aportes':'credito';
  document.getElementById('indCedula').value=r.cedula;document.getElementById('indId').value='';
  document.getElementById('indTipo').value='';document.getElementById('indFecha').value=new Date().toISOString().slice(0,10);
  document.getElementById('indArchivo').value='';modalInd.show();
}
async function guardarIndagacion(){
  const ced=document.getElementById('indCedula').value,tipo=document.getElementById('indTipo').value.trim(),fec=document.getElementById('indFecha').value;
  if(!tipo||!fec){alert('Tipo y fecha son obligatorios.');return;}
  const archivo=document.getElementById('indArchivo').files[0];
  try{
    const id=(await api('cobranza/indagacion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({asociado_cedula:ced,tipo_indagacion:tipo,fecha:fec,tipo_origen:indTipoOrigen})})).id;
    if(archivo){
      const fd=new FormData();fd.append('indagacion_id',id);fd.append('archivo',archivo);
      const res=await api('cobranza/adjunto',{method:'POST',body:fd});
      if(!res||!res.id) alert('El adjunto no se guardó correctamente.');
    }
    modalInd.hide();cargar();
  }catch(e){alert(e.message);}
}

// ── Remisión ──
async function toggleRemision(r){
  const remitir=r.remitido_juridica==1?false:true;
  if(!confirm(remitir?'¿Remitir a cobranza jurídica (requiere mora ≥91 días)?':'¿Quitar la remisión jurídica?'))return;
  try{
    await api('cobranza/remision',{method:remitir?'POST':'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({cedula:r.cedula})});
    cargar();
  }catch(e){alert(e.message);}
}

async function exportar(){let a=document.createElement('a');a.href=API+'cobranza/export';a.download='';document.body.appendChild(a);a.click();a.remove();}

document.addEventListener('DOMContentLoaded',()=>{
  modalCom=new bootstrap.Modal('#mCom');modalInd=new bootstrap.Modal('#mInd');
  const qp=new URLSearchParams(location.search).get('q');
  if(qp){document.getElementById('q').value=qp;state.q=qp;}
  window.Listado.mountSize('fSizeBox', state, cargar);
  cargarTiposComm();cargarKPIs();cargar();
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>