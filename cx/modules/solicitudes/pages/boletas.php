<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Solicitar boletas';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card">
  <div class="card-header-brand"><i class="fa-solid fa-cart-shopping"></i><div class="fw-semibold">Armar carrito de boletas</div></div>
  <div class="p-3">
    <div id="cat" class="text-center text-muted py-3">Cargando catálogo…</div>
    <div id="carrito" class="d-none">
      <hr><h6 class="fw-semibold">Carrito</h6><div id="cartItems"></div>
      <div class="input-group input-group-sm mt-2 mb-2"><span class="input-group-text">Motivo / entrega</span><input class="portal-input form-control" id="bMotivo" placeholder="Observaciones"></div>
      <button class="btn btn-portal-gradient" onclick="enviar()"><i class="fa-solid fa-paper-plane me-1"></i>Solicitar boletas</button>
      <div id="msg" class="mt-2 small"></div>
    </div>
  </div>
</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>;
const CART_KEY='cx_boleta_cart';
var catalog=[],cart={};
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){return '$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
function guardarCart(){try{localStorage.setItem(CART_KEY,JSON.stringify(cart));}catch(e){}}
function leerCart(){try{return JSON.parse(localStorage.getItem(CART_KEY))||{};}catch(e){return {};}}

function renderCat(){
  var cont=document.getElementById('cat'),html='<div class="row g-2">';
  catalog.forEach(function(p){
    var tot=(cart[p.id]||0);
    html+='<div class="col-md-6"><div class="border rounded p-2 h-100 d-flex justify-content-between align-items-center"><div><div class="fw-semibold small">'+esc(p.nombre)+'</div><div class="text-muted small">'+esc(p.convenio)+' · '+money(p.precio_venta)+'</div><div class="text-muted" style="font-size:.75rem">Disponibles: '+p.disponibles+'</div></div>'+
      '<div class="d-flex align-items-center"><button class="btn btn-sm btn-portal-outline" onclick="changeQty('+p.id+',-1)">−</button><span class="mx-2 fw-semibold" id="qty'+p.id+'">'+tot+'</span><button class="btn btn-sm btn-portal-gradient" onclick="changeQty('+p.id+',1)">+</button></div></div></div>';
  });
  cont.innerHTML=html;
}
function changeQty(id,delta){
  var p=catalog.find(x=>x.id===id);if(!p)return;
  cart[id]=Math.max(0,(cart[id]||0)+delta);
  if(cart[id]>p.disponibles)cart[id]=p.disponibles;
  if(!cart[id])delete cart[id];
  document.getElementById('qty'+id).textContent=cart[id];
  renderCart();guardarCart();
}
function renderCart(){
  var rows=Object.entries(cart).filter(([,q])=>q>0);
  var cont=document.getElementById('carrito');
  document.getElementById('cartItems').innerHTML=rows.map(function([id,q]){var p=catalog.find(x=>x.id===id);return '<div class="d-flex justify-content-between border-bottom py-1"><span>'+esc(p.nombre)+' × '+q+'</span><b>'+money(p.precio_venta*q)+'</b><button class="btn btn-sm btn-portal-outline" onclick="changeQty('+id+',-100)">Quitar</button></div>';}).join('')||'<span class="text-muted">Carrito vacío</span>';
  cont.classList.toggle('d-none',!rows.length);
}
function enviar(){
  var items=Object.entries(cart).filter(([,q])=>q>0).map(function([id,q]){return {producto_id:Number(id),cantidad:q};});
  var msg=document.getElementById('msg');
  if(!items.length){msg.className='text-danger small';msg.textContent='Agregue al menos un producto al carrito.';return;}
  msg.className='text-muted small';msg.textContent='Enviando…';
  fetch(PAPI+'/crear.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tipo:'boleta',detalle:{motivo:document.getElementById('bMotivo').value.trim(),items:items}})}).then(r=>r.json()).then(function(j){
    if(!j.ok){msg.className='text-danger small';msg.textContent=j.error;return;}
    msg.className='text-success small';msg.textContent='Solicitud '+j.data.solicitud.numero_radicado+' creada. El área la atenderá y emitirá las boletas.';
    cart={};guardarCart();renderCat();renderCart();
  });
}
fetch(PAPI+'/productos_boleta.php').then(r=>r.json()).then(function(j){
  if(!j.ok){document.getElementById('cat').textContent=j.error;return;}
  catalog=j.data.rows||[];
  cart=leerCart();
  catalog.forEach(function(p){ if((cart[p.id]||0)>p.disponibles) cart[p.id]=p.disponibles; });
  renderCat();renderCart();
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>