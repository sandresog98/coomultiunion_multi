<?php
declare(strict_types=1);

/**
 * Prueba de regresión: aislamiento de solicitudes por cédula en la API compartida.
 *
 * Verifica:
 *   1. La regla de resolución de actor (ApiActor::via) en el caso que causaba la fuga:
 *      marca de portal + cookies de portal y staff coexistiendo → 'portal'.
 *   2. Que el listado filtrado por cédula devuelve SOLO filas de esa cédula.
 *
 * Uso:  php scripts/test_aislamiento_solicitudes.php
 * Salida: OK/FAIL + código de salida 0/1 (para uso manual o CI futura).
 */

require_once dirname(__DIR__) . '/core/portal/ApiActor.php';
require_once dirname(__DIR__) . '/core/solicitudes/SolicitudService.php';

$fallos = 0;
$ok = 0;
function chk(string $desc, bool $cond): void
{
    global $fallos, $ok;
    if ($cond) { $ok++; echo "  [OK]  $desc\n"; }
    else       { $fallos++; echo "  [FALLA] $desc\n"; }
}

echo "== 1) Resolución de actor (ApiActor::via) ==\n";
// El caso reportado: cookie de staff + cookie de portal + página del portal (marca).
chk("portal marcado + portal + staff -> portal", ApiActor::via(true, true, true) === 'portal');
chk("portal marcado sin cookie portal   -> portal (luego 401, no staff)", ApiActor::via(true, false, false) === 'portal');
// Comportamiento previo (sin marca) intacto.
chk("sin marca, solo portal             -> portal", ApiActor::via(false, true, false) === 'portal');
chk("sin marca, portal + staff          -> staff",  ApiActor::via(false, true, true) === 'staff');
chk("sin marca, solo staff              -> staff",  ApiActor::via(false, false, true) === 'staff');

echo "\n== 2) Aislamiento de datos por cédula ==\n";
$svc = new SolicitudService();
$db  = db();

$global = (int) $db->query("SELECT COUNT(*) FROM solicitudes")->fetchColumn();
$cedula = (string) $db->query(
    "SELECT cedula_solicitante FROM solicitudes
     GROUP BY cedula_solicitante ORDER BY COUNT(*) DESC LIMIT 1"
)->fetchColumn();

$dbCount = 0;
if ($cedula !== '') {
    $st = $db->prepare("SELECT COUNT(*) FROM solicitudes WHERE cedula_solicitante = ?");
    $st->execute([$cedula]);
    $dbCount = (int) $st->fetchColumn();
}

chk("hay al menos una cédula con solicitudes", $cedula !== '' && $dbCount > 0);

$res   = $svc->listar(['cedula' => $cedula], 200, 0);
$rows  = $res['rows'] ?? [];
$total = (int) ($res['total'] ?? -1);

$aProper = (string) $cedula;
$ajenas  = array_filter($rows, static fn(array $r): bool => (string) $r['cedula_solicitante'] !== $aProper);

chk("total del listado == conteo en BD para la cédula ($cedula)", $total === $dbCount);
chk("ninguna fila pertenece a otra cédula", count($ajenas) === 0);
chk("el filtro reduce el universo (total < global)", $dbCount < $global || $global === $dbCount);
chk("respuesta incluye 'por_tipo'", array_key_exists('por_tipo', $res));

echo "\n----------------------------------------\n";
printf("Resultado: %d OK, %d FALLA\n", $ok, $fallos);
exit($fallos === 0 ? 0 : 1);
