<?php
declare(strict_types=1);

/**
 * Worker de la cola de correo `email_cola` (general de salida).
 *
 * Uso (CLI):  php core/Email/worker.php [lote]
 * Procesa las filas `pendiente` y con Mailer + SMTP real de .env las envía:
 *   - éxito   → estado='enviado', fecha_envio=NOW()
 *   - fallo   → estado='fallido', error=<mensaje> (NUNCA la contraseña)
 * Si SMTP no está configurado en .env se hace un dry-run: NO marca la cola.
 * Solo para invocar por CLI (php_sapi_name); no se expone por HTTP.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por CLI.');
}

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Email/Mailer.php';

$lote = max(1, (int) ($argv[1] ?? 50));

$pdo = db();

if (!Mailer::isConfigured()) {
    $total = (int) $pdo->query("SELECT COUNT(*) FROM email_cola WHERE estado = 'pendiente'")->fetchColumn();
    echo "[email-worker] SMTP NO configurado en .env (SMTP_HOST/SMTP_USER/SMTP_PASS). Dry-run: {$total} correos pendientes sin marcar.\n";
    return;
}

if (!Mailer::disponible()) {
    echo "[email-worker] PHPMailer no disponible; abortado.\n";
    return;
}

$st = $pdo->prepare(
    "SELECT id, tipo, destino, asunto, cuerpo FROM email_cola
     WHERE estado = 'pendiente' ORDER BY id LIMIT ?"
);
$st->bindValue(1, $lote, PDO::PARAM_INT);
$st->execute();
$filas = $st->fetchAll();

$updOk = $pdo->prepare("UPDATE email_cola SET estado='enviado', fecha_envio=NOW(), error=NULL WHERE id=?");
$updErr = $pdo->prepare("UPDATE email_cola SET estado='fallido', error=? WHERE id=?");

$ok = 0;
$err = 0;
foreach ($filas as $f) {
    $id = (int) $f['id'];
    try {
        $sent = Mailer::send((string) $f['destino'], (string) $f['asunto'], (string) $f['cuerpo']);
        if ($sent) {
            $updOk->execute([$id]);
            $ok++;
            echo "[email-worker] #{$id} enviado → {$f['destino']}\n";
        } else {
            $updErr->execute(['PHPMailer devolvió false (ver log).', $id]);
            $err++;
            echo "[email-worker] #{$id} FALLO → {$f['destino']} (marcado fallido)\n";
        }
    } catch (Throwable $e) {
        $updErr->execute([substr($e->getMessage(), 0, 500), $id]);
        $err++;
        echo "[email-worker] #{$id} excepción → {$f['destino']} (marcado fallido)\n";
    }
}

echo "[email-worker] lotes: {$ok} enviados, {$err} fallidos de " . count($filas) . " procesados.\n";