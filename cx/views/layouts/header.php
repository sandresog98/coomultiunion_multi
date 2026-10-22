<?php
declare(strict_types=1);
require_once __DIR__ . '/../../_env.php';
$actor = PortalAuth::actor();
if ($actor === null) {
    header('Location: ' . cx_url('login'));
    exit;
}
$pageTitle = $pageTitle ?? 'Portal Asociados';
$activeNav = $activeNav ?? '';
$__envClass = 'env-' . preg_replace('/[^a-z0-9_-]/i', '', app_env());
$__envLabel = env_label();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars(\Csrf::token()) ?>">
<title><?= htmlspecialchars($pageTitle) ?> · Coomultiunion</title>
<link rel="icon" href="<?= $APP ?>/assets/favicons/favicon.ico" sizes="any">
<!-- Tema: fija data-bs-theme ANTES de pintar (evita parpadeo). Por defecto: claro. -->
<script>(function(){try{var t=localStorage.getItem('multi-theme');document.documentElement.setAttribute('data-bs-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-bs-theme','light');}})();</script>
<link href="<?= $APP ?>/assets/vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $APP ?>/assets/vendor/fontawesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="<?= $APP ?>/assets/css/fonts.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $APP ?>/assets/css/front.css">
<link rel="stylesheet" href="<?= $CX ?>/assets/cx.css">
<link rel="stylesheet" href="<?= $APP ?>/assets/css/theme-dark.css">
<script src="<?= $APP ?>/assets/js/solicitud_detalle.js"></script>
</head>
<body class="portal front-body-falling <?php echo $__envClass; ?>">
<header class="portal-topbar">
  <div class="portal-topbar-inner d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <button type="button" class="btn btn-link text-white p-2 me-2 d-md-none" id="cx-drawer-toggle" aria-label="Menú"><i class="fa-solid fa-bars fa-lg"></i></button>
      <a class="header-logos text-decoration-none" href="<?= cx_url('inicio') ?>">
        <img class="logo-sq" src="<?= $APP ?>/assets/img/logo.png" alt="Multi Asociados">
        <img class="logo-169" src="<?= $APP ?>/assets/img/logo_coomultiunion.png" alt="Coomultiunion">
      </a>
      <?php if ($__envLabel !== ''): ?><span class="env-badge ms-2"><?php echo $__envLabel; ?></span><?php endif; ?>
    </div>
    <div class="d-flex align-items-center gap-3 ms-auto">
      <a class="btn-notif btn" href="<?= cx_url('notificaciones') ?>" title="Notificaciones" aria-label="Notificaciones"><i class="fa-solid fa-bell"></i></a>
      <span class="portal-user d-none d-md-inline"><i class="fa-regular fa-user me-1"></i><?= htmlspecialchars($actor['nombre'] ?? '') ?></span>
      <a class="btn btn-logout btn-sm" href="<?= cx_url('logout') ?>" title="Salir"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
  </div>
</header>
<div class="portal-drawer-overlay" id="cx-drawer-overlay"></div>
<div class="portal-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="portal-main">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="portal-page-title fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h1>
</div>
<script>
/* Helpers globales del portal: se definen ANTES de los scripts inline de cada página. */
window.CX_API  = <?= json_encode($API) ?>;
window.CX_BASE = <?= json_encode($CX) ?>;
/* Mapa de rutas amigables (slug -> URL) y helper para construir enlaces en JS.
   Soporta segmentos dinámicos: cxUrl('creditos/{numero}', {numero: '0001'})
   -> /base/cx/creditos/0001 ; los parámetros restantes van como query string. */
window.CX_URLS = <?= json_encode(cx_urls_map()) ?>;
window.cxUrl = function(slug, params){
  slug = String(slug == null ? '' : slug).replace(/^\/+|\/+$/g, '');
  params = params || {};
  slug = slug.replace(/\{([a-zA-Z0-9_]+)\}/g, function(_, name){
    var v = params[name];
    if(v === undefined || v === null) return '';
    delete params[name];
    return encodeURIComponent(v);
  });
  var url = window.CX_URLS[slug] || (String(window.CX_BASE).replace(/\/+$/,'') + '/' + slug);
  var q = [];
  for(var k in params){
    if(!Object.prototype.hasOwnProperty.call(params,k)) continue;
    if(params[k] === undefined || params[k] === null) continue;
    q.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
  }
  if(q.length) url += (url.indexOf('?') >= 0 ? '&' : '?') + q.join('&');
  return url;
};
/* Marca las peticiones a la API como "portal" (X-Portal-Auth) para que el backend
   resuelva la sesión de asociado aunque exista una cookie de staff en el navegador. */
