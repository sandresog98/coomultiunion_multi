<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.catalogo');
$rol = $staff['rol'] ?? '';
$canCarga = ui_accion('tienda.catalogo', 'carga', $rol);
$pageTitle = 'Carga masiva de catálogo';
$currentPage = 'tienda_catalogo_carga';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-file-import me-2"></i>Carga masiva de catálogo</span>
    <span class="small text-white-50" id="infoCarga"></span>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="border rounded p-3 h-100">
          <h6 class="text-brand"><i class="fas fa-download me-2"></i>1. Plantilla</h6>
          <p class="small text-muted mb-2">Descarga la plantilla con el catálogo actual. Completa o ajusta las filas usando los <strong>nombres</strong> de categoría, marca y producto.</p>
          <a class="btn btn-outline-primary btn-sm" href="<?php echo apiUrl(); ?>tienda/carga/plantilla">
            <i class="fas fa-file-excel me-1"></i>Descargar plantilla
          </a>
        </div>
      </div>
      <div class="col-md-6">
        <div class="border rounded p-3 h-100">
          <h6 class="text-brand"><i class="fas fa-upload me-2"></i>2. Subir archivo</h6>
          <form id="frmCarga">
            <input class="form-control form-control-sm mb-2" type="file" id="archivo" accept=".xls,.xlsx" required>
            <button class="btn btn-brand btn-sm" id="btnAnalizar" type="submit" <?php echo $canCarga ? '' : 'disabled'; ?>>
              <i class="fas fa-magnifying-glass me-1"></i>Analizar archivo
            </button>
          </form>
          <div class="small text-muted mt-2">Máximo 5 MB y 1.000 filas.</div>
        </div>
      </div>
    </div>

    <div id="validacion" class="d-none">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div class="small" id="resumenCarga"></div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-danger btn-sm" onclick="cancelarCarga()"><i class="fas fa-xmark me-1"></i>Cancelar carga</button>
          <button class="btn btn-brand btn-sm" id="btnCommit" onclick="revisarCommit()" <?php echo $canCarga ? '' : 'disabled'; ?>>
            <i class="fas fa-check me-1"></i>Revisar y confirmar
          </button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>#</th><th>Estado</th><th>Categoría</th><th>Marca</th><th>Nombre</th><th>Descripción</th>
            <th class="text-end">P. compra</th><th class="text-end">P. venta</th><th class="text-center">Activo</th><th class="text-center">Agotado</th><th>Acciones</th>
          </tr></thead>
          <tbody id="filasTbody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Confirmación de creación de categoría/marca -->
<div class="modal fade" id="mMaestro" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-circle-question me-2"></i>Confirmar creación</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p id="maestroTexto" class="mb-0"></p>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Corregir</button>
    <button class="btn btn-brand" onclick="confirmarMaestro()"><i class="fas fa-plus me-1"></i>Crear</button>
  </div>
</div></div></div>

<!-- Confirmación final -->
<div class="modal fade" id="mCommit" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-check-double me-2"></i>Confirmar carga</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="commitResumen"></div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Revisar nuevamente</button>
    <button class="btn btn-brand" onclick="confirmarCommit()"><i class="fas fa-cloud-arrow-up me-1"></i>Confirmar carga</button>
  </div>
