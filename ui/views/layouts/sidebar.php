<?php
/** Barra lateral v2 — menú data-driven (grupos/ítems con permiso y clave = $currentPage).
 *  Solo se muestran los módulos CONSTRUIDOS. Cada ítem mapea al bloque (B#) y a la
 *  fila del tracker de pantallas. Para activar un módulo nuevo
 *  basta agregar su(s) ítem(s) con built=true (aquí ya se incluyen al escribirse).
 *
 *  Desktop: columna fija. Mobile: offcanvas (mismo menú, renderizado dos veces con
 *  un namespace de ids distinto).
 */
require_once __DIR__ . '/../../config/paths.php';
require_once dirname(__DIR__, 3) . '/core/Permisos.php';
require_once dirname(__DIR__, 3) . '/core/env.php';
$rol   = $staff['rol'] ?? '';
$cp    = $currentPage ?? '';
$__envLabel = $__envLabel ?? env_label();

/**
 * Nodo: ['tipo'=>'link|grupo', 'label', 'icon', 'key'(solo link), 'href'(solo link),
 *        'perm', 'built', 'hijos'(solo grupo)]
 * 'built' solo queda documental: en este sidebar solo se incluyen construidos.
 */
$menu = [
    ['tipo'=>'link', 'label'=>'Inicio', 'key'=>'dashboard', 'href'=>'index', 'perm'=>null, 'icon'=>'fa-home'],
    ['tipo'=>'grupo', 'label'=>'Oficina', 'icon'=>'fa-building', 'perm'=>'oficina', 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Asociados', 'key'=>'asociados', 'href'=>'modules/asociados/pages/asociados', 'perm'=>'oficina.asociados', 'icon'=>'fa-users', 'built'=>true, 'b'=>'B3', 'p'=>3],
        ['tipo'=>'grupo', 'label'=>'Operaciones', 'icon'=>'fa-exchange-alt', 'perm'=>'oficina.transacciones', 'hijos'=>[
            ['tipo'=>'link', 'label'=>'Pagos PSE', 'icon'=>'fa-building-columns',           'key'=>'pagos_pse',          'href'=>'modules/transacciones/pages/pagos_pse',            'perm'=>'oficina.transacciones', 'built'=>true, 'b'=>'B5', 'p'=>7],
            ['tipo'=>'link', 'label'=>'Pagos Confiar', 'icon'=>'fa-cash-register',       'key'=>'pagos_confiar',      'href'=>'modules/transacciones/pages/pagos_confiar',        'perm'=>'oficina.transacciones', 'built'=>true, 'b'=>'B5', 'p'=>8],
            ['tipo'=>'link', 'label'=>'Crear transacción', 'icon'=>'fa-file-invoice-dollar',   'key'=>'transacciones_crear','href'=>'modules/transacciones/pages/crear',                'perm'=>'oficina.transacciones', 'built'=>true, 'b'=>'B5', 'p'=>5],
            ['tipo'=>'link', 'label'=>'Ver transacciones', 'icon'=>'fa-receipt',   'key'=>'transacciones_lista','href'=>'modules/transacciones/pages/transacciones_listar',  'perm'=>'oficina.transacciones', 'built'=>true, 'b'=>'B5', 'p'=>6],
            ['tipo'=>'link', 'label'=>'Movimientos', 'icon'=>'fa-right-left',         'key'=>'movimientos',       'href'=>'modules/movimientos/pages/movimientos',            'perm'=>'oficina.transacciones', 'built'=>true, 'b'=>'B4', 'p'=>9],
        ]],
        ['tipo'=>'link', 'label'=>'Descargas', 'icon'=>'fa-download', 'key'=>'oficina_descargas', 'href'=>'modules/oficina/pages/descargas', 'perm'=>'oficina.descargas', 'built'=>true, 'b'=>'—', 'p'=>17],
        ['tipo'=>'grupo', 'label'=>'Datos', 'icon'=>'fa-database', 'perm'=>'cargas', 'hijos'=>[
            ['tipo'=>'link', 'label'=>'Cargas de archivos', 'icon'=>'fa-file-import', 'key'=>'cargas', 'href'=>'modules/cargas/pages/cargas', 'perm'=>'cargas', 'built'=>true, 'b'=>'B2', 'p'=>16],
        ]],
    ]],
    ['tipo'=>'grupo', 'label'=>'Créditos', 'icon'=>'fa-industry', 'perm'=>'creditos', 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Nueva solicitud', 'icon'=>'fa-file-circle-plus', 'key'=>'creditos_nueva', 'href'=>'modules/creditos/pages/nueva_solicitud', 'perm'=>'creditos', 'built'=>true, 'b'=>'B7a', 'p'=>25],
        ['tipo'=>'link', 'label'=>'Solicitudes', 'icon'=>'fa-file-lines',     'key'=>'creditos_lista',  'href'=>'modules/creditos/pages/lista_solicitudes', 'perm'=>'creditos', 'built'=>true, 'b'=>'B7a', 'p'=>25],
    ]],
    ['tipo'=>'grupo', 'label'=>'Cobranza', 'icon'=>'fa-phone', 'perm'=>'cobranza', 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Cobranza unificada', 'icon'=>'fa-chart-pie', 'key'=>'cobranza', 'href'=>'modules/cobranza/pages/cobranza', 'perm'=>'cobranza', 'built'=>true, 'b'=>'B6', 'p'=>28],
    ]],
    ['tipo'=>'grupo', 'label'=>'Comunicaciones', 'icon'=>'fa-comment-dots', 'perm'=>'comunicaciones', 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Panel', 'icon'=>'fa-chart-line', 'key'=>'comunicaciones_panel', 'href'=>'modules/comunicaciones/pages/panel', 'perm'=>'comunicaciones', 'built'=>true, 'b'=>'—', 'p'=>0],
        ['tipo'=>'link', 'label'=>'Nueva comunicación', 'icon'=>'fa-paper-plane', 'key'=>'comunicaciones_nueva', 'href'=>'modules/comunicaciones/pages/nueva', 'perm'=>'comunicaciones', 'built'=>true, 'b'=>'—', 'p'=>0],
        ['tipo'=>'link', 'label'=>'Planes', 'icon'=>'fa-calendar-check', 'key'=>'comunicaciones_planes', 'href'=>'modules/comunicaciones/pages/planes', 'perm'=>'comunicaciones', 'built'=>true, 'b'=>'—', 'p'=>0],
        ['tipo'=>'link', 'label'=>'Historial', 'icon'=>'fa-clock-rotate-left', 'key'=>'comunicaciones_historial', 'href'=>'modules/comunicaciones/pages/historial', 'perm'=>'comunicaciones', 'built'=>true, 'b'=>'—', 'p'=>0],
        ['tipo'=>'link', 'label'=>'Plantillas', 'icon'=>'fa-file-lines', 'key'=>'comunicaciones_plantillas', 'href'=>'modules/comunicaciones/pages/plantillas', 'perm'=>'comunicaciones', 'built'=>true, 'b'=>'—', 'p'=>0],
        ['tipo'=>'link', 'label'=>'Configuración', 'icon'=>'fa-gears', 'key'=>'comunicaciones_configuracion', 'href'=>'modules/comunicaciones/pages/configuracion', 'perm'=>'comunicaciones_config', 'built'=>true, 'b'=>'—', 'p'=>0],
    ]],
    ['tipo'=>'grupo', 'label'=>'Solicitudes', 'icon'=>'fa-file-lines', 'perm'=>'solicitudes', 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Tickets', 'icon'=>'fa-list-check', 'key'=>'solicitudes_gestion', 'href'=>'modules/solicitudes/pages/gestion', 'perm'=>'solicitudes.gestion', 'built'=>true, 'b'=>'B7', 'p'=>29],
    ]],
    ['tipo'=>'grupo', 'label'=>'Tienda', 'icon'=>'fa-store', 'perm'=>'tienda', 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Catálogo', 'icon'=>'fa-box-open',     'key'=>'tienda_catalogo',    'href'=>'modules/tienda/pages/catalogo',    'perm'=>'tienda.catalogo',    'built'=>true, 'b'=>'B8', 'p'=>47],
        ['tipo'=>'link', 'label'=>'Clientes', 'icon'=>'fa-id-card',     'key'=>'tienda_clientes',    'href'=>'modules/tienda/pages/clientes',    'perm'=>'tienda.clientes',    'built'=>true, 'b'=>'B8', 'p'=>48],
        ['tipo'=>'link', 'label'=>'Compras', 'icon'=>'fa-cart-plus',      'key'=>'tienda_compras',     'href'=>'modules/tienda/pages/compras',     'perm'=>'tienda.compras',     'built'=>true, 'b'=>'B8', 'p'=>49],
        ['tipo'=>'link', 'label'=>'Inventario', 'icon'=>'fa-warehouse',   'key'=>'tienda_inventario',  'href'=>'modules/tienda/pages/inventario',  'perm'=>'tienda.inventario',  'built'=>true, 'b'=>'B8', 'p'=>50],
        ['tipo'=>'link', 'label'=>'Ventas', 'icon'=>'fa-cart-shopping',       'key'=>'tienda_ventas',      'href'=>'modules/tienda/pages/ventas',      'perm'=>'tienda.ventas',      'built'=>true, 'b'=>'B8', 'p'=>51],
        ['tipo'=>'link', 'label'=>'Facturación', 'icon'=>'fa-file-invoice',  'key'=>'tienda_facturacion', 'href'=>'modules/tienda/pages/facturacion', 'perm'=>'tienda.ventas',      'built'=>true, 'b'=>'B8', 'p'=>52],
        ['tipo'=>'link', 'label'=>'Reversiones / Bonos', 'icon'=>'fa-rotate-left', 'key'=>'tienda_reversiones', 'href'=>'modules/tienda/pages/reversiones', 'perm'=>'tienda.reversiones', 'built'=>true, 'b'=>'B8', 'p'=>53],
        ['tipo'=>'link', 'label'=>'Promociones', 'icon'=>'fa-percent',  'key'=>'tienda_promociones', 'href'=>'modules/tienda/pages/promociones', 'perm'=>'tienda.promociones', 'built'=>true, 'b'=>'B8', 'p'=>54],
        ['tipo'=>'link', 'label'=>'Rangos de precio', 'icon'=>'fa-money-bill-transfer', 'key'=>'tienda_rangos_precio', 'href'=>'modules/tienda/pages/rangos_precio', 'perm'=>'tienda.rangos_precio', 'built'=>true, 'b'=>'B8', 'p'=>55],
        ['tipo'=>'link', 'label'=>'Información', 'icon'=>'fa-circle-info',  'key'=>'tienda_informacion', 'href'=>'modules/tienda/pages/informacion', 'perm'=>'tienda.informacion', 'built'=>true, 'b'=>'B8', 'p'=>56],
    ]],
    ['tipo'=>'grupo', 'label'=>'Convenios', 'icon'=>'fa-handshake', 'perm'=>'convenios.admin', 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Convenios y productos', 'icon'=>'fa-tags', 'key'=>'convenios', 'href'=>'modules/convenios/pages/index', 'perm'=>'convenios.admin', 'built'=>true, 'b'=>'B9/B10', 'p'=>30],
        ['tipo'=>'link', 'label'=>'Stock de boletas', 'icon'=>'fa-ticket',     'key'=>'convenios_stock', 'href'=>'modules/convenios/pages/stock', 'perm'=>'convenios.boletas', 'built'=>true, 'b'=>'B9', 'p'=>24],
    ]],
    ['tipo'=>'grupo', 'label'=>'Configuración', 'icon'=>'fa-cog', 'perm'=>null, 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Productos y Cláusulas', 'icon'=>'fa-boxes-stacked', 'key'=>'config', 'href'=>'modules/config/pages/config', 'perm'=>'oficina', 'built'=>true, 'b'=>'B4', 'p'=>10],
        ['tipo'=>'link', 'label'=>'Tipos de movimiento', 'icon'=>'fa-sliders',   'key'=>'movimientos_tipos','href'=>'modules/config/pages/movimientos_tipos','perm'=>'oficina.movimientos_tipos','built'=>true, 'b'=>'B4', 'p'=>9],
        ['tipo'=>'link', 'label'=>'Parámetros de solicitudes', 'icon'=>'fa-gears', 'key'=>'configuracion', 'href'=>'modules/config/pages/parametros', 'perm'=>'configuracion', 'built'=>true, 'b'=>'B7', 'p'=>14],
        ['tipo'=>'link', 'label'=>'Tipos de comunicación', 'icon'=>'fa-comments', 'key'=>'cobranza_tipos', 'href'=>'modules/config/pages/cobranza_tipos', 'perm'=>'cobranza', 'built'=>true, 'b'=>'B6', 'p'=>28],
        ['tipo'=>'link', 'label'=>'Fondo de Bienestar', 'icon'=>'fa-gift', 'key'=>'fondo_bienestar', 'href'=>'modules/fondo_bienestar/pages/convenios', 'perm'=>'solicitudes.fondo_bienestar', 'built'=>true, 'b'=>'B7', 'p'=>14],
        ['tipo'=>'link', 'label'=>'Categorías de ticket', 'icon'=>'fa-folder-tree', 'key'=>'ticket_categorias', 'href'=>'modules/solicitudes/pages/ticket_categorias', 'perm'=>'solicitudes.gestion', 'built'=>true, 'b'=>'B7', 'p'=>22],
        ['tipo'=>'grupo', 'label'=>'Créditos', 'icon'=>'fa-industry', 'perm'=>'creditos', 'hijos'=>[
            ['tipo'=>'link', 'label'=>'Líneas', 'icon'=>'fa-layer-group',            'key'=>'creditos_config_lineas',       'href'=>'modules/creditos/pages/config_lineas',       'perm'=>'creditos', 'built'=>true, 'b'=>'B7a', 'p'=>25],
            ['tipo'=>'link', 'label'=>'Secciones (etapas)', 'icon'=>'fa-list-ol','key'=>'creditos_config_secciones',   'href'=>'modules/creditos/pages/config_secciones',   'perm'=>'creditos', 'built'=>true, 'b'=>'B7a', 'p'=>25],
            ['tipo'=>'link', 'label'=>'Aceptación', 'icon'=>'fa-file-signature',        'key'=>'creditos_config_aceptaciones', 'href'=>'modules/creditos/pages/config_aceptaciones', 'perm'=>'creditos', 'built'=>true, 'b'=>'B7a', 'p'=>25],
        ]],
    ]],
    ['tipo'=>'grupo', 'label'=>'Sistema', 'icon'=>'fa-server', 'perm'=>null, 'hijos'=>[
        ['tipo'=>'link', 'label'=>'Usuarios', 'key'=>'usuarios', 'href'=>'modules/usuarios/pages/usuarios', 'perm'=>'usuarios.gestion', 'icon'=>'fa-user-cog', 'built'=>true, 'b'=>'B1', 'p'=>19],
        ['tipo'=>'link', 'label'=>'Logs',      'key'=>'logs',     'href'=>'modules/logs/pages/logs',         'perm'=>'logs.gestion',     'icon'=>'fa-clipboard-list', 'built'=>true, 'b'=>'B12', 'p'=>20],
        ['tipo'=>'link', 'label'=>'Asistente IA', 'key'=>'chat_metricas', 'href'=>'modules/chat/pages/metricas', 'perm'=>'chat.metricas', 'icon'=>'fa-robot', 'built'=>true, 'b'=>'B12', 'p'=>21],
        ['tipo'=>'link', 'label'=>'API / Endpoints', 'key'=>'api_authorized_endpoints', 'href'=>'modules/api/pages/authorized_endpoints', 'perm'=>'api', 'icon'=>'fa-plug', 'built'=>true, 'b'=>'B12', 'p'=>22],
    ]],
];

