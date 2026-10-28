<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once __DIR__ . '/TipoComunicacionService.php';

/**
 * Comunicaciones de cobranza (B6). `estado='comprometido'` exige fecha_compromiso + monto_compromiso.
 * El cumplimiento del compromiso se calcula en CobranzaService::compromisosPorCedulas (transacciones).
 */
final class ComunicacionService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function opcionesTipos(): array
    {
        return (new TipoComunicacionService($this->db))->all(true);
    }

    /** Historial de compromisos de un asociado (comunicaciones con fecha/monto compromiso) + cumplimiento. */
    public function historialCompromisos(string $cedula, int $limit = 100): array
    {
        $st = $this->db->prepare(
            "SELECT c.*, u.nombre_completo AS usuario_nombre,
                    COALESCE((
                        SELECT SUM(t.valor_total)
                        FROM transacciones t
                        WHERE t.cedula = c.asociado_cedula
                          AND t.fecha_creacion >= c.fecha
                          AND (c.fecha_compromiso IS NULL
                               OR t.fecha_creacion <= CONCAT(c.fecha_compromiso, ' 23:59:59'))
                    ), 0) AS suma_pagado
             FROM cobranza_comunicaciones c
             LEFT JOIN usuarios u ON u.id = c.creado_por
             WHERE c.asociado_cedula = ? AND (c.fecha_compromiso IS NOT NULL OR (c.monto_compromiso IS NOT NULL AND c.monto_compromiso > 0))
             ORDER BY c.fecha DESC, c.id DESC LIMIT ?"
        );
        $st->bindValue(1, $cedula);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        $hoy = date('Y-m-d');
        foreach ($rows as &$r) {
            $suma = (float) ($r['suma_pagado'] ?? 0);
            $monto = (float) $r['monto_compromiso'];
            if ($r['fecha_compromiso']) {
                if ($hoy <= $r['fecha_compromiso']) $r['estado_compromiso'] = $suma >= $monto ? 'cumplido' : 'pendiente_tiempo';
                else $r['estado_compromiso'] = $suma >= $monto ? 'cumplido' : 'incumplido';
            } else {
                $r['estado_compromiso'] = null;
            }
            $r['suma_pagado'] = $suma;
        }
        return $rows;
    }

    public function listarPorCedula(string $cedula, int $limit = 50): array
    {
        $st = $this->db->prepare(
            "SELECT c.*, u.nombre_completo AS usuario_nombre
             FROM cobranza_comunicaciones c
             LEFT JOIN usuarios u ON u.id = c.creado_por
             WHERE c.asociado_cedula = ?
             ORDER BY c.fecha DESC, c.id DESC LIMIT ?"
        );
        $st->bindValue(1, $cedula);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function crear(array $d, ?array $actor = null): int
    {
        [$ced, $tipoOrig, $tipo, $est, $fecha, $fecComp, $monto, $com] = $this->normalizar($d);
        $st = $this->db->prepare(
            "INSERT INTO cobranza_comunicaciones
             (asociado_cedula, tipo_origen, tipo_comunicacion, estado, fecha, fecha_compromiso, monto_compromiso, comentario, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([$ced, $tipoOrig, $tipo, $est, $fecha, $fecComp, $monto, $com, $actor['id'] ?? null]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'cobranza.comunicacion', $actor['id'] ?? null, "Comunicación #$id para $ced ($tipo/$est)");
        return $id;
    }

    public function editar(int $id, array $d, ?array $actor = null): void
    {
        $this->verificarDueno($id, $actor);
        [, , $tipo, $est, $fecha, $fecComp, $monto, $com] = $this->normalizar($d, $id);
        $this->db->prepare(
            "UPDATE cobranza_comunicaciones SET tipo_comunicacion=?, estado=?, fecha=?, fecha_compromiso=?, monto_compromiso=?, comentario=? WHERE id=?"
        )->execute([$tipo, $est, $fecha, $fecComp, $monto, $com, $id]);
        Logger::log('editar', 'cobranza.comunicacion', $actor['id'] ?? null, "Editada comunicación #$id");
    }

    public function eliminar(int $id, ?array $actor = null): void
    {
        $this->verificarDueno($id, $actor);
        $this->db->prepare("DELETE FROM cobranza_comunicaciones WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'cobranza.comunicacion', $actor['id'] ?? null, "Eliminada comunicación #$id", null, null, 'warning');
    }

    /** Solo el creador puede editar/eliminar (regla B6). */
    private function verificarDueno(int $id, ?array $actor): void
    {
        $st = $this->db->prepare("SELECT creado_por FROM cobranza_comunicaciones WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) throw new DomainException('Comunicación no encontrada.');
        if ((int) ($r['creado_por'] ?? 0) !== (int) ($actor['id'] ?? -1)) {
            throw new DomainException('Solo el creador puede modificar/eliminar esta comunicación.');
        }
    }

    private function normalizar(array $d, ?int $id = null): array
    {
        $ced = trim((string) ($d['asociado_cedula'] ?? ''));
        if ($ced === '') throw new DomainException('Falta el asociado.');
        $tipoOrig = trim((string) ($d['tipo_origen'] ?? 'credito'));
        if (!in_array($tipoOrig, ['credito', 'aportes'], true)) $tipoOrig = 'credito';
        $tipo = trim((string) ($d['tipo_comunicacion'] ?? ''));
        $est = trim((string) ($d['estado'] ?? ''));
        if ($tipo === '') throw new DomainException('Falta el tipo de comunicación.');
        if ($est === '') throw new DomainException('Falta el estado de la comunicación.');
        // Validar existencia en catálogo (si aplica)
        $cat = (new TipoComunicacionService($this->db))->existe($tipo, $est);
        if (!$cat) throw new DomainException('Tipo/estado de comunicación no configurado.');

        $fecha = trim((string) ($d['fecha'] ?? ''));
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}( [0-9:]+)?$/', $fecha)) throw new DomainException('Fecha inválida.');

        $fecComp = trim((string) ($d['fecha_compromiso'] ?? '')) ?: null;
        $monto = $d['monto_compromiso'] ?? null;
        $monto = $monto !== null && $monto !== '' ? (float) $monto : null;
        $com = trim((string) ($d['comentario'] ?? '')) ?: null;
        if ($est === 'comprometido' && ($fecComp === null || $monto === null)) {
            throw new DomainException('Para un compromiso de pago debe indicar fecha y monto del compromiso.');
        }
        return [$ced, $tipoOrig, $tipo, $est, $fecha, $fecComp, $monto, $com];
    }
}