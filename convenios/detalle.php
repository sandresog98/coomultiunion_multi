<?php
declare(strict_types=1);
/** Detalle público de convenio (B15/B10) — beneficios del convenio con botón "Ordenar".
 *  Consume api/convenios_publico (sin auth): action=detalle&id= (+orden/direccion/página). */
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$appBase = rtrim(dirname($dir), '/');
$API = $appBase . '/api/convenios_publico.php';
$LOGO = $appBase . '/assets/img/logo_coomultiunion.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Convenio — Coomultiunion</title>
<link rel="icon" href="<?= $appBase ?>/assets/favicons/favicon.ico" sizes="any">
<!-- Tema: fija data-bs-theme ANTES de pintar (evita parpadeo). Por defecto: claro. -->
<script>(function(){try{var t=localStorage.getItem('multi-theme');document.documentElement.setAttribute('data-bs-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-bs-theme','light');}})();</script>
<link href="<?= $appBase ?>/assets/vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $appBase ?>/assets/vendor/fontawesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="<?= $appBase ?>/assets/css/fonts.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $appBase ?>/assets/css/front.css">
<link rel="stylesheet" href="<?= $appBase ?>/convenios/assets/convenios.css">
<link rel="stylesheet" href="<?= $appBase ?>/assets/css/theme-dark.css">
</head>
<body>

<div class="cv-falling-wrap" aria-hidden="true"><div class="cv-falling"></div><div class="cv-falling-overlay"></div></div>

<div class="cv-app">
<header class="cv-header">
  <a class="cv-brand" href="<?= $appBase ?>/convenios/index" aria-label="Convenios">
    <img src="<?= $LOGO ?>" alt="Coomultiunion">
  </a>
  <div class="cv-header-right">
    <button type="button" class="cv-icon-btn" data-theme-toggle title="Cambiar tema" aria-label="Cambiar tema" aria-pressed="false"><i class="fas fa-moon" data-theme-icon></i></button>
    <a class="cv-btn cv-btn-wa" href="https://wa.me/573505780222?text=Hola%2C%20quiero%20consultar%20los%20convenios" target="_blank" rel="noopener" aria-label="Contactar">
      <i class="fab fa-whatsapp"></i><span class="d-none d-sm-inline">Contactar</span>
    </a>
    <a class="cv-btn cv-btn-ghost" href="index"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
</header>

<main class="cv-main">
  <div id="hero" class="cv-hero mb-4"></div>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="cv-page-title mb-0 fs-5" id="sectionTitle">Beneficios</h2>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted small" id="infoTotal"></span>
      <div class="cv-sort" id="sortWrap">
        <button type="button" class="cv-sort-btn" id="sortBtn"><i class="fas fa-sort" style="font-size:.75rem;margin-right:6px"></i><span id="sortLbl">Ordenar: Nombre A-Z</span> <i class="fas fa-chevron-down" style="font-size:.7rem"></i></button>
        <div class="cv-sort-menu" id="sortMenu"></div>
      </div>
    </div>
  </div>

  <div class="cv-grid" id="grid"><div class="cv-card text-center text-muted py-5" style="grid-column:1/-1">Cargando…</div></div>
  <div class="cv-pagination" id="pagination"></div>
</main>

<div class="cv-footer">
  <div style="margin-top:6px"><i class="fas fa-info-circle"></i> Catálogo informativo de la cooperativa. Los precios y disponibilidad pueden cambiar; consulta los detalles con el convenio.</div>
  <div style="margin-top:6px"><a href="<?= $appBase ?>/privacidad" style="color:var(--cv-brand)">Política de tratamiento de datos</a></div>
</div>
</div><!-- .cv-app -->

<script>
const API = <?= json_encode($API) ?>;
const id = new URLSearchParams(location.search).get('id');
let pagina = 1, total = 0, totalPages = 1;
let PER = window.innerWidth < 768 ? 8 : 9;   // móvil 2×4 · PC 3×3
function detectarPer(){ return window.innerWidth < 768 ? 8 : 9; }
let estado = { orden:'nombre', dir:'asc' };

function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
const $=id=>document.getElementById(id);

/* ---------- Ordenar ---------- */
const SORTS = [
  { v:'nombre', d:'asc',  l:'Nombre A-Z' },
  { v:'nombre', d:'desc', l:'Nombre Z-A' },
  { v:'precio', d:'asc',  l:'Precio menor a mayor' },
  { v:'precio', d:'desc', l:'Precio mayor a menor' },
];
function renderSort(){
  $('sortMenu').innerHTML = SORTS.map((s,i)=>`<button type="button" class="cv-sort-opt ${(s.v===estado.orden&&s.d===estado.dir)?'is-selected':''}" data-i="${i}">${s.l}</button>`).join('');
}
$('sortBtn').addEventListener('click',function(e){e.stopPropagation();$('sortMenu').classList.toggle('is-open');});
$('sortMenu').addEventListener('click',function(e){
  const b=e.target.closest('.cv-sort-opt'); if(!b)return;
  const s=SORTS[+b.dataset.i];
  estado.orden=s.v; estado.dir=s.d; pagina=1;
  $('sortLbl').textContent='Ordenar: '+s.l;
  $('sortMenu').classList.remove('is-open'); cargar();
});
document.addEventListener('click',function(){ $('sortMenu').classList.remove('is-open'); });

