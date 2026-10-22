<?php
declare(strict_types=1);

require_once __DIR__ . '/ExcelReader.php';
require_once dirname(__DIR__) . '/database.php';

/**
 * Importador único de archivos Sifone (reemplaza el ETL Python + su espejo PHP).
 * Config-driven: cada tipo declara tabla, modo de carga y mapeo.
 * - Tablas "header": el nombre de columna del Excel == columna de BD (case-insensitive).
 * - datacredito: columnas posicionales del archivo (a..cd) -> nombres semánticos.
 * Limpieza por TIPO de columna (fecha/numérico/texto) leído de information_schema.
 */
final class SifoneImporter
{
    private const CONFIG = [
        'sifone_libro'                   => ['tabla' => 'sifone_asociados',               'modo' => 'reemplazo', 'mapeo' => 'header', 'post' => 'upsert_asociados'],
        'sifone_cartera_aseguradora'     => ['tabla' => 'sifone_cartera_aseguradora',     'modo' => 'reemplazo', 'mapeo' => 'header'],
        'sifone_cartera_mora'            => ['tabla' => 'sifone_cartera_mora',            'modo' => 'reemplazo', 'mapeo' => 'header'],
        'sifone_balance_prueba'          => ['tabla' => 'sifone_balance_prueba',          'modo' => 'reemplazo', 'mapeo' => 'header'],
        'sifone_movimientos_tributarios' => ['tabla' => 'sifone_movimientos_tributarios', 'modo' => 'por_anio',  'mapeo' => 'header', 'anio_col' => 'fecham'],
        'sifone_datacredito'             => ['tabla' => 'sifone_datacredito',             'modo' => 'reemplazo', 'mapeo' => 'datacredito'],
        'estado_cuenta'                  => ['tabla' => 'sifone_estado_cuenta',           'modo' => 'reemplazo', 'mapeo' => 'header', 'guardarrail' => 0.9, 'requeridas' => ['cedula', 'numero']],
    ];

    // datacredito: columna semántica => letra posicional en el archivo. (fecha_vencimiento = g + 1 mes)
    private const DATACREDITO_MAP = [
        'cedula' => 'b', 'numero_credito' => 'c', 'nombre' => 'd', 'fecha_emision' => 'f',
        'fecha_vencimiento' => 'g', 'codeudor' => 'h', 'estado_credito' => 'm', 'fecha_pago' => 'q',
        'desembolso_inicial' => 'x', 'saldo_capital' => 'y', 'cuota' => 'aa',
        'cuotas_iniciales' => 'ac', 'cuotas_pendientes' => 'ad', 'direccion' => 'az',
        'email' => 'ba', 'celular' => 'bb',
    ];

