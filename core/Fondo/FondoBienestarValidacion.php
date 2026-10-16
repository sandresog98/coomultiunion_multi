<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Validación de aptitud y bloqueos para Fondo de bienestar.
 *
 * PORT literal del algoritmo legacy `cx2/models/FondoBienestarValidacion.php`
 * (mismas constantes, mismo orden de reglas en validarApto). No se reescribe
 * distinto: solo se adaptan los nombres de tablas a v2 y se usa el PDO de v2.
 *
 * Pagos:
 * - Se calculan sobre sifone_movimientos_tributarios (cuenta 42209506) a partir de los abonos (credit).
 * - La cantidad de "pagos" corresponde a meses equivalentes pagados según el valor del abono en cada fecha.
 *   - Antes de 2026-02-01: cada $8.000 abonados cuentan como 1 pago.
 *   - Desde  2026-02-01: cada $10.000 abonados cuentan como 1 pago.
 * - Solo se consideran movimientos desde 2025-06-01 en adelante.
 *
 * Bloqueo/frecuencia: ahora la fuente ÚNICA es `fondo_bienestar_redenciones`
 * (el efecto de aprobación inserta ahí), por lo que se elimina la rama legacy
 * que también leía `fondo_bienestar_solicitudes` aprobadas.
 */
final class FondoBienestarValidacion
{
    private const CUENTA_BIENESTAR = 42209506;
    private const VALOR_PAGO_ANTERIOR = 8000;
    private const VALOR_PAGO_NUEVO = 10000;
    private const FECHA_CAMBIO_VALOR = '2026-02-01';
    private const FECHA_INICIO_CALCULO = '2025-06-01';
    private const ABONO_MINIMO_12_MESES = 96000;

    private PDO $conn;

    public function __construct(?PDO $pdo = null)
    {
        $this->conn = $pdo ?? db();
    }

