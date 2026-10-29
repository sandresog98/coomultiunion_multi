<?php
declare(strict_types=1);
/** Detalle de producto — Tienda pública (B15/B8). Consume api/tienda_publico (sin auth). */
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$appBase = rtrim(dirname($dir), '/');
$API = $appBase . '/api/tienda_publico.php';
$LOGO = $appBase . '/assets/img/logo_telmovil.png';
$COOP = $appBase . '/assets/img/logo_coomultiunion.png';
require_once __DIR__ . '/../core/env.php';
$__envClass = env_body_class();
$__envLabel = env_label();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tienda Multi — Coomultiunion</title>
<link rel="icon" href="<?= $appBase ?>/assets/favicons/favicon_telmovil.ico" sizes="any">
<!-- Tema: fija data-bs-theme ANTES de pintar (evita parpadeo). Por defecto: claro. -->
<script>(function(){try{var t=localStorage.getItem('multi-theme');document.documentElement.setAttribute('data-bs-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-bs-theme','light');}})();</script>
<link href="<?= $appBase ?>/assets/vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $appBase ?>/assets/vendor/fontawesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="<?= $appBase ?>/assets/css/fonts.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $appBase ?>/assets/css/front.css">
<link rel="stylesheet" href="<?= $appBase ?>/tienda/assets/tienda.css">
<link rel="stylesheet" href="<?= $appBase ?>/assets/css/theme-dark.css">
</head>
<body class="<?= $__envClass ?>">

<div class="td-falling-wrap" aria-hidden="true">
  <div class="td-falling"></div>
  <div class="td-falling-overlay"></div>
</div>

<div class="td-app">

<header class="td-header">
  <a class="td-brand" href="<?= $appBase ?>/tienda/index">
    <img src="<?= $LOGO ?>" alt="TELMOVIL">
  </a>
  <?php if ($__envLabel !== ''): ?><span class="env-badge"><?= htmlspecialchars($__envLabel) ?></span><?php endif; ?>
  <div class="td-header-right">
    <span class="td-coop d-none d-md-inline-flex"><img src="<?= $COOP ?>" alt="Coomultiunion"></span>
    <button type="button" class="td-icon-btn" data-theme-toggle title="Cambiar tema" aria-label="Cambiar tema" aria-pressed="false"><i class="fas fa-moon" data-theme-icon></i></button>
    <a class="td-btn td-btn-ghost" href="index"><i class="fas fa-arrow-left"></i> Volver al catálogo</a>
  </div>
</header>

<main class="td-main py-4" id="detalle" style="max-width:1080px">
  <div class="td-card text-center text-muted py-5" style="grid-column:1/-1"><i class="fas fa-spinner fa-spin"></i> Cargando…</div>
</main>

<div class="td-footer">
  <div class="td-coop"><img src="<?= $COOP ?>" alt="Coomultiunion"></div>
  <div style="margin-top:6px"><i class="fas fa-info-circle"></i> Catálogo informativo de la cooperativa.</div>
  <div style="margin-top:6px"><a href="<?= $appBase ?>/privacidad" style="color:var(--tp-brand)">Política de tratamiento de datos</a></div>
</div>

</div><!-- .td-app -->

<script>
const API = <?= json_encode($API) ?>;
const id = new URLSearchParams(location.search).get('id');
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

async function cargar(){
  const el=document.getElementById('detalle');
  if(!id){el.innerHTML='<div class="card p-4 text-center">Producto no indicado.</div>';return;}
  try{
    const r=await fetch(`${API}?action=producto&id=${id}`);
    const j=await r.json();
    if(!j.ok) throw new Error(j.error||'Error');
    const p=j.data, promo=p.en_promocion===1&&p.precio_descuento>0;
    el.innerHTML=`
      <div class="row g-4">
        <div class="col-md-6">
          <div class="td-qv-img" style="aspect-ratio:1/1">${p.foto_url?`<img src="${esc(p.foto_url)}" alt="${esc(p.nombre)}">`:'<i class="fas fa-box" style="font-size:6rem;color:#c4d3e6"></i>'}</div>
        </div>
        <div class="col-md-6">
          <div class="td-card p-4">
            ${promo?`<span class="td-promo-badge" style="position:static;display:inline-block;margin-bottom:8px">PROMO ${p.promocion_porcentaje}%</span>`:''}
            <p class="text-muted small mb-1">${esc(p.categoria)} · ${esc(p.marca)}</p>
            <h2 class="fw-bold mb-3">${esc(p.nombre)} ${p.agotado==1?` <span class="td-agotado-badge" style="position:static;display:inline-block;vertical-align:middle">AGOTADO</span>`:''}</h2>
            ${promo
              ?`<div class="td-det-precio">
                  <div class="td-det-oferta">${money(p.precio_descuento)} <span class="td-det-badge-oferta">OFERTA</span></div>
                  <div class="td-det-coop">${money(p.precio_multiunion)} <span class="td-det-etiq">(Precio Coomultiunion)</span></div>
                  <div class="td-det-comercial">${money(p.precio_venta)} <span class="td-det-etiq">(Precio Comercial)</span></div>
                </div>`
              :`<div class="td-det-precio">
                  <div class="td-det-coop td-det-coop-main">${money(p.precio_multiunion)} <span class="td-det-etiq">(Precio Coomultiunion)</span></div>
                  <div class="td-det-comercial">${money(p.precio_venta)} <span class="td-det-etiq">(Precio Comercial)</span></div>
                </div>`}
            ${p.descripcion?`<p class="small mb-4" style="white-space:pre-line">${esc(p.descripcion)}</p>`:''}
            <a class="td-btn td-btn-wa w-100 mb-2" id="wa" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Cotizar por WhatsApp</a>
            <a class="td-btn td-btn-ghost w-100" style="color:var(--tp-brand-dark);border-color:var(--tp-line);background:#fff;justify-content:center" href="index"><i class="fas fa-arrow-left"></i> Volver al catálogo</a>
          </div>
        </div>
      </div>`;
    document.getElementById('wa').href=`https://wa.me/573505780222?text=${encodeURIComponent('Hola, quiero cotizar: '+p.nombre)}`;
  }catch(e){el.innerHTML=`<div class="card p-4 text-danger">${esc(e.message)}</div>`;}
}
cargar();
</script>
<script src="<?= $appBase ?>/assets/js/theme.js"></script>
</body>
</html>