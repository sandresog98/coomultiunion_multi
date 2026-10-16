<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Movimientos';
$activeNav = 'mov';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-12">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3 d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2"><i class="fa-solid fa-list fa-lg"></i><div class="fw-semibold">Pagos y transacciones</div></div>
        <input type="month" id="fMes" class="form-control form-control-sm" style="width:auto">
      </div>
      <div id="box" class="text-center text-muted py-4">Cargando…</div>
      <div id="body"></div>
      <div id="info" class="d-none small text-muted text-end mt-1"></div>
      <nav id="nav" class="d-none"></nav>
      <div id="empty" class="d-none text-center text-muted py-4">No hay movimientos.</div>
    </div>
  </div>
</div>
<script>
var API=CX_API, mes='', tod=[]; var st={page:1};
var fMes=document.getElementById('fMes');
(function prevMonth(){ var d=new Date(), y=d.getFullYear(), m=d.getMonth()-1; if(m<0){ y--; m=11; } var v=y+'-'+String(m+1).padStart(2,'0'); fMes.value=v; mes=v; })();
fMes.addEventListener('change',function(){mes=fMes.value||'';cargar();});
function navHtml(rows){
  var per=20, pag=Math.max(1,Math.ceil(rows.length/per)), p=Math.min(st.page,pag);
  if(pag<=1){ st.page=1; return ''; }
  var h='<ul class="pagination pagination-sm justify-content-center mb-0">';
  h+='<li class="page-item '+(p<=1?'disabled':'')+'"><a class="page-link" href="#" data-p="'+(p-1)+'">‹</a></li>';
  for(var i=1;i<=pag;i++){ if(i===1||i===pag||Math.abs(i-p)<=1) h+='<li class="page-item '+(i===p?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>'; else if(Math.abs(i-p)===2) h+='<li class="page-item disabled"><span class="page-link">…</span></li>'; }
  h+='<li class="page-item '+(p>=pag?'disabled':'')+'"><a class="page-link" href="#" data-p="'+(p+1)+'">›</a></li>'; return h;
}
function cardHtml(t){
  var det='<div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Referencia</th><th>Detalle</th><th>Valor</th></tr></thead><tbody class="small">'
    +t.detalles.map(function(d){return '<tr><td>'+esc(d.tipo_rubro)+'</td><td>'+esc(d.referencia_credito||d.producto_id||'—')+'</td><td>'+esc(d.descripcion||'—')+'</td><td>'+money(d.valor_asignado)+'</td></tr>';}).join('')
    +'</tbody></table></div>';
  return '<div class="portal-card mb-2"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2">'
    +'<div><span class="badge bg-primary">'+esc(t.origen)+'</span> <span class="text-muted small">RC '+esc(t.recibo_caja)+'</span></div>'
    +'<div class="fw-semibold money">'+money(t.valor_total)+'</div>'
    +'</div><div class="text-muted small mb-2">'+esc(t.fecha_creacion)+' · '+t.items+' item(s)</div>'+det+'</div>';
}
function render(){
  var rows=tod, body=document.getElementById('body'), info=document.getElementById('info'), nav=document.getElementById('nav'), empty=document.getElementById('empty');
  if(!rows.length){ body.innerHTML=''; info.classList.add('d-none'); nav.classList.add('d-none'); empty.classList.remove('d-none'); return; }
  empty.classList.add('d-none');
  var per=20, pag=Math.max(1,Math.ceil(rows.length/per)); st.page=Math.min(st.page,pag);
  var ini=(st.page-1)*per, fin=Math.min(rows.length,ini+per), page=rows.slice(ini,fin);
  body.innerHTML=page.map(cardHtml).join('');
  info.textContent='Mostrando '+(rows.length?ini+1:0)+'-'+fin+' de '+rows.length; info.classList.remove('d-none');
  nav.innerHTML=navHtml(rows); nav.classList.remove('d-none');
}
async function cargar(){
  var box=document.getElementById('box');
  var sp=new URLSearchParams(); if(mes)sp.set('mes',mes);
  var r=await fetch(API+'/movimientos.php?'+sp.toString()); var j=await r.json();
  if(!j.ok){ box.textContent=j.error||'Error'; return; }
  tod=j.data||[]; box.classList.add('d-none');
  render();
}
document.getElementById('nav').addEventListener('click',function(e){var a=e.target.closest('a[data-p]');if(!a)return;e.preventDefault();var p=+a.dataset.p;var pag=Math.ceil(tod.length/20);if(p<1||p>pag)return;st.page=p;render();});
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>