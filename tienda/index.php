<?php
declare(strict_types=1);
/** Tienda pública (B15/B8) — fuera de ui/. Consume api/tienda_publico (sin auth). */
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$appBase = rtrim(dirname($dir), '/'); // '' si la app está en la raíz, o '/multi_app_v2'
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
<title>Tienda — Coomultiunion</title>
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
  <button type="button" class="td-icon-btn d-md-none" id="drawerToggle" aria-label="Abrir filtros"><i class="fas fa-sliders-h"></i></button>
  <a class="td-brand" href="<?= $appBase ?>/tienda/index" aria-label="Catálogo">
    <img src="<?= $LOGO ?>" alt="TELMOVIL">
  </a>
  <?php if ($__envLabel !== ''): ?><span class="env-badge"><?= htmlspecialchars($__envLabel) ?></span><?php endif; ?>
  <div class="td-search" id="searchWrap">
    <i class="fas fa-search td-lupa"></i>
    <input type="search" id="buscar" placeholder=" " autocomplete="off" aria-label="Buscar productos">
    <div class="td-search-overlay" id="suggestiveOverlay" aria-hidden="true">
      <span class="td-suggestive-inner" id="suggestiveInner"><span class="td-suggestive-text" id="suggestiveText"></span></span>
    </div>
  </div>
  <div class="td-header-right">
    <span class="td-coop d-none d-md-inline-flex"><img src="<?= $COOP ?>" alt="Coomultiunion"></span>
    <button type="button" class="td-icon-btn" data-theme-toggle title="Cambiar tema" aria-label="Cambiar tema" aria-pressed="false"><i class="fas fa-moon" data-theme-icon></i></button>
    <a class="td-btn td-btn-wa" id="btnWa" href="https://wa.me/573505780222?text=Hola%2C%20quiero%20cotizar%20un%20producto" target="_blank" rel="noopener">
      <i class="fab fa-whatsapp"></i><span class="d-none d-sm-inline">Contactar</span>
    </a>
  </div>
</header>

<!-- Drawer móvil -->
<div class="td-backdrop" id="drawerBackdrop"></div>
<aside class="td-drawer d-md-none" id="drawer">
  <div class="td-drawer-head"><h3>Filtros</h3><button type="button" class="td-drawer-close" id="drawerClose" aria-label="Cerrar">&times;</button></div>
  <div class="td-field" style="margin-bottom:14px">
    <label>Categoría</label>
    <button type="button" class="td-filter-trigger" style="width:100%" id="btnCatM"><span class="td-txt"><b id="lblCatM">Todas</b></span></button>
  </div>
  <div class="td-field" style="margin-bottom:14px">
    <label>Marca</label>
    <button type="button" class="td-filter-trigger" style="width:100%" id="btnMarM"><span class="td-txt"><b id="lblMarM">Todas</b></span></button>
  </div>
  <div class="td-field" style="margin-bottom:14px">
    <label>Rango de precio</label>
    <div class="td-price-range">
      <input type="text" inputmode="numeric" id="minMov" placeholder="Mín" autocomplete="off">
      <span>–</span>
      <input type="text" inputmode="numeric" id="maxMov" placeholder="Máx" autocomplete="off">
    </div>
  </div>
  <button type="button" class="td-btn td-btn-primary" id="applyMov" style="width:100%;justify-content:center">Aplicar filtros</button>
</aside>

<!-- Modal flotante de filtro (categoría / marca) -->
<div class="td-backdrop" id="fFilterBackdrop"></div>
<div class="td-modal" id="filterModal" role="dialog" aria-modal="true">
  <button type="button" class="td-modal-close" data-close onclick="cerrarModales()">&times;</button>
  <h3 id="filterTitle">Categoría</h3>
  <ul class="td-opt-list" id="filterList"></ul>
</div>

<!-- Quick view -->
<div class="td-backdrop" id="qvBackdrop"></div>
<div class="td-modal td-modal-lg" id="qvModal" role="dialog" aria-modal="true">
  <button type="button" class="td-modal-close" data-close onclick="cerrarModales()">&times;</button>
  <div id="qvContent"></div>
</div>

