<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
$pageTitle = 'Cambiar contraseña';
$activeNav = 'password';
require __DIR__ . '/views/layouts/header.php';
?>
<div class="row g-3" style="max-width:720px">
  <div class="col-12">
    <div class="portal-card portal-card-glowing-border">
      <div class="card-header-brand mb-3">
        <i class="fa-solid fa-key fa-lg"></i><div>
          <div class="fw-semibold">Cambiar contraseña</div>
          <div class="crumb">Verificamos tu contraseña actual antes de actualizarla</div>
        </div>
      </div>
      <div id="msg" class="d-none alert py-2 small"></div>
      <form id="cpForm" autocomplete="off">
        <div class="mb-3">
          <label class="form-label">Contraseña actual</label>
          <div class="input-group">
            <input class="portal-input" id="actual" type="password" required>
            <button type="button" class="btn-eye" id="toggleActual" tabindex="-1" aria-label="Ver contraseña"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Nueva contraseña</label>
          <div class="input-group">
            <input class="portal-input" id="nueva" type="password" minlength="6" required>
            <button type="button" class="btn-eye" id="toggleNueva" tabindex="-1" aria-label="Ver contraseña"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Confirmar nueva contraseña</label>
          <div class="input-group">
            <input class="portal-input" id="nueva2" type="password" minlength="6" required>
            <button type="button" class="btn-eye" id="toggleNueva2" tabindex="-1" aria-label="Ver contraseña"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-portal-gradient">Actualizar contraseña</button>
      </form>
    </div>
  </div>
</div>
<script>
function bindEye(btnId,inpId){ document.getElementById(btnId).addEventListener('click',function(){ var ic=btn=document.querySelector('#'+btnId+' i'); var p=document.getElementById(inpId); var show=p.type==='password'; p.type=show?'text':'password'; ic.className='fa-solid '+(show?'fa-eye-slash':'fa-eye'); }); }
bindEye('toggleActual','actual');
bindEye('toggleNueva','nueva');
bindEye('toggleNueva2','nueva2');
document.getElementById('cpForm').addEventListener('submit',async function(e){
  e.preventDefault();
  var msg=document.getElementById('msg'); msg.classList.add('d-none');
  var n1=document.getElementById('nueva').value, n2=document.getElementById('nueva2').value;
  if(n1!==n2){ msg.className='alert py-2 small alert-danger'; msg.textContent='Las contraseñas nuevas no coinciden.'; msg.classList.remove('d-none'); return; }
  try{
    var r=await fetch(CX_API+'/auth_cambiar_password.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({password_actual:document.getElementById('actual').value,password_nueva:n1})});
    var j=await r.json();
    if(!j.ok){ msg.className='alert py-2 small alert-danger'; msg.textContent=j.error||'Error'; msg.classList.remove('d-none'); return; }
    msg.className='alert py-2 small alert-success'; msg.textContent='Contraseña actualizada correctamente.';
    msg.classList.remove('d-none'); e.target.reset();
  }catch(err){ msg.className='alert py-2 small alert-danger'; msg.textContent='Error de conexión'; msg.classList.remove('d-none'); }
});
</script>
<?php require __DIR__ . '/views/layouts/footer.php'; ?>