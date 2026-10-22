<?php
declare(strict_types=1);
/**
 * Roles y permisos (referencia pública). Se genera desde ui/config/secciones.php
 * para que refleje el RBAC real. Sin login.
 */
require __DIR__ . '/_layout.php';

$cfg = require dirname(__DIR__) . '/ui/config/secciones.php';
$modulos  = $cfg['modulos']  ?? [];
$acciones = $cfg['acciones'] ?? [];

/** Pinta un chip simple. */
function ad_chip(string $label, string $extraClass = ''): string
{
    $cls = 'ad-chip' . ($extraClass !== '' ? ' ' . $extraClass : '');
    return '<span class="' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

/** Pinta una lista como chips (badges). */
function ad_chips(array $items): string
{
    if (!$items) {
        return '<span class="text-muted">—</span>';
    }
    $h = '';
    foreach ($items as $i) {
        $h .= ad_chip((string) $i);
    }
    return $h;
}

/**
 * Agrupa las claves por su prefijo (primer segmento) y pinta un chip por grupo.
 * Los grupos con más de un valor se vuelven expandibles (clic para desglosar).
 * Ej.: [solicitudes, solicitudes.gestion, solicitudes.config] → chip "solicitudes"
 * que al abrir muestra "solicitudes.gestion · solicitudes.config".
 */
function ad_chips_grouped(array $items): string
{
    if (!$items) {
        return '<span class="text-muted">—</span>';
    }
    // Agrupa preservando el orden de aparición.
    $grupos = [];
    foreach ($items as $it) {
        $it  = (string) $it;
        $dot = strpos($it, '.');
        $grupo = $dot === false ? $it : substr($it, 0, $dot);
        $grupos[$grupo][] = $it;
    }

    $h = '';
    foreach ($grupos as $grupo => $full) {
        $hijos = array_values(array_filter($full, static fn($f) => $f !== $grupo));
        // Un único valor (o sin hijos) → chip simple, sin desglose.
        if (count($full) === 1 || !$hijos) {
            $h .= ad_chip($full[0]);
            continue;
        }
        $n = count($hijos);
        $h .= '<span class="ad-group">'
            . '<button type="button" class="ad-chip ad-chip-group" data-ad-expand aria-expanded="false" title="Ver ' . $n . ' valor(es)">'
            . htmlspecialchars($grupo)
            . '<span class="ad-group-count">' . $n . '</span>'
            . '<i class="fas fa-chevron-down ad-group-caret"></i>'
            . '</button>'
            . '<span class="ad-group-items" hidden>' . ad_chips($hijos) . '</span>'
            . '</span>';
    }
    return $h;
}

ad_head('Roles y permisos', 'roles');
?>
<div class="card ad-card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm align-top ad-table mb-0">
        <thead>
          <tr><th style="min-width:190px">Rol</th><th style="width:38%">Acceso a módulos</th><th>Operaciones</th></tr>
        </thead>
        <tbody>
        <?php foreach (array_keys($modulos) as $rol):
            if ($rol === 'admin') { continue; }
            $mods    = (array) ($modulos[$rol] ?? []);
            $todoMod = in_array('*', $mods, true);
            $acts    = array_keys(array_filter((array) ($acciones[$rol] ?? []), static fn($v) => $v === true));
            $todoAct = in_array('*', $acts, true);
        ?>
          <tr>
            <td>
              <div class="ad-role"><?= htmlspecialchars((string) $rol) ?></div>
            </td>
            <td><?= $todoMod ? '<span class="ad-chip ad-chip-todo">Todos (*)</span>' : ad_chips_grouped($mods) ?></td>
            <td><?= $todoAct ? '<span class="ad-chip ad-chip-todo">Todas (*)</span>' : ad_chips_grouped($acts) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="ad-note mt-3">
  <i class="fas fa-key"></i>
  <div>
    <strong>Scope especial <code>portal.actuar</code>:</strong> permite operar en nombre de un asociado
    (header <code>X-Acting-As-Cedula</code>) sobre los endpoints del portal. No es un permiso de rol:
    se otorga explícitamente al token.
  </div>
</div>

<script>
  /* Desglose de chips agrupados por prefijo (delegación de eventos). */
  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('[data-ad-expand]') : null;
    if (!b) return;
    e.preventDefault();
    var box  = b.parentElement.querySelector('.ad-group-items');
    var open = b.getAttribute('aria-expanded') === 'true';
    b.setAttribute('aria-expanded', open ? 'false' : 'true');
    if (box) box.hidden = open;
  });
</script>
<?php ad_foot(); ?>
