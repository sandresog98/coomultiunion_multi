<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('creditos');
$pageTitle = 'Nueva solicitud de crédito';
$currentPage = 'creditos_nueva';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card" style="max-width:760px">
  <div class="card-header brand"><i class="fas fa-industry me-2"></i>Nueva solicitud</div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>
    <div class="row g-3">
      <div class="col-md-6 position-relative">
        <label class="form-label">Asociado <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="fAsocInp" placeholder="Buscar por cédula o nombre" autocomplete="off">
        <input type="hidden" id="fCedula">
        <div id="fAsocRes" class="list-group position-absolute w-100" style="top:100%;left:0;z-index:1070;max-height:200px;overflow:auto;background:#fff;border:1px solid #dee2e6;border-top:none"></div>
      </div>
      <div class="col-md-6"><label class="form-label">Tipo de solicitante <span class="text-danger">*</span></label>
        <select class="form-select" id="fTipo">
          <option>estudiante</option><option>empleado_descuento_nomina</option><option>empleado_sin_descuento</option>
          <option>independiente</option><option>pensionado_descuento_libranza</option><option>pensionado_sin_descuento_libranza</option>
        </select>
      </div>
      <div class="col-md-6"><label class="form-label">Nombre solicitante <span class="text-danger">*</span></label><input class="form-control" id="fNombre"></div>
      <div class="col-md-6"><label class="form-label">Nº identificación</label><input class="form-control" id="fIde"></div>
      <div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" id="fTel"></div>
      <div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" id="fMail"></div>
      <div class="col-md-6"><label class="form-label">Línea de crédito</label><select class="form-select" id="fLinea"><option value="">—</option></select></div>
      <div class="col-md-3"><label class="form-label">Monto deseado $ <span class="text-danger">*</span></label><input type="number" class="form-control" id="fMonto" min="100000" step="1000"></div>
      <div class="col-md-3"><label class="form-label">Cuotas deseadas <span class="text-danger">*</span></label><input type="number" class="form-control" id="fCuotas" min="1" max="120"></div>
      <div class="col-md-6 d-flex align-items-end gap-3 mb-2">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="fCodeudor"><label class="form-check-label" for="fCodeudor">¿Desea codeudor?</label></div>
        <select class="form-select" id="fTipoCodeudor" style="width:auto"><option>no_necesita</option><option>codeudor_dependiente</option><option>codeudor_pensionado</option></select>
      </div>
    </div>
    <button class="btn btn-brand mt-3" onclick="crear()"><i class="fas fa-save me-1"></i>Crear solicitud</button>
  </div>
</div>
<script>
const API=<?php echo json_encode(apiUrl()); ?>, U=<?php echo json_encode(uiBaseUrl()); ?>;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
const inp=document.getElementById('fAsocInp'),hid=document.getElementById('fCedula'),res=document.getElementById('fAsocRes');let deb;
inp.addEventListener('input',()=>{const q=(inp.value||'').trim();hid.value='';clearTimeout(deb);
  if(q.length<1){res.innerHTML='';return;}res.innerHTML='<div class="list-group-item text-muted">Buscando…</div>';
  deb=setTimeout(async()=>{try{const d=await api('asociados/listar?q='+encodeURIComponent(q)+'&limit=10');const rows=d.rows||[];
    if(!rows.length){res.innerHTML='<div class="list-group-item text-muted">Sin resultados</div>';return;}
    res.innerHTML=rows.map(r=>`<button type="button" class="list-group-item list-group-item-action" data-ce="${esc(r.cedula)}" data-nm="${esc(r.nombre)}">${esc(r.cedula)} — ${esc(r.nombre)}</button>`).join('');
    res.querySelectorAll('button').forEach(b=>b.addEventListener('mousedown',e=>{e.preventDefault();hid.value=b.dataset.ce;inp.value=b.dataset.nm+' ('+b.dataset.ce+')';document.getElementById('fNombre').value=b.dataset.nm;res.innerHTML='';}));
  }catch(_){}} ,300);});
document.addEventListener('mousedown',e=>{if(!res.contains(e.target)&&e.target!==inp)res.innerHTML='';});
(async()=>{const l=await api('creditos/lineas');document.getElementById('fLinea').innerHTML='<option value="">—</option>'+l.filter(x=>x.estado_activo==1&&x.mostrar_en_fabrica==1).map(x=>`<option value="${x.id}">${esc(x.nombre_credito)} · ${x.limite_meses} ms</option>`).join('');})();
async function crear(){
  const d={cedula_asociado:hid.value,tipo_solicitante:document.getElementById('fTipo').value,nombre_solicitante:document.getElementById('fNombre').value.trim(),
    numero_identificacion:document.getElementById('fIde').value.trim(),numero_telefono:document.getElementById('fTel').value.trim(),correo_electronico:document.getElementById('fMail').value.trim(),
    monto_deseado:parseFloat(document.getElementById('fMonto').value)||0,numero_cuotas_deseadas:parseInt(document.getElementById('fCuotas').value||'0')||0,
    desea_codeudor:document.getElementById('fCodeudor').checked,tipo_codeudor:document.getElementById('fTipoCodeudor').value,tasa_interes_id:document.getElementById('fLinea').value||0};
  if(!d.cedula_asociado){alert('Seleccione el asociado.');return;}
  try{const r=await api('creditos/crear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});location.href='gestionar_solicitud?id='+r.id;}
  catch(e){const a=document.getElementById('alert');a.className='alert alert-danger';a.textContent=e.message;a.classList.remove('d-none');}
}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>