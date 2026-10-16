<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/Storage/Storage.php';

/**
 * Convenios + productos (B10) — el producto puede ser un "beneficio"
 * (maneja_stock=0) o una "boleta/ticket" con stock (maneja_stock=1).
 * Tablas: convenios, convenios_productos. Recursos: uploads/convenios/.
 */
final class ConvenioService
{
    private const ORDER_COLS_CONVENIOS = ['nombre', 'fecha_creacion', 'fecha_actualizacion', 'productos'];
    private const ORDER_COLS_PRODUCTOS = ['nombre', 'valor', 'precio_venta', 'fecha_creacion', 'fecha_actualizacion'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Lista de convenios con paginación (patrón Apéndice A §4.2). */
    public function listarConvenios(
        string $q = '',
        string $estado = '',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(c.nombre LIKE ? OR c.descripcion LIKE ? OR c.correo LIKE ?)";
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        if ($estado !== '' && in_array($estado, ['0', '1'], true)) {
            $where[] = "c.activo = ?";
            $params[] = (int) $estado;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM convenios c $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_CONVENIOS, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $col = $orderBy === 'productos' ? '(SELECT COUNT(*) FROM convenios_productos pc WHERE pc.convenio_id = c.id)' : "c.$orderBy";

        $sql = "SELECT c.*, (SELECT COUNT(*) FROM convenios_productos pc WHERE pc.convenio_id = c.id) AS total_productos
                FROM convenios c $w ORDER BY $col $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function obtenerConvenio(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT c.*, (SELECT COUNT(*) FROM convenios_productos pc WHERE pc.convenio_id = c.id) AS total_productos
             FROM convenios c WHERE c.id = ?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Crea o actualiza un convenio. Imagen opcional (upload multipart). */
    public function guardarConvenio(array $data, ?array $actor = null, ?array $file = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre del convenio es requerido.');

        $campos = [
            'nombre' => $nombre,
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'correo' => trim((string) ($data['correo'] ?? '')),
            'link' => trim((string) ($data['link'] ?? '')),
            'anexo_nombre' => trim((string) ($data['anexo_nombre'] ?? '')),
        ];

        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $campos['imagen'] = $this->_guardarImagen('convenio', $id, $file);
        } elseif (isset($data['imagen']) && $data['imagen'] !== '') {
            $campos['imagen'] = $this->_normalizarImagen((string) $data['imagen']);
        }

        if ($id > 0) {
            $campos['activo'] = isset($data['activo']) ? ((int) $data['activo'] === 1 ? 1 : 0) : 1;
            $campos['fecha_actualizacion'] = date('Y-m-d H:i:s');
            $sets = [];
            $vals = [];
            foreach ($campos as $k => $v) {
                $sets[] = "$k = ?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $this->db->prepare("UPDATE convenios SET " . implode(', ', $sets) . " WHERE id = ?")
                ->execute($vals);
            Logger::log('editar', 'convenios', $actor['id'] ?? null, "Editado convenio #$id ($nombre)");
            return ['id' => $id];
        }

        $st = $this->db->prepare(
            "INSERT INTO convenios (nombre, descripcion, telefono, correo, link, anexo_nombre, imagen, activo, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $n = $campos['nombre'], $campos['descripcion'], $campos['telefono'], $campos['correo'],
            $campos['link'], $campos['anexo_nombre'], $campos['imagen'] ?? null,
            isset($data['activo']) ? ((int) $data['activo'] === 1 ? 1 : 0) : 1,
            $actor['id'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'convenios', $actor['id'] ?? null, "Creado convenio #$id ($n)");
        return ['id' => $id];
    }

    public function toggleConvenio(int $id, ?array $actor = null): array
    {
        $c = $this->obtenerConvenio($id);
        if (!$c) throw new DomainException('Convenio no encontrado.');
        $nuevo = $c['activo'] ? 0 : 1;
        $this->db->prepare("UPDATE convenios SET activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'convenios', $actor['id'] ?? null,
            ($nuevo ? 'Activado' : 'Inactivado') . " convenio #$id", null, null, 'warning');
        return ['id' => $id, 'activo' => $nuevo];
    }

    public function eliminarConvenio(int $id, ?array $actor = null): void
    {
        $c = $this->obtenerConvenio($id);
        if (!$c) throw new DomainException('Convenio no encontrado.');
        $st = $this->db->prepare("SELECT COUNT(*) FROM convenios_productos WHERE convenio_id = ?");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: el convenio tiene productos asociados.');
        }
        $this->db->prepare("DELETE FROM convenios WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'convenios', $actor['id'] ?? null, "Eliminado convenio #$id", null, null, 'warning');
    }

    /** Lista de productos (por convenio o todos) con paginación. */
    public function listarProductos(
        int $convenioId = 0,
        string $q = '',
        string $tipo = '',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($convenioId > 0) {
            $where[] = "p.convenio_id = ?";
            $params[] = $convenioId;
        }
        if ($q !== '') {
            $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR c.nombre LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($tipo === 'boleta') { $where[] = "p.maneja_stock = 1"; }
        elseif ($tipo === 'beneficio') { $where[] = "p.maneja_stock = 0"; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM convenios_productos p JOIN convenios c ON c.id = p.convenio_id $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_PRODUCTOS, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT p.*, c.nombre AS convenio_nombre
                FROM convenios_productos p
                JOIN convenios c ON c.id = p.convenio_id
                $w ORDER BY p.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        foreach ($rows as &$p) {
            $p['tipo'] = $p['maneja_stock'] ? 'boleta' : 'beneficio';
            $st2 = $this->db->prepare("SELECT COUNT(*) FROM boleteria_boletas WHERE producto_id = ?");
            $st2->execute([$p['id']]);
            $p['total_boletas'] = (int) $st2->fetchColumn();
        }

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function obtenerProducto(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.*, c.nombre AS convenio_nombre FROM convenios_productos p
             JOIN convenios c ON c.id = p.convenio_id WHERE p.id = ?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) $row['tipo'] = $row['maneja_stock'] ? 'boleta' : 'beneficio';
        return $row ?: null;
    }

    /** Crea o actualiza un producto. maneja_stock=1 → requiere precios de compra/venta. */
    public function guardarProducto(array $data, ?array $actor = null, ?array $file = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $convenioId = (int) ($data['convenio_id'] ?? 0);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($convenioId <= 0 || !$this->obtenerConvenio($convenioId)) {
            throw new DomainException('Debe seleccionar un convenio válido.');
        }
        if ($nombre === '') throw new DomainException('El nombre del producto es requerido.');

        $manejaStock = (isset($data['maneja_stock']) && ((int) $data['maneja_stock'] === 1 || $data['maneja_stock'] === '1'));
        $precioCompra = null;
        $precioVenta = null;
        if ($manejaStock) {
            $precioCompra = (float) ($data['precio_compra'] ?? 0);
            $precioVenta = (float) ($data['precio_venta'] ?? 0);
            if ($precioCompra < 0 || $precioVenta < 0) throw new DomainException('Los precios deben ser >= $0.');
            if ($precioVenta <= 0) throw new DomainException('El precio de venta de la boleta debe ser mayor a $0.');
        }

        $campos = [
            'convenio_id' => $convenioId,
            'nombre' => $nombre,
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'valor' => (float) ($data['valor'] ?? 0),
            'valor_general' => (isset($data['valor_general']) && $data['valor_general'] !== '') ? (float) $data['valor_general'] : null,
            'maneja_stock' => $manejaStock ? 1 : 0,
            'precio_compra' => $manejaStock ? $precioCompra : null,
            'precio_venta' => $manejaStock ? $precioVenta : null,
        ];

        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $campos['imagen'] = $this->_guardarImagen('convenio_producto', $id, $file);
        } elseif (isset($data['imagen']) && $data['imagen'] !== '') {
            $campos['imagen'] = $this->_normalizarImagen((string) $data['imagen']);
        }

        if ($id > 0) {
            $campos['activo'] = isset($data['activo']) ? ((int) $data['activo'] === 1 ? 1 : 0) : 1;
            $campos['fecha_actualizacion'] = date('Y-m-d H:i:s');
            $sets = [];
            $vals = [];
            foreach ($campos as $k => $v) {
                $sets[] = "$k = ?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $this->db->prepare("UPDATE convenios_productos SET " . implode(', ', $sets) . " WHERE id = ?")
                ->execute($vals);
            Logger::log('editar', 'convenios.productos', $actor['id'] ?? null, "Editado producto #$id ($nombre)");
            return ['id' => $id];
        }

        $st = $this->db->prepare(
            "INSERT INTO convenios_productos (convenio_id, nombre, descripcion, valor, valor_general, imagen, activo,
                                             precio_compra, precio_venta, maneja_stock, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $convenioId, $nombre, $campos['descripcion'], $campos['valor'], $campos['valor_general'],
            $campos['imagen'] ?? null,
            isset($data['activo']) ? ((int) $data['activo'] === 1 ? 1 : 0) : 1,
            $campos['precio_compra'], $campos['precio_venta'], $campos['maneja_stock'],
            $actor['id'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'convenios.productos', $actor['id'] ?? null,
            "Creado producto #$id ($nombre)", null, null, 'info');
        return ['id' => $id];
    }

    public function toggleProducto(int $id, ?array $actor = null): array
    {
        $p = $this->obtenerProducto($id);
        if (!$p) throw new DomainException('Producto no encontrado.');
        $nuevo = $p['activo'] ? 0 : 1;
        $this->db->prepare("UPDATE convenios_productos SET activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'convenios.productos', $actor['id'] ?? null,
            ($nuevo ? 'Activado' : 'Inactivado') . " producto #$id", null, null, 'warning');
        return ['id' => $id, 'activo' => $nuevo];
    }

    public function eliminarProducto(int $id, ?array $actor = null): void
    {
        $p = $this->obtenerProducto($id);
        if (!$p) throw new DomainException('Producto no encontrado.');
        $st = $this->db->prepare("SELECT COUNT(*) FROM boleteria_boletas WHERE producto_id = ?");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: el producto tiene boletas en stock.');
        }
        $this->db->prepare("DELETE FROM convenios_productos WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'convenios.productos', $actor['id'] ?? null, "Eliminado producto #$id", null, null, 'warning');
    }

    /** URL de cabecera para el público/staff usando una imagen opaca. */
    public function imagenUrl(mixed $imagen, ?callable $resolver = null): ?string
    {
        if (empty($imagen)) return null;
        $s = (string) $imagen;
        if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://')) return $s;
        if ($resolver !== null) return $resolver($s);
        return $s;
    }

    public function uploadsConveniosDir(): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') $base = dirname(__DIR__, 2) . '/' . $base;
        $dir = rtrim($base, '/') . '/convenios';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @chmod($dir, 0777);
        return $dir;
    }

    private function _guardarImagen(string $base, int $codigo, array $file): string
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new DomainException('No se pudo subir la imagen.');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new DomainException('Formato de imagen no permitido (jpg/jpeg/png/webp).');
        }
        $maxMb = (float) (getenv('UPLOAD_MAX_IMAGE_MB') ?: '5');
        if ($file['size'] > $maxMb * 1024 * 1024) throw new DomainException("La imagen supera el límite de {$maxMb} MB.");
        $clave = substr(hash('sha256', (string) microtime(true)), 0, 16);
        $nombre = Storage::nombreOpaco($base, (string) $codigo, $clave, $ext);
        Storage::for('convenios')->put('convenios/' . $nombre, $file['tmp_name'], ['mime' => $file['type'] ?? 'image/jpeg']);
        return $nombre;
    }

    private function _normalizarImagen(string $imagen): string
    {
        $imagen = trim($imagen);
        if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) return $imagen;
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $imagen)) return $imagen; // basename opaco
        return '';
    }
}