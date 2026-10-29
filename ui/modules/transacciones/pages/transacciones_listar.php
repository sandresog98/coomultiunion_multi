<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.transacciones');
$pageTitle = 'Transacciones';
$currentPage = 'transacciones_lista';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list-check me-2"></i>Transacciones</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:320px"><label class="form-label small text-muted mb-0">Buscar</label><div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Nombre, cédula, PSE, Confiar ID…"></div></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Origen</label><select class="form-select" id="fOrigen" data-searchable style="width:130px"><option value="">Todos</option><option value="pse">PSE</option><option value="confiar">Confiar</option><option value="boleta">Boleta</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Valor desde</label><input type="number" step="0.01" min="0" class="form-control" id="fValorMin" style="width:120px" placeholder="0"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Valor hasta</label><input type="number" step="0.01" min="0" class="form-control" id="fValorMax" style="width:120px" placeholder="0"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Recibo</label><input type="text" class="form-control" id="fRecibo" style="width:150px" placeholder="N° recibo"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control" id="fDesde" style="width:140px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control" id="fHasta" style="width:140px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light"><tr>
          <th>ID</th><th>Asociado</th><th>Origen</th><th>Pago ID</th>
          <th class="text-end">Valor</th><th>Recibo</th><th>Fecha</th><th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Detalle de transacción -->
<div class="modal fade" id="mTxDetalle" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Detalle de transacción</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="txDetalleContent"></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>,alertBox=document.getElementById('alert');
let state={q:'',origen:'',valor_min:'',valor_max:'',recibo:'',desde:'',hasta:'',page:0,size:20};
let rowsData=[];
let mTxDetalle;
let debounceTimer;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
function apicall(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function valorTx(tx){const v=Number(tx.valor_asignado||0);return v>0?v:(Number(tx.valor_total||0));}

document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
const filtros={fOrigen:'origen',fValorMin:'valor_min',fValorMax:'valor_max',fRecibo:'recibo',fDesde:'desde',fHasta:'hasta'};
Object.keys(filtros).forEach(id=>document.getElementById(id).addEventListener('change',function(){state[filtros[id]]=this.value.trim();state.page=0;cargar();}));

async function cargar(){
  let o=state.page*state.size,bs='';
  if(state.q)bs+='&q='+encodeURIComponent(state.q);if(state.origen)bs+='&origen='+state.origen;
  if(state.valor_min!=='')bs+='&valor_min='+encodeURIComponent(state.valor_min);if(state.valor_max!=='')bs+='&valor_max='+encodeURIComponent(state.valor_max);
  if(state.recibo)bs+='&recibo='+encodeURIComponent(state.recibo);
  if(state.desde)bs+='&fecha_desde='+state.desde;if(state.hasta)bs+='&fecha_hasta='+state.hasta;
  try{
    let d=await apicall('transacciones/listar_todas?limit='+state.size+'&offset='+o+bs);
    rowsData=d.rows||[];
    let t=d.total||0;
    document.getElementById('infoTotal').textContent=t>0?`Mostrando ${o+1}-${Math.min(o+state.size,t)} de ${t}`:'';
    document.getElementById('tbody').innerHTML=rowsData.map((tx,i)=>{
      let origenDisplay = tx.origen === 'cash_qr' ? 'confiar' : tx.origen;
      let pago = tx.pse_id || tx.confiar_id || '—';
      return `<tr>
        <td><span class="badge text-bg-secondary fw-normal">#${tx.id}</span></td>
        <td><span class="fw-semibold">${esc(tx.nombre_asociado||tx.cedula)}</span><div class="small text-muted">CC ${esc(tx.cedula)}</div></td>
        <td><span class="badge text-bg-secondary">${esc(origenDisplay)}</span></td>
        <td><small>${esc(pago)}</small></td>
        <td class="text-end">${money(valorTx(tx))}</td>
        <td><small>${tx.recibo_caja ? esc(tx.recibo_caja) : '—'}</small></td>
        <td><small>${fmtDt(tx.fecha_creacion)}</small></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-secondary me-1" onclick="verDetalle(${i})" title="Ver detalle"><i class="fas fa-eye"></i></button>
          <button class="btn btn-sm btn-outline-danger" onclick="eliminarTx(${tx.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(t);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

function verDetalle(i){
  const tx=rowsData[i];
  if(!tx)return;
  const detalles=(tx.detalles||[]).map(dd=>`<tr>
    <td><span class="badge text-bg-light">${esc(dd.tipo_rubro)}</span></td>
    <td>${esc(dd.producto_nombre||dd.descripcion||dd.referencia_credito||dd.producto_id||'—')}</td>
    <td class="text-end fw-semibold">${money(dd.valor_asignado)}</td>
  </tr>`).join('');
  const origenDisplay = tx.origen === 'cash_qr' ? 'confiar' : tx.origen;
  document.getElementById('txDetalleContent').innerHTML=`
    <div class="row small g-2">
      <div class="col-md-6"><strong>Transacción:</strong> #${tx.id}</div>
      <div class="col-md-6"><strong>Fecha:</strong> ${fmtDt(tx.fecha_creacion)}</div>
      <div class="col-md-6"><strong>Asociado:</strong> ${esc(tx.nombre_asociado||tx.cedula)} <span class="text-muted">CC ${esc(tx.cedula)}</span></div>
      <div class="col-md-6"><strong>Origen:</strong> ${esc(origenDisplay)}</div>
      <div class="col-md-6"><strong>Pago ID:</strong> ${esc(tx.pse_id||tx.confiar_id||'—')}</div>
      <div class="col-md-6"><strong>Recibo:</strong> ${tx.recibo_caja ? esc(tx.recibo_caja) : '—'}</div>
      <div class="col-md-6"><strong>Valor:</strong> ${money(valorTx(tx))}</div>
      <div class="col-md-6"><strong>Registrado por:</strong> ${esc(tx.nombre_usuario||'—')}</div>
    </div>
    <hr>
    <h6 class="text-brand mb-2">Desglose de rubros</h6>
    <div class="table-responsive"><table class="table table-sm small mb-0">
      <thead class="table-light"><tr><th>Tipo</th><th>Referencia / Producto</th><th class="text-end">Valor</th></tr></thead>
      <tbody>${detalles||'<tr><td colspan="3" class="text-muted">Sin detalle</td></tr>'}</tbody>
    </table></div>`;
  mTxDetalle.show();
}

function renderPagination(t){let N=Math.ceil(t/state.size);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}let C=state.page,pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;}
function goPage(p){state.page=p;cargar();}
async function eliminarTx(id){if(!confirm('¿Eliminar transacción #'+id+'?'))return;try{await apicall('transacciones/crear',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});cargar();}catch(e){alert(e.message);}}
document.addEventListener('DOMContentLoaded',()=>{mTxDetalle=new bootstrap.Modal('#mTxDetalle');cargar();});
window.Listado.mountSize('fSizeBox', state, cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
