<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * RAG ligero sobre `chat_conocimiento` (búsqueda FULLTEXT de MySQL/MariaDB).
 *
 * Estrategia:
 *   1) MATCH ... AGAINST en modo lenguaje natural (rápido, con ranking).
 *   2) Fallback por LIKE con las palabras significativas si el FULLTEXT no
 *      devuelve nada (p. ej. consultas muy cortas o sin coincidencias exactas).
 *
 * Devuelve fragmentos listos para inyectar en el system prompt.
 */
final class KnowledgeRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * @return array<int,array{titulo:string,categoria:?string,contenido:string}>
     */
    public function buscar(string $consulta, int $limit = 4): array
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return [];
        }
        $limit = max(1, min(10, $limit));

        // 1) FULLTEXT (lenguaje natural).
        try {
            $st = $this->db->prepare(
                'SELECT titulo, categoria, contenido,
                        MATCH(titulo, palabras_clave, contenido) AGAINST (? IN NATURAL LANGUAGE MODE) AS score
                   FROM chat_conocimiento
                  WHERE estado = 1
                    AND MATCH(titulo, palabras_clave, contenido) AGAINST (? IN NATURAL LANGUAGE MODE) > 0
                  ORDER BY score DESC
                  LIMIT ' . $limit
            );
            $st->execute([$consulta, $consulta]);
            $rows = $st->fetchAll();
            if ($rows) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log('[chat-ai] fulltext: ' . $e->getMessage());
        }

        // 2) Fallback LIKE por palabras significativas (>= 4 caracteres).
        $palabras = preg_split('/[^\p{L}\p{N}]+/u', $consulta) ?: [];
        $palabras = array_values(array_filter($palabras, static fn($w) => mb_strlen($w, 'UTF-8') >= 4));
        $palabras = array_slice(array_unique($palabras), 0, 6);
        if (!$palabras) {
            return [];
        }
        $conds = [];
        $params = [];
        foreach ($palabras as $w) {
            $conds[] = '(titulo LIKE ? OR palabras_clave LIKE ? OR contenido LIKE ?)';
            $like = '%' . $w . '%';
            array_push($params, $like, $like, $like);
        }
        $sql = 'SELECT titulo, categoria, contenido
                  FROM chat_conocimiento
                 WHERE estado = 1 AND (' . implode(' OR ', $conds) . ')
                 LIMIT ' . $limit;
        try {
            $st = $this->db->prepare($sql);
            $st->execute($params);
            return $st->fetchAll();
        } catch (Throwable $e) {
            error_log('[chat-ai] fallback: ' . $e->getMessage());
            return [];
        }
    }
}
