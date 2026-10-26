<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.transacciones');
$pageTitle = 'Pagos Confiar';
$currentPage = 'pagos_confiar';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-building-columns me-2"></i>Pagos Confiar</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:280px"><label class="form-label small text-muted mb-0">Buscar</label><div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="CONF ID, cédula o descripción…"></div></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Descripción</label><select class="form-select" id="fDescripcion" data-searchable style="width:250px"><option value="Credito ACH" selected>Credito ACH</option><option value="Consignacion Pago QR">Consignacion Pago QR</option><option value="Consignacion en Cuenta Efectivo">Consignacion en Cuenta Efectivo</option><option value="Consignacion por Transf. Agencia Virtual">Consignacion por Transf. Agencia Virtual</option><option value="Cheque">Cheque</option><option value="">Todos</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label><select class="form-select" id="fEstado" data-searchable style="width:140px"><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="asignado">Asignado</option><option value="no_valido">No válido</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Asignación</label><select class="form-select" id="fAsignacion" data-searchable style="width:135px"><option value="">Todos</option><option value="0">Sin asignar</option><option value="parcial">Parcial</option><option value="completo">Completo</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Cruce PSE</label><select class="form-select" id="fPse" data-searchable style="width:145px"><option value="">Todos</option><option value="con">Con PSE</option><option value="sin">Sin PSE</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control" id="fDesde" style="width:140px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control" id="fHasta" style="width:140px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="confiar_id">CONF ID</th><th class="sortable text-end" data-col="valor_consignacion">Valor</th>
          <th class="sortable text-end" data-col="usado">Usado</th><th class="sortable text-end" data-col="restante">Restante</th>
          <th class="sortable text-center" data-col="pse_count">PSE</th>
          <th class="sortable" data-col="descripcion">Descripción</th><th class="sortable" data-col="cedula">Cédula</th>
          <th class="sortable" data-col="confirmacion_estado">Estado</th><th class="text-center">Comp.</th>
          <th class="sortable" data-col="fecha">Fecha</th><th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="11" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Modal Asignar pago -->
<div class="modal fade" id="mConfirmar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Asignar pago</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form id="frmConfirmar" onsubmit="return guardarConfirmacion(event)"><div class="modal-body">
    <input type="hidden" id="cf_confiar_id">
    <div class="mb-2 position-relative"><label class="form-label">Asociado <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="cf_cedula_input" placeholder="Buscar por cédula o nombre…" autocomplete="off">
      <input type="hidden" id="cf_cedula">
      <div id="cf_cedula_res" class="list-group position-absolute w-100" style="top:100%;left:0;z-index:1070;max-height:220px;overflow:auto;background:#fff;border:1px solid #dee2e6;border-top:none;box-shadow:0 4px 10px rgba(0,0,0,0.1)"></div>
    </div>
    <div class="mb-2"><label class="form-label">Comentario</label><textarea class="form-control" id="cf_comentario" rows="2"></textarea></div>
    <div class="mb-2"><label class="form-label">Comprobante</label><input type="file" class="form-control" id="cf_archivo" accept=".pdf,.jpg,.jpeg,.png"></div>
    <div id="cf_upload_status" class="small text-muted"></div>
  </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" id="cf_btn">Asignar</button></div></form>
</div></div></div>

<!-- Modal Rechazar -->
<div class="modal fade" id="mRechazar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-ban me-2"></i>No válido</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form onsubmit="return guardarRechazo(event)"><div class="modal-body"><input type="hidden" id="rj_confiar_id">
    <div class="mb-2"><label class="form-label">Motivo <span class="text-danger">*</span></label><textarea class="form-control" id="rj_motivo" rows="2" required></textarea></div>
  </div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Marcar no válido</button></div></form>
</div></div></div>

<!-- Modal Detalle PSE -->
<div class="modal fade" id="mPseDetalle" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-link me-2"></i>Cruce PSE</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="pseDetalleContent"></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>,alertBox=document.getElementById('alert');
let state={q:'',descripcion:'Credito ACH',estado:'',asignacion:'',pse:'',desde:'',hasta:'',page:0,size:20,order:'fecha',dir:'DESC'};
let mConfirmar,mRechazar,mPseDetalle,debounceTimer;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
async function apicall(p,o){let r=await fetch(API+p,o),j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

document.querySelectorAll('.sortable').forEach(th=>{th.addEventListener('click',()=>{let c=th.dataset.col;if(state.order===c)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=c;state.dir='ASC'}state.page=0;cargar();});});
function renderSortIcons(){document.querySelectorAll('.sortable').forEach(th=>{let c=th.dataset.col,a=state.order===c;th.classList.toggle('active',a);let t=th.textContent.trim().replace(/[▲▼]/g,''),ar=a?(state.dir==='ASC'?'▲':'▼'):'';th.innerHTML=t+(ar?` <span class="sort-icon">${ar}</span>`:'');});}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
document.getElementById('fDescripcion').addEventListener('change',function(){state.descripcion=this.value;state.page=0;cargar();});
document.getElementById('fEstado').addEventListener('change',function(){state.estado=this.value;state.page=0;cargar();});
document.getElementById('fAsignacion').addEventListener('change',function(){state.asignacion=this.value;state.page=0;cargar();});
document.getElementById('fPse').addEventListener('change',function(){state.pse=this.value;state.page=0;cargar();});
['fDesde','fHasta'].forEach(id=>document.getElementById(id).addEventListener('change',function(){state[id.substring(1)]=this.value;state.page=0;cargar();}));

