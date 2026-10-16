<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.transacciones');
$canCrearTrx = ui_accion('transacciones', 'crear', $staff['rol'] ?? '');
$pageTitle = 'Transacciones';
$currentPage = 'transacciones';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header brand"><i class="fas fa-search me-2"></i>Buscar asociado</div>
      <div class="card-body">
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="fas fa-id-card"></i></span>
          <input class="form-control" id="bCedula" placeholder="Cédula del asociado…">
          <button class="btn btn-brand" onclick="buscarAsociado()">Buscar</button>
        </div>
        <div id="aInfo" class="d-none">
          <h6 class="text-brand" id="aNombre"></h6>
          <div class="small text-muted mb-2">Cédula: <b id="aCedula"></b></div>
          <div class="small mb-1"><strong>Total saldos a favor:</strong> <span id="aSaldos"></span></div>
          <div class="small mb-1"><strong>Créditos activos:</strong> <span id="aCreditos"></span></div>
          <div class="small mb-1"><strong>Producto mensual:</strong> <span id="aProdMensual"></span></div>
        </div>
      </div>
    </div>

    <div class="card mt-3 d-none" id="cardRubros">
      <div class="card-header brand"><i class="fas fa-list-check me-2"></i>Rubros a pagar</div>
      <div class="card-body p-2">
        <div id="rubrosList"></div>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="card d-none" id="cardPago">
      <div class="card-header brand"><i class="fas fa-cash-register me-2"></i>Nueva transacción</div>
      <div class="card-body">
        <div id="txAlert" class="alert d-none"></div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Origen</label>
            <select class="form-select" id="txOrigen"><option value="confiar">Confiar</option><option value="pse">PSE</option></select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Recibo caja Sifone</label>
            <input class="form-control" id="txRecibo" placeholder="Nº recibo">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Valor total del pago <span class="text-danger">*</span></label>
          <input type="number" class="form-control" id="txValorTotal" step="0.01" min="0" placeholder="0.00">
        </div>
        <table class="table table-sm small mb-3" id="txDetalles">
          <thead class="table-light"><tr>
            <th>Tipo</th><th>Referencia</th><th class="text-end">Recomendado</th><th class="text-end">Asignado</th>
          </tr></thead>
          <tbody id="txDetallesBody"><tr><td colspan="4" class="text-center text-muted">Busque un asociado primero</td></tr></tbody>
        </table>
        <div class="text-muted small mb-2">Asignado: <b id="txTotalAsignado">$0</b> · Restante: <b id="txRestante" class="text-danger">$0</b></div>
        <?php if ($canCrearTrx): ?>
        <button class="btn btn-brand w-100" onclick="crearTransaccion()"><i class="fas fa-save me-2"></i>Crear transacción</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mt-3 d-none" id="cardHistorial">
      <div class="card-header brand"><i class="fas fa-history me-2"></i>Historial</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm small mb-0"><thead class="table-light"><tr>
            <th>ID</th><th>Origen</th><th>Recibo</th><th class="text-end">Valor</th><th>Fecha</th><th></th>
          </tr></thead><tbody id="histTbody"></tbody></table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
