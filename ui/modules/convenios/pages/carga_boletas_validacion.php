<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('convenios.boletas');
$pageTitle = 'Validación de carga masiva';
$currentPage = 'convenios_stock';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-check-double me-2"></i>Validación de carga masiva</span>
    <span>
      <button class="btn btn-light btn-sm me-1" id="btnCargar" onclick="commitCarga()"><i class="fas fa-database me-1"></i>Cargar a base de datos</button>
      <button class="btn btn-outline-light btn-sm me-1" onclick="cancelarCarga()"><i class="fas fa-times me-1"></i>Cancelar</button>
      <a class="btn btn-outline-light btn-sm" href="<?php echo uiBaseUrl(); ?>modules/convenios/pages/stock"><i class="fas fa-arrow-left me-1"></i>Volver</a>
    </span>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>
    <div id="sinCarga" class="d-none"></div>

    <p class="text-muted small">Archivo: <strong id="lblArchivo">—</strong> · <span id="lblTotal">0</span> registros</p>

    <div class="mb-2">
      <span class="badge text-bg-success">✔ Válida (se cargará)</span>
      <span class="badge text-bg-danger">✘ Error (corrige antes de cargar)</span>
      <span class="badge text-bg-warning">Precios vacíos = precio del producto</span>
    </div>

    <div class="mb-2 fw-semibold">
      <span class="text-success" id="cntOk">0</span> válidas ·
      <span class="text-danger" id="cntErr">0</span> con error
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle table-sm">
        <thead><tr>
          <th>#</th>
          <th>Producto *</th>
          <th>Serial *</th>
          <th>Nº factura *</th>
          <th>P. compra</th>
          <th>P. venta</th>
          <th>Vencimiento</th>
          <th>Estado</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>

    <p class="small text-muted mb-0"><i class="fas fa-lock me-1"></i>No se permite cargar mientras exista al menos una fila con error; el servidor vuelve a validar todo antes de insertar.</p>
  </div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>, U = <?php echo json_encode(uiBaseUrl()); ?>;
const alertBox = document.getElementById('alert');
let filas = [], productos = [];

function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function showAlert(msg,ok=false){alertBox.className='alert alert-'+(ok?'success':'danger');alertBox.textContent=msg;alertBox.classList.remove('d-none');}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}

function normNum(v){
  if(v===null||v==='')return null;
  if(typeof v==='number')return v;
  let s=String(v).trim(); if(s==='')return null;
  if(s.includes(',')&&s.includes('.')){
    if(s.lastIndexOf(',')>s.lastIndexOf('.')){s=s.split('.').join('');s=s.split(',').join('.');}
    else{s=s.split(',').join('');}
  }else if(s.includes(',')){s=s.split(',').join('.');}
  else if(s.split('.').length>2){s=s.split('.').join('');}
  if(!/^-?\d*\.?\d+$/.test(s))return null;
  return parseFloat(s);
}
function normFecha(v){
  if(typeof v==='number'){if(v<=0)return{ok:false,valor:null};return{ok:true,valor:new Date(Math.round((v-25569)*86400*1000)).toISOString().slice(0,10)};}
  let s=String(v??'').trim(); if(!s)return{ok:true,valor:null};
  let m=s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if(m){const y=+m[1],mo=+m[2],d=+m[3];return(d>=1&&d<=31&&mo>=1&&mo<=12)?{ok:true,valor:s}:{ok:false,valor:null};}
  m=s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if(m){const d=+m[1],mo=+m[2],y=+m[3];const val=y+'-'+String(mo).padStart(2,'0')+'-'+String(d).padStart(2,'0');return(d>=1&&d<=31&&mo>=1&&mo<=12)?{ok:true,valor:val}:{ok:false,valor:null};}
  return{ok:false,valor:null};
}

const CAMPOS=['producto','serial','numero_factura','precio_compra','precio_venta','fecha_vencimiento'];

function validarTodas(){
  const seen={};
  filas.forEach(f=>{
    f.errores={};
    const p=parseInt(f.producto_id||'0',10);
    if(!p||!productos.find(x=>x.id===p)){f.errores.producto='Producto de stock requerido.';}
    const serial=(f.serial||'').trim().toUpperCase();
    f.serial=serial;
    if(!serial){f.errores.serial='Serial requerido.';}
    else if(serial.length>64){f.errores.serial='Máximo 64 caracteres.';}
    else if(seen[''+p+'|'+serial]){f.errores.serial='Serial duplicado en el archivo.';}
    else seen[''+p+'|'+serial]=true;
    if(!(f.numero_factura||'').trim()){f.errores.numero_factura='Nº factura requerido.';}
    const pc=normNum(f.precio_compra);
    if((f.precio_compra??'')!==''&&pc===null){f.errores.precio_compra='Número inválido.';}
    else if(pc!==null&&pc<0){f.errores.precio_compra='No puede ser negativo.';}
    const pv=normNum(f.precio_venta);
    if((f.precio_venta??'')!==''&&pv===null){f.errores.precio_venta='Número inválido.';}
    else if(pv!==null&&pv<=0){f.errores.precio_venta='Debe ser mayor a $0.';}
    const fv=normFecha(f.fecha_vencimiento);
    if(!fv.ok){f.errores.fecha_vencimiento='Fecha inválida.';}
    f._fv=fv.valor;
    f.estado=Object.keys(f.errores).length?'error':'ok';
  });
  refrescar();
}

