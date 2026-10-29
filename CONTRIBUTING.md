# Contribución y flujo de ramas

## Regla de oro (obligatoria)

**Nunca se hace merge directo a `master`.** El único flujo permitido es:

```text
rama/*  ──PR──▶  develop  ──PR──▶  master
```

- Ningún `feature/*`, `fix/*`, `hotfix/*` o rama personal puede abrir PR directo a `master`.
- `hotfix/*` **no es una excepción**: nace desde `develop`, entra por PR a `develop` y luego
  `develop` pasa por PR a `master`.
- No se permite merge local ni push directo a `master`.
- `master` solo puede recibir cambios desde `develop` mediante un PR aprobado.
- Si un PR aparece con base `master` y no viene de `develop`, debe cerrarse y recrearse contra `develop`.

> Procedimiento detallado, workflows, configuración de rulesets y resolución de problemas:
> [`docs/flujo_ramas_y_sincronizacion.md`](docs/flujo_ramas_y_sincronizacion.md).

## Ramas

| Rama | Uso | Protección |
|---|---|---|
| `master` | Producción (línea base desplegada). Solo recibe PR desde `develop`. | PR obligatorio · sin push directo · sin force-push · sin borrar |
| `develop` | Integración y **fuente del servidor de test**. | PR obligatorio · sin push directo · sin force-push · **no se elimina** |
| `feature/*`, `fix/*`, `docs/*`, `hotfix/*` | Trabajo local. Siempre entran por PR a `develop`. | — |

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

```text
feature/*  ──PR──▶  develop  ──PR──▶  master (producción)
fix/*      ──PR──▶  develop
hotfix/*   ──PR──▶  develop  ──PR──▶  master
```

### Hotfixes

Un hotfix sigue exactamente el mismo flujo:

```bash
git checkout develop && git pull
git checkout -b hotfix/descripcion-corta
# corregir, commit y push
git push -u origin hotfix/descripcion-corta
```

Luego: PR `hotfix/*` → `develop` y, una vez integrado y validado, PR `develop` → `master`.
**No se abre PR de `hotfix/*` directo a `master`.**

## Estrategia de merge y sincronización

- `feature/*`, `fix/*`, `docs/*` y `hotfix/*` → `develop`: **Squash and merge**.
- `develop` → `master`: **Merge commit**, no squash ni rebase. Así los commits de `develop`
  quedan como ancestros de `master` y no se genera divergencia artificial.
- Después de cada merge a `master`, el workflow
  `.github/workflows/sync-master-into-develop.yml` crea/actualiza automáticamente un PR
  `automation/sync-master-into-develop` → `develop`.
- Ese PR de sincronización debe mergearse para que `develop` no quede atrás de `master`.

Si el repositorio no permite que GitHub Actions cree PRs, activar:

**Settings → Actions → General → Workflow permissions → Allow GitHub Actions to create and approve pull requests**.

Si el workflow falla por conflictos, hacer el back-merge manual:

```bash
git checkout develop && git pull
git checkout -b fix/sync-master-into-develop
git merge origin/master
git push -u origin fix/sync-master-into-develop
```

Y abrir PR `fix/sync-master-into-develop` → `develop`.

> Si se usa **Squash** también para `develop` → `master`, el workflow de sincronización es
> obligatorio: sin él, `develop` siempre aparecerá “behind master” por los commits squash,
> aunque el contenido sea idéntico.

## Reglas de protección (GitHub)

Configurar en **Settings → Rules → Rulesets** (o *Branches*):

### `master`
- Require a pull request before merging · **1 aprobación**.
- Dismiss stale approvals · Require conversation resolution.
- Block force pushes · Restrict deletions.
- No permitir push directo (incluido admin).
- Exigir el check **Source branch must be develop** (`.github/workflows/guard-master-source.yml`).
- (Opcional) Require linear history.

### `develop`
- Require a pull request before merging · **1 aprobación**.
- Require conversation resolution.
- Block force pushes · Restrict deletions (**la rama no se elimina**).
- No permitir push directo.

> Los PR hacia `master` deben originarse **exclusivamente** en `develop`. Los PR hacia `develop`
> deben originarse en una rama local (`feature/*`, `fix/*`, `docs/*` o `hotfix/*`).

## Base de datos

- `sql/ddl.sql` es la **línea base congelada**: **no se edita**.
- Cualquier cambio de esquema posterior → nueva migración en `sql/migrations/` (ver su `README.md`).