async function cargar(){
  let o=state.page*state.size,bs='';
  if(state.q)bs+='&q='+encodeURIComponent(state.q);if(state.descripcion)bs+='&descripcion='+encodeURIComponent(state.descripcion);if(state.estado)bs+='&estado='+state.estado;
  if(state.asignacion)bs+='&asignacion='+state.asignacion;if(state.pse)bs+='&pse='+state.pse;if(state.desde)bs+='&fecha_desde='+state.desde;if(state.hasta)bs+='&fecha_hasta='+state.hasta;
  try{
    let d=await apicall(`pagos/listar?tipo=confiar&limit=${state.size}&offset=${o}&order=${state.order}&dir=${state.dir}${bs}`);
    let t=d.total||0;document.getElementById('infoTotal').textContent=t>0?`Mostrando ${o+1}-${Math.min(o+state.size,t)} de ${t}`:'';renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>{
      let est=r.confirmacion_estado||'pendiente',acc='';
      if(est==='asignado')acc=`<button class="btn btn-sm btn-outline-warning" onclick="desasignarConfiar('${esc(r.confiar_id)}')" title="Desasignar"><i class="fas fa-undo"></i></button>`;
      else if(est==='pendiente')acc=`<div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" onclick="abrirConfirmar('${esc(r.confiar_id)}')" title="Asignar"><i class="fas fa-user-check"></i></button><button class="btn btn-outline-danger" onclick="abrirRechazar('${esc(r.confiar_id)}')" title="No válido"><i class="fas fa-ban"></i></button></div>`;
      else acc=`<button class="btn btn-sm btn-outline-secondary" onclick="desasignarConfiar('${esc(r.confiar_id)}')" title="Revertir a pendiente"><i class="fas fa-undo"></i></button>`;
      if(Number(r.pse_count||0)>0 && est==='pendiente')acc=acc.replace(/<button[^>]*abrirConfirmar[^>]*>.*?<\/button>/,'');
      if(Number(r.pse_count||0)>0)acc=`<button class="btn btn-sm btn-outline-info me-1" onclick="verPseDetalle('${esc(r.confiar_id)}')" title="Cubierto por ${r.pse_count} PSE; gestiónalo en Pagos PSE"><i class="fas fa-link"></i> PSE</button>`+acc;
      let pseCell=Number(r.pse_count||0)>0
        ? `<button class="btn btn-sm btn-outline-info" onclick="verPseDetalle('${esc(r.confiar_id)}')" title="PSE conciliados: ${r.pse_count} (${money(r.pse_total)})">${r.pse_count}</button><div class="small text-muted">${money(r.pse_total)}</div>`
        : '<span class="text-muted">&#8212;</span>';
      return`<tr><td><small>${esc(r.confiar_id)}</small></td><td class="text-end">${money(r.valor_consignacion)}</td><td class="text-end">${money(r.usado)}</td><td class="text-end">${money(r.restante)}</td><td class="text-center">${pseCell}</td><td class="text-truncate" style="max-width:180px">${esc(r.descripcion)}</td><td>${esc(r.cedula||'—')}</td><td>${est==='asignado'?'<span class="badge text-bg-success">Asignado</span>':est==='no_valido'?'<span class="badge text-bg-danger">No válido</span>':'<span class="badge text-bg-secondary">Pendiente</span>'}</td><td class="text-center">${r.ruta_comprobante?`<a href="${API}pagos/serve_file?f=${encodeURIComponent(r.ruta_comprobante)}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file"></i></a>`:'—'}</td><td><small>${fmtDt(r.fecha)}</small></td><td>${acc}</td></tr>`;
    }).join('')||'<tr><td colspan="11" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(t);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

function renderPagination(t){let N=Math.ceil(t/state.size);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}let C=state.page,pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;}
function goPage(p){state.page=p;cargar();}