function refrescar(){
  document.querySelectorAll('#tbody tr').forEach((tr,i)=>{
    const f=filas[i]; if(!f)return;
    const cell=tr.querySelector('.estado'); if(!cell)return; // fila sin celdas de edición
    tr.classList.toggle('table-danger',f.estado==='error');
    cell.innerHTML=f.estado==='error'
      ?'<strong><span class="badge text-bg-danger">Error</span></strong>'
      :'<strong><span class="badge text-bg-success">Válida</span></strong>';
    CAMPOS.forEach(c=>{const el=tr.querySelector('.e-'+c);if(el)el.textContent=f.errores[c]||'';});
  });
  const ok=filas.filter(f=>f.estado==='ok').length, err=filas.length-ok;
  document.getElementById('cntOk').textContent=ok;
  document.getElementById('cntErr').textContent=err;
  document.getElementById('btnCargar').disabled=err>0;
}

function renderFila(f,i){
  const opts=productos.map(p=>`<option value="${p.id}" ${parseInt(f.producto_id,10)===p.id?'selected':''}>${esc(p.convenio_nombre)} — ${esc(p.nombre)} (${p.id})</option>`).join('');
  const inp=(cls,val,extra='')=>`<input class="form-control form-control-sm ${cls}" value="${esc(val)}" ${extra}>`;
  return `<tr class="${f.estado==='error'?'table-danger':''}">
    <td>${i+1}</td>
    <td><select class="form-select form-select-sm d-prod">${opts||'<option value="">—</option>'}</select><span class="text-danger small d-block e-producto"></span></td>
    <td>${inp('d-serial',f.serial)}<span class="text-danger small d-block e-serial"></span></td>
    <td>${inp('d-fac',f.numero_factura)}<span class="text-danger small d-block e-numero_factura"></span></td>
    <td>${inp('d-pc',f.precio_compra??'')}<span class="text-danger small d-block e-precio_compra"></span></td>
    <td>${inp('d-pv',f.precio_venta??'')}<span class="text-danger small d-block e-precio_venta"></span></td>
    <td>${inp('d-fv',f.fecha_vencimiento??'')}<span class="text-danger small d-block e-fecha_vencimiento"></span></td>
    <td class="estado"></td>
  </tr>`;
}
function render(){
  document.getElementById('tbody').innerHTML=filas.map(renderFila).join('');
  document.querySelectorAll('#tbody tr').forEach((tr,i)=>{
    const f=filas[i];
    tr.querySelector('.d-prod').addEventListener('change',function(){f.producto_id=this.value;const p=productos.find(x=>x.id===parseInt(this.value,10));if(p)f.producto_nombre=p.nombre;validarTodas();});
    tr.querySelector('.d-serial').addEventListener('input',function(){f.serial=this.value;validarTodas();});
    tr.querySelector('.d-fac').addEventListener('input',function(){f.numero_factura=this.value;validarTodas();});
    tr.querySelector('.d-pc').addEventListener('input',function(){f.precio_compra=this.value;validarTodas();});
    tr.querySelector('.d-pv').addEventListener('input',function(){f.precio_venta=this.value;validarTodas();});
    tr.querySelector('.d-fv').addEventListener('input',function(){f.fecha_vencimiento=this.value;validarTodas();});
  });
}

async function cargar(){
  try{
    const res=await api('boletas/carga/pendientes');
    if(!res.activa || !res.rows.length){
      document.getElementById('sinCarga').classList.remove('d-none');
      document.getElementById('sinCarga').innerHTML='<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i>No hay una carga pendiente. Inicia desde <a href="'+U+'modules/convenios/pages/stock">Stock de boletas › Carga masiva</a>.</div>';
      document.getElementById('tbody').innerHTML='';
      document.getElementById('btnCargar').disabled=true;
      return;
    }
    document.getElementById('lblArchivo').textContent=res.archivo||'—';
    document.getElementById('lblTotal').textContent=res.total||res.rows.length;
    filas=res.rows.map(r=>({
      indice:r.indice, producto_id:r.producto_id, producto_nombre:r.producto_nombre||'',
      serial:r.serial||'', numero_factura:r.numero_factura||'', precio_compra:r.precio_compra, precio_venta:r.precio_venta,
      fecha_vencimiento:r.fecha_vencimiento, estado:r.estado||'ok', errores:{}, _fv:null
    }));
    const r2=await api('boletas/resumen?producto_id=0');
    productos=r2.productos||[];
    render();   // renderizar primero (crea las celdas .estado) y luego validar
    validarTodas();
  }catch(e){showAlert(e.message);}
}

async function commitCarga(){
  if(document.getElementById('btnCargar').disabled)return;
  const err=filas.filter(f=>f.estado==='error').length;
  if(err>0){alert('Hay '+err+' fila(s) con error. Corrige antes de cargar.');return;}
  const rows=filas.map(f=>({
    producto_id:f.producto_id, producto_nombre:f.producto_nombre, serial:f.serial,
    numero_factura:(f.numero_factura||'').trim(),
    precio_compra:(f.precio_compra??''), precio_venta:(f.precio_venta??''),
    fecha_vencimiento:f._fv||''
  }));
  if(!confirm('¿Cargar '+rows.length+' boleta(s) a la base de datos?'))return;
  const btn=document.getElementById('btnCargar'); btn.disabled=true;
  try{
    const res=await post('boletas/carga/commit',{rows});
    showAlert('Carga OK · '+res.creadas+' boleta(s) creada(s).',true);
    setTimeout(()=>location.href=U+'modules/convenios/pages/stock',1600);
  }catch(e){btn.disabled=false;showAlert(e.message);validarTodas();}
}
function cancelarCarga(){
  if(!confirm('¿Descartar la carga pendiente? No se guardará nada.'))return;
  post('boletas/carga/cancelar',{}).then(()=>{location.href=U+'modules/convenios/pages/stock';}).catch(e=>showAlert(e.message));
}

document.addEventListener('DOMContentLoaded',cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>