    /** Listar convenios activos ordenados (no agotados). */
    public function listarConveniosActivos(): array
    {
        $stmt = $this->conn->query(
            "SELECT * FROM fondo_bienestar_convenios WHERE estado_activo = 1 AND agotado = 0 ORDER BY orden ASC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Obtener un convenio por ID (activo y no agotado). */
    public function obtenerConvenio(int $id): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM fondo_bienestar_convenios WHERE id = ? AND estado_activo = 1 AND agotado = 0"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cálculo de pagos (meses equivalentes) y si está al día.
     * @return array{pagos:int, al_dia:bool}
     */
    private function calcularPagosYAlDia(string $cedula): array
    {
        $sql = "
            SELECT
                FLOOR(
                    COALESCE(SUM(
                        CASE
                            WHEN fecha < :fecha_cambio1 THEN credit / :valor_anterior
                            WHEN fecha >= :fecha_cambio2 THEN credit / :valor_nuevo
                            ELSE 0
                        END
                    ), 0) + 0.3
                ) AS pagos,
                CASE
                    WHEN MAX(fecha) IS NULL THEN 0
                    WHEN MAX(fecha) >= DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01') THEN 1
                    ELSE 0
                END AS al_dia
            FROM sifone_movimientos_tributarios
            WHERE cuenta = :cuenta
              AND COALESCE(credit, 0) > 0
              AND fecha >= :fecha_inicio
              AND TRIM(cedula) = TRIM(:cedula)
        ";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'fecha_cambio1' => self::FECHA_CAMBIO_VALOR,
                'valor_anterior' => self::VALOR_PAGO_ANTERIOR,
                'fecha_cambio2' => self::FECHA_CAMBIO_VALOR,
                'valor_nuevo' => self::VALOR_PAGO_NUEVO,
                'cuenta' => self::CUENTA_BIENESTAR,
                'fecha_inicio' => self::FECHA_INICIO_CALCULO,
                'cedula' => $cedula,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['pagos' => 0, 'al_dia' => 0];
        } catch (Throwable $e) {
            error_log('FondoBienestarValidacion::calcularPagosYAlDia: ' . $e->getMessage());
            $row = ['pagos' => 0, 'al_dia' => 0];
        }

        $pagos = (int) ($row['pagos'] ?? 0);
        $alDiaRaw = $row['al_dia'] ?? 0;
        $alDia = $alDiaRaw === 1 || $alDiaRaw === '1';

        return ['pagos' => $pagos, 'al_dia' => $alDia];
    }

    /** Cantidad de pagos realizados (meses equivalentes). */
    public function contarPagosRealizados(string $cedula): int
    {
        return $this->calcularPagosYAlDia($cedula)['pagos'];
    }

    /** Resumen de pagos y estado al día. */
    public function obtenerEstadoPagos(string $cedula): array
    {
        return $this->calcularPagosYAlDia($cedula);
    }

    /** Suma de abonos (credit) para validación de 12 meses. */
    public function sumarAbono(string $cedula): float
    {
        $stmt = $this->conn->prepare(
            "SELECT COALESCE(SUM(credit), 0) AS total FROM sifone_movimientos_tributarios
             WHERE cuenta = ? AND TRIM(cedula) = TRIM(?)"
        );
        $stmt->execute([self::CUENTA_BIENESTAR, $cedula]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['total'] ?? 0);
    }

    /** ¿Está bloqueado para el convenio (bloqueado_hasta >= hoy)? Fuente única: redenciones. */
    public function estaBloqueado(string $cedula, int $convenioId): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM fondo_bienestar_redenciones
             WHERE cedula_asociado = ? AND convenio_id = ? AND bloqueado_hasta IS NOT NULL AND bloqueado_hasta >= ?
             LIMIT 1"
        );
        $stmt->execute([$cedula, $convenioId, date('Y-m-d')]);
        return (bool) $stmt->fetch();
    }

    /** ¿Tiene una solicitud pendiente para el convenio? Devuelve id o null. */
    public function tienePendiente(string $cedula, int $convenioId): ?int
    {
        $stmt = $this->conn->prepare(
            "SELECT s.id
             FROM solicitudes s
             JOIN solicitudes_det_fondo d ON d.solicitud_id = s.id
             WHERE s.tipo_codigo = 'fondo' AND s.cedula_solicitante = ?
               AND d.convenio_id = ? AND s.estado_codigo = 'pendiente'
             LIMIT 1"
        );
        $stmt->execute([$cedula, $convenioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }

    /** Fecha de fin de bloqueo (máxima bloqueado_hasta) para cédula + convenio, o null. */
    public function obtenerBloqueadoHasta(string $cedula, int $convenioId): ?string
    {
        $stmt = $this->conn->prepare(
            "SELECT MAX(bloqueado_hasta) AS hasta FROM fondo_bienestar_redenciones
             WHERE cedula_asociado = ? AND convenio_id = ? AND bloqueado_hasta >= CURDATE()"
        );
        $stmt->execute([$cedula, $convenioId]);
        $hasta = $stmt->fetchColumn();
        return $hasta ? (string) $hasta : null;
    }

    /** ¿Está dentro de la ventana cumpleaños + 30 días? */
    public function dentroVentanaCumpleanos(string $cedula): bool
    {
        $stmt = $this->conn->prepare("SELECT fecnac FROM sifone_asociados WHERE TRIM(cedula) = TRIM(?) LIMIT 1");
        $stmt->execute([$cedula]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['fecnac'])) {
            return false;
        }
        $fecnac = $row['fecnac'];
        $hoy = new DateTimeImmutable('today');
        $md = date('m-d', strtotime($fecnac));
        $cumpleEsteAno = DateTimeImmutable::createFromFormat('Y-m-d', $hoy->format('Y') . '-' . $md);
        if ($cumpleEsteAno === false) {
            return false;
        }
        $cumpleReciente = $hoy >= $cumpleEsteAno ? $cumpleEsteAno : $cumpleEsteAno->modify('-1 year');
        $finVentana = $cumpleReciente->modify('+30 days');
        return $hoy >= $cumpleReciente && $hoy <= $finVentana;
    }

    /** ¿El mes actual está dentro de los meses permitidos del convenio? */
    public function validarPeriodo(array $convenio): bool
    {
        if (empty($convenio['meses_permitidos'])) {
            return true;
        }
        $meses = [];
        $raw = $convenio['meses_permitidos'];
        if (str_starts_with((string) $raw, '[')) {
            $meses = json_decode((string) $raw, true) ?: [];
        } else {
            $meses = array_map('intval', explode(',', (string) $raw));
        }
        if (empty($meses)) {
            return true;
        }
        return in_array((int) date('n'), array_map('intval', $meses), true);
    }

    /** ¿Ha pasado el tiempo (meses) desde la última redención? */
    public function validarFrecuencia(string $cedula, int $convenioId, int $mesesEspera): bool
    {
        if ($mesesEspera <= 0) {
            return true;
        }
        $stmt = $this->conn->prepare(
            "SELECT MAX(fecha_redencion) AS ultima_fecha FROM fondo_bienestar_redenciones
             WHERE cedula_asociado = ? AND convenio_id = ?"
        );
        $stmt->execute([$cedula, $convenioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['ultima_fecha']) {
            return true;
        }
        $ultima = new DateTime($row['ultima_fecha']);
        $hoy = new DateTime();
        $intervalo = $ultima->diff($hoy);
        $mesesPasados = ($intervalo->y * 12) + $intervalo->m;
        return $mesesPasados >= $mesesEspera;
    }

    /** ¿El asociado tiene el producto "Fondo Bienestar" asignado y activo? */
    public function tieneProductoAsignado(string $cedula): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT 1 FROM asociados_productos ap
             INNER JOIN productos p ON p.id = ap.producto_id
             WHERE ap.cedula = ?
               AND ap.estado_activo = 1
               AND p.estado_activo = 1
               AND p.nombre = 'Fondo Bienestar'
             LIMIT 1"
        );
        $stmt->execute([$cedula]);
        return (bool) $stmt->fetch();
    }

    /**
     * Valida si el asociado es apto para el convenio.
     * @return array{apto:bool, mensaje:string, bloqueado_hasta:?string}
     */
    public function validarApto(string $cedula, int $convenioId): array
    {
        $convenio = $this->obtenerConvenio($convenioId);
        if (!$convenio) {
            return ['apto' => false, 'mensaje' => 'Convenio no encontrado.', 'bloqueado_hasta' => null];
        }

        if ($this->estaBloqueado($cedula, $convenioId)) {
            $hasta = $this->obtenerBloqueadoHasta($cedula, $convenioId);
            return [
                'apto' => false,
                'mensaje' => 'Bloqueado hasta ' . ($hasta ? date('d/m/Y', strtotime($hasta)) : '—') . '.',
                'bloqueado_hasta' => $hasta,
            ];
        }

        // Validación global: debe estar al día para cualquier beneficio.
        $estadoPagos = $this->calcularPagosYAlDia($cedula);
        if (!$estadoPagos['al_dia']) {
            return ['apto' => false, 'mensaje' => 'Debe estar al día para acceder a los beneficios.', 'bloqueado_hasta' => null];
        }

        if (!empty($convenio['no_redime'])) {
            return ['apto' => true, 'mensaje' => 'Convenio informativo (no redimible).', 'bloqueado_hasta' => null];
        }

        $mesesRequeridos = (int) $convenio['meses_requeridos'];
        $pagosRealizados = $this->contarPagosRealizados($cedula);

        if ($convenio['requiere_cumpleanos_30_dias'] && !$this->dentroVentanaCumpleanos($cedula)) {
            return ['apto' => false, 'mensaje' => 'Solo puede solicitar dentro de los 30 días siguientes a su cumpleaños.', 'bloqueado_hasta' => null];
        }

        if (!$this->validarPeriodo($convenio)) {
            $mesesLabel = $this->obtenerMesesLabel($convenio['meses_permitidos']);
            return ['apto' => false, 'mensaje' => "Este beneficio solo se puede redimir en: $mesesLabel.", 'bloqueado_hasta' => null];
        }

        if (!$this->validarFrecuencia($cedula, $convenioId, (int) ($convenio['meses_espera_proxima'] ?? 0))) {
            return ['apto' => false, 'mensaje' => "Debe esperar al menos {$convenio['meses_espera_proxima']} meses desde su última redención.", 'bloqueado_hasta' => null];
        }

        if ($pagosRealizados >= $mesesRequeridos) {
            return ['apto' => true, 'mensaje' => 'Apto (pagos: ' . $pagosRealizados . '/' . $mesesRequeridos . ').', 'bloqueado_hasta' => null];
        }

        if ($mesesRequeridos === 12 && $this->sumarAbono($cedula) >= self::ABONO_MINIMO_12_MESES) {
            return ['apto' => true, 'mensaje' => 'Apto por abono mínimo (12 meses).', 'bloqueado_hasta' => null];
        }

        return [
            'apto' => false,
            'mensaje' => 'No apto. Pagos realizados: ' . $pagosRealizados . ' (se requieren ' . $mesesRequeridos . ').',
            'bloqueado_hasta' => null,
        ];
    }

    private function obtenerMesesLabel(?string $raw): string
    {
        if (!$raw) return '';
        $meses = [];
        if (str_starts_with($raw, '[')) {
            $meses = json_decode($raw, true) ?: [];
        } else {
            $meses = array_map('intval', explode(',', $raw));
        }
        $nombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $res = [];
        foreach ($meses as $m) {
            if (isset($nombres[(int) $m])) $res[] = $nombres[(int) $m];
        }
        return implode(', ', $res);
    }
}