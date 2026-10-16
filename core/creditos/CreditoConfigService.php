<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Catálogos configurables del módulo Créditos: líneas, secciones, campos,
 * documentos y "Aceptación y Autorizaciones". También renderiza por etapa/canal.
 */
final class CreditoConfigService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    // ── Líneas de crédito ────────────────────────────────────────────────
    public function lineas(bool $soloFabrica = false, bool $soloActivas = true): array
    {
        $w = [];
        if ($soloActivas) $w[] = 'estado_activo = 1';
        if ($soloFabrica) $w[] = 'mostrar_en_fabrica = 1';
        $w = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return $this->db->query("SELECT * FROM credito_lineas $w ORDER BY nombre_credito")->fetchAll();
    }
    public function lineaCrear(array $d, ?array $actor = null): int
    {
        $nombre = trim((string) ($d['nombre_credito'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre de la línea es obligatorio.');
        $this->db->prepare(
            "INSERT INTO credito_lineas (nombre_credito, limite_meses, tasa, seguro_vida, seguro_deudores, estado_activo, mostrar_en_fabrica, creado_por)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([
            $nombre, (int) ($d['limite_meses'] ?? 0) ?: null,
            (float) ($d['tasa'] ?? 0), (float) ($d['seguro_vida'] ?? 0), (float) ($d['seguro_deudores'] ?? 0),
            (int) (($d['estado_activo'] ?? true) ? 1 : 0), (int) (($d['mostrar_en_fabrica'] ?? true) ? 1 : 0),
            $actor['id'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'creditos.lineas', $actor['id'] ?? null, "Creada línea '$nombre' #$id");
        return $id;
    }
    public function lineaEditar(int $id, array $d, ?array $actor = null): void
    {
        $sets = []; $vals = [];
        foreach (['nombre_credito' => 'nombre_credito', 'tasa' => 'tasa', 'seguro_vida' => 'seguro_vida', 'seguro_deudores' => 'seguro_deudores'] as $k => $c) {
            if (array_key_exists($k, $d)) { $sets[] = "$c=?"; $vals[] = trim((string) $d[$k]); }
        }
        foreach (['limite_meses', 'estado_activo', 'mostrar_en_fabrica'] as $k) {
            if (array_key_exists($k, $d)) { $sets[] = "$k=?"; $vals[] = (int) $d[$k]; }
        }
        if ($sets) { $vals[] = $id; $this->db->prepare("UPDATE credito_lineas SET " . implode(',', $sets) . ", fecha_actualizacion=NOW() WHERE id=?")->execute($vals); }
        Logger::log('editar', 'creditos.lineas', $actor['id'] ?? null, "Editada línea #$id");
    }

    // ── Secciones ────────────────────────────────────────────────────────
    public function secciones(): array
    {
        return $this->db->query("SELECT * FROM credito_config_seccion ORDER BY orden, id")->fetchAll();
    }
    public function seccionCrear(array $d, ?array $actor = null): int
    {
        $codigo = strtolower(trim((string) ($d['codigo'] ?? '')));
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($codigo === '' || $nombre === '') throw new DomainException('Código y nombre son obligatorios.');
        $st = $this->db->prepare("SELECT id FROM credito_config_seccion WHERE codigo=?");
        $st->execute([$codigo]);
        if ($st->fetch()) throw new DomainException('Ya existe una sección con ese código.');
        $this->db->prepare(
            "INSERT INTO credito_config_seccion (codigo, nombre, ocasion, visible_en, orden, estado_activo) VALUES (?,?,?,?,?,?)"
        )->execute([$codigo, $nombre, trim((string) ($d['ocasion'] ?? 'creacion')), json_encode($d['visible_en'] ?? ['staff', 'cx']), (int) ($d['orden'] ?? 100), (int) (($d['estado_activo'] ?? true) ? 1 : 0)]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'creditos.config', $actor['id'] ?? null, "Creada sección '$codigo' #$id");
        return $id;
    }
    public function seccionEditar(int $id, array $d, ?array $actor = null): void
    {
        $sql = []; $vals = [];
        foreach (['nombre', 'ocasion', 'orden', 'estado_activo'] as $k) {
            if (array_key_exists($k, $d)) { $sql[] = "$k=?"; $vals[] = in_array($k, ['orden', 'estado_activo'], true) ? (int) $d[$k] : $d[$k]; }
        }
        if (array_key_exists('visible_en', $d) && is_array($d['visible_en'])) { $sql[] = "visible_en=?"; $vals[] = json_encode($d['visible_en']); }
        if ($sql) { $vals[] = $id; $this->db->prepare("UPDATE credito_config_seccion SET " . implode(',', $sql) . " WHERE id=?")->execute($vals); }
        Logger::log('editar', 'creditos.config', $actor['id'] ?? null, "Editada sección #$id");
    }
    public function seccionEliminar(int $id, ?array $actor = null): void
    {
        $this->db->prepare("DELETE FROM credito_config_seccion WHERE id=?")->execute([$id]);
        Logger::log('eliminar', 'creditos.config', $actor['id'] ?? null, "Eliminada sección #$id", null, null, 'warning');
    }

    // ── Campos dinámicos ────────────────────────────────────────────────
    public function campos(int $seccionId): array
    {
        $st = $this->db->prepare("SELECT * FROM credito_config_campo WHERE seccion_id=? ORDER BY orden, id");
        $st->execute([$seccionId]);
        return $st->fetchAll();
    }
    public function campoCrear(int $seccionId, array $d, ?array $actor = null): int
    {
        $campo = strtolower(trim((string) ($d['campo'] ?? '')));
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($campo === '' || $nombre === '') throw new DomainException('Campo (clave) y nombre son obligatorios.');
        // opciones y para_tipo: NULL cuando no se proveen (no guardar la cadena 'null').
        $opciones = (isset($d['opciones']) && is_array($d['opciones']) && $d['opciones'] !== []) ? json_encode($d['opciones']) : null;
        $para = (isset($d['para_tipo_solicitante']) && is_array($d['para_tipo_solicitante'])) ? json_encode($d['para_tipo_solicitante']) : null;
        $this->db->prepare(
            "INSERT INTO credito_config_campo (seccion_id, campo, nombre, tipo, opciones, obligatorio, para_tipo_solicitante, visible_en, orden, estado_activo)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $seccionId, $campo, $nombre, trim((string) ($d['tipo'] ?? 'texto')),
            $opciones, (int) (($d['obligatorio'] ?? false) ? 1 : 0),
            $para, json_encode($d['visible_en'] ?? ['staff', 'cx']),
            (int) ($d['orden'] ?? 100), (int) (($d['estado_activo'] ?? true) ? 1 : 0),
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'creditos.config', $actor['id'] ?? null, "Creado campo '$campo' #$id");
        return $id;
    }
    public function campoEditar(int $id, array $d, ?array $actor = null): void
    {
        $sql = []; $vals = [];
        foreach (['nombre' => 'nombre', 'tipo' => 'tipo', 'obligatorio' => 'obligatorio', 'orden' => 'orden', 'estado_activo' => 'estado_activo'] as $k => $c) {
            if (array_key_exists($k, $d)) { $sql[] = "$c=?"; $vals[] = $k === 'obligatorio' || $k === 'estado_activo' ? (int) $d[$k] : $d[$k]; }
        }
        foreach (['opciones' => 'opciones', 'para_tipo_solicitante' => 'para_tipo_solicitante', 'visible_en' => 'visible_en'] as $k => $c) {
            if (array_key_exists($k, $d) && is_array($d[$k])) { $sql[] = "$c=?"; $vals[] = json_encode($d[$k]); }
        }
        if ($sql) { $vals[] = $id; $this->db->prepare("UPDATE credito_config_campo SET " . implode(',', $sql) . " WHERE id=?")->execute($vals); }
        Logger::log('editar', 'creditos.config', $actor['id'] ?? null, "Editado campo #$id");
    }
    public function campoEliminar(int $id, ?array $actor = null): void
    {
        $this->db->prepare("DELETE FROM credito_campos_valores WHERE config_campo_id=?")->execute([$id]);
        $this->db->prepare("DELETE FROM credito_config_campo WHERE id=?")->execute([$id]);
        Logger::log('eliminar', 'creditos.config', $actor['id'] ?? null, "Eliminado campo #$id", null, null, 'warning');
    }

    // ── Documentos requeridos ────────────────────────────────────────────
    public function documentos(int $seccionId): array
    {
        $st = $this->db->prepare("SELECT * FROM credito_config_documento WHERE seccion_id=? ORDER BY orden, id");
        $st->execute([$seccionId]);
        return $st->fetchAll();
    }
    public function documentoCrear(int $seccionId, array $d, ?array $actor = null): int
    {
        $tipo = strtolower(trim((string) ($d['tipo_documento'] ?? '')));
        $nombre = trim((string) ($d['nombre_mostrar'] ?? ''));
        if ($tipo === '' || $nombre === '') throw new DomainException('Tipo y nombre del documento son obligatorios.');
        $this->db->prepare(
            "INSERT INTO credito_config_documento (seccion_id, tipo_documento, nombre_mostrar, descripcion, es_obligatorio, es_opcional, es_trato_especial, para_tipo_solicitante, visible_en, orden, estado_activo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $seccionId, $tipo, $nombre, trim((string) ($d['descripcion'] ?? '')),
            (int) (($d['es_obligatorio'] ?? true) ? 1 : 0), (int) (($d['es_opcional'] ?? false) ? 1 : 0), (int) (($d['es_trato_especial'] ?? false) ? 1 : 0),
            json_encode($d['para_tipo_solicitante'] ?? null), json_encode($d['visible_en'] ?? ['staff', 'cx']),
            (int) ($d['orden'] ?? 100), (int) (($d['estado_activo'] ?? true) ? 1 : 0),
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'creditos.config', $actor['id'] ?? null, "Creado requerimiento doc '$tipo' #$id");
        return $id;
    }
    public function documentoEditar(int $id, array $d, ?array $actor = null): void
    {
        $sql = []; $vals = [];
        foreach (['nombre_mostrar' => 'nombre_mostrar', 'descripcion' => 'descripcion', 'es_obligatorio' => 'es_obligatorio', 'es_opcional' => 'es_opcional', 'es_trato_especial' => 'es_trato_especial', 'orden' => 'orden', 'estado_activo' => 'estado_activo'] as $k => $c) {
            if (array_key_exists($k, $d)) { $sql[] = "$c=?"; $vals[] = in_array($k, ['es_obligatorio', 'es_opcional', 'es_trato_especial', 'orden', 'estado_activo'], true) ? (int) $d[$k] : $d[$k]; }
        }
        foreach (['para_tipo_solicitante' => 'para_tipo_solicitante', 'visible_en' => 'visible_en'] as $k => $c) {
            if (array_key_exists($k, $d) && is_array($d[$k])) { $sql[] = "$c=?"; $vals[] = json_encode($d[$k]); }
        }
        if ($sql) { $vals[] = $id; $this->db->prepare("UPDATE credito_config_documento SET " . implode(',', $sql) . " WHERE id=?")->execute($vals); }
        Logger::log('editar', 'creditos.config', $actor['id'] ?? null, "Editado req documento #$id");
    }
    public function documentoEliminar(int $id, ?array $actor = null): void
    {
        $this->db->prepare("DELETE FROM credito_config_documento WHERE id=?")->execute([$id]);
        Logger::log('eliminar', 'creditos.config', $actor['id'] ?? null, "Eliminado req documento #$id", null, null, 'warning');
    }

    // ── Aceptación y Autorizaciones ──────────────────────────────────────
    public function aceptaciones(): array
    {
        return $this->db->query("SELECT * FROM credito_config_aceptacion ORDER BY orden, id")->fetchAll();
    }
    public function aceptacionCrear(array $d, ?array $actor = null): int
    {
        $clave = strtolower(trim((string) ($d['clave'] ?? '')));
        if ($clave === '') $clave = 'aceptacion_' . mt_rand(100, 999);
        $this->db->prepare(
            "INSERT INTO credito_config_aceptacion (clave, tipo, titulo, texto, obligatorio, visible_en, orden, estado_activo) VALUES (?,?,?,?,?,?,?,?)"
        )->execute([
            $clave, trim((string) ($d['tipo'] ?? 'aceptacion')), trim((string) ($d['titulo'] ?? '')),
            (string) ($d['texto'] ?? ''), (int) (($d['obligatorio'] ?? false) ? 1 : 0),
            json_encode($d['visible_en'] ?? ['staff', 'cx']), (int) ($d['orden'] ?? 100), (int) (($d['estado_activo'] ?? true) ? 1 : 0),
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'creditos.config', $actor['id'] ?? null, "Creada aceptación '$clave' #$id");
        return $id;
    }
    public function aceptacionEditar(int $id, array $d, ?array $actor = null): void
    {
        $sql = []; $vals = [];
        foreach (['tipo' => 'tipo', 'titulo' => 'titulo', 'texto' => 'texto', 'obligatorio' => 'obligatorio', 'orden' => 'orden', 'estado_activo' => 'estado_activo'] as $k => $c) {
            if (array_key_exists($k, $d)) { $sql[] = "$c=?"; $vals[] = in_array($k, ['obligatorio', 'orden', 'estado_activo'], true) ? (int) $d[$k] : $d[$k]; }
        }
        if (array_key_exists('visible_en', $d) && is_array($d['visible_en'])) { $sql[] = "visible_en=?"; $vals[] = json_encode($d['visible_en']); }
        if ($sql) { $vals[] = $id; $this->db->prepare("UPDATE credito_config_aceptacion SET " . implode(',', $sql) . " WHERE id=?")->execute($vals); }
        Logger::log('editar', 'creditos.config', $actor['id'] ?? null, "Editada aceptación #$id");
    }
    public function aceptacionEliminar(int $id, ?array $actor = null): void
    {
        $this->db->prepare("DELETE FROM credito_campos_valores WHERE config_aceptacion_id=?")->execute([$id]);
        $this->db->prepare("DELETE FROM credito_config_aceptacion WHERE id=?")->execute([$id]);
        Logger::log('eliminar', 'creditos.config', $actor['id'] ?? null, "Eliminada aceptación #$id", null, null, 'warning');
    }

    /**
     * Render de una sección (por su `ocasion`/etapa o código) para un canal y tipo de solicitante:
     * devuelve los campos y documentos que aplican.
     */
    public function renderSeccion(string|int $ref, string $canal = 'staff', ?string $tipoSolicitante = null, string $modo = 'ocasion'): array
    {
        if ($modo === 'ocasion') {
            $st = $this->db->prepare("SELECT * FROM credito_config_seccion WHERE ocasion=? AND estado_activo=1 ORDER BY orden, id");
            $st->execute([$ref]);
        } else {
            $st = $this->db->prepare("SELECT * FROM credito_config_seccion WHERE (codigo=? OR id=?) AND estado_activo=1");
            $st->execute([(string) $ref, (int) $ref]);
        }
        $secciones = $st->fetchAll();
        $out = [];
        foreach ($secciones as $sec) {
            $vis = json_decode((string) $sec['visible_en'], true) ?: ['staff', 'cx'];
            if (!in_array($canal, $vis, true)) continue;

            $cam = $this->db->prepare("SELECT * FROM credito_config_campo WHERE seccion_id=? AND estado_activo=1 ORDER BY orden, id");
            $cam->execute([$sec['id']]);
            $campos = array_filter($cam->fetchAll(), fn($c) => self::aplica($c, $canal, $tipoSolicitante));

            $doc = $this->db->prepare("SELECT * FROM credito_config_documento WHERE seccion_id=? AND estado_activo=1 ORDER BY orden, id");
            $doc->execute([$sec['id']]);
            $documentos = array_filter($doc->fetchAll(), fn($d) => self::aplica($d, $canal, $tipoSolicitante));

            $out[] = [
                'seccion' => ['id' => $sec['id'], 'codigo' => $sec['codigo'], 'nombre' => $sec['nombre'], 'ocasion' => $sec['ocasion']],
                'campos' => array_values($campos),
                'documentos' => array_values($documentos),
            ];
        }
        return $out;
    }

    /**
     * Devuelve todas las secciones activas (por etapa) con sus campos y
     * documentos ya filtrados por canal y tipo de solicitante.
     */
    public function renderSeccionesAll(string $canal = 'staff', ?string $tipoSolicitante = null): array
    {
        $secciones = $this->db->query("SELECT * FROM credito_config_seccion WHERE estado_activo=1 ORDER BY orden, id")->fetchAll();
        $out = [];
        foreach ($secciones as $sec) {
            $vis = json_decode((string) $sec['visible_en'], true) ?: ['staff', 'cx'];
            if (!in_array($canal, $vis, true)) continue;

            $cam = $this->db->prepare("SELECT * FROM credito_config_campo WHERE seccion_id=? AND estado_activo=1 ORDER BY orden, id");
            $cam->execute([$sec['id']]);
            $campos = array_filter($cam->fetchAll(), fn($c) => self::aplica($c, $canal, $tipoSolicitante));

            $doc = $this->db->prepare("SELECT * FROM credito_config_documento WHERE seccion_id=? AND estado_activo=1 ORDER BY orden, id");
            $doc->execute([$sec['id']]);
            $documentos = array_filter($doc->fetchAll(), fn($d) => self::aplica($d, $canal, $tipoSolicitante));

            $out[] = [
                'seccion' => ['id' => $sec['id'], 'codigo' => $sec['codigo'], 'nombre' => $sec['nombre'], 'ocasion' => $sec['ocasion']],
                'campos' => array_values($campos),
                'documentos' => array_values($documentos),
            ];
        }
        return $out;
    }

    public function renderAceptaciones(string $canal = 'staff'): array
    {
        $rows = $this->db->query("SELECT * FROM credito_config_aceptacion WHERE estado_activo=1 ORDER BY orden, id")->fetchAll();
        return array_values(array_filter($rows, fn($a) => self::aplica($a, $canal, null)));
    }

    private static function aplica(array $ent, string $canal, ?string $tipoSolicitante): bool
    {
        $vis = json_decode((string) ($ent['visible_en'] ?? '["staff","cx"]'), true) ?: [];
        if ($vis && !in_array($canal, $vis, true)) return false;
        if ($tipoSolicitante !== null) {
            // Solo campos/documentos traen para_tipo_solicitante (secciones/aceptaciones no: aplican siempre).
            if (array_key_exists('para_tipo_solicitante', $ent)) {
                $p = json_decode((string) ($ent['para_tipo_solicitante'] ?? 'null'), true);
                // Semántica: lista vacía o NULL => no aplica a ningún tipo.
                if (!is_array($p) || !in_array($tipoSolicitante, array_map('strval', $p), true)) {
                    return false;
                }
            }
        }
        return true;
    }
}