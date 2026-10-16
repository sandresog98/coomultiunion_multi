<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Convenios · Boletas';
$activeNav = 'convenios';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <div class="fw-semibold fs-5"><i class="fa-solid fa-handshake me-2"></i>Convenios con boletas disponibles</div>
    <div class="text-muted small">Agrega boletas al carrito y finaliza desde "Solicitar boletas".</div>
  </div>
  <button class="btn btn-portal-gradient" onclick="irCarrito()" id="btnCarrito"><i class="fa-solid fa-cart-shopping me-1"></i>Ir al carrito <span id="cartCount" class="badge bg-light text-dark ms-1 d-none"></span></button>
</div>
<div id="cat" class="text-center text-muted py-4">Cargando convenios…</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>;
const CART_KEY='cx_boleta_cart';
function money(v){return '$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function leerCart(){try{return JSON.parse(localStorage.getItem(CART_KEY))||{};}catch(e){return {};}}
function guardarCart(c){localStorage.setItem(CART_KEY,JSON.stringify(c));pintarContador();}
function contador(){var c=leerCart();return Object.values(c).reduce(function(a,b){return a+(Number(b)||0);},0);}
function pintarContador(){
  var n=contador(),el=document.getElementById('cartCount');
  if(n>0){el.textContent=n;el.classList.remove('d-none');}else{el.classList.add('d-none');}
}
function irCarrito(){window.location.href=CX_BASE+'/modules/solicitudes/pages/boletas';}
function add(id){
  var c=leerCart();c[id]=Math.max(0,(c[id]||0)+1);
  var p=window.__prod[id];if(p&&c[id]>p.disponibles)c[id]=p.disponibles;
  guardarCart(c);document.getElementById('qty'+id).textContent=c[id]||0;
}
function sub(id){
  var c=leerCart();c[id]=Math.max(0,(c[id]||0)-1);if(!c[id])delete c[id];
  guardarCart(c);document.getElementById('qty'+id).textContent=c[id]||0;
}
(function init(){
  pintarContador();
  fetch(CX_API+'/convenios.php').then(function(r){return r.json();}).then(function(j){
    var cat=document.getElementById('cat');
    if(!j.ok){cat.textContent=j.error||'Error';return;}
    var convs=j.data||[];window.__prod={};
    if(!convs.length){cat.className='text-center text-muted py-4';cat.innerHTML='No hay convenios con boletas disponibles.';return;}
    var cart=leerCart(),html='';
    convs.forEach(function(c){
      html+='<div class="col-12 mb-3"><div class="portal-card"><div class="card-header-brand"><i class="fa-solid fa-store me-1"></i>'+esc(c.nombre)+'</div><div class="p-3"><div class="row g-2">';
      c.productos.forEach(function(p){
        window.__prod[p.id]=p;
        var q=(cart[p.id]||0);
        html+='<div class="col-md-6 col-lg-4"><div class="border rounded p-2 h-100 d-flex flex-column justify-content-between"><div><div class="fw-semibold small">'+esc(p.nombre)+'</div><div class="text-muted small">'+money(p.precio_venta)+'</div><div class="text-muted" style="font-size:.75rem">Disponibles: '+p.disponibles+'</div></div><div class="d-flex align-items-center justify-content-between mt-2"><button class="btn btn-sm btn-portal-outline" onclick="sub('+p.id+')">−</button><span class="fw-semibold" id="qty'+p.id+'">'+q+'</span><button class="btn btn-sm btn-portal-gradient" onclick="add('+p.id+')">+</button></div></div></div>';
      });
      html+='</div></div></div></div>';
    });
    cat.className='row';
    cat.innerHTML=html;
  });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>