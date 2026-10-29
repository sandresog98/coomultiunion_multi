<?php
declare(strict_types=1);
/**
 * Procesador de archivos de pagos Confiar (.xls/.xlsx).
 * Inserta en pagos_confiar con comprobación de duplicados por confiar_id.
 */

require_once __DIR__ . '/ExcelReader.php';
require_once __DIR__ . '/../database.php';

class PagosConfiarImporter
{
    private const REQUIRED_KEYS = ['FECHA', 'DESCRIPCION', 'DOCUMENTO', 'OFICINA', 'VALOR CONSIGNACION', 'VALOR RETIRO', 'SALDO'];

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

        // Buscar la fila donde están TODAS las columnas requeridas (tabla real; ignora filas iniciales inservibles)
        $headerRowIndex = $this->reader->detectHeaderRowByColumns(self::REQUIRED_KEYS, 50);
        if ($headerRowIndex === null) {
            throw new \RuntimeException('No se encontró la fila de encabezados con las columnas: FECHA, DESCRIPCION, DOCUMENTO, OFICINA, VALOR CONSIGNACION, VALOR RETIRO, SALDO. Revise que el Excel tenga esa fila de cabecera y no solo filas previas como "FECHA INICIAL".');
        }

        // Leer datos hasta la primera fila donde FECHA esté vacía (ignora filas finales inservibles)
        $rows = $this->reader->rowsWithHeadersStopAtEmpty($headerRowIndex, 'FECHA', 0);
        if (empty($rows)) {
            throw new \RuntimeException('No hay filas de datos después de la cabecera.');
        }

        $first = $rows[0];
        foreach (self::REQUIRED_KEYS as $key) {
            if (!$this->hasKey($first, $key)) {
                throw new \RuntimeException('Falta columna requerida: ' . $key . '. Columnas presentes: ' . implode(', ', array_keys($first)));
            }
        }

        $fecha = $this->assertUnaSolaFecha($rows);
        $records = $this->buildRecords($rows);
        $numRows = count($rows);
        if (empty($records)) {
            $this->lastDiagnostics = ['filas_leidas' => $numRows, 'registros_validos' => 0, 'duplicados' => 0, 'fecha' => $fecha];
            return 0;
        }

