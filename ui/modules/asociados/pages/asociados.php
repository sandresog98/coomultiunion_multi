<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.asociados');
$pageTitle = 'Asociados';
$currentPage = 'asociados';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-users me-2"></i>Asociados</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:340px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="Cédula o nombre…">
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="estadoFiltro" data-searchable style="width:130px">
          <option value="">Todos</option>
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Créditos</label>
        <select class="form-select" id="creditoFiltro" data-searchable style="width:140px">
          <option value="">Todos</option>
          <option value="1">Con créditos</option>
          <option value="0">Sin créditos</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Productos</label>
        <select class="form-select" id="produtoFiltro" data-searchable style="width:155px">
          <option value="">Todos</option>
          <option value="1">Con productos</option>
          <option value="0">Sin productos</option>
        </select>
      </div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="cedula">Cédula</th>
          <th class="sortable" data-col="nombre">Nombre</th>
          <th class="sortable" data-col="celular">Celular</th>
          <th class="sortable text-end" data-col="total_saldos_favor">Saldos a favor</th>
          <th class="sortable text-center" data-col="creditos">Créditos</th>
          <th class="sortable text-end" data-col="producto_mensual">Prod. mensual</th>
          <th class="text-center">Fondo B.</th>
          <th class="sortable text-center" data-col="estado_activo">Estado</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3" aria-label="Paginación"></nav>
  </div>
</div>



<script>
window.API = <?php echo json_encode(apiUrl()); ?>;
const UI = <?php echo json_encode(uiBaseUrl()); ?>;
const alertBox=document.getElementById('alert'), L=window.Listado;
let state = { q: '', estado: '', creditos: '', productos: '', page: 0, size: 20, order: 'nombre', dir: 'ASC' };
let t;

const esc=L.esc, money=L.money, api=L.api;
L.bindSort(document, state, cargar);
function renderSortIcons(){L.renderSortIcons(document, state);}

// ── Búsqueda ──
document.getElementById('q').addEventListener('input',()=>{
  clearTimeout(t);
  t = setTimeout(()=>{ state.q = document.getElementById('q').value.trim(); state.page = 0; cargar(); }, 300);
});

// ── Filtro estado ──
document.getElementById('estadoFiltro').addEventListener('change',function(){
  state.estado = this.value; state.page = 0; cargar();
});

// ── Filtro créditos ──
document.getElementById('creditoFiltro').addEventListener('change',function(){
  state.creditos = this.value; state.page = 0; cargar();
});

// ── Filtro productos ──
document.getElementById('produtoFiltro').addEventListener('change',function(){
  state.productos = this.value; state.page = 0; cargar();
});

// ── Datos ──
async function cargar(){
  const offset = state.page * state.size;
  const params = `q=${encodeURIComponent(state.q)}&estado=${state.estado}&creditos=${state.creditos}&productos=${state.productos}&limit=${state.size}&offset=${offset}&order=${state.order}&dir=${state.dir}`;
  try{
    const d = await api('asociados/listar?'+params);
    const total = d.total || 0;
    document.getElementById('infoTotal').textContent = total > 0 ? `Mostrando ${offset+1}-${Math.min(offset+state.size, total)} de ${total}` : '';
    renderSortIcons();

    document.getElementById('tbody').innerHTML = (d.rows||[]).map(a=>`<tr class="fila-link" onclick="location.href='${UI}modules/asociados/pages/asociados_detalle?cedula=${encodeURIComponent(a.cedula)}'">
      <td>${esc(a.cedula)}</td><td>${esc(a.nombre)}</td><td>${esc(a.celular||'')}</td>
      <td class="text-end">${money(a.total_saldos_favor)}</td>
      <td class="text-center">${a.creditos>0?`<span class="badge text-bg-info">${a.creditos}</span>`:'—'}</td>
      <td class="text-end">${a.producto_mensual>0?money(a.producto_mensual):'—'}</td>
      <td class="text-center">${a.fondo_bienestar_al_dia==1?'<span class="badge text-bg-success">Al día</span>':(a.fondo_bienestar_tiene_producto==1?'<span class="badge text-bg-warning">No al día</span>':'—')}</td>
      <td class="text-center">${a.estado_activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-danger">Inactivo</span>'}</td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation()" href="${UI}modules/asociados/pages/asociados_detalle?cedula=${encodeURIComponent(a.cedula)}"><i class="fas fa-id-card"></i></a></td>
    </tr>`).join('') || '<tr><td colspan="9" class="text-center text-muted">Sin resultados</td></tr>';

    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

// ── Paginación ──
function renderPagination(total){document.getElementById('paginacion').innerHTML=L.paginacion(total,state.page,state.size);}
function goPage(p){state.page = p; document.querySelector('.card-body').scrollIntoView({behavior:'smooth'}); cargar();}

L.mountSize('fSizeBox', state, cargar);
document.addEventListener('DOMContentLoaded', cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
