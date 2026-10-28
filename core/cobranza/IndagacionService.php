<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/Storage/Storage.php';

/**
 * Indagaciones de cobranza (B6) con adjuntos (servidos por API autenticada).
 */
final class IndagacionService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function listarPorCedula(string $cedula, int $limit = 50): array
    {
        $st = $this->db->prepare(
            "SELECT i.*, u.nombre_completo AS usuario_nombre
             FROM cobranza_indagaciones i
             LEFT JOIN usuarios u ON u.id = i.creado_por
             WHERE i.asociado_cedula = ?
             ORDER BY i.fecha DESC, i.id DESC LIMIT ?"
        );
        $st->bindValue(1, $cedula);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        if (!$rows) {
            return [];
        }

        // Adjuntos en una sola consulta (evita N+1 por indagación).
        $ids = array_map(static fn(array $r): int => (int) $r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stAdj = $this->db->prepare(
            "SELECT * FROM cobranza_indagaciones_adjuntos
             WHERE indagacion_id IN ($in)
             ORDER BY fecha_subida, id"
        );
        $stAdj->execute($ids);
        $adjuntos = [];
        foreach ($stAdj->fetchAll() as $a) {
            $adjuntos[(int) $a['indagacion_id']][] = $a;
        }
        foreach ($rows as &$r) {
            $r['adjuntos'] = $adjuntos[(int) $r['id']] ?? [];
        }
        unset($r);
        return $rows;
    }

    public function crear(array $d, ?array $actor = null): int
    {
        $ced = trim((string) ($d['asociado_cedula'] ?? ''));
        if ($ced === '') throw new DomainException('Falta el asociado.');
        $tipoInd = trim((string) ($d['tipo_indagacion'] ?? ''));
        if ($tipoInd === '') throw new DomainException('Falta el tipo de indagación.');
        $fecha = trim((string) ($d['fecha'] ?? ''));
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new DomainException('Fecha inválida.');
        $tipoOrig = trim((string) ($d['tipo_origen'] ?? 'credito'));
        $tipoOrig = in_array($tipoOrig, ['credito', 'aportes'], true) ? $tipoOrig : 'credito';

        $this->db->prepare(
            "INSERT INTO cobranza_indagaciones (asociado_cedula, tipo_origen, tipo_indagacion, fecha, creado_por)
             VALUES (?,?,?,?,?)"
        )->execute([$ced, $tipoOrig, $tipoInd, $fecha, $actor['id'] ?? null]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'cobranza.indagacion', $actor['id'] ?? null, "Indagación #$id para $ced");
        return $id;
    }

    public function editar(int $id, array $d, ?array $actor = null): void
    {
        $this->verificarDueno($id, $actor);
        $tipoInd = trim((string) ($d['tipo_indagacion'] ?? ''));
        if ($tipoInd === '') throw new DomainException('Falta el tipo de indagación.');
        $fecha = trim((string) ($d['fecha'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new DomainException('Fecha inválida.');
        $this->db->prepare("UPDATE cobranza_indagaciones SET tipo_indagacion=?, fecha=? WHERE id=?")
            ->execute([$tipoInd, $fecha, $id]);
        Logger::log('editar', 'cobranza.indagacion', $actor['id'] ?? null, "Editada indagación #$id");
    }

    public function eliminar(int $id, ?array $actor = null): void
    {
        $this->verificarDueno($id, $actor);
        foreach ($this->adjuntos($id) as $a) { $this->eliminarAdjunto((int) $a['id'], $actor); }
        $this->db->prepare("DELETE FROM cobranza_indagaciones WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'cobranza.indagacion', $actor['id'] ?? null, "Eliminada indagación #$id", null, null, 'warning');
    }

    /** Solo el creador puede editar/eliminar (regla B6). */
    private function verificarDueno(int $id, ?array $actor): void
    {
        $st = $this->db->prepare("SELECT creado_por FROM cobranza_indagaciones WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) throw new DomainException('Indagación no encontrada.');
        if ((int) ($r['creado_por'] ?? 0) !== (int) ($actor['id'] ?? -1)) {
            throw new DomainException('Solo el creador puede modificar/eliminar esta indagación.');
        }
    }

    public function adjuntos(int $indagacionId): array
    {
        $st = $this->db->prepare("SELECT * FROM cobranza_indagaciones_adjuntos WHERE indagacion_id = ? ORDER BY fecha_subida");
        $st->execute([$indagacionId]);
        return $st->fetchAll();
    }

    public function agregarAdjunto(int $indagacionId, string $nombre, string $ruta, ?string $mime = null, ?int $size = null, ?array $actor = null): int
    {
        $this->verificarDueno($indagacionId, $actor);
        $this->db->prepare(
            "INSERT INTO cobranza_indagaciones_adjuntos (indagacion_id, nombre_archivo, ruta_archivo, tipo_mime, tamaño_archivo)
             VALUES (?,?,?,?,?)"
        )->execute([$indagacionId, $nombre, $ruta, $mime, $size]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'cobranza.indagacion_adjunto', $actor['id'] ?? null, "Adjunto a indagación #$indagacionId");
        return $id;
    }

    public function eliminarAdjunto(int $adjuntoId, ?array $actor = null): void
    {
        $st = $this->db->prepare("SELECT id, indagacion_id, ruta_archivo FROM cobranza_indagaciones_adjuntos WHERE id = ?");
        $st->execute([$adjuntoId]);
        $r = $st->fetch();
        if (!$r) throw new DomainException('Adjunto no encontrado.');
        $this->verificarDueno((int) $r['indagacion_id'], $actor);
        Storage::for('cobranza')->delete('cobranza/' . $r['ruta_archivo']);
        $this->db->prepare("DELETE FROM cobranza_indagaciones_adjuntos WHERE id = ?")->execute([$adjuntoId]);
        Logger::log('eliminar', 'cobranza.indagacion_adjunto', $actor['id'] ?? null, "Adjunto #$adjuntoId", null, null, 'warning');
    }

    public function getAdjuntoPorId(int $adjuntoId): ?array
    {
        $st = $this->db->prepare("SELECT * FROM cobranza_indagaciones_adjuntos WHERE id = ?");
        $st->execute([$adjuntoId]);
        return $st->fetch() ?: null;
    }

    private function verificar(int $id): void
    {
        $st = $this->db->prepare("SELECT id FROM cobranza_indagaciones WHERE id = ?");
        $st->execute([$id]);
        if (!$st->fetch()) throw new DomainException('Indagación no encontrada.');
    }
}