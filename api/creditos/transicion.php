<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/creditos/transicion { id, accion: etapa|cancelar|rechazar|aprobar|costos_pac|desembolso, ... }
Http::requireMethod('POST');
$in = Http::input(); $actor = Auth::actor();
Idempotencia::begin('creditos');
$id = (int) ($in['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.');
$accion = trim((string) ($in['accion'] ?? ''));
Http::crud('creditos.gestionar_solicitud', [
    'etapa'      => 'etapas',
    'actualizar' => 'actualizar',
    'cancelar'   => 'acciones',
    'rechazar'   => 'acciones',
    'aprobar'    => 'acciones',
    'costos_pac' => 'acciones',
    'desembolso' => 'acciones',
][$accion] ?? 'acciones');
match ($accion) {
    'etapa' => $cred->cambiarEtapa($id, trim((string) ($in['destino'] ?? '')), $actor),
    'cancelar' => $cred->cancelar($id, trim((string) ($in['motivo'] ?? '')), $actor),
    'rechazar' => $cred->rechazar($id, trim((string) ($in['comentario'] ?? '')), $actor),
    'aprobar' => $cred->aprobar($id, $in, $actor),
    'actualizar' => $cred->actualizarDatosBasicos($id, $in, $actor),
    'costos_pac' => $cred->costosPac($id, $in, $actor),
    'desembolso' => $cred->desembolso($id, $in, $actor),
    default => Http::fail('Acción inválida.'),
};
Http::ok(['id' => $id]);