<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/portal/PortalService.php';
require_once dirname(__DIR__, 2) . '/solicitudes/SolicitudService.php';
require_once dirname(__DIR__, 2) . '/Logger.php';

/**
 * Herramientas (function calling) del Asistente IA del portal.
 *
 * REGLA DE ORO: toda consulta o acción usa la cédula de la SESIÓN ($this->cedula),
 * NUNCA un argumento del modelo. El modelo solo elige la herramienta y sus datos
 * de negocio; el servidor fija la identidad y valida la propiedad.
 *
 * Resultado de cada herramienta: array JSON-serializable que se devuelve al modelo
 * como mensaje `role: tool` (datos RESUMIDOS para control de tokens y privacidad).
 */
final class AsistenteTools
{
    private string $cedula;
    private PortalService $portal;
    private SolicitudService $solicitudes;

    public function __construct(string $cedula, ?PortalService $portal = null, ?SolicitudService $solicitudes = null)
    {
        $this->cedula      = $cedula;
        $this->portal      = $portal ?? new PortalService();
        $this->solicitudes = $solicitudes ?? new SolicitudService();
    }

    /** Definiciones de herramientas para el LLM (formato OpenAI `tools`). */
    public function definiciones(): array
    {
        $str  = static fn(string $d = '') => ['type' => 'string', 'description' => $d];
        $int  = static fn(string $d = '') => ['type' => 'integer', 'description' => $d];
        $num  = static fn(string $d = '') => ['type' => 'number',  'description' => $d];
        $bool = static fn(string $d = '') => ['type' => 'boolean', 'description' => $d];
        $obj  = static fn(array $p, array $req = []) => ['type' => 'object', 'properties' => $p ?: new \stdClass(), 'required' => $req];
        $tool = static fn(string $n, string $d, array $params) => [
            'type' => 'function',
            'function' => ['name' => $n, 'description' => $d, 'parameters' => $params],
        ];

        $confirm = $bool('Pon true SOLO después de que el asociado confirme explícitamente. Si es false o falta, se devuelve una vista previa.');

        return [
            $tool('consultar_creditos',
                'Créditos ACTIVOS del asociado autenticado. Devuelve número, línea, saldo de capital, valor de cuota, cuotas pendientes y días de mora. No menciones créditos finalizados. Si el asociado ya tiene créditos activos, responde su información y NO le recomiendes solicitar uno nuevo.',
                $obj([])),

            $tool('consultar_credito_detalle',
                'Detalle de un crédito del asociado por su número (saldo total, cuotas, mora, fechas).',
                $obj(['numero' => $str('Número del crédito del asociado')], ['numero'])),

            $tool('consultar_aportes',
                'Resumen de aportes/saldos del asociado (aportes ordinarios, sociales, totales, revalorizaciones, plan futuro, bolsillos, incentivos).',
                $obj([])),

            $tool('consultar_extractos',
                'Movimientos de extractos del asociado (aportes). Opcionalmente por mes (YYYY-MM).',
                $obj(['mes' => $str('Mes en formato YYYY-MM (opcional)')])),

            $tool('consultar_movimientos',
                'Pagos/transacciones del asociado: fecha, valor y número de PRODUCTOS (di "productos"; nunca "items" ni el medio de pago). Opcionalmente por mes (YYYY-MM). Para saber QUÉ productos, usa consultar_movimiento_productos con la fecha (y el valor si hace falta).',
                $obj(['mes' => $str('Mes en formato YYYY-MM (opcional)')])),

            $tool('consultar_movimiento_productos',
                'Lista los PRODUCTOS (descripción y valor) de un pago del asociado, por la FECHA. NUNCA menciones ids/códigos de transacción.',
                $obj([
                    'fecha' => $str('Fecha del pago en formato YYYY-MM-DD'),
                    'valor' => $num('Valor del pago (opcional, para desambiguar)'),
                ], ['fecha'])),

            $tool('consultar_solicitudes',
                'Solicitudes/trámites del asociado (PQRS, actualizar info, certificados, etc.) con radicado, tipo y estado.',
                $obj([])),

            $tool('consultar_perfil',
                'Datos de contacto actuales del asociado (nombre, email, celular, ciudad, dirección).',
                $obj([])),

            $tool('consultar_lineas_credito',
                'Líneas de crédito disponibles con su id y nombre. Úsala para elegir la línea antes de solicitar un crédito.',
                $obj([])),

            // ── Acciones ──────────────────────────────────────────────────
            $tool('actualizar_informacion',
                'Crea la solicitud de ACTUALIZAR INFORMACIÓN de contacto del asociado. Reúne todos los campos y confirma antes de crear.',
                $obj([
                    'celular'       => $str('Nuevo celular'),
                    'email'         => $str('Nuevo correo electrónico'),
                    'direccion'     => $str('Nueva dirección'),
                    'ciudad'        => $str('Nueva ciudad'),
                    'observaciones' => $str('Observaciones (opcional)'),
                    'confirmado'    => $confirm,
                ], ['celular', 'email', 'direccion', 'ciudad', 'confirmado'])),

            $tool('crear_pqrs',
                'Crea una PQRS (Petición, Queja, Reclamo o Sugerencia) a nombre del asociado. Confirma antes de crear.',
                $obj([
                    'tipo_pqrs'   => ['type' => 'string', 'enum' => ['Peticiones', 'Quejas', 'Reclamos', 'Sugerencias']],
                    'resumen'     => $str('Resumen corto (máx. 200 caracteres)'),
                    'descripcion' => $str('Descripción detallada (mín. 20 caracteres)'),
                    'confirmado'  => $confirm,
                ], ['tipo_pqrs', 'resumen', 'descripcion', 'confirmado'])),

            $tool('solicitar_certificado',
                'Solicita un certificado (declaración de renta o estado de cuenta) del asociado. Confirma antes de crear.',
                $obj([
                    'especie'    => ['type' => 'string', 'enum' => ['declaracion_renta', 'estado_cuenta']],
                    'anio'       => $int('Año o período del certificado'),
                    'confirmado' => $confirm,
                ], ['especie', 'anio', 'confirmado'])),

            $tool('solicitar_credito',
                'Crea una solicitud de crédito del asociado. Usa consultar_lineas_credito para obtener tasa_interes_id. Confirma antes de crear (incluye la aceptación del tratamiento de datos).',
                $obj([
                    'tipo_solicitante'       => $str('Tipo de solicitante (empleado, pensionado, independiente… según las líneas)'),
                    'tasa_interes_id'        => $int('Id de la línea de crédito (de consultar_lineas_credito)'),
                    'monto_deseado'          => $num('Monto deseado en pesos (mínimo 100000)'),
                    'numero_cuotas_deseadas' => $int('Número de cuotas deseado'),
                    'confirmado'             => $confirm,
                ], ['tipo_solicitante', 'tasa_interes_id', 'monto_deseado', 'numero_cuotas_deseadas', 'confirmado'])),
        ];
    }

