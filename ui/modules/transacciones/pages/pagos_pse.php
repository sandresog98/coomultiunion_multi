<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.transacciones');
$pageTitle = 'Pagos PSE';
$currentPage = 'pagos_pse';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-credit-card me-2"></i>Pagos PSE</span>
    <span class="small text-white-50" id="infoTotal"></span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:280px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="PSE ID, cédula o nombre…">
        </div>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label><select class="form-select" id="fEstado" style="width:140px"><option value="">Todos</option><option value="Aprobada">Aprobada</option><option value="Rechazada">Rechazada</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Conciliado</label><select class="form-select" id="fConciliado" style="width:145px"><option value="">Todos</option><option value="1">Conciliados</option><option value="0">Sin conciliar</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Asignación</label><select class="form-select" id="fAsignacion" style="width:135px"><option value="">Todos</option><option value="0">Sin asignar</option><option value="parcial">Parcial</option><option value="completo">Completo</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control" id="fDesde" style="width:140px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control" id="fHasta" style="width:140px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="pse_id">PSE ID</th><th class="sortable text-end" data-col="valor">Valor</th>
          <th class="sortable text-end" data-col="usado">Usado</th><th class="sortable text-end" data-col="restante">Restante</th>
          <th class="sortable" data-col="estado">Estado</th><th>Banco</th><th class="sortable" data-col="referencia_2">Cédula</th><th>Nombre</th>
          <th>Conciliado</th><th class="sortable" data-col="fecha_resolucion">Fecha</th><th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="11" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Modal Asignar Confiar -->
<div class="modal fade" id="mConciliar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-link me-2"></i>Asignar Confiar</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form onsubmit="return guardarConciliacion(event)"><div class="modal-body"><input type="hidden" id="cc_pse_id">
    <div class="mb-2 position-relative"><label class="form-label">Confiar ID <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="cc_confiar_input" placeholder="Buscar CONF... o descripción" autocomplete="off">
      <input type="hidden" id="cc_confiar_id">
      <div id="cc_confiar_res" class="list-group position-absolute w-100" style="top:100%;left:0;z-index:1070;max-height:220px;overflow:auto;background:#fff;border:1px solid #dee2e6;border-top:none;box-shadow:0 4px 10px rgba(0,0,0,0.1)"></div>
    </div>
  </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand">Asignar</button></div></form>
</div></div></div>

<!-- Modal Detalle Confiar -->
<div class="modal fade" id="mConfiarDetalle" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalle Confiar</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="confiarDetalleContent"></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>,PAGE_SIZE=20,alertBox=document.getElementById('alert');
let state={q:'',estado:'',conciliado:'',asignacion:'',desde:'',hasta:'',page:0,order:'fecha_resolucion',dir:'DESC'};
let mConciliar,mConfiarDetalle,debounceTimer;
let confiarData={};

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
function fmtD(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleDateString('es-CO');}
async function apicall(p,o){let r=await fetch(API+p,o),j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

document.querySelectorAll('.sortable').forEach(th=>{th.addEventListener('click',()=>{let c=th.dataset.col;if(state.order===c)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=c;state.dir='ASC'}state.page=0;cargar();});});
function renderSortIcons(){document.querySelectorAll('.sortable').forEach(th=>{let c=th.dataset.col,a=state.order===c;th.classList.toggle('active',a);let t=th.textContent.trim().replace(/[▲▼]/g,''),ar=a?(state.dir==='ASC'?'▲':'▼'):'';th.innerHTML=t+(ar?` <span class="sort-icon">${ar}</span>`:'');});}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
['fEstado','fConciliado','fAsignacion'].forEach(id=>document.getElementById(id).addEventListener('change',function(){state[id.substring(1)]=this.value;state.page=0;cargar();}));
['fDesde','fHasta'].forEach(id=>document.getElementById(id).addEventListener('change',function(){state[id.substring(1)]=this.value;state.page=0;cargar();}));

async function cargar(){
  let o=state.page*PAGE_SIZE,bs='';
  if(state.q)bs+='&q='+encodeURIComponent(state.q);if(state.estado)bs+='&estado='+state.estado;
  if(state.conciliado)bs+='&conciliado='+state.conciliado;if(state.asignacion)bs+='&asignacion='+state.asignacion;if(state.desde)bs+='&fecha_desde='+state.desde;if(state.hasta)bs+='&fecha_hasta='+state.hasta;
  try{
    let d=await apicall(`pagos/listar?tipo=pse&limit=${PAGE_SIZE}&offset=${o}&order=${state.order}&dir=${state.dir}${bs}`);
    let t=d.total||0;document.getElementById('infoTotal').textContent=t>0?`Mostrando ${o+1}-${Math.min(o+PAGE_SIZE,t)} de ${t}`:'';renderSortIcons();
    confiarData={};
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>{
      let acc='';if(r.conciliado_confiar){acc=`<button class="btn btn-sm btn-outline-danger" onclick="desconciliar('${esc(r.pse_id)}','${esc(r.conciliado_confiar)}')" title="Desasignar"><i class="fas fa-unlink"></i></button>`;
      }else{acc=`<button class="btn btn-sm btn-outline-primary" onclick="abrirConciliar('${esc(r.pse_id)}')" title="Asignar Confiar"><i class="fas fa-link"></i></button>`;}
      let cb='—';if(r.conciliado_confiar&&r.confiar_fecha){confiarData[r.pse_id]={fecha:r.confiar_fecha,descripcion:r.confiar_descripcion||'',documento:r.confiar_documento||'',oficina:r.confiar_oficina||'',valor_consignacion:r.confiar_valor,valor_retiro:r.confiar_retiro,saldo:r.confiar_saldo,tipo_transaccion:r.confiar_tipo||''};cb=`<a href="#" class="badge text-bg-info text-decoration-none" onclick="verConfiarDetalle('${esc(r.pse_id)}')">${esc(r.conciliado_confiar)}</a>`;}
      return`<tr><td><small>${esc(r.pse_id)}</small></td><td class="text-end">${money(r.valor)}</td><td class="text-end">${money(r.usado)}</td><td class="text-end">${money(r.restante)}</td><td><span class="badge text-bg-${r.estado==='Aprobada'?'success':'danger'}">${esc(r.estado)}</span></td><td>${esc(r.banco_originador)}</td><td>${esc(r.referencia_2)}</td><td>${esc(r.referencia_3)}</td><td>${cb}</td><td><small>${fmtDt(r.fecha_resolucion)}</small></td><td>${acc}</td></tr>`;
    }).join('')||'<tr><td colspan="11" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(t);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

function renderPagination(t){let N=Math.ceil(t/PAGE_SIZE);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}let C=state.page,pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;}
function goPage(p){state.page=p;cargar();}