    // Llaves de datacredito que hay que normalizar al ancho del resto del esquema (10).
    private const DATACREDITO_LLAVES = ['cedula', 'numero_credito'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public static function tiposSoportados(): array
    {
        return array_keys(self::CONFIG);
    }

    /** Procesa un archivo. Devuelve stats [tipo, filas_leidas, insertados, modo]. */
    public function procesar(string $tipo, string $filePath): array
    {
        $cfg = self::CONFIG[$tipo] ?? null;
        if ($cfg === null) {
            throw new DomainException("Tipo de carga no soportado: $tipo");
        }
        $reader = new ExcelReader();
        if (!$reader->load($filePath)) {
            throw new RuntimeException('No se pudo leer el archivo: ' . $reader->getError());
        }
        $rows = $reader->rowsWithHeaders(0, 0); // fila 0 = encabezados
        $tabla = $cfg['tabla'];
        $tipos = $this->columnasTipadas($tabla);      // col => data_type
        $registros = $this->construir($rows, $cfg, $tipos);

        if (empty($registros)) {
            throw new DomainException('El archivo no contiene filas válidas.');
        }

        // Guardarraíl (estado_cuenta): no cargar si el nuevo trae <90% del existente.
        if (isset($cfg['guardarrail'])) {
            $existentes = (int) $this->db->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
            if ($existentes > 0 && count($registros) < $cfg['guardarrail'] * $existentes) {
                throw new DomainException(sprintf(
                    'Guardarraíl: el archivo trae %d registros (<%.0f%% de %d existentes). Carga abortada.',
                    count($registros), $cfg['guardarrail'] * 100, $existentes
                ));
            }
        }

        $this->db->beginTransaction();
        try {
            if ($cfg['modo'] === 'reemplazo') {
                $this->db->exec("DELETE FROM `$tabla`");
            } elseif ($cfg['modo'] === 'por_anio') {
                $anios = $this->aniosPresentes($registros, $cfg['anio_col']);
                foreach ($anios as $a) {
                    $this->db->prepare("DELETE FROM `$tabla` WHERE YEAR(`{$cfg['anio_col']}`) = ?")->execute([$a]);
                }
            }
            $insertados = $this->insertarLote($tabla, $registros);

            if (($cfg['post'] ?? '') === 'upsert_asociados') {
                $this->upsertAsociados();
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['tipo' => $tipo, 'tabla' => $tabla, 'filas_leidas' => count($rows), 'insertados' => $insertados, 'modo' => $cfg['modo']];
    }

    /** Construye los registros mapeados y limpios según la configuración. */
    private function construir(array $rows, array $cfg, array $tipos): array
    {
        $out = [];
        foreach ($rows as $row) {
            $rec = [];
            if ($cfg['mapeo'] === 'datacredito') {
                foreach (self::DATACREDITO_MAP as $col => $letra) {
                    $val = $this->valorPorClave($row, $letra);
                    if ($col === 'fecha_vencimiento') {
                        $val = $this->fechaMasUnMes($val); // lógica de la vista legacy
                    }
                    $rec[$col] = $this->limpiar($val, $tipos[$col] ?? 'varchar');
                    if (in_array($col, self::DATACREDITO_LLAVES, true)) {
                        $rec[$col] = $this->normalizarLlave($rec[$col]);
                    }
                }
            } else { // header: columna BD == encabezado del Excel
                foreach ($tipos as $col => $tipoDato) {
                    if ($col === 'id') { continue; }
                    if ($this->tieneClave($row, $col)) {
                        $rec[$col] = $this->limpiar($this->valorPorClave($row, $col), $tipoDato);
                    }
                }
            }
            // requeridas (ej. estado_cuenta: cedula+numero)
            $ok = true;
            foreach ($cfg['requeridas'] ?? [] as $rk) {
                if (trim((string) ($rec[$rk] ?? '')) === '') { $ok = false; break; }
            }
            if ($ok && $rec) { $out[] = $rec; }
        }
        return $out;
    }

    private function aniosPresentes(array $registros, string $col): array
    {
        $anios = [];
        foreach ($registros as $r) {
            $f = $r[$col] ?? null;
            if ($f && preg_match('/^(\d{4})-/', (string) $f, $m)) { $anios[$m[1]] = true; }
        }
        return array_keys($anios);
    }

    private function insertarLote(string $tabla, array $registros): int
    {
        $cols = array_keys($registros[0]);
        $colList = '`' . implode('`,`', $cols) . '`';
        $insertados = 0;
        foreach (array_chunk($registros, 500) as $chunk) {
            $ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $sql = "INSERT INTO `$tabla` ($colList) VALUES " . implode(',', array_fill(0, count($chunk), $ph));
            $vals = [];
            foreach ($chunk as $r) {
                foreach ($cols as $c) { $vals[] = $r[$c] ?? null; }
            }
            $this->db->prepare($sql)->execute($vals);
            $insertados += count($chunk);
        }
        return $insertados;
    }

    /** asociados_control: da de alta las cédulas nuevas (activo=1), sin tocar las existentes. */
    private function upsertAsociados(): void
    {
        $this->db->exec(
            "INSERT INTO asociados_control (cedula, estado_activo)
             SELECT DISTINCT sa.cedula, 1 FROM sifone_asociados sa
             LEFT JOIN asociados_control ca ON ca.cedula = sa.cedula
             WHERE ca.cedula IS NULL AND sa.cedula IS NOT NULL AND sa.cedula <> ''"
        );
    }

    // ---- helpers de columnas / limpieza ---------------------------------

    private function columnasTipadas(string $tabla): array
    {
        $st = $this->db->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ORDINAL_POSITION"
        );
        $st->execute([$tabla]);
        $out = [];
        foreach ($st->fetchAll() as $r) { $out[$r['COLUMN_NAME']] = strtolower($r['DATA_TYPE']); }
        return $out;
    }

    private function tieneClave(array $row, string $clave): bool
    {
        if (array_key_exists($clave, $row)) { return true; }
        foreach (array_keys($row) as $k) { if (strcasecmp(trim((string) $k), $clave) === 0) { return true; } }
        return false;
    }

    private function valorPorClave(array $row, string $clave): mixed
    {
        if (array_key_exists($clave, $row)) { return $row[$clave]; }
        foreach ($row as $k => $v) { if (strcasecmp(trim((string) $k), $clave) === 0) { return $v; } }
        return null;
    }

    private function limpiar(mixed $val, string $tipoDato): mixed
    {
        if (in_array($tipoDato, ['date', 'datetime', 'timestamp'], true)) {
            return $this->normalizarFecha($val);
        }
        if ($tipoDato === 'time') {
            $s = trim((string) $val);
            return $s === '' ? null : $s;
        }
        if (in_array($tipoDato, ['int', 'bigint', 'decimal', 'numeric', 'tinyint', 'smallint'], true)) {
            return $this->limpiarNumero($val);
        }
        return $this->textoUtf8($val);
    }

    /** Sanea a UTF-8 válido (los archivos Sifone traen Ñ/tildes en CP1252/Latin-1). */
    private function textoUtf8(mixed $val): ?string
    {
        if ($val === null) { return null; }
        $s = trim((string) $val);
        if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        return $s;
    }

    /** "2019.03.04", "20250703", "2019-03-04", "0"/"" -> Y-m-d o null. */
    private function normalizarFecha(mixed $val): ?string
    {
        $s = trim((string) $val);
        if ($s === '' || $s === '0') { return null; }
        $d = preg_replace('/\D/', '', $s); // solo dígitos
        if (strlen($d) === 8) {
            $y = substr($d, 0, 4); $m = substr($d, 4, 2); $day = substr($d, 6, 2);
            if ((int) $m >= 1 && (int) $m <= 12 && (int) $day >= 1 && (int) $day <= 31) {
                return "$y-$m-$day";
            }
        }
        return null;
    }

    private function fechaMasUnMes(mixed $val): ?string
    {
        $f = $this->normalizarFecha($val);
        if ($f === null) { return null; }
        $dt = DateTime::createFromFormat('Y-m-d', $f);
        return $dt ? $dt->modify('+1 month')->format('Y-m-d') : null;
    }

    private function limpiarNumero(mixed $val): ?string
    {
        if ($val === null || $val === '') { return null; }
        if (is_numeric($val)) { return (string) $val; }
        $s = preg_replace('/[^\d\.\-]/', '', (string) $val);
        return $s === '' || $s === '-' ? null : $s;
    }

    /**
     * Normaliza las llaves de datacredito (cedula, numero_credito) al ancho de 10 que
     * usa el resto del esquema (sifone_asociados/estado_cuenta/cartera). Sifone las trae
     * sin ceros a la izquierda, por eso fallaban los joins por igualdad de string.
     */
    private function normalizarLlave(mixed $val): ?string
    {
        $s = trim((string) $val);
        return $s === '' ? null : str_pad($s, 10, '0', STR_PAD_LEFT);
    }
}
