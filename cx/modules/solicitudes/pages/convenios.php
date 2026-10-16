<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Convenios';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="d-flex justify-content-end align-items-center mb-2">
  <div class="text-muted small" id="cartBadge"></div>
</div>

<div class="portal-card mb-3">
  <div class="p-3">
    <div class="d-flex align-items-center flex-wrap gap-1 mb-3" id="stepper"></div>
    <div id="app" class="text-muted">Cargando…</div>
    <div id="msg" class="small mt-2"></div>
  </div>
</div>

<!-- Modal de cantidad -->
<div class="modal fade" id="qtyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title">Cantidad</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="qtyProd" class="fw-semibold"></div>
        <div id="qtyPrice" class="text-muted small mb-3"></div>
        <label class="form-label small text-muted mb-0">Unidades</label>
        <div class="input-group">
          <button class="btn btn-outline-secondary" type="button" onclick="qtyStep(-1)">−</button>
          <input id="qtyInput" type="number" min="1" value="1" class="form-control text-center">
          <button class="btn btn-outline-secondary" type="button" onclick="qtyStep(1)">+</button>
        </div>
        <div class="text-end small mt-2">Total: <b id="qtyTotal">$0</b></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-portal-gradient btn-sm" id="qtyAdd">Agregar</button>
      </div>
    </div>
  </div>
</div>

<div id="toasts" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080"></div>

<script>
const CXAPI=<?php echo json_encode($API); ?>;                       // /api/portal
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>;   // creación de la solicitud (boleta)
const ASSET=<?php echo json_encode($APP . '/assets'); ?>;           // imágenes estáticas
const CART_KEY='cx_convenios_cart_v1';
const CED=<?php echo json_encode((string) ($actor['cedula'] ?? '')); ?>;
let CONV=[], cart={}, step=1, convId=null, obs='', metodoPago=null, PAGO_INFO=null, pagoLoading=false;

