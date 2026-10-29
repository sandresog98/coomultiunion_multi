# Flujo de ramas, merges y sincronización

**Última actualización:** 2026-10-29  
**Aplica a:** repositorio `sandresog98/coomultiunion_multi`

Esta guía documenta el flujo obligatorio de ramas, la estrategia de merge, los workflows de protección y el procedimiento para mantener `develop` y `master` sincronizados.

---

## 1. Regla de oro

**Nunca se hace merge directo a `master`.**

El único flujo permitido es:

```text
rama/*  ──PR──▶  develop  ──PR──▶  master
```

Reglas:

- Ningún `feature/*`, `fix/*`, `docs/*`, `hotfix/*` o rama personal puede abrir PR directo a `master`.
- `hotfix/*` no es excepción: entra por PR a `develop` y luego `develop` pasa por PR a `master`.
- No se permite push directo ni merge local a `master`.
- `master` solo recibe cambios desde `develop` mediante PR aprobado.
- Si un PR aparece con base `master` y no viene de `develop`, debe cerrarse y recrearse contra `develop`.

---

## 2. Ramas

| Rama | Uso | Regla |
|---|---|---|
| `master` | Producción. | Solo recibe PR desde `develop`. |
| `develop` | Integración y fuente del servidor de test. | Recibe PRs de ramas locales. No se elimina. |
| `feature/*` | Nuevas funcionalidades. | PR a `develop`. |
| `fix/*` | Correcciones no urgentes. | PR a `develop`. |
| `docs/*` | Documentación. | PR a `develop`. |
| `hotfix/*` | Corrección urgente. | PR a `develop`; luego `develop` a `master`. |

```text
feature/*  ──PR──▶  develop  ──PR──▶  master
fix/*      ──PR──▶  develop
docs/*     ──PR──▶  develop
hotfix/*   ──PR──▶  develop  ──PR──▶  master
```

---

## 3. Estrategia de merge

| Origen → Destino | Estrategia | Motivo |
|---|---|---|
| `feature/*`, `fix/*`, `docs/*`, `hotfix/*` → `develop` | **Squash and merge** | Mantener `develop` limpio y un commit por PR. |
| `develop` → `master` | **Create a merge commit** | Conservar los commits de `develop` como ancestros de `master`. |
| `master` → `develop` (sincronización) | **Create a merge commit** | Incorporar la historia de `master` en `develop`. |

Si `master` tiene `Require linear history` activo, `Create a merge commit` no estará disponible. En ese caso:

- usar **Squash and merge** para `develop` → `master`,
- y confiar en el workflow de sincronización para el back-merge.

**No usar Rebase and merge** para `develop` → `master` ni para el PR de sincronización, porque no conserva la relación de ancestros.

---

## 4. Workflows de GitHub Actions

### 4.1. `guard-master-source.yml`

Archivo:

```text
.github/workflows/guard-master-source.yml
```

Propósito: impedir PRs a `master` que no vengan de `develop`.

Se ejecuta en:

```yaml
on:
  pull_request:
    branches: [master]
```

Valida:

1. Que el PR venga del repositorio oficial, no de un fork.
2. Que la rama de origen sea exactamente `develop`.

Nombre del check:

```text
Source branch must be develop
```

Ese check debe configurarse como **obligatorio** en el ruleset de `master`.

### 4.2. `sync-master-into-develop.yml`

Archivo:

```text
.github/workflows/sync-master-into-develop.yml
```

Propósito: después de un merge a `master`, hacer back-merge automático hacia `develop`.

Se ejecuta en:

```yaml
on:
  push:
    branches: [master]
  workflow_dispatch:
```

Proceso:

1. Hace checkout de `develop`.
2. Cuenta cuántos commits de `master` no están en `develop`.
3. Si no hay commits pendientes, termina sin hacer nada.
4. Si hay commits pendientes:
   - crea/actualiza la rama `automation/sync-master-into-develop`,
   - mergea `origin/master`,
   - la sube al remoto,
   - crea o actualiza un PR `automation/sync-master-into-develop` → `develop`.
5. Ese PR debe mergearse con **Create a merge commit**.

---

## 5. Configuración de GitHub

### 5.1. Pull requests

**Settings → General → Pull requests**

Activar:

- **Allow merge commits**
- **Allow squash merging**

Opcional:

- **Allow rebase merging** (no se usa para `develop`/`master`).

### 5.2. Permisos de Actions

**Settings → Actions → General → Workflow permissions**

Activar:

```text
Allow GitHub Actions to create and approve pull requests
```

Sin este permiso, `sync-master-into-develop.yml` falla en el paso:

```text
Create or update pull request
```

El error típico es un `403` al ejecutar `gh pr create`.

### 5.3. Ruleset de `master`

**Settings → Rules → Rulesets**

Crear o editar el ruleset que aplica a `master`:

- **Target:** `master` (`~DEFAULT_BRANCH` si `master` es la default).
- **Enforcement:** Active.
- **Bypass list:** vacía.
- **Require a pull request before merging:** activo.
- **Required approvals:** 1.
- **Dismiss stale approvals:** activo.
- **Require conversation resolution:** activo.
- **Require status checks to pass:** activo.
  - Check obligatorio: `Source branch must be develop`.
- **Block force pushes:** activo.
- **Restrict deletions:** activo.
- **Require linear history:** opcional. Si se activa, no se podrá usar merge commit en `master`.

