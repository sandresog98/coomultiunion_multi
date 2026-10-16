<?php
declare(strict_types=1);
/**
 * Procesador de archivos de pagos PSE (.xls/.xlsx).
 * Inserta en pagos_pse con comprobación de duplicados por pse_id.
 */

require_once __DIR__ . '/ExcelReader.php';
require_once __DIR__ . '/../database.php';

class PagosPseImporter
{
    /** Columnas mínimas para detectar cabecera y validar */
    private const REQUIRED_KEYS = ['CUS', 'Valor', 'Estado', 'Banco Originador', 'Fecha-Hora creada'];

    private PDO $conn;
    private ExcelReader $reader;

    /** Diagnóstico de la última ejecución (filas_leidas, registros_validos, duplicados) */
    private ?array $lastDiagnostics = null;

    public function __construct()
    {
        $this->conn = db();
    }

    public function getLastDiagnostics(): ?array
    {
        return $this->lastDiagnostics;
    }

    /**
     * Procesar archivo y devolver número de registros insertados.
     * @throws \Throwable
     */
    public function procesar(string $filePath): int
    {
        $this->lastDiagnostics = null;
        $this->reader = new ExcelReader();
        if (!$this->reader->load($filePath)) {
            throw new \RuntimeException('No se pudo leer el archivo: ' . $this->reader->getError());
        }

        $headerRowIndex = $this->reader->detectHeaderRowByColumns(self::REQUIRED_KEYS, 50);
        if ($headerRowIndex === null) {
            throw new \RuntimeException('No se encontró la fila de encabezados con las columnas: CUS, Valor, Estado, Banco Originador, Fecha-Hora creada.');
        }

        $rows = $this->reader->rowsWithHeaders($headerRowIndex, 0);
        $rows = array_filter($rows, function ($row) {
            $cus = $this->getRowVal($row, 'CUS');
            return $cus !== null && trim((string) $cus) !== '';
        });
        $rows = array_values($rows);

        if (empty($rows)) {
            throw new \RuntimeException('No hay filas de datos con CUS válido.');
        }

        $first = $rows[0];
        foreach (self::REQUIRED_KEYS as $key) {
            if (!$this->hasKey($first, $key)) {
                throw new \RuntimeException('Falta columna requerida: ' . $key . '. Columnas presentes: ' . implode(', ', array_keys($first)));
            }
        }

        $records = $this->buildRecords($rows);
        $numRows = count($rows);
        if (empty($records)) {
            $this->lastDiagnostics = ['filas_leidas' => $numRows, 'registros_validos' => 0, 'duplicados' => 0];
            return 0;
        }

        $existing = $this->getExistingPseIds(array_column($records, 'pse_id'));
        $toInsert = [];
        foreach ($records as $r) {
            if (!in_array($r['pse_id'], $existing, true)) {
                $toInsert[] = $r;
            }
        }
        $inserted = $this->insertBatch($toInsert);
        $this->lastDiagnostics = [
            'filas_leidas' => $numRows,
            'registros_validos' => count($records),
            'duplicados' => count($records) - count($toInsert),
        ];
        return $inserted;
    }

    private function getRowVal(array $row, string $key): mixed
    {
        if (array_key_exists($key, $row)) {
            return $row[$key];
        }
        foreach (array_keys($row) as $rk) {
            if (strcasecmp(trim((string) $rk), $key) === 0) {
                return $row[$rk];
            }
        }
        return null;
    }

    private function hasKey(array $row, string $key): bool
    {
        if (array_key_exists($key, $row)) {
            return true;
        }
        foreach (array_keys($row) as $rk) {
            if (strcasecmp(trim((string) $rk), $key) === 0) {
                return true;
            }
        }
        return false;
    }

    private function cleanString(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        return trim((string) $v);
    }

    private function cleanInt(mixed $v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        if (is_numeric($v)) {
            return (int) $v;
        }
        $v = preg_replace('/[^\d\-]/', '', (string) $v);
        return $v === '' ? 0 : (int) $v;
    }

