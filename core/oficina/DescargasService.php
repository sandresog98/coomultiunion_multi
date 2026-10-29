<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Exportaciones de la oficina (Descargas).
 * Port de multi_app/ui/modules/oficina/pages/descargas.php al esquema v2.
 * Cada método devuelve ['data','filename','sheet','widths'] listo para ExcelGenerator.
 *
 * Mapeo de tablas (legacy → v2):
 *   control_asociados → asociados_control
 *   control_productos / control_clausulas → productos (tipo 'producto' / 'clausula')
 *   control_asignacion_asociado_producto / _clausula → asociados_productos
 *   control_transaccion(_detalle) → transacciones(_detalle)
 *   banco_pse → pagos_pse · banco_confiar → pagos_confiar
 *   banco_asignacion_pse → pagos_conciliacion · banco_confirmacion_confiar → pagos_confirmacion
 *   sifone_datacredito_vw → sifone_datacredito · tienda_* → tienda_* (plurales)
 *   control_asignacion_asociado_producto.monto_pago → asociados_productos.monto
 *   transacciones.recibo_caja_sifone → recibo_caja · valor_pago_total → valor_total
 *   pagos_pse.fecha_hora_resolucion_de_la_transaccion → fecha_resolucion
 */
final class DescargasService
{
    private const TIPOS = ['pse', 'mora', 'transacciones', 'inventario_tienda', 'asociados_activos', 'clausulas_asignadas'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public static function tipos(): array
    {
        return self::TIPOS;
    }

    public function exportar(string $tipo, ?int $start = null): array
    {
        return match ($tipo) {
            'pse' => $this->exportarPse((int) ($start ?? 300000)),
            'mora' => $this->exportarMora(),
            'transacciones' => $this->exportarTransacciones(),
            'inventario_tienda' => $this->exportarInventarioTienda(),
            'asociados_activos' => $this->exportarAsociadosActivos(),
            'clausulas_asignadas' => $this->exportarClausulasAsignadas(),
            default => throw new DomainException('Tipo de descarga no soportado.'),
        };
    }

    /** Archivo PSE (3 ramas: crédito vigente + aportes · créditos adicionales · solo aportes). */
    public function exportarPse(int $start): array
    {
        try {
            $this->db->exec("SET lc_time_names = 'es_ES'");
        } catch (Throwable $e) {
            /* ignorar si el locale no está disponible */
        }

        $sql = <<<SQL
WITH credito_reciente AS (
  SELECT a.cedula, a.numero, a.tipopr, a.valorc,
         ROW_NUMBER() OVER (PARTITION BY a.cedula ORDER BY a.numero DESC) AS rn
  FROM sifone_cartera_aseguradora a
  INNER JOIN asociados_control ca ON ca.cedula = a.cedula AND ca.estado_activo = 1
  WHERE UPPER(TRIM(COALESCE(a.tipopr, ''))) != 'CONVENIO'
),
aportes_totales AS (
  SELECT ap.cedula,
    SUM(CASE WHEN p.nombre = 'Plan Futuro' THEN ap.monto ELSE 0 END) AS plan_futuro,
    SUM(CASE WHEN p.nombre = 'Bolsillo' THEN ap.monto ELSE 0 END) AS bolsillo,
    SUM(CASE WHEN p.nombre = 'Fondo Bienestar' THEN ap.monto ELSE 0 END) AS fondo_bienestar,
    SUM(CASE WHEN p.nombre = 'Aportes' THEN ap.monto ELSE 0 END) AS aportes
  FROM asociados_productos ap
  INNER JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1
    AND p.nombre IN ('Plan Futuro', 'Bolsillo', 'Fondo Bienestar', 'Aportes')
  GROUP BY ap.cedula
)
SELECT
  sa.cedula AS cedula_src,
  sa.nombre AS customerid_type,
  cr.numero AS optional1,
  cr.tipopr AS payment_description1,
  CAST(CASE WHEN m.diav IS NULL THEN cr.valorc ELSE COALESCE(m.sdomor,0) END AS DECIMAL(12,2)) AS optional2,
  COALESCE(apt.plan_futuro, 0) AS optional3,
  CAST(0 AS DECIMAL(12,2)) AS optional4,
  COALESCE(apt.bolsillo, 0) AS optional5,
  CAST(0 AS DECIMAL(12,2)) AS optional6,
  COALESCE(apt.fondo_bienestar, 0) AS optional7,
  CAST(ROUND(cr.valorc * (
    CASE
      WHEN m.diav > 60 THEN 0.08
      WHEN m.diav > 50 THEN 0.06
      WHEN m.diav > 40 THEN 0.05
      WHEN m.diav > 30 THEN 0.04
      WHEN m.diav > 20 THEN 0.03
      WHEN m.diav > 11 THEN 0.02
      ELSE 0
    END
  ), 2) AS DECIMAL(12,2)) AS optional8,
  DATE_FORMAT(COALESCE(m.fechap, dv.fecha_pago), '%M %e de %Y') AS optional9,
  CAST(
    (CASE WHEN m.diav IS NULL THEN cr.valorc ELSE COALESCE(m.sdomor,0) END)
    + ROUND(cr.valorc * (
        CASE
          WHEN m.diav > 60 THEN 0.08
          WHEN m.diav > 50 THEN 0.06
          WHEN m.diav > 40 THEN 0.05
          WHEN m.diav > 30 THEN 0.04
          WHEN m.diav > 20 THEN 0.03
          WHEN m.diav > 11 THEN 0.02
          ELSE 0
        END
      ), 2)
    + COALESCE(apt.plan_futuro, 0)
    + COALESCE(apt.bolsillo, 0)
    + COALESCE(apt.fondo_bienestar, 0)
    + COALESCE(apt.aportes, 0)
    AS DECIMAL(12,2)
  ) AS amount1,
  DATE_FORMAT(LAST_DAY(CURDATE()), '%d/%m/%Y') AS date1,
  CAST(0 AS DECIMAL(12,2)) AS optional10,
  CAST(0 AS DECIMAL(12,2)) AS vatAmount1,
  COALESCE(apt.aportes, 0) AS optional11
FROM asociados_control ca
JOIN sifone_asociados sa ON sa.cedula = ca.cedula
JOIN credito_reciente cr ON cr.cedula = sa.cedula AND cr.rn = 1
LEFT JOIN sifone_cartera_mora m ON m.cedula = cr.cedula AND m.presta = cr.numero
LEFT JOIN sifone_datacredito dv
  ON dv.cedula_num = CAST(cr.cedula AS UNSIGNED)
 AND dv.numero_num = CAST(cr.numero AS UNSIGNED)
LEFT JOIN aportes_totales apt ON apt.cedula = sa.cedula
WHERE ca.estado_activo = 1

UNION ALL

SELECT
  sa.cedula AS cedula_src,
  sa.nombre AS customerid_type,
  a.numero AS optional1,
  a.tipopr AS payment_description1,
  CAST(CASE WHEN m.diav IS NULL THEN a.valorc ELSE COALESCE(m.sdomor,0) END AS DECIMAL(12,2)) AS optional2,
  CAST(0 AS DECIMAL(12,2)) AS optional3,
  CAST(0 AS DECIMAL(12,2)) AS optional4,
  CAST(0 AS DECIMAL(12,2)) AS optional5,
  CAST(0 AS DECIMAL(12,2)) AS optional6,
  CAST(0 AS DECIMAL(12,2)) AS optional7,
  CAST(ROUND(a.valorc * (
    CASE
      WHEN m.diav > 60 THEN 0.08
      WHEN m.diav > 50 THEN 0.06
      WHEN m.diav > 40 THEN 0.05
      WHEN m.diav > 30 THEN 0.04
      WHEN m.diav > 20 THEN 0.03
      WHEN m.diav > 11 THEN 0.02
      ELSE 0
    END
  ), 2) AS DECIMAL(12,2)) AS optional8,
  DATE_FORMAT(COALESCE(m.fechap, dv.fecha_pago), '%M %e de %Y') AS optional9,
  CAST(
    (CASE WHEN m.diav IS NULL THEN a.valorc ELSE COALESCE(m.sdomor,0) END)
    + ROUND(a.valorc * (
        CASE
          WHEN m.diav > 60 THEN 0.08
          WHEN m.diav > 50 THEN 0.06
          WHEN m.diav > 40 THEN 0.05
          WHEN m.diav > 30 THEN 0.04
          WHEN m.diav > 20 THEN 0.03
          WHEN m.diav > 11 THEN 0.02
          ELSE 0
        END
      ), 2)
    AS DECIMAL(12,2)
  ) AS amount1,
  DATE_FORMAT(LAST_DAY(CURDATE()), '%d/%m/%Y') AS date1,
  CAST(0 AS DECIMAL(12,2)) AS optional10,
  CAST(0 AS DECIMAL(12,2)) AS vatAmount1,
  CAST(0 AS DECIMAL(12,2)) AS optional11
FROM asociados_control ca
JOIN sifone_asociados sa ON sa.cedula = ca.cedula
JOIN sifone_cartera_aseguradora a ON a.cedula = sa.cedula
LEFT JOIN credito_reciente cr ON cr.cedula = a.cedula AND cr.numero = a.numero AND cr.rn = 1
LEFT JOIN sifone_cartera_mora m ON m.cedula = a.cedula AND m.presta = a.numero
LEFT JOIN sifone_datacredito dv
  ON dv.cedula_num = CAST(a.cedula AS UNSIGNED)
 AND dv.numero_num = CAST(a.numero AS UNSIGNED)
WHERE ca.estado_activo = 1
  AND cr.numero IS NULL

UNION ALL

SELECT
  sa.cedula AS cedula_src,
  sa.nombre AS customerid_type,
  0 AS optional1,
  'APORTES' AS payment_description1,
  CAST(0 AS DECIMAL(12,2)) AS optional2,
  COALESCE(apt.plan_futuro, 0) AS optional3,
  CAST(0 AS DECIMAL(12,2)) AS optional4,
  COALESCE(apt.bolsillo, 0) AS optional5,
  CAST(0 AS DECIMAL(12,2)) AS optional6,
  COALESCE(apt.fondo_bienestar, 0) AS optional7,
  CAST(0 AS DECIMAL(12,2)) AS optional8,
  DATE_FORMAT(LAST_DAY(CURDATE()), '%M %e de %Y') AS optional9,
  (COALESCE(apt.plan_futuro,0)+COALESCE(apt.bolsillo,0)+COALESCE(apt.fondo_bienestar,0)+COALESCE(apt.aportes,0)) AS amount1,
  DATE_FORMAT(LAST_DAY(CURDATE()), '%d/%m/%Y') AS date1,
  CAST(0 AS DECIMAL(12,2)) AS optional10,
  CAST(0 AS DECIMAL(12,2)) AS vatAmount1,
  COALESCE(apt.aportes, 0) AS optional11
FROM asociados_control ca
JOIN sifone_asociados sa ON sa.cedula = ca.cedula
LEFT JOIN credito_reciente cr ON cr.cedula = sa.cedula
LEFT JOIN aportes_totales apt ON apt.cedula = sa.cedula
WHERE ca.estado_activo = 1
  AND cr.numero IS NULL
  AND (COALESCE(apt.plan_futuro,0)+COALESCE(apt.bolsillo,0)+COALESCE(apt.fondo_bienestar,0)+COALESCE(apt.aportes,0)) > 0

ORDER BY 1, 4, 3
SQL;

        $st = $this->db->prepare($sql);
        $st->execute();

        $header = ['customer_id','customerid_type','optional1','payment_description1','invoice_id','optional2','optional3','optional4','optional5','optional6','optional7','optional8','optional9','amount1','date1','optional10','vatAmount1','optional11'];
        $data = [$header];

        $invoice = (int) $start;
        foreach ($st->fetchAll() as $row) {
            $amount = (float) ($row['amount1'] ?? 0);
            if ($amount <= 0) { continue; }
            $digits = preg_replace('/\D+/', '', (string) ($row['cedula_src'] ?? ''));
            $customerId = ($digits !== '' ? (int) $digits : null);
            $invoice++;
            $money = function ($v) { return (float) $v; };
            $str = function ($v) { return ($v === null ? '' : (string) $v); };
            $data[] = [
                $customerId,
                $str($row['customerid_type']),
                ($row['optional1'] !== null && $row['optional1'] !== '' ? $str($row['optional1']) : '0'),
                $str($row['payment_description1']),
                $invoice,
                $money($row['optional2']),
                $money($row['optional3']),
                $money($row['optional4']),
                $money($row['optional5']),
                $money($row['optional6']),
                $money($row['optional7']),
                $money($row['optional8']),
                $str($row['optional9']),
                $money($row['amount1']),
                $str($row['date1']),
                $money($row['optional10']),
                $money($row['vatAmount1']),
                $money($row['optional11']),
            ];
        }

        return [
            'data' => $data,
            'filename' => 'pse.xlsx',
            'sheet' => 'PSE',
            'widths' => [14, 30, 14, 22, 12, 14, 14, 14, 14, 14, 14, 14, 22, 14, 14, 14, 14, 14],
        ];
    }

    /**
     * Genera el archivo PSE con el MISMO formato del legacy v1:
     * .xls BIFF, columnas de dinero como texto con 2 decimales y coma,
     * invoice_id entero y textos en Windows-1252.
     *
     * No usa ExcelGenerator porque éste escribe .xlsx y trunca los decimales.
     */
    public function exportarPseXls(int $start): void
    {
        $rep  = $this->exportarPse($start);
        $rows = $rep['data'];

        if (ob_get_length()) {
            @ob_end_clean();
        }
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="pse.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        $encode = static function ($text): string {
            $s = (string) $text;
            if (function_exists('mb_convert_encoding')) {
                $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
                if ($out !== false) {
                    return $out;
                }
            }
            if (function_exists('iconv')) {
                $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
                if ($out !== false) {
                    return $out;
                }
            }
            return $s;
        };

        $xlsBOF = static function (): void {
            echo pack('vvvvvv', 0x0809, 0x0008, 0x0000, 0x0010, 0x0000, 0x0000);
        };
        $xlsEOF = static function (): void {
            echo pack('vv', 0x000A, 0x0000);
        };
        $xlsLabel = static function (int $row, int $col, $text) use ($encode): void {
            $str = $encode($text);
            $len = strlen($str);
            echo pack('vvvvv', 0x0204, 8 + $len, $row, $col, 0x0000) . pack('v', $len) . $str;
        };

        $xlsBOF();
        $moneyCols = [5, 6, 7, 8, 9, 10, 11, 13, 15, 16, 17];
        $totalRows = count($rows);
        for ($r = 0; $r < $totalRows; $r++) {
            $row = $rows[$r];
            $cols = count($row);
            for ($c = 0; $c < $cols; $c++) {
                $val = $row[$c];
                if ($r === 0) {
                    $xlsLabel($r, $c, $val);
                } elseif (in_array($c, $moneyCols, true)) {
                    $xlsLabel($r, $c, number_format((float) $val, 2, ',', ''));
                } elseif ($c === 4) {
                    $xlsLabel($r, $c, (string) (int) $val);
                } else {
                    $xlsLabel($r, $c, (string) $val);
                }
            }
        }
        $xlsEOF();
        exit;
    }

