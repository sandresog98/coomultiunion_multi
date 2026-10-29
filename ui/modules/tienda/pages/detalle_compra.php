<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.compras');
$pageTitle = 'Detalle compra';
$currentPage = 'tienda_compras';
$compraId = (int) ($_GET['id'] ?? 0);
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-file-invoice me-2"></i>Detalle compra #<span id="compraNum">…</span></span>
    <span class="d-flex gap-2">
      <button class="btn btn-light btn-sm" id="btnEditar" onclick="toggleEdicion(true)"><i class="fas fa-edit me-1"></i>Habilitar edición</button>
      <button class="btn btn-light btn-sm" id="btnGuardar" style="display:none" onclick="guardar()"><i class="fas fa-check me-1"></i>Guardar cambios</button>
      <a class="btn btn-secondary btn-sm" href="<?php echo uiBaseUrl(); ?>modules/tienda/pages/compras">Volver</a>
    </span>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>
    <div class="row g-3 mb-3">
      <div class="col-md-3"><label class="form-label small text-muted mb-0">Fecha de compra</label>
        <div id="fechaView" class="fw-semibold">—</div></div>
      <div class="col-md-5"><label class="form-label small text-muted mb-0">Observación</label>
        <div id="obsView" class="text-wrap">—</div></div>
      <div class="col-md-4"><label class="form-label small text-muted mb-0">Registrada</label>
        <div id="regView" class="fw-semibold">—</div></div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light"><tr>
          <th>Producto</th><th style="width:90px">Cant.</th><th style="width:140px">P. compra</th>
          <th style="width:150px">P. venta sug.</th><th class="text-center">Vendido</th><th class="text-center">Disponible</th>
          <th class="text-center">Anulado</th><th>Series</th><th style="width:60px"></th>
        </tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <button class="btn btn-outline-primary btn-sm" id="btnNuevoItem" style="display:none" onclick="anadirItem()"><i class="fas fa-plus me-1"></i>Nuevo ítem</button>
  </div>
</div>

<div class="modal fade" id="modalNuevoItem" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Nuevo ítem</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small text-muted mb-0">Producto *</label>
    <input class="form-control mb-2" id="niBuscar" placeholder="Buscar producto…" autocomplete="off">
    <input type="hidden" id="niId">
    <div id="niRes" class="list-group position-absolute" style="z-index:1050;max-width:520px"></div>
    <div class="row g-2 mt-1">
      <div class="col-3"><label class="form-label small text-muted mb-0">Cantidad</label><input type="number" class="form-control" id="niCant" value="1" min="1"></div>
      <div class="col"> <label class="form-label small text-muted mb-0">P. compra</label><input type="number" class="form-control" id="niPc"></div>
      <div class="col"><label class="form-label small text-muted mb-0">P. venta sugerido</label><input type="number" class="form-control" id="niPv"></div>
      <div class="col-12"><label class="form-label small text-muted mb-0">Series (una por línea)</label><textarea class="form-control" id="niImeis" rows="2"></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="aceptarItem()">Agregar</button></div>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const COMPRA_ID=<?php echo $compraId; ?>, alertBox=document.getElementById('alert');
let data=null, editando=false, nuevos=[], borrados=[];
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(m,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=m;alertBox.classList.remove('d-none');}