### 5.4. Ruleset de `develop`

- **Target:** `develop`.
- **Require a pull request before merging:** activo.
- **Required approvals:** 1.
- **Require conversation resolution:** activo.
- **Block force pushes:** activo.
- **Restrict deletions:** activo.
- **Require linear history:** **desactivado**. Es necesario para permitir el merge commit del PR de sincronización.
- **Bypass list:** vacía.

---

## 6. Runbook: cambio normal

### 6.1. Crear la rama

```bash
git checkout develop
git pull --ff-only origin develop
git checkout -b feature/mi-cambio
```

### 6.2. Desarrollar, commit y push

```bash
git add .
git commit -m "feat: descripción"
git push -u origin feature/mi-cambio
```

### 6.3. PR a develop

- Base: `develop`
- Compare: `feature/mi-cambio`
- Merge: **Squash and merge**
- Requiere 1 aprobación.

### 6.4. Release: PR develop → master

- Base: `master`
- Compare: `develop`
- Esperar el check `Source branch must be develop`.
- Merge: **Create a merge commit** si está disponible; si no, **Squash and merge**.

### 6.5. Sincronización automática

Después del merge a `master`, el workflow `Sync master into develop` debe crear un PR:

```text
automation/sync-master-into-develop → develop
```

Mergearlo con **Create a merge commit**.

### 6.6. Verificación

```bash
git fetch origin --prune
git rev-list --left-right --count origin/develop...origin/master
git diff --quiet origin/develop origin/master && echo "contenido idéntico"
```

Resultado esperado:

```text
N    0
contenido idéntico
```

- `N`: commits que `develop` tiene por delante.
- `0`: `develop` no está atrás de `master`.
- Contenido idéntico: no hay diferencias de archivos.

---

## 7. Runbook: hotfix

Un hotfix sigue el mismo flujo:

```bash
git checkout develop
git pull --ff-only origin develop
git checkout -b hotfix/descripcion-corta
# corregir
git add .
git commit -m "fix: descripción"
git push -u origin hotfix/descripcion-corta
```

1. PR `hotfix/*` → `develop` con **Squash and merge**.
2. Validar en test.
3. PR `develop` → `master` con **Create a merge commit** (o squash si linear history está activo).
4. Mergear el PR de sincronización que genere el workflow.

**No se abre PR de `hotfix/*` directo a `master`.**

---

## 8. Por qué `develop` aparece “1 commit ahead”

Después de cada release ocurre esto:

1. PR `develop` → `master` se mergea con merge commit.
2. El workflow crea el PR `master` → `develop`.
3. Ese PR se mergea con merge commit en `develop`.

El merge commit del back-merge solo existe en `develop`; `master` no lo necesita. Por eso `develop` queda **1 commit adelante y 0 atrás**.

Ejemplo real:

```text
#33  develop → master  → merge commit 2b4c61b
#35  master → develop  → merge commit b053081
```

Estado:

```text
develop: 1 ahead, 0 behind master
contenido: idéntico
```

Eso es correcto. No se debe resetear `develop` a `master` para “arreglar” el contador: se perdería el commit de sincronización y se reescribiría historia.

---

## 9. Problemas conocidos

### 9.1. No aparece `Source branch must be develop` en los rulesets

Causas:

- El workflow nunca se ha ejecutado en un PR hacia `master`.
- El PR se creó antes de que el workflow existiera en `master`.
- GitHub Actions está deshabilitado.
- El nombre del check cambió.

Solución:

1. Crear un PR nuevo `develop` → `master`.
2. Esperar a que corra **Guard PR to master**.
3. Volver al ruleset y buscar:
   - `Source branch must be develop`, o
   - `Guard PR to master / Source branch must be develop`.

### 9.2. El workflow de sync falla en `Create or update pull request`

Causa: falta el permiso de Actions para crear PRs.

Solución:

**Settings → Actions → General → Workflow permissions → Allow GitHub Actions to create and approve pull requests**

Luego reejecutar el workflow.

### 9.3. No aparece `Create a merge commit`

Causa: `Require linear history` está activo en la rama base.

Solución:

- Desactivarlo en `develop` para el PR de sincronización.
- En `master`, si se mantiene, usar **Squash and merge** para `develop` → `master`.

### 9.4. El PR de sincronización tiene conflictos

El workflow fallará. Resolver manualmente:

```bash
git checkout develop
git pull --ff-only origin develop
git checkout -b fix/sync-master-into-develop
git merge origin/master
# resolver conflictos
git push -u origin fix/sync-master-into-develop
```

Abrir PR `fix/sync-master-into-develop` → `develop` y mergear con merge commit.

### 9.5. Un PR a `master` desde una rama distinta

El check `Source branch must be develop` falla y el merge queda bloqueado. Cerrar el PR y recrearlo contra `develop`.

---

## 10. Mantenimiento

- Los workflows deben existir en `master` para ejecutarse en PRs hacia `master`.
- Después de cambiar un workflow, mergearlo primero a `develop` y luego a `master`.
- No borrar `develop`.
- No hacer force-push a `master` ni `develop`.
- Revisar periódicamente que la lista de bypass de los rulesets siga vacía.
- Si se cambia la estrategia de merge del repositorio, actualizar esta guía y `CONTRIBUTING.md`.