    private function toDateTime(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            $candidate = $v->format('Y-m-d H:i:s');
            return ExcelReader::isValidDate(substr($candidate, 0, 10)) ? $candidate : null;
        }
        // Excel serial date (número de días desde 1899-12-30); en algunos servidores las fechas vienen así
        if (is_numeric($v)) {
            $serial = (float) $v;
            if ($serial >= 1 && $serial <= 2958465) {
                try {
                    $seconds = (int) round($serial * 86400);
                    $dt = new \DateTime('1899-12-30 00:00:00');
                    $dt->modify('+' . $seconds . ' seconds');
                    $candidate = $dt->format('Y-m-d H:i:s');
                    return ExcelReader::isValidDate(substr($candidate, 0, 10)) ? $candidate : null;
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }
        $v = trim((string) $v);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/', $v, $m)) {
            $candidate = $m[1] . '-' . $m[2] . '-' . $m[3] . ' ' . $m[4] . ':' . $m[5] . ':00';
            return ExcelReader::isValidDate(substr($candidate, 0, 10)) ? $candidate : null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{1,2}):(\d{2})/', $v, $m)) {
            $candidate = $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . str_pad($m[4], 2, '0', STR_PAD_LEFT) . ':' . $m[5] . ':00';
            return ExcelReader::isValidDate(substr($candidate, 0, 10)) ? $candidate : null;
        }
        if (strlen($v) >= 10 && ExcelReader::isValidDate(substr($v, 0, 10))) {
            return $v;
        }
        return null;
    }

    private function buildRecords(array $rows): array
    {
        $records = [];
        foreach ($rows as $row) {
            $cus = $this->cleanInt($this->getRowVal($row, 'CUS'));
            if ($cus === 0) {
                continue;
            }
            $pseId = 'CUS' . $cus;
            $fechaCreada = $this->toDateTime($this->getRowVal($row, 'Fecha-Hora creada'));
            $fechaUltimo = $this->toDateTime($this->getRowVal($row, 'Fecha-Hora último estado'));
            $fechaResolucion = $this->toDateTime($this->getRowVal($row, 'Fecha-Hora resolución de la transacción'));
            if (!$fechaCreada || !$fechaUltimo || !$fechaResolucion) {
                continue;
            }
            $records[] = [
                'pse_id' => $pseId,
                'cus' => $cus,
                'valor' => $this->cleanInt($this->getRowVal($row, 'Valor')),
                'banco_originador' => $this->cleanString($this->getRowVal($row, 'Banco Originador')),
                'estado' => $this->cleanString($this->getRowVal($row, 'Estado')),
                'cod_de_autorizacion_rechazo_o_fallida' => $this->cleanString($this->getRowVal($row, 'Cod. de autorización, rechazo o fallida')),
                'fecha_hora_creada' => $fechaCreada,
                'fecha_hora_ultimo_estado' => $fechaUltimo,
                'impuesto' => $this->cleanInt($this->getRowVal($row, 'Impuesto')),
                'ticket_id' => $this->cleanInt($this->getRowVal($row, 'Ticket ID')),
                'ciclo_origen' => $this->cleanInt($this->getRowVal($row, 'Ciclo Origen')),
                'ciclo_transaccion' => $this->cleanInt($this->getRowVal($row, 'Ciclo Transacción')),
                'servicio_codigo' => $this->cleanInt($this->getRowVal($row, 'Servicio Código')),
                'servicio_nombre' => $this->cleanString($this->getRowVal($row, 'Servicio Nombre')),
                'referencia_1' => $this->cleanString($this->getRowVal($row, 'Referencia 1')),
                'referencia_2' => $this->cleanInt($this->getRowVal($row, 'Referencia 2')),
                'referencia_3' => $this->cleanString($this->getRowVal($row, 'Referencia 3')),
                'tipo_de_usuario' => $this->cleanString($this->getRowVal($row, 'Tipo de Usuario')),
                'tipo_de_autorizacion' => $this->cleanString($this->getRowVal($row, 'Tipo de Autorización')) ?: null,
                'fecha_hora_resolucion_de_la_transaccion' => $fechaResolucion,
                'banco_recaudador' => $this->cleanString($this->getRowVal($row, 'Banco Recaudador')),
                'modalidad_de_vinculacion' => $this->cleanString($this->getRowVal($row, 'Modalidad de Vinculación')),
                'id_funcionalidad' => $this->cleanInt($this->getRowVal($row, 'ID Funcionalidad')),
                'nombre_funcionalidad' => $this->cleanString($this->getRowVal($row, 'Nombre Funcionalidad')),
                'tipo_de_cuenta_destino' => $this->cleanString($this->getRowVal($row, 'Tipo de Cuenta Destino')),
                'numero_de_cuenta_destino' => $this->cleanInt($this->getRowVal($row, 'Número de Cuenta Destino')),
                'procedencia_de_pago' => $this->cleanString($this->getRowVal($row, 'Procedencia de Pago')),
                'medio_de_pago' => $this->cleanString($this->getRowVal($row, 'Medio de Pago')),
                'tipo_de_dispositivo' => $this->cleanString($this->getRowVal($row, 'Tipo de Dispositivo')),
                'navegador' => $this->cleanString($this->getRowVal($row, 'Navegador')),
                'tipo_de_flujo' => $this->cleanString($this->getRowVal($row, 'Tipo de Flujo')),
            ];
        }
        return $records;
    }

    private function getExistingPseIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare("SELECT pse_id FROM pagos_pse WHERE pse_id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function insertBatch(array $records): int
    {
        if (empty($records)) {
            return 0;
        }
        $sql = "INSERT INTO pagos_pse (pse_id, cus, valor, banco_originador, estado, referencia_1, referencia_2, referencia_3, fecha_creada, fecha_resolucion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $count = 0;
        foreach ($records as $r) {
            $stmt->execute([
                $r['pse_id'], $r['cus'], $r['valor'], $r['banco_originador'], $r['estado'],
                $r['referencia_1'] ?? '0', $r['referencia_2'] ?? '0', $r['referencia_3'] ?? '',
                $r['fecha_hora_creada'], $r['fecha_hora_resolucion_de_la_transaccion'] ?? $r['fecha_hora_creada'],
            ]);
            $count++;
        }
        return $count;
    }
}