    /** Asociados en mora (crédito + aportes) con codeudor y desglose de productos. */
    public function exportarMora(): array
    {
        try {
            $this->db->exec("SET lc_time_names = 'es_ES'");
        } catch (Throwable $e) {
            /* ignorar si el locale no está disponible */
        }

        $sql = <<<SQL
SELECT
  COALESCE(NULLIF(TRIM(a.nombre), ''), NULLIF(TRIM(m.nombre), ''), NULLIF(TRIM(sa.nombre), ''), '') AS nombre_completo,
  COALESCE(NULLIF(TRIM(a.nombr1), ''), NULLIF(TRIM(m.nombre), ''), NULLIF(TRIM(sa.nombre), ''), '') AS nombre,
  sa.celula AS telefono,
  sa.mail AS email,
  'crédito' AS segmento,
  COALESCE(m.sdomor, 0) AS saldo_mora,
  COALESCE(m.diav, 0) AS dias_mora,
  COALESCE(fb.monto, 0) AS fondo_bienestar,
  COALESCE(ap.monto, 0) AS aportes,
  COALESCE(bol.monto, 0) AS bolsillo,
  COALESCE(pf.monto, 0) AS plan_futuro,
  COALESCE(m.sdomor, 0) +
  COALESCE(m.diav, 0) +
  COALESCE(fb.monto, 0) +
  COALESCE(ap.monto, 0) +
  COALESCE(bol.monto, 0) +
  COALESCE(pf.monto, 0) AS total,
  COALESCE(NULLIF(TRIM(dvco.nombre), ''), '') AS codeudor_nombre,
  COALESCE(NULLIF(TRIM(dvco.celular), ''), '') AS codeudor_celular,
  COALESCE(NULLIF(TRIM(dvco.email), ''), '') AS codeudor_email,
  COALESCE(NULLIF(TRIM(dvco.direccion), ''), '') AS codeudor_direccion
FROM asociados_control ca
JOIN sifone_asociados sa ON sa.cedula = ca.cedula
JOIN sifone_cartera_mora m ON m.cedula = sa.cedula
LEFT JOIN sifone_cartera_aseguradora a ON a.cedula = m.cedula AND a.numero = m.presta
LEFT JOIN sifone_datacredito dvco
  ON dvco.numero_num = CAST(TRIM(COALESCE(a.numero, m.presta)) AS UNSIGNED)
  AND dvco.codeudor = 1
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Fondo Bienestar'
  GROUP BY ap.cedula
) fb ON fb.cedula = sa.cedula
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Aportes'
  GROUP BY ap.cedula
) ap ON ap.cedula = sa.cedula
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Bolsillo'
  GROUP BY ap.cedula
) bol ON bol.cedula = sa.cedula
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Plan Futuro'
  GROUP BY ap.cedula
) pf ON pf.cedula = sa.cedula
WHERE ca.estado_activo = 1 AND COALESCE(m.sdomor,0) > 0

