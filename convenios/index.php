<?php
declare(strict_types=1);
/** Convenios públicos (B15/B10) — fuera de ui/. Consume api/convenios_publico (sin auth).
 *  Grilla de convenios con botón "Ordenar" (sin filtros). */
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
<title>Convenios — Coomultiunion</title>
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
  <a class="cv-brand" href="<?= $appBase ?>/convenios/index" aria-label="Convenios"><img src="<?= $LOGO ?>" alt="Coomultiunion"></a>
  <div class="cv-search" id="searchWrap">
    <i class="fas fa-search cv-lupa"></i>
    <input type="search" id="buscar" placeholder=" " autocomplete="off" aria-label="Buscar convenios">
    <div class="cv-search-overlay" id="suggestiveOverlay" aria-hidden="true">
      <span class="cv-suggestive-inner" id="suggestiveInner"><span class="cv-suggestive-text" id="suggestiveText"></span></span>
    </div>
  </div>
  <div class="cv-header-right">
    <button type="button" class="cv-icon-btn" data-theme-toggle title="Cambiar tema" aria-label="Cambiar tema" aria-pressed="false"><i class="fas fa-moon" data-theme-icon></i></button>
    <a class="cv-btn cv-btn-wa" href="https://wa.me/573505780222?text=Hola%2C%20quiero%20consultar%20los%20convenios" target="_blank" rel="noopener">
      <i class="fab fa-whatsapp"></i><span class="d-none d-sm-inline">Contactar</span>
    </a>
  </div>
</header>

<main class="cv-main">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" style="padding-top:14px">
    <h2 class="cv-page-title mb-0 fs-5">Convenios disponibles</h2>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted small" id="infoTotal"></span>
      <div class="cv-sort" id="sortWrap">
        <button type="button" class="cv-sort-btn" id="sortBtn"><span id="sortLbl">Destacados</span> <i class="fas fa-chevron-down" style="font-size:.7rem"></i></button>
        <div class="cv-sort-menu" id="sortMenu"></div>
      </div>
    </div>
  </div>
  <div class="cv-grid cv-grid--cols4" id="grid"><div class="cv-card text-center text-muted py-5" style="grid-column:1/-1">Cargando…</div></div>
  <div class="cv-pagination" id="pagination"></div>
</main>

<div class="cv-footer">
  <div style="margin-top:6px"><i class="fas fa-info-circle"></i> En convenios seleccionados un asociado podrá adquirir beneficios y boletas con precios especiales.</div>
  <div style="margin-top:6px"><a href="<?= $appBase ?>/privacidad" style="color:var(--cv-brand)">Política de tratamiento de datos</a></div>
</div>
</div><!-- .cv-app -->

<script>
const API = <?= json_encode($API) ?>;
let pagina = 1, total = 0, totalPages = 1;
let PER = window.innerWidth < 768 ? 8 : 12;   // móvil 2×4 · PC 4×3
function detectarPer(){ return window.innerWidth < 768 ? 8 : 12; }
let estado = { q:'', orden:'destacado', dir:'asc' };

function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
const $=id=>document.getElementById(id);

/* ---------- Ordenar ---------- */
const SORTS = [
  { v:'destacado', l:'Destacados' },
  { v:'productos', d:'desc', l:'Más productos' },
  { v:'nombre', d:'asc', l:'Nombre A-Z' },
  { v:'nombre', d:'desc', l:'Nombre Z-A' },
];
function renderSort(){ $('sortMenu').innerHTML = SORTS.map((s,i)=>`<button type="button" class="cv-sort-opt ${(s.v===estado.orden&&(s.d||'asc')===estado.dir)?'is-selected':''}" data-i="${i}">${s.l}</button>`).join(''); }
$('sortBtn').addEventListener('click',function(e){e.stopPropagation();$('sortMenu').classList.toggle('is-open');});
$('sortMenu').addEventListener('click',function(e){
  const b=e.target.closest('.cv-sort-opt'); if(!b)return;
  const s=SORTS[+b.dataset.i];
  estado.orden=s.v; estado.dir=s.d||'asc'; pagina=1;
  $('sortLbl').textContent=s.l; $('sortMenu').classList.remove('is-open'); cargar();
});
document.addEventListener('click',function(){ $('sortMenu').classList.remove('is-open'); });

/* ---------- Búsqueda (debounce) ---------- */
let tQ;
$('buscar').addEventListener('input',function(){clearTimeout(tQ);tQ=setTimeout(()=>{estado.q=this.value.trim();pagina=1;cargar();},300);});

