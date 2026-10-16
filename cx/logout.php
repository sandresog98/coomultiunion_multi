<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
PortalAuth::logout();
// Limpia el carrito del portal (sessionStorage) antes de volver al login.
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Cerrando sesión…</title>
<script>
try { sessionStorage.removeItem('cx_convenios_cart_v1'); } catch (e) {}
window.location.replace(<?php echo json_encode(cx_url('login')); ?>);
</script>
</head><body></body></html>
