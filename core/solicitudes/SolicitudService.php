<?php
declare(strict_types=1);

require_once __DIR__ . '/SolicitudRepository.php';
require_once __DIR__ . '/EstadoMachine.php';
require_once __DIR__ . '/Efectos.php';
require_once __DIR__ . '/../Fondo/FondoBienestarValidacion.php';
require_once __DIR__ . '/../Logger.php';

/**
 * Orquestador del sistema único de solicitudes.
 * Un solo servicio sirve a todos los tipos; el comportamiento lo define la config
 * (solicitudes_tipos / _estados / _transiciones), no el código.
 *
 * "Actor" = quien ejecuta la acción:
 *   ['tipo' => 'asociado'|'interno'|'sistema', 'id' => <cedula|control_usuarios.id>]
 *
 * Familia ampliada (B7): pqrs · actualizar_info · redencion · nuevo_producto ·
 * ticket · boleta · retiro_incentivos · certificado.
 *
 * Reglas por tipo se resuelven en `gate_regla` (handlers aquí) y autobloqueo
 * 1-pendiente-a-la-vez en creación. Los efectos post-transición se ejecutan
 * AFTER COMMIT en su propia transacción (los servicios reusados abren la suya).
 */
final class SolicitudService
{
    private SolicitudRepository $repo;
    private EstadoMachine $maquina;
    private Efectos $efectos;

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? db();
        $this->repo    = new SolicitudRepository($pdo);
        $this->maquina = new EstadoMachine($pdo);
        $this->efectos = new Efectos($this->repo);
    }

    /**
     * Crea una solicitud de cualquier tipo.
     * @param array $detalle campos propios del tipo (se filtran por columnas conocidas).
     *                Para 'boleta', `$detalle['items']` = [{producto_id, cantidad}].
     * @param array $ctx solicitante_tipo | origen | creado_por | actor_tipo | actor_id | responsable_id
     */
    public function crear(string $tipoCodigo, string $cedula, array $detalle, array $ctx = []): array
    {
        $tipo = $this->repo->tipo($tipoCodigo);
        if (!$tipo) {
            throw new DomainException("Tipo de solicitud desconocido o inactivo: $tipoCodigo");
        }
        $solicitanteTipo = $ctx['solicitante_tipo'] ?? 'asociado';

        // Autobloqueo 1-pendiente-a-la-vez (tipos que lo exigen).
        if (in_array($tipoCodigo, ['actualizar_info', 'retiro_incentivos', 'boleta', 'redencion', 'pqrs', 'retiro_aporte'], true)
            && $solicitanteTipo === 'asociado'
            && $this->repo->tienePendiente($tipoCodigo, $cedula)) {
            throw new DomainException('Ya tiene una solicitud de este tipo en trámite. Espere a que se resuelva.');
        }

        // Gate de elegibilidad en creación (saldo disponible para retiros/redenciones).
        if ($tipoCodigo === 'retiro_incentivos') {
            $this->gateSaldoIncentivos($cedula, (float) ($detalle['total_retiro'] ?? 0));
        }

        $estadoInicial = $this->maquina->estadoInicial($tipoCodigo);
        $fase          = $this->maquina->faseDe($tipoCodigo, $estadoInicial);

        $pdo = $this->repo->pdo();
        $pdo->beginTransaction();
        try {
            $id = $this->repo->crearCabecera([
                'tipo_codigo'        => $tipoCodigo,
                'prefijo'            => $tipo['prefijo_radicado'],
                'fase'               => $fase,
                'estado_codigo'      => $estadoInicial,
                'cedula_solicitante' => $cedula,
                'solicitante_tipo'   => $solicitanteTipo,
                'origen'             => $ctx['origen'] ?? 'portal',
                'creado_por'         => $ctx['creado_por'] ?? null,
            ]);

            if ($tipo['detalle_tabla']) {
                $this->repo->insertarDetalle($tipo['detalle_tabla'], $id, $detalle);
            }
            // Items del carrito de boletas.
            if ($tipoCodigo === 'boleta' && !empty($detalle['items'])) {
                $this->repo->insertarItemsBoleta($id, $detalle['items']);
            }
            // Ticket interno: responsable se asigna al crearse.
            if ($solicitanteTipo === 'interno' && !empty($ctx['responsable_id'])) {
                $this->repo->actualizarCabecera($id, ['responsable_id' => (int) $ctx['responsable_id']]);
            }

            $this->repo->evento($id, [
                'tipo_evento' => 'creacion',
                'estado_nuevo' => $estadoInicial,
                'actor_tipo'  => $ctx['actor_tipo'] ?? 'asociado',
                'actor_id'    => $ctx['actor_id'] ?? $cedula,
                'mensaje'     => 'Solicitud creada',
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Logger::log(
            'crear',
            'solicitudes',
            (string) ($ctx['actor_id'] ?? $cedula),
            "Solicitud #$id ($tipoCodigo) creada por $cedula",
            null,
            ['estado' => $estadoInicial, 'fase' => $fase]
        );
        return $this->detalle($id);
    }

    /** Asigna (o reasigna) responsable. Transición hacia el estado 'asignado'. */
    public function asignar(int $id, int $responsableId, array $actor, string $estadoDestino = 'asignado'): array
    {
        $s = $this->repo->cabecera($id);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.');
        }
        $relacion = $this->relacionActor($s, $actor);
        // valida la transición (asignar suele ser rol NULL = cualquiera con permiso)
        $this->maquina->validar($s['tipo_codigo'], $s['estado_codigo'], $estadoDestino, $relacion, null);

        $fase = $this->maquina->faseDe($s['tipo_codigo'], $estadoDestino);
        $respAnterior = $s['responsable_id'] !== null ? (int) $s['responsable_id'] : null;

        $this->repo->actualizarCabecera($id, [
            'estado_codigo'    => $estadoDestino,
            'fase'             => $fase,
            'responsable_id'   => $responsableId,
            'asignado_por'     => $actor['id'] ?? null,
            'fecha_asignacion' => date('Y-m-d H:i:s'),
        ]);
        $this->repo->evento($id, [
            'tipo_evento' => $respAnterior ? 'reasignacion' : 'asignacion',
            'estado_anterior' => $s['estado_codigo'],
            'estado_nuevo' => $estadoDestino,
            'responsable_anterior_id' => $respAnterior,
            'responsable_nuevo_id' => $responsableId,
            'actor_tipo' => $actor['tipo'] ?? 'interno',
            'actor_id'   => (string) ($actor['id'] ?? ''),
            'mensaje'    => 'Responsable asignado',
        ]);
        Logger::log(
            $respAnterior ? 'reasignar' : 'asignar',
            'solicitudes',
            (string) ($actor['id'] ?? null),
            "Solicitud #$id → $estadoDestino (responsable #$responsableId)",
            ['responsable' => $respAnterior],
            ['responsable' => $responsableId]
        );
        return $this->detalle($id);
    }

    /**
     * Reasigna responsable SIN cambiar el estado (patrón ticketera/tickets internos).
     * Solo cambia responsable_id + evento 'reasignacion'.
     */
    public function reasignar(int $id, int $responsableId, array $actor): array
    {
        $s = $this->repo->cabecera($id);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.');
        }
        $respAnterior = $s['responsable_id'] !== null ? (int) $s['responsable_id'] : null;
        $this->repo->actualizarCabecera($id, ['responsable_id' => $responsableId]);
        $this->repo->evento($id, [
            'tipo_evento' => 'reasignacion',
            'responsable_anterior_id' => $respAnterior,
            'responsable_nuevo_id' => $responsableId,
            'actor_tipo' => $actor['tipo'] ?? 'interno',
            'actor_id'   => (string) ($actor['id'] ?? ''),
            'mensaje'    => 'Responsable reasignado',
        ]);
        Logger::log(
            'reasignar',
            'solicitudes',
            (string) ($actor['id'] ?? null),
            "Solicitud #$id → responsable #$responsableId",
            ['responsable' => $respAnterior],
            ['responsable' => $responsableId]
        );
        return $this->detalle($id);
    }

    /**
     * Cambia el estado (responder/avanzar/anular...). Valida transición, rol, gate y comentario;
     * aplica el efecto post-transición DESPUÉS del commit (transacción propia).
     */
    public function transicionar(
        int $id,
        string $estadoDestino,
        array $actor,
        ?string $comentario = null,
        array $respuesta = []
    ): array {
        $s = $this->repo->cabecera($id);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.');
        }
        $relacion = $this->relacionActor($s, $actor);
        $tr = $this->maquina->validar($s['tipo_codigo'], $s['estado_codigo'], $estadoDestino, $relacion, $comentario);

        $fase = $this->maquina->faseDe($s['tipo_codigo'], $estadoDestino);
        $esResolucion = in_array($fase, ['RESUELTA_POSITIVA', 'RESUELTA_NEGATIVA'], true);

        // Gate de regla (handler por tipo) ANTES de mutar estado.
        if (!empty($tr['gate_regla'])) {
            $this->validarGate((string) $tr['gate_regla'], $s);
        }

        $campos = ['estado_codigo' => $estadoDestino, 'fase' => $fase];
        if ($esResolucion) {
            $campos['resultado']            = $respuesta['resultado'] ?? $estadoDestino;
            $campos['respondido_por']       = $actor['id'] ?? null;
            $campos['comentario_respuesta'] = $comentario;
            $campos['ruta_archivo_respuesta'] = $respuesta['ruta_archivo'] ?? null;
            $campos['fecha_respuesta']      = date('Y-m-d H:i:s');
        }

        $pdo = $this->repo->pdo();
        $pdo->beginTransaction();
        try {
            $this->repo->actualizarCabecera($id, $campos);
            $this->repo->evento($id, [
                'tipo_evento' => $esResolucion ? 'respuesta' : 'cambio_estado',
                'estado_anterior' => $s['estado_codigo'],
                'estado_nuevo' => $estadoDestino,
                'actor_tipo' => $actor['tipo'] ?? 'interno',
                'actor_id'   => (string) ($actor['id'] ?? ''),
                'mensaje'    => $comentario,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Efectos post-commit en transacción propia (servicios reusados abren la suya).
        if (!empty($tr['efecto'])) {
            $sActual = $this->repo->cabecera($id);
            try {
                $efectoMsg = $this->efectos->aplicar((string) $tr['efecto'], $sActual);
                if ($efectoMsg) {
                    $this->repo->evento($id, [
                        'tipo_evento' => 'cambio_estado',
                        'actor_tipo'  => 'sistema',
                        'mensaje'     => '[efecto] ' . $efectoMsg,
                    ]);
                }
            } catch (Throwable $e) {
                // No revierte el estado (ya commiteado); registra la falencia para trazabilidad.
                $this->repo->evento($id, [
                    'tipo_evento' => 'cambio_estado',
                    'actor_tipo'  => 'sistema',
                    'mensaje'     => '[efecto] ERROR: ' . $e->getMessage(),
                ]);
                error_log('[solicitudes-efecto] ' . $e->getMessage());
            }
        }
        // Token de calificación al resolver (positiva o negativa), tipos que lo ofrecen.
        if (in_array($fase, ['RESUELTA_POSITIVA', 'RESUELTA_NEGATIVA'], true) && !in_array($s['tipo_codigo'], ['ticket', 'certificado'], true)) {
            $this->generarCalificacion($s);
        }
        Logger::log(
            'transicionar',
            'solicitudes',
            (string) ($actor['id'] ?? null),
            "Solicitud #$id {$s['tipo_codigo']}: {$s['estado_codigo']} → $estadoDestino" . ($esResolucion && $comentario ? " ($comentario)" : ''),
            $esResolucion ? null : ['estado' => $s['estado_codigo'], 'fase' => $s['fase']],
            $esResolucion ? null : ['estado' => $estadoDestino, 'fase' => $fase],
            'warning'
        );
        return $this->detalle($id);
    }

    public function comentar(int $id, array $actor, string $mensaje, array $adjuntos = []): array
    {
        $s = $this->repo->cabecera($id);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.');
        }
        // Cerrada la conversación: cuando la solicitud está resuelta o anulada no se admiten más comentarios.
        if (in_array((string) $s['fase'], ['RESUELTA_POSITIVA', 'RESUELTA_NEGATIVA', 'ANULADA'], true)) {
            throw new DomainException('La solicitud ya fue respondida; no admite más comentarios.');
        }
        if (trim($mensaje) === '') {
            throw new DomainException('El comentario no puede estar vacío.');
        }
        $actorId = (string) ($actor['id'] ?? '');
        $tipoCom = ($actor['tipo'] ?? 'interno') === 'asociado' ? 'solicitante' : 'interno';

        $pdo = $this->repo->pdo();
        $pdo->beginTransaction();
        try {
            $this->repo->comentario($id, $tipoCom, $actorId, $mensaje, null);
            // Evento del comentario: su id liga los adjuntos de este comentario.
            $eventoId = $this->repo->evento($id, [
                'tipo_evento' => 'comentario',
                'actor_tipo'  => $actor['tipo'] ?? 'interno',
                'actor_id'    => $actorId,
                'mensaje'     => $mensaje,
            ]);
            foreach ($adjuntos as $a) {
                if (empty($a['ruta_archivo'])) {
                    continue;
                }
                $this->repo->insertarAdjunto($id, [
                    'contexto'        => 'comentario',
                    'tipo'            => $a['tipo'] ?? 'imagen',
                    'tipo_documento'  => $a['tipo_documento'] ?? null,
                    'nombre_original' => $a['nombre_original'] ?? null,
                    'nombre_archivo'  => $a['nombre_archivo'] ?? null,
                    'ruta_archivo'    => $a['ruta_archivo'],
                    'tamano_archivo'  => $a['tamano_archivo'] ?? null,
                    'tipo_mime'       => $a['tipo_mime'] ?? null,
                    'subido_por'      => $actorId,
                ], $eventoId);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Logger::log('comentar', 'solicitudes', $actorId ?: null, "Comentario en solicitud #$id");
        return $this->detalle($id);
    }

    /** Sube/registra un adjunto en una solicitud (ruta ya persistida por la API). */
    public function adjuntar(int $id, array $actor, array $a): array
    {
        $s = $this->repo->cabecera($id);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.');
        }
        if (($actor['tipo'] ?? '') === 'asociado' && (string) $s['cedula_solicitante'] !== (string) $actor['id']) {
            throw new DomainException('No autorizado', 403);
        }
        $this->repo->insertarAdjunto($id, [
            'contexto' => $a['contexto'] ?? 'solicitud',
            'tipo'     => $a['tipo'] ?? 'imagen',
            'tipo_documento' => $a['tipo_documento'] ?? null,
            'nombre_original' => $a['nombre_original'] ?? null,
            'nombre_archivo'  => $a['nombre_archivo'] ?? null,
            'ruta_archivo'    => $a['ruta_archivo'],
            'tamano_archivo'  => $a['tamano_archivo'] ?? null,
            'tipo_mime'       => $a['tipo_mime'] ?? null,
            'subido_por'      => (string) ($actor['id'] ?? ''),
        ]);
        $this->repo->evento($id, [
            'tipo_evento' => 'adjunto',
            'actor_tipo'  => $actor['tipo'] ?? 'interno',
            'actor_id'    => (string) ($actor['id'] ?? ''),
            'mensaje'     => 'Adjunto registrado',
        ]);
        Logger::log('adjuntar', 'solicitudes', (string) ($actor['id'] ?? null), "Adjunto en solicitud #$id");
        return $this->detalle($id);
    }

    /**
     * Re-autoriza y sirve un adjunto opaco. Solo el dueño (asociado de la solicitud)
     * o staff con permiso solicitudes.* pueden descargarlo.
     */
    public function serveAdjunto(int $adjId, array $actor): array
    {
        $adj = $this->repo->adjunto($adjId);
        if (!$adj) {
            throw new DomainException('Adjunto no encontrado.', 404);
        }
        $s = $this->repo->cabecera((int) $adj['solicitud_id']);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.', 404);
        }
        if (($actor['tipo'] ?? '') === 'asociado') {
            if ((string) $s['cedula_solicitante'] !== (string) $actor['id']) {
                throw new DomainException('No autorizado', 403);
            }
        } elseif (($actor['tipo'] ?? '') !== 'interno') {
            throw new DomainException('No autorizado', 403);
        }
        return $adj;
    }

    public function detalle(int $id): array
    {
        $s = $this->repo->cabecera($id);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.');
        }
        $s['solicitante_nombre'] = $this->repo->nombreSolicitante(
            (string) $s['cedula_solicitante'],
            (string) ($s['solicitante_tipo'] ?? 'asociado')
        );
        $tipo = $this->repo->tipo($s['tipo_codigo']);
        $detalle = $tipo && $tipo['detalle_tabla']
            ? $this->repo->detalle($tipo['detalle_tabla'], $id)
            : null;
        $extra = [];
        if ($s['tipo_codigo'] === 'boleta') {
            $extra = $this->repo->detalleBoleta($id);
        }
        $elegibilidad = null;
        if ($s['tipo_codigo'] === 'fondo' && $detalle && !empty($detalle['convenio_id'])) {
            $detalle['convenio_nombre'] = $this->repo->nombreConvenioFondo((int) $detalle['convenio_id']);
            $validacion = new FondoBienestarValidacion($this->repo->pdo());
            $elegibilidad = $validacion->validarApto((string) $s['cedula_solicitante'], (int) $detalle['convenio_id']);
        }
        return [
            'solicitud'    => $s,
            'detalle'      => $detalle,
            'items'        => $extra['items'] ?? null,
            'boletas'      => $extra['boletas'] ?? null,
            'eventos'      => $this->repo->eventos($id),
            'comentarios'  => $this->repo->comentarios($id),
            'adjuntos'     => $this->repo->adjuntos($id),
            'transiciones' => $this->maquina->disponibles($s['tipo_codigo'], $s['estado_codigo']),
            'elegibilidad' => $elegibilidad,
            'calificacion' => $this->repo->calificacion($id),
        ];
    }

    /**
     * Registra la calificación de satisfacción (estrellas 1-5) del asociado.
     * Se permite cuando la solicitud está RESUELTA (positiva o negativa).
     * Si se pasa $token, autoriza por token en vez de por sesión.
     */
    public function calificar(int $id, array $data, ?string $token = null, ?string $cedula = null): array
    {
        $s = $this->repo->cabecera($id);
        if (!$s) {
            throw new DomainException('Solicitud no encontrada.');
        }
        if (!in_array((string) $s['fase'], ['RESUELTA_POSITIVA', 'RESUELTA_NEGATIVA'], true)) {
            throw new DomainException('Solo se puede calificar una solicitud resuelta.');
        }
        $existe = $this->repo->calificacion($id);
        if ($existe && $existe['puntuacion'] !== null) {
            throw new DomainException('Esta solicitud ya fue calificada.');
        }
        if ($token !== null) {
            $cal = $this->repo->calificacionPorToken($token);
            if (!$cal || (int) $cal['solicitud_id'] !== $id) {
                throw new DomainException('Enlace de calificación inválido.', 403);
            }
        } elseif ($cedula !== null && $cedula !== '' ) {
            if ((string) $s['cedula_solicitante'] !== $cedula) {
                throw new DomainException('No autorizado', 403);
            }
        } else {
            throw new DomainException('No autorizado', 403);
        }

        $puntuacion = (int) ($data['puntuacion'] ?? 0);
        if ($puntuacion < 1 || $puntuacion > 5) {
            throw new DomainException('La puntuación debe estar entre 1 y 5.');
        }
        $comentarios = trim((string) ($data['comentarios'] ?? ''));
        $this->repo->guardarCalificacion($id, $puntuacion, $comentarios !== '' ? $comentarios : null);
        Logger::log('calificar', 'solicitudes', $cedula ?? ('token:' . substr((string) $token, 0, 8)), "Calificación #$id = $puntuacion/5");
        return $this->detalle($id);
    }

    /** Info de una calificación por token (para la página pública de calificación). */
    public function porToken(string $token): array
    {
        $cal = $this->repo->calificacionPorToken($token);
        if (!$cal) {
            throw new DomainException('Enlace de calificación inválido.', 404);
        }
        return [
            'id'                 => (int) $cal['solicitud_id'],
            'numero_radicado'    => $cal['numero_radicado'],
            'tipo_codigo'        => $cal['tipo_codigo'],
            'estado_codigo'      => $cal['estado_codigo'],
            'puntuacion'         => $cal['puntuacion'],
            'comentarios'        => $cal['comentarios'],
            'fecha_calificacion' => $cal['fecha_calificacion'],
        ];
    }

    /** Registra la calificación a partir del token (enlace por correo, sin sesión). */
    public function calificarPorToken(string $token, array $data): array
    {
        $cal = $this->repo->calificacionPorToken($token);
        if (!$cal) {
            throw new DomainException('Enlace de calificación inválido.', 403);
        }
        return $this->calificar((int) $cal['solicitud_id'], $data, $token);
    }

    /** ¿El asociado ya tiene una solicitud pendiente de ese tipo? (autobloqueo) */
    public function tienePendiente(string $tipoCodigo, string $cedula): bool
    {
        return $this->repo->tienePendiente($tipoCodigo, $cedula);
    }

    public function listar(array $filtros, int $limit = 50, int $offset = 0): array
    {
        $res = $this->repo->listar($filtros, $limit, $offset);
        $res['por_tipo'] = $this->repo->contarPorTipo($filtros);
        return $res;
    }

    // ---------- Config (para formularios / UI, sin exponer secretos) ----------

    public function tipos(): array
    {
        return $this->repo->tiposActivos();
    }

    public function estadosDelTipo(string $tipoCodigo): array
    {
        return $this->repo->estadosDe($tipoCodigo);
    }

    public function responsables(): array
    {
        return $this->repo->responsables();
    }

    /** Catálogo de productos-boleta (maneja_stock=1) para armar el carrito. */
    public function productosBoleta(): array
    {
        return $this->repo->productosBoleta();
    }

    // ---------- Categorías de ticket (CRUD catálogo) ----------

    public function ticketCategorias(bool $soloActivas = true): array
    {
        return $this->repo->ticketCategorias($soloActivas);
    }

    public function guardarTicketCategoria(array $d, ?int $actorId = null): int
    {
        $id = $this->repo->guardarTicketCategoria($d, $actorId);
        Logger::log(
            !empty($d['id']) ? 'editar' : 'crear',
            'solicitudes.categorias',
            $actorId !== null ? (string) $actorId : null,
            (!empty($d['id']) ? "Editada" : "Creada") . " categoría de ticket " . ($d['nombre'] ?? "#$id")
        );
        return $id;
    }

    public function eliminarTicketCategoria(int $id, ?int $actorId = null): void
    {
        $this->repo->eliminarTicketCategoria($id);
        Logger::log('eliminar', 'solicitudes.categorias', $actorId !== null ? (string) $actorId : null, "Eliminada categoría de ticket #$id", null, null, 'warning');
    }

    // ---------------- Gates -----------------

    private function validarGate(string $gate, array $s): void
    {
        switch ($gate) {
            case 'saldo_incentivos':
                $det = $this->repo->detalle('solicitudes_det_retiro_incentivos', (int) $s['id']);
                $total = $det ? (float) ($det['total_retiro'] ?? 0) : 0.0;
                $this->gateSaldoIncentivos((string) $s['cedula_solicitante'], $total);
                break;
            case 'stock_boleta':
                $this->gateStockBoleta((int) $s['id']);
                break;
            case 'plazo_desembolso':
                $this->gatePlazoDesembolso((int) $s['id']);
                break;
            default:
                throw new DomainException("Regla de validación desconocida: $gate");
        }
    }

    private function gateSaldoIncentivos(string $cedula, float $solicitado): void
    {
        $disp = $this->disponibleIncentivos($cedula);
        if ($solicitado > $disp) {
            throw new DomainException(
                'El monto supera el disponible de incentivos (' . number_format($disp, 0, ',', '.') . ').'
            );
        }
    }

    /** Gate de desembolso (retiro_aporte): solo se puede desembolsar desde la fecha estimada. */
    private function gatePlazoDesembolso(int $solicitudId): void
    {
        $det = $this->repo->detalle('solicitudes_det_retiro_aporte', $solicitudId);
        $fecha = $det['fecha_estimada_desembolso'] ?? null;
        if (!$fecha) {
            throw new DomainException('La solicitud no tiene fecha estimada de desembolso.');
        }
        if (date('Y-m-d') < $fecha) {
            throw new DomainException(
                'Aún no vence el plazo para desembolsar. Fecha estimada: ' . date('d/m/Y', strtotime($fecha)) . '.'
            );
        }
    }

    /** Disponible de incentivos (aportes_incentivos + comisiones) desde vw_productos. */
    private function disponibleIncentivos(string $cedula): float
    {
        $st = $this->repo->pdo()->prepare(
            "SELECT COALESCE(aportes_incentivos,0) + COALESCE(comisiones,0) AS dispo FROM vw_productos WHERE cedula = ?"
        );
        $st->execute([$cedula]);
        return (float) ($st->fetchColumn() ?? 0);
    }

    /** Genera (si no existe) la calificación con token único al resolver positivamente. */
    private function generarCalificacion(array $s): void
    {
        $pdo = $this->repo->pdo();
        $st = $pdo->prepare('SELECT COUNT(*) FROM solicitudes_calificaciones WHERE solicitud_id = ?');
        $st->execute([(int) $s['id']]);
        if ((int) $st->fetchColumn() > 0) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO solicitudes_calificaciones (solicitud_id, token_acceso, fecha_creacion) VALUES (?,?,NOW())'
        )->execute([(int) $s['id'], $token]);
    }

    /** Gate de emisión: cada producto-boleta debe tener la cantidad solicitada disponible. */
    private function gateStockBoleta(int $solicitudId): void
    {
        $items = $this->repo->itemsBoleta($solicitudId);
        if (!$items) {
            return;
        }
        $ids = array_values(array_unique(array_map(static fn(array $it): int => (int) $it['producto_id'], $items)));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->repo->pdo()->prepare(
            "SELECT producto_id, COUNT(*) AS disponibles
             FROM boleteria_boletas
             WHERE producto_id IN ($in) AND estado = 'disponible'
             GROUP BY producto_id"
        );
        $st->execute($ids);
        $disponibles = [];
        foreach ($st->fetchAll() as $r) {
            $disponibles[(int) $r['producto_id']] = (int) $r['disponibles'];
        }
        foreach ($items as $it) {
            $stock = $disponibles[(int) $it['producto_id']] ?? 0;
            if ($stock < (int) $it['cantidad']) {
                throw new DomainException(
                    "Stock insuficiente para '" . $it['producto_nombre'] . "' — disponible $stock, se requieren {$it['cantidad']}."
                );
            }
        }
    }

    /** Relación del actor respecto a la solicitud: responsable | creador | solicitante | '' */
    private function relacionActor(array $s, array $actor): string
    {
        $id = (string) ($actor['id'] ?? '');
        if ($id !== '' && (string) $s['responsable_id'] === $id) {
            return 'responsable';
        }
        if ($id !== '' && (string) $s['creado_por'] === $id) {
            return 'creador';
        }
        if (($actor['tipo'] ?? '') === 'asociado' && (string) $s['cedula_solicitante'] === $id) {
            return 'solicitante';
        }
        return '';
    }
}