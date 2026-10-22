<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Métricas/registro del Asistente IA (tablas chat_*) para la UI staff.
 *
 * Solo lectura. NO expone el contenido de los mensajes: muestra por conversación
 * el asociado, nº de mensajes, tokens, costo estimado (USD) y duración.
 */
final class ChatMetricasService
{
    /** Mapa de ordenamiento permitido (whitelist → expresión SQL). */
    private const ORDER_MAP = [
        'id'                  => 's.id',
        'cedula'              => 's.cedula',
        'estado'              => 's.estado',
        'fecha_creacion'      => 's.fecha_creacion',
        'fecha_actualizacion' => 's.fecha_actualizacion',
        'mensajes'            => 'mensajes',
        'tokens'              => '(tokens_prompt + tokens_completion)',
        'costo_usd'           => 'costo_usd',
        'duracion_seg'        => 'duracion_seg',
    ];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Listado paginado de conversaciones con métricas agregadas. */
    public function listar(array $f): array
    {
        $orderKey = trim((string) ($f['order'] ?? 'fecha_actualizacion'));
        if (!isset(self::ORDER_MAP[$orderKey])) {
            $orderKey = 'fecha_actualizacion';
        }
        $orderCol = self::ORDER_MAP[$orderKey];
        $orderDir = strtoupper(trim((string) ($f['dir'] ?? 'DESC'))) === 'ASC' ? 'ASC' : 'DESC';

        [$whereSQL, $params] = $this->filtros($f);
        $limit  = min(200, max(1, (int) ($f['limit'] ?? 20)));
        $offset = max(0, (int) ($f['offset'] ?? 0));

        $sql = "SELECT s.id, s.cedula, s.titulo, s.estado, s.fecha_creacion, s.fecha_actualizacion,
                       COALESCE(a.nombre, '') AS nombre,
                       COUNT(m.id)                          AS mensajes,
                       COALESCE(SUM(m.tokens_prompt), 0)     AS tokens_prompt,
                       COALESCE(SUM(m.tokens_completion), 0) AS tokens_completion,
                       COALESCE(SUM(m.tokens_cached), 0)     AS tokens_cached,
                       COALESCE(SUM(m.costo_usd), 0)         AS costo_usd,
                       TIMESTAMPDIFF(SECOND, MIN(m.fecha_creacion), MAX(m.fecha_creacion)) AS duracion_seg,
                       (SELECT COUNT(*) FROM chat_feedback f JOIN chat_mensajes mm ON mm.id = f.mensaje_id
                         WHERE mm.sesion_id = s.id AND f.util = 1) AS fb_util,
                       (SELECT COUNT(*) FROM chat_feedback f JOIN chat_mensajes mm ON mm.id = f.mensaje_id
                         WHERE mm.sesion_id = s.id AND f.util = 0) AS fb_no_util
                  FROM chat_sesiones s
                  LEFT JOIN vw_asociados a ON CAST(a.cedula AS UNSIGNED) = CAST(s.cedula AS UNSIGNED)
                  LEFT JOIN chat_mensajes m ON m.sesion_id = s.id AND m.rol IN ('user','assistant')
                 {$whereSQL}
                 GROUP BY s.id
                 ORDER BY {$orderCol} {$orderDir}
                 LIMIT {$limit} OFFSET {$offset}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        foreach ($rows as &$r) {
            $r['mensajes']     = (int) $r['mensajes'];
            $r['tokens_total'] = (int) $r['tokens_prompt'] + (int) $r['tokens_completion'];
            $r['costo_usd']    = (float) $r['costo_usd'];
            $r['duracion_seg'] = (int) ($r['duracion_seg'] ?? 0);
            $r['fb_util']      = (int) ($r['fb_util'] ?? 0);
            $r['fb_no_util']   = (int) ($r['fb_no_util'] ?? 0);
            $r['abierta']      = ((string) $r['estado'] === 'activa');
        }
        unset($r);

        $cnt = $this->db->prepare(
            "SELECT COUNT(*) FROM chat_sesiones s
              LEFT JOIN vw_asociados a ON CAST(a.cedula AS UNSIGNED) = CAST(s.cedula AS UNSIGNED)
              {$whereSQL}"
        );
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Totales aplicando los filtros + opciones de filtro (globales). */
    public function resumen(array $f = []): array
    {
        [$whereSQL, $params] = $this->filtros($f);

        $tot = $this->db->prepare(
            "SELECT
               COUNT(DISTINCT s.id)                                          AS sesiones,
               COUNT(DISTINCT CASE WHEN s.estado = 'activa'  THEN s.id END)  AS sesiones_activas,
               COUNT(DISTINCT CASE WHEN s.estado = 'cerrada' THEN s.id END)  AS sesiones_cerradas,
               COALESCE(SUM(m.rol = 'user'), 0)      AS preguntas,
               COALESCE(SUM(m.rol = 'assistant'), 0) AS respuestas,
               COALESCE(SUM(m.tokens_prompt), 0)     AS tokens_prompt,
               COALESCE(SUM(m.tokens_completion), 0) AS tokens_completion,
               COALESCE(SUM(m.tokens_prompt + m.tokens_completion), 0) AS tokens,
               COALESCE(SUM(m.costo_usd), 0)         AS costo_usd
              FROM chat_sesiones s
              LEFT JOIN vw_asociados a ON CAST(a.cedula AS UNSIGNED) = CAST(s.cedula AS UNSIGNED)
              LEFT JOIN chat_mensajes m ON m.sesion_id = s.id AND m.rol IN ('user','assistant')
             {$whereSQL}"
        );
        $tot->execute($params);
        $tot = $tot->fetch();

        $fb = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(f.util = 1), 0) AS utiles,
                    COALESCE(SUM(f.util = 0), 0) AS no_utiles
               FROM chat_feedback f
               JOIN chat_mensajes mm ON mm.id = f.mensaje_id
               JOIN chat_sesiones s ON s.id = mm.sesion_id
               LEFT JOIN vw_asociados a ON CAST(a.cedula AS UNSIGNED) = CAST(s.cedula AS UNSIGNED)
              {$whereSQL}"
        );
        $fb->execute($params);
        $fb = $fb->fetch();

