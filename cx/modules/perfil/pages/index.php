<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Mi información';
$activeNav = 'perfil';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-12">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3">
        <i class="fa-solid fa-user fa-lg"></i>
        <div><div class="fw-semibold">Datos personales</div><div class="crumb">Actualización de datos por solicitud (B7) — pendiente</div></div>
      </div>
      <div id="perfilBox" class="text-center text-muted py-4">Cargando…</div>
      <div class="portal-datos-grid d-none" id="datosGrid"></div>
    </div>
  </div>
</div>
<script>
(async function(){
  var box=document.getElementById('perfilBox'), grid=document.getElementById('datosGrid');
  var r=await fetch(CX_API+'/perfiles.php'); var j=await r.json();
  if(!j.ok){ box.textContent=j.error||'Error'; return; }
  var d=j.data;
  var items=[
    ['Nombre', d.nombre], ['Cédula', d.cedula], ['Correo', d.email],
    ['Celular', d.celular], ['Teléfono', d.telefono], ['Ciudad', d.ciudad],
    ['Dirección', d.direccion], ['Barrio', d.barrio], ['Ocupación', d.ocupacion],
    ['Empresa', d.empresa], ['Nacimiento', d.fecha_nacimiento], ['Ingreso', d.fecha_ingreso],
  ];
  grid.innerHTML=items.map(function(i){return '<div class="dato-item"><div class="dato-label">'+esc(i[0])+'</div><div class="dato-val">'+esc(i[1]||'—')+'</div></div>';}).join('');
  box.classList.add('d-none'); grid.classList.remove('d-none');
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>