function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function money(v){return '$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
function conv(){return CONV.find(function(c){return c.id===convId;})||null;}
function prod(id){var c=conv();if(!c)return null;return c.productos.find(function(p){return p.id===id;})||null;}
function total(){var t=0;Object.keys(cart).forEach(function(id){var p=prod(Number(id));if(p)t+=p.precio_venta*cart[id];});return t;}
function countItems(){return Object.keys(cart).reduce(function(a,id){return a+cart[id];},0);}
function save(){try{sessionStorage.setItem(CART_KEY,JSON.stringify({u:CED,conv:convId,items:cart}));}catch(e){}}
function load(){try{var d=JSON.parse(sessionStorage.getItem(CART_KEY))||{};return {u:d.u||null,conv:d.conv||null,items:d.items||{}};}catch(e){return {u:null,conv:null,items:{}};}}
function msg(t,cls){var m=document.getElementById('msg');m.className='small mt-2 '+(cls||'text-muted');m.textContent=t||'';}
function toast(text,type){
  var box=document.getElementById('toasts');if(!box)return;
  var el=document.createElement('div');
  el.className='toast align-items-center text-bg-'+(type||'success')+' border-0';
  el.innerHTML='<div class="d-flex"><div class="toast-body">'+esc(text)+'</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  box.appendChild(el);
  new bootstrap.Toast(el,{delay:2500}).show();
  el.addEventListener('hidden.bs.toast',function(){el.remove();});
}

function renderStepper(){
  var names=['Convenio','Productos','Carrito','Pago'],html=[];
  for(var i=0;i<4;i++){
    var s=i+1,on=s<=step;
    var enabled=(s===1)||(s===2&&convId)||(s===3&&countItems())||(s===4&&countItems());
    html.push('<button type="button" class="btn btn-sm '+(on?'btn-portal-gradient':'btn-portal-outline')+'" '+(enabled?'':'disabled')+' onclick="irPaso('+s+')">'+s+'. '+names[i]+'</button>');
  }
  document.getElementById('stepper').innerHTML=html.join('<i class="fa-solid fa-chevron-right text-muted mx-1" style="font-size:.7rem"></i>');
}
function render(){
  document.getElementById('cartBadge').innerHTML=countItems()>0?('<i class="fa-solid fa-cart-shopping me-1"></i>'+countItems()+' ítem(s) · '+money(total())):'';
  renderStepper();
  var app=document.getElementById('app');
  if(step===1)app.innerHTML=vConvenios();
  else if(step===2)app.innerHTML=vProductos();
  else if(step===3)app.innerHTML=vCarrito();
  else app.innerHTML=vPago();
}
function irPaso(s){
  if(s===1){cambiar();return;}
  if(s===2&&convId){step=2;render();msg('');return;}
  if(s===3&&countItems()){step=3;render();msg('');return;}
  if(s===4&&countItems()){step=4;render();msg('');return;}
}

function vConvenios(){
  if(!CONV.length)return '<div class="text-center text-muted py-4">No hay convenios disponibles.</div>';
  var h='<div class="row g-3">';
  CONV.forEach(function(c){
    var media='<div class="position-relative" style="height:150px;background:linear-gradient(135deg,#11458B,#1384D2)">'
      +'<div class="d-flex align-items-center justify-content-center h-100 text-white" style="font-size:2rem"><i class="fa-solid fa-store"></i></div>'
      +(c.tiene_imagen?'<img src="'+CXAPI+'/convenio_imagen.php?id='+c.id+'" alt="" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit:cover" onerror="this.remove()">':'')
      +'</div>';
    h+='<div class="col-6 col-md-4 col-lg-3"><button type="button" class="portal-card portal-card-glowing-border text-center w-100 h-100 p-0 overflow-hidden" onclick="elegir('+c.id+')">'
      +media
      +'<div class="p-2"><div class="fw-semibold small">'+esc(c.nombre)+'</div></div>'
      +'</button></div>';
  });
  return h+'</div>';
}
function elegir(id){
  if(convId&&convId!==id&&Object.keys(cart).length&&!confirm('Cambiar de convenio reiniciará tu carrito. ¿Continuar?'))return;
  if(convId!==id){cart={};metodoPago=null;obs='';}
  convId=id;step=2;save();render();msg('');
}

/* Paso 2: lista de productos (izquierda) + vista previa del carrito (derecha). */
function vProductos(){
  var c=conv();if(!c)return vConvenios();
  var h='<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">'
    +'<span class="fw-semibold"><i class="fa-solid fa-store me-1"></i>'+esc(c.nombre)+'</span>'
    +'<button class="btn btn-sm btn-portal-outline" onclick="cambiar()"><i class="fa-solid fa-rotate-left me-1"></i>Cambiar convenio</button>'
    +'</div>';
  h+='<div class="row g-3">';
  h+='<div class="col-12 col-lg-7"><div class="border rounded">';
  c.productos.forEach(function(p){
    h+='<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">'
      +'<div><div class="fw-semibold">'+esc(p.nombre)+'</div><div class="text-muted small">Precio unidad: '+money(p.precio_venta)+'</div></div>'
      +'<button class="btn btn-sm btn-portal-gradient" onclick="abrirAgregar('+p.id+')"><i class="fa-solid fa-cart-plus me-1"></i>Agregar</button>'
      +'</div>';
  });
  h+='</div></div>';
  h+='<div class="col-12 col-lg-5"><div class="border rounded p-3 h-100">'+vCartPreview()+'</div></div>';
  h+='</div>';
  return h;
}
function vCartPreview(){
  var h='<div class="fw-semibold mb-2"><i class="fa-solid fa-cart-shopping me-1"></i>Carrito</div>';
  if(!countItems()){h+='<div class="text-muted small">Aún no has agregado productos.</div>';return h;}
  Object.keys(cart).forEach(function(id){
    var pid=Number(id),p=prod(pid);if(!p)return;
    h+='<div class="d-flex justify-content-between align-items-center small py-1 border-bottom">'
      +'<span class="text-truncate me-2">'+esc(p.nombre)+' <span class="text-muted">×'+cart[id]+'</span></span>'
      +'<span class="d-flex align-items-center gap-2 text-nowrap"><b>'+money(p.precio_venta*cart[id])+'</b>'
      +'<button class="btn btn-sm btn-outline-danger py-0 px-1" title="Quitar" onclick="cartQty('+pid+',-1e9)"><i class="fa-solid fa-xmark"></i></button></span>'
      +'</div>';
  });
  h+='<div class="d-flex justify-content-between align-items-center mt-2"><span class="fw-semibold">Total</span><b>'+money(total())+'</b></div>';
  h+='<button class="btn btn-portal-gradient w-100 mt-2" onclick="irPaso(3)"><i class="fa-solid fa-arrow-right me-1"></i>Continuar'+(countItems()?' ('+countItems()+')':'')+'</button>';
  return h;
}

/* Paso 3: carrito completo. */
function vCarrito(){
  var c=conv();
  if(!c||!countItems())return '<div class="text-center text-muted py-3">Tu carrito está vacío.</div>';
  var h='<div class="fw-semibold mb-1"><i class="fa-solid fa-cart-shopping me-1"></i>Carrito de compras</div>';
  Object.keys(cart).forEach(function(id){
    var pid=Number(id),p=prod(pid);if(!p)return;
    h+='<div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2">'
      +'<div class="flex-grow-1"><div class="fw-semibold small">'+esc(c.nombre)+' — '+esc(p.nombre)+'</div>'
      +'<div class="text-muted small">'+money(p.precio_venta)+' c/u</div></div>'
      +'<div class="input-group input-group-sm" style="max-width:130px">'
      +'<button class="btn btn-outline-secondary" onclick="cartQty('+pid+',-1)">−</button>'
      +'<input class="form-control text-center" value="'+cart[id]+'" readonly>'
      +'<button class="btn btn-outline-secondary" onclick="cartQty('+pid+',1)">+</button></div>'
      +'<b class="text-end" style="min-width:90px">'+money(p.precio_venta*cart[id])+'</b>'
      +'<button class="btn btn-sm btn-outline-danger" title="Quitar" onclick="cartQty('+pid+',-1e9)"><i class="fa-solid fa-trash"></i></button>'
      +'</div>';
  });
  h+='<div class="d-flex justify-content-between align-items-center mt-3"><span class="fw-semibold">Total</span><span class="fs-5 fw-bold">'+money(total())+'</span></div>';
  h+='<div class="mt-3"><label class="form-label small text-muted mb-0">Observaciones (opcional)</label>'
    +'<textarea id="motivo" rows="2" class="form-control form-control-sm" placeholder="Observaciones" oninput="obs=this.value">'+esc(obs)+'</textarea></div>';
  h+='<div class="d-flex flex-wrap gap-2 mt-3">'
    +'<button class="btn btn-portal-outline" onclick="volverProductos()"><i class="fa-solid fa-plus me-1"></i>Agregar otro producto</button>'
    +'<button class="btn btn-portal-gradient ms-auto" onclick="irPaso(4)"><i class="fa-solid fa-arrow-right me-1"></i>Continuar a pago</button>'
    +'</div>';
  return h;
}

/* Paso 4: método de pago. */
function vPago(){
  if(PAGO_INFO===null){ if(!pagoLoading){pagoLoading=true;cargarPagoInfo();} return '<div class="text-center text-muted py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Consultando disponibilidad…</div>'; }
  var tot=total();
  var aptoCred=PAGO_INFO.apto && tot<=(Number(PAGO_INFO.limite)||0);
  var motivo='';
  if(!PAGO_INFO.apto)motivo='No cumples los requisitos: necesitas 6 meses de antigüedad o 6 pagos de aportes.';
  else if(tot>(Number(PAGO_INFO.limite)||0))motivo='El total supera el límite de '+money(PAGO_INFO.limite)+'.';
  // Si el crédito dejó de ser válido (p. ej. cambió el total), se limpia la selección.
  if(metodoPago==='credito'&&!aptoCred)metodoPago=null;

  var h='<div class="fw-semibold mb-2"><i class="fa-solid fa-credit-card me-1"></i>Método de pago</div>';
  h+='<div class="row g-3">';
  // QR
  h+='<div class="col-12 col-md-6"><div class="border rounded p-3 h-100 '+(metodoPago==='qr'?'border-2 border-primary':'')+'">'
    +'<label class="d-flex align-items-center gap-2" style="cursor:pointer"><input type="radio" name="mp" '+(metodoPago==='qr'?'checked':'')+' onchange="setMetodo(\'qr\')"><b>Pago por QR</b></label>'
    +'<div class="text-muted small mt-1">Escanea el QR, paga el total y sube el comprobante.</div>';
  if(metodoPago==='qr'){
    h+='<div class="text-center mt-2"><img src="'+ASSET+'/img/qr_pago.png" alt="QR de pago" style="max-width:240px;width:100%;border-radius:8px" onerror="this.outerHTML=\'<div class=&quot;text-muted small&quot;>Imagen QR no disponible.</div>\'"></div>'
      +'<div class="mt-2"><label class="form-label small text-muted mb-0">Comprobante <span class="text-danger">*</span></label>'
      +'<input type="file" id="comp" class="form-control form-control-sm" accept="image/*,application/pdf" onchange="onComp(this)">'
      +'<div class="small text-muted mt-1" id="compMsg"></div></div>';
  }
  h+='</div></div>';
  // Crédito
  h+='<div class="col-12 col-md-6"><div class="border rounded p-3 h-100 '+(metodoPago==='credito'?'border-2 border-primary':'')+'" style="'+(aptoCred?'':'opacity:.6')+'">'
    +'<label class="d-flex align-items-center gap-2" style="'+(aptoCred?'cursor:pointer':'cursor:not-allowed')+'"><input type="radio" name="mp" '+(metodoPago==='credito'?'checked':'')+' '+(aptoCred?'':'disabled')+' onchange="setMetodo(\'credito\')"><b>Crédito cooperativa</b></label>'
    +'<div class="text-muted small mt-1">Paga con crédito de la cooperativa (hasta '+money(PAGO_INFO.limite)+').</div>'
    +(aptoCred?'<div class="small text-success mt-1"><i class="fa-solid fa-circle-check me-1"></i>Disponible</div>':'<div class="small text-danger mt-1"><i class="fa-solid fa-circle-xmark me-1"></i>'+esc(motivo)+'</div>')
    +'</div></div>';
  h+='</div>';
  h+='<div class="d-flex justify-content-between align-items-center mt-3"><span class="fw-semibold">Total</span><span class="fs-5 fw-bold">'+money(tot)+'</span></div>';
  h+='<div class="d-flex flex-wrap gap-2 mt-3">'
    +'<button class="btn btn-portal-outline" onclick="volverCarrito()"><i class="fa-solid fa-arrow-left me-1"></i>Volver al carrito</button>'
    +'<button class="btn btn-portal-gradient ms-auto" id="btnEnviar" onclick="enviar()" '+(metodoPago?'':'disabled')+'><i class="fa-solid fa-paper-plane me-1"></i>Enviar solicitud</button>'
    +'</div>';
  return h;
}
function setMetodo(m){metodoPago=m;render();msg('');}
function onComp(inp){
  var el=document.getElementById('compMsg');
  if(el)el.textContent=inp.files&&inp.files[0]?('Archivo: '+inp.files[0].name):'';
}
function cargarPagoInfo(){
  fetch(CXAPI+'/boleta_pago_info.php').then(function(r){return r.json();}).then(function(j){
    PAGO_INFO=j.ok?(j.data||{}):{apto:false,limite:100000};
    pagoLoading=false;render();
  }).catch(function(){PAGO_INFO={apto:false,limite:100000};pagoLoading=false;render();});
}

function volverProductos(){step=2;render();msg('');}
function volverCarrito(){step=3;render();msg('');}
function cambiar(){
  if(Object.keys(cart).length&&!confirm('Cambiar de convenio reiniciará tu carrito. ¿Continuar?'))return;
  cart={};convId=null;metodoPago=null;obs='';step=1;save();render();msg('');
}
function irCarrito(){step=3;render();msg('');}
function cartQty(id,d){cart[id]=(cart[id]||0)+d;if(cart[id]<=0)delete cart[id];save();render();}

/* Modal de cantidad */
let qtyPid=null,qtyVal=1,qtyModal=null;
function abrirAgregar(pid){
  var p=prod(pid);if(!p)return;
  qtyPid=pid;qtyVal=1;
  document.getElementById('qtyProd').textContent=p.nombre;
  document.getElementById('qtyPrice').textContent='Precio unidad: '+money(p.precio_venta);
  document.getElementById('qtyInput').value=1;
  document.getElementById('qtyTotal').textContent=money(p.precio_venta);
  if(!qtyModal)qtyModal=new bootstrap.Modal(document.getElementById('qtyModal'));
  qtyModal.show();
}
function qtySet(v){var p=prod(qtyPid);qtyVal=Math.max(1,Number(v)||1);document.getElementById('qtyInput').value=qtyVal;if(p)document.getElementById('qtyTotal').textContent=money(p.precio_venta*qtyVal);}
function qtyStep(d){qtySet(qtyVal+d);}
function qtyConfirmar(){if(!qtyPid)return;var p=prod(qtyPid);cart[qtyPid]=(cart[qtyPid]||0)+qtyVal;qtyModal.hide();save();render();toast('Agregado: '+p.nombre+' ×'+qtyVal);qtyPid=null;}
document.getElementById('qtyAdd').onclick=qtyConfirmar;
document.getElementById('qtyInput').addEventListener('input',function(){qtySet(this.value);});

function enviar(){
  if(!metodoPago){msg('Selecciona un método de pago.','text-danger');return;}
  if(metodoPago==='credito'&&!(PAGO_INFO&&PAGO_INFO.apto&&total()<=(Number(PAGO_INFO.limite)||0))){
    msg('El crédito cooperativa no está disponible para este total.','text-danger');return;
  }
  var items=Object.keys(cart).map(function(id){return {producto_id:Number(id),cantidad:cart[id]};});
  if(!items.length){msg('El carrito está vacío.','text-danger');return;}
  var btn=document.getElementById('btnEnviar');
  var fd=new FormData();
  fd.append('tipo','boleta');
  fd.append('detalle',JSON.stringify({motivo:obs,items:items,metodo_pago:metodoPago}));
  if(metodoPago==='qr'){
    var fi=document.getElementById('comp');
    var f=fi&&fi.files?fi.files[0]:null;
    if(!f){msg('Adjunta el comprobante del pago por QR.','text-danger');return;}
    fd.append('files[]',f);
  }
  if(btn){btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Enviando…';}
  fetch(PAPI+'/crear.php',{method:'POST',body:fd})
    .then(function(r){return r.json();}).then(function(j){
      if(!j.ok){
        if(btn){btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-paper-plane me-1"></i>Enviar solicitud';}
        msg(j.error||'Error','text-danger');return;
      }
      cart={};convId=null;obs='';metodoPago=null;PAGO_INFO=null;step=1;save();
      document.getElementById('cartBadge').innerHTML='';renderStepper();
      document.getElementById('app').innerHTML='<div class="text-center py-4">'
        +'<div class="mb-2" style="font-size:2.4rem;color:#10b981"><i class="fa-solid fa-circle-check"></i></div>'
        +'<div class="fw-semibold">Solicitud creada</div>'
        +'<div class="text-muted small">Radicado: <b>'+esc(j.data.solicitud.numero_radicado)+'</b></div>'
        +'<div class="text-muted small mt-2">El área la atenderá y emitirá las boletas.</div>'
        +'<a class="btn btn-sm btn-portal-outline mt-3" href="'+cxUrl('solicitudes/mis')+'">Ver mis solicitudes</a>'
        +'</div>';
      msg('');
    }).catch(function(){
      if(btn){btn.disabled=false;btn.innerHTML='<i class="fa-solid fa-paper-plane me-1"></i>Enviar solicitud';}
      msg('Error de conexión.','text-danger');
    });
}

(function init(){
  try{localStorage.removeItem(CART_KEY);}catch(e){}   // migración: antes se usaba localStorage
  fetch(CXAPI+'/boleta_pendiente.php').then(function(r){return r.json();}).then(function(p){
    if(p.ok&&p.data&&p.data.pendiente){
      document.getElementById('stepper').innerHTML='';
      document.getElementById('app').innerHTML='<div class="alert alert-warning mb-0"><i class="fa-solid fa-circle-exclamation me-1"></i>Ya tienes una solicitud de boletas en trámite. Podrás crear una nueva cuando se resuelva.</div>'
        +'<a class="btn btn-sm btn-portal-outline mt-3" href="'+cxUrl('solicitudes/mis')+'">Ver mis solicitudes</a>';
      return;
    }
    cargarCatalogo();
  }).catch(function(){cargarCatalogo();});
})();
function cargarCatalogo(){
  var saved=load();
  fetch(CXAPI+'/convenios.php').then(function(r){return r.json();}).then(function(j){
    if(!j.ok){document.getElementById('app').innerHTML='<div class="alert alert-warning">'+esc(j.error||'Error')+'</div>';return;}
    CONV=j.data||[];
    if(saved.conv&&saved.u===CED){
      var c=CONV.find(function(x){return x.id===saved.conv;});
      if(c){convId=saved.conv;cart=saved.items||{};step=2;
        Object.keys(cart).forEach(function(id){if(!prod(Number(id)))delete cart[id];});
        save();
      }
    }
    render();
  }).catch(function(){document.getElementById('app').innerHTML='<div class="alert alert-danger">Error de conexión.</div>';});
}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