function abrirConfirmar(id){document.getElementById('cf_confiar_id').value=id;document.getElementById('cf_cedula').value='';document.getElementById('cf_cedula_input').value='';document.getElementById('cf_cedula_res').innerHTML='';document.getElementById('cf_comentario').value='';document.getElementById('cf_archivo').value='';document.getElementById('cf_upload_status').textContent='';document.getElementById('cf_btn').disabled=false;mConfirmar.show();}
function setupAsociadoAuto(){
  const input=document.getElementById('cf_cedula_input'),hidden=document.getElementById('cf_cedula'),cont=document.getElementById('cf_cedula_res');
  input.addEventListener('input',function(){
    const q=(this.value||'').trim();hidden.value='';clearTimeout(debounceTimer);
    if(q.length<1){cont.innerHTML='';return;}
    cont.innerHTML='<div class="list-group-item text-muted">Buscando…</div>';
    debounceTimer=setTimeout(async()=>{
      try{
        const d=await apicall('asociados/listar?q='+encodeURIComponent(q)+'&limit=10');
        const rows=d.rows||[];
        if(!rows.length){cont.innerHTML='<div class="list-group-item text-muted">Sin resultados</div>';return;}
        cont.innerHTML=rows.map(r=>`<button type="button" class="list-group-item list-group-item-action" data-ce="${esc(r.cedula)}" data-nm="${esc(r.nombre)}">${esc(r.cedula)} — ${esc(r.nombre)}</button>`).join('');
        cont.querySelectorAll('button').forEach(b=>b.addEventListener('mousedown',e=>{e.preventDefault();hidden.value=b.dataset.ce;input.value=b.dataset.nm+' ('+b.dataset.ce+')';cont.innerHTML='';}));
      }catch(_){cont.innerHTML='<div class="list-group-item text-danger">Error de búsqueda</div>';}
    },300);
  });
  document.addEventListener('mousedown',e=>{if(!cont.contains(e.target)&&e.target!==input)cont.innerHTML='';});
}
async function guardarConfirmacion(e){e.preventDefault();let c=document.getElementById('cf_cedula').value.trim();if(!c){alert('Seleccione el asociado');return;}let b=document.getElementById('cf_btn');b.disabled=true;try{let r=null,fi=document.getElementById('cf_archivo');if(fi.files&&fi.files[0]){document.getElementById('cf_upload_status').textContent='Subiendo...';let fd=new FormData();fd.append('archivo',fi.files[0]);r=(await apicall('pagos/upload',{method:'POST',body:fd})).ruta;}await apicall('pagos/confirmar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({confiar_id:document.getElementById('cf_confiar_id').value,cedula:c,comentario:document.getElementById('cf_comentario').value.trim(),ruta_comprobante:r})});mConfirmar.hide();cargar();}catch(e){alert(e.message);b.disabled=false;}}
function abrirRechazar(id){document.getElementById('rj_confiar_id').value=id;document.getElementById('rj_motivo').value='';mRechazar.show();}
async function guardarRechazo(e){e.preventDefault();let d={confiar_id:document.getElementById('rj_confiar_id').value,motivo:document.getElementById('rj_motivo').value.trim()};if(!d.motivo){alert('Ingrese un motivo');return;}try{await apicall('pagos/confirmar',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});mRechazar.hide();cargar();}catch(e){alert(e.message);}}
async function verPseDetalle(confiarId){
  try{
    const d=await apicall(`pagos/listar?tipo=pse&confiar_id=${encodeURIComponent(confiarId)}&limit=200&offset=0`);
    const rows=d.rows||[];
    const total=rows.reduce((s,r)=>s+Number(r.valor||0),0);
    let h=`<h6 class="text-brand mb-1">${esc(confiarId)}</h6><div class="small text-muted mb-2">${rows.length} PSE &middot; ${money(total)}</div>`;
    h += rows.length
      ? `<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>PSE ID</th><th class="text-end">Valor</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>${rows.map(r=>`<tr><td><small>${esc(r.pse_id)}</small></td><td class="text-end">${money(r.valor)}</td><td><span class="badge text-bg-${r.estado==='Aprobada'?'success':'danger'}">${esc(r.estado)}</span></td><td><small>${fmtDt(r.fecha_resolucion)}</small></td></tr>`).join('')}</tbody></table></div>`
      : '<div class="text-muted">Sin PSE.</div>';
    document.getElementById('pseDetalleContent').innerHTML=h;
    mPseDetalle.show();
  }catch(e){alert(e.message);}
}
async function desasignarConfiar(id){if(!confirm('¿Revertir este pago? Volverá a estado pendiente.'))return;try{await apicall('pagos/confirmar',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({confiar_id:id,desasignar:true})});cargar();}catch(e){alert(e.message);}}

document.addEventListener('DOMContentLoaded',()=>{mConfirmar=new bootstrap.Modal('#mConfirmar');mRechazar=new bootstrap.Modal('#mRechazar');mPseDetalle=new bootstrap.Modal('#mPseDetalle');setupAsociadoAuto();window.Listado.mountSize('fSizeBox', state, cargar);cargar();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
