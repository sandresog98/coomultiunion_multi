<?php
declare(strict_types=1);

/**
 * Bootstrap de endpoints públicos del módulo (webhooks, baja, media firmada).
 * No abre sesión ni exige staff: cada endpoint valida su propia firma/token.
 */
$root = dirname(__DIR__, 2);

require_once $root . '/core/database.php';
require_once $root . '/core/env.php';
require_once $root . '/core/Http.php';
require_once $root . '/core/Twilio/TwilioException.php';
require_once $root . '/core/SendGrid/SendGridException.php';
require_once $root . '/core/config/ConfigService.php';
require_once $root . '/core/comunicaciones/ContactoService.php';
require_once $root . '/core/comunicaciones/RemitenteService.php';
require_once $root . '/core/comunicaciones/PlantillaService.php';
require_once $root . '/core/comunicaciones/MediaService.php';
require_once $root . '/core/comunicaciones/CostoService.php';
require_once $root . '/core/comunicaciones/ComunicacionService.php';
require_once $root . '/core/comunicaciones/EnvioService.php';
require_once $root . '/core/comunicaciones/FirmaService.php';

date_default_timezone_set('UTC');
try { db()->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) { /* noop */ }

$cfg = new ConfigService();
$envio = new EnvioService(null, $cfg);