    /**
     * Ejecuta una herramienta por nombre con los argumentos del modelo.
     * Nunca lanza: devuelve un array con ok=true/false para realimentar al modelo.
     */
    public function ejecutar(string $nombre, array $args): array
    {
        try {
            return match ($nombre) {
                'consultar_creditos'      => $this->creditos(),
                'consultar_credito_detalle' => $this->creditoDetalle((string) ($args['numero'] ?? '')),
                'consultar_aportes'       => $this->aportes(),
                'consultar_extractos'     => $this->extractos((string) ($args['mes'] ?? '')),
                'consultar_movimientos'   => $this->movimientos((string) ($args['mes'] ?? '')),
                'consultar_movimiento_productos' => $this->movimientoProductos((string) ($args['fecha'] ?? ''), (float) ($args['valor'] ?? 0)),
                'consultar_solicitudes'   => $this->solicitudes(),
                'consultar_perfil'        => $this->perfil(),
                'consultar_lineas_credito' => $this->lineas(),
                'actualizar_informacion'  => $this->actualizarInformacion($args),
                'crear_pqrs'              => $this->crearPqrs($args),
                'solicitar_certificado'   => $this->solicitarCertificado($args),
                'solicitar_credito'       => $this->solicitarCredito($args),
                default                   => ['ok' => false, 'error' => 'Herramienta no disponible.'],
            };
        } catch (DomainException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('[asistente-tools] ' . $nombre . ': ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo completar la operación.'];
        }
    }

    // ── Lectura (datos resumidos del asociado) ───────────────────────────