let asociadoCedula = '', rubros = {creditos:[],mora:[],productos:[]}, detallesTx = [];

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';const d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleDateString('es-CO');}
async function apicall(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

async function buscarAsociado(){
  const cedula = document.getElementById('bCedula').value.trim();
  if(!cedula) return alert('Ingrese una cédula');
  try{
    const d = await apicall('asociados/ficha?cedula='+encodeURIComponent(cedula));
    const m = d.productos||{}, p = d.persona||{};
    asociadoCedula = cedula;
    document.getElementById('aNombre').textContent = p.nombre||'';
    document.getElementById('aCedula').textContent = cedula;
    document.getElementById('aSaldos').textContent = money(m.total_saldos_favor||0);
    document.getElementById('aCreditos').textContent = (m.total_creditos||0);
    document.getElementById('aProdMensual').textContent = money(m.producto_mensual_total||0);
    document.getElementById('aInfo').classList.remove('d-none');

    rubros = await apicall('transacciones/crear?cedula='+encodeURIComponent(cedula));
    renderRubros();
    await cargarHistorial();
    document.getElementById('cardRubros').classList.remove('d-none');
    document.getElementById('cardPago').classList.remove('d-none');
    document.getElementById('cardHistorial').classList.remove('d-none');
  }catch(e){alert(e.message);}
}

function renderRubros(){
  let html = '';
  detallesTx = [];
  if(rubros.mora.length){
    html += '<h6 class="text-danger small fw-bold">Mora</h6>';
    rubros.mora.forEach(m=>{
      html+=`<div class="d-flex justify-content-between small border rounded p-1 mb-1"><span>Crédito ${esc(m.presta)} (${money(m.sdomor)}) · ${esc(m.diav)} días</span><input type="number" class="form-control form-control-sm" style="width:120px" value="${m.sdomor||0}" data-tipo="credito_mora" data-ref="${esc(m.presta)}" data-recom="${m.sdomor||0}" onchange="syncDetalles()"></div>`;
      detallesTx.push({tipo_rubro:'credito_mora',referencia_credito:m.presta,descripcion:'Mora crédito '+m.presta,valor_recomendado:parseFloat(m.sdomor||0),valor_asignado:parseFloat(m.sdomor||0)});
    });
  }
  if(rubros.creditos.length){
    html += '<h6 class="text-warning small fw-bold mt-2">Cuotas crédito</h6>';
    rubros.creditos.forEach(c=>{
      const enMora = rubros.mora.some(m=>m.presta===c.numero);
      if(!enMora){
        html+=`<div class="d-flex justify-content-between small border rounded p-1 mb-1"><span>Crédito ${esc(c.numero)} · ${esc(c.tipopr)}</span><input type="number" class="form-control form-control-sm" style="width:120px" value="${c.valorc||0}" data-tipo="credito" data-ref="${esc(c.numero)}" data-recom="${c.valorc||0}" onchange="syncDetalles()"></div>`;
        detallesTx.push({tipo_rubro:'credito',referencia_credito:c.numero,descripcion:'Cuota crédito '+c.numero,valor_recomendado:parseFloat(c.valorc||0),valor_asignado:parseFloat(c.valorc||0)});
      }
    });
  }
  if(rubros.productos.length){
    html += '<h6 class="text-info small fw-bold mt-2">Productos</h6>';
    rubros.productos.forEach(p=>{
      html+=`<div class="d-flex justify-content-between small border rounded p-1 mb-1"><span>${esc(p.nombre)}</span><input type="number" class="form-control form-control-sm" style="width:120px" value="${p.monto||0}" data-tipo="producto" data-ref="${p.id}" data-recom="${p.monto||0}" onchange="syncDetalles()"></div>`;
      detallesTx.push({tipo_rubro:'producto',producto_id:p.id,descripcion:p.nombre,valor_recomendado:parseFloat(p.monto||0),valor_asignado:parseFloat(p.monto||0)});
    });
  }
  document.getElementById('rubrosList').innerHTML = html || '<p class="text-muted small mb-0">Sin rubros pendientes.</p>';
  syncDetalles();
  document.getElementById('txValorTotal').value = detallesTx.reduce((s,d)=>s+d.valor_recomendado,0);
  updateDetallesTable();
}

function syncDetalles(){
  document.querySelectorAll('#rubrosList input').forEach(inp=>{
    const idx = detallesTx.findIndex(d=>{
      if(inp.dataset.tipo==='producto') return d.producto_id==parseInt(inp.dataset.ref);
      return d.tipo_rubro===inp.dataset.tipo && d.referencia_credito===inp.dataset.ref;
    });
    if(idx>=0) detallesTx[idx].valor_asignado = parseFloat(inp.value)||0;
  });
  updateDetallesTable();
}

function updateDetallesTable(){
  document.getElementById('txDetallesBody').innerHTML = detallesTx.map(d=>`<tr>
    <td><span class="badge text-bg-light">${esc(d.tipo_rubro)}</span></td>
    <td>${esc(d.referencia_credito||d.producto_id||'—')}</td>
    <td class="text-end">${money(d.valor_recomendado)}</td>
    <td class="text-end">${money(d.valor_asignado)}</td>
  </tr>`).join('');
  const asignado = detallesTx.reduce((s,d)=>s+d.valor_asignado,0);
  const total = parseFloat(document.getElementById('txValorTotal').value)||0;
  document.getElementById('txTotalAsignado').textContent = money(asignado);
  const restante = total - asignado;
  document.getElementById('txRestante').textContent = money(restante);
  document.getElementById('txRestante').className = restante<0?'text-danger fw-bold':'text-success';
}

document.getElementById('txValorTotal').addEventListener('input', ()=>{syncDetalles();});

async function crearTransaccion(){
  if(!asociadoCedula) return alert('Busque un asociado primero');
  const data = {
    cedula: asociadoCedula,
    origen: document.getElementById('txOrigen').value,
    recibo_caja: document.getElementById('txRecibo').value.trim(),
    valor_total: parseFloat(document.getElementById('txValorTotal').value)||0,
    detalles: detallesTx.filter(d=>d.valor_asignado>0),
  };
  if(!data.valor_total || !data.detalles.length) return alert('Asigne valores a los rubros');
  try{
    await apicall('transacciones/crear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    document.getElementById('txAlert').className='alert alert-success';document.getElementById('txAlert').textContent='Transacción creada.';document.getElementById('txAlert').classList.remove('d-none');
    await cargarHistorial();
  }catch(e){document.getElementById('txAlert').className='alert alert-danger';document.getElementById('txAlert').textContent=e.message;document.getElementById('txAlert').classList.remove('d-none');}
}

async function cargarHistorial(){
  if(!asociadoCedula) return;
  try{
    const d = await apicall('transacciones/listar?cedula='+encodeURIComponent(asociadoCedula)+'&limit=50');
    document.getElementById('histTbody').innerHTML = (d.rows||[]).map(tx=>{
      let detHtml = tx.detalles.map(d=>`<small class="d-block"><span class="badge text-bg-light">${esc(d.tipo_rubro)}</span> ${esc(d.referencia_credito||d.producto_id||'')}: ${money(d.valor_asignado)}</small>`).join('');
      return `<tr>
        <td>#${tx.id}</td><td><span class="badge text-bg-secondary">${esc(tx.origen)}</span></td>
        <td>${esc(tx.recibo_caja||'—')}</td><td class="text-end">${money(tx.valor_total)}</td>
        <td><small>${fmtDt(tx.fecha_creacion)}</small></td>
        <td><button class="btn btn-sm btn-outline-danger" onclick="eliminarTx(${tx.id})"><i class="fas fa-trash"></i></button></td>
      </tr><tr class="table-light"><td colspan="6">${detHtml}</td></tr>`;
    }).join('')||'<tr><td colspan="6" class="text-center text-muted">Sin transacciones</td></tr>';
  }catch(e){console.error(e);}
}

async function eliminarTx(id){
  if(!confirm('¿Eliminar transacción #'+id+'?')) return;
  try{await apicall('transacciones/crear',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});await cargarHistorial();}catch(e){alert(e.message);}
}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
