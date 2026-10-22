<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/import/ExcelReader.php';
require_once __DIR__ . '/ContactoService.php';
require_once __DIR__ . '/PhoneNormalizer.php';
require_once dirname(__DIR__) . '/config/ConfigService.php';

/**
 * Lectura y validación de bases CSV/XLSX para campañas.
 * No escribe en la base: devuelve filas normalizadas y un resumen.
 */
final class BaseImportService
{
    private ContactoService $contactos;
    private ConfigService $cfg;
    /** @var array<string,array<string,mixed>|null> */
    private array $cacheAsociados = [];
    /** @var array<string,array<string,mixed>|null> */
    private array $cacheContactos = [];

    public function __construct(?ContactoService $contactos = null, ?ConfigService $cfg = null)
    {
        $this->contactos = $contactos ?? new ContactoService();
        $this->cfg = $cfg ?? new ConfigService();
    }

    /**
     * @return array{archivo:string,headers:array<int,string>,rows:array<int,array<string,mixed>>}
     */
    public function leerArchivo(string $path, string $ext): array
    {
        if (!is_file($path)) {
            throw new DomainException('Archivo no encontrado.');
        }
        $ext = strtolower($ext);
        if ($ext === 'csv' || $ext === 'txt') {
            [$headers, $rows] = $this->leerCsv($path);
        } elseif ($ext === 'xlsx' || $ext === 'xls') {
            [$headers, $rows] = $this->leerExcel($path);
        } else {
            throw new DomainException('Formato no soportado. Use CSV, XLS o XLSX.');
        }
        return ['archivo' => basename($path), 'headers' => $headers, 'rows' => $rows];
    }

    /**
     * Valida filas y devuelve las primeras $limiteDetalle + resumen completo.
     * @param array<int,array<string,mixed>> $rows
     * @return array{rows:array<int,array<string,mixed>>,resumen:array<string,int>,total:int}
     */
    public function validar(array $rows, string $canal, int $limiteDetalle = 500): array
    {
        if (!in_array($canal, ['sms', 'whatsapp', 'email'], true)) {
            throw new DomainException('Canal inválido.');
        }
        $max = max(1, (int) $this->cfg->get('com_max_destinatarios', '50000'));
        if (count($rows) > $max) {
            throw new DomainException("La base supera el máximo permitido de {$max} registros.");
        }

        $out = [];
        $seen = [];
        $resumen = [
            'total' => count($rows),
            'ok' => 0,
            'error' => 0,
            'omitido' => 0,
            'sin_opt_in' => 0,
            'opt_out' => 0,
            'duplicado' => 0,
            'sin_contacto' => 0,
        ];

        foreach ($rows as $i => $raw) {
            $row = $this->normalizarFila($raw, $canal, $i + 1);
            $key = $row['estado'] === 'ok' ? ($row['canal'] . '|' . $row['destino']) : null;
            if ($key !== null && isset($seen[$key])) {
                $row['estado'] = 'omitido';
                $row['motivo'] = 'duplicado';
                $resumen['duplicado']++;
            } elseif ($key !== null) {
                $seen[$key] = true;
            }

            if ($row['estado'] === 'ok') {
                $resumen['ok']++;
            } elseif ($row['estado'] === 'error') {
                $resumen['error']++;
            } else {
                $resumen['omitido']++;
                if ($row['motivo'] === 'sin_opt_in') { $resumen['sin_opt_in']++; }
                if ($row['motivo'] === 'opt_out') { $resumen['opt_out']++; }
                if ($row['motivo'] === 'sin_contacto') { $resumen['sin_contacto']++; }
            }
            $out[] = $row;
        }

        return [
            'rows' => array_slice($out, 0, max(1, min(2000, $limiteDetalle))),
            'resumen' => $resumen,
            'total' => count($out),
        ];
    }

