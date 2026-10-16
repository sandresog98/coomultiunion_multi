<?php
declare(strict_types=1);
/**
 * Wrapper unificado para leer .xlsx (SimpleXLSX) y .xls (SimpleXLS).
 * Expone la misma interfaz: filas como array de arrays.
 */

class ExcelReader
{
    private const VENDOR_XLSX = '/utils/vendor/simplexlsx/SimpleXLSX.php';
    private const VENDOR_XLS  = '/utils/vendor/simplexls/SimpleXLS.php';

    /** @var \Shuchkin\SimpleXLSX|\Shuchkin\SimpleXLS|null */
    private $parser = null;

    private string $filePath = '';
    private bool $isXlsx = true;

    /**
     * Cargar archivo Excel (.xls o .xlsx).
     * @return bool true si se cargó correctamente
     */
    public function load(string $filePath): bool
    {
        $this->filePath = $filePath;
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            $this->isXlsx = true;
            if (!is_readable((dirname(__DIR__,2) . self::VENDOR_XLSX))) {
                return false;
            }
            require_once (dirname(__DIR__,2) . self::VENDOR_XLSX);
            $this->parser = \Shuchkin\SimpleXLSX::parse($filePath);
        } elseif ($ext === 'xls') {
            $this->isXlsx = false;
            if (!is_readable((dirname(__DIR__,2) . self::VENDOR_XLS))) {
                return false;
            }
            require_once (dirname(__DIR__,2) . self::VENDOR_XLS);
            $this->parser = \Shuchkin\SimpleXLS::parse($filePath);
        } else {
            return false;
        }