        $existing = $this->getExistingConfiarIds(array_column($records, 'confiar_id'));
        $toInsert = [];
        foreach ($records as $r) {
            if (!in_array($r['confiar_id'], $existing, true)) {
                $toInsert[] = $r;
            }
        }
        $inserted = $this->insertBatch($toInsert);
        $this->lastDiagnostics = [
            'filas_leidas' => $numRows,
            'registros_validos' => count($records),
            'duplicados' => count($records) - count($toInsert),
            'fecha' => $fecha,
        ];
        return $inserted;
    }

    /**
     * Preflight sin insertar: valida una sola fecha y cuenta existentes/nuevos.
     * @return array{fecha:string,fecha_label:string,total_archivo:int,existentes:int,nuevos:int,requiere_confirmacion:bool,mensaje:string}
     * @throws \Throwable
     */
    public function preflight(string $filePath): array
    {
        $this->reader = new ExcelReader();
        if (!$this->reader->load($filePath)) {
            throw new \RuntimeException('No se pudo leer el archivo: ' . $this->reader->getError());
        }

        $headerRowIndex = $this->reader->detectHeaderRowByColumns(self::REQUIRED_KEYS, 50);
        if ($headerRowIndex === null) {
            throw new \RuntimeException('No se encontró la fila de encabezados con las columnas: FECHA, DESCRIPCION, DOCUMENTO, OFICINA, VALOR CONSIGNACION, VALOR RETIRO, SALDO.');
        }

        $rows = $this->reader->rowsWithHeadersStopAtEmpty($headerRowIndex, 'FECHA', 0);
        if (empty($rows)) {
            throw new \RuntimeException('No hay filas de datos después de la cabecera.');
        }

        $first = $rows[0];
        foreach (self::REQUIRED_KEYS as $key) {
            if (!$this->hasKey($first, $key)) {
                throw new \RuntimeException('Falta columna requerida: ' . $key . '.');
            }
        }

        $fecha = $this->assertUnaSolaFecha($rows);
        $records = $this->buildRecords($rows);

        $existingIds = $this->getExistingConfiarIds(array_column($records, 'confiar_id'));
        $nuevos = 0;
        foreach ($records as $r) {
            if (!in_array($r['confiar_id'], $existingIds, true)) {
                $nuevos++;
            }
        }

        $st = $this->conn->prepare("SELECT COUNT(*) FROM pagos_confiar WHERE fecha = ?");
        $st->execute([$fecha]);
        $existentes = (int) $st->fetchColumn();

        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $label = $dt !== false ? $dt->format('d/m/Y') : $fecha;

        return [
            'fecha' => $fecha,
            'fecha_label' => $label,
            'total_archivo' => count($records),
            'existentes' => $existentes,
            'nuevos' => $nuevos,
            'requiere_confirmacion' => $existentes > 0 && $nuevos > 0,
            'mensaje' => $existentes > 0
                ? sprintf('Ya hay %d movimientos para la fecha %s. Este archivo tiene %d nuevos. ¿Deseas continuar?', $existentes, $label, $nuevos)
                : '',
        ];
    }

    /**
     * Rechaza archivos con más de una fecha y devuelve la única fecha (Y-m-d).
     * @param array<int,array<string,mixed>> $rows
     * @throws \DomainException
     */
    private function assertUnaSolaFecha(array $rows): string
    {
        $fechas = [];
        foreach ($rows as $row) {
            $fecha = $this->fechaToDateString($this->getRowVal($row, 'FECHA'));
            if ($fecha !== null && $fecha !== '') {
                $fechas[$fecha] = true;
            }
        }

        if (count($fechas) === 0) {
            throw new \DomainException('No se encontraron fechas válidas en el archivo.');
        }

        if (count($fechas) > 1) {
            $lista = array_keys($fechas);
            sort($lista);
            $fmt = array_map(static function (string $f): string {
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $f);
                return $d !== false ? $d->format('d/m/Y') : $f;
            }, $lista);
            $texto = count($fmt) === 2
                ? $fmt[0] . ' y ' . $fmt[1]
                : implode(', ', array_slice($fmt, 0, -1)) . ' y ' . $fmt[count($fmt) - 1];

            throw new \DomainException('El archivo trae varias fechas: ' . $texto . '. Carga solo un día a la vez.');
        }

        return (string) array_key_first($fechas);
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

    private function buildRecords(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $fecha = $this->getRowVal($row, 'FECHA');
            if ($fecha === null || (string) $fecha === '') {
                continue;
            }
            $vc = $this->cleanNumeric($this->getRowVal($row, 'VALOR CONSIGNACION'));
            $vr = $this->cleanNumeric($this->getRowVal($row, 'VALOR RETIRO'));
            $saldo = $this->cleanNumeric($this->getRowVal($row, 'SALDO'));
            $doc = $this->cleanString($this->getRowVal($row, 'DOCUMENTO'));
            $desc = $this->cleanString($this->getRowVal($row, 'DESCRIPCION'));
            $normalized[] = [
                'fecha_raw' => $fecha,
                'descripcion' => $desc,
                'documento' => $doc,
                'oficina' => $this->cleanString($this->getRowVal($row, 'OFICINA')),
                'valor_consignacion' => $vc,
                'valor_retiro' => $vr,
                'saldo' => $saldo,
                'doc_str' => $doc,
                'desc_str' => $desc,
                'saldo_num' => $saldo,
                'vc_num' => $vc,
                'vr_num' => $vr,
            ];
        }

        usort($normalized, function ($a, $b) {
            $t = strcmp((string) $a['fecha_raw'], (string) $b['fecha_raw']);
            if ($t !== 0) return $t;
            $t = $a['saldo_num'] <=> $b['saldo_num'];
            if ($t !== 0) return $t;
            $t = $a['vc_num'] <=> $b['vc_num'];
            if ($t !== 0) return $t;
            $t = $a['vr_num'] <=> $b['vr_num'];
            if ($t !== 0) return $t;
            $t = strcmp($a['doc_str'], $b['doc_str']);
            if ($t !== 0) return $t;
            return strcmp($a['desc_str'], $b['desc_str']);
        });

        $seqByDate = [];
        $records = [];
        foreach ($normalized as $n) {
            $fechaStr = $this->fechaToDateString($n['fecha_raw']);
            if ($fechaStr === null || $fechaStr === '') {
                continue;
            }
            $seqKey = $fechaStr;
            $seqByDate[$seqKey] = ($seqByDate[$seqKey] ?? 0) + 1;
            $seq = $seqByDate[$seqKey];
            $confiarId = $this->generateConfiarId($fechaStr, $seq, $n['valor_consignacion'], $n['valor_retiro']);
            $tipoTransaccion = $this->segmentarTipoPago($n['descripcion'], $n['valor_consignacion']);
            $records[] = [
                'confiar_id' => $confiarId,
                'fecha' => $fechaStr,
                'descripcion' => $n['descripcion'],
                'documento' => $n['documento'],
                'oficina' => $n['oficina'],
                'valor_consignacion' => $n['valor_consignacion'],
                'valor_retiro' => $n['valor_retiro'],
                'saldo' => $n['saldo'],
                'tipo_transaccion' => $tipoTransaccion,
            ];
        }
        return $records;
    }

    private function cleanString(mixed $v): string
    {
        if ($v === null) return '';
        return trim((string) $v);
    }

    private function cleanNumeric(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;
        $v = preg_replace('/[^\d.\-]/', '', (string) $v);
        return $v === '' ? null : (float) $v;
    }

    private function fechaToDateString(mixed $v): ?string
    {
        return ExcelReader::normalizeDateSafe($v);
    }

    private function generateConfiarId(string $fechaStr, int $seq, ?float $vc, ?float $vr): string
    {
        $fechaClean = str_replace('-', '', $fechaStr);
        $vcStr = $vc !== null ? str_replace('.', '', number_format((float) $vc, 2, '.', '')) : '0';
        $vrStr = $vr !== null ? str_replace('.', '', number_format((float) $vr, 2, '.', '')) : '0';
        return 'CONF' . $fechaClean . $seq . 'V' . $vcStr . 'R' . $vrStr;
    }

    private function segmentarTipoPago(string $descripcion, ?float $valorConsignacion): string
    {
        if ($valorConsignacion === null || $valorConsignacion <= 0) {
            return '';
        }
        $desc = mb_strtolower($descripcion, 'UTF-8');
        $desc = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $desc);
        // Primero evaluar tipos más específicos para evitar falsos positivos (ej. CAMACHO contiene "ach").
        if (str_contains($desc, 'pago qr')) return 'Pago QR';
        if (str_contains($desc, 'consignacion en cuenta cheque')) return 'Cheque';
        if (str_contains($desc, 'consignacion en cuenta efectivo')) return 'Pago Efectivo';
        if (str_contains($desc, 'consignacion por transf. agencia virtual')) return 'Transf. Agencia Virtual';
        if (preg_match('/(^|[^a-z0-9])ach([^a-z0-9]|$)/i', $desc) === 1) return 'Pago ACH';
        return '';
    }

    private function getExistingConfiarIds(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare("SELECT confiar_id FROM pagos_confiar WHERE confiar_id IN ($placeholders)");
        $stmt->execute($ids);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function insertBatch(array $records): int
    {
        if (empty($records)) return 0;
        $sql = "INSERT INTO pagos_confiar (confiar_id, fecha, descripcion, documento, oficina, valor_consignacion, valor_retiro, saldo, tipo_transaccion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $count = 0;
        foreach ($records as $r) {
            $stmt->execute([
                $r['confiar_id'],
                $r['fecha'] ?: null,
                $r['descripcion'],
                $r['documento'],
                $r['oficina'],
                $r['valor_consignacion'],
                $r['valor_retiro'],
                $r['saldo'],
                $r['tipo_transaccion'],
            ]);
            $count += $stmt->rowCount();
        }
        return $count;
    }
}