    /** Valida una sola fila (envío individual). */
    public function validarFila(array $raw, string $canal): array
    {
        return $this->normalizarFila($raw, $canal, 1);
    }

    private function normalizarFila(array $raw, string $canal, int $n): array
    {
        $cedula = trim((string) ($raw['cedula'] ?? ''));
        $nombre = trim((string) ($raw['nombre'] ?? ''));
        $telRaw = trim((string) ($raw['telefono'] ?? ''));
        $mailRaw = trim((string) ($raw['email'] ?? ''));
        $optInRaw = $raw['opt_in'] ?? null;
        $optOutRaw = $raw['opt_out'] ?? null;

        if ($cedula !== '' && !array_key_exists($cedula, $this->cacheAsociados)) {
            $this->cacheAsociados[$cedula] = $this->contactos->fichaAsociado($cedula);
        }
        $asociado = $cedula !== '' ? ($this->cacheAsociados[$cedula] ?? null) : null;
        if ($asociado) {
            $nombre = $nombre !== '' ? $nombre : (string) ($asociado['nombre'] ?? '');
            $telRaw = $telRaw !== '' ? $telRaw : (string) ($asociado['telefono'] ?? '');
            $mailRaw = $mailRaw !== '' ? $mailRaw : (string) ($asociado['email'] ?? '');
            $cedula = (string) ($asociado['cedula'] ?? $cedula);
        }

        $destino = '';
        $error = '';
        if ($canal === 'email') {
            $destino = (string) PhoneNormalizer::normalizeEmail($mailRaw);
            if ($destino === '') {
                $error = 'Correo inválido o vacío.';
            }
        } else {
            $destino = (string) PhoneNormalizer::normalize($telRaw);
            if ($destino === '') {
                $error = 'Teléfono inválido o vacío.';
            }
        }

        // Variables libres: columnas distintas de las conocidas.
        $conocidas = ['cedula', 'nombre', 'telefono', 'email', 'opt_in', 'opt_out'];
        $variables = [];
        foreach ($raw as $k => $v) {
            if (!in_array($k, $conocidas, true) && $v !== null && trim((string) $v) !== '') {
                $variables[(string) $k] = (string) $v;
            }
        }

        $ckey = $canal . '|' . $destino;
        if ($destino !== '' && !array_key_exists($ckey, $this->cacheContactos)) {
            $this->cacheContactos[$ckey] = $this->contactos->obtener($canal, $destino);
        }
        $contacto = $destino !== '' ? ($this->cacheContactos[$ckey] ?? null) : null;
        $optIn = $this->parseBool($optInRaw);
        if ($optIn === null && $asociado && (int) ($asociado['opt_in'] ?? 0) === 1) {
            $optIn = true;
        }
        $optOut = $this->parseBool($optOutRaw);
        $motivo = '';
        $estado = 'ok';

        if ($error !== '') {
            $estado = 'error';
            $motivo = $error;
        } elseif ($optOut === true || ($contacto && (int) $contacto['opt_out'] === 1)) {
            $estado = 'omitido';
            $motivo = 'opt_out';
        } elseif ($optIn === true || ($contacto && (int) $contacto['opt_in'] === 1)) {
            $estado = 'ok';
        } elseif ((int) $this->cfg->get('com_consentimiento_obligatorio', '1') === 1) {
            $estado = 'omitido';
            $motivo = 'sin_opt_in';
        }

        if ($estado === 'ok' && $contacto === null && $cedula === '') {
            // No es un error: puede ser un contacto externo con opt-in explícito.
        }

        return [
            'n' => $n,
            'estado' => $estado,
            'motivo' => $motivo,
            'cedula' => $cedula,
            'nombre' => $nombre,
            'canal' => $canal,
            'destino' => $destino,
            'telefono' => $canal === 'email' ? (string) PhoneNormalizer::normalize($telRaw) : $destino,
            'email' => $canal === 'email' ? $destino : (string) PhoneNormalizer::normalizeEmail($mailRaw),
            'opt_in' => $optIn === true,
            'opt_out' => $optOut === true,
            'variables' => $variables,
            'contacto_id' => $contacto ? (int) $contacto['id'] : 0,
        ];
    }

