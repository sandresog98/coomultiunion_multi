<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Motor de estados configurable.
 * Lee `solicitudes_transiciones` / `solicitudes_estados` y valida cada cambio
 * de estado según el tipo, el rol de relación del actor y las reglas (gate/comentario).
 * Un solo motor sirve a todos los tipos (PQRS ... crédito), sin ramificar el código.
 */
final class EstadoMachine
{
    public function __construct(private PDO $db) {}

    /** Estado inicial de un tipo (fase CREADA, menor orden). */
    public function estadoInicial(string $tipo): string
    {
        $st = $this->db->prepare(
            "SELECT codigo FROM solicitudes_estados
             WHERE tipo_codigo = ? AND fase = 'CREADA' ORDER BY orden ASC LIMIT 1"
        );
        $st->execute([$tipo]);
        $codigo = $st->fetchColumn();
        if ($codigo === false) {
            throw new RuntimeException("El tipo '$tipo' no tiene estado inicial configurado.");
        }
        return (string) $codigo;
    }

    /** Fase canónica de un (tipo, estado). */
    public function faseDe(string $tipo, string $estado): string
    {
        $st = $this->db->prepare(
            "SELECT fase FROM solicitudes_estados WHERE tipo_codigo = ? AND codigo = ?"
        );
        $st->execute([$tipo, $estado]);
        $fase = $st->fetchColumn();
        if ($fase === false) {
            throw new RuntimeException("Estado '$estado' inválido para el tipo '$tipo'.");
        }
        return (string) $fase;
    }

    /**
     * Valida una transición. Devuelve la fila de transición (incluye efecto/gate)
     * o lanza excepción con el motivo (mensaje apto para el usuario).
     *
     * @param string      $relacionActor 'solicitante'|'responsable'|'creador'|'' (ninguna)
     */
    public function validar(
        string $tipo,
        string $estadoOrigen,
        string $estadoDestino,
        string $relacionActor,
        ?string $comentario
    ): array {
        $st = $this->db->prepare(
            "SELECT * FROM solicitudes_transiciones
             WHERE tipo_codigo = ? AND estado_origen = ? AND estado_destino = ?"
        );
        $st->execute([$tipo, $estadoOrigen, $estadoDestino]);
        $tr = $st->fetch();

        if (!$tr) {
            throw new DomainException("Transición no permitida: $estadoOrigen → $estadoDestino.");
        }
        if ($tr['rol_relacion'] !== null && $tr['rol_relacion'] !== $relacionActor) {
            throw new DomainException("No autorizado: esta acción la realiza el rol '{$tr['rol_relacion']}'.");
        }
        if ((int) $tr['requiere_comentario'] === 1 && trim((string) $comentario) === '') {
            throw new DomainException('Se requiere un comentario para esta transición.');
        }
        // gate_regla se valida en la capa de servicio (handlers por tipo).
        return $tr;
    }

    /** Transiciones disponibles desde un estado (para pintar botones/opciones). */
    public function disponibles(string $tipo, string $estadoOrigen): array
    {
        $st = $this->db->prepare(
            "SELECT estado_destino, rol_relacion, requiere_comentario, gate_regla, efecto
             FROM solicitudes_transiciones
             WHERE tipo_codigo = ? AND estado_origen = ?"
        );
        $st->execute([$tipo, $estadoOrigen]);
        return $st->fetchAll();
    }
}
