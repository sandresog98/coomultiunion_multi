<?php
declare(strict_types=1);

/**
 * Bootstrap de la API del módulo Comunicaciones (staff con permiso `comunicaciones`).
 * Los webhooks y la baja de email tienen su propio bootstrap público.
 */
$root = dirname(__DIR__, 2);

require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/Twilio/TwilioException.php';
require_once $root . '/core/SendGrid/SendGridException.php';
require_once $root . '/core/config/ConfigService.php';
require_once $root . '/core/comunicaciones/ContactoService.php';
require_once $root . '/core/comunicaciones/RemitenteService.php';
require_once $root . '/core/comunicaciones/PlantillaService.php';
require_once $root . '/core/comunicaciones/MediaService.php';
require_once $root . '/core/comunicaciones/CostoService.php';
require_once $root . '/core/comunicaciones/BaseImportService.php';
require_once $root . '/core/comunicaciones/ComunicacionService.php';
require_once $root . '/core/comunicaciones/EnvioService.php';

// El módulo guarda sus fechas en UTC (independiente de la zona del hosting).
date_default_timezone_set('UTC');
try { db()->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) { /* sin conexión: Auth dará el error */ }

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    if ($e instanceof TwilioException) {
        error_log('[comunicaciones-api][twilio] ' . $e->getMessage());
        Http::fail('Twilio: ' . $e->getMessage(), 502);
    }
    if ($e instanceof SendGridException) {
        error_log('[comunicaciones-api][sendgrid] ' . $e->getMessage());
        Http::fail('SendGrid: ' . $e->getMessage(), 502);
    }
    error_log('[comunicaciones-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
$staff = Auth::requireModulo('comunicaciones');
$cfg     = new ConfigService();
$contactos = new ContactoService(null, $cfg);
$remitentes = new RemitenteService();
$plantillas = new PlantillaService();
$media      = new MediaService();
$costos     = new CostoService($cfg);
$svc        = new ComunicacionService(null, $cfg);
$importador = new BaseImportService($contactos, $cfg);
$envio      = new EnvioService(null, $cfg, $svc);
$actor      = Auth::actor();
