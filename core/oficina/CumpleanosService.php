<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Cumpleaños de asociados activos (Oficina → Información → Cumpleaños).
 * Calcula edad, día/mes y días para el próximo cumpleaños en PHP para
 * manejar correctamente el 29 de febrero.
 */
final class CumpleanosService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function listar(string $q = '', int $mes = 0, int $limit = 20, int $offset = 0): array
    {
        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $where = ["a.fecha_nacimiento IS NOT NULL", "COALESCE(ac.estado_activo, 1) = 1"];
        $params = [];

        if ($mes >= 1 && $mes <= 12) {
            $where[] = 'MONTH(a.fecha_nacimiento) = ?';
            $params[] = $mes;
        }
        if ($q !== '') {
            $where[] = '(a.cedula LIKE ? OR a.nombre LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $from = "FROM vw_asociados a
                 LEFT JOIN asociados_control ac ON ac.cedula = a.cedula";
        $w = 'WHERE ' . implode(' AND ', $where);

        $st = $this->db->prepare("SELECT COUNT(*) $from $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $sql = "SELECT a.cedula, a.nombre, a.fecha_nacimiento, a.celular, a.email,
                       COALESCE(ac.estado_activo, 1) AS estado_activo
                $from $w
                ORDER BY MONTH(a.fecha_nacimiento), DAY(a.fecha_nacimiento), a.nombre
                LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) {
            $st->bindValue($i + 1, $v);
        }
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        $hoy = new DateTimeImmutable('today');
        foreach ($rows as &$r) {
            $raw = (string) ($r['fecha_nacimiento'] ?? '');
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                continue;
            }
            try {
                $fn = new DateTimeImmutable($raw);
            } catch (Throwable $e) {
                continue;
            }

            $edad = $fn->diff($hoy)->y;
            $mesNac = (int) $fn->format('n');
            $diaNac = (int) $fn->format('j');

            $proximo = $this->fechaEnAnio((int) $hoy->format('Y'), $mesNac, $diaNac);
            if ($proximo < $hoy) {
                $proximo = $this->fechaEnAnio((int) $hoy->format('Y') + 1, $mesNac, $diaNac);
            }

            $r['edad'] = $edad;
            $r['dia'] = $diaNac;
            $r['mes'] = $mesNac;
            $r['dias_para_cumple'] = (int) $hoy->diff($proximo)->days;
            $r['cumple_hoy'] = $r['dias_para_cumple'] === 0;
        }
        unset($r);

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Fecha del cumpleaños en un año; 29/02 se ajusta a 28/02 en años no bisiestos. */
    private function fechaEnAnio(int $year, int $mes, int $dia): DateTimeImmutable
    {
        if ($mes === 2 && $dia === 29 && !checkdate(2, 29, $year)) {
            $dia = 28;
        }
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $mes, $dia));
    }
}
