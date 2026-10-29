<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.compras');
$pageTitle = 'Nueva compra';
$currentPage = 'tienda_compras';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-cart-plus me-2"></i>Nueva compra</span>
    <a class="btn btn-light btn-sm" href="<?php echo uiBaseUrl(); ?>modules/tienda/pages/compras">Volver al historial</a>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto"><label class="form-label small text-muted mb-0">Fecha de compra</label>
        <input class="form-control" type="date" id="fecha" style="width:170px"></div>
      <div class="col-md-4"><label class="form-label small text-muted mb-0">Observación</label>
        <input class="form-control" id="observacion" maxlength="300"></div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light"><tr>
          <th style="min-width:260px">Producto</th>
          <th style="width:80px">Cant.</th>
          <th style="width:150px">P. compra</th>
          <th style="width:150px">P. venta sug.</th>
          <th style="width:auto">Series / IMEI (una por línea)</th>
          <th style="width:60px"></th>
        </tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <button class="btn btn-outline-primary btn-sm" onclick="addRow()"><i class="fas fa-plus me-1"></i>Agregar producto</button>
    <div class="d-flex justify-content-end mt-3">
      <button class="btn btn-lg btn-success" onclick="guardar()"><i class="fas fa-check me-1"></i>Guardar compra</button>
    </div>
  </div>
</div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const alertBox=document.getElementById('alert');
let rows=[];
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(m,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=m;alertBox.classList.remove('d-none');}

function addRow(){
  rows.push({producto_id:null,nombre:'',cantidad:1,precio_compra:'',precio_venta_sugerido:'',imeis:''});
  renderRows();
}
function renderRows(){
  const tb=document.getElementById('tbody');
  tb.innerHTML=rows.map((r,i)=>`<tr>
    <td>
      <input class="form-control form-control-sm" id="busq${i}" placeholder="Buscar producto…" autocomplete="off" value="${esc(r.nombre)}">
      <input type="hidden" id="pid${i}" value="${r.producto_id||''}">
      <div id="res${i}" class="list-group position-absolute" style="z-index:1050;max-width:340px"></div>
    </td>
    <td><input type="number" class="form-control form-control-sm text-center" min="1" value="${r.cantidad}" data-k="cantidad" data-i="${i}" onchange="upd(${i},this)"></td>
    <td><input type="number" class="form-control form-control-sm" min="0" step="1" value="${r.precio_compra}" data-k="precio_compra" data-i="${i}" onchange="upd(${i},this)"></td>
    <td><input type="number" class="form-control form-control-sm" min="0" step="1" value="${r.precio_venta_sugerido}" data-k="precio_venta_sugerido" data-i="${i}" onchange="upd(${i},this)"></td>
    <td><textarea class="form-control form-control-sm" rows="1" placeholder="354111... cada línea una serie" data-k="imeis" data-i="${i}" onchange="upd(${i},this)">${esc(r.imeis)}</textarea></td>
    <td class="text-center"><button class="btn btn-sm btn-outline-danger" onclick="rows.splice(${i},1);renderRows();"><i class="fas fa-times"></i></button></td>
  </tr>`).join('')||'<tr><td colspan="6" class="text-center text-muted">Sin productos</td></tr>';
  rows.forEach((r,i)=>{
    if(!r._bound){
      const input=document.getElementById('busq'+i);
      if(input) setupAc(i);
      r._bound=true;
    }
  });
}
function upd(i,inp){rows[i][inp.dataset.k]=inp.value;}
function setupAc(i){
  let t;
  const input=document.getElementById('busq'+i);
  input.addEventListener('input',function(){
    const q=this.value.trim(); if(q.length<1){document.getElementById('res'+i).innerHTML='';document.getElementById('pid'+i).value='';return;}
    clearTimeout(t); t=setTimeout(async()=>{
      try{
        const d=await api('tienda/catalogo.php?ent=productos&action=listar&q='+encodeURIComponent(q)+'&limit=20');
        document.getElementById('res'+i).innerHTML=d.rows.map(p=>`<button class="list-group-item list-group-item-action res-item" onclick="pick(${i},${p.id},'${esc(p.nombre)}','${p.precio_compra_sugerido||''}','${p.precio_venta_sugerido||''}')">${esc(p.nombre)} <span class="small text-muted">(${esc(p.categoria_nombre)})</span></button>`).join('')
          ||'<div class="list-group-item text-muted">Sin resultados</div>';
      }catch(e){}
    },300);
  });
}
function pick(i,id,nombre,pc,pv){
  rows[i].producto_id=id; rows[i].nombre=nombre;
  document.getElementById('busq'+i).value=nombre; document.getElementById('pid'+i).value=id; document.getElementById('res'+i).innerHTML='';
  if(!rows[i].precio_compra)rows[i].precio_compra=pc;
  if(!rows[i].precio_venta_sugerido)rows[i].precio_venta_sugerido=pv;
  renderRows();
}
async function guardar(){
  const validos=rows.filter(r=>r.producto_id);
  if(validos.length===0){showA('Agregue al menos un producto.',false);return;}
  for(const r of validos){
    const series=r.imeis?r.imeis.split('\n').map(s=>s.trim()).filter(s=>s!==''):[];
    if(series.length>0 && series.length!==parseInt(r.cantidad)){
      showA(`El producto "${r.nombre}" tiene ${series.length} series para ${r.cantidad} unidades.`,false);return;
    }
    if(!r.precio_compra && r.precio_compra!=='0'){showA(`Falta el precio de compra de "${r.nombre}".`,false);return;}
    if(!r.precio_venta_sugerido && r.precio_venta_sugerido!=='0'){showA(`Falta el precio de venta sugerido de "${r.nombre}".`,false);return;}
  }
  const payload={
    fecha_compra:document.getElementById('fecha').value||null,
    observacion:document.getElementById('observacion').value,
    items:validos.map(r=>({
      producto_id:r.producto_id,
      cantidad:parseInt(r.cantidad)||1,
      precio_compra:parseFloat(r.precio_compra)||0,
      precio_venta_sugerido:parseFloat(r.precio_venta_sugerido)||0,
      imeis:r.imeis?r.imeis.split('\n').map(s=>s.trim()).filter(s=>s!==''):[]
    }))
  };
  try{
    const d=await post('tienda/compras.php?action=guardar',payload);
    showA('Compra #'+d.id+' creada.',true);
    window.location.href=UI+'modules/tienda/pages/detalle_compra?id='+d.id;
  }catch(e){showA(e.message,false);}
}
addRow();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>