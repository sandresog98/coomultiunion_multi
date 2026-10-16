<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Detalle de solicitud';
$activeNav = 'solicitudes';
$detId = (int) ($_GET['id'] ?? 0);
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="mb-2"><a class="btn btn-sm btn-portal-outline" href="<?= cx_url('solicitudes/mis') ?>"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a></div>
<div class="portal-card">
  <div class="card-header-brand mb-3"><i class="fa-solid fa-file-lines fa-lg"></i><div class="fw-semibold">Detalle de solicitud</div></div>
  <div id="box" class="text-center text-muted py-4">Cargando…</div>
</div>
<script>
const API=<?php echo json_encode($APP . '/api/'); ?>,PAPI=API+'solicitudes',DID=<?php echo $detId; ?>;
const SD=window.SolDetalle;
let JD=null;

async function cargarDet(){
  var box=document.getElementById('box');
  box.innerHTML='<div class="text-center text-muted py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando…</div>';
  try{
    var r=await fetch(PAPI+'/detalle.php?id='+DID),j=await r.json();
    if(!j.ok){
      if(j.error==='No autorizado'){window.location=cxUrl('login');return;}
      box.innerHTML='<div class="alert alert-warning">'+SD.esc(j.error||'Error')+'</div>';return;
    }
    JD=j.data;box.classList.remove('text-center');box.innerHTML=render(JD);
  }catch(e){box.innerHTML='<div class="alert alert-danger">Error de conexión.</div>';}
}

function render(d){
  var s=d.solicitud,det=d.detalle||{},h='';
  var fw=SD.faseBadge(s.fase);
  // Encabezado alineado con la gestión: radicado + tipo + estado·fase + fecha.
  h+='<div class="d-flex flex-wrap align-items-center gap-2 mb-3">'
    +'<h5 class="fw-semibold mb-0">'+SD.esc(s.numero_radicado)+'</h5>'
    +'<span class="badge text-bg-primary">'+SD.esc(SD.tipoLabel(s.tipo_codigo))+'</span>'
    +'<span class="badge text-bg-'+fw[1]+'">'+SD.esc(SD.estadoLabel(s.estado_codigo))+' · '+SD.esc(fw[0])+'</span>'
    +'<span class="text-muted small ms-auto"><i class="fa-regular fa-clock me-1"></i>'+SD.esc(SD.fmtDtHora(s.fecha_creacion))+'</span>'
    +'</div>';
  // Detalle tipado (etiquetas amigables; sin ids internos).
  h+='<h6 class="fw-semibold">Detalle</h6><div>'+SD.renderDetalleTipo(s.tipo_codigo,det)+'</div>';
  if(d.items&&d.items.length){
    h+='<div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Producto</th><th>Convenio</th><th class="text-end">Cantidad</th></tr></thead><tbody>'
      +d.items.map(function(i){return '<tr><td>'+SD.esc(i.producto_nombre)+'</td><td>'+SD.esc(i.convenio_nombre)+'</td><td class="text-end">'+i.cantidad+'</td></tr>';}).join('')
      +'</tbody></table></div>';
  }
  // Adjuntos de la solicitud (los subidos al crearla).
  var adjs=SD.adjuntosSolicitud(d.adjuntos);
  if(adjs.length){
    h+='<div class="mt-2"><div class="text-muted small mb-1"><i class="fa-solid fa-paperclip me-1"></i>Adjuntos de la solicitud</div>'
      +'<div class="d-flex flex-wrap">'+adjs.map(function(a){return SD.adjuntoLink(a,API);}).join('')+'</div></div>';
  }
  // Fondo de Bienestar.
  if(s.tipo_codigo==='fondo'){
    if(d.elegibilidad){var el=d.elegibilidad;
      h+='<div class="mt-3 p-2 rounded '+(el.apto?'alert-success':'alert-danger')+'"><i class="fa-solid '+(el.apto?'fa-circle-check':'fa-circle-xmark')+' me-1"></i><b>Elegibilidad:</b> '+SD.esc(el.mensaje)+'</div>';
    }
    if(det.ruta_pdf_obligatorio){
      h+='<a class="btn btn-sm btn-portal-outline mt-2" target="_blank" href="'+CX_API+'/fondo/archivo.php?id='+DID+'"><i class="fa-solid fa-file-pdf me-1"></i> Ver PDF adjunto</a>';
    }
  }
  // Boletas emitidas.
  if(d.boletas&&d.boletas.length){
    h+='<hr><h6 class="fw-semibold">Boletas emitidas</h6>'
      +d.boletas.map(function(b){return '<div class="border rounded p-2 mb-1"><i class="fa-solid fa-ticket me-1"></i>'+SD.esc(b.producto_nombre||'')+' · serial '+SD.esc(b.serial)+' · <b>'+SD.esc(b.estado)+'</b></div>';}).join('');
  }
  // Actividad: historial de estados + comentarios + respuesta final, con adjuntos por comentario.
  h+='<hr><h6 class="fw-semibold">Actividad</h6>'+SD.feedHtml(SD.buildActividad(d.eventos,d.adjuntos,{mode:'portal',respuestaFallback:{texto:s.comentario_respuesta,fecha:s.fecha_respuesta,actor:'Multi'}}),API);
  // Satisfacción: si la solicitud quedó resuelta, mostrar la calificación o el widget.
  var cerrada=(s.fase==='RESUELTA_POSITIVA'||s.fase==='RESUELTA_NEGATIVA'||s.fase==='ANULADA');
  var calificable=(s.fase==='RESUELTA_POSITIVA'||s.fase==='RESUELTA_NEGATIVA');
  if(calificable){
    var cal=d.calificacion||null;
    if(cal&&cal.puntuacion!=null){
      h+='<div class="mt-3 border rounded p-3"><div class="text-muted small mb-1"><i class="fa-solid fa-face-smile me-1"></i>Tu calificación de satisfacción</div>'+SD.calificacionHtml(cal)+'</div>';
    }else{
      h+='<div class="mt-3 border rounded p-3">'
        +'<div class="fw-semibold mb-2"><i class="fa-solid fa-face-smile me-1"></i>¿Cómo calificas la atención?</div>'
        +'<div id="starPick" class="mb-2" style="font-size:1.7rem;letter-spacing:4px">'
        +[1,2,3,4,5].map(function(n){return '<i class="fa-regular fa-star" style="cursor:pointer;color:#FCD329" data-n="'+n+'" onclick="setStar('+n+')"></i>';}).join('')
        +'</div>'
        +'<textarea id="calCom" rows="2" class="form-control form-control-sm mb-2" placeholder="Comentario (opcional)"></textarea>'
        +'<button class="btn btn-sm btn-portal-gradient" onclick="enviarCalificacion(this)"><i class="fa-solid fa-star me-1"></i>Enviar calificación</button>'
        +'</div>';
    }
  }
  // Comentario del asociado (con adjuntos): cerrado al responder/cancelar la solicitud.
  if(cerrada){
    h+='<div class="mt-3 small text-muted"><i class="fa-solid fa-lock me-1"></i>La solicitud ya fue respondida; no admite más comentarios.</div>';
  }else{
    h+='<div class="mt-3 border rounded p-2">'
      +'<textarea id="cmt" rows="2" class="form-control form-control-sm mb-2" placeholder="Escribir un comentario…"></textarea>'
      +'<div class="d-flex align-items-center gap-2">'
      +'<input id="cmtFiles" type="file" class="form-control form-control-sm" multiple>'
      +'<button class="btn btn-sm btn-portal-gradient text-nowrap ms-auto" onclick="enviarComentario(this)"><i class="fa-solid fa-paper-plane me-1"></i>Enviar</button>'
      +'</div></div>';
  }
  return h;
}

