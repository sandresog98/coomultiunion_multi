<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Mis solicitudes';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="mb-2"><a class="btn btn-sm btn-portal-outline" href="<?= $CX ?>/modules/solicitudes/pages/index"><i class="fa-solid fa-arrow-left me-1"></i>Volver a solicitudes</a></div>
<div class="portal-card portal-card-glowing-border">
  <div class="card-header-brand mb-3"><i class="fa-solid fa-list-check fa-lg"></i><div class="fw-semibold">Mis solicitudes</div></div>
  <div id="box" class="text-center text-muted py-4">Cargando…</div>
  <div id="body"></div>
  <div id="empty" class="d-none text-center text-muted py-4">Aún no tienes solicitudes.</div>
</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>;
const TIPOS={'pqrs':'PQRS','actualizar_info':'Actualización','redencion':'Redención','nuevo_producto':'Nuevo producto','boleta':'Boletas','retiro_incentivos':'Retiro incentivos','certificado':'Certificado','fondo':'Fondo de bienestar','ticket':'Ticket','retiro_aporte':'Retiro de aportes'};
const ESTBADGE={pendiente:['Pendiente','bg-secondary'],recibido:['Recibido','bg-info'],asignado:['Asignado','bg-info'],backlog:['Backlog','bg-secondary'],en_curso:['En curso','bg-warning text-dark'],en_espera:['En espera','bg-warning text-dark'],resuelto:['Resuelto','bg-primary'],aceptado:['Aceptado','bg-success'],aprobada:['Aprobada','bg-success'],rechazada:['Rechazada','bg-danger'],rechazado:['Rechazado','bg-danger'],cancelado:['Cancelado','bg-secondary']};
(function init(){
  var box=document.getElementById('box'),body=document.getElementById('body'),empty=document.getElementById('empty');
  fetch(PAPI+'/listar.php?limit=200').then(r=>r.json()).then(j=>{
    if(!j.ok){box.textContent=j.error||'Error';return;}
    box.classList.add('d-none');
    var rows=j.data.rows||[];
    if(!rows.length){empty.classList.remove('d-none');return;}
    window.cxTabla({
      el:'#body',porPagina:function(){return window.innerWidth<992?3:6;},
      cols:[
        {label:'Radicado',val:function(s){return s.numero_radicado;}},
        {label:'Tipo',val:function(s){return s.tipo_codigo;},html:function(s){return '<span class="badge-etapa bg-primary">'+(TIPOS[s.tipo_codigo]||s.tipo_codigo)+'</span>';}},
        {label:'Estado',val:function(s){return s.estado_codigo;},html:function(s){var e=ESTBADGE[s.estado_codigo]||[s.estado_codigo,'bg-secondary'];return '<span class="badge-etapa '+e[1]+'">'+e[0]+'</span>';}},
        {label:'Fecha',val:function(s){return cxFecha(s.fecha_creacion);}},
        {label:'Acciones',sort:false,val:function(){return'';},html:function(s){return '<a class="btn btn-sm btn-portal-outline" href="'+CX_BASE+'/modules/solicitudes/pages/detalle?id='+s.id+'">Ver</a>';}},
      ],filas:rows
    });
  }).catch(e=>{box.textContent='Error de conexión.';});
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>