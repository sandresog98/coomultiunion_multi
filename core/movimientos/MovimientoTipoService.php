<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Gestión del catálogo `movimientos_tipos` (configurable).
 * Controla: nombre, clasificación (ingreso/egreso), requiere_referido, valida_disponible y activo.
 * 'eliminar' = inactivar (baja lógica), igual que el catálogo de productos.
 */
final class MovimientoTipoService
{
    private const CLASIF = ['ingreso', 'egreso'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Todos (opcional: solo activos) para selects y validación. */
    public function all(bool $soloActivos = true, bool $conUso = false): array
    {
        $w = $soloActivos ? 'WHERE activo = 1' : '';
        $cols = "t.tipo, t.nombre, t.clasificacion, t.requiere_referido, t.valida_disponible, t.activo";
        $sql = "SELECT $cols" . ($conUso ? ", (SELECT COUNT(*) FROM movimientos_multi m WHERE m.tipo = t.tipo) AS en_uso" : '')
             . " FROM movimientos_tipos t $w ORDER BY t.nombre, t.tipo";
        return $this->db->query($sql)->fetchAll();
    }

    /** Listado paginado para la pantalla de gestión. */
    public function listar(string $q = '', string $orderBy = 'nombre', string $orderDir = 'ASC', int $limit = 50, int $offset = 0): array
    {
        $ORDER_COLS = ['tipo', 'nombre', 'clasificacion', 'requiere_referido', 'valida_disponible', 'activo', 'en_uso'];
        $orderBy = in_array($orderBy, $ORDER_COLS, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = 'WHERE t.tipo LIKE ? OR t.nombre LIKE ?';
            $like = '%' . $q . '%';
            $params = [$like, $like];
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM movimientos_tipos t $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->db->prepare(
            "SELECT t.tipo, t.nombre, t.clasificacion, t.requiere_referido, t.valida_disponible, t.activo,
                    (SELECT COUNT(*) FROM movimientos_multi m WHERE m.tipo = t.tipo) AS en_uso
             FROM movimientos_tipos t $where ORDER BY t.`$orderBy` $orderDir LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Crea un tipo de movimiento. El código se autogenera desde el nombre (slug único). */
    public function crear(array $data, ?array $actor = null): string
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre es obligatorio.');

        $clasif = trim((string) ($data['clasificacion'] ?? ''));
        if (!in_array($clasif, self::CLASIF, true)) throw new DomainException('Clasificación inválida.');

        $base = self::slug($nombre);
        if ($base === '') throw new DomainException('No se puede generar el código a partir del nombre.');
        $tipo = $this->unico($base);

        $this->db->prepare(
            "INSERT INTO movimientos_tipos (tipo, nombre, clasificacion, requiere_referido, valida_disponible, activo, creado_por)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([
            $tipo, $nombre, $clasif,
            (int) (($data['requiere_referido'] ?? false) ? 1 : 0),
            (int) (($data['valida_disponible'] ?? false) ? 1 : 0),
            (int) (($data['activo'] ?? true) ? 1 : 0),
            $actor['id'] ?? null,
        ]);
        Logger::log('crear', 'movimientos.tipos', $actor['id'] ?? null, "Creado tipo de movimiento '$tipo'");
        return $tipo;
    }

    /** Slug a partir del nombre: minúsculas, acentos transliterados, no-alnum → _, máx 30. */
    private static function slug(string $nombre): string
    {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        $t = strtolower((string) $t);
        $t = preg_replace('/[^a-z0-9]+/', '_', $t) ?? '';
        return trim($t, '_');
    }

    /** Garantiza unicidad del código agregando _2, _3… si el slug ya existe. */
    private function unico(string $base): string
    {
        $slug = substr($base, 0, 30);
        $candidato = $slug;
        $n = 2;
        while ($this->existe($candidato)) {
            $sufijo = '_' . $n++;
            $candidato = substr(substr($slug, 0, 30 - strlen($sufijo)) . $sufijo, 0, 30);
        }
        return $candidato;
    }

    /** Edita (o activa/inactiva) un tipo de movimiento. */
    public function editar(string $tipo, array $data, ?array $actor = null): void
    {
        if (!$this->existe($tipo)) throw new DomainException('Tipo de movimiento no encontrado.');
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $clasif = trim((string) ($data['clasificacion'] ?? ''));
        if ($clasif !== '' && !in_array($clasif, self::CLASIF, true)) throw new DomainException('Clasificación inválida.');
        if ($nombre === '' && $clasif === '' && !array_key_exists('activo', $data)) {
            throw new DomainException('No hay cambios para aplicar.');
        }

        $SET = [];
        $vals = [];
        if ($nombre !== '') { $SET[] = 'nombre=?'; $vals[] = $nombre; }
        if ($clasif !== '') { $SET[] = 'clasificacion=?'; $vals[] = $clasif; }
        if (array_key_exists('requiere_referido', $data)) { $SET[] = 'requiere_referido=?'; $vals[] = (int) ($data['requiere_referido'] ? 1 : 0); }
        if (array_key_exists('valida_disponible', $data)) { $SET[] = 'valida_disponible=?'; $vals[] = (int) ($data['valida_disponible'] ? 1 : 0); }
        if (array_key_exists('activo', $data)) { $SET[] = 'activo=?'; $vals[] = (int) ($data['activo'] ? 1 : 0); }
        $SET[] = 'fecha_actualizacion = NOW()';
        $vals[] = $tipo;
        $this->db->prepare("UPDATE movimientos_tipos SET " . implode(', ', $SET) . " WHERE tipo=?")->execute($vals);
        Logger::log('editar', 'movimientos.tipos', $actor['id'] ?? null, "Editado tipo de movimiento '$tipo'");
    }

    /** Inactiva un tipo de movimiento. */
    public function eliminar(string $tipo, ?array $actor = null): void
    {
        if (!$this->existe($tipo)) throw new DomainException('Tipo de movimiento no encontrado.');
        $this->db->prepare("UPDATE movimientos_tipos SET activo=0, fecha_actualizacion=NOW() WHERE tipo=?")->execute([$tipo]);
        Logger::log('eliminar', 'movimientos.tipos', $actor['id'] ?? null, "Inactivado tipo de movimiento '$tipo'", null, null, 'warning');
    }

    private function existe(string $tipo): bool
    {
        $st = $this->db->prepare("SELECT tipo FROM movimientos_tipos WHERE tipo = ?");
        $st->execute([$tipo]);
        return (bool) $st->fetch();
    }
}