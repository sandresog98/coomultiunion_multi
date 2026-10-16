<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Fondo de Bienestar';
$activeNav = 'fondo';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-gift fa-lg"></i><div><div class="fw-semibold">Fondo de Bienestar</div><div class="crumb">Explora los beneficios disponibles para ti</div></div></div>
      <div id="box" class="text-center text-muted py-4">Cargando beneficios…</div>
      <div class="row g-3" id="lista"></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="portal-card">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-circle-info fa-lg"></i><div class="fw-semibold">¿Cómo funciona?</div></div>
      <ul class="small mb-0" style="line-height:1.8">
        <li>Cada beneficio indica los <b>meses de aporte requeridos</b>.</li>
        <li>Debes estar <b>al día</b> en tus aportes.</li>
        <li>Al aprobarse, la redención genera un <b>bloqueo</b> (meses) y la solicitud se registra.</li>
      </ul>
    </div>
  </div>
</div>
<script>
(function init(){
  var box=document.getElementById('box'), lista=document.getElementById('lista');
  fetch(CX_API+'/fondo/convenios.php').then(function(r){return r.json();}).then(function(j){
    box.textContent='';
    if(!j.ok){ box.textContent=j.error||'Error'; return; }
    var items=j.data||[];
    if(!items.length){ box.textContent='No hay beneficios configurados.'; return; }
    lista.innerHTML=items.map(function(it){
      var c=it.convenio;
      return '<div class="col-md-6"><div class="portal-card h-100">'+
        '<div class="card-header-brand mb-2"><i class="fa-solid fa-tags fa-lg"></i><div class="fw-semibold">'+esc(c.nombre)+'</div></div>'+
        '<div class="small text-muted mb-2">'+esc(c.descripcion||'')+'</div>'+
        '<div class="small mb-2">Requisitos: <span class="badge text-bg-primary">'+c.meses_requeridos+' meses</span>'+
        (c.requiere_cumpleanos_30_dias?' <span class="badge text-bg-info">Cumpleaños +30 días</span>':'')+
        (c.requiere_pdf_obligatorio?' <span class="badge text-bg-warning">Requiere '+esc(c.nombre_pdf_obligatorio||'PDF')+'</span>':'')+'</div>'+
        '<div class="mb-2">'+(it.apto
            ? (it.convenio.no_redime==1 ? '<span class="badge text-bg-light">Informativo</span>' : '<span class="text-success small"><i class="fa-solid fa-circle-check"></i> Apto</span>')
            : '<span class="small text-danger"><i class="fa-solid fa-circle-xmark"></i> '+esc(it.mensaje)+'</span>')+'</div>'+
        (it.apto && it.convenio.no_redime!=1
          ? (it.tiene_pendiente
              ? '<a class="btn btn-sm btn-portal-outline" href="'+CX_BASE+'/modules/fondo_bienestar/pages/mis_solicitudes">Solicitud en curso</a>'
              : '<a class="btn btn-sm btn-portal-gradient" href="'+CX_BASE+'/modules/fondo_bienestar/pages/solicitar?convenio_id='+c.id+'"><i class="fa-solid fa-paper-plane me-1"></i>Solicitar</a>')
          : '')+
        '</div></div>';
    }).join('');
  }).catch(function(e){ box.textContent='No se pudo cargar.'; });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>