<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ' . $CX . '/modules/solicitud_credito/pages/mis_solicitudes'); exit; }
$pageTitle = 'Documentos de solicitud';
$activeNav = 'solic';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-12" id="headBox"></div>
  <div class="col-lg-8">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-folder-open fa-lg"></i><div class="fw-semibold">Documentos requeridos</div></div>
      <div id="box" class="text-center text-muted py-4">Cargando…</div>
      <div id="docsBody" class="d-none"></div>
    </div>
  </div>
</div>
<script>
var SOLID=<?= $id ?>;
(async function(){
  var r=await fetch(CX_API+'/documentos.php?id='+SOLID); var j=await r.json();
  var head=document.getElementById('headBox');
  if(!j.ok){ head.innerHTML='<div class="alert alert-danger">'+esc(j.error||'Error')+'</div>'; return; }
  var s=j.data.solicitud, secs=j.data.documentos;
  var ETAPAS={creacion:['Creado','bg-secondary'],revision:['En revisión','bg-info'],estudio:['En estudio','bg-primary'],
    estudio_aprobado:['Estudio aprobado','bg-success'],estudio_rechazado:['Estudio rechazado','bg-danger'],
    radicado_tesoreria:['Radicado a tesorería','bg-warning text-dark'],final:['Desembolsado','bg-dark'],
    consideracion:['Consideración','bg-primary']};
  function item(lab,val){ return '<div class="dato-item"><div class="dato-label">'+esc(lab)+'</div><div class="dato-val">'+(val==null||val===''?'—':val)+'</div></div>'; }
  function par(lab,valSol,valApr,moneyFmt){
    var h='<span>'+(moneyFmt?moneyAbs(valSol):esc(valSol||'—'))+'</span>';
    if(valApr!==null&&valApr!==undefined&&valApr!==''&&valApr!==0){ h+='<div class="small fw-bold" style="color:var(--cx-dark)">Aprobado: '+(moneyFmt?moneyAbs(valApr):esc(valApr))+'</div>'; }
    return item(lab,h);
  }
  var e=ETAPAS[s.etapa_actual]||[s.etapa_actual,'bg-secondary'];
  head.innerHTML='<div class="portal-card portal-card-glowing-border">'
    +'<div class="card-header-brand mb-3"><i class="fa-solid fa-file-lines fa-lg"></i><div class="fw-semibold">Solicitud '+esc(s.numero_solicitud)+'</div></div>'
    +'<div class="portal-datos-grid">'
    +item('Tipo',esc(s.tipo_solicitante))
    +item('Etapa','<span class="badge-etapa '+e[1]+'">'+esc(e[0])+'</span>')
    +par('Monto',s.monto_deseado,s.monto_aprobado,true)
    +par('Cuotas',s.numero_cuotas_deseadas,s.cuotas_aprobadas,false)
    +par('Línea',s.linea_solicitada_nombre,s.linea_aprobada_nombre,false)
    +'</div></div>';
  if(s.etapa_actual==='final' || s.numero_credito_sifone){
    head.innerHTML+='<div class="portal-card portal-card-glowing-border mt-2">'
      +'<div class="card-header-brand mb-3"><i class="fa-solid fa-money-bill-wave fa-lg"></i><div class="fw-semibold">Desembolso</div></div>'
      +'<div class="portal-datos-grid">'
      +item('N° Crédito Sifone',esc(s.numero_credito_sifone))
      +item('Valor desembolsado',moneyAbs(s.valor_real_desembolso))
      +item('Fecha',cxFecha(s.fecha_desembolso))
      +item('Base de aportes',moneyAbs(s.base_aportes))
      +item('Fianza',moneyAbs(s.fianza))
      +item('Préstamos a recoger',moneyAbs(s.prestamos_recoger))
      +item('Dto. papelería',moneyAbs(s.dto_papeleria))
      +'</div></div>';
  }
  // Recopilar documentos configurados (TODAS las secciones visibles en cx, sin importar la etapa)
  var req=[];
  (secs||[]).forEach(function(sec){
    var oc=(sec.seccion&&sec.seccion.ocasion)||'creacion';
    (sec.documentos||[]).forEach(function(doc){ var o={}; for(var k in doc)o[k]=doc[k]; o._etapa=oc; req.push(o); });
  });
  var editable=(s.etapa_actual==='creacion');
  var box=document.getElementById('box'), body=document.getElementById('docsBody');
  box.classList.add('d-none');
  if(!req.length){ body.innerHTML='<p class="text-muted">No hay documentos configurados para solicitar.</p>'; body.classList.remove('d-none'); return; }
  body.innerHTML='<table class="portal-table"><tbody>'+req.map(function(doc){
    var st=s.documentos? s.documentos.find(function(x){return x.tipo_documento===doc.tipo_documento;}) : null;
    var estadoCell = st
      ? (st.ruta_archivo?'<span class="badge bg-success">Subido</span> <a class="btn btn-sm btn-portal-outline" href="'+CX_API+'/serve_documento.php?id='+st.id+'" target="_blank">Ver</a>':'<span class="badge bg-secondary">Pendiente</span>')
      : '<span class="badge bg-secondary">Pendiente</span>';
    var upCell = editable
      ? '<input type="file" class="form-control form-control-sm" data-tipo="'+esc(doc.tipo_documento)+'" data-etapa="'+esc(doc._etapa||'creacion')+'">'
      : '<span class="text-muted small">Solo editable en etapa creación</span>';
    return '<tr><td data-label="Documento"><span class="cell-val">'+esc(doc.nombre_mostrar)+'<div class="small text-muted">'+esc(doc.descripcion||'')+'</div></span></td>'
      +'<td data-label="Estado"><span class="cell-val">'+estadoCell+'</span></td>'
      +'<td data-label="Acción"><span class="cell-val">'+upCell+'</span></td></tr>';
  }).join('')+'</tbody></table>';
  body.classList.remove('d-none');

  document.querySelectorAll('#docsBody input[type=file]').forEach(function(inp){
    inp.addEventListener('change',async function(){
      if(!inp.files.length)return;
      var fd=new FormData();
      fd.append('solicitud_id',SOLID); fd.append('etapa',inp.dataset.etapa);
      fd.append('tipo_documento',inp.dataset.tipo); fd.append('archivo',inp.files[0]);
      try{
        var r=await fetch(CX_API+'/documentos.php',{method:'POST',body:fd}); var j=await r.json();
        if(j.ok){ window.location.reload(); } else { alert(j.error||'Error'); }
      }catch(err){ alert('Error de conexión'); }
    });
  });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>