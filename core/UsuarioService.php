<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/UsuarioRepository.php';
require_once __DIR__ . '/Logger.php';

/**
 * Reglas de negocio de usuarios internos: alta, cambio de contraseña,
 * política de complejidad y no-reutilización (últimas 5).
 */
final class UsuarioService
{
    private UsuarioRepository $repo;

    public function __construct(?PDO $pdo = null)
    {
        $this->repo = new UsuarioRepository($pdo ?? db());
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }

    public function listarPaginado(string $q = '', int $limit = 20, int $offset = 0): array
    {
        return $this->repo->listarPaginado($limit, $offset, $q);
    }

    /**
     * Regla de negocio: un 'lider' no puede gestionar usuarios 'admin' ni 'lider'.
     * 'admin' puede con todos. (El legacy aplicaba estas mismas restricciones.)
     */
    private function verificarGestion(?array $actor, string $rolObjetivo): void
    {
        $rolActor = $actor['rol'] ?? '';
        if ($rolActor === 'admin') {
            return;
        }
        if ($rolActor === 'lider' && in_array($rolObjetivo, ['admin', 'lider'], true)) {
            throw new DomainException("Un líder no puede gestionar usuarios de rol '$rolObjetivo'.");
        }
    }

    /** Alta de usuario con contraseña aleatoria de un solo uso. Devuelve [id, password_plano]. */
    public function crear(string $usuario, string $nombre, ?string $email, string $rol, ?array $actor = null): array
    {
        $usuario = trim($usuario);
        if ($usuario === '' || $nombre === '' || $rol === '') {
            throw new DomainException('Usuario, nombre y rol son obligatorios.');
        }
        $this->verificarGestion($actor, $rol);
        if ($this->repo->porUsuario($usuario)) {
            throw new DomainException('El nombre de usuario ya existe.');
        }
        $plano = $this->passwordAleatoria();
        $hash = password_hash($plano, PASSWORD_BCRYPT);
        $id = $this->repo->crear($usuario, $hash, $nombre, $email ?: null, $rol);
        $this->repo->guardarHistorial($id, $hash);   // la clave inicial no debe reutilizarse
        Logger::log('crear', 'usuarios', $actor['id'] ?? null, "Alta usuario $usuario (rol $rol)");
        return ['id' => $id, 'password' => $plano];
    }

    /**
     * Cambio de contraseña. Si $actual se provee, se verifica (autoservicio);
     * si es un reseteo por admin, $actual puede ser null pero $oneUse=true.
     */
    public function cambiarPassword(int $id, ?string $actual, string $nueva, bool $oneUse = false): void
    {
        $u = $this->repo->porId($id);
        if (!$u) {
            throw new DomainException('Usuario no encontrado.');
        }
        if ($actual !== null && !password_verify($actual, $u['password'])) {
            throw new DomainException('La contraseña actual es incorrecta.');
        }
        $this->validarComplejidad($nueva);
        if (password_verify($nueva, $u['password'])) {
            throw new DomainException('La nueva contraseña no puede ser igual a la actual.');
        }
        if ($this->repo->estaEnHistorial($id, $nueva)) {
            throw new DomainException('No puede reutilizar una de sus últimas contraseñas.');
        }
        $this->repo->guardarHistorial($id, $u['password']);   // guarda la vigente antes de reemplazarla
        $this->repo->cambiarPassword($id, password_hash($nueva, PASSWORD_BCRYPT), $oneUse);
        Logger::log('editar', 'usuarios', (string) $id, 'Cambio de contraseña');
    }

    public function actualizar(array $actor, int $id, string $nombre, ?string $email, string $rol, bool $activo): void
    {
        $u = $this->repo->porId($id);
        if (!$u) {
            throw new DomainException('Usuario no encontrado.');
        }
        $this->verificarGestion($actor, $u['rol']);   // sobre el rol actual
        $this->verificarGestion($actor, $rol);         // y sobre el nuevo rol
        $this->repo->actualizar($id, $nombre, $email, $rol, $activo);
        Logger::log('editar', 'usuarios', (string) $id, "Edición de usuario {$u['usuario']}");
    }

    public function eliminar(array $actor, int $id): void
    {
        $u = $this->repo->porId($id);
        if (!$u) {
            throw new DomainException('Usuario no encontrado.');
        }
        if ((string) ($actor['id'] ?? '') === (string) $id) {
            throw new DomainException('No puede eliminar su propio usuario.');
        }
        $this->verificarGestion($actor, $u['rol']);
        $this->repo->desactivar($id);
        Logger::log('eliminar', 'usuarios', (string) $id, "Baja de usuario {$u['usuario']}", null, null, 'warning');
    }

    /** Reseteo por administrador → nueva contraseña aleatoria de un solo uso. */
    public function resetear(array $actor, int $id): string
    {
        $u = $this->repo->porId($id);
        if (!$u) {
            throw new DomainException('Usuario no encontrado.');
        }
        $this->verificarGestion($actor, $u['rol']);
        $plano = $this->passwordAleatoria();
        $this->cambiarPassword($id, null, $plano, true);
        Logger::log('resetear', 'usuarios', $actor['id'] ?? null, "Reseteada contraseña de {$u['usuario']}", null, null, 'warning');
        return $plano;
    }

    public function validarComplejidad(string $p): void
    {
        if (strlen($p) < 8 || !preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/\d/', $p)) {
            throw new DomainException('La contraseña debe tener mínimo 8 caracteres, con mayúscula, minúscula y número.');
        }
    }

    private function passwordAleatoria(int $len = 10): string
    {
        // garantiza al menos una mayúscula, minúscula y dígito
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789'];
        $out = '';
        foreach ($sets as $s) {
            $out .= $s[random_int(0, strlen($s) - 1)];
        }
        $all = implode('', $sets) . '!@#$%*';
        for ($i = strlen($out); $i < $len; $i++) {
            $out .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($out);
    }
}