    private function creditos(): array
    {
        $all = $this->portal->creditos($this->cedula);
        $map = static fn(array $c) => [
            'numero'             => (string) $c['numero_credito'],
            'linea'              => (string) $c['tipo_prestamo'],
            'saldo_capital'      => (float) $c['saldo_capital'],
            'valor_cuota'        => (float) $c['valor_cuota'],
            'cuotas_pendientes'  => (int) $c['cuotas_pendientes'],
            'cuotas_totales'     => (int) $c['cuotas_totales'],
            'dias_mora'          => (int) $c['dias_mora'],
            'saldo_mora'         => (float) $c['saldo_mora'],
            'fecha_vencimiento'  => (string) ($c['fecha_vencimiento'] ?? ''),
        ];
        $activos = array_map($map, $all['activos'] ?? []);
        $res = [
            'ok'            => true,
            'activos'       => $activos,
            'total_activos' => count($activos),
        ];
        if ($activos) {
            $res['nota'] = 'El asociado YA tiene créditos activos: muestra su información y NO le recomiendes solicitar uno nuevo.';
        }
        return $res;
    }

    private function creditoDetalle(string $numero): array
    {
        if (trim($numero) === '') {
            return ['ok' => false, 'error' => 'Falta el número del crédito.'];
        }
        $r = $this->portal->creditoDetalle($this->cedula, $numero);
        return [
            'ok'                  => true,
            'numero'              => (string) ($r['numero_credito'] ?? $numero),
            'linea'               => (string) ($r['tipo_prestamo'] ?? ''),
            'saldo_capital'       => (float) ($r['saldo_capital'] ?? 0),
            'saldo_total'         => (float) ($r['saldo_total'] ?? 0),
            'valor_cuota'         => (float) ($r['valor_cuota'] ?? 0),
            'cuotas_totales'      => (int) ($r['cuotas_totales'] ?? 0),
            'cuotas_pendientes'   => (int) ($r['cuotas_pendientes'] ?? 0),
            'dias_mora'           => (int) ($r['dias_mora'] ?? 0),
            'proxima_cuota'       => (string) ($r['fecha_pago'] ?? ''),
            'fecha_vencimiento'   => (string) ($r['fecha_vencimiento'] ?? ''),
        ];
    }

    private function aportes(): array
    {
        $a = $this->portal->aportes($this->cedula);
        if (!$a) {
            return ['ok' => true, 'mensaje' => 'No se encontró información de aportes.'];
        }
        return [
            'ok'                    => true,
            'aportes_ordinarios'    => (float) ($a['aportes_ordinarios'] ?? 0),
            'aportes_sociales'      => (float) ($a['aportes_sociales'] ?? 0),
            'aportes_totales'       => (float) ($a['aportes_totales'] ?? 0),
            'revalorizaciones'      => (float) ($a['aportes_revalorizaciones'] ?? 0),
            'plan_futuro'           => (float) ($a['plan_futuro'] ?? 0),
            'bolsillos'             => (float) ($a['bolsillos'] ?? 0),
            'incentivos'            => (float) ($a['aportes_incentivos'] ?? 0),
            'comisiones'            => (float) ($a['comisiones'] ?? 0),
            'total_saldos_favor'    => (float) ($a['total_saldos_favor'] ?? 0),
        ];
    }

