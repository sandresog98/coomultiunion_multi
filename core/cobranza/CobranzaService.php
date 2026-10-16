<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Cobranza unificada (B6): mora de CRÉDITO (sifone_cartera_mora) y mora de APORTES
 * (asociados activos sin mora y con ≥30 días sin aporte en sifone_movimientos_tributarios 31050501).
 * Incluye: grilla unificada con filtros, KPIs/distribuciones, remisión jurídica (con
 * auto-deshabilitación) y snapshots de mora. Comunicaciones/indagaciones → sus servicios.
 */
final class CobranzaService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Grilla unificada. Filtros: tipo_mora(credito|aportes|''), q, banda,
     * rango_ult_comm, solo_remitidos, order/dir/limit/offset.
     */
    public function listar(
        string $tipoMora = '',
        string $q = '',
        string $banda = '',
        string $rango = '',
        bool $soloRemitidos = false,
        string $compromiso = '',
        string $orderBy = 'dias',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $orderBy = in_array($orderBy, ['dias', 'total_mora', 'nombre', 'ultima_comunicacion', 'compromiso'], true) ? $orderBy : 'dias';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $base = $this->unio();
        $where = [];
        $params = [];
        if ($tipoMora !== '') { $where[] = 'u.tipo_mora = ?'; $params[] = $tipoMora; }
        if ($q !== '') {
            $where[] = '(u.cedula LIKE ? OR u.nombre LIKE ? OR u.celular LIKE ? OR u.email LIKE ?)';
            $l = '%' . $q . '%';
            array_push($params, $l, $l, $l, $l);
        }
        if ($banda !== '') { $where[] = 'u.banda = ?'; $params[] = $banda; }
        if ($rango !== '') { $where[] = 'u.rango = ?'; $params[] = $rango; }
        if ($soloRemitidos) { $where[] = 'u.remitido_juridica = 1'; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Conjunto filtrado completo (sin LIMIT) → compromiso/orden/pagina en PHP.
        $st = $this->db->prepare("SELECT u.* FROM ($base) u $w");
        $st->execute($params);
        $rows = $st->fetchAll();

        // Compromisos para todas las cédulas
        $combe = array_column($rows, 'cedula');
        $comps = $this->compromisosPorCedulas($combe);
        $rank = ['cumplido' => 1, 'pendiente_tiempo' => 2, 'incumplido' => 3];
        foreach ($rows as &$r) {
            $c = $comps[$r['cedula']] ?? null;
            $r['compromiso'] = $c;
            $r['compromiso_key'] = $c ? ($c['estado_compromiso'] ?? '') : '';
            // auto-deshabilitar remisión jurídica si días en mora < días desde remisión
            if ((int) $r['remitido_juridica'] === 1 && $r['fecha_remision'] && (int) $r['dias'] < $this->diasDesde((string) $r['fecha_remision'])) {
                $this->desremitir($r['cedula'], null);
                $r['remitido_juridica'] = '0';
                $r['fecha_remision'] = null;
            }
            $r['banda_label'] = $this->bandaLabel($r['banda']);
            $r['estado_color'] = $this->bandaColor($r['banda']);
        }

        // Filtro por compromiso
        if ($compromiso === 'sin') {
            $rows = array_values(array_filter($rows, fn($r) => $r['compromiso_key'] === ''));
        } elseif ($compromiso !== '') {
            $rows = array_values(array_filter($rows, fn($r) => $r['compromiso_key'] === $compromiso));
        }

        // Orden en PHP
        usort($rows, function ($a, $b) use ($orderBy, $orderDir, $rank) {
            $cmp = $this->cmp($orderBy, $a, $b, $rank);
            return $orderDir === 'ASC' ? $cmp : -$cmp;
        });

        $total = count($rows);
        $rows = array_slice($rows, $offset, $limit);
        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    private function cmp(string $orderBy, array $a, array $b, array $rank): int
    {
        return match ($orderBy) {
            'nombre' => strcasecmp((string) $a['nombre'], (string) $b['nombre']),
            'total_mora' => (float) $a['total_mora'] <=> (float) $b['total_mora'],
            'ultima_comunicacion' => strtotime((string) ($a['ultima_comunicacion'] ?: '1970-01-01')) <=> strtotime((string) ($b['ultima_comunicacion'] ?: '1970-01-01')),
            'compromiso' => ($rank[$a['compromiso_key']] ?? 9) <=> ($rank[$b['compromiso_key']] ?? 9),
            default => (int) $a['dias'] <=> (int) $b['dias'],
        };
    }

    /** UNION del universo de asociados en mora (crédito ∪ aportes). */
    private function unio(): string
    {
        return "
         (SELECT m.cedula AS cedula, MAX(m.nombre) AS nombre, MAX(m.telefo) AS celular, MAX(m.mail) AS email,
                 MAX(m.diav) AS dias, SUM(COALESCE(m.sdomor,0)) AS total_mora,
                 'credito' AS tipo_mora,
                 cc.ultima_comunicacion AS ultima_comunicacion, cc.total_comunicaciones AS total_comunicaciones,
                 COALESCE(rj.remitido,0) AS remitido_juridica, rj.fecha_remision AS fecha_remision,
                 CASE WHEN MAX(m.diav)<=30 THEN 'persuasiva' WHEN MAX(m.diav)<=90 THEN 'prejuridico' ELSE 'juridico' END AS banda,
                 CASE WHEN cc.ultima_comunicacion IS NULL THEN 'sin'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<2 THEN 'muy_reciente'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<5 THEN 'reciente'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<10 THEN 'intermedia'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<=20 THEN 'lejana' ELSE 'muy_lejana' END AS rango
           FROM sifone_cartera_mora m
           LEFT JOIN (SELECT asociado_cedula, MAX(fecha) AS ultima_comunicacion, COUNT(*) AS total_comunicaciones
                      FROM cobranza_comunicaciones WHERE tipo_origen='credito' GROUP BY asociado_cedula) cc ON cc.asociado_cedula=m.cedula
           LEFT JOIN cobranza_remisiones_juridica rj ON rj.asociado_cedula=m.cedula
           GROUP BY m.cedula, cc.ultima_comunicacion, cc.total_comunicaciones, rj.remitido, rj.fecha_remision)
         UNION ALL
         (SELECT ua.cedula AS cedula, MAX(sa.nombre) AS nombre, MAX(sa.celula) AS celular, MAX(sa.mail) AS email,
                 DATEDIFF(CURDATE(), MAX(ua.ultima_aporte)) AS dias, 0 AS total_mora,
                 'aportes' AS tipo_mora,
                 cc.ultima_comunicacion AS ultima_comunicacion, cc.total_comunicaciones AS total_comunicaciones,
                 0 AS remitido_juridica, NULL AS fecha_remision,
                 CASE WHEN DATEDIFF(CURDATE(), MAX(ua.ultima_aporte))<=60 THEN '30_60'
                      WHEN DATEDIFF(CURDATE(), MAX(ua.ultima_aporte))<=90 THEN '61_90' ELSE '90_mas' END AS banda,
                 CASE WHEN cc.ultima_comunicacion IS NULL THEN 'sin'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<2 THEN 'muy_reciente'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<5 THEN 'reciente'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<10 THEN 'intermedia'
                      WHEN DATEDIFF(CURDATE(), cc.ultima_comunicacion)<=20 THEN 'lejana' ELSE 'muy_lejana' END AS rango
           FROM (SELECT t.cedula, MAX(t.fecham) AS ultima_aporte FROM sifone_movimientos_tributarios t
                 WHERE t.cuenta='31050501' AND COALESCE(t.credit,0)>0 GROUP BY t.cedula) ua
           INNER JOIN sifone_asociados sa ON sa.cedula = ua.cedula
           INNER JOIN asociados_control ac ON ac.cedula = ua.cedula AND ac.estado_activo = 1
           LEFT JOIN (SELECT DISTINCT cedula FROM sifone_cartera_mora) mor ON mor.cedula = ua.cedula
           LEFT JOIN (SELECT asociado_cedula, MAX(fecha) AS ultima_comunicacion, COUNT(*) AS total_comunicaciones
                      FROM cobranza_comunicaciones WHERE tipo_origen='aportes' GROUP BY asociado_cedula) cc ON cc.asociado_cedula = ua.cedula
           WHERE mor.cedula IS NULL AND DATEDIFF(CURDATE(), ua.ultima_aporte) >= 30
           GROUP BY ua.cedula, cc.ultima_comunicacion, cc.total_comunicaciones)
        ";
    }

    /** Cumplimiento del último compromiso para un lote de cédulas (v2: transacciones.valor_total). */
    public function compromisosPorCedulas(array $cedulas): array
    {
        if (!$cedulas) return [];
        $ph = implode(',', array_fill(0, count($cedulas), '?'));
        $sql = "SELECT l.asociado_cedula, l.id, l.fecha, l.fecha_compromiso, l.monto_compromiso,
                       COALESCE(SUM(t.valor_total),0) AS suma_pagado
                FROM (
                  SELECT id, asociado_cedula, fecha, fecha_compromiso, monto_compromiso,
                         ROW_NUMBER() OVER (PARTITION BY asociado_cedula ORDER BY fecha DESC, id DESC) AS rn
                  FROM cobranza_comunicaciones
                  WHERE estado = 'comprometido' AND fecha_compromiso IS NOT NULL AND monto_compromiso IS NOT NULL
                ) l
                LEFT JOIN transacciones t ON t.cedula = l.asociado_cedula
                  AND t.fecha_creacion >= l.fecha
                  AND t.fecha_creacion <= CONCAT(l.fecha_compromiso, ' 23:59:59')
                WHERE l.rn = 1 AND l.asociado_cedula IN ($ph)
                GROUP BY l.asociado_cedula, l.id, l.fecha, l.fecha_compromiso, l.monto_compromiso";
        $st = $this->db->prepare($sql);
        $st->execute(array_values($cedulas));
        $hoy = date('Y-m-d');
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $suma = (float) $r['suma_pagado'];
            $monto = (float) $r['monto_compromiso'];
            if ($hoy <= $r['fecha_compromiso']) {
                $estado = $suma >= $monto ? 'cumplido' : 'pendiente_tiempo';
            } else {
                $estado = $suma >= $monto ? 'cumplido' : 'incumplido';
            }
            $out[$r['asociado_cedula']] = [
                'estado_compromiso' => $estado,
                'fecha_compromiso' => $r['fecha_compromiso'],
                'monto_compromiso' => $monto,
                'suma_pagado' => $suma,
            ];
        }
        return $out;
    }

    /** KPIs resumen de la vista unificada. */
    public function kpis(): array
    {
        $base = $this->unio();
        $r = $this->db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN tipo_mora='credito' THEN 1 ELSE 0 END) AS credito,
            SUM(CASE WHEN tipo_mora='aportes' THEN 1 ELSE 0 END) AS aportes,
            SUM(CASE WHEN total_mora>0 THEN total_mora ELSE 0 END) AS total_mora,
            SUM(CASE WHEN ultima_comunicacion IS NULL THEN 1 ELSE 0 END) AS sin_comunicacion
            FROM ($base) u")->fetch();
        $bandas = $this->db->query("SELECT banda, COUNT(*) AS n FROM ($base) u GROUP BY banda")->fetchAll();
        return [
            'total' => (int) ($r['total'] ?? 0),
            'credito' => (int) ($r['credito'] ?? 0),
            'aportes' => (int) ($r['aportes'] ?? 0),
            'total_mora' => (float) ($r['total_mora'] ?? 0),
            'sin_comunicacion' => (int) ($r['sin_comunicacion'] ?? 0),
            'bandas' => $bandas,
        ];
    }

    /** Remite un asociado (mora de crédito ≥91). */
    public function remitir(string $cedula, ?array $actor = null): void
    {
        $st = $this->db->prepare("SELECT MAX(diav) AS dias FROM sifone_cartera_mora WHERE cedula = ?");
        $st->execute([$cedula]);
        $dias = (int) ($st->fetchColumn() ?? 0);
        if ($dias < 91) throw new DomainException('Solo se remite a jurídica mora de crédito con ≥91 días.');
        $this->db->prepare(
            "INSERT INTO cobranza_remisiones_juridica (asociado_cedula, remitido, fecha_remision, creado_por)
             VALUES (?,1,NOW(),?)
             ON DUPLICATE KEY UPDATE remitido=1, fecha_remision=NOW(), creado_por=VALUES(creado_por)"
        )->execute([$cedula, $actor['id'] ?? null]);
        Logger::log('editar', 'cobranza.remision', $actor['id'] ?? null, "Remitido a jurídica $cedula", null, null, 'warning');
    }

    public function desremitir(string $cedula, ?array $actor = null): void
    {
        $this->db->prepare(
            "UPDATE cobranza_remisiones_juridica SET remitido=0, fecha_remision=NULL WHERE asociado_cedula = ?"
        )->execute([$cedula]);
        Logger::log('editar', 'cobranza.remision', $actor['id'] ?? null, "Desremitido $cedula", null, null, 'warning');
    }

    /** Captura un snapshot de mora (para la sección Cobranza del detalle). */
    public function capturarSnapshot(string $cedula, ?int $comunicacionId = null, ?array $actor = null): int
    {
        $cred = $this->db->prepare(
            "SELECT m.presta AS numero_credito, COALESCE(a.carter,0) AS deuda_capital,
                    COALESCE(m.sdomor,0) AS deuda_mora, COALESCE(m.diav,0) AS dias_mora, m.fechap AS fecha_pago
             FROM sifone_cartera_mora m
             LEFT JOIN sifone_cartera_aseguradora a ON a.numero = m.presta AND a.cedula = m.cedula
             WHERE m.cedula = ?"
        );
        $cred->execute([$cedula]);
        $creditos = $cred->fetchAll();
        // aportes_monto: sin referencia clara en el snapshot v2 → 0 (solo se registra total_creditos portado de legacy).
        $aportes = 0.0;
        $totalCred = count($creditos);
        $this->db->prepare(
            "INSERT INTO cobranza_detalle_mora (comunicacion_id, asociado_cedula, aportes_monto, total_creditos, creado_por)
             VALUES (?,?,?,?,?)"
        )->execute([$comunicacionId, $cedula, $aportes, $totalCred, $actor['id'] ?? null]);
        $detId = (int) $this->db->lastInsertId();
        $ins = $this->db->prepare(
            "INSERT INTO cobranza_detalle_mora_credito (detalle_id, numero_credito, deuda_capital, deuda_mora, dias_mora, fecha_pago)
             VALUES (?,?,?,?,?,?)"
        );
        foreach ($creditos as $c) {
            $ins->execute([$detId, $c['numero_credito'], $c['deuda_capital'], $c['deuda_mora'], $c['dias_mora'], $c['fecha_pago']]);
        }
        Logger::log('crear', 'cobranza.snapshot', $actor['id'] ?? null, "Snapshot de mora generado para $cedula (id $detId)");
        return $detId;
    }

    private function bandaLabel(string $b): string
    {
        return match ($b) {
            'persuasiva' => 'Persuasiva', 'prejuridico' => 'Prejurídico', 'juridico' => 'Jurídico',
            '30_60' => '30–60 días', '61_90' => '61–90 días', '90_mas' => '91+ días',
            default => $b,
        };
    }
    private function bandaColor(string $b): string
    {
        return match ($b) {
            'persuasiva', '30_60' => 'primary',
            'prejuridico', '61_90' => 'warning',
            'juridico', '90_mas' => 'danger',
            default => 'secondary',
        };
    }
    private function diasDesde(string $fecha): int
    {
        return (int) floor((time() - strtotime($fecha)) / 86400);
    }
}