function abrirConciliar(id){document.getElementById('cc_pse_id').value=id;document.getElementById('cc_confiar_id').value='';document.getElementById('cc_confiar_input').value='';document.getElementById('cc_confiar_res').innerHTML='';mConciliar.show();}
async function guardarConciliacion(e){e.preventDefault();let d={pse_id:document.getElementById('cc_pse_id').value,confiar_id:document.getElementById('cc_confiar_id').value.trim()};if(!d.confiar_id)return;try{await apicall('pagos/conciliacion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});mConciliar.hide();cargar();}catch(e){alert(e.message);}}
function setupConfiarAuto(){
  const input=document.getElementById('cc_confiar_input'),hidden=document.getElementById('cc_confiar_id'),cont=document.getElementById('cc_confiar_res');
  input.addEventListener('input',function(){
    const q=(this.value||'').trim();hidden.value='';clearTimeout(debounceTimer);
    if(q.length<1){cont.innerHTML='';return;}
    cont.innerHTML='<div class="list-group-item text-muted">Buscando…</div>';
    debounceTimer=setTimeout(async()=>{
      try{
        const d=await apicall('pagos/listar?tipo=confiar&q='+encodeURIComponent(q)+'&limit=10');
        const rows=(d.rows||[]).filter(r=>Number(r.restante||0)>0 && (!r.confirmacion_estado||r.confirmacion_estado==='pendiente'));
        if(!rows.length){cont.innerHTML='<div class="list-group-item text-muted">Sin Confiar disponibles</div>';return;}
        cont.innerHTML=rows.map(r=>`<button type="button" class="list-group-item list-group-item-action" data-id="${esc(r.confiar_id)}" data-txt="${esc(r.confiar_id)} · ${esc(r.descripcion||'')} · $${Number(r.restante||0).toLocaleString('es-CO')} disp.">${esc(r.confiar_id)} — <small>${esc(r.descripcion||'')}</small> <span class="text-muted">$${Number(r.restante||0).toLocaleString('es-CO')} disp.</span></button>`).join('');
        cont.querySelectorAll('button').forEach(b=>b.addEventListener('mousedown',e=>{e.preventDefault();hidden.value=b.dataset.id;input.value=b.dataset.txt||b.dataset.id;cont.innerHTML='';}));
      }catch(_){cont.innerHTML='<div class="list-group-item text-danger">Error de búsqueda</div>';}
    },300);
  });
  document.addEventListener('mousedown',e=>{if(!cont.contains(e.target)&&e.target!==input)cont.innerHTML='';});
}
async function desconciliar(p,c){if(!confirm('¿Desasignar conciliación?'))return;try{await apicall('pagos/conciliacion',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({pse_id:p,confiar_id:c})});cargar();}catch(e){alert(e.message);}}

function verConfiarDetalle(pseId){let d=confiarData[pseId];if(!d){alert('Sin datos');return;}let h=`<h6 class="text-brand mb-2">${esc(pseId)}</h6><hr><div class="row small"><div class="col-md-6 mb-1"><strong>Fecha:</strong> ${fmtD(d.fecha)}</div><div class="col-md-6 mb-1"><strong>Documento:</strong> ${esc(d.documento)}</div><div class="col-md-6 mb-1"><strong>Oficina:</strong> ${esc(d.oficina)}</div><div class="col-md-6 mb-1"><strong>Tipo:</strong> ${esc(d.tipo_transaccion)}</div><div class="col-md-6 mb-1"><strong>Valor consignación:</strong> ${money(d.valor_consignacion)}</div><div class="col-md-6 mb-1"><strong>Valor retiro:</strong> ${money(d.valor_retiro)}</div><div class="col-md-6 mb-1"><strong>Saldo:</strong> ${money(d.saldo)}</div></div><div class="mt-2"><strong>Descripción:</strong><br><span class="small">${esc(d.descripcion)}</span></div>`;document.getElementById('confiarDetalleContent').innerHTML=h;mConfiarDetalle.show();}

document.addEventListener('DOMContentLoaded',()=>{mConciliar=new bootstrap.Modal('#mConciliar');mConfiarDetalle=new bootstrap.Modal('#mConfiarDetalle');setupConfiarAuto();cargar();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
