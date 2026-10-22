<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Nueva solicitud de crédito';
$activeNav = 'solic';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
$TIPOS = ['estudiante','empleado_descuento_nomina','empleado_sin_descuento','independiente','pensionado_descuento_libranza','pensionado_sin_descuento_libranza'];
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-hand-holding-dollar fa-lg"></i><div><div class="fw-semibold">Solicitud de crédito</div><div class="crumb">Diligencias y envía tu solicitud</div></div></div>
      <div id="msg" class="d-none alert py-2 small"></div>
      <form id="solForm">
        <div class="mb-3">
          <label class="form-label">Tipo de solicitante <span class="text-danger">*</span></label>
          <select class="portal-select" id="tipo_solicitante" required>
            <option value="">Seleccione…</option>
            <?php foreach ($TIPOS as $t): ?><option value="<?= $t ?>"><?= str_replace('_',' ', ucwords($t,'_')) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6"><label class="form-label">Nombres y apellidos <span class="text-danger">*</span></label><input class="portal-input" id="nombre" required></div>
          <div class="col-md-6"><label class="form-label">Cédula</label><input class="portal-input" id="cedula" readonly></div>
          <div class="col-md-6"><label class="form-label">Celular</label><input class="portal-input" id="celular"></div>
          <div class="col-md-6"><label class="form-label">Correo</label><input class="portal-input" id="email" type="email"></div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Línea de crédito <span class="text-danger">*</span></label>
            <select class="portal-select" id="tasa_interes_id" required><option value="">Cargando líneas…</option></select>
          </div>
          <div class="col-md-4"><label class="form-label">Monto deseado (mín $100.000) <span class="text-danger">*</span></label><input class="portal-input text-end" id="monto_deseado" type="text" inputmode="numeric" autocomplete="off" placeholder="0" required></div>
          <div class="col-md-4"><label class="form-label">Número de cuotas <span class="text-danger">*</span></label><input class="portal-input" id="numero_cuotas_deseadas" type="number" min="1" max="72" required></div>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="acepta_tratamiento_datos" required>
          <label class="form-check-label small" for="acepta_tratamiento_datos">Acepto el <b>tratamiento de datos personales</b> y consulta en centrales de riesgo <span class="text-danger">*</span> · <a href="<?= $APP ?>/privacidad" target="_blank" rel="noopener">Política de datos</a></label>
        </div>
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="acepta_ofertas_comerciales">
          <label class="form-check-label small" for="acepta_ofertas_comerciales">Acepto recibir ofertas comerciales</label>
        </div>
        <button type="submit" class="btn btn-portal-gradient" id="btnSol"><i class="fa-solid fa-paper-plane me-1"></i> Enviar solicitud</button>
      </form>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="portal-card">
      <div class="card-header-brand mb-3"><i class="fa-solid fa-circle-info fa-lg"></i><div class="fw-semibold">¿Qué sigue?</div></div>
      <ul class="small mb-0" style="line-height:1.8">
        <li>Tu solicitud queda en etapa <b>Creado</b> y la revisa el equipo de Créditos.</li>
        <li>Podrás subir documentos requeridos (cédula, soportes) desde <b>Mis solicitudes</b>.</li>
        <li>Una vez estudiada, la aceptas o la encuentras en tu bandeja.</li>
      </ul>
    </div>
  </div>
</div>
<script>
var API=CX_API, CX=CX_BASE, maxCuotas=72;
/* Máscara visual de separadores de miles (no afecta el valor que se inserta). */
var monEl=document.getElementById('monto_deseado');
function fmtMont(v){ var s=v.replace(/\D/g,'').replace(/^0+(?=\d)/,''); return s?s.replace(/\B(?=(\d{3})+(?!\d))/g,'.'):''; }
function montoVal(){ return parseInt((monEl.value||'0').replace(/\./g,''),10)||0; }
monEl.addEventListener('input',function(){ var c=this.value.length; this.value=fmtMont(this.value); });
(async function(){
  var r=await fetch(API+'/perfiles.php'); var p=await r.json();
  if(p.ok){ document.getElementById('nombre').value=p.data.nombre||''; document.getElementById('cedula').value=p.data.cedula||'';
    document.getElementById('celular').value=p.data.celular||''; document.getElementById('email').value=p.data.email||''; }
  var r2=await fetch(API+'/lineas.php'); var j=await r2.json();
  if(j.ok){
    document.getElementById('tasa_interes_id').innerHTML='<option value="">Seleccione…</option>'+j.data.map(function(l){
      return '<option value="'+l.id+'" data-max="'+(l.limite_meses||72)+'">'+esc(l.nombre_credito)+' (hasta '+(l.limite_meses||72)+' cuotas)</option>';
    }).join('');
  }
  document.getElementById('tasa_interes_id').addEventListener('change',function(){
    var o=this.selectedOptions[0]; maxCuotas=Math.min(o&&o.dataset.max?parseInt(o.dataset.max):72,72);
    var c=document.getElementById('numero_cuotas_deseadas'); c.max=maxCuotas; if(c.value>maxCuotas)c.value=maxCuotas;
  });
})();
document.getElementById('solForm').addEventListener('submit',async function(e){
  e.preventDefault();
  var msg=document.getElementById('msg'); msg.classList.add('d-none');
  var monto=montoVal();
  if(monto<100000){ msg.className='alert py-2 small alert-danger'; msg.textContent='El monto deseado debe ser al menos $100.000.'; msg.classList.remove('d-none'); return; }
  var cuotas=parseInt(document.getElementById('numero_cuotas_deseadas').value);
  if(cuotas<1||cuotas>maxCuotas){ msg.className='alert py-2 small alert-danger'; msg.textContent='Las cuotas deben estar entre 1 y '+maxCuotas+'.'; msg.classList.remove('d-none'); return; }
  var btn=document.getElementById('btnSol'); btn.disabled=true;
  var body={
    tipo_solicitante:document.getElementById('tipo_solicitante').value,
    nombre_solicitante:document.getElementById('nombre').value.trim(),
    numero_telefono:document.getElementById('celular').value.trim(),
    correo_electronico:document.getElementById('email').value.trim(),
    monto_deseado:monto,
    numero_cuotas_deseadas:cuotas,
    tasa_interes_id:parseInt(document.getElementById('tasa_interes_id').value)||0,
    acepta_tratamiento_datos:document.getElementById('acepta_tratamiento_datos').checked,
    acepta_ofertas_comerciales:document.getElementById('acepta_ofertas_comerciales').checked,
  };
  try{
    var r=await fetch(API+'/solicitudes_crear.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    var j=await r.json();
    if(!j.ok){ msg.className='alert py-2 small alert-danger'; msg.textContent=j.error||'Error'; msg.classList.remove('d-none'); btn.disabled=false; return; }
    window.location=cxUrl('solicitar-credito/{id}',{id:j.data.id});
  }catch(err){ msg.className='alert py-2 small alert-danger'; msg.textContent='Error de conexión'; msg.classList.remove('d-none'); btn.disabled=false; }
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>