</div></div></div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const alertBox = document.getElementById('alert');
let filas = [];
let resumen = null;
let mMaestro = null, mCommit = null;
let maestroPendiente = null;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function showAlert(msg, ok=false){alertBox.className='alert alert-'+(ok?'success':'danger');alertBox.textContent=msg;alertBox.classList.remove('d-none');}
function hideAlert(){alertBox.classList.add('d-none');}
async function api(p,opt){const r=await fetch(API+p,opt);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

function estadoBadge(e){
  const map={crear:['Crear','success'],actualizar:['Actualizar','primary'],sin_cambios:['Sin cambios','secondary'],requiere_confirmacion:['Confirmar','warning'],error:['Error','danger']};
  const x=map[e]||[e,'secondary'];
  return `<span class="badge text-bg-${x[1]}">${x[0]}</span>`;
}

function resumenTexto(r){
  if(!r) return '';
  return `Total ${r.total} · Crear ${r.crear} · Actualizar ${r.actualizar} · Sin cambios ${r.sin_cambios} · Confirmar ${r.requiere_confirmacion} · Errores ${r.errores}`;
}

function render(){
  const box=document.getElementById('validacion');
  if(!filas.length){box.classList.add('d-none');document.getElementById('infoCarga').textContent='';return;}
  box.classList.remove('d-none');
  document.getElementById('infoCarga').textContent=resumenTexto(resumen);
  document.getElementById('resumenCarga').textContent=resumenTexto(resumen);

  document.getElementById('filasTbody').innerHTML=filas.map(f=>{
    const d=f.datos||{};
    const errores=Object.values(f.errores||{}).filter(Boolean).join(' · ');
    const puedeCrearCat=!!f.puede_crear_categoria;
    const puedeCrearMarca=!!f.puede_crear_marca;
    return `<tr id="fila_${esc(f.id)}">
      <td>${f.indice}</td>
      <td>${estadoBadge(f.estado)}${errores?`<div class="small text-danger">${esc(errores)}</div>`:''}</td>
      <td><input class="form-control form-control-sm" data-field="categoria" value="${esc(d.categoria||'')}" style="min-width:150px"></td>
      <td><input class="form-control form-control-sm" data-field="marca" value="${esc(d.marca||'')}" style="min-width:130px"></td>
      <td><input class="form-control form-control-sm" data-field="nombre" value="${esc(d.nombre||'')}" style="min-width:180px"></td>
      <td><input class="form-control form-control-sm" data-field="descripcion" value="${esc(d.descripcion||'')}" style="min-width:220px"></td>
      <td><input type="number" step="0.01" class="form-control form-control-sm text-end" data-field="precio_compra_sugerido" value="${d.precio_compra_sugerido ?? ''}" style="min-width:100px"></td>
      <td><input type="number" step="0.01" class="form-control form-control-sm text-end" data-field="precio_venta_sugerido" value="${d.precio_venta_sugerido ?? ''}" style="min-width:100px"></td>
      <td class="text-center"><input type="checkbox" class="form-check-input" data-field="estado_activo" ${d.estado_activo?'checked':''}></td>
      <td class="text-center"><input type="checkbox" class="form-check-input" data-field="agotado" ${d.agotado?'checked':''}></td>
      <td class="text-nowrap">
        <button class="btn btn-sm btn-outline-primary me-1" onclick="guardarFila('${esc(f.id)}')" title="Guardar fila"><i class="fas fa-save"></i></button>
        ${puedeCrearCat?`<button class="btn btn-sm btn-outline-warning me-1" onclick="abrirMaestro('${esc(f.id)}','categoria')" title="Crear categoría">Cat.</button>`:''}
        ${puedeCrearMarca?`<button class="btn btn-sm btn-outline-warning" onclick="abrirMaestro('${esc(f.id)}','marca')" title="Crear marca">Marca</button>`:''}
      </td>
    </tr>`;
  }).join('');
}

function leerFila(id){
  const tr=document.getElementById('fila_'+id);
  if(!tr) return null;
  const datos={};
  tr.querySelectorAll('[data-field]').forEach(el=>{
    const k=el.dataset.field;
    if(el.type==='checkbox') datos[k]=el.checked?1:0;
    else datos[k]=el.value;
  });
  return datos;
}

async function guardarFila(id){
  hideAlert();
  const datos=leerFila(id);
  if(!datos) return;
  try{
    const d=await api('tienda/carga/actualizar_fila',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({fila_id:id,datos})});
    filas=d.filas; resumen=d.resumen; render();
    showAlert('Fila actualizada.', true);
  }catch(e){showAlert(e.message);}
}