    private function extractos(string $mes): array
    {
        $rows = $this->portal->extractos($this->cedula, 0, $mes);
        $rows = array_slice($rows, 0, 20);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'fecha'  => (string) ($r['fecha'] ?? ''),
                'cuenta' => (string) ($r['cuenta'] ?? ''),
                'credito'=> (float) ($r['credit'] ?? 0),
                'debito' => (float) ($r['debito'] ?? 0),
                'detalle'=> mb_substr((string) ($r['detall'] ?? ''), 0, 120),
            ];
        }
        return ['ok' => true, 'movimientos' => $out, 'total_devuelto' => count($out)];
    }

    private function movimientos(string $mes): array
    {
        $rows = $this->portal->movimientos($this->cedula, $mes);
        $rows = array_slice($rows, 0, 15);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'fecha'     => (string) ($r['fecha_creacion'] ?? ''),
                'valor'     => (float) ($r['valor_total'] ?? 0),
                'productos' => (int) ($r['items'] ?? 0),
            ];
        }
        return [
            'ok'             => true,
            'transacciones'  => $out,
            'total_devuelto' => count($out),
            'nota'           => 'No menciones ids ni códigos de transacción. Aclara que un pago reciente puede tardar hasta 2 días hábiles en reflejarse.',
        ];
    }

    /** Productos (descripción y valor) de un pago del asociado, por su fecha. */
    private function movimientoProductos(string $fecha, float $valor = 0.0): array
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return ['ok' => false, 'error' => 'Falta la fecha del pago (YYYY-MM-DD).'];
        }
        $dia = substr($fecha, 0, 10);
        $encontrados = [];
        foreach ($this->portal->movimientos($this->cedula, '') as $r) {
            if (substr((string) ($r['fecha_creacion'] ?? ''), 0, 10) !== $dia) {
                continue;
            }
            if ($valor > 0 && abs((float) ($r['valor_total'] ?? 0) - $valor) > 0.5) {
                continue;
            }
            $prod = [];
            foreach (($r['detalles'] ?? []) as $d) {
                $prod[] = [
                    'producto' => (string) ($d['descripcion'] ?: ($d['tipo_rubro'] ?? 'Producto')),
                    'valor'    => (float) ($d['valor_asignado'] ?? 0),
                ];
            }
            $encontrados[] = [
                'fecha'     => (string) ($r['fecha_creacion'] ?? ''),
                'valor'     => (float) ($r['valor_total'] ?? 0),
                'productos' => $prod,
            ];
        }
        if (!$encontrados) {
            return ['ok' => false, 'error' => 'No encontré un pago en esa fecha.'];
        }
        if (count($encontrados) === 1) {
            return ['ok' => true] + $encontrados[0];
        }
        return ['ok' => true, 'pagos' => $encontrados];
    }

    private function solicitudes(): array
    {
        $res = $this->solicitudes->listar(['cedula' => $this->cedula], 25, 0);
        $out = [];
        foreach (($res['rows'] ?? []) as $s) {
            $out[] = [
                'radicado' => (string) ($s['numero_radicado'] ?? ''),
                'tipo'     => (string) ($s['tipo_nombre'] ?? $s['tipo_codigo'] ?? ''),
                'estado'   => (string) ($s['estado_codigo'] ?? ''),
                'fecha'    => (string) ($s['fecha_creacion'] ?? ''),
            ];
        }
        return ['ok' => true, 'solicitudes' => $out, 'total' => (int) ($res['total'] ?? count($out))];
    }

    private function perfil(): array
    {
        $p = $this->portal->perfil($this->cedula);
        return [
            'ok'        => true,
            'nombre'    => (string) ($p['nombre'] ?? ''),
            'email'     => (string) ($p['email'] ?? ''),
            'celular'   => (string) ($p['celular'] ?? ''),
            'ciudad'    => (string) ($p['ciudad'] ?? ''),
            'direccion' => (string) ($p['direccion'] ?? ''),
        ];
    }

    private function lineas(): array
    {
        $out = [];
        foreach ($this->portal->lineas() as $l) {
            $out[] = [
                'id'         => (int) ($l['id'] ?? 0),
                'nombre'     => (string) ($l['nombre_credito'] ?? $l['nombre'] ?? ''),
                'max_cuotas' => (int) ($l['limite_meses'] ?? 72),
            ];
        }
        return ['ok' => true, 'lineas' => $out];
    }

    // ── Acciones (crean solicitudes del propio asociado) ─────────────────

    private function actualizarInformacion(array $a): array
    {
        $datos = [
            'celular'   => trim((string) ($a['celular'] ?? '')),
            'email'     => trim((string) ($a['email'] ?? '')),
            'direccion' => trim((string) ($a['direccion'] ?? '')),
            'ciudad'    => trim((string) ($a['ciudad'] ?? '')),
            'observaciones' => trim((string) ($a['observaciones'] ?? '')),
        ];
        if ($datos['celular'] === '' || $datos['email'] === '' || $datos['direccion'] === '' || $datos['ciudad'] === '') {
            return ['ok' => false, 'error' => 'Faltan datos: celular, correo, dirección y ciudad son obligatorios.'];
        }
        if (!$this->confirmado($a)) {
            return $this->preview('actualizar mi información de contacto', $datos);
        }
        $p = $this->portal->perfil($this->cedula);
        $detalle = $datos + ['nombre' => (string) ($p['nombre'] ?? '')];
        $res = $this->solicitudes->crear('actualizar_info', $this->cedula, $detalle, $this->ctx());
        return $this->creada($res, 'Solicitud de actualización de información');
    }

    private function crearPqrs(array $a): array
    {
        $detalle = [
            'tipo_pqrs'   => trim((string) ($a['tipo_pqrs'] ?? '')),
            'resumen'     => mb_substr(trim((string) ($a['resumen'] ?? '')), 0, 200),
            'descripcion' => trim((string) ($a['descripcion'] ?? '')),
        ];
        if ($detalle['tipo_pqrs'] === '' || $detalle['resumen'] === '' || mb_strlen($detalle['descripcion']) < 20) {
            return ['ok' => false, 'error' => 'Faltan datos: tipo, resumen y una descripción de al menos 20 caracteres.'];
        }
        if (!$this->confirmado($a)) {
            return $this->preview('una PQRS', $detalle);
        }
        $res = $this->solicitudes->crear('pqrs', $this->cedula, $detalle, $this->ctx());
        return $this->creada($res, 'PQRS');
    }

    private function solicitarCertificado(array $a): array
    {
        $especie = trim((string) ($a['especie'] ?? ''));
        $anio    = (int) ($a['anio'] ?? 0);
        if (!in_array($especie, ['declaracion_renta', 'estado_cuenta'], true) || $anio < 2000) {
            return ['ok' => false, 'error' => 'Indica el tipo de certificado (declaracion_renta o estado_cuenta) y un año válido.'];
        }
        $detalle = ['especie' => $especie, 'anio' => $anio];
        if (!$this->confirmado($a)) {
            return $this->preview('un certificado', $detalle);
        }
        $res = $this->solicitudes->crear('certificado', $this->cedula, $detalle, $this->ctx());
        return $this->creada($res, 'Solicitud de certificado');
    }

    private function solicitarCredito(array $a): array
    {
        $tipo   = trim((string) ($a['tipo_solicitante'] ?? ''));
        $linea  = (int) ($a['tasa_interes_id'] ?? 0);
        $monto  = (float) ($a['monto_deseado'] ?? 0);
        $cuotas = (int) ($a['numero_cuotas_deseadas'] ?? 0);
        if ($tipo === '' || $linea <= 0 || $monto < 100000 || $cuotas < 1) {
            return ['ok' => false, 'error' => 'Faltan datos: tipo de solicitante, línea (tasa_interes_id), monto (mínimo $100.000) y número de cuotas.'];
        }
        if (!$this->confirmado($a)) {
            return $this->preview('una solicitud de crédito (incluye aceptación del tratamiento de datos)', [
                'tipo_solicitante' => $tipo, 'tasa_interes_id' => $linea,
                'monto_deseado' => $monto, 'numero_cuotas_deseadas' => $cuotas,
            ]);
        }
        $id = $this->portal->solicitudCrear($this->cedula, [
            'tipo_solicitante'         => $tipo,
            'tasa_interes_id'          => $linea,
            'monto_deseado'            => $monto,
            'numero_cuotas_deseadas'    => $cuotas,
            'acepta_tratamiento_datos' => true,
        ]);
        Logger::log('chat_ia', 'portal.chat', $this->cedula, "Herramienta solicitar_credito → solicitud #$id");
        return ['ok' => true, 'id' => $id, 'mensaje' => 'Solicitud de crédito creada correctamente.'];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Contexto de actor para el motor de solicitudes (portal, el propio asociado). */
    private function ctx(): array
    {
        return [
            'origen'           => 'portal',
            'solicitante_tipo' => 'asociado',
            'actor_tipo'       => 'asociado',
            'actor_id'         => $this->cedula,
        ];
    }

    private function confirmado(array $a): bool
    {
        return filter_var($a['confirmado'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** Vista previa antes de crear (pide confirmación al asociado). */
    private function preview(string $que, array $detalle): array
    {
        return [
            'ok'      => false,
            'estado'  => 'requiere_confirmacion',
            'detalle' => $detalle,
            'mensaje' => "Muestra este resumen al asociado, pídele que confirme y solo entonces vuelve a llamar la herramienta con confirmado=true. Vas a crear: $que.",
        ];
    }

    /** Respuesta estándar tras crear una solicitud. */
    private function creada(array $res, string $titulo): array
    {
        $s = $res['solicitud'] ?? [];
        $radicado = (string) ($s['numero_radicado'] ?? '');
        Logger::log('chat_ia', 'portal.chat', $this->cedula, "Herramienta creó solicitud {$s['tipo_codigo']} #{$s['id']} radicado=$radicado");
        return [
            'ok'       => true,
            'radicado' => $radicado,
            'estado'   => (string) ($s['estado_codigo'] ?? ''),
            'mensaje'  => "$titulo creada correctamente. Radicado: $radicado.",
        ];
    }
}
