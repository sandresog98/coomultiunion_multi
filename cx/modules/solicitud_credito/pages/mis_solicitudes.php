<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Mis solicitudes';
$activeNav = 'solic';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card portal-card-glowing-border">
  <div class="card-header-brand mb-3"><i class="fa-solid fa-list-check fa-lg"></i><div class="fw-semibold">Mis solicitudes de crédito</div></div>
  <div id="box" class="text-center text-muted py-4">Cargando…</div>
  <div id="body"></div>
  <div id="empty" class="d-none text-center text-muted py-4">No tienes solicitudes.</div>
</div>
<script>
var ETAPAS={creacion:['Creado','bg-secondary'],revision:['En revisión','bg-info'],estudio:['En estudio','bg-primary'],
  estudio_aprobado:['Estudio aprobado','bg-success'],estudio_rechazado:['Estudio rechazado','bg-danger'],
  radicado_tesoreria:['Radicado a tesorería','bg-warning text-dark'],final:['Desembolsado','bg-dark'],
  consideracion:['Consideración','bg-primary']};
function etapaHtml(s){
  var e=ETAPAS[s.etapa_actual]||[s.etapa_actual,'bg-secondary'];
  return '<span class="badge-etapa '+e[1]+'">'+esc(e[0])+'</span>';
}
function parHtml(sol, apr, moneyFmt){
  var h='<div><span class="text-muted small">Solicitado:</span> '+(moneyFmt?moneyAbs(sol):esc(sol||'—'));
  if(apr!==null && apr!==undefined && apr!=='' && apr!==0){ h+='<div><span class="text-muted small">Aprobado:</span> <b>'+(moneyFmt?moneyAbs(apr):esc(apr))+'</b></div>'; }
  return h+'</div>';
}
(function init(){
  var box=document.getElementById('box'), body=document.getElementById('body'), empty=document.getElementById('empty');
  fetch(CX_API+'/solicitudes_listar.php?limit=200').then(function(r){return r.json();}).then(function(j){
    if(!j.ok){ box.textContent=j.error||'Error'; return; }
    box.classList.add('d-none');
    var rows=j.data.rows||[];
    if(!rows.length){ empty.classList.remove('d-none'); return; }
    window.cxTabla({
      el:'#body',
      porPagina:function(){ return window.innerWidth < 992 ? 3 : 5; },
      cols:[
        {label:'N°', val:function(s){return s.numero_solicitud;}},
        {label:'Línea', val:function(s){return s.linea_solicitada||'';}, html:function(s){return parHtml(s.linea_solicitada, s.linea_aprobada, false);}},
        {label:'Monto', val:function(s){return s.monto_deseado;}, html:function(s){return parHtml(s.monto_deseado, s.monto_aprobado, true);}, class:'money'},
        {label:'Cuotas', val:function(s){return s.numero_cuotas_deseadas;}, html:function(s){return parHtml(s.numero_cuotas_deseadas, s.cuotas_aprobadas, false);}},
        {label:'Etapa', val:function(s){return s.etapa_actual;}, html:etapaHtml},
        {label:'Fecha', val:function(s){return cxFecha(s.fecha_creacion);}},
        {label:'Acciones', sort:false, val:function(){return '';}, html:function(s){
          var pendAceptar=((s.aceptado_por_asociado===null) && (s.etapa_actual==='creacion'||s.etapa_actual==='revision'));
          return '<a class="btn btn-sm btn-portal-outline" href="'+cxUrl('solicitar-credito/{id}',{id:s.id})+'">Documentos</a>'
            +(pendAceptar?' <a class="btn btn-sm btn-portal-accent" href="'+cxUrl('solicitar-credito/aceptar/{id}',{id:s.id})+'">Autorizar</a>':'');
        }},
      ],
      filas:rows
    });
  });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>