function abrirMaestro(id, tipo){
  const f=filas.find(x=>x.id===id);
  if(!f) return;
  const nombre = tipo==='categoria' ? (f.datos.categoria||'') : (f.datos.marca||'');
  maestroPendiente={id,tipo};
  document.getElementById('maestroTexto').innerHTML =
    `La <strong>${tipo}</strong> <b>${esc(nombre)}</b> no existe.<br>¿Deseas crearla y usarla para esta fila?`;
  mMaestro.show();
}

async function confirmarMaestro(){
  if(!maestroPendiente) return;
  const {id,tipo}=maestroPendiente;
  const datos=leerFila(id)||{};
  if(tipo==='categoria') datos.crear_categoria=true;
  else datos.crear_marca=true;
  try{
    const d=await api('tienda/carga/actualizar_fila',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({fila_id:id,datos})});
    filas=d.filas; resumen=d.resumen; render();
    mMaestro.hide(); maestroPendiente=null;
    showAlert('Confirmación registrada. Se creará al momento del commit.', true);
  }catch(e){showAlert(e.message);}
}

async function revisarCommit(){
  hideAlert();
  try{
    const r=await api('tienda/carga/resumen_commit');
    resumen=r;
    if(r.bloqueado){
      render();
      showAlert(`Hay ${r.errores} fila(s) con error y ${r.requiere_confirmacion} pendiente(s) de confirmación. Corrígelas antes de continuar.`);
      return;
    }
    let html=`<p>Se aplicará la siguiente carga:</p><ul class="mb-0">
      <li>Productos nuevos: <b>${r.crear}</b></li>
      <li>Productos a actualizar: <b>${r.actualizar}</b></li>
      <li>Sin cambios: <b>${r.sin_cambios}</b></li>
      <li>Categorías nuevas: <b>${r.nuevas_categorias.length}</b>${r.nuevas_categorias.length?' ('+esc(r.nuevas_categorias.join(', '))+')':''}</li>
      <li>Marcas nuevas: <b>${r.nuevas_marcas.length}</b>${r.nuevas_marcas.length?' ('+esc(r.nuevas_marcas.join(', '))+')':''}</li>
    </ul>`;
    document.getElementById('commitResumen').innerHTML=html;
    mCommit.show();
  }catch(e){showAlert(e.message);}
}

async function confirmarCommit(){
  hideAlert();
  try{
    const d=await api('tienda/carga/commit',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({confirmar:true})});
    mCommit.hide();
    filas=[]; resumen=null; render();
    showAlert(`Carga aplicada: ${d.creados} creado(s), ${d.actualizados} actualizado(s), ${d.categorias_creadas} categoría(s), ${d.marcas_creadas} marca(s).`, true);
  }catch(e){showAlert(e.message);}
}

async function cancelarCarga(){
  if(!confirm('¿Cancelar la carga activa? Se perderán las filas analizadas.')) return;
  try{await api('tienda/carga/cancelar',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});filas=[];resumen=null;render();showAlert('Carga cancelada.',true);}catch(e){showAlert(e.message);}
}

document.getElementById('frmCarga').addEventListener('submit', async (e)=>{
  e.preventDefault(); hideAlert();
  const file=document.getElementById('archivo').files[0];
  if(!file){showAlert('Seleccione un archivo.');return;}
  const fd=new FormData(); fd.append('archivo',file);
  const btn=document.getElementById('btnAnalizar'); btn.disabled=true;
  try{
    const d=await api('tienda/carga/analizar',{method:'POST',body:fd});
    filas=d.filas; resumen=d.resumen; render();
    showAlert(`Archivo analizado: ${d.resumen.total} fila(s).`, true);
  }catch(e){showAlert(e.message);}
  finally{btn.disabled=false;}
});

async function cargarPendientes(){
  try{
    const d=await api('tienda/carga/pendientes');
    if(d.activa){filas=d.filas;resumen=d.resumen;render();}
  }catch(_){}
}

document.addEventListener('DOMContentLoaded',()=>{
  mMaestro=new bootstrap.Modal('#mMaestro');
  mCommit=new bootstrap.Modal('#mCommit');
  cargarPendientes();
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
