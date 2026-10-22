<?php
declare(strict_types=1);
require __DIR__ . '/_public.php';

/**
 * Baja de correo con enlace firmado (List-Unsubscribe).
 * Acepta GET (clic) y POST (One-Click de clientes de correo).
 */
$token = (string) ($_GET['t'] ?? ($_POST['t'] ?? ''));
$data = FirmaService::verify($token);
$email = is_array($data) ? (string) ($data['email'] ?? '') : '';
$ok = false;
$mensaje = 'El enlace no es válido o ya venció.';

if ($email !== '' && PhoneNormalizer::isEmail($email)) {
    try {
        $contactos = new ContactoService();
        $contactos->registrarOptOut('email', $email, 'unsubscribe', 'Baja desde enlace de correo');
        $ok = true;
        $mensaje = 'Tu correo fue retirado de nuestras comunicaciones.';
    } catch (Throwable $e) {
        error_log('[comunicaciones][baja] ' . $e->getMessage());
        $mensaje = 'No pudimos procesar la baja. Intenta nuevamente.';
    }
}

http_response_code($ok ? 200 : 400);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Baja de comunicaciones</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f5f7fb;margin:0;padding:40px 16px;color:#1f2937}
    .card{max-width:520px;margin:0 auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 8px 30px rgba(17,70,138,.12)}
    h1{color:#11468A;font-size:1.3rem;margin:0 0 12px}
    p{line-height:1.5;margin:0}
  </style>
</head>
<body>
  <div class="card">
    <h1><?= $ok ? 'Baja registrada' : 'No se pudo procesar' ?></h1>
    <p><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
  </div>
</body>
</html>
