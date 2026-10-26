# Contribución y flujo de ramas

## Ramas

| Rama | Uso | Protección |
|---|---|---|
| `master` | Producción (línea base desplegada). | PR obligatorio · sin push directo · sin force-push · sin borrar |
| `develop` | Integración y **fuente del servidor de test**. | PR obligatorio · sin push directo · sin force-push · **no se elimina** |
| `feature/*`, `fix/*`, `docs/*` | Trabajo local. | — |

## Flujo de cambios

1. Crear la rama local desde `develop`:
   ```bash
   git checkout develop && git pull
   git checkout -b feature/mi-cambio
   ```
2. Commit(s) y push de la rama:
   ```bash
   git push -u origin feature/mi-cambio
   ```
3. Abrir **Pull Request → `develop`** (requiere 1 aprobación).
4. Validar en el servidor de test (despliega `develop`).
5. Abrir **Pull Request `develop` → `master`** (requiere 1 aprobación).
6. Merge a `master` y etiquetar el release.

```
feature/*  ──PR──▶  develop  ──PR──▶  master (producción)
```

## Reglas de protección (GitHub)

Configurar en **Settings → Rules → Rulesets** (o *Branches*):

### `master`
- Require a pull request before merging · **1 aprobación**.
- Dismiss stale approvals · Require conversation resolution.
- Block force pushes · Restrict deletions.
- No permitir push directo (incluido admin).
- (Opcional) Require linear history.

### `develop`
- Require a pull request before merging · **1 aprobación**.
- Require conversation resolution.
- Block force pushes · Restrict deletions (**la rama no se elimina**).
- No permitir push directo.

> Los PR hacia `master` deben originarse en `develop` (o `hotfix/*`); los PR hacia `develop`, en ramas locales.

## Base de datos

- `sql/ddl.sql` es la **línea base congelada**: **no se edita**.
- Cualquier cambio de esquema posterior → nueva migración en `sql/migrations/` (ver su `README.md`).
