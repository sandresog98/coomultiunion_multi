<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Retiro de aportes';
$activeNav = 'aportes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="portal-card">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-hand-holding-dollar fa-lg"></i><div><div class="fw-semibold">Solicitar retiro de aportes</div><div class="crumb">Adjunta los dos PDF obligatorios</div></div></div>
      <div id="msg" class="d-none alert py-2 small"></div>
      <form id="frm">
        <div class="mb-3">
          <label class="form-label">Tipo de retiro <span class="text-danger">*</span></label>
          <select class="portal-select" id="tipo">
            <option value="">Seleccione…</option>
            <option value="total">Total (+90 días)</option>
            <option value="parcial">Parcial (+30 días)</option>
          </select>
        </div>
        <div class="mb-3 d-none" id="montoWrap">
          <label class="form-label">Monto a retirar <span class="text-danger">*</span></label>
          <input class="portal-input" type="number" step="0.01" min="0" id="monto" placeholder="0.00">
        </div>
        <div class="mb-3">
          <label class="form-label">PDF firma de retiro <span class="text-danger">*</span></label>
          <input class="form-control" type="file" id="pdf_retiro" accept="application/pdf,.pdf">
          <div class="form-text small">Solo PDF, máximo 10 MB.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">PDF certificado de aportes <span class="text-danger">*</span></label>
          <input class="form-control" type="file" id="pdf_certificado" accept="application/pdf,.pdf">
          <div class="form-text small">Solo PDF, máximo 10 MB.</div>
        </div>
        <button type="submit" class="btn btn-portal-gradient" id="btn"><i class="fa-solid fa-paper-plane me-1"></i>Enviar solicitud</button>
        <div class="form-text small text-muted mt-2">Al enviar aceptas nuestra <a href="<?= $APP ?>/privacidad" target="_blank" rel="noopener">Política de Tratamiento de Datos Personales</a>.</div>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="portal-card">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-list-check fa-lg"></i><div class="fw-semibold">Mis solicitudes de retiro</div></div>
      <div id="box" class="text-center text-muted py-4">Cargando…</div>
      <div id="body"></div>
      <div id="empty" class="d-none text-center text-muted py-4">No tienes solicitudes de retiro.</div>
    </div>
  </div>
</div>

<div id="detWrap" class="mt-3 d-none">
  <div class="portal-card">
    <div class="card-header-brand mb-2"><i class="fa-solid fa-file-circle-check fa-lg"></i><div class="fw-semibold">Detalle de la solicitud</div></div>
    <div id="det"></div>
  </div>
