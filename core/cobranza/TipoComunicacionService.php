<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Catálogo de tipos/estados de comunicación de cobranza (configurable).
 */
final class TipoComunicacionService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function all(bool $soloActivos = true): array
    {
        $w = $soloActivos ? 'WHERE activo = 1' : '';
        return $this->db->query("SELECT id, tipo, estado, label, tipo_origen, activo FROM cobranza_tipos_comunicacion $w ORDER BY tipo, estado")->fetchAll();
    }

    public function listar(string $q = '', string $orderBy = 'tipo', string $orderDir = 'ASC', int $limit = 50, int $offset = 0): array
    {
        $ORDER_COLS = ['tipo', 'estado', 'label', 'tipo_origen', 'activo'];
        $orderBy = in_array($orderBy, $ORDER_COLS, true) ? $orderBy : 'tipo';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = 'WHERE label LIKE ? OR tipo LIKE ? OR estado LIKE ?';
            $l = '%' . $q . '%';
            $params = [$l, $l, $l];
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM cobranza_tipos_comunicacion $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $this->db->prepare("SELECT * FROM cobranza_tipos_comunicacion $where ORDER BY `$orderBy` $orderDir LIMIT ? OFFSET ?");
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function crear(array $d, ?array $actor = null): int
    {
        $tipo = strtolower(trim((string) ($d['tipo'] ?? '')));
        $estado = strtolower(trim((string) ($d['estado'] ?? '')));
        $label = trim((string) ($d['label'] ?? ''));
        if ($tipo === '' || $estado === '' || $label === '') throw new DomainException('Faltan tipo, estado o label.');
        $tipoOrigen = trim((string) ($d['tipo_origen'] ?? 'ambos'));
        if (!in_array($tipoOrigen, ['credito', 'aportes', 'ambos'], true)) $tipoOrigen = 'ambos';
        $this->db->prepare(
            "INSERT INTO cobranza_tipos_comunicacion (tipo, estado, label, tipo_origen, activo, creado_por)
             VALUES (?,?,?,?,?,?)"
        )->execute([$tipo, $estado, $label, $tipoOrigen, (int) (($d['activo'] ?? true) ? 1 : 0), $actor['id'] ?? null]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'cobranza.tipos', $actor['id'] ?? null, "Creado tipo de comunicación '$tipo/$estado'");
        return $id;
    }

    public function editar(int $id, array $d, ?array $actor = null): void
    {
        $this->existeId($id);
        $SQL = [];
        $vals = [];
        if (array_key_exists('label', $d)) { $SQL[] = 'label=?'; $vals[] = trim((string) $d['label']); }
        if (array_key_exists('tipo', $d)) {
            $tipo = strtolower(trim((string) $d['tipo']));
            if ($tipo === '') throw new DomainException('El tipo es obligatorio.');
            $SQL[] = 'tipo=?'; $vals[] = $tipo;
        }
        if (array_key_exists('estado', $d)) {
            $estado = strtolower(trim((string) $d['estado']));
            if ($estado === '') throw new DomainException('El estado es obligatorio.');
            $SQL[] = 'estado=?'; $vals[] = $estado;
        }
        if (array_key_exists('tipo_origen', $d)) {
            $origen = trim((string) $d['tipo_origen']);
            if (!in_array($origen, ['credito', 'aportes', 'ambos'], true)) $origen = 'ambos';
            $SQL[] = 'tipo_origen=?'; $vals[] = $origen;
        }
        if (array_key_exists('activo', $d)) { $SQL[] = 'activo=?'; $vals[] = (int) ($d['activo'] ? 1 : 0); }
        if ($SQL) {
            $vals[] = $id;
            $this->db->prepare("UPDATE cobranza_tipos_comunicacion SET " . implode(',', $SQL) . " WHERE id=?")->execute($vals);
        }
        Logger::log('editar', 'cobranza.tipos', $actor['id'] ?? null, "Editado tipo de comunicación #$id");
    }

    public function eliminar(int $id, ?array $actor = null): void
    {
        $this->existeId($id);
        $this->db->prepare("UPDATE cobranza_tipos_comunicacion SET activo=0 WHERE id=?")->execute([$id]);
        Logger::log('eliminar', 'cobranza.tipos', $actor['id'] ?? null, "Inactivado tipo de comunicación #$id", null, null, 'warning');
    }

    /** ¿Existe una combinación tipo/estado? (para validar al crear una comunicación) */
    public function existe(string $tipo, string $estado): bool
    {
        $st = $this->db->prepare("SELECT id FROM cobranza_tipos_comunicacion WHERE tipo=? AND estado=? AND activo=1");
        $st->execute([$tipo, $estado]);
        return (bool) $st->fetch();
    }

    private function existeId(int $id): void
    {
        $st = $this->db->prepare("SELECT id FROM cobranza_tipos_comunicacion WHERE id=?");
        $st->execute([$id]);
        if (!$st->fetch()) throw new DomainException('Tipo de comunicación no encontrado.');
    }
}