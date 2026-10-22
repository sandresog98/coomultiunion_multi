<?php
declare(strict_types=1);
/**
 * Documentación pública de la API por tokens — renderiza openapi.yaml con Swagger UI.
 * El spec se embebe en la página (no se expone su ruta). Sin login.
 *
 * Mejoras: versiones de CDN fijadas con SRI (integridad), y un enriquecimiento del
 * spec que hace visibles las extensiones x-* (actor, scopes, módulo-gate, idempotencia
 * y restricciones) que Swagger UI no muestra por defecto.
 */
require __DIR__ . '/_layout.php';
$spec = (string) @file_get_contents(__DIR__ . '/openapi.yaml');

ad_head('Documentación', 'docs',
  '<link rel="stylesheet" href="' . ad_base() . '/assets/vendor/swagger-ui/5.33.3/swagger-ui.css">');
?>
<div class="ad-swagger">
  <div id="swagger-ui"><div class="ad-loading"><i class="fas fa-spinner fa-spin"></i> Cargando documentación…</div></div>
</div>

<script src="<?= ad_base() ?>/assets/vendor/js-yaml/4.1.0/js-yaml.min.js"></script>
<script src="<?= ad_base() ?>/assets/vendor/swagger-ui/5.33.3/swagger-ui-bundle.js"></script>
<script>
  const SPEC = jsyaml.load(<?= json_encode($spec, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);

  /* ------------------------------------------------------------------
   * Plugin de metadatos: Swagger UI ignora las extensiones x-*; aquí las
   * convertimos en una tabla Markdown legible al final de cada descripción
   * y quitamos la prosa "**Restricciones.**" duplicada.
   * ------------------------------------------------------------------ */
  (function enriquecer(x) {
    const esc  = (s) => String(s).replace(/\|/g, '\\|');
    const limpia = (d) => typeof d === 'string'
      ? d.replace(/(?:\s*—\s*)?\*\*Restricciones\.\*\*[\s\S]*$/, '').trim()
      : d;
    const paths = (x && x.paths) || {};
    for (const p of Object.keys(paths)) {
      for (const m of ['get', 'post', 'put', 'delete', 'patch']) {
        const op = paths[p][m];
        if (!op) continue;
        const filas = [];
        const scopes = op['x-required-scopes'];
        if (Array.isArray(scopes) && scopes.length) {
          filas.push(['Scopes', scopes.map((s) => '`' + esc(s) + '`').join(', ')]);
        }
        if (op['x-module-gate']) filas.push(['Módulo', '`' + esc(op['x-module-gate']) + '`']);
        if (op['x-actor'])       filas.push(['Actor', esc(op['x-actor'])]);
        if (op['x-idempotent'])  filas.push(['Idempotencia', 'Requiere `X-Idempotency-Key`']);
        if (op['x-restrictions']) {
          const notas = String(op['x-restrictions']).split(/\r?\n/).map((l) => l.trim()).filter(Boolean).join(' <br> ');
          filas.push(['Notas', esc(notas)]);
        }
        let desc = limpia(op.description || '');
        if (filas.length) {
          const tabla = '\n\n| Campo | Valor |\n|---|---|\n' +
            filas.map(([k, v]) => '| ' + k + ' | ' + v + ' |').join('\n');
          desc = (desc ? desc + '\n' : '') + tabla;
        }
        op.description = desc;
      }
    }
  })(SPEC);

  window.ui = SwaggerUIBundle({
    spec: SPEC,
    dom_id: '#swagger-ui',
    deepLinking: true,
    docExpansion: 'list',
    defaultModelsExpandDepth: -1,
    displayRequestDuration: true,
    tryItOutEnabled: true,
    presets: [SwaggerUIBundle.presets.apis]
  });
</script>
<?php ad_foot(); ?>