    private function parseBool($v): ?bool
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['1', 'si', 'sí', 's', 'true', 'x', 'ok', 'autoriza', 'autorizado', 'acepta'], true)) {
            return true;
        }
        if (in_array($s, ['0', 'no', 'n', 'false', 'baja', 'rechaza'], true)) {
            return false;
        }
        return null;
    }

    /** @return array{0:array<int,string>,1:array<int,array<string,mixed>>} */
    private function leerCsv(string $path): array
    {
        $fh = fopen($path, 'rb');
        if (!$fh) {
            throw new DomainException('No se pudo abrir el archivo.');
        }
        $first = (string) fgets($fh);
        $delim = ',';
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $delim = (string) array_key_first($counts);
        rewind($fh);

        $headers = [];
        $rows = [];
        $firstLine = true;
        while (($cols = fgetcsv($fh, 0, $delim)) !== false) {
            if ($cols === [null] || count(array_filter($cols, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if ($firstLine) {
                $headers = $this->normalizarHeaders($cols);
                $firstLine = false;
                continue;
            }
            $assoc = [];
            foreach ($cols as $i => $v) {
                $key = $headers[$i] ?? ('col_' . $i);
                $assoc[$key] = ExcelReader::ensureUtf8(trim((string) $v));
            }
            $rows[] = $assoc;
        }
        fclose($fh);
        return [$headers, $rows];
    }

    /** @return array{0:array<int,string>,1:array<int,array<string,mixed>>} */
    private function leerExcel(string $path): array
    {
        $reader = new ExcelReader();
        if (!$reader->load($path)) {
            throw new DomainException('No se pudo leer el Excel: ' . $reader->getError());
        }
        $raw = $reader->rows(0, 0);
        if (!$raw) {
            return [[], []];
        }
        $headers = $this->normalizarHeaders($raw[0] ?? []);
        $rows = [];
        for ($i = 1; $i < count($raw); $i++) {
            $cols = $raw[$i];
            if (!is_array($cols) || count(array_filter($cols, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($cols as $j => $v) {
                $key = $headers[$j] ?? ('col_' . $j);
                $assoc[$key] = ExcelReader::ensureUtf8(trim((string) $v));
            }
            $rows[] = $assoc;
        }
        return [$headers, $rows];
    }

    /** @param array<int,mixed> $cols */
    private function normalizarHeaders(array $cols): array
    {
        $out = [];
        foreach ($cols as $c) {
            $h = trim((string) $c);
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
            $orig = $h;
            $h = strtolower($h);
            $h = strtr($h, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
            $h = preg_replace('/[^a-z0-9]+/', '_', $h) ?? $h;
            $h = trim($h, '_');
            $alias = [
                'cedula' => 'cedula', 'documento' => 'cedula', 'identificacion' => 'cedula', 'cc' => 'cedula',
                'telefono' => 'telefono', 'celular' => 'telefono', 'movil' => 'telefono', 'whatsapp' => 'telefono',
                'phone' => 'telefono', 'tel' => 'telefono',
                'email' => 'email', 'correo' => 'email', 'mail' => 'email', 'e_mail' => 'email',
                'nombre' => 'nombre', 'nombre_completo' => 'nombre', 'asociado' => 'nombre',
                'opt_in' => 'opt_in', 'optin' => 'opt_in', 'autoriza' => 'opt_in', 'consentimiento' => 'opt_in',
                'opt_out' => 'opt_out', 'optout' => 'opt_out', 'baja' => 'opt_out',
            ];
            $out[] = $alias[$h] ?? ($h !== '' ? $h : $orig);
        }
        return $out;
    }
}
