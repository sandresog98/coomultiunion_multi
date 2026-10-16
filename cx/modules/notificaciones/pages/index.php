<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Notificaciones';
$activeNav = 'notif';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card portal-card-glowing-border">
  <div class="card-header-brand mb-3"><i class="fa-solid fa-bell fa-lg"></i><div class="fw-semibold">Notificaciones</div></div>
  <div id="box" class="text-center text-muted py-4">Cargando…</div>
  <div id="body"></div>
  <div id="empty" class="d-none text-center text-muted py-4">Aún no tienes avisos.</div>
</div>
<script>
var LAB={
  evento_creacion:'Solicitud creada',evento_cambio_estado:'Cambio de estado',evento_respuesta:'Respuesta',
  evento_asignacion:'Asignación',evento_reasignacion:'Reasignación',evento_comentario:'Comentario',evento_adjunto:'Adjunto',
  evento_crear:'Correo enviado',evento_desembolso:'Correo enviado',correo:'Correo'
};
function tipoTxt(r){
  if(r.tipo==='correo') return '✉️ '+esc(r.tipo_evento==='desembolso'?'Desembolso':r.tipo_evento||'Correo');
  var t=LAB['evento_'+r.tipo_evento]||r.tipo_evento;
  return esc(t);
}
function detalleTxt(r){
  if(r.tipo==='correo') return esc(r.mensaje||'');
  var out='';
  if(r.estado_anterior&&r.estado_nuevo) out+=esc(r.estado_anterior)+' → '+esc(r.estado_nuevo);
  else if(r.estado_nuevo) out+='Estado: '+esc(r.estado_nuevo);
  if(r.mensaje && r.tipo_evento!=='creacion') out+=(out?' — ':'')+esc(r.mensaje);
  return out;
}
(function init(){
  var box=document.getElementById('box'),body=document.getElementById('body'),empty=document.getElementById('empty');
  fetch(CX_API+'/notificaciones.php?limit=80').then(function(r){return r.json();}).then(function(j){
    if(!j.ok){box.textContent=j.error||'Error';return;}
    var rows=j.data||[];
    box.classList.add('d-none');
    if(!rows.length){empty.classList.remove('d-none');return;}
    window.cxTabla({el:'#body',porPagina:function(){return window.innerWidth<992?5:8;},cols:[
      {label:'Fecha',val:function(s){return cxFecha(s.fecha);},class:'text-center'},
      {label:'Radicado',val:function(s){return s.numero_radicado;}},
      {label:'Aviso',val:function(s){return tipoTxt(s);}},
      {label:'Detalle',val:function(s){return detalleTxt(s);}},
    ],filas:rows});
  });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>