<?php
declare(strict_types=1);

/**
 * Tabla única de rutas amigables del portal CX (fuente de verdad).
 *
 * Cada entrada: 'slug-de-la-url' => 'archivo real relativo a cx/'
 *   - Los slugs con {param} son dinámicos: el router captura el segmento y lo
 *     inyecta en $_GET (p. ej. 'solicitudes/{id}' -> $_GET['id']).
 *   - El router primero busca coincidencia EXACTA; si no, prueba los patrones
 *     dinámicos en el orden declarado.
 *
 * Los enlaces se generan con cx_url('slug', $params); si el slug tiene {param}
 * el valor se interpola en la ruta y el resto va como query string.
 *
 * IMPORTANTE: los archivos reales NO se mueven ni renombran. Las URLs largas
 * (cx/modules/x/pages/y) siguen funcionando porque son archivos físicos.
 */

if (!isset($CX_ROUTES)) {
    $CX_ROUTES = [
        // Inicio / cuenta
        'inicio'                       => 'index.php',
        'mi-informacion'               => 'modules/perfil/pages/index.php',

        // Aportes
        'aportes'                      => 'modules/aportes/pages/index.php',
        'extractos'                    => 'modules/aportes/pages/extractos.php',
        'movimientos'                  => 'modules/aportes/pages/movimientos.php',
        'aportes/retiro'               => 'modules/aportes/pages/retiro_aporte.php',

        // Créditos
        'creditos'                     => 'modules/creditos/pages/index.php',
        'creditos/detalle'             => 'modules/creditos/pages/detalle.php', // respaldo ?numero=
        'creditos/{numero}'            => 'modules/creditos/pages/detalle.php', // /cx/creditos/0000002840

        // Solicitudes (servicios del asociado)
        'solicitudes'                  => 'modules/solicitudes/pages/index.php',
        'solicitudes/mis'              => 'modules/solicitudes/pages/mis_solicitudes.php',
        'solicitudes/detalle'          => 'modules/solicitudes/pages/detalle.php', // respaldo ?id=
        'solicitudes/pqrs'             => 'modules/solicitudes/pages/pqrs.php',
        'solicitudes/actualizar-info'  => 'modules/solicitudes/pages/actualizar_info.php',
        'solicitudes/retirar'          => 'modules/solicitudes/pages/retirar.php',
        'solicitudes/certificados'     => 'modules/solicitudes/pages/certificados.php',
        'solicitudes/convenios'        => 'modules/solicitudes/pages/convenios.php',
        'solicitudes/{id}'             => 'modules/solicitudes/pages/detalle.php', // /cx/solicitudes/85

        // Solicitud de crédito
        'nueva-solicitud'              => 'modules/solicitud_credito/pages/index.php',        // formulario
        'solicitar-credito'            => 'modules/solicitud_credito/pages/mis_solicitudes.php', // listado
        'solicitar-credito/mis'        => 'modules/solicitud_credito/pages/mis_solicitudes.php', // alias
        'solicitar-credito/{id}'       => 'modules/solicitud_credito/pages/documentos.php',   // /cx/solicitar-credito/71
        'solicitar-credito/documentos' => 'modules/solicitud_credito/pages/documentos.php',   // alias ?id=
        'solicitar-credito/documentos/{id}' => 'modules/solicitud_credito/pages/documentos.php', // alias
        'solicitar-credito/aceptar'    => 'modules/solicitud_credito/pages/aceptar.php',      // alias ?id=
        'solicitar-credito/aceptar/{id}'    => 'modules/solicitud_credito/pages/aceptar.php', // /cx/solicitar-credito/aceptar/71

        // Fondo de bienestar
        'fondo-bienestar'              => 'modules/fondo_bienestar/pages/index.php',
        'fondo-bienestar/solicitar'    => 'modules/fondo_bienestar/pages/solicitar.php', // alias ?convenio_id=
        'fondo-bienestar/{convenio_id}' => 'modules/fondo_bienestar/pages/solicitar.php', // /cx/fondo-bienestar/2

        // Notificaciones
        'notificaciones'               => 'modules/notificaciones/pages/index.php',

        // Sesión / contraseña / calificación
        'login'                        => 'login.php',
        'logout'                       => 'logout.php',
        'calificar'                    => 'calificar.php',
        'mi-clave'                     => 'cambiar_password.php',
        'clave/crear'                  => 'password_crear.php',
        'clave/verificar'              => 'password_verificar.php',
    ];
}

if (!function_exists('cx_route')) {
    /** Devuelve el archivo real (relativo a cx/) para un slug EXACTO, o null. */
    function cx_route(string $slug): ?string
    {
        global $CX_ROUTES;
        $slug = trim($slug, '/');
        return $CX_ROUTES[$slug] ?? null;
    }
}

if (!function_exists('cx_match')) {
    /**
     * Busca un slug dinámico (con {param}) que coincida con la ruta dada.
     * Devuelve [archivo, ['param' => valor]] o [null, []] si no hay coincidencia.
     *
     * @return array{0:?string,1:array<string,string>}
     */
    function cx_match(string $path): array
    {
        global $CX_ROUTES;
        $path = trim($path, '/');
        foreach ($CX_ROUTES as $slug => $file) {
            if (strpos($slug, '{') === false) {
                continue;
            }
            $names = [];
            $re    = [];
            foreach (explode('/', trim($slug, '/')) as $part) {
                if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $part, $m)) {
                    $names[] = $m[1];
                    $re[]    = '([^/]+)';
                } else {
                    $re[] = preg_quote($part, '#');
                }
            }
            if (preg_match('#^' . implode('/', $re) . '$#', $path, $mm)) {
                array_shift($mm);
                $params = [];
                foreach ($names as $i => $name) {
                    $params[$name] = rawurldecode((string) ($mm[$i] ?? ''));
                }
                return [$file, $params];
            }
        }
        return [null, []];
    }
}

if (!function_exists('cx_url')) {
    /**
     * Construye la URL amigable de un slug. Soporta segmentos dinámicos {param}:
     * el valor se interpola en la ruta y el resto de $query va como query string.
     *
     * Ej: cx_url('aportes')                                -> /base/cx/aportes
     *     cx_url('creditos/{numero}', ['numero'=>2840])    -> /base/cx/creditos/2840
     *     cx_url('solicitudes/{id}', ['id'=>7, 'x'=>'y'])  -> /base/cx/solicitudes/7?x=y
     *
     * @param array<string,mixed> $query
     */
    function cx_url(string $slug, array $query = []): string
    {
        global $CX;
        $slug = trim($slug, '/');

        $slug = (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $m) use (&$query): string {
            if (!array_key_exists($m[1], $query)) {
                return $m[0];
            }
            $value = (string) $query[$m[1]];
            unset($query[$m[1]]);
            return rawurlencode($value);
        }, $slug);

        $url  = rtrim((string) $CX, '/') . ($slug === '' ? '/' : '/' . $slug);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }
}

if (!function_exists('cx_urls_map')) {
    /**
     * Mapa slug => URL absoluta para exponer al JS (window.CX_URLS).
     * Solo incluye rutas estáticas; las dinámicas las resuelve cxUrl() en JS.
     *
     * @return array<string,string>
     */
    function cx_urls_map(): array
    {
        global $CX_ROUTES;
        $out = [];
        foreach (array_keys($CX_ROUTES) as $slug) {
            if (strpos($slug, '{') !== false) {
                continue;
            }
            $out[$slug] = cx_url($slug);
        }
        return $out;
    }
}