/** ¿El rol puede ver el nodo? (perm null = cualquiera con sesión). */
function menuPuede(string $rol, ?string $perm): bool {
    return $perm === null || Permisos::puede($rol, $perm);
}

/** Devuelve solo los nodos visibles (recursivo). */
function menuVisibles(array $items, string $rol): array {
    $out = [];
    foreach ($items as $nodo) {
        if (($nodo['tipo'] ?? '') === 'grupo') {
            $hijos = menuVisibles($nodo['hijos'] ?? [], $rol);
            if ($hijos) {
                $nodo['hijos'] = $hijos;
                $out[] = $nodo;
            }
        } elseif (menuPuede($rol, $nodo['perm'] ?? null)) {
            $out[] = $nodo;
        }
    }
    return $out;
}

/** ¿El subárbol contiene la hoja activa (key === currentPage)? */
function menuGrupoActivo(array $items, string $cp): bool {
    foreach ($items as $nodo) {
        if (($nodo['tipo'] ?? '') === 'grupo') {
            if (menuGrupoActivo($nodo['hijos'] ?? [], $cp)) {
                return true;
            }
        } elseif (($nodo['key'] ?? '') === $cp) {
            return true;
        }
    }
    return false;
}

/** Renderiza el menú (recursivo) con namespace de ids para collapse. */
function menuRender(array $items, string $rol, string $cp, string $ns, string $prefix = ''): void {
    foreach ($items as $i => $nodo) {
        $id = $ns . '-' . $prefix . $i;
        $perm = $nodo['perm'] ?? null;
        $visible = ($nodo['tipo'] ?? '') === 'grupo' ? true : menuPuede($rol, $perm);

        if (($nodo['tipo'] ?? '') === 'grupo') {
            $open   = menuGrupoActivo($nodo['hijos'] ?? [], $cp);
            $openCls = $open ? ' show' : '';
            $activeCls = $open ? ' text-white fw-semibold' : '';
            $state  = $open ? 'true' : 'false';
            echo '<a class="nav-link d-flex align-items-center justify-content-between' . $activeCls . '" data-bs-toggle="collapse" href="#' . $id . '" role="button" aria-expanded="' . $state . '" aria-controls="' . $id . '">';
            echo '<span><i class="fas ' . ($nodo['icon'] ?? 'fa-folder') . ' me-2"></i>' . htmlspecialchars($nodo['label']) . '</span>';
            echo '<i class="fas fa-chevron-down small chev"></i>';
            echo '</a>';
            echo '<div class="collapse' . $openCls . ' ms-3" id="' . $id . '">';
            menuRender($nodo['hijos'] ?? [], $rol, $cp, $ns, $prefix . $i . '-');
            echo '</div>';
        } else {
            $active = ($cp !== '' && ($nodo['key'] ?? '') === $cp) ? ' active' : '';
            echo '<a class="nav-link' . $active . ' mb-1" href="' . uiBaseUrl() . htmlspecialchars($nodo['href']) . '">';
            echo '<i class="fas ' . ($nodo['icon'] ?? 'fa-circle') . ' me-2"></i>' . htmlspecialchars($nodo['label']);
            echo '</a>';
        }
    }
}
?>
<nav class="col-md-3 col-lg-2 d-none d-md-block sidebar p-3">
  <div class="text-center text-white mb-4">
    <img src="<?php echo assetUrl('img/logo.png'); ?>" alt="Multi" class="mb-2" style="height:64px;max-width:100%;object-fit:contain;" />
    <div class="fw-brand fs-6">Multi App <small class="text-white-50">v2</small><?php if ($__envLabel !== ''): ?> <span class="env-badge ms-1"><?php echo $__envLabel; ?></span><?php endif; ?></div>
  </div>
  <div class="nav flex-column">
    <?php menuRender(menuVisibles($menu, $rol), $rol, $cp, 'sb'); ?>
  </div>
  <div class="mt-3 pt-3 border-top border-white border-opacity-25">
    <button type="button" class="nav-link theme-toggle d-flex align-items-center gap-2 w-100" data-theme-toggle aria-pressed="false">
      <i class="fas fa-moon" data-theme-icon></i>
      <span data-theme-label>Tema oscuro</span>
    </button>
  </div>
</nav>

<div class="offcanvas offcanvas-start d-md-none" id="sidebarOffcanvas" tabindex="-1" style="max-width:280px">
  <div class="offcanvas-header border-bottom border-white border-opacity-25">
    <img src="<?php echo assetUrl('img/logo.png'); ?>" alt="Multi" style="height:36px;object-fit:contain;" class="me-2" />
    <h5 class="text-white fw-brand mb-0">Multi App <small class="text-white-50">v2</small><?php if ($__envLabel !== ''): ?> <span class="env-badge ms-1"><?php echo $__envLabel; ?></span><?php endif; ?></h5>
    <button class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="nav flex-column">
      <?php menuRender(menuVisibles($menu, $rol), $rol, $cp, 'oc'); ?>
    </div>
    <div class="mt-3 pt-3 border-top border-white border-opacity-25">
      <button type="button" class="nav-link theme-toggle d-flex align-items-center gap-2 w-100" data-theme-toggle aria-pressed="false">
        <i class="fas fa-moon" data-theme-icon></i>
        <span data-theme-label>Tema oscuro</span>
      </button>
    </div>
  </div>
</div>