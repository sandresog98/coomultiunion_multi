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
    <span class="small text-white-50" id="infoTotal"></span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:320px"><label class="form-label small text-muted mb-0">Buscar</label><div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Nombre, cédula, PSE, Confiar ID…"></div></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Origen</label><select class="form-select" id="fOrigen" style="width:130px"><option value="">Todos</option><option value="pse">PSE</option><option value="confiar">Confiar</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control" id="fDesde" style="width:140px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control" id="fHasta" style="width:140px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th>ID</th><th>Asociado</th><th>Origen</th><th>Pago ID</th><th class="text-end">Valor</th><th>Recibo Sifone</th><th>Fecha</th><th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>,PAGE_SIZE=20,alertBox=document.getElementById('alert');
let state={q:'',origen:'',desde:'',hasta:'',page:0};
let debounceTimer;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
async function apicall(p,o){let r=await fetch(API+p,o),j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
['fOrigen','fDesde','fHasta'].forEach(id=>document.getElementById(id).addEventListener('change',function(){state[id.substring(1)]=this.value;state.page=0;cargar();}));

async function cargar(){
  let o=state.page*PAGE_SIZE,bs='';
  if(state.q)bs+='&q='+encodeURIComponent(state.q);if(state.origen)bs+='&origen='+state.origen;
  if(state.desde)bs+='&fecha_desde='+state.desde;if(state.hasta)bs+='&fecha_hasta='+state.hasta;
  try{
    let d=await apicall('transacciones/listar_todas?limit='+PAGE_SIZE+'&offset='+o+bs);
    let t=d.total||0;document.getElementById('infoTotal').textContent=t>0?`Mostrando ${o+1}-${Math.min(o+PAGE_SIZE,t)} de ${t}`:'';
    document.getElementById('tbody').innerHTML=(d.rows||[]).map((tx,i)=>{
      let det=tx.detalles.map(dd=>`<tr><td><span class="badge text-bg-light">${esc(dd.tipo_rubro)}</span></td><td>${esc(dd.producto_nombre||dd.descripcion||dd.referencia_credito||dd.producto_id||'—')}</td><td class="text-end fw-semibold">${money(dd.valor_asignado)}</td></tr>`).join('');
      let origenDisplay = tx.origen === 'cash_qr' ? 'confiar' : tx.origen;
      return `<tr class="hist-item p-0">
        <td colspan="8" class="p-0">
          <div class="hist-header d-flex align-items-center justify-content-between" data-bs-toggle="collapse" data-bs-target="#txCollapse${i}" aria-expanded="false">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <span class="badge text-bg-secondary fw-normal">#${tx.id}</span>
              <span><span class="fw-semibold">${esc(tx.nombre_asociado||tx.cedula)}</span><small class="text-muted ms-1">CC ${esc(tx.cedula)}</small></span>
              <span class="badge text-bg-secondary">${esc(origenDisplay)}</span>
              <small class="text-muted">${esc(tx.pse_id||tx.confiar_id||'')}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
              <small class="fw-semibold">${money(tx.valor_total)}</small>
              <small class="text-muted">${fmtDt(tx.fecha_creacion)}</small>
              <button class="btn btn-sm btn-outline-danger ms-1" onclick="event.stopPropagation();eliminarTx(${tx.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
              <i class="fas fa-chevron-down hist-chevron text-muted small"></i>
            </div>
          </div>
          <div class="collapse" id="txCollapse${i}">
            <div class="px-3 pb-2 pt-1">
              ${tx.recibo_caja?`<div class="small text-muted mb-2">Recibo caja: <b>${esc(tx.recibo_caja)}</b>${tx.nombre_usuario?' · por: <b>'+esc(tx.nombre_usuario)+'</b>':''}</div>`:''}
              <table class="table table-sm small mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Referencia / Producto</th><th class="text-end">Valor</th></tr></thead><tbody>${det}</tbody></table>
            </div>
          </div>
        </td>
      </tr>`;
    }).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(t);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

function renderPagination(t){let N=Math.ceil(t/PAGE_SIZE);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}let C=state.page,pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;}
function goPage(p){state.page=p;cargar();}
async function eliminarTx(id){if(!confirm('¿Eliminar transacción #'+id+'?'))return;try{await apicall('transacciones/crear',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});cargar();}catch(e){alert(e.message);}}
document.addEventListener('DOMContentLoaded',cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