        return $this->parser !== false && $this->parser->success();
    }

    /**
     * Obtener mensaje de error del parser.
     */
    public function getError(): string
    {
        if ($this->parser === null) {
            return 'Archivo no cargado';
        }
        if ($this->isXlsx) {
            return (string) \Shuchkin\SimpleXLSX::parseError();
        }
        return (string) \Shuchkin\SimpleXLS::parseError();
    }

    /**
     * Leer todas las filas de la hoja indicada (0 = primera).
     * @param int $sheetIndex Índice de hoja (0-based)
     * @param int $limit 0 = sin límite
     * @return array<int, array<int, mixed>> Array de filas, cada fila es array de celdas
     */
    public function rows(int $sheetIndex = 0, int $limit = 0): array
    {
        if ($this->parser === null) {
            return [];
        }
        $rows = $this->parser->rows($sheetIndex, $limit);
        return is_array($rows) ? $rows : iterator_to_array($rows, false);
    }

    /**
     * Detectar índice de la fila que contiene la palabra clave (ej. "FECHA" para cabecera).
     * Busca en las primeras $maxRows filas.
     * @return int|null Índice 0-based de la fila, o null si no se encuentra
     */
    public function detectHeaderRow(string $keyword, int $maxRows = 20): ?int
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return null;
        }
        $rows = $this->rows(0, $maxRows);
        foreach ($rows as $i => $row) {
            foreach ($row as $cell) {
                if (is_scalar($cell) && stripos((string) $cell, $keyword) !== false) {
                    return $i;
                }
            }
        }
        return null;
    }

    /**
     * Detectar la fila donde están TODAS las columnas requeridas (cabecera real de la tabla).
     * Útil cuando el Excel tiene filas iniciales inservibles; busca la primera fila que contiene
     * exactamente esos nombres de columna (comparación sin acentos y sin distinguir mayúsculas).
     * @param array<int, string> $requiredColumns Nombres de columnas requeridos (ej. ['FECHA', 'DESCRIPCION', ...])
     * @param int $maxRows Número máximo de filas a escanear desde el inicio
     * @return int|null Índice 0-based de la fila de cabecera, o null si no se encuentra
     */
    public function detectHeaderRowByColumns(array $requiredColumns, int $maxRows = 50): ?int
    {
        if (empty($requiredColumns)) {
            return null;
        }
        $rows = $this->rows(0, $maxRows);
        $requiredNormalized = array_map(function (string $c) {
            return $this->normalizeHeaderName($c);
        }, $requiredColumns);
        foreach ($rows as $i => $row) {
            $cellValues = [];
            foreach ($row as $cell) {
                $v = trim((string) $cell);
                if ($v !== '') {
                    $cellValues[] = $this->normalizeHeaderName($v);
                }
            }
            $allPresent = true;
            foreach ($requiredNormalized as $needle) {
                if (!in_array($needle, $cellValues, true)) {
                    $allPresent = false;
                    break;
                }
            }
            if ($allPresent) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Normalizar nombre de columna para comparación: mayúsculas, sin espacios extra, sin acentos.
     */
    private function normalizeHeaderName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'],
            ['A', 'E', 'I', 'O', 'U', 'N', 'U'],
            $name
        );
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }

    /**
     * Leer filas usando la fila $headerRowIndex como cabecera y devolver array asociativo por nombre de columna.
     * Los nombres de columna se toman de la primera celda no vacía por posición; si hay menos cabeceras que celdas, se usa "col_N".
     * @return array<int, array<string, mixed>>
     */
    public function rowsWithHeaders(int $headerRowIndex = 0, int $limit = 0): array
    {
        $allRows = $this->rows(0, $limit ? $headerRowIndex + 1 + $limit : 0);
        if ($headerRowIndex >= count($allRows)) {
            return [];
        }
        $headers = $allRows[$headerRowIndex];
        $result = [];
        for ($i = $headerRowIndex + 1; $i < count($allRows); $i++) {
            $row = $allRows[$i];
            $assoc = [];
            foreach ($row as $colIndex => $value) {
                $name = isset($headers[$colIndex]) && (string) $headers[$colIndex] !== ''
                    ? trim((string) $headers[$colIndex])
                    : 'col_' . $colIndex;
                $assoc[$name] = $value;
            }
            $result[] = $assoc;
        }
        return $result;
    }

    /**
     * Igual que rowsWithHeaders pero para al encontrar la primera fila donde la columna indicada está vacía.
     * Sirve para Excels con filas finales inservibles (totales, notas, etc.).
     * @param int $headerRowIndex Fila 0-based donde está la cabecera
     * @param string $emptyStopColumn Nombre de la columna que, si viene vacía, indica fin de datos (ej. "FECHA")
     * @param int $limit 0 = sin límite de filas (solo se para por vacío o fin de hoja)
     * @return array<int, array<string, mixed>>
     */
    public function rowsWithHeadersStopAtEmpty(int $headerRowIndex, string $emptyStopColumn, int $limit = 0): array
    {
        $allRows = $this->rows(0, 0);
        if ($headerRowIndex >= count($allRows)) {
            return [];
        }
        $headers = $allRows[$headerRowIndex];
        $result = [];
        $emptyStopNormalized = $this->normalizeHeaderName($emptyStopColumn);
        for ($i = $headerRowIndex + 1; $i < count($allRows); $i++) {
            $row = $allRows[$i];
            $assoc = [];
            $stopColumnValue = null;
            foreach ($row as $colIndex => $value) {
                $name = isset($headers[$colIndex]) && (string) $headers[$colIndex] !== ''
                    ? trim((string) $headers[$colIndex])
                    : 'col_' . $colIndex;
                $assoc[$name] = $value;
                if ($this->normalizeHeaderName($name) === $emptyStopNormalized) {
                    $stopColumnValue = $value;
                }
            }
            $isEmpty = $stopColumnValue === null || $stopColumnValue === '' || trim((string) $stopColumnValue) === '';
            if ($isEmpty) {
                break;
            }
            $result[] = $assoc;
            if ($limit > 0 && count($result) >= $limit) {
                break;
            }
        }
        return $result;
    }

    /**
     * Asegurar que la cadena es UTF-8 válida para MySQL (evitar "Incorrect string value").
     * Excel/SimpleXLS suele devolver Latin-1 o Windows-1252; la BD espera UTF-8.
     */
    public static function ensureUtf8(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        $converted = @mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        $converted = @mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        return (string) mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }

    /**
     * Comprobar si una cadena Y-m-d es una fecha válida (para evitar "Incorrect date value" en MySQL).
     */
    public static function isValidDate(string $yMd): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $yMd, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Convertir número serial de Excel a Y-m-d H:i:s (para uso en fechas con hora).
     * Excel: días desde 1899-12-30; la parte decimal es la hora del día.
     */
    public static function excelSerialToDateTime(float $serial): ?string
    {
        if ($serial < 1 || $serial > 2958465) {
            return null;
        }
        try {
            $seconds = (int) round($serial * 86400);
            $dt = new \DateTime('1899-12-30 00:00:00');
            $dt->modify('+' . $seconds . ' seconds');
            $candidate = $dt->format('Y-m-d H:i:s');
            return self::isValidDate(substr($candidate, 0, 10)) ? $candidate : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalizar valor a fecha Y-m-d válida o null.
     * - Valida con checkdate; si el resultado es inválido (ej. mes 18), prueba DDMMYYYY en 8 dígitos.
     * - Acepta número serial de Excel (en algunos servidores las celdas de fecha vienen así).
     * - Equivalente a Python: pd.to_datetime(..., errors='coerce') que devuelve NaT para inválidos.
     */
    public static function normalizeDateSafe(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            $candidate = $v->format('Y-m-d');
            return self::isValidDate($candidate) ? $candidate : null;
        }
        if (is_numeric($v)) {
            $serial = (float) $v;
            $dtStr = self::excelSerialToDateTime($serial);
            return $dtStr !== null ? substr($dtStr, 0, 10) : null;
        }
        $v = trim((string) $v);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            $candidate = $m[1] . '-' . $m[2] . '-' . $m[3];
            if (self::isValidDate($candidate)) {
                return $candidate;
            }
        }
        // XX/YY/AAAA: Confiar usa MM/DD/AAAA; también admitir DD/MM/AAAA
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $v, $m)) {
            $g1 = (int) $m[1];
            $g2 = (int) $m[2];
            $yyyy = (int) $m[3];
            if ($g1 >= 1 && $g1 <= 12 && $g2 >= 1 && $g2 <= 31 && checkdate($g1, $g2, $yyyy)) {
                return sprintf('%04d-%02d-%02d', $yyyy, $g1, $g2);
            }
            if ($g2 >= 1 && $g2 <= 12 && $g1 >= 1 && $g1 <= 31 && checkdate($g2, $g1, $yyyy)) {
                return sprintf('%04d-%02d-%02d', $yyyy, $g2, $g1);
            }
        }
        $digits = preg_replace('/\D/', '', $v);
        if (strlen($digits) === 8 && is_numeric($digits)) {
            $yyyy = substr($digits, 0, 4);
            $mm = substr($digits, 4, 2);
            $dd = substr($digits, 6, 2);
            $candidate = $yyyy . '-' . $mm . '-' . $dd;
            if (self::isValidDate($candidate)) {
                return $candidate;
            }
            $ddFirst = substr($digits, 0, 2);
            $mmMid = substr($digits, 2, 2);
            $yyyyEnd = substr($digits, 4, 4);
            $candidateDdMm = $yyyyEnd . '-' . $mmMid . '-' . $ddFirst;
            if (self::isValidDate($candidateDdMm)) {
                return $candidateDdMm;
            }
        }
        return null;
    }
}