async function cargar(){
  try{
    data=await api('tienda/compras.php?action=detalle&id='+COMPRA_ID);
    document.getElementById('compraNum').textContent=data.id;
    document.getElementById('fechaView').textContent=data.fecha_compra||'—';
    document.getElementById('obsView').textContent=data.observacion||'—';
    document.getElementById('regView').textContent=String(data.fecha_creacion||'').replace('T',' ');
    render();
  }catch(e){showA(e.message,false);}
}
function render(){
  document.getElementById('tbody').innerHTML=data.detalles.map((d,i)=>`<tr>
    <td class="fw-semibold">${esc(d.producto_nombre)} <span class="small text-muted">(${esc(d.categoria_nombre)})</span>
      <div class="small text-muted">serie: ${d.con_serie?'SÍ':'NO'}</div></td>
    ${editando?`
      <td><input type="number" class="form-control form-control-sm text-center" id="cant${d.id}" min="${d.vendido}" value="${d.cantidad}"></td>
      <td><input type="number" class="form-control form-control-sm" min="0" step="1" id="pc${d.id}" value="${Number(d.precio_compra)}" ${d.vendido>0?'disabled':''}></td>
      <td><input type="number" class="form-control form-control-sm" min="0" step="1" id="pv${d.id}" value="${Number(d.precio_venta_sugerido)}"></td>`
      :`<td class="text-center">${d.cantidad}</td><td class="text-end">${money(d.precio_compra)}</td><td class="text-end">${money(d.precio_venta_sugerido)}</td>`}
    <td class="text-center">${d.vendido>0?`<span class="badge text-bg-secondary">${d.vendido}</span>`:'—'}</td>
    <td class="text-center">${d.disponible>0?`<span class="badge text-bg-success">${d.disponible}</span>`:'—'}</td>
    <td class="text-center">${d.anulado>0?`<span class="badge text-bg-secondary">${d.anulado}</span>`:'—'}</td>
    <td style="max-width:220px">${editando&&d.vendido+ d.disponible+ d.anulado>0
      ?`<textarea class="form-control form-control-sm" id="imeis${d.id}" style="min-width:180px">${esc((d.series||[]).join('\n'))}</textarea>`
      :`<span class="small">${d.series&&d.series.length?esc(d.series.join(', ')):'—'}</span>`}</td>
    <td class="text-center">${editando?`<button class="btn btn-sm btn-outline-danger" onclick="marcarBorrar(${i})"><i class="fas fa-trash"></i></button>`:''}</td>
  </tr>`).join('');
}
function toggleEdicion(on){
  editando=on; nuevos=[];borrados=[];
  document.getElementById('btnEditar').style.display=on?'none':'';
  document.getElementById('btnGuardar').style.display=on?'':'none';
  document.getElementById('btnNuevoItem').style.display=on?'':'none';
  render();
}
let t;
document.getElementById('niBuscar').addEventListener('input',function(){
  const q=this.value.trim(); if(q.length<1){document.getElementById('niRes').innerHTML='';document.getElementById('niId').value='';return;}
  clearTimeout(t);t=setTimeout(async()=>{
    try{
      const d=await api('tienda/catalogo.php?ent=productos&action=listar&q='+encodeURIComponent(q)+'&limit=20');
      document.getElementById('niRes').innerHTML=d.rows.map(p=>`<button class="list-group-item list-group-item-action res-item" onclick="niPick(${p.id},'${esc(p.nombre)}','${p.precio_compra_sugerido||''}','${p.precio_venta_sugerido||''}')">${esc(p.nombre)}</button>`).join('')||'<div class="list-group-item text-muted">Sin resultados</div>';
    }catch(e){}
  },300);
});
function niPick(id,nombre,pc,pv){
  document.getElementById('niId').value=id;document.getElementById('niBuscar').value=nombre;document.getElementById('niRes').innerHTML='';
  if(!document.getElementById('niPc').value)document.getElementById('niPc').value=pc;
  if(!document.getElementById('niPv').value)document.getElementById('niPv').value=pv;
}
function anadirItem(){(new bootstrap.Modal(document.getElementById('modalNuevoItem'))).show();}
function aceptarItem(){
  const id=parseInt(document.getElementById('niId').value);
  if(!id){showA('Seleccione el producto.',false);return;}
  const imeis=document.getElementById('niImeis').value.split('\n').map(s=>s.trim()).filter(Boolean);
  const cant=parseInt(document.getElementById('niCant').value)||1;
  if(imeis.length>0&&imeis.length!==cant){showA('Las series deben coincidir con la cantidad.',false);return;}
  nuevos.push({producto_id:id,cantidad:cant,precio_compra:parseFloat(document.getElementById('niPc').value)||0,precio_venta_sugerido:parseFloat(document.getElementById('niPv').value)||0,imeis});
  bootstrap.Modal.getInstance(document.getElementById('modalNuevoItem')).hide();
  document.getElementById('niBuscar').value='';document.getElementById('niId').value='';document.getElementById('niImeis').value='';document.getElementById('niCant').value='1';document.getElementById('niPc').value='';document.getElementById('niPv').value='';
  renderNuevos();
}
function renderNuevos(){
  const extra=nuevos.map((n,i)=>`<tr class="table-warning"><td class="fw-semibold">${esc(n.nombre||'Producto #'+n.producto_id)} <span class="badge text-bg-warning">nuevo</span></td>
    <td class="text-center">${n.cantidad}</td><td class="text-end">${money(n.precio_compra)}</td><td class="text-end">${money(n.precio_venta_sugerido)}</td>
    <td colspan="3">—</td><td class="small">${esc(n.imeis.join(', '))}</td><td class="text-center"><button class="btn btn-sm btn-outline-danger" onclick="nuevos.splice(${i},1);renderNuevos();"><i class="fas fa-times"></i></button></td></tr>`).join('');
  document.getElementById('tbody').innerHTML+=extra;
}
function marcarBorrar(i){
  if(data.detalles[i].vendido>0){showA('No se puede eliminar un ítem con unidades vendidas.',false);return;}
  borrados.push(data.detalles[i].id);
  data.detalles.splice(i,1); render();
}
async function guardar(){
  const items=[], delete_ids=[...borrados];
  for(const d of (data.detalles?.filter(x=>!borrados.includes(x.id))||[])){
    items.push({id:d.id,
      cantidad:parseInt(document.getElementById('cant'+d.id)?.value||d.cantidad)||d.cantidad,
      precio_compra:parseFloat(document.getElementById('pc'+d.id)?.value||d.precio_compra),
      precio_venta_sugerido:parseFloat(document.getElementById('pv'+d.id)?.value||d.precio_venta_sugerido),
      imeis:document.getElementById('imeis'+d.id)?document.getElementById('imeis'+d.id).value.split('\n').map(s=>s.trim()).filter(Boolean):[]});
  }
  for(const n of nuevos){ n.nombre=n.nombre||('Producto #'+n.producto_id); items.push(n); }
  try{
    await post('tienda/compras.php?action=editar&id='+COMPRA_ID,{items,delete_ids});
    showA('Compra actualizada.',true); cargar(); toggleEdicion(false);
  }catch(e){showA(e.message,false);}
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>