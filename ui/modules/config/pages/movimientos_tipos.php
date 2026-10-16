<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.movimientos_tipos');
$acc = ui_acciones('movimientos_tipos', $staff['rol'] ?? '');
$canCrear = ui_accion('movimientos_tipos', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('movimientos_tipos', 'editar',   $staff['rol'] ?? '');
$canElim  = ui_accion('movimientos_tipos', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Tipos de movimiento';
$currentPage = 'movimientos_tipos';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-tags me-2"></i>Tipos de movimiento</span>
    <span class="small text-white-50" id="infoTotal"></span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="abrir()"><i class="fas fa-plus me-1"></i>Nuevo</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:280px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Código o nombre…"></div>
      </div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th class="sortable" data-col="tipo">Código</th><th class="sortable" data-col="nombre">Nombre</th><th class="sortable" data-col="clasificacion">Clasificación</th><th class="text-center sortable" data-col="requiere_referido">Referido</th><th class="text-center sortable" data-col="valida_disponible">Valida disp.</th><th class="text-center sortable" data-col="activo">Activo</th><th class="text-center sortable" data-col="en_uso">Nº movs.</th><th class="text-end">Acciones</th></tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<div class="modal fade" id="m" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mt">Nuevo tipo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input class="form-control" id="f_nombre" placeholder="ej. Bonificación" autocomplete="off" oninput="previewCodigo()">
      <div class="form-text" id="codigoPreview"></div>
    </div>
    <div class="mb-2"><label class="form-label">Clasificación <span class="text-danger">*</span></label>
      <select class="form-select" id="f_clasificacion"><option value="ingreso">Ingreso (+)</option><option value="egreso">Egreso (−)</option></select>
    </div>
    <div class="d-flex gap-4">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="f_referido"><label class="form-check-label" for="f_referido">Requiere referido</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="f_valida_disponible"><label class="form-check-label" for="f_valida_disponible">Valida disponible</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="f_activo" checked><label class="form-check-label" for="f_activo">Activo</label></div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-brand" onclick="guardar()"><i class="fas fa-save me-1"></i>Guardar</button>
  </div>
</div></div></div>

<input type="hidden" id="editTipo">


<script>
const API=<?php echo json_encode(apiUrl()); ?>, PAGE_SIZE=20, alertBox=document.getElementById('alert');
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['movimientos_tipos.editar']===true,CAN_ELIM=ACC['movimientos_tipos.eliminar']===true;
let state={q:'',page:0,order:'nombre',dir:'ASC'}, editTipo=null, modal, debounceTimer;

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
  const off=state.page*PAGE_SIZE;
  try{
    const d=await api('movimientos_tipos/listar?q='+encodeURIComponent(state.q)+'&order='+state.order+'&dir='+state.dir+'&limit='+PAGE_SIZE+'&offset='+off);
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>`<tr>
      <td><code>${esc(r.tipo)}</code></td>
      <td><span class="fw-semibold">${esc(r.nombre)}</span></td>
      <td><span class="badge ${r.clasificacion==='egreso'?'text-bg-danger':'text-bg-success'}">${esc(r.clasificacion)}</span></td>
      <td class="text-center">${r.requiere_referido==1?'<i class="fas fa-check text-success"></i>':'—'}</td>
      <td class="text-center">${r.valida_disponible==1?'<i class="fas fa-check text-warning"></i>':'—'}</td>
      <td class="text-center">${r.activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-secondary">Inactivo</span>'}</td>
      <td class="text-center"><span class="badge text-bg-light">${r.en_uso}</span></td>
      <td class="text-end">
        ${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary me-1" onclick='abrir(${JSON.stringify(r)})'><i class="fas fa-edit"></i></button>`:''}
        ${CAN_EDIT?`<button class="btn btn-sm ${r.activo==1?'btn-outline-danger':'btn-outline-success'}" onclick="toggle(${JSON.stringify(r)})"><i class="fas ${r.activo==1?'fa-ban':'fa-check'}"></i></button>`:''}
      </td></tr>`).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin registros</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}
function renderPagination(t){
  const N=Math.ceil(t/PAGE_SIZE);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}
  const C=state.page;let pages=[];
  if(N<=10){for(let i=0;i<N;i++)pages.push(i);}
  else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}
function goPage(p){state.page=p;cargar();}

function previewCodigo(){
  if(editTipo){document.getElementById('codigoPreview').innerHTML='Código: <code>'+esc(editTipo)+'</code>';return;}
  const n=document.getElementById('f_nombre').value;
  const base=n.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').slice(0,30);
  document.getElementById('codigoPreview').innerHTML=base?('Código: <code>'+esc(base)+'</code>'):'Escriba el nombre para generar el código…';
}

function abrir(r){
  editTipo=r?r.tipo:null;
  document.getElementById('mt').textContent=editTipo?'Editar tipo':'Nuevo tipo';
  document.getElementById('f_nombre').value=r?(r.nombre||''):'';
  document.getElementById('f_clasificacion').value=r?(r.clasificacion||'ingreso'):'ingreso';
  document.getElementById('f_referido').checked=r?(r.requiere_referido==1):false;
  document.getElementById('f_valida_disponible').checked=r?(r.valida_disponible==1):false;
  document.getElementById('f_activo').checked=r?(r.activo==1):true;
  document.getElementById('codigoPreview').innerHTML=editTipo?`Código: <code>${esc(r.tipo)}</code>`:'';
  modal.show();
}

async function guardar(){
  const data={
    nombre:document.getElementById('f_nombre').value.trim(),
    clasificacion:document.getElementById('f_clasificacion').value,
    requiere_referido:document.getElementById('f_referido').checked,
    valida_disponible:document.getElementById('f_valida_disponible').checked,
    activo:document.getElementById('f_activo').checked,
  };
  if(!data.nombre){alert('El nombre es obligatorio.');return;}
  try{
    if(editTipo){await api('movimientos_tipos/editar',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({tipo:editTipo,...data})});}
    else{await api('movimientos_tipos/crear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
    modal.hide();cargar();
  }catch(e){alert(e.message);}
}

async function toggle(r){
  const act=r.activo==1?0:1;
  if(!confirm(`¿${act?'Activar':'Inactivar'} el tipo "${r.nombre}"?`))return;
  try{await api('movimientos_tipos/editar',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({tipo:r.tipo,activo:act})});cargar();}
  catch(e){alert(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{modal=new bootstrap.Modal('#m');cargar();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>