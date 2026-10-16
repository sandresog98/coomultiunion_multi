<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Configuración de la aplicación: almacén clave/valor (`app_configuracion`).
 * Para reglas que antes estaban fijas en código (p. ej. el crédito cooperativa
 * de boletas/convenios). Expone lecturas tipadas y un set de escritura.
 */
final class ConfigService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Todas las claves → valor. */
    public function all(): array
    {
        $rows = $this->db->query(
            "SELECT clave, valor FROM app_configuracion ORDER BY clave"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['clave']] = $r['valor'];
        }
        return $out;
    }

    public function get(string $clave, ?string $default = null): ?string
    {
        $st = $this->db->prepare("SELECT valor FROM app_configuracion WHERE clave = ? LIMIT 1");
        $st->execute([$clave]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string) $v;
    }

    public function getInt(string $clave, int $default = 0): int
    {
        $v = $this->get($clave);
        return ($v === null || $v === '') ? $default : (int) $v;
    }

    public function set(string $clave, ?string $valor): void
    {
        $this->db->prepare(
            "INSERT INTO app_configuracion (clave, valor, fecha_actualizacion) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), fecha_actualizacion = NOW()"
        )->execute([$clave, $valor]);
    }

    /** Reglas del crédito cooperativa para boletas (con valores por defecto). */
    public function creditoBoleta(): array
    {
        return [
            'meses_min'   => $this->getInt('boleta_credito_meses_min', 6),
            'aportes_min' => $this->getInt('boleta_credito_aportes_min', 6),
            'limite'      => $this->getInt('boleta_credito_limite', 100000),
        ];
    }
}