UNION ALL

SELECT
  COALESCE(NULLIF(TRIM(sa.nombre), ''), '') AS nombre_completo,
  COALESCE(NULLIF(TRIM(sa.nombre), ''), '') AS nombre,
  sa.celula AS telefono,
  sa.mail AS email,
  'aportes' AS segmento,
  0 AS saldo_mora,
  DATEDIFF(CURDATE(), ua.ultima_aporte) AS dias_mora,
  COALESCE(fb.monto, 0) AS fondo_bienestar,
  COALESCE(ap.monto, 0) AS aportes,
  COALESCE(bol.monto, 0) AS bolsillo,
  COALESCE(pf.monto, 0) AS plan_futuro,
  COALESCE(fb.monto, 0) + COALESCE(ap.monto, 0) + COALESCE(bol.monto, 0) + COALESCE(pf.monto, 0) AS total,
  '' AS codeudor_nombre,
  '' AS codeudor_celular,
  '' AS codeudor_email,
  '' AS codeudor_direccion
FROM (
  SELECT t.cedula, MAX(t.fecham) AS ultima_aporte
  FROM sifone_movimientos_tributarios t
  WHERE t.cuenta = '31050501' AND COALESCE(t.credit,0) > 0
  GROUP BY t.cedula
) ua
JOIN sifone_asociados sa ON sa.cedula = ua.cedula
JOIN asociados_control ca ON ca.cedula = sa.cedula
LEFT JOIN (
  SELECT DISTINCT cedula FROM sifone_cartera_mora
) mor ON mor.cedula = ua.cedula
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Fondo Bienestar'
  GROUP BY ap.cedula
) fb ON fb.cedula = sa.cedula
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Aportes'
  GROUP BY ap.cedula
) ap ON ap.cedula = sa.cedula
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Bolsillo'
  GROUP BY ap.cedula
) bol ON bol.cedula = sa.cedula
LEFT JOIN (
  SELECT ap.cedula, SUM(ap.monto) AS monto
  FROM asociados_productos ap
  JOIN productos p ON p.id = ap.producto_id
  WHERE ap.estado_activo = 1 AND p.estado_activo = 1 AND p.nombre = 'Plan Futuro'
  GROUP BY ap.cedula
) pf ON pf.cedula = sa.cedula
WHERE ca.estado_activo = 1 AND mor.cedula IS NULL AND DATEDIFF(CURDATE(), ua.ultima_aporte) >= 30

