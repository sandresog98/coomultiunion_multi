<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET  /api/comunicaciones/plan?id=            → detalle + destinatarios + eventos
 * POST /api/comunicaciones/plan {id, accion}   → programar | cancelar | duplicar | reintentar | enviar_ahora
 */
if (Http::method() === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) { Http::fail('Falta el id del plan.'); }
    Http::ok($svc->detallePlan($id));
}

Http::requireMethod('POST');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
$accion = trim((string) ($in['accion'] ?? ''));
if ($id <= 0) { Http::fail('Falta el id del plan.'); }

switch ($accion) {
    case 'programar':
        Http::crud('comunicaciones.planes', 'programar');
        Idempotencia::begin('comunicaciones');
        $modo = (string) ($in['modo'] ?? 'fecha');
        $plan = $svc->programarPlan($id, (string) ($in['programado_para'] ?? ''), $modo, $actor);
        $procesando = null;
        if ($modo === 'ahora') {
            $procesando = $envio->procesarPlan($id, 25, true);
            $plan = $svc->detallePlan($id)['plan'];
        }
        Http::ok(['plan' => $plan, 'procesando' => $procesando]);
        break;

    case 'enviar_ahora':
        Http::crud('comunicaciones.planes', 'programar');
        Idempotencia::begin('comunicaciones');
        $plan = $svc->programarPlan($id, null, 'ahora', $actor);
        $procesando = $envio->procesarPlan($id, 25, true);
        Http::ok(['plan' => $svc->detallePlan($id)['plan'], 'procesando' => $procesando]);
        break;

    case 'cancelar':
        Http::crud('comunicaciones.planes', 'cancelar');
        Idempotencia::begin('comunicaciones');
        $svc->cancelarPlan($id, $actor);
        Http::ok($svc->detallePlan($id)['plan']);
        break;

    case 'duplicar':
        Http::crud('comunicaciones.planes', 'duplicar');
        Idempotencia::begin('comunicaciones');
        $newId = $svc->duplicarPlan($id, $actor);
        Http::ok(['id' => $newId, 'plan' => $svc->detallePlan($newId)['plan']], 201);
        break;

    case 'reintentar':
    case 'reenviar':
        Http::crud('comunicaciones.planes', 'reintentar');
        Idempotencia::begin('comunicaciones');
        $destId = (int) ($in['destinatario_id'] ?? 0);
        if ($destId <= 0) { Http::fail('Falta el destinatario.'); }
        $envio->reintentar($destId, $actor, $accion === 'reenviar');
        $svc->recalcularPlan($id);
        Http::ok($svc->detallePlan($id));
        break;

    default:
        Http::fail('Acción no válida.');
}