<div class="td-layout">
  <!-- Filtros a la izquierda (escritorio) -->
  <aside class="td-sidebar d-none d-md-block" id="sidebar">
    <h3>Filtros</h3>
    <div style="display:grid;gap:10px">
      <button type="button" class="td-filter-trigger" id="btnCat" style="width:100%">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
        <span class="td-txt"><label>CATEGORÍA</label><b id="lblCat">Todas</b></span>
      </button>
      <button type="button" class="td-filter-trigger" id="btnMar" style="width:100%">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/></svg>
        <span class="td-txt"><label>MARCA</label><b id="lblMar">Todas</b></span>
      </button>
      <div>
        <label style="font-size:.62rem;text-transform:uppercase;letter-spacing:.6px;color:var(--tp-muted)">Rango de precio</label>
        <div class="td-price-range">
          <input type="text" inputmode="numeric" id="minDesk" placeholder="Mín" autocomplete="off">
          <span>–</span>
          <input type="text" inputmode="numeric" id="maxDesk" placeholder="Máx" autocomplete="off">
        </div>
      </div>
      <button type="button" class="td-btn td-btn-primary" id="applyDesk" style="justify-content:center">Aplicar</button>
    </div>
  </aside>

  <!-- Contenido -->
  <main class="td-content">
    <div class="td-qchips" id="qchips"></div>
    <div class="td-chips" id="chips"></div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h2 class="td-page-title mb-0 fs-5" id="sectionTitle">Productos</h2>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted small" id="infoTotal"></span>
        <div class="td-sort" id="sortWrap">
          <button type="button" class="td-sort-btn" id="sortBtn"><span id="sortLbl">Destacados</span> <i class="fas fa-chevron-down" style="font-size:.7rem"></i></button>
          <div class="td-sort-menu" id="sortMenu"></div>
        </div>
      </div>
    </div>
    <div class="td-grid" id="grid"><div class="td-card text-center text-muted py-5" style="grid-column:1/-1">Cargando…</div></div>
    <div class="td-pagination" id="pagination"></div>
  </main>
</div>

<div class="td-footer">
  <div class="td-coop"><img src="<?= $COOP ?>" alt="Coomultiunion"></div>
  <div style="margin-top:6px"><i class="fas fa-info-circle"></i> Catálogo informativo de la cooperativa. Las ofertas y precios pueden cambiar.</div>
  <div style="margin-top:6px"><a href="<?= $appBase ?>/privacidad" style="color:var(--tp-brand)">Política de tratamiento de datos</a></div>
</div>

</div><!-- .td-app -->

<script>
const API = <?= json_encode($API) ?>;
let pagina = 1, total = 0, totalPages = 1;
let PER = window.innerWidth < 768 ? 14 : 15;
function detectarPer(){ return window.innerWidth < 768 ? 14 : 15; }
let estado = { cat: '', mar: '', q: '', min: '', max: '', orden: 'destacado', dir: 'asc' };
let categorias = [], marcas = [];

function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
const $=id=>document.getElementById(id);

/* ---------- Orden (9 opciones tipo legacy) ---------- */
const SORTS = [
  { v:'destacado', l:'Destacados' },
  { v:'nombre', d:'asc', l:'Nombre A-Z' },
  { v:'nombre', d:'desc', l:'Nombre Z-A' },
  { v:'precio', d:'asc', l:'Precio menor a mayor' },
  { v:'precio', d:'desc', l:'Precio mayor a menor' },
  { v:'categoria', d:'asc', l:'Categoría A-Z' },
  { v:'categoria', d:'desc', l:'Categoría Z-A' },
  { v:'marca', d:'asc', l:'Marca A-Z' },
  { v:'marca', d:'desc', l:'Marca Z-A' },
];
function renderSort(){
  $('sortMenu').innerHTML = SORTS.map((s,i)=>`<button type="button" class="td-sort-opt ${(s.v===estado.orden&&(s.d||'asc')===estado.dir)?'is-selected':''}" data-i="${i}">${s.l}</button>`).join('');
}
$('sortBtn').addEventListener('click',function(e){e.stopPropagation();$('sortMenu').classList.toggle('is-open');});
$('sortMenu').addEventListener('click',function(e){
  const b=e.target.closest('.td-sort-opt'); if(!b)return;
  const s=SORTS[+b.dataset.i];
  estado.orden=s.v; estado.dir=s.d||'asc'; pagina=1;
  $('sortLbl').textContent=s.l;
  $('sortMenu').classList.remove('is-open');
  cargar();
});
document.addEventListener('click',function(){ $('sortMenu').classList.remove('is-open'); });