ORDER BY nombre_completo
SQL;

        $st = $this->db->prepare($sql);
        $st->execute();

        $header = ['Nombre Completo','Nombres','Telefono','Email','Segmento','Saldo mora','dias mora','Valor Fondo Bienestar','Valor Aportes','Valor Bolsillo','Valor Plan Futuro','Total','Codeudor nombre','Codeudor teléfono','Codeudor email','Codeudor dirección'];
        $data = [$header];

        foreach ($st->fetchAll() as $row) {
            $data[] = [
                (string) ($row['nombre_completo'] ?? ''),
                (string) ($row['nombre'] ?? ''),
                (string) ($row['telefono'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['segmento'] ?? ''),
                (float) ($row['saldo_mora'] ?? 0),
                (int) ($row['dias_mora'] ?? 0),
                (float) ($row['fondo_bienestar'] ?? 0),
                (float) ($row['aportes'] ?? 0),
                (float) ($row['bolsillo'] ?? 0),
                (float) ($row['plan_futuro'] ?? 0),
                (float) ($row['total'] ?? 0),
                (string) ($row['codeudor_nombre'] ?? ''),
                (string) ($row['codeudor_celular'] ?? ''),
                (string) ($row['codeudor_email'] ?? ''),
                (string) ($row['codeudor_direccion'] ?? ''),
            ];
        }

        return [
            'data' => $data,
            'filename' => 'asociados_mora.xlsx',
            'sheet' => 'Mora',
            'widths' => [18, 40, 22, 16, 16, 12, 18, 16, 16, 16, 16, 16, 28, 18, 28, 36],
        ];
    }

    /** Transacciones con información bancaria (PSE/Confiar), conciliación, confirmación y asignación. */
    public function exportarTransacciones(): array
    {
        $sql = <<<SQL
SELECT b.origen,
       b.id,
       b.fecha_banco,
       b.valor,
       b.tipo_transaccion,
       COALESCE(pc.fecha_creacion, pco.fecha_creacion) AS fecha_validacion,
       t.id                                               AS transaccion_id,
       t.cedula,
       sa.nombre                                          AS asociado_nombre,
       t.valor_total,
       t.fecha_creacion                                   AS fecha_asignacion,
       t.recibo_caja,
       SUM(COALESCE(d.valor_asignado, 0))                 AS valor_asignado
FROM (SELECT 'pse'                 AS origen,
             pse_id                AS id,
             valor,
             fecha_resolucion      AS fecha_banco,
             'PSE'                 AS tipo_transaccion
      FROM pagos_pse
      WHERE estado = 'Aprobada'
      UNION ALL
      SELECT 'confiar'          AS origen,
             confiar_id         AS id,
             valor_consignacion AS valor,
             fecha              AS fecha_banco,
             tipo_transaccion
      FROM pagos_confiar
      WHERE LENGTH(tipo_transaccion) > 0) AS b
         LEFT JOIN pagos_conciliacion AS pc
                   ON (b.origen = 'pse' AND b.id = pc.pse_id)
         LEFT JOIN pagos_confirmacion AS pco
                   ON (b.origen = 'confiar' AND b.id = pco.confiar_id)
         LEFT JOIN transacciones AS t
                   ON ((b.origen = 'pse' AND t.pse_id = b.id)
                       OR (b.origen = 'confiar' AND t.confiar_id = b.id))
         LEFT JOIN transacciones_detalle d ON d.transaccion_id = t.id
         LEFT JOIN sifone_asociados sa ON sa.cedula = t.cedula
GROUP BY b.valor, pc.fecha_creacion, pco.fecha_creacion, t.id, t.cedula, sa.nombre, t.valor_total, t.fecha_creacion, t.recibo_caja
ORDER BY b.fecha_banco, b.id, t.id, d.id
SQL;

        $st = $this->db->prepare($sql);
        $st->execute();

        $header = ['origen','id','fecha_banco','valor','tipo_transaccion','fecha_validacion','transaccion_id','cedula','asociado_nombre','valor_pago_total','fecha_asignacion','recibo_caja_sifone','valor_asignado'];
        $data = [$header];

        foreach ($st->fetchAll() as $r) {
            $data[] = [
                (string) ($r['origen'] ?? ''),
                (string) ($r['id'] ?? ''),
                (string) ($r['fecha_banco'] ?? ''),
                (float) ($r['valor'] ?? 0),
                (string) ($r['tipo_transaccion'] ?? ''),
                (string) ($r['fecha_validacion'] ?? ''),
                (int) ($r['transaccion_id'] ?? 0),
                (string) ($r['cedula'] ?? ''),
                (string) ($r['asociado_nombre'] ?? ''),
                (float) ($r['valor_total'] ?? 0),
                (string) ($r['fecha_asignacion'] ?? ''),
                (string) ($r['recibo_caja'] ?? ''),
                (float) ($r['valor_asignado'] ?? 0),
            ];
        }

        return [
            'data' => $data,
            'filename' => 'transacciones.xlsx',
            'sheet' => 'Transacciones',
            'widths' => [10, 22, 14, 14, 18, 16, 12, 16, 36, 16, 16, 18, 16],
        ];
    }

    /** Inventario de la tienda: disponible vía tienda_stock (1 fila por unidad física). */
    public function exportarInventarioTienda(): array
    {
        $sql = <<<SQL
SELECT
  p.id AS producto_id,
  p.nombre AS producto_nombre,
  c.nombre AS categoria,
  m.nombre AS marca,
  COALESCE(p.precio_compra_sugerido, 0) AS precio_compra,
  COALESCE(p.precio_venta_sugerido, 0) AS precio_venta,
  (SELECT COUNT(*) FROM tienda_stock st WHERE st.producto_id = p.id AND st.estado <> 'anulado')
    - (SELECT COUNT(*) FROM tienda_stock st WHERE st.producto_id = p.id AND st.estado = 'vendido') AS cantidad_disponible
FROM tienda_productos p
INNER JOIN tienda_categorias c ON c.id = p.categoria_id
INNER JOIN tienda_marcas m ON m.id = p.marca_id
WHERE p.estado_activo = 1
ORDER BY c.nombre, m.nombre, p.nombre
SQL;

        $st = $this->db->prepare($sql);
        $st->execute();

        $header = ['ID Producto','Nombre Producto','Categoría','Marca','Precio Compra','Precio Venta','Cantidad Disponible'];
        $data = [$header];

        foreach ($st->fetchAll() as $row) {
            $data[] = [
                (int) ($row['producto_id'] ?? 0),
                (string) ($row['producto_nombre'] ?? ''),
                (string) ($row['categoria'] ?? ''),
                (string) ($row['marca'] ?? ''),
                (float) ($row['precio_compra'] ?? 0),
                (float) ($row['precio_venta'] ?? 0),
                (int) ($row['cantidad_disponible'] ?? 0),
            ];
        }

        return [
            'data' => $data,
            'filename' => 'Inventario_tienda.xlsx',
            'sheet' => 'Inventario',
            'widths' => [12, 40, 20, 20, 16, 16, 20],
        ];
    }

    /** Lista de asociados (cédula, nombre, teléfono, email, estado). */
    public function exportarAsociadosActivos(): array
    {
        $sql = <<<SQL
SELECT
  sa.cedula,
  COALESCE(NULLIF(TRIM(sa.nombre), ''), '') AS nombre,
  COALESCE(NULLIF(TRIM(sa.celula), ''), '') AS telefono,
  COALESCE(NULLIF(TRIM(sa.mail), ''), '') AS email,
  ca.estado_activo
FROM asociados_control ca
JOIN sifone_asociados sa ON sa.cedula = ca.cedula
ORDER BY sa.nombre, sa.cedula
SQL;

        $st = $this->db->prepare($sql);
        $st->execute();

        $header = ['Cédula','Nombre','Teléfono','Email','Estado'];
        $data = [$header];

        foreach ($st->fetchAll() as $r) {
            $data[] = [
                (string) ($r['cedula'] ?? ''),
                (string) ($r['nombre'] ?? ''),
                (string) ($r['telefono'] ?? ''),
                (string) ($r['email'] ?? ''),
                ($r['estado_activo'] ? 'Activo' : 'Inactivo'),
            ];
        }

        return [
            'data' => $data,
            'filename' => 'asociados.xlsx',
            'sheet' => 'Asociados activos',
            'widths' => [16, 42, 18, 32, 12],
        ];
    }

    /** Cláusulas asignadas a asociados (catálogo `productos` con tipo 'clausula'). */
    public function exportarClausulasAsignadas(): array
    {
        $sql = <<<SQL
SELECT
  aac.cedula,
  COALESCE(NULLIF(TRIM(sa.nombre), ''), '') AS nombre_asociado,
  c.nombre AS clausula,
  aac.monto,
  aac.fecha_inicio,
  aac.meses_vigencia,
  COALESCE(NULLIF(TRIM(COALESCE(aac.detalle, c.parametros)), ''), '') AS parametros,
  CASE WHEN aac.estado_activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado
FROM asociados_productos aac
INNER JOIN productos c ON c.id = aac.producto_id AND c.tipo = 'clausula'
INNER JOIN asociados_control ca ON ca.cedula = aac.cedula AND ca.estado_activo = 1
LEFT JOIN sifone_asociados sa ON sa.cedula = aac.cedula
ORDER BY sa.nombre, c.nombre
SQL;

        $st = $this->db->prepare($sql);
        $st->execute();

        $header = ['Cédula','Nombre Asociado','Cláusula','Monto Mensual','Fecha Inicio','Meses Vigencia','Parámetros','Estado'];
        $data = [$header];

        foreach ($st->fetchAll() as $r) {
            $data[] = [
                (string) ($r['cedula'] ?? ''),
                (string) ($r['nombre_asociado'] ?? ''),
                (string) ($r['clausula'] ?? ''),
                (float) ($r['monto'] ?? 0),
                (string) ($r['fecha_inicio'] ?? ''),
                (string) ($r['meses_vigencia'] ?? ''),
                (string) ($r['parametros'] ?? ''),
                (string) ($r['estado'] ?? ''),
            ];
        }

        return [
            'data' => $data,
            'filename' => 'clausulas_asignadas.xlsx',
            'sheet' => 'Cláusulas asignadas',
            'widths' => [16, 42, 28, 18, 14, 16, 60, 12],
        ];
    }
}