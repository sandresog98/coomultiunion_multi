<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/Email/Mailer.php';

/**
 * POST /api/comunicaciones/smtp_test { to }
 * Envía un correo simple para verificar la conexión SMTP real.
 */
Http::requireMethod('POST');
Http::crud('comunicaciones.configuracion', 'probar');

$in = Http::input();
$to = trim((string) ($in['to'] ?? ''));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    Http::fail('Correo de prueba inválido.');
}
if (!Mailer::isConfigured()) {
    Http::fail('SMTP no está configurado (SMTP_HOST/SMTP_USER/SMTP_PASS).');
}
if (!Mailer::disponible()) {
    Http::fail('PHPMailer no está disponible.');
}

$ok = Mailer::sendAdvanced(
    $to,
    'Prueba SMTP · Multi App',
    "Este es un correo de prueba del módulo de Comunicaciones.\n\nSi lo recibió, el SMTP está operativo.\n\nFecha: " . date('Y-m-d H:i:s'),
    [
        'from' => Mailer::from(),
        'fromName' => 'Coomultiunion',
        'isHtml' => false,
        'headers' => ['X-Test' => 'comunicaciones-smtp'],
    ]
);

if (!$ok) {
    Http::fail('El SMTP rechazó el envío: ' . (Mailer::lastError() ?: 'sin detalle'), 502);
}
Logger::log('probar', 'comunicaciones.smtp', $staff['id'] ?? null, 'Correo de prueba SMTP enviado a ' . $to);
Http::ok(['to' => $to, 'enviado' => true]);
