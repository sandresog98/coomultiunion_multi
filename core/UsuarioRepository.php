<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Acceso a datos de usuarios internos. Historial de contraseñas en `password_history`
 * (rotación 3 meses, no repetir las últimas 5).
 */
final class UsuarioRepository
{
    public function __construct(private PDO $db) {}

    public function porUsuario(string $usuario): ?array
    {
        $st = $this->db->prepare("SELECT * FROM usuarios WHERE usuario = ? AND estado_activo = 1");
        $st->execute([$usuario]);
        return $st->fetch() ?: null;
    }

    public function porId(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function crear(string $usuario, string $hash, string $nombre, ?string $email, string $rol): int
    {
        $st = $this->db->prepare(
            "INSERT INTO usuarios (usuario, password, nombre_completo, email, rol, password_is_one_use)
             VALUES (?,?,?,?,?,1)"
        );
        $st->execute([$usuario, $hash, $nombre, $email, $rol]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<int,array> lista de usuarios (sin el hash). */
    public function listar(): array
    {
        $rows = $this->db->query(
            "SELECT id, usuario, nombre_completo, email, rol, estado_activo,
                    password_is_one_use, password_changed_at, fecha_creacion
             FROM usuarios ORDER BY usuario"
        )->fetchAll();
        return $rows;
    }

    /** Lista paginada con búsqueda por usuario/nombre (patrón §4.2). */
    public function listarPaginado(int $limit, int $offset, string $q = ''): array
    {
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = 'WHERE (usuario LIKE ? OR nombre_completo LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like];
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM usuarios $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $this->db->prepare(
            "SELECT id, usuario, nombre_completo, email, rol, estado_activo,
                    password_is_one_use, password_changed_at, fecha_creacion
             FROM usuarios $where ORDER BY usuario LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Actualiza datos básicos (no la contraseña). */
    public function actualizar(int $id, string $nombre, ?string $email, string $rol, bool $activo): void
    {
        $this->db->prepare(
            "UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, estado_activo = ? WHERE id = ?"
        )->execute([$nombre, $email ?: null, $rol, $activo ? 1 : 0, $id]);
    }

    /** Baja lógica. */
    public function desactivar(int $id): void
    {
        $this->db->prepare("UPDATE usuarios SET estado_activo = 0 WHERE id = ?")->execute([$id]);
    }

    /** Cambia la contraseña. */
    public function cambiarPassword(int $id, string $hashNuevo, bool $oneUse): void
    {
        $this->db->prepare(
            "UPDATE usuarios
             SET password = ?, password_is_one_use = ?, password_changed_at = NOW()
             WHERE id = ?"
        )->execute([$hashNuevo, $oneUse ? 1 : 0, $id]);
    }

    /** Guarda un hash en el historial y conserva solo las últimas 5 contraseñas previas. */
    public function guardarHistorial(int $id, string $passwordHash): void
    {
        $this->db->prepare(
            "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)"
        )->execute([$id, $passwordHash]);
        $this->db->prepare(
            "DELETE FROM password_history
             WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id FROM password_history
                    WHERE user_id = ?
                    ORDER BY created_at DESC, id DESC
                    LIMIT 5
                ) keep_ids
             ) AND user_id = ?"
        )->execute([$id, $id]);
    }

    /** ¿La contraseña coincide con alguna de las últimas 5 guardadas? */
    public function estaEnHistorial(int $id, string $passwordPlano): bool
    {
        $st = $this->db->prepare(
            "SELECT password_hash FROM password_history
             WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5"
        );
        $st->execute([$id]);
        foreach ($st->fetchAll() as $h) {
            if (password_verify($passwordPlano, (string) $h['password_hash'])) {
                return true;
            }
        }
        return false;
    }
}
