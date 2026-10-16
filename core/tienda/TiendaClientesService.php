<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Clientes de Tienda (B8). Tipo 'asociado' (vinculado a sifone_asociados.cedula)
 * o 'externo' (documento nit_cedula propio). Tabla: tienda_clientes.
 */
final class TiendaClientesService
{
    private const ORDER_COLS = ['nombre', 'nit_cedula', 'cedula', 'telefono', 'email', 'fecha_creacion'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function listar(
        string $q = '',
        string $tipo = '',
        string $estado = '',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(c.nombre LIKE ? OR c.nit_cedula LIKE ? OR c.cedula LIKE ? OR c.telefono LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($tipo !== '' && in_array($tipo, ['asociado', 'externo'], true)) {
            $where[] = "c.tipo = ?";
            $params[] = $tipo;
        }
        if ($estado !== '' && in_array($estado, ['0', '1'], true)) {
            $where[] = "c.estado_activo = ?";
            $params[] = (int) $estado;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_clientes c $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT c.*, (SELECT COUNT(*) FROM tienda_ventas v WHERE v.cliente_id = c.id) AS usado_ventas
                FROM tienda_clientes c $w ORDER BY c.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function listarExternosActivos(): array
    {
        return $this->db->query(
            "SELECT id, nombre, nit_cedula FROM tienda_clientes WHERE tipo = 'externo' AND estado_activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM tienda_clientes WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function guardar(array $data, ?array $actor = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $tipo = ($data['tipo'] ?? 'externo') === 'asociado' ? 'asociado' : 'externo';
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre del cliente es requerido.');

        $cedula = null;
        $nitCedula = null;
        if ($tipo === 'asociado') {
            $cedula = trim((string) ($data['cedula'] ?? ''));
            if ($cedula === '') throw new DomainException('Para un cliente asociado la cédula es requerida.');
            $st = $this->db->prepare("SELECT COUNT(*) FROM sifone_asociados WHERE cedula = ?");
            $st->execute([$cedula]);
            if ((int) $st->fetchColumn() === 0) {
                throw new DomainException('La cédula indicada no existe entre los asociados (Sifone).');
            }
        } else {
            $nitCedula = trim((string) ($data['nit_cedula'] ?? ''));
            if ($nitCedula === '') throw new DomainException('Para un cliente externo el NIT/cédula es requerido.');
        }

        try {
            if ($id > 0) {
                $this->db->prepare(
                    "UPDATE tienda_clientes SET tipo = ?, cedula = ?, nit_cedula = ?, nombre = ?, telefono = ?, email = ?, estado_activo = ?, fecha_actualizacion = ? WHERE id = ?"
                )->execute([
                    $tipo, $cedula, $nitCedula, $nombre,
                    trim((string) ($data['telefono'] ?? '')), trim((string) ($data['email'] ?? '')),
                    isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                    date('Y-m-d H:i:s'),
                    $id,
                ]);
                Logger::log('editar', 'tienda.clientes', $actor['id'] ?? null, "Editado cliente #$id ($nombre)");
                return ['id' => $id];
            }
            $st = $this->db->prepare(
                "INSERT INTO tienda_clientes (tipo, cedula, nit_cedula, nombre, telefono, email, estado_activo) VALUES (?,?,?,?,?,?,?)"
            );
            $st->execute([
                $tipo, $cedula, $nitCedula, $nombre,
                trim((string) ($data['telefono'] ?? '')), trim((string) ($data['email'] ?? '')),
                isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
            ]);
            $id = (int) $this->db->lastInsertId();
            Logger::log('crear', 'tienda.clientes', $actor['id'] ?? null,
                'Creado cliente ' . ($tipo === 'asociado' ? "asociado #$id ($nombre, cédula $cedula)" : "externo #$id ($nombre)"));
            return ['id' => $id];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('Ya existe un cliente con ese documento/cédula.');
            throw $e;
        }
    }

    public function toggleEstado(int $id, ?array $actor = null): array
    {
        $c = $this->obtener($id);
        if (!$c) throw new DomainException('Cliente no encontrado.');
        $nuevo = $c['estado_activo'] ? 0 : 1;
        $this->db->prepare("UPDATE tienda_clientes SET estado_activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'tienda.clientes', $actor['id'] ?? null,
            ($nuevo ? 'Activado' : 'Inactivado') . " cliente #$id", null, null, 'warning');
        return ['id' => $id, 'estado_activo' => $nuevo];
    }

    public function eliminar(int $id, ?array $actor = null): void
    {
        $c = $this->obtener($id);
        if (!$c) throw new DomainException('Cliente no encontrado.');
        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_ventas WHERE cliente_id = ?");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: el cliente tiene ventas asociadas.');
        }
        $this->db->prepare("DELETE FROM tienda_clientes WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'tienda.clientes', $actor['id'] ?? null, "Eliminado cliente #$id", null, null, 'warning');
    }

    /** Autocomplete de asociados (Apéndice A §11): por cédula o nombre. */
    public function buscarAsociados(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $like = '%' . $q . '%';
        $st = $this->db->prepare(
            "SELECT cedula, nombre, celula AS telefono FROM sifone_asociados
             WHERE cedula LIKE ? OR nombre LIKE ? ORDER BY nombre LIMIT ?"
        );
        $st->bindValue(1, $like);
        $st->bindValue(2, $like);
        $st->bindValue(3, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}