        // Opciones del filtro "usuario": SIEMPRE globales (no se encogen al filtrar).
        $cedulas = $this->db->query(
            "SELECT DISTINCT s.cedula, COALESCE(a.nombre, '') AS nombre
               FROM chat_sesiones s
               LEFT JOIN vw_asociados a ON CAST(a.cedula AS UNSIGNED) = CAST(s.cedula AS UNSIGNED)
              ORDER BY nombre ASC, s.cedula ASC
              LIMIT 1000"
        )->fetchAll();

        return [
            'totales'  => $tot,
            'cedulas'  => $cedulas,
            'feedback' => $fb,
            'estados'  => ['activa', 'cerrada', 'archivada'],
        ];
    }

    /**
     * Cabecera de una conversación + sus mensajes (visor de staff, solo lectura).
     * Se accede por `id` de sesión (contexto staff con permiso chat.metricas).
     */
    public function mensajes(int $sesionId): array
    {
        $st = $this->db->prepare(
            "SELECT s.id, s.cedula, s.titulo, s.estado, s.fecha_creacion, s.fecha_actualizacion,
                    COALESCE(a.nombre, '') AS nombre
               FROM chat_sesiones s
               LEFT JOIN vw_asociados a ON CAST(a.cedula AS UNSIGNED) = CAST(s.cedula AS UNSIGNED)
              WHERE s.id = ? LIMIT 1"
        );
        $st->execute([$sesionId]);
        $ses = $st->fetch();
        if (!$ses) {
            throw new DomainException('Conversación no encontrada.');
        }

        $st = $this->db->prepare(
            "SELECT m.id, m.rol, m.contenido, m.estado, m.modelo, m.tokens_prompt, m.tokens_completion,
                    m.tokens_cached, m.latencia_ms, m.costo_usd, m.fecha_creacion,
                    f.util, f.comentario
               FROM chat_mensajes m
               LEFT JOIN chat_feedback f ON f.mensaje_id = m.id
              WHERE m.sesion_id = ? AND m.rol IN ('user','assistant')
              ORDER BY m.id ASC"
        );
        $st->execute([$sesionId]);
        $msgs = $st->fetchAll();
        foreach ($msgs as &$m) {
            $m['costo_usd'] = (float) ($m['costo_usd'] ?? 0);
            $m['util']      = ($m['util'] === null) ? null : (int) $m['util'];
        }
        unset($m);

        return ['sesion' => $ses, 'mensajes' => $msgs];
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function filtros(array $f): array
    {
        $where = [];
        $params = [];
        if (!empty($f['cedula'])) {
            $where[] = 's.cedula LIKE ?';
            $params[] = '%' . $f['cedula'] . '%';
        }
        if (!empty($f['estado'])) {
            $where[] = 's.estado = ?';
            $params[] = $f['estado'];
        }
        if (!empty($f['fecha_desde'])) {
            $where[] = 's.fecha_creacion >= ?';
            $params[] = $f['fecha_desde'] . ' 00:00:00';
        }
        if (!empty($f['fecha_hasta'])) {
            $where[] = 's.fecha_creacion <= ?';
            $params[] = $f['fecha_hasta'] . ' 23:59:59';
        }
        if (!empty($f['q'])) {
            $where[] = '(s.titulo LIKE ? OR s.cedula LIKE ? OR a.nombre LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }
}