/* ---------- Búsqueda (debounce) ---------- */
let tQ;
$('buscar').addEventListener('input',function(){clearTimeout(tQ);tQ=setTimeout(()=>{estado.q=this.value.trim();pagina=1;cargar();},300);});

/* ---------- Búsqueda sugestiva (typewriter, del legacy) ---------- */
(function initSuggestiveSearch(){
  const SUG_LIST=['iPhone','Samsung','Airpods','Redmi','Xiaomi','Huawei'], TYP=1200, DEL=300;
  let index=0, timers=[];
  const reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const inner=$('suggestiveInner'), textEl=$('suggestiveText');
  function clearT(){timers.forEach(t=>clearTimeout(t));timers=[];}
  function cancel(){ if($('buscar').value.trim()!==''||$('buscar')===document.activeElement) return true; return false; }
  function hideOverlay(){ $('searchWrap').classList.remove('is-suggestive'); clearT(); inner.classList.remove('is-typing','is-paused','is-deleting'); inner.style.width='0'; }
  function showOverlay(){ if(cancel())return; $('searchWrap').classList.add('is-suggestive'); run(); }
  function measureWidth(){
    inner.style.transition='none'; inner.style.width='auto'; inner.style.maxWidth='none';
    var w=inner.getBoundingClientRect().width;
    inner.style.width='0px'; inner.style.maxWidth=''; inner.style.transition='';
    return Math.ceil(w);
  }
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
        inner.classList.remove('is-typing');
        inner.style.transition='none'; inner.style.width='0px';
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

/* ---------- Drawer móvil (con backdrop y botón cerrar) ---------- */
function abrirDrawer(){ $('drawerBackdrop').classList.add('is-open'); $('drawer').classList.add('is-open'); }
function cerrarDrawer(){ $('drawerBackdrop').classList.remove('is-open'); $('drawer').classList.remove('is-open'); }
$('drawerToggle').addEventListener('click',abrirDrawer);
$('drawerClose').addEventListener('click',cerrarDrawer);
$('drawerBackdrop').addEventListener('click',cerrarDrawer);

/* ---------- Modal flotante categoría/marca ---------- */
function abrirModalFiltro(tipo){
  if(tipo==='categoria'){
    $('filterTitle').innerHTML='<i class="fas fa-th-large"></i> Categoría';
    $('filterList').innerHTML=[{id:'',nombre:'Todas'}].concat(categorias).map(c=>`<li><button type="button" data-v="${c.id}" class="${String(c.id)===String(estado.cat||'')?'is-selected':''}">${esc(c.nombre)}</button></li>`).join('');
  }else{
    $('filterTitle').innerHTML='<i class="fas fa-tag"></i> Marca';
    $('filterList').innerHTML=[{id:'',nombre:'Todas'}].concat(marcas).map(m=>`<li><button type="button" data-v="${m.id}" class="${String(m.id)===String(estado.mar||'')?'is-selected':''}">${esc(m.nombre)}</button></li>`).join('');
  }
  $('filterModal')._tipo=tipo;
  $('fFilterBackdrop').classList.add('is-open'); $('filterModal').classList.add('is-open');
}
$('filterList').addEventListener('click',function(e){
  const b=e.target.closest('button'); if(!b)return;
  const tipo=$('filterModal')._tipo, v=b.dataset.v;
  if(tipo==='categoria'){ estado.cat=v; $('lblCat').textContent=(v?categorias.find(c=>String(c.id)===v)?.nombre:'Todas')||'Todas'; $('lblCatM').textContent=$('lblCat').textContent; }
  else{ estado.mar=v; $('lblMar').textContent=(v?marcas.find(m=>String(m.id)===v)?.nombre:'Todas')||'Todas'; $('lblMarM').textContent=$('lblMar').textContent; }
  pagina=1; cerrarModales(); renderQchips(); renderPills(); cargar();
});
['btnCat','btnCatM'].forEach(id=>$(id).addEventListener('click',()=>abrirModalFiltro('categoria')));
['btnMar','btnMarM'].forEach(id=>$(id).addEventListener('click',()=>abrirModalFiltro('marca')));
function cerrarModales(){ ['fFilterBackdrop','qvBackdrop','filterModal','qvModal'].forEach(id=>$(id)&&$(id).classList.remove('is-open')); }

/* ---------- Rango de precio ---------- */
function parseNum(v){ v=String(v||'').replace(/[^\d]/g,''); return v?parseInt(v,10):''; }
function aplicarPrecio(desde, cerrar){
  estado.min = desde==='desk' ? parseNum($('minDesk').value) : parseNum($('minMov').value);
  estado.max = desde==='desk' ? parseNum($('maxDesk').value) : parseNum($('maxMov').value);
  $('minDesk').value = estado.min||''; $('maxDesk').value = estado.max||'';
  $('minMov').value = estado.min||''; $('maxMov').value = estado.max||'';
  pagina=1; if(cerrar)cerrarDrawer(); renderPills(); cargar();
}
$('applyDesk').addEventListener('click',()=>aplicarPrecio('desk',false));
$('applyMov').addEventListener('click',()=>aplicarPrecio('mov',true));

/* ---------- Chips de categoría rápida (con íconos) ---------- */
const ICONS={
  'CELULARES':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>',
  'RELOJES':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
  'AUDIFONOS':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>',
  'TELEVISORES':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2"/><path d="M17 2l-5 5-5-5"/></svg>',
  'CARGADORES':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v6a2 2 0 0 1 2 2v8a2 2 0 0 1-4 0v-8a2 2 0 0 1 2-2V2z"/><path d="M9 6h6"/></svg>',
  'TODOS':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>'
};
const PREF_ORDEN=['CELULARES','RELOJES','AUDIFONOS','CARGADORES'];
function renderQchips(){
  const quick=[{id:'',nombre:'TODOS'}];
  PREF_ORDEN.forEach(n=>{ const c=categorias.find(c=>c.nombre.toUpperCase().trim()===n); if(c)quick.push(c); });
  $('qchips').innerHTML = quick.map(c=>{
    const icono=ICONS[c.nombre.toUpperCase().trim()]||ICONS['TODOS'];
    const active=String(c.id)==='' ? (estado.cat==='') : String(c.id)===String(estado.cat);
    return `<button type="button" class="td-qchip ${active?'is-active':''}" data-id="${c.id}">${icono}<span>${esc(c.nombre)}</span></button>`;
  }).join('');
}
$('qchips').addEventListener('click',function(e){
  const b=e.target.closest('.td-qchip'); if(!b)return;
  estado.cat=b.dataset.id; pagina=1;
  $('lblCat').textContent=(estado.cat?categorias.find(c=>String(c.id)===estado.cat)?.nombre:'Todas')||'Todas';
  $('lblCatM').textContent=$('lblCat').textContent;
  renderQchips(); renderPills(); cargar();
});

/* ---------- Pills de filtros aplicados ---------- */
function renderPills(){
  const pills=[];
  if(estado.q) pills.push({txt:'', k:'q'});
  if(estado.cat) pills.push({txt:'Cat: '+(categorias.find(c=>String(c.id)===estado.cat)?.nombre||''), k:'cat'});
  if(estado.mar) pills.push({txt:'Marca: '+(marcas.find(m=>String(m.id)===estado.mar)?.nombre||''), k:'mar'});
  if(estado.min!==''||estado.max!=='') pills.push({txt:'Precio: '+money(estado.min||0)+' – '+money(estado.max||999999999), k:'precio'});
  const pillIcon=k=>{
    if(k==='q')return '<i class="fas fa-search" style="font-size:.72rem"></i>';
    if(k==='precio')return '<i class="fas fa-dollar-sign" style="font-size:.7rem"></i>';
    if(k==='mar')return '<i class="fas fa-tag" style="font-size:.72rem"></i>';
    return '<i class="fas fa-filter" style="font-size:.65rem"></i>';
  };
  $('chips').innerHTML=pills.map((p,i)=>`<span class="td-chip">${pillIcon(p.k)}${p.txt?' '+esc(p.txt):''} <button type="button" data-i="${i}" aria-label="Quitar">&times;</button></span>`).join('');
  $('chips').style.display = pills.length ? '' : 'none';
}
$('chips').addEventListener('click',function(e){
  const b=e.target.closest('button'); if(!b)return;
  const pills=[]; if(estado.q)pills.push('q'); if(estado.cat)pills.push('cat'); if(estado.mar)pills.push('mar'); if(estado.min!==''||estado.max!=='')pills.push('precio');
  const k=pills[+b.dataset.i];
  if(k==='q'){estado.q='';$('buscar').value='';}
  else if(k==='cat'){estado.cat='';$('lblCat').textContent='Todas';$('lblCatM').textContent='Todas';renderQchips();}
  else if(k==='mar'){estado.mar='';$('lblMar').textContent='Todas';$('lblMarM').textContent='Todas';}
  else {estado.min='';estado.max='';$('minDesk').value='';$('maxDesk').value='';$('minMov').value='';$('maxMov').value='';}
  pagina=1; renderPills(); cargar();
});

/* ---------- Carga ---------- */
async function cargar(){
  const sp=new URLSearchParams({action:'catalogo',por_pagina:PER,pagina});
  if(estado.cat) sp.set('categoria_id',estado.cat);
  if(estado.mar) sp.set('marca_id',estado.mar);
  if(estado.q) sp.set('nombre',estado.q);
  if(estado.min!=='') sp.set('precio_min',estado.min);
  if(estado.max!=='') sp.set('precio_max',estado.max);
  if(estado.orden!=='destacado') sp.set('orden',estado.orden);
  sp.set('direccion',estado.dir);
  try{
    const r=await fetch(API+'?'+sp.toString());
    const j=await r.json();
    if(!j.ok) throw new Error(j.error||'Error');
    const d=j.data||{};
    total=d.total||0; totalPages=Math.max(1,Math.ceil(total/PER));
    pagina=Math.min(pagina,totalPages);
    $('infoTotal').textContent=total>0?`${(pagina-1)*PER+1}-${Math.min(total,(pagina-1)*PER+d.rows.length)} de ${total} productos`:'';
    renderGrid(d.rows); renderPaginacion();
  }catch(e){ $('grid').innerHTML=`<div class="td-card text-danger py-4" style="grid-column:1/-1">${esc(e.message)}</div>`; }
}
function renderGrid(rows){
  $('grid').innerHTML=rows.length?rows.map(pr=>{
    const promo=pr.en_promocion===1&&pr.precio_descuento>0;
    return `<article class="td-card">
      <div class="td-card-img">
        ${promo?`<span class="td-promo-badge">PROMO ${pr.promocion_porcentaje}%</span>`:''}
        ${pr.agotado==1?`<span class="td-agotado-badge">AGOTADO</span>`:''}
        ${pr.foto_url?`<img src="${esc(pr.foto_url)}" alt="${esc(pr.nombre)}" loading="lazy">`:'<i class="fas fa-box td-noimg"></i>'}
      </div>
      <div class="td-card-body">
        <h3 class="td-card-title">${esc(pr.nombre)}</h3>
        <p class="td-card-meta">${esc(pr.categoria)} · ${esc(pr.marca)}</p>
        ${promo
          ?`<div><span class="td-price-promo">${money(pr.precio_descuento)}</span><span class="td-strike">${money(pr.precio_venta)}</span></div>`
          :`<div><span class="td-price">${money(pr.precio_multiunion!=null?pr.precio_multiunion:pr.precio_venta)}</span>
             ${pr.precio_venta>pr.precio_multiunion?`<span class="td-strike">${money(pr.precio_venta)}</span>`:''}</div>`}
        <div class="td-card-actions">
          <button type="button" class="td-btn td-btn-detail" style="color:var(--tp-brand-dark);border-color:var(--tp-line);background:#fff" onclick="abrirQV(${pr.id})">
            <i class="fas fa-eye"></i> Ver detalle</button>
        </div>
      </div>
    </article>`;
  }).join(''):'<div class="td-card text-center text-muted py-5" style="grid-column:1/-1"><i class="fas fa-box-open fa-2x d-block mb-2"></i>No se encontraron productos. Ajusta los filtros.</div>';
}
function renderPaginacion(){
  const el=$('pagination');
  if(totalPages<=1){el.innerHTML='';return;}
  let h='';
  h+=`<a class="td-page ${pagina<=1?'is-disabled':''}" href="#" onclick="go(${pagina-1});return false;">‹</a>`;
  let start=Math.max(1,pagina-2), end=Math.min(totalPages,pagina+2);
  if(start>1){ h+=`<a class="td-page" href="#" onclick="go(1);return false;">1</a>`; if(start>2)h+=`<span class="td-page is-disabled">…</span>`; }
  for(let i=start;i<=end;i++) h+=`<a class="td-page ${i===pagina?'is-active':''}" href="#" onclick="go(${i});return false;">${i}</a>`;
  if(end<totalPages){ if(end<totalPages-1)h+=`<span class="td-page is-disabled">…</span>`; h+=`<a class="td-page" href="#" onclick="go(${totalPages});return false;">${totalPages}</a>`; }
  h+=`<a class="td-page ${pagina>=totalPages?'is-disabled':''}" href="#" onclick="go(${pagina+1});return false;">›</a>`;
  el.innerHTML=h;
}
function go(p){ if(p<1||p>totalPages)return; pagina=p; window.scrollTo({top:0,behavior:'smooth'}); cargar(); }

/* ---------- Quick view ---------- */
async function abrirQV(id){
  try{
    const r=await fetch(`${API}?action=producto&id=${id}`);
    const j=await r.json();
    if(!j.ok) throw new Error(j.error||'Error');
    const p=j.data, promo=p.en_promocion===1&&p.precio_descuento>0;
    $('qvContent').innerHTML=`
      <div class="row g-3">
        <div class="col-md-5"><div class="td-qv-img">${p.foto_url?`<img src="${esc(p.foto_url)}" alt="${esc(p.nombre)}">`:'<i class="fas fa-box" style="font-size:5rem;color:#c4d3e6"></i>'}</div></div>
        <div class="col-md-7">
          <p class="text-muted small mb-1">${esc(p.categoria)} · ${esc(p.marca)}</p>
          <h3 class="fw-bold mb-2">${esc(p.nombre)} ${p.agotado==1?` <span class="td-agotado-badge" style="position:static;display:inline-block;vertical-align:middle">AGOTADO</span>`:''}</h3>
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
          ${p.descripcion?`<p class="small mb-3" style="white-space:pre-line">${esc(p.descripcion)}</p>`:''}
          <a class="td-btn td-btn-wa" target="_blank" rel="noopener" id="qvWa"><i class="fab fa-whatsapp"></i> Cotizar por WhatsApp</a>
          <a class="td-btn td-btn-ghost" style="color:var(--tp-brand-dark);border-color:var(--tp-line);background:#fff" href="detalle.php?id=${p.id}">Ver ficha completa</a>
        </div>
      </div>`;
    $('qvWa').href=`https://wa.me/573505780222?text=${encodeURIComponent('Hola, quiero cotizar: '+p.nombre)}`;
    $('qvBackdrop').classList.add('is-open'); $('qvModal').classList.add('is-open');
  }catch(e){ alert(e.message); }
}
document.querySelectorAll('.td-backdrop').forEach(b=>b.addEventListener('click',()=>cerrarModales()));

/* ---------- Init ---------- */
(async function init(){
  try{
    const [c,m]=await Promise.all([
      fetch(`${API}?action=categorias`).then(r=>r.json()),
      fetch(`${API}?action=marcas`).then(r=>r.json())]);
    categorias=c.data||[]; marcas=m.data||[];
  }catch(e){}
  renderSort(); renderQchips(); renderPills(); cargar();
})();

// Cambio de dispositivo (móvil↔PC): ajusta el nº por página y recarga
window.addEventListener('resize',function(){
  const nuevo=detectarPer();
  if(nuevo!==PER){ PER=nuevo; pagina=1; cargar(); }
});
</script>
<script src="<?= $appBase ?>/assets/js/theme.js"></script>
</body>
</html>