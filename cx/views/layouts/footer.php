<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/_env.php';
?>
</main>
</div><!-- .portal-shell -->
<footer class="py-3 text-center text-muted small border-top bg-white">
  © <?= date('Y') ?> Coomultiunion · Portal de asociados ·
  <a class="link-secondary" href="<?= $APP ?>/privacidad">Política de tratamiento de datos</a>
</footer>
<script src="<?= $APP ?>/assets/vendor/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="<?= $APP ?>/assets/js/common.js"></script>
<script src="<?= $APP ?>/assets/js/theme.js"></script>
<script>
(function(){
  var ov=document.getElementById('cx-drawer-overlay'),
      tg=document.getElementById('cx-drawer-toggle');
  function setOpen(o){ document.body.classList.toggle('portal-drawer-open',o); }
  if(tg){ tg.addEventListener('click',function(){ setOpen(!document.body.classList.contains('portal-drawer-open')); }); }
  if(ov){ ov.addEventListener('click',function(){ setOpen(false); }); }
  document.querySelectorAll('.portal-dock-item').forEach(function(a){ a.addEventListener('click',function(){ setOpen(false); }); });
})();
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function money(v){ var n=Math.abs(Number(v)||0); return '$'+Number(n).toLocaleString('de-DE'); }
</script>
</body>
</html>