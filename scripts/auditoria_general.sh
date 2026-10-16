#!/usr/bin/env bash
# =====================================================================
# auditoria_general.sh — Auditoría general PREVENTIVA de multi_app_v2
# Detecta drift/rutas rotas entre: DDL↔BD, UI/CX↔API, sidebar↔páginas,
# rutas referenciadas en .md, directorios de uploads ↔ drivers de storage.
# =====================================================================
set -u
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
QUIET=0; [ "${1:-}" = "--quiet" ] && QUIET=1
PHP=/opt/lampp/bin/php
MYSQLBIN=/opt/lampp/bin/mysql
SOCK=/opt/lampp/var/mysql/mysql.sock
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); [ $QUIET -eq 0 ] && echo "  ✅ $1"; return 0; }
warn() { [ $QUIET -eq 0 ] && echo "  ⚠️  $1"; return 0; }
bad()  { FAIL=$((FAIL+1)); echo "  ❌ $1"; return 0; }

echo "== Auditoría general multi_app_v2 =="

# 1) php -l
echo "-- 1. Sintaxis PHP"
ERR=0
while IFS= read -r f; do out=$("$PHP" -l "$f" 2>&1); case "$out" in *"No syntax errors"*) ;; *) echo "   SINTAX: $f :: $out"; ERR=1;; esac; done < <(find ui api core cx simulador convenios tienda -name '*.php' 2>/dev/null)
[ $ERR -eq 0 ] && ok "php -l: sin errores" || bad "php -l presenta errores"

# 2) DDL vs BD (tablas)
echo "-- 2. Esquema DDL ↔ BD"
"$MYSQLBIN" -N -u root --socket="$SOCK" multi_app_v2 -e "SHOW TABLES" 2>/tmp/_aud_mysql.err | sort > /tmp/_aud_dbtab.txt
if [ -s /tmp/_aud_mysql.err ]; then warn "mysql: $(head -1 /tmp/_aud_mysql.err)"; fi
grep -oE "CREATE TABLE IF NOT EXISTS [a-z_]+" sql/ddl.sql | awk '{print $6}' | sort -u > /tmp/_aud_ddl.txt
d1=$(comm -23 /tmp/_aud_dbtab.txt /tmp/_aud_ddl.txt | grep -v '^vw_' | tr '\n' ' ')
d2=$(comm -13 /tmp/_aud_dbtab.txt /tmp/_aud_ddl.txt | tr '\n' ' ')
if [ -z "$d1" ] && [ -z "$d2" ]; then ok "DDL ↔ BD en sync"; else bad "BD-no-DDL: $d1 | DDL-no-BD: $d2"; fi

# 3) Sidebar (ui) hrefs → páginas
echo "-- 3. Enlaces del sidebar (ui) → páginas"
grep -rhoE "modules/[a-zA-Z0-9_/]+/pages/[a-zA-Z0-9_]+" ui/views/layouts/sidebar.php | sort -u | while read -r p; do [ -f "ui/$p.php" ] || echo "$p"; done > /tmp/_aud_side.txt
if [ -s /tmp/_aud_side.txt ]; then bad "sidebar roto:"; sed 's/^/     /' /tmp/_aud_side.txt; else ok "sidebar → páginas OK"; fi

# 4) Referencias api/ (UI/CX) → endpoints (literal 'api/…' + concatenados a CX_API='api/portal')
echo "-- 4. Referencias api/ (UI/CX) → endpoints"
grep -rhoE "api/[a-zA-Z0-9_/]+\.php" ui cx --include='*.php' 2>/dev/null | sort -u > /tmp/_aud_api.txt
grep -rhoE "CX_API\s*\+\s*['\"]/[a-zA-Z0-9_]+/[a-zA-Z0-9_]+\.php" cx --include='*.php' 2>/dev/null | grep -oE "/[a-zA-Z0-9_]+/[a-zA-Z0-9_]+\.php" | sed -E 's#^/#api/portal/#' >> /tmp/_aud_api.txt
m=0
while read -r r; do
  [ -f "$r" ] && continue
  echo "   ROTA: $r"; m=1
done < /tmp/_aud_api.txt
[ $m -eq 0 ] && ok "api refs OK" || bad "api refs rotas (ver arriba)"

# 5) Referencias a docs/*.md (ya consolidados)
echo "-- 5. Referencias a 'docs/*.md'"
grep -rlnE "docs/(design/)?[0-9a-zA-Z_]+\.md" --include='*.md' --include='*.php' . 2>/dev/null | grep -vE "/graphs/|/vendor/|/uploads/|graphify-out" > /tmp/_aud_docs.txt
if [ -s /tmp/_aud_docs.txt ]; then warn "menciones a 'docs/*.md' (históricas):"; sed 's/^/     /' /tmp/_aud_docs.txt; else ok "sin refs a docs/"; fi

# 6) uploads/ dirs ↔ STORAGE_DRIVERS
echo "-- 6. uploads/ ↔ STORAGE_DRIVERS"
drivers=$(grep -oE "^STORAGE_DRIVERS=.*" .env 2>/dev/null | cut -d= -f2)
miss=0
for dir in uploads/*/; do
  b="$(basename "$dir")"
  case "$drivers" in *$b*) ;; *) echo "   uploads/$b sin driver en STORAGE_DRIVERS"; miss=1;; esac
done
[ $miss -eq 0 ] && ok "uploads dirs cubiertos por STORAGE_DRIVERS" || bad "uploads dirs sin driver (o .env incompleto)"

echo "== Resultado: $PASS ✅ / $FAIL ❌ =="
[ $FAIL -eq 0 ] || exit 1