var _star=0;
function setStar(n){
  _star=n;var box=document.getElementById('starPick');if(!box)return;
  var is=box.querySelectorAll('i');
  for(var i=0;i<is.length;i++){is[i].className=(i<n)?'fa-solid fa-star':'fa-regular fa-star';}
}

async function enviarCalificacion(btn){
  if(!_star){alert('Selecciona una puntuación de 1 a 5 estrellas.');return;}
  var com=(document.getElementById('calCom')||{}).value||'';
  try{
    var r=await fetch(CX_API+'/solicitud_calificar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:DID,puntuacion:_star,comentarios:com})});
    var j=await r.json();
    if(!j.ok){alert(j.error||'Error');return;}
    cargarDet();
  }catch(e){alert('Error de conexión.');}
}

async function enviarComentario(btn){
  // Defensa: si la solicitud ya fue respondida, el portal solo admite el comentario de la calificación.
  var f=JD&&JD.solicitud?JD.solicitud.fase:'';
  if(['RESUELTA_POSITIVA','RESUELTA_NEGATIVA','ANULADA'].indexOf(f)!==-1)return;
  var c=document.getElementById('cmt').value.trim();
  var fi=document.getElementById('cmtFiles'),files=fi&&fi.files?fi.files:[];
  if(!c&&!files.length)return;
  var fd=new FormData();
  fd.append('id',DID);fd.append('mensaje',c||'(adjunto)');
  for(var i=0;i<files.length;i++)fd.append('files[]',files[i]);
  try{
    var r=await fetch(PAPI+'/comentar.php',{method:'POST',body:fd}),j=await r.json();
    if(!j.ok){alert(j.error||'Error');return;}
    cargarDet();
  }catch(e){alert('Error de conexión.');}
}

cargarDet();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