</div>
<script>
const EST={pendiente:['Pendiente','bg-secondary'],desembolsado:['Desembolsado','bg-success'],rechazada:['Rechazada','bg-danger']};
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function money(v){return v==null?'':('$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0}));}
function msg(t,c){var m=document.getElementById('msg');m.className='d-none alert alert-'+c+' py-2 small';m.classList.remove('d-none');m.classList.add('alert-'+c);m.textContent=t;}

document.getElementById('tipo').addEventListener('change',function(){
  document.getElementById('montoWrap').classList.toggle('d-none',this.value!=='parcial');
});

document.getElementById('frm').addEventListener('submit',function(e){
  e.preventDefault();
  var tipo=document.getElementById('tipo').value;
  if(!tipo){msg('Seleccione el tipo de retiro.','danger');return;}
  var fd=new FormData();
  fd.append('tipo',tipo);
  if(tipo==='parcial')fd.append('monto_solicitado',document.getElementById('monto').value);
  var pr=document.getElementById('pdf_retiro').files[0],pc=document.getElementById('pdf_certificado').files[0];
  if(!pr||!pc){msg('Adjunte los dos PDF obligatorios.','danger');return;}
  fd.append('pdf_retiro',pr);fd.append('pdf_certificado',pc);
  var b=document.getElementById('btn');b.disabled=true;
  fetch(CX_API+'/aportes/retiro_solicitar.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    b.disabled=false;
    if(j.ok){msg('Solicitud '+esc(j.data.radicado)+' creada.','success');cargar();}
    else msg(j.error||'Error','danger');
  }).catch(function(){b.disabled=false;msg('Error de conexión','danger');});
});

function stHtml(s){var e=EST[s.estado_codigo]||[s.estado_codigo,'bg-secondary'];return '<span class="badge-etapa '+e[1]+'">'+esc(e[0])+'</span>';}

function cargar(){
  var box=document.getElementById('box'),body=document.getElementById('body'),empty=document.getElementById('empty');
  box.classList.remove('d-none');box.textContent='Cargando…';body.innerHTML='';empty.classList.add('d-none');
  fetch(CX_API+'/aportes/retiro_mis_solicitudes.php?limit=200').then(function(r){return r.json();}).then(function(j){
    box.classList.add('d-none');
    if(!j.ok){box.textContent=j.error||'Error';box.classList.remove('d-none');return;}
    var rows=j.data.rows||j.data||[];
    if(!rows.length){empty.classList.remove('d-none');return;}
    window.cxTabla({el:'#body',porPagina:function(){return window.innerWidth<992?4:6;},cols:[
      {label:'Radicado',val:function(s){return s.numero_radicado;}},
      {label:'Estado',val:function(s){return s.estado_codigo;},html:stHtml,class:'text-center'},
      {label:'Fecha',val:function(s){return cxFecha(s.fecha_creacion);},class:'text-center'},
      {label:'Acciones',sort:false,val:function(){return '';},html:function(s){
        return '<button class="btn btn-sm btn-portal-outline" onclick="verDetalle('+s.id+')">Ver detalle</button>';
      }},
    ],filas:rows});
  });
}

function verDetalle(id){
  var w=document.getElementById('detWrap');
  document.getElementById('det').innerHTML='<div class="text-center text-muted py-3">Cargando…</div>';
  w.classList.remove('d-none');
  fetch(CX_API+'/aportes/retiro_detalle.php?id='+id).then(function(r){return r.json();}).then(function(j){
    if(!j.ok){document.getElementById('det').innerHTML='<span class="text-danger">'+esc(j.error||'Error')+'</span>';return;}
    var d=j.data,s=d.solicitud,dd=d.detalle||{};
    document.getElementById('det').innerHTML=
      '<div class="portal-datos-grid">'+
      '<div class="dato-item"><div class="dato-label">Radicado</div><div class="dato-val">'+esc(s.numero_radicado)+'</div></div>'+
      '<div class="dato-item"><div class="dato-label">Estado</div><div class="dato-val">'+stHtml(s)+'</div></div>'+
      '<div class="dato-item"><div class="dato-label">Tipo</div><div class="dato-val">'+esc(dd.tipo||'')+'</div></div>'+
      '<div class="dato-item"><div class="dato-label">Monto</div><div class="dato-val">'+money(dd.monto_solicitado)+'</div></div>'+
      '<div class="dato-item"><div class="dato-label">Fecha estimada desembolso</div><div class="dato-val">'+cxFecha(dd.fecha_estimada_desembolso)+'</div></div>'+
      '<div class="dato-item"><div class="dato-label">Fecha desembolso</div><div class="dato-val">'+cxFecha(s.fecha_respuesta)+'</div></div>'+
      (s.comentario_respuesta?'<div class="dato-item"><div class="dato-label">Comentario</div><div class="dato-val">'+esc(s.comentario_respuesta)+'</div></div>':'')+
      '</div>'+
      '<div class="mt-3">'+
      '<a class="btn btn-sm btn-portal-outline" target="_blank" href="'+(dd.ruta_pdf_retiro?CX_API+'/aportes/retiro_archivo.php?id='+s.id+'&tipo=retiro':'#')+'">'+(dd.ruta_pdf_retiro?'<i class="fa-solid fa-file-pdf me-1"></i>PDF retiro':'Sin PDF retiro')+'</a> '+
      '<a class="btn btn-sm btn-portal-outline" target="_blank" href="'+(dd.ruta_pdf_certificado?CX_API+'/aportes/retiro_archivo.php?id='+s.id+'&tipo=cert':'#')+'">'+(dd.ruta_pdf_certificado?'<i class="fa-solid fa-file-pdf me-1"></i>PDF certificado':'Sin PDF certificado')+'</a>'+
      '</div>'+
      '<div class="mt-3 small text-muted">Eventos:</div><div class="mt-1" id="evts"></div>';
    var evh=(d.eventos||[]).map(function(ev){
      return '<div class="d-flex justify-content-between border-bottom py-1 small"><span>'+esc(ev.mensaje||ev.tipo_evento)+'</span><span class="text-muted">'+cxFecha(ev.fecha)+'</span></div>';
    }).join('')||'<span class="text-muted small">Sin eventos.</span>';
    document.getElementById('evts').innerHTML=evh;
  });
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>