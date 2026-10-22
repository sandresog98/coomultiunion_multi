<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET  /api/comunicaciones/configuracion
 * POST /api/comunicaciones/configuracion {accion:guardar|probar, valores:{...}}
 */
if (Http::method() === 'GET') {
    Auth::requireModulo('comunicaciones_config');
    Http::ok($svc->configuracion());
}

Http::requireMethod('POST');
$in = Http::input();
$accion = trim((string) ($in['accion'] ?? 'guardar'));

if ($accion === 'probar') {
    require_once dirname(__DIR__, 2) . '/core/Email/Mailer.php';
}

if ($accion === 'guardar') {
    Http::crud('comunicaciones.configuracion', 'editar');
    Idempotencia::begin('comunicaciones');
    $valores = $in['valores'] ?? $in;
    if (!is_array($valores)) { Http::fail('Valores inválidos.'); }
    $svc->guardarConfiguracion($valores, $actor);
    Http::ok($svc->configuracion());
}

if ($accion === 'probar') {
    Http::crud('comunicaciones.configuracion', 'probar');
    $res = [
        'twilio' => ['ok' => false, 'detalle' => 'No configurado'],
        'sendgrid' => ['ok' => false, 'detalle' => 'No configurado'],
        'smtp' => [
            'ok' => Mailer::isConfigured() && Mailer::disponible(),
            'detalle' => Mailer::isConfigured()
                ? (Mailer::disponible() ? 'SMTP disponible' : 'PHPMailer no disponible')
                : 'SMTP no configurado',
            'host' => Mailer::host(),
        ],
    ];
    try {
        $tw = TwilioClient::fromEnv();
        if ($tw->isConfigured()) {
            $cuenta = $tw->account();
            $saldo = [];
            try { $saldo = $tw->balance(); } catch (Throwable $e) { $saldo = ['balance' => null, 'currency' => null]; }
            $res['twilio'] = [
                'ok' => true,
                'detalle' => 'Conectado',
                'cuenta' => [
                    'sid' => (string) ($cuenta['sid'] ?? ''),
                    'nombre' => (string) ($cuenta['friendly_name'] ?? ''),
                    'tipo' => (string) ($cuenta['type'] ?? ''),
                    'estado' => (string) ($cuenta['status'] ?? ''),
                ],
                'saldo' => (string) ($saldo['balance'] ?? ''),
                'moneda' => (string) ($saldo['currency'] ?? 'USD'),
            ];
        }
    } catch (Throwable $e) {
        $res['twilio'] = ['ok' => false, 'detalle' => $e->getMessage()];
    }
    try {
        $sg = SendGridClient::fromEnv();
        if ($sg->isConfigured()) {
            $cuenta = $sg->account();
            $res['sendgrid'] = ['ok' => true, 'detalle' => 'Conectado', 'cuenta' => $cuenta];
        }
    } catch (Throwable $e) {
        $res['sendgrid'] = ['ok' => false, 'detalle' => $e->getMessage()];
    }
    Http::ok($res);
}

Http::fail('Acción no válida.');
