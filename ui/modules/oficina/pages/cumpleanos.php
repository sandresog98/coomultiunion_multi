<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.cumpleanos');
$pageTitle = 'Cumpleaños';
$currentPage = 'cumpleanos';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$mesActual = (int) date('n');
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-cake-candles me-2"></i>Cumpleaños de asociados</span>
    <span class="small text-white-50" id="infoTotal"></span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="Cédula o nombre…">
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Mes</label>
        <select class="form-select" id="mes" style="width:170px">
          <option value="">Todo el año</option>
          <?php foreach ($meses as $n => $label): ?>
            <option value="<?php echo $n; ?>" <?php echo $n === $mesActual ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Día</th><th>Cédula</th><th>Nombre</th><th>Fecha nacimiento</th><th class="text-center">Edad</th>
          <th class="text-center">Próximo</th><th>Celular</th><th>Email</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const MESES = <?php echo json_encode($meses, JSON_UNESCAPED_UNICODE); ?>;
const alertBox = document.getElementById('alert');
let state = { q: '', mes: <?php echo $mesActual; ?>, page: 0, size: 20 };
let debounceTimer;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function fmtFecha(v){if(!v)return '—';const d=new Date(v+'T00:00:00');return isNaN(d.getTime())?'—':d.toLocaleDateString('es-CO');}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
document.getElementById('q').addEventListener('input',()=>{
  clearTimeout(debounceTimer);
  debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);
});
document.getElementById('mes').addEventListener('change',function(){state.mes=this.value;state.page=0;cargar();});

async function cargar(){
  const o=state.page*state.size;
  let qs=`q=${encodeURIComponent(state.q)}&limit=${state.size}&offset=${o}`;
  if(state.mes)qs+='&mes='+encodeURIComponent(state.mes);
  try{
    const d=await api('cumpleanos/listar?'+qs);
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${o+1}-${Math.min(o+state.size,total)} de ${total}`:'';
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>`
      <tr class="${r.cumple_hoy?'table-warning':''}">
        <td><span class="badge text-bg-light">${r.dia} ${esc(MESES[r.mes]||'')}</span></td>
        <td>${esc(r.cedula)}</td>
        <td>${esc(r.nombre)}${r.cumple_hoy?' <span class="badge text-bg-warning">¡Hoy!</span>':''}</td>
        <td>${fmtFecha(r.fecha_nacimiento)}</td>
        <td class="text-center">${r.edad}</td>
        <td class="text-center">${r.dias_para_cumple===0?'Hoy':(r.dias_para_cumple+' días')}</td>
        <td>${esc(r.celular||'—')}</td>
        <td><small>${esc(r.email||'—')}</small></td>
      </tr>`).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin cumpleaños</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

function renderPagination(total){
  const N=Math.ceil(total/state.size), nav=document.getElementById('paginacion');
  if(N<=1){nav.innerHTML='';return;}
  const C=state.page;
  let h='<ul class="pagination pagination-sm justify-content-center mb-0">';
  h+=`<li class="page-item ${C<=0?'disabled':''}"><a class="page-link" href="#" onclick="goPage(${C-1});return false;">«</a></li>`;
  for(let i=Math.max(0,C-2);i<=Math.min(N-1,C+2);i++){
    h+=`<li class="page-item ${i===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${i});return false;">${i+1}</a></li>`;
  }
  h+=`<li class="page-item ${C>=N-1?'disabled':''}"><a class="page-link" href="#" onclick="goPage(${C+1});return false;">»</a></li></ul>`;
  nav.innerHTML=h;
}
function goPage(p){if(p<0)return;state.page=p;cargar();}

document.addEventListener('DOMContentLoaded',cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
