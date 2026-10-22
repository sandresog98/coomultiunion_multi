<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
require_once dirname(__DIR__, 4) . '/core/config/ConfigService.php';

$pageTitle = 'Soli IA';
$activeNav = 'asistente';

$chatNombre = 'Soli';
try {
    $v = trim((string) (new ConfigService())->get('chat_ia_nombre', (string) env('AI_CHAT_NOMBRE', 'Soli')));
    if ($v !== '') {
        $chatNombre = $v;
    }
} catch (Throwable $e) {
    // Config no disponible: se mantiene el valor por defecto.
}

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card portal-card-glowing-border chat-app" id="chat-app">
  <div class="chat-layout">
    <!-- Lista de conversaciones -->
    <aside class="chat-side">
      <div class="chat-side-head">
        <div class="card-header-brand mb-0">
          <i class="fa-solid fa-comments fa-lg"></i>
          <div class="fw-semibold">Conversaciones</div>
        </div>
        <button type="button" class="btn btn-sm btn-portal-gradient chat-new" id="chat-nueva" title="Nueva conversación">
          <i class="fa-solid fa-plus"></i><span class="d-none d-sm-inline ms-1">Nueva</span>
        </button>
      </div>
      <div id="chat-sesiones" class="chat-sesiones"></div>
    </aside>

    <!-- Conversación -->
    <section class="chat-main">
      <div class="chat-mobilebar">
        <button type="button" class="chat-mobilebar-btn" id="chat-historial-toggle" aria-label="Ver conversaciones">
          <i class="fa-solid fa-bars"></i><span>Conversaciones</span>
        </button>
      </div>
      <div id="chat-mensajes" class="chat-mensajes" aria-live="polite"></div>
      <form id="chat-form" class="chat-inputbar" autocomplete="off">
        <textarea id="chat-input" class="portal-input chat-input" rows="1" maxlength="1500"
                  placeholder="Escribe tu duda…"></textarea>
        <button type="submit" class="btn btn-portal-gradient chat-send" id="chat-send" title="Enviar" aria-label="Enviar">
          <i class="fa-solid fa-paper-plane"></i>
        </button>
      </form>
      <div class="chat-help">
        <div class="chat-help-notes">
          <span class="chat-help-note">
            <i class="fa-solid fa-circle-info"></i>
            Este asistente usa IA y procesa tus consultas. No compartas contraseñas, códigos de verificación ni datos financieros.
          </span>
          <span class="chat-help-note">
            <i class="fa-solid fa-database"></i>
            Las conversaciones pueden ser almacenadas por la cooperativa.
          </span>
        </div>
        <a class="chat-help-link chat-link" href="<?= cx_url('solicitudes/pqrs') ?>" data-destino="la solicitud de PQRS">
          <i class="fa-solid fa-headset"></i> ¿Necesitas ayuda humana? Radicar PQRS
        </a>
      </div>
    </section>
  </div>
</div>

<!-- Fondo del cajón de historial (solo móvil) -->
<div class="chat-historial-backdrop" id="chat-historial-backdrop"></div>

<!-- Alerta reutilizable: conversación cerrada / nueva conversación -->
<div class="modal fade" id="chat-alerta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="chat-alerta-titulo">Aviso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="chat-alerta-texto"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="chat-alerta-cancelar" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-portal-gradient" id="chat-alerta-ok">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<script>
window.CX_CHAT_NOMBRE = <?= json_encode($chatNombre) ?>;
window.CX_CHAT_AVATAR = <?= json_encode(is_file(dirname(__DIR__, 4) . '/assets/img/ia.png') ? $APP . '/assets/img/ia.png' : '') ?>;
</script>
<script src="<?= $CX ?>/assets/chat.js"></script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