(function(){
  var orig = window.fetch;
  if(typeof orig !== 'function') return;
  window.fetch = function(input, init){
    init = init || {};
    try{
      var url = (typeof input === 'string') ? input : ((input && input.url) || '');
      if(String(url).indexOf('/api/') !== -1){
        var h = new Headers(init.headers || (input && input.headers) || {});
        if(!h.has('X-Portal-Auth')) h.set('X-Portal-Auth','1');
        init.headers = h;
      }
    }catch(e){}
    return orig.call(this, input, init);
  };
})();
window.esc = function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});};
window.money = function(v){var n=Number(v)||0;var neg=n<0;var out=Math.abs(n).toLocaleString('de-DE');return (neg?'-':'')+'$'+out;};
window.moneyAbs = function(v){return '$'+Math.abs(Number(v)||0).toLocaleString('de-DE');};
/* Render simple de fecha en d/m/Y */
window.cxFecha = function(s){ if(!s) return '—'; s=String(s); var d=s.slice(0,10).split('-'); return (d[2])+'/'+(d[1])+'/'+(d[0]); };
/* ------------------------------------------------------------------
   Estándar de listado (Apéndice A §4.2) ligero por cliente.
   window.cxTabla(config) renderiza una tabla con búsqueda, columnas
   ordenables y paginación de 20/página + "Mostrando X-Y de Z".
   config = {
     el: selector del contenedor resultado,
     busqueda: {placeholder, campos:[fn(row),...]},   // o false para ocultar
     cols: [{label, val:fn(row)->string, class:'money'|'money-neg', sort:true}],
     filas: [row,...],
     porPagina: 20 | function(){return n;},   // número o función (env (pt) responsive)
     defaultSort: {col:index, dir:1}   // dir: 1 asc, -1 desc
   }
------------------------------------------------------------------ */
window.cxTabla = function(conf){
  var el = document.querySelector(conf.el);
  if(!el) return;
  function porPagina(){ return typeof conf.porPagina==='function' ? (conf.porPagina()||20) : (conf.porPagina||20); }
  var state = {q:'', order: conf.defaultSort?conf.defaultSort.col:0, dir: conf.defaultSort&&conf.defaultSort.dir!==undefined?conf.defaultSort.dir:-1, page:1};
  var busqueda = conf.busqueda && conf.busqueda.campos && conf.busqueda.campos.length;
  var qWrap, qInput;
  if(busqueda){
    qWrap=document.createElement('div'); qWrap.className='mb-3';
    qWrap.innerHTML='<label class="form-label small text-muted mb-0">Buscar</label>' +
      '<input type="search" class="portal-input" placeholder="'+window.esc(conf.busqueda.placeholder||'Buscar…')+'">';
    qInput=qWrap.querySelector('input');
    qInput.addEventListener('input',function(){ state.q=this.value.trim().toLowerCase(); state.page=1; render(); });
  }
  function parseVal(v){
    if(typeof v==='string'){ var nn=parseFloat(String(v).replace(/[$.,\s]/g,'')); return isNaN(nn)?String(v).toLocaleLowerCase():nn; }
    if(typeof v==='number') return v;
    return String(v==null?'':v).toLowerCase();
  }
  function fila(){
    var rows=conf.filas.map(function(r){
      var campos='';
      if(conf.busqueda && Array.isArray(conf.busqueda.campos)){
        campos=conf.busqueda.campos.map(function(f){return String(f(r)==null?'':f(r)).toLowerCase();}).join(' \u0001 ');
      }
      return {r:r, s:campos};
    });
    if(state.q!==''){ rows=rows.filter(function(x){return x.s.indexOf(state.q)>=0;}); }
    if(conf.cols[state.order].sort!==false){
      var col=conf.cols[state.order];
      rows.sort(function(a,b){ var A=parseVal(col.val(a.r)), B=parseVal(col.val(b.r)); if(A===B)return 0; var c=A>B?1:-1; return c*state.dir; });
    }
    return rows.map(function(x){return x.r;});
  }
  function render(){
    var pp=porPagina();
    var rows=fila(), total=rows.length, paginas=Math.max(1,Math.ceil(total/pp));
    if(state.page>paginas) state.page=paginas;
    var inicio=(state.page-1)*pp, fin=Math.min(total,inicio+pp), page=rows.slice(inicio,fin);
    var ths=conf.cols.map(function(c,i){
      var ic= i===state.order ? (state.dir===1?'fa-arrow-up':'fa-arrow-down') : ((c.sort!==false)?'fa-sort':'');
      var cs=(i===state.order||c.sort!==false)?' sortable':'';
      return '<th class="'+cs+'" data-i="'+i+'">'+window.esc(c.label)+(ic?' <i class="fa-solid '+ic+'"></i>':'')+'</th>';
    }).join('');
    var head='<thead><tr>'+ths+'</tr></thead>';
    var body=page.map(function(r){
      var cells=conf.cols.map(function(c){
        var cls=typeof c.class==='function' ? (c.class(r)||'') : (c.class||'');
        return '<td data-label="'+window.esc(c.label)+'"><span class="cell-val'+(cls?' '+cls:'')+'">'+(c.html?c.html(r):window.esc(c.val(r)))+'</span></td>';
      }).join('');
      return '<tr>'+cells+'</tr>';
    }).join('');
    var tabla='<table class="portal-table"><'+head+'<tbody>'+body+'</tbody></table>';
    var info='Mostrando '+(total?inicio+1:0)+'-'+fin+' de '+total;
    var nav=paginacion(paginas);
    el.innerHTML=tabla+nav;
    var infoEl=document.createElement('div'); infoEl.className='table-info small text-muted text-end mt-1'; infoEl.textContent=info;
    el.appendChild(infoEl);
  }
  function paginacion(paginas){
    if(paginas<=1) return '';
    var h='<nav class="my-2"><ul class="pagination pagination-sm justify-content-center mb-0">';
    h+='<li class="page-item '+(state.page<=1?'disabled':'')+'"><a class="page-link" href="#" data-p="'+(state.page-1)+'">‹</a></li>';
    var start=Math.max(1,state.page-2), end=Math.min(paginas,state.page+2);
    if(start>1){ h+=pageItem(1); if(start>2)h+=ellipsis(); }
    for(var i=start;i<=end;i++) h+='<li class="page-item '+(i===state.page?'active':'')+'"><a class="page-link" href="#" data-p="'+i+'">'+i+'</a></li>';
    if(end<paginas){ if(end<paginas-1)h+=ellipsis(); h+=pageItem(paginas); }
    h+='<li class="page-item '+(state.page>=paginas?'disabled':'')+'"><a class="page-link" href="#" data-p="'+(state.page+1)+'">›</a></li>';
    return h+'</ul></nav>';
    function pageItem(p){return '<li class="page-item '+(p===state.page?'active':'')+'"><a class="page-link" href="#" data-p="'+p+'">'+p+'</a></li>';}
    function ellipsis(){return '<li class="page-item disabled"><span class="page-link">…</span></li>';}
  }
  function init(){
    if(qWrap && qInput){ el.parentNode.insertBefore(qWrap,el); }
    var cont=el.parentNode;
    cont.addEventListener('click',function(e){
      var th=e.target.closest('th.sortable');
      if(th){
        var i=+th.dataset.i;
        if(state.order===i){ state.dir=-state.dir; } else { state.order=i; state.dir=-1; state.page=1; }
        render(); return;
      }
      var a=e.target.closest('a[data-p]');
      if(a){ e.preventDefault(); var p=+a.dataset.p; if(p<1||p>Math.ceil(fila().length/porPagina()))return; state.page=p; render(); }
    });
  }
  var _rz; window.addEventListener('resize',function(){ clearTimeout(_rz); _rz=setTimeout(render,120); });
  init(); render();
};
</script>