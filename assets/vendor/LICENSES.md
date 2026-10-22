# Librerías vendorizadas — Multi App v2

Estos assets se sirven **localmente** (self-host) para no hacer peticiones a
terceros (privacidad + rendimiento + robustez). Se conservaron **las mismas
versiones** que se usaban desde los CDN.

| Librería | Versión(es) | Licencia | Origen |
|---|---|---|---|
| Bootstrap | 5.3.0 · 5.3.3 | MIT | https://github.com/twbs/bootstrap |
| Font Awesome Free | 6.0.0 · 6.5.2 | Icons: CC BY 4.0 · Fonts: SIL OFL 1.1 · Code: MIT | https://github.com/FortAwesome/Font-Awesome |
| Poppins | Google Fonts | SIL Open Font License 1.1 | https://github.com/itfoundry/Poppins |
| Tom Select | 2.6.2 | Apache-2.0 | https://github.com/orchidjs/tom-select |
| Swagger UI | 5.33.3 | Apache-2.0 | https://github.com/swagger-api/swagger-ui |
| js-yaml | 4.1.0 | MIT | https://github.com/nodeca/js-yaml |

## Estructura

```
assets/
├── css/fonts.css                      # @font-face de Poppins (rutas locales)
├── fonts/poppins/*.woff2              # Poppins 400/600/700/800 (latin, latin-ext, devanagari)
└── vendor/
    ├── bootstrap/{5.3.0,5.3.3}/       # css/bootstrap.min.css · js/bootstrap.bundle.min.js
    ├── fontawesome/{6.0.0,6.5.2}/     # css/all.min.css · webfonts/*
    ├── tom-select/2.6.2/              # css/tom-select.bootstrap5.min.css · js/tom-select.complete.min.js
    ├── swagger-ui/5.33.3/             # swagger-ui.css · swagger-ui-bundle.js
    └── js-yaml/4.1.0/                 # js-yaml.min.js
```

Los textos completos de cada licencia están en los repositorios de origen
enlazados arriba.
