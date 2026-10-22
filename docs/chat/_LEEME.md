# Base de conocimiento del Asistente IA

Esta carpeta alimenta al asistente del portal (`chat_conocimiento`).

## Cómo agregar contenido

- Crea archivos `.md` o `.txt`.
- Separa cada tema con un encabezado de nivel 2: `## Título del tema`.
- Cada sección se convierte en un fragmento buscable.
- Los archivos que empiezan por `_` o `.` se ignoran (como este `_LEEME.md`).

## Cargar / recargar

Desde la raíz del proyecto:

```bash
php scripts/ingest_conocimiento.php
php scripts/ingest_conocimiento.php --limpiar   # borra todo antes de cargar
```

## Recomendaciones

- Escribe respuestas claras y concretas (sin ambigüedades), en el tono de la cooperativa.
- Incluye datos exactos (tasas, plazos, requisitos) solo si están aprobados para publicar.
- No incluyas datos personales de asociados ni información interna sensible.
- Revisa y actualiza cada trimestre, o cuando cambien políticas o funciones de la app.

## Estructura sugerida

- `institucional.md` — quiénes somos, servicios, horarios, contacto.
- `creditos.md` — requisitos, tipos de crédito, plazos.
- `aportes.md` — aportes ordinarios, extractos, retiros.
- `convenios.md` — convenios y boletas.
- `aplicacion.md` — cómo usar cada función del portal.