/* ---------- Búsqueda sugestiva (typewriter) ---------- */
(function initSuggestive(){
  const SUG_LIST=['Cine','Parque','Salitre','Piscilago','Maloka','Helados','KFC'], TYP=1200;
  let index=0, timers=[];
  const reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const inner=$('suggestiveInner'), textEl=$('suggestiveText');
  function clearT(){timers.forEach(t=>clearTimeout(t));timers=[];}
  function cancel(){ return $('buscar').value.trim()!==''||$('buscar')===document.activeElement; }
  function hideOverlay(){ $('searchWrap').classList.remove('is-suggestive'); clearT(); inner.classList.remove('is-typing','is-paused','is-deleting'); inner.style.width='0'; }
  function showOverlay(){ if(cancel())return; $('searchWrap').classList.add('is-suggestive'); run(); }
  function measureWidth(){ inner.style.transition='none'; inner.style.width='auto'; inner.style.maxWidth='none'; const w=inner.getBoundingClientRect().width; inner.style.width='0px'; inner.style.maxWidth=''; inner.style.transition=''; return Math.ceil(w); }
  function run(){
    if(cancel())return;
    const text=SUG_LIST[index%SUG_LIST.length]; textEl.textContent=text;
    inner.classList.remove('is-typing','is-paused','is-deleting');
    if(reduced){ inner.style.width='auto'; timers.push(setTimeout(()=>{index=(index+1)%SUG_LIST.length; run();},600)); return; }
    const w=measureWidth();
    requestAnimationFrame(()=>{
      if(cancel())return;
      inner.classList.add('is-typing'); inner.style.width=w+'px';
      timers.push(setTimeout(()=>{
        if(cancel())return;
        inner.classList.remove('is-typing'); inner.style.transition='none'; inner.style.width='0px';
        index=(index+1)%SUG_LIST.length;
        requestAnimationFrame(()=>{ inner.style.transition=''; run(); });
      }, TYP));
    });
  }
  $('buscar').addEventListener('focus',hideOverlay);
  $('buscar').addEventListener('blur',function(){ if(this.value.trim()==='')showOverlay(); });
  $('buscar').addEventListener('input',function(){ if(this.value.trim()!=='')hideOverlay(); else if($('buscar')!==document.activeElement)showOverlay(); });
  if($('buscar').value.trim()==='' && $('buscar')!==document.activeElement) showOverlay();
})();

/* ---------- Carga ---------- */
async function cargar(){
  const sp=new URLSearchParams({action:'listado', por_pagina:String(PER), pagina:String(pagina)});
  if(estado.q) sp.set('q',estado.q);
  if(estado.orden!=='destacado') sp.set('orden',estado.orden);
  sp.set('direccion',estado.dir);
  try{
    const r=await fetch(API+'?'+sp.toString());
    const j=await r.json();
    if(!j.ok) throw new Error(j.error||'Error');
    const d=j.data||{};
    total=d.total||0; totalPages=Math.max(1,Math.ceil(total/PER));
    pagina=Math.min(pagina,totalPages);
    $('infoTotal').textContent=total>0?`${(pagina-1)*PER+1}-${Math.min(total,(pagina-1)*PER+(d.rows||[]).length)} de ${total} convenios`:'';
    renderGrid(d.rows||[]); renderPaginacion();
  }catch(e){ $('grid').innerHTML=`<div class="cv-card text-danger py-4" style="grid-column:1/-1">${esc(e.message)}</div>`; }
}
function renderGrid(rows){
  $('grid').innerHTML=rows.length?rows.map(c=>{
    const conProd=c.total_productos>0;
    const link=conProd?`detalle?id=${c.id}`:(c.link||'#');
    const ext=!conProd&&c.link?' target="_blank" rel="noopener"':'';
    return `<a class="cv-card cv-card-link" href="${esc(link)}"${ext}>
      <div class="cv-card-img">
        ${c.imagen_url?`<img src="${esc(c.imagen_url)}" alt="${esc(c.nombre)}" loading="lazy">`:'<i class="fas fa-handshake" style="font-size:2.6rem;color:#c4d3e6"></i>'}
      </div>
      <div class="cv-card-body">
        <h3 class="cv-card-title">${esc(c.nombre)}</h3>
        ${c.descripcion?`<div class="cv-card-desc">${esc(c.descripcion)}</div>`:''}
        ${c.telefono?`<div class="cv-card-meta"><span class="cv-chip-contact"><i class="fas fa-phone"></i>${esc(c.telefono)}</span></div>`:''}
        <div class="cv-card-actions">${conProd?'Ver productos <i class="fas fa-arrow-right"></i>':esc(c.anexo_nombre||'Visitar')+' <i class="fas fa-external-link-alt"></i>'}</div>
      </div>
    </a>`;
  }).join(''):'<div class="cv-empty" style="grid-column:1/-1"><i class="fas fa-handshake"></i>No se encontraron convenios.</div>';
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