/* ---------- Carga ---------- */
async function cargar(){
  if(!id){ $('grid').innerHTML='<div class="cv-empty" style="grid-column:1/-1">Convenio no indicado.</div>'; return; }
  const sp=new URLSearchParams({action:'detalle', id:String(id), por_pagina:String(PER), pagina:String(pagina)});
  sp.set('orden',estado.orden);
  sp.set('direccion',estado.dir);
  try{
    const r=await fetch(API+'?'+sp.toString());
    const j=await r.json();
    if(!j.ok) throw new Error(j.error||'Error');
    const d=j.data||{};
    renderHero(d);
    total=d.total_productos||0; totalPages=Math.max(1,Math.ceil(total/PER));
    pagina=Math.min(pagina,totalPages);
    $('sectionTitle').textContent='Beneficios';
    $('infoTotal').textContent=total>0?`${(pagina-1)*PER+1}-${Math.min(total,(pagina-1)*PER+(d.productos||[]).length)} de ${total} beneficios`:'';
    renderGrid(d.productos||[]); renderPaginacion();
  }catch(e){ $('grid').innerHTML=`<div class="cv-card text-danger py-4" style="grid-column:1/-1">${esc(e.message)}</div>`; }
}
function renderHero(c){
  document.title=c.nombre+' — Convenios Coomultiunion';
  let chips='';
  if(c.telefono)chips+=`<span class="cv-chip-contact"><i class="fas fa-phone"></i>${esc(c.telefono)}</span>`;
  if(c.correo)chips+=`<span class="cv-chip-contact"><i class="fas fa-envelope"></i>${esc(c.correo)}</span>`;
  if(c.link)chips+=`<a class="cv-chip-contact" style="text-decoration:none;color:var(--cv-brand)" href="${esc(c.link)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i>${esc(c.anexo_nombre||'Visitar')}</a>`;
  $('hero').innerHTML=`
    <div class="cv-hero-img">${c.imagen_url?`<img src="${esc(c.imagen_url)}" alt="${esc(c.nombre)}">`:'<div class="cv-noimg"><i class="fas fa-handshake"></i></div>'}</div>
    <div class="cv-hero-body">
      <h2>${esc(c.nombre)}</h2>
      ${c.descripcion?`<div class="cv-hero-desc mb-2">${esc(c.descripcion)}</div>`:''}
      <div>${chips}</div>
    </div>`;
}
function renderGrid(rows){
  $('grid').innerHTML=rows.length?rows.map(p=>`
    <div class="cv-card">
      <div class="cv-card-img">${p.imagen_url?`<img src="${esc(p.imagen_url)}" alt="${esc(p.nombre)}" loading="lazy">`:'<i class="fas fa-tag" style="font-size:2.4rem;color:#c4d3e6"></i>'}</div>
      <div class="cv-card-body">
        <h3 class="cv-card-title">${esc(p.nombre)}</h3>
        ${p.descripcion?`<div class="cv-card-desc">${esc(p.descripcion)}</div>`:''}
        <div class="cv-price">${esc(p.valor_format||'')}</div>
        ${p.valor_general_format?`<span class="cv-strike">${esc(p.valor_general_format)}</span>`:''}
      </div>
    </div>`).join(''):'<div class="cv-empty" style="grid-column:1/-1"><i class="fas fa-box-open"></i>Este convenio no tiene beneficios publicados.</div>';
}
function renderPaginacion(){
  const el=$('pagination');
  if(totalPages<=1){el.innerHTML='';return;}
  let h=`<a class="cv-page ${pagina<=1?'is-disabled':''}" href="#" onclick="go(${pagina-1});return false;">‹</a>`;
  let start=Math.max(1,pagina-2), end=Math.min(totalPages,pagina+2);
  if(start>1){h+=`<a class="cv-page" href="#" onclick="go(1);return false;">1</a>`; if(start>2)h+=`<span class="cv-page is-disabled">…</span>`;}
  for(let i=start;i<=end;i++) h+=`<a class="cv-page ${i===pagina?'is-active':''}" href="#" onclick="go(${i});return false;">${i}</a>`;
  if(end<totalPages){ if(end<totalPages-1)h+=`<span class="cv-page is-disabled">…</span>`; h+=`<a class="cv-page" href="#" onclick="go(${totalPages});return false;">${totalPages}</a>`; }
  h+=`<a class="cv-page ${pagina>=totalPages?'is-disabled':''}" href="#" onclick="go(${pagina+1});return false;">›</a>`;
  el.innerHTML=h;
}
function go(p){ if(p<1||p>totalPages)return; pagina=p; window.scrollTo({top:0,behavior:'smooth'}); cargar(); }

renderSort(); cargar();
window.addEventListener('resize',function(){ const nuevo=detectarPer(); if(nuevo!==PER){ PER=nuevo; pagina=1; cargar(); } });
</script>
<script src="<?= $appBase ?>/assets/js/theme.js"></script>
</body>
</html>