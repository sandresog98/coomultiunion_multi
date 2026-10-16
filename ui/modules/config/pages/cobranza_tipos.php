<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('cobranza');
$acc = ui_acciones('cobranza_tipos', $staff['rol'] ?? '');
$canCrear = ui_accion('cobranza_tipos', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('cobranza_tipos', 'editar',   $staff['rol'] ?? '');
$pageTitle = 'Tipos de comunicación';
$currentPage = 'cobranza_tipos';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-comments me-2"></i>Tipos de comunicación (cobranza)</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="abrir()"><i class="fas fa-plus me-1"></i>Nuevo</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:260px"><label class="form-label small text-muted mb-0">Buscar</label><div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Tipo, estado o label…"></div></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th class="sortable" data-col="label">Label</th><th class="sortable" data-col="tipo">Tipo</th><th class="sortable" data-col="estado">Estado</th><th class="sortable" data-col="tipo_origen">Origen</th><th class="text-center sortable" data-col="activo">Activo</th><th class="text-end">Acciones</th></tr></thead>
        <tbody id="tbody"><tr><td colspan="6" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>
<div class="modal fade" id="m" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mt">Nuevo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Tipo</label>
      <select class="form-select" id="f_tipo"><option value="llamada">Llamada</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option><option value="sms">Mensaje de texto</option></select>
    </div>
    <div class="mb-2"><label class="form-label">Estado</label>
      <select class="form-select" id="f_estado"><option value="sin_respuesta">Sin respuesta</option><option value="informa_pago">Informa de pago</option><option value="comprometido">Comprometido a pagar</option><option value="pendiente">Pendiente</option></select>
    </div>
    <div class="mb-2"><label class="form-label">Label</label><input class="form-control" id="f_label" placeholder="ej. WhatsApp · Sin respuesta"></div>
    <div class="mb-2"><label class="form-label">Tipo de mora</label>
      <select class="form-select" id="f_origen"><option value="ambos">Ambos</option><option value="credito">Crédito</option><option value="aportes">Aportes</option></select>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardar()">Guardar</button></div>
</div></div></div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>, alertBox=document.getElementById('alert');
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['cobranza_tipos.editar']===true;
let state={q:'',page:0,size:20,order:'tipo',dir:'ASC'}, editId=null, modal, debounceTimer;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
document.querySelectorAll('.sortable').forEach(th=>{th.addEventListener('click',()=>{
  const col=th.dataset.col;
  if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';
  else{state.order=col;state.dir='ASC';}
  state.page=0;cargar();
});});
function renderSortIcons(){
  document.querySelectorAll('.sortable').forEach(th=>{
    const col=th.dataset.col,active=state.order===col;
    th.classList.toggle('active',active);
    const txt=th.textContent.trim().replace(/[▲▼]/g,'');
    const arrow=active?(state.dir==='ASC'?'▲':'▼'):'';
    th.innerHTML=txt+(arrow?` <span class="sort-icon">${arrow}</span>`:'');
  });
}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
async function cargar(){
  const off=state.page*state.size;
  try{
    const d=await api('cobranza/tipos?q='+encodeURIComponent(state.q)+'&order='+state.order+'&dir='+state.dir+'&limit='+state.size+'&offset='+off);
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${off+1}-${Math.min(off+state.size,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>`<tr>
      <td><span class="fw-semibold">${esc(r.label)}</span></td>
      <td><span class="badge text-bg-light">${esc(r.tipo)}</span></td><td><span class="badge text-bg-info">${esc(r.estado)}</span></td>
      <td><small>${esc(r.tipo_origen)}</small></td>
      <td class="text-center">${r.activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-secondary">Inactivo</span>'}</td>
      <td class="text-end">
        ${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary me-1" onclick='abrir(${JSON.stringify(r)})'><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm ${r.activo==1?'btn-outline-danger':'btn-outline-success'}" onclick="toggle(${JSON.stringify(r)})"><i class="fas ${r.activo==1?'fa-ban':'fa-check'}"></i></button>`:''}
      </td></tr>`).join('')||'<tr><td colspan="6" class="text-center text-muted">Sin registros</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}
function renderPagination(t){
  const N=Math.ceil(t/state.size);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}
  const C=state.page;let pages=[];
  if(N<=10){for(let i=0;i<N;i++)pages.push(i);}
  else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}
function goPage(p){state.page=p;cargar();}
function abrir(r){
  editId=r?r.id:null;document.getElementById('mt').textContent=editId?'Editar':'Nuevo';
  document.getElementById('f_tipo').value=r?(r.tipo||'llamada'):'llamada';
  document.getElementById('f_estado').value=r?(r.estado||'sin_respuesta'):'sin_respuesta';
  document.getElementById('f_label').value=r?(r.label||''):'';
  document.getElementById('f_origen').value=r?(r.tipo_origen||'ambos'):'ambos';
  modal.show();
}
async function guardar(){
  const d={tipo:document.getElementById('f_tipo').value,estado:document.getElementById('f_estado').value,
    label:document.getElementById('f_label').value.trim(),tipo_origen:document.getElementById('f_origen').value};
  if(!d.label){alert('El label es obligatorio.');return;}
  try{
    if(editId)await api('cobranza/tipo_catalogo',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:editId,...d})});
    else await api('cobranza/tipo_catalogo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});
    modal.hide();cargar();
  }catch(e){alert(e.message);}
}
async function toggle(r){
  if(!confirm(`${r.activo==1?'Inactivar':'Activar'} '${r.label}'?`))return;
  try{await api('cobranza/tipo_catalogo',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:r.id,activo:r.activo==1?0:1})});cargar();}catch(e){alert(e.message);}
}
document.addEventListener('DOMContentLoaded',()=>{modal=new bootstrap.Modal('#m');window.Listado.mountSize('fSizeBox', state, cargar);cargar();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>