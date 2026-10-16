<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Solicitar beneficio';
$activeNav = 'fondo';
$conId = (int) ($_GET['convenio_id'] ?? 0);
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-gift fa-lg"></i><div><div class="fw-semibold">Solicitar beneficio</div><div class="crumb">Completa los datos para enviar tu solicitud</div></div></div>
      <div id="msg" class="d-none alert py-2 small"></div>
      <div id="loading" class="text-center text-muted py-4">Cargando convenio…</div>
      <div id="noApto" class="d-none"><div class="alert alert-danger small">No puedes solicitar este beneficio.</div><a class="btn btn-sm btn-portal-outline" href="<?= cx_url('fondo-bienestar') ?>">Volver a beneficios</a></div>
      <form id="solForm" class="d-none" enctype="multipart/form-data">
        <input type="hidden" id="convenio_id">
        <div class="mb-3">
          <label class="form-label">Beneficio</label>
          <select class="portal-select" id="selConvenio"></select>
        </div>
        <div id="opcionesWrap" class="mb-3 d-none">
          <label class="form-label">Opción del beneficio <span class="text-danger">*</span></label>
          <select class="portal-select" id="opcion_elegida"><option value="">Seleccione…</option></select>
        </div>
        <div id="pdfWrap" class="mb-3 d-none">
          <label class="form-label" id="pdfLabel">Adjuntar PDF</label>
          <input class="form-control" type="file" id="archivo" accept="application/pdf,.pdf">
          <div class="form-text small">Solo PDF, máximo 10 MB.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Observaciones <span class="text-danger">*</span></label>
          <textarea class="portal-input" id="observaciones" rows="3" placeholder="Describe tu solicitud…"></textarea>
        </div>
        <button type="submit" class="btn btn-portal-gradient" id="btnSol"><i class="fa-solid fa-paper-plane me-1"></i> Enviar solicitud</button>
      </form>
    </div>
  </div>
</div>
<script>
var CON_ID=<?= $conId ?>, data=null;
function msg(t,cls){var m=document.getElementById('msg');m.className='d-none alert alert-'+cls+' py-2 small '.replace('  ',' ')+' alert-'+(cls);m.classList.remove('d-none');m.classList.add('alert-'+cls);m.textContent=t;}
(function init(){
  fetch(CX_API+'/fondo/convenios.php').then(function(r){return r.json();}).then(function(j){
    if(!j.ok){msg(j.error||'Error','danger');document.getElementById('loading').textContent='';return;}
    var items=(j.data||[]).filter(function(it){return it.apto && it.convenio.no_redime!=1 && !it.tiene_pendiente;});
    document.getElementById('loading').classList.add('d-none');
    if(!items.length){document.getElementById('noApto').classList.remove('d-none');return;}
    var sel=document.getElementById('selConvenio');
    sel.innerHTML=items.map(function(it){return '<option value="'+it.convenio.id+'">'+esc(it.convenio.nombre)+'</option>';}).join('');
    if(CON_ID && items.some(function(it){return it.convenio.id==CON_ID;})) sel.value=CON_ID.toString();
    var cur=items.find(function(it){return it.convenio.id==parseInt(sel.value,10);});
    poblar(cur);cargarOpciones(cur);
    sel.addEventListener('change',function(){var c=items.find(function(it){return it.convenio.id==parseInt(sel.value,10);});poblar(c);cargarOpciones(c);});
    document.getElementById('solForm').classList.remove('d-none');
  }).catch(function(e){document.getElementById('loading').textContent='Error cargando.';});
})();
function poblar(c){
  document.getElementById('convenio_id').value=c.convenio.id;
  var pdfW=document.getElementById('pdfWrap'),lab=document.getElementById('pdfLabel');
  if(c.convenio.requiere_pdf_obligatorio==1){pdfW.classList.remove('d-none');lab.textContent='Adjuntar '+esc(c.convenio.nombre_pdf_obligatorio||'PDF obligatorio')+' <span class="text-danger">*</span>';}
  else pdfW.classList.add('d-none');
}
function cargarOpciones(c){
  var w=document.getElementById('opcionesWrap'),sel=document.getElementById('opcion_elegida');
  var opts=[]; if(c.convenio.opciones_json){try{opts=JSON.parse(c.convenio.opciones_json)||[];}catch(e){opts=[];}}
  if(opts.length){w.classList.remove('d-none');sel.innerHTML='<option value="">Seleccione…</option>'+opts.map(function(o){return '<option value="'+esc(o)+'">'+esc(o)+'</option>';}).join('');}
  else{w.classList.add('d-none');}
}
document.getElementById('solForm').addEventListener('submit',function(e){
  e.preventDefault();
  var fd=new FormData();
  fd.append('convenio_id',document.getElementById('convenio_id').value);
  fd.append('opcion_elegida',document.getElementById('opcion_elegida').value);
  fd.append('observaciones',document.getElementById('observaciones').value);
  var ar=document.getElementById('archivo').files[0]; if(ar) fd.append('archivo',ar);
  var b=document.getElementById('btnSol'); b.disabled=true;
  fetch(CX_API+'/fondo/solicitar.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    b.disabled=false;
    if(j.ok){window.location.href=cxUrl('solicitudes/mis');}
    else msg(j.error||'Error','danger');
  }).catch(function(e){b.disabled=false;msg('Error de conexión','danger');});
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>