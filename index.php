<?php
declare(strict_types=1);
/**
 * Landing / hub de Multi App v2.
 * Página estática y pública (sin auth, sin BD) que enlaza a las apps.
 * Resuelve la raíz "/" (existía 403 por falta de DirectoryIndex + Options -Indexes).
 */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Base de la app (robusto en subdirectorio): /proyecto/multi/multi
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

// Nodos del hub: [url, label, desc, icono(emoji), color]
$apps = [
    ['url' => 'ui/login',          'label' => 'Panel de administración', 'desc' => 'Oficina, cobranza, créditos, tienda, convenios, sistema.', 'icon' => '🏢', 'color' => '#11468A'],
    ['url' => 'cx/login',          'label' => 'Portal del asociado',     'desc' => 'Mi información, aportes, movimientos y créditos.',       'icon' => '👤', 'color' => '#1882CD'],
    ['url' => 'simulador/',        'label' => 'Simulador de crédito',    'desc' => 'Calcula tu plan de pagos de forma pública.',            'icon' => '📈', 'color' => '#1882CD'],
    ['url' => 'convenios/',        'label' => 'Convenios',               'desc' => 'Catálogo de beneficios y aliados.',                     'icon' => '🤝', 'color' => '#EB3031'],
    ['url' => 'tienda/',           'label' => 'Tienda',                  'desc' => 'Catálogo de productos con promociones.',               'icon' => '🛒', 'color' => '#FCD329'],
];
// Rutas RELATIVAS: cada app es una carpeta hermana del index raíz (robusto en subdirectorio).
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Multi App v2</title>
<link rel="icon" href="<?= $appBase ?>/assets/favicons/favicon.ico" sizes="any">
<style>
  :root{ --b:#11468A; --a:#1882CD; --y:#FCD329; --r:#EB3031; }
  *{ box-sizing:border-box; margin:0; padding:0; }
  body{
    min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:40px 16px; font-family:'Poppins','Segoe UI',system-ui,Arial,sans-serif;
    background:linear-gradient(160deg,#0b2f5f 0%,var(--a) 55%,#0b2f5f 100%); color:#fff;
  }
  .brand{ text-align:center; margin-bottom:34px; }
  .brand h1{ font-size:2.6rem; font-weight:800; letter-spacing:.5px;
    background:linear-gradient(90deg,#fff,var(--y)); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; }
  .brand small{ display:block; margin-top:4px; color:#cfddff; font-weight:600; font-size:.95rem; }
  .grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; width:100%; max-width:900px; }
  a.card{
    display:block; padding:22px 18px; border-radius:14px; text-decoration:none; color:#fff;
    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.16);
    transition:transform .15s ease, box-shadow .15s ease, background .15s ease;
  }
  a.card:hover{ transform:translateY(-4px); box-shadow:0 10px 24px rgba(0,0,0,.28); background:rgba(255,255,255,.12); }
  a.card .ico{ font-size:2rem; display:block; margin-bottom:10px; }
  a.card .lbl{ font-weight:700; font-size:1.05rem; margin-bottom:4px; }
  a.card .dsc{ font-size:.83rem; color:#d7e2ff; line-height:1.4; }
  .foot{ margin-top:30px; font-size:.8rem; color:#b9c9ee; }
</style>
</head>
<body>
  <div class="brand">
    <h1>Multi App</h1>
    <small>Cooperativa Multiactiva — Aplicación v2</small>
  </div>
  <div class="grid">
    <?php foreach ($apps as $a): ?>
      <a class="card" href="<?php echo htmlspecialchars($a['url']); ?>">
        <span class="ico"><?php echo $a['icon']; ?></span>
        <span class="lbl"><?php echo htmlspecialchars($a['label']); ?></span>
        <span class="dsc"><?php echo htmlspecialchars($a['desc']); ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="foot">© <?php echo date('Y'); ?> Coopmultiactiva · Selecciona una aplicación para continuar.</div>
</body>
</html>