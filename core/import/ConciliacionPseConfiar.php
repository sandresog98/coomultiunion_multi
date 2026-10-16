<?php
declare(strict_types=1);
/**
 * Procesador de relaciones automáticas PSE–Confiar.
 * Lee pagos_pse (estado Aprobada) y pagos_confiar; busca coincidencias por fecha+valor
 * (directas y agrupadas por ciclo_transaccion); inserta en pagos_conciliacion.
 */

require_once __DIR__ . '/../database.php';

class ConciliacionPseConfiar
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = db();
    }

    /**
     * Ejecutar proceso: buscar relaciones válidas e insertar en pagos_conciliacion.
     * @return array{success: bool, message: string, direct: int, grouped: int, inserted: int}
     */
    public function ejecutar(): array
    {
        $pseRows = $this->getPseData();
        $confiarRows = $this->getConfiarData();

        if (empty($pseRows) || empty($confiarRows)) {
            return [
                'success' => true,
                'message' => 'No hay datos PSE aprobados o Confiar para relacionar.',
                'direct' => 0,
                'grouped' => 0,
                'inserted' => 0,
            ];
        }

        $directas = $this->findDirectRelations($pseRows, $confiarRows);
        $agrupadas = $this->findGroupedRelations($pseRows, $confiarRows);
        $todas = array_merge($directas, $agrupadas);
        $validas = $this->validateRelations($todas);
        $inserted = $this->insertRelations($validas);

        return [
            'success' => true,
            'message' => 'OK: ' . $inserted . ' relaciones insertadas (directas: ' . count($directas) . ', agrupadas: ' . count($agrupadas) . ').',
            'direct' => count($directas),
            'grouped' => count($agrupadas),
            'inserted' => $inserted,
        ];
    }

    /** @return list<array{pse_id: string, fecha_resolucion: string, valor: int|float, ciclo_transaccion: int}> */
    private function getPseData(): array
    {
        $sql = "SELECT pse_id, DATE(fecha_hora_resolucion_de_la_transaccion) AS fecha_resolucion, valor, ciclo_transaccion
                FROM pagos_pse WHERE estado = 'Aprobada'
                ORDER BY fecha_hora_resolucion_de_la_transaccion";
        $stmt = $this->conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array{confiar_id: string, fecha: string|null, valor_consignacion: float|null}> */
    private function getConfiarData(): array
    {
        $sql = "SELECT confiar_id, fecha, valor_consignacion
                FROM pagos_confiar
                WHERE valor_consignacion > 0
                  AND (
                    tipo_transaccion = 'Pago ACH'
                    OR (tipo_transaccion IS NULL AND descripcion REGEXP '[[:<:]]ACH[[:>:]]')
                  )
                ORDER BY fecha";
        $stmt = $this->conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Relaciones directas: misma fecha y mismo valor.
     * @param list<array{pse_id: string, fecha_resolucion: string, valor: int|float, ciclo_transaccion: int}> $pseRows
     * @param list<array{confiar_id: string, fecha: string|null, valor_consignacion: float|null}> $confiarRows
     * @return list<array{pse_id: string, confiar_id: string, method: string}>
     */
    private function findDirectRelations(array $pseRows, array $confiarRows): array
    {
        $relaciones = [];
        foreach ($pseRows as $pse) {
            $fecha = $pse['fecha_resolucion'] ?? null;
            $valor = $this->num($pse['valor'] ?? 0);
            foreach ($confiarRows as $confiar) {
                $cfFecha = $confiar['fecha'] ?? null;
                $cfValor = $this->num($confiar['valor_consignacion'] ?? 0);
                if ($fecha === $cfFecha && abs($valor - $cfValor) < 0.01) {
                    $relaciones[] = [
                        'pse_id' => $pse['pse_id'],
                        'confiar_id' => $confiar['confiar_id'],
                        'method' => 'direct',
                    ];
                }
            }
        }
        return $relaciones;
    }

    /**
     * Relaciones agrupadas: PSE agrupados por fecha_resolucion + ciclo_transaccion; suma valor; coincide con Confiar por fecha y valor total.
     * @param list<array{pse_id: string, fecha_resolucion: string, valor: int|float, ciclo_transaccion: int}> $pseRows
     * @param list<array{confiar_id: string, fecha: string|null, valor_consignacion: float|null}> $confiarRows
     * @return list<array{pse_id: string, confiar_id: string, method: string}>
     */
    private function findGroupedRelations(array $pseRows, array $confiarRows): array
    {
        $grupos = [];
        foreach ($pseRows as $pse) {
            $key = ($pse['fecha_resolucion'] ?? '') . '|' . (string) ($pse['ciclo_transaccion'] ?? '');
            if (!isset($grupos[$key])) {
                $grupos[$key] = ['fecha' => $pse['fecha_resolucion'] ?? null, 'valor' => 0.0, 'pse_ids' => []];
            }
            $grupos[$key]['valor'] += $this->num($pse['valor'] ?? 0);
            $grupos[$key]['pse_ids'][] = $pse['pse_id'];
        }

        $relaciones = [];
        foreach ($grupos as $group) {
            $fecha = $group['fecha'];
            $valorTotal = $group['valor'];
            foreach ($confiarRows as $confiar) {
                $cfFecha = $confiar['fecha'] ?? null;
                $cfValor = $this->num($confiar['valor_consignacion'] ?? 0);
                if ($fecha === $cfFecha && abs($valorTotal - $cfValor) < 0.01) {
                    foreach ($group['pse_ids'] as $pseId) {
                        $relaciones[] = [
                            'pse_id' => $pseId,
                            'confiar_id' => $confiar['confiar_id'],
                            'method' => 'grouped',
                        ];
                    }
                }
            }
        }
        return $relaciones;
    }

    private function num(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        return 0.0;
    }

    /**
     * Filtrar duplicados: no insertar par (pse_id, confiar_id) ya existente; un pse_id solo una vez en esta ejecución.
     * @param list<array{pse_id: string, confiar_id: string, method: string}> $relaciones
     * @return list<array{pse_id: string, confiar_id: string, method: string}>
     */
    private function validateRelations(array $relaciones): array
    {
        $existing = $this->getExistingRelations();
        $seenPairs = [];
        $seenPse = [];
        $validas = [];
        foreach ($relaciones as $r) {
            $pseId = $r['pse_id'];
            $confiarId = $r['confiar_id'];
            $pair = $pseId . '|' . $confiarId;
            if (isset($existing[$pair])) {
                continue;
            }
            if (isset($seenPairs[$pair])) {
                continue;
            }
            if (isset($seenPse[$pseId])) {
                continue;
            }
            $seenPairs[$pair] = true;
            $seenPse[$pseId] = true;
            $validas[] = $r;
        }
        return $validas;
    }

    /** @return array<string, true> pares "pse_id|confiar_id" */
    private function getExistingRelations(): array
    {
        $stmt = $this->conn->query("SELECT pse_id, confiar_id FROM pagos_conciliacion");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $out = [];
        foreach ($rows as $row) {
            $out[$row[0] . '|' . $row[1]] = true;
        }
        return $out;
    }

    /**
     * @param list<array{pse_id: string, confiar_id: string, method: string}> $relaciones
     */
    private function insertRelations(array $relaciones): int
    {
        if (empty($relaciones)) {
            return 0;
        }
        $sql = "INSERT IGNORE INTO pagos_conciliacion (pse_id, confiar_id, tipo) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $count = 0;
        foreach ($relaciones as $r) {
            $tipo = $r['method'] === 'direct' ? 'directa' : ($r['method'] === 'grouped' ? 'grupal' : null);
            $stmt->execute([$r['pse_id'], $r['confiar_id'], $tipo]);
            $count += $stmt->rowCount();
        }
        return $count;
    }
}
