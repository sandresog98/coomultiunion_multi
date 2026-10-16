<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$numero = trim((string) ($_GET['numero'] ?? ''));
if ($numero === '') { header('Location: ' . $CX . '/modules/creditos/pages/index'); exit; }
$pageTitle = 'Detalle crédito ' . $numero;
$activeNav = 'cred';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-12">
    <div id="box" class="text-center text-muted py-5">Cargando…</div>
  </div>
</div>
<script>
(async function(){
  var box=document.getElementById('box');
  var r=await fetch(CX_API+'/creditos_detalle.php?numero='+encodeURIComponent('<?= $numero ?>')); var j=await r.json();
  if(!j.ok){ box.textContent=j.error||'Error'; return; }
  var d=j.data, enMora=d.saldo_mora>0;
  var cards=[
    ['Información general','bg-primary text-white',[
      ['Número',d.numero_credito],['Tipo',d.tipo_prestamo],['Fecha inicio',d.fecha_emision],
      ['Vencimiento',d.fecha_vencimiento],['Cuotas',d.cuotas_pendientes+' / '+d.cuotas_totales],['Cuota',money(d.valor_cuota)],
    ]],
    ['Estados financieros','bg-info text-white',[
      ['Desembolso inicial',money(d.desembolso_inicial)],['Saldo capital',money(d.saldo_capital)],
      ['Saldo intereses',money(d.saldo_intereses)],['Saldo total',money(d.saldo_total)],
    ]],
    ['Estado de pago',(enMora?'bg-danger text-white':'bg-success text-white'),[
      ['Estado',enMora?'En mora':'Al día'],['Días mora',d.dias_mora],['Saldo mora',money(d.saldo_mora)],
      ['Próximo pago',d.fecha_pago],
    ]],
  ];
  var html='';
  cards.forEach(function(c){
    html+='<div class="portal-card mb-3"><div class="card-header-brand mb-3"><span class="badge '+c[1]+' px-3 py-2">'+esc(c[0])+'</span></div><div class="portal-datos-grid">'
      +c[2].map(function(f){return '<div class="dato-item"><div class="dato-label">'+esc(f[0])+'</div><div class="dato-val">'+esc(f[1])+'</div></div>';}).join('')
      +'</div></div>';
  });
  box.innerHTML=html;
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>