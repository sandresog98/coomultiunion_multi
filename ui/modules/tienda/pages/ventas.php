<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.ventas');
$canCrear = ui_accion('tienda.ventas', 'crear', $staff['rol'] ?? '');
$pageTitle = 'Ventas';
$currentPage = 'tienda_ventas';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-cash-register me-2"></i>Ventas (POS)</span>
    <span class="small text-white-50">La suma de pagos debe ser igual al total</span>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>

    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">CECO</label>
        <select class="form-select" id="ceco" data-searchable style="width:230px">
          <option value="">Seleccione…</option>
          <option>TIENDA GALERIAS</option>
          <option>COOPERATIVA OFICINA</option>
          <option>REDES - WEB</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Tipo de cliente</label>
        <div class="btn-group" id="tipoGrupo">
          <button type="button" class="btn btn-outline-primary btn-sm active" data-tipo="externo">Externo</button>
          <button type="button" class="btn btn-outline-primary btn-sm" data-tipo="asociado">Asociado</button>
        </div>
      </div>
      <div class="col-auto" id="campoAsociado" style="display:none;min-width:260px">
        <label class="form-label small text-muted mb-0">Asociado</label>
        <input class="form-control" id="buscarAsoc" placeholder="Cédula o nombre…" autocomplete="off">
        <input type="hidden" id="asocCedula">
        <div id="resAsoc" class="list-group position-absolute" style="z-index:1050;max-width:320px"></div>
      </div>
      <div class="col-auto" id="campoExterno" style="min-width:260px">
        <label class="form-label small text-muted mb-0">Cliente externo</label>
        <select class="form-select" id="clienteExt"><option value="">Seleccione…</option></select>
      </div>
      <div class="col-auto ms-auto">
        <?php if ($canCrear): ?>
        <button class="btn btn-brand" onclick="abrirSelector()"><i class="fas fa-plus me-1"></i>Agregar producto</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light"><tr>
          <th>Producto</th><th class="text-center" style="width:90px">Cant.</th>
          <th style="width:150px">Precio unit.</th><th class="text-end" style="width:150px">Subtotal</th>
          <th style="width:120px">Series</th><th style="width:60px"></th>
        </tr></thead>
        <tbody id="itemsTbody"></tbody>
      </table>
    </div>

    <div class="card border mt-2">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-money-bill-wave me-2"></i>Pagos</span>
        <span class="small text-white-50">Total: <strong id="labelTotal">$0</strong></span>
      </div>
      <div class="card-body">
        <table class="table table-sm align-middle" id="pagosTable">
          <thead><tr><th style="width:170px">Tipo</th><th style="width:160px">Monto</th><th style="width:210px">Nº crédito Sifone</th><th>Bono</th><th style="width:60px"></th></tr></thead>
          <tbody id="pagosTbody"></tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center">
          <button class="btn btn-outline-primary btn-sm" onclick="addPago()"><i class="fas fa-plus me-1"></i>Agregar pago</button>
          <div class="text-end">
            <div>Pagado: <strong id="labelPagado">$0</strong></div>
            <div class="small text-muted" id="labelDif">Faltante: $0</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
      <?php if ($canCrear): ?>
      <button class="btn btn-lg btn-success" onclick="guardarVenta()"><i class="fas fa-check me-1"></i>Registrar venta</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal: elegir producto con stock -->
<div class="modal fade" id="modalProductos" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Agregar producto</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input class="form-control mb-2" id="prodBuscar" placeholder="Buscar producto, categoría o marca…">
    <div id="prodLista" class="list-group" style="max-height:380px;overflow:auto"></div>
  </div>
</div></div></div>

<!-- Modal: elegir series (IMEI/serial) -->
<div class="modal fade" id="modalSeries" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Seleccionar series</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="small text-muted mb-2" id="seriesInfo"></div>
    <div id="seriesLista" class="list-group" style="max-height:320px;overflow:auto"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-brand" data-bs-dismiss="modal">Aceptar</button></div>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const CECOS=['TIENDA GALERIAS','COOPERATIVA OFICINA','REDES - WEB'];
let items=[], pagos=[];
let serieRow=null, serieLista=[];
const alertBox=document.getElementById('alert');
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showAlert(msg,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=msg;alertBox.classList.remove('d-none');}

// ── Tipo de cliente ──
document.querySelectorAll('#tipoGrupo button').forEach(b=>b.addEventListener('click',function(){
  document.querySelectorAll('#tipoGrupo button').forEach(x=>x.classList.remove('active'));
  this.classList.add('active');
  document.getElementById('campoAsociado').style.display = this.dataset.tipo==='asociado' ? '' : 'none';
  document.getElementById('campoExterno').style.display = this.dataset.tipo==='externo' ? '' : 'none';
  refrescarBonos();
}));
function tipoCliente(){return document.querySelector('#tipoGrupo button.active').dataset.tipo;}

// ── Cargar clientes externos ──
async function cargarExternos(){
  try{const d=await api('tienda/clientes.php?action=externos');
    document.getElementById('clienteExt').innerHTML='<option value="">Seleccione…</option>'+d.map(c=>`<option value="${c.id}">${esc(c.nombre)} — ${esc(c.nit_cedula)}</option>`).join('');
  }catch(e){}
}

// ── Autocomplete asociado ──
let tA;
document.getElementById('buscarAsoc').addEventListener('input',function(){
  clearTimeout(tA); const q=this.value.trim();
  if(q.length<1){document.getElementById('resAsoc').innerHTML='';document.getElementById('asocCedula').value='';refrescarBonos();return;}
  tA=setTimeout(async()=>{
    try{const d=await api('tienda/clientes.php?action=buscar_asociados&q='+encodeURIComponent(q)+'&limit=20');
      document.getElementById('resAsoc').innerHTML=d.map(a=>`<button class="list-group-item list-group-item-action res-item" onclick="pickAsoc('${esc(a.cedula)}','${esc(a.nombre)}')">${esc(a.cedula)} — ${esc(a.nombre)}</button>`).join('')||'<div class="list-group-item text-muted">Sin resultados</div>';
    }catch(e){}
  },300);
});
function pickAsoc(cedula,nombre){
  document.getElementById('asocCedula').value=cedula;
  document.getElementById('buscarAsoc').value=nombre;
  document.getElementById('resAsoc').innerHTML='';
  refrescarBonos();
}

// ── Selector de productos ──
let catVen=[];
async function abrirSelector(){
  await cargarCat(''); new bootstrap.Modal(document.getElementById('modalProductos')).show();
  setTimeout(()=>document.getElementById('prodBuscar').focus(),100);
}
async function cargarCat(q){
  try{catVen=await api('tienda/stock.php?action=catalogo_venta&q='+encodeURIComponent(q));
    document.getElementById('prodLista').innerHTML=catVen.map(p=>`<button class="list-group-item list-group-item-action d-flex justify-content-between" onclick="agregarItem(${p.id})">
      <span>${esc(p.nombre)} <span class="small text-muted">(${esc(p.categoria_nombre)} · ${esc(p.marca_nombre)})</span></span>
      <span>${p.disponible} disp · ${money(p.precio_sugerido)}</span></button>`).join('')||'<div class="list-group-item text-muted">Sin stock disponible</div>';
  }catch(e){}
}
let tP;
document.getElementById('prodBuscar').addEventListener('input',function(){clearTimeout(tP);tP=setTimeout(()=>cargarCat(this.value.trim()),300);});
function agregarItem(pid){
  const p=catVen.find(x=>x.id===pid); if(!p)return;
  if(items.some(i=>i.producto_id===pid)){showAlert('El producto ya está en la venta.',false);return;}
  items.push({producto_id:p.id,nombre:p.nombre,categoria:p.categoria_nombre,marca:p.marca_nombre,
    cantidad:1,precio_unitario:Number(p.precio_sugerido||0),tiene_serie:p.tiene_serie>0,imeis:[]});
  bootstrap.Modal.getInstance(document.getElementById('modalProductos')).hide();
  renderItems(); recalcular();
}

// ── Render ítems ──
function renderItems(){
  document.getElementById('itemsTbody').innerHTML=items.map((it,i)=>`<tr>
    <td>${esc(it.nombre)} <span class="small text-muted">(${esc(it.categoria)})</span></td>
    <td class="text-center"><input type="number" class="form-control form-control-sm text-center" min="1" value="${it.cantidad}" onchange="cambiarCant(${i},this.value)"></td>
    <td><input type="number" class="form-control form-control-sm" min="0" step="1" value="${it.precio_unitario}" onchange="cambiarPrecio(${i},this.value)"></td>
    <td class="text-end">${money(it.precio_unitario*it.cantidad)}</td>
    <td class="text-center">${it.tiene_serie?`<button class="btn btn-sm btn-outline-secondary" onclick="abrirSeries(${i})">${it.imeis.length>0?`<i class="fas fa-check text-success"></i> ${it.imeis.length}`:'<i class="fas fa-sim-card"></i>'}</button>`:'<span class="text-muted">—</span>'}</td>
    <td class="text-center"><button class="btn btn-sm btn-outline-danger" onclick="items.splice(${i},1);renderItems();recalcular();"><i class="fas fa-times"></i></button></td>
  </tr>`).join('')||'<tr><td colspan="6" class="text-center text-muted">Sin productos</td></tr>';
}
function cambiarCant(i,v){items[i].cantidad=Math.max(1,parseInt(v)||1);renderItems();recalcular();}
function cambiarPrecio(i,v){items[i].precio_unitario=Math.max(0,parseFloat(v)||0);renderItems();recalcular();}

// ── Series (IMEI/serial) ──
async function abrirSeries(i){
  serieRow=i; serieLista=[];
  if(items[i].tiene_serie){
    try{serieLista=await api('tienda/stock.php?action=series&producto_id='+items[i].producto_id);}catch(e){}
  }
  document.getElementById('seriesInfo').textContent=`Producto: ${items[i].nombre} · Seleccione ${items[i].cantidad} serie(s) disponible(s):`;
  const sel=items[i].imeis||[];
  document.getElementById('seriesLista').innerHTML=serieLista.length?serieLista.map(s=>`<label class="list-group-item"><input type="checkbox" data-serie="${esc(s.numero_serie)}" ${sel.includes(s.numero_serie)?'checked':''}> ${esc(s.numero_serie)}</label>`).join(''):'<div class="text-muted">Sin series disponibles</div>';
  const m=new bootstrap.Modal(document.getElementById('modalSeries'));
  m._tiendaCb=()=>{
    const checks=[...document.querySelectorAll('#seriesLista input:checked')].map(c=>c.dataset.serie);
    if(checks.length!==items[serieRow].cantidad){showAlert(`Debe seleccionar ${items[serieRow].cantidad} serie(s).`,false);return;}
    items[serieRow].imeis=checks; renderItems();
  };
  document.getElementById('modalSeries').addEventListener('hidden.bs.modal',()=>{if(m._tiendaCb){const cb=m._tiendaCb;m._tiendaCb=null;cb();}});
  m.show();
}

// ── Pagos ──
function addPago(tipo='efectivo',monto=''){
  pagos.push({tipo,monto:monto===''?'':parseFloat(monto)||0,numero:'',bono_id:''});
  renderPagos();
}
function renderPagos(){
  document.getElementById('pagosTbody').innerHTML=pagos.map((p,i)=>`<tr>
    <td><select class="form-select form-select-sm" onchange="pagoTipo(${i},this.value)">
      ${['efectivo','bold','qr','sifone','sistecredito','alocredito','bono'].map(t=>`<option value="${t}" ${p.tipo===t?'selected':''}>${t}</option>`).join('')}
    </select></td>
    <td><input type="number" class="form-control form-control-sm" min="0" step="1" value="${p.monto}" onchange="pagoMonto(${i},this.value)"></td>
    <td>${['sifone','sistecredito','alocredito'].includes(p.tipo)?`<input class="form-control form-control-sm" placeholder="Nº crédito" value="${esc(p.numero)}" onchange="pagoNumero(${i},this.value)">`:''}</td>
    <td>${p.tipo==='bono'?`<select class="form-select form-select-sm" id="bonoSel${i}" onchange="pagoBono(${i},this.value)"></select>`:'<span class="text-muted small">—</span>'}</td>
    <td class="text-center"><button class="btn btn-sm btn-outline-danger" onclick="pagos.splice(${i},1);renderPagos();recalcular();"><i class="fas fa-times"></i></button></td>
  </tr>`).join('')||'<tr><td colspan="5" class="text-center text-muted">Sin pagos. Agregue al menos uno.</td></tr>';
  refrescarBonos();
}
function pagoTipo(i,t){pagos[i].tipo=t;renderPagos();}
function pagoMonto(i,v){pagos[i].monto=parseFloat(v)||0;recalcular();}
function pagoNumero(i,v){pagos[i].numero=v;}
function pagoBono(i,v){pagos[i].bono_id=v;}
async function refrescarBonos(){
  try{
    let params='';
    const t=tipoCliente();
    if(t==='asociado'){const c=document.getElementById('asocCedula').value; if(c)params='cedula='+encodeURIComponent(c);}
    else{const c=document.getElementById('clienteExt').value; if(c)params='cliente_id='+c;}
    const bonos=params?await api('tienda/bonos.php?action=disponibles&'+params):[];
    pagos.forEach((p,i)=>{
      if(p.tipo==='bono'){
        const el=document.getElementById('bonoSel'+i);
        if(el) el.innerHTML=bonos.length?bonos.map(n=>`<option value="${n.id}">Bono #${n.id} — saldo ${money(n.saldo)}</option>`).join(''):'<option value="">Sin bonos</option>';
      }
    });
  }catch(e){}
}

// ── Totales ──
function totalVenta(){return items.reduce((s,it)=>s+it.precio_unitario*it.cantidad,0);}
function recalcular(){
  const tot=totalVenta(), pag=pagos.reduce((s,p)=>s+(p.monto||0),0);
  document.getElementById('labelTotal').textContent=money(tot);
  document.getElementById('labelPagado').textContent=money(pag);
  const dif=tot-pag;
  document.getElementById('labelDif').textContent=(dif>0?'Faltante: ':'Excedente: ')+money(Math.abs(dif));
  document.getElementById('labelDif').className='small '+((Math.abs(dif)>0.01)?'text-danger':'text-muted');
}

// ── Guardar ──
async function guardarVenta(){
  try{
    const t=tipoCliente(); const ceco=document.getElementById('ceco').value;
    if(ceco===''){showAlert('Seleccione el CECO.',false);return;}
    let cedula=null, clienteId=null;
    if(t==='asociado'){cedula=document.getElementById('asocCedula').value;if(!cedula){showAlert('Seleccione el asociado.',false);return;}}
    else{clienteId=parseInt(document.getElementById('clienteExt').value)||0;if(!clienteId){showAlert('Seleccione el cliente externo.',false);return;}}
    if(items.length===0){showAlert('Agregue al menos un producto.',false);return;}
    if(pagos.length===0){showAlert('Agregue al menos un medio de pago.',false);return;}
    const payload={
      tipo_cliente:t, cedula, cliente_id:clienteId, ceco,
      items:items.map(it=>({producto_id:it.producto_id,cantidad:it.cantidad,precio_unitario:it.precio_unitario,imeis:it.imeis})),
      pagos:pagos.map(p=>({tipo:p.tipo,monto:p.monto,numero_credito_sifone:p.numero,bono_id:p.tipo==='bono'?(parseInt(p.bono_id)||null):null}))
    };
    const d=await post('tienda/ventas.php?action=guardar',payload);
    showAlert('Venta #'+d.id+' registrada por '+money(d.total)+'.',true);
    items=[];pagos=[];renderItems();renderPagos();recalcular();
    cargarExternos();
  }catch(e){showAlert(e.message,false);}
}

cargarExternos(); addPago(); recalcular();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>