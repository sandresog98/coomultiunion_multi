<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('cargas');
$canProcesar = ui_accion('cargas', 'procesar', $staff['rol'] ?? '');
$pageTitle = 'Cargas de Archivos';
$currentPage = 'cargas';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header brand"><i class="fas fa-file-import me-2"></i>Cargar archivo</div>
      <div class="card-body">
        <div id="alert" class="alert d-none"></div>
        <form id="form">
          <div class="mb-3">
            <label class="form-label fw-semibold">Tipo de archivo</label>
            <select class="form-select" id="tipo" required>
              <option value="" disabled selected>Seleccione…</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Archivo (.xls / .xlsx)</label>
            <input class="form-control" type="file" id="archivo" accept=".xls,.xlsx" required>
            <div class="form-text" id="hintConfiar" style="display:none">Pagos Confiar: el archivo debe traer una sola fecha. Si la fecha ya existe, se pedirá confirmación.</div>
          </div>
          <?php if ($canProcesar): ?>
          <button class="btn btn-brand w-100" id="btn" type="submit"><i class="fas fa-upload me-2"></i>Cargar y procesar</button>
          <?php endif; ?>
          <div class="progress mt-3 d-none" id="prog"><div class="progress-bar progress-bar-striped progress-bar-animated brand-gradient" style="width:100%">Procesando…</div></div>
        </form>
        <p class="text-muted small mt-3 mb-0">Las tablas Sifone son la base de información; la carga <strong>reemplaza</strong> los datos del tipo seleccionado (movimientos: por año).</p>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clock-rotate-left me-2"></i>Historial</span>
        <button class="btn btn-light btn-sm" onclick="cargar()"><i class="fas fa-rotate"></i></button>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Estado</th><th class="text-end">Registros</th><th>Usuario</th></tr></thead>
            <tbody id="tbody"><tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const alertBox = document.getElementById('alert');
function showAlert(m, ok=false, warning=false){ alertBox.className='alert alert-'+(warning?'warning':(ok?'success':'danger')); alertBox.textContent=m; alertBox.classList.remove('d-none'); }
function esc(s){ return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
function badge(e){ const m={completado:'success',error:'danger',procesando:'warning',pendiente:'secondary'}; return `<span class="badge text-bg-${m[e]||'secondary'}">${esc(e)}</span>`; }

let tipoLabels = {};
async function cargar(){
  const r = await fetch(API+'cargas/listar'); const j = await r.json();
  if(!j.ok){ showAlert(j.error); return; }
  tipoLabels = j.data.etiquetas || {};
  const sel=document.getElementById('tipo');
  if(sel.options.length<=1){ j.data.tipos.forEach(t=>{ const o=document.createElement('option'); o.value=t; o.textContent=tipoLabels[t]||t; sel.appendChild(o); }); }
  document.getElementById('tbody').innerHTML = j.data.historial.map(h=>`
    <tr><td>${esc(h.fecha_creacion)}</td><td><code>${esc(tipoLabels[h.tipo]||h.tipo)}</code></td><td>${badge(h.estado)}</td>
    <td class="text-end">${h.registros ?? ''}</td><td>${esc(h.usuario||'')}</td></tr>`).join('')
    || '<tr><td colspan="5" class="text-center text-muted">Sin cargas</td></tr>';
}
async function enviarCarga(tipo, file, confirmar){
  const fd=new FormData(); fd.append('tipo',tipo); fd.append('archivo',file);
  if(confirmar) fd.append('confirmar','1');
  const r=await fetch(API+'cargas/procesar',{method:'POST',body:fd}); const j=await r.json();
  if(!j.ok) throw new Error(j.error||'Error');
  if(j.data && j.data.requiere_confirmacion && !confirmar){
    if(window.confirm(j.data.mensaje)){
      return enviarCarga(tipo,file,true);
    }
    return {cancelado:true};
  }
  return j.data;
}

document.getElementById('tipo').addEventListener('change',function(){
  const hint=document.getElementById('hintConfiar');
  if(hint) hint.style.display=this.value==='pagos_confiar'?'':'none';
});

document.getElementById('form').addEventListener('submit', async (e)=>{
  e.preventDefault(); alertBox.classList.add('d-none');
  const tipo=document.getElementById('tipo').value, file=document.getElementById('archivo').files[0];
  if(!tipo||!file){ showAlert('Seleccione tipo y archivo'); return; }
  document.getElementById('btn').disabled=true; document.getElementById('prog').classList.remove('d-none');
  try{
    const data=await enviarCarga(tipo,file,false);
    if(data && data.cancelado){
      showAlert('Carga cancelada. No se insertaron registros.', false, true);
    }else{
      showAlert(`Cargado: ${data.insertados} registros (${data.tipo}).`, true);
      document.getElementById('form').reset();
      document.getElementById('hintConfiar').style.display='none';
    }
  }catch(err){ showAlert(err.message); }
  finally{ document.getElementById('btn').disabled=false; document.getElementById('prog').classList.add('d-none'); cargar(); }
});
document.addEventListener('DOMContentLoaded', cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
