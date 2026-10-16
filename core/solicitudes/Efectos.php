<?php
declare(strict_types=1);

require_once __DIR__ . '/SolicitudRepository.php';
require_once dirname(__DIR__) . '/movimientos/MovimientoService.php';
require_once dirname(__DIR__) . '/convenios/ProductoStockService.php';
require_once dirname(__DIR__) . '/Fondo/FondoMailService.php';
require_once dirname(__DIR__) . '/Email/EmailCola.php';
require_once dirname(__DIR__) . '/Email/EmailTemplates.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Handlers de efectos post-transición.
 * Registrados por nombre en `solicitudes_transiciones.efecto`.
 * La ejecución ocurre DESPUÉS del commit del cambio de estado, en transacción propia:
 * los servicios reusados (ProductoStockService, MovimientoService) abren la suya.
 */
final class Efectos
{
    public function __construct(private SolicitudRepository $repo) {}

    /** Ejecuta el efecto indicado; devuelve un mensaje breve para el timeline. */
    public function aplicar(string $efecto, array $solicitud): ?string
    {
        return match ($efecto) {
            'crear_asignacion_producto'     => $this->crearAsignacionProducto($solicitud),
            'emitir_boletas'                => $this->emitirBoletas($solicitud),
            'crear_movimiento_incentivos'   => $this->crearMovimientoIncentivos($solicitud),
            'aplicar_actualizacion'         => $this->aplicarActualizacion($solicitud),
            'entregar_certificado'          => $this->entregarCertificado($solicitud),
            'crear_redencion_fondo'         => $this->crearRedencionFondo($solicitud),
            'notificar_desembolso_retiro'   => $this->notificarDesembolsoRetiro($solicitud),
            default => null,
        };
    }

    /**
     * Aprobar 'nuevo_producto' → alta/actualización de asignación de producto al asociado.
     * Escribe en asociados_productos (v2, B4). Si la tabla no existe todavía
     * (fase no construida), deja el efecto pendiente (evento) sin romper el flujo.
     */
    private function crearAsignacionProducto(array $solicitud): string
    {
        $det = $this->repo->detalle('solicitudes_det_nuevo_prod', (int) $solicitud['id']);
        if (!$det) {
            return 'Efecto omitido: sin detalle de nuevo producto.';
        }
        $cedula = $solicitud['cedula_solicitante'];
        $productoId = (int) $det['producto_id'];
        $monto = (float) ($det['valor'] ?? 0);
        $diaPago = $det['dia_pago'] !== null ? (int) $det['dia_pago'] : null;
        if (!$this->tablaExiste('asociados_productos')) {
            return 'Efecto pendiente: asociados_productos aún no existe (fase posterior).';
        }
        if ($monto <= 0) {
            return 'Efecto omitido: el valor del producto no es válido (mayor a cero).';
        }
        $pdo = $this->repo->pdo();
        $st = $pdo->prepare(
            "SELECT id FROM asociados_productos WHERE cedula = ? AND producto_id = ? AND estado_activo = 1 LIMIT 1"
        );
        $st->execute([$cedula, $productoId]);
        $activa = $st->fetch();
        if ($activa) {
            $pdo->prepare("UPDATE asociados_productos SET monto = ?, dia_pago = ? WHERE id = ?")
                ->execute([$monto, $diaPago, (int) $activa['id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO asociados_productos (cedula, producto_id, monto, dia_pago, estado_activo)
                 VALUES (?,?,?,?,1)"
            )->execute([$cedula, $productoId, $monto, $diaPago]);
        }
        return 'Producto asignado al asociado.';
    }

    /**
     * Aprobar 'boleta' → emite/sella las unidades del carrito. El gate 'stock_boleta'
     * ya garantizó la cantidad disponible. Marca cada boleta 'disponible' → 'vendida'
     * (método 'credito') reusando ProductoStockService (NO duplica la lógica de venta)
     * y persiste el vínculo solicitud↔boleta.
     */
    private function emitirBoletas(array $solicitud): string
    {
        $id = (int) $solicitud['id'];
        $cedula = (string) $solicitud['cedula_solicitante'];
        $items = $this->repo->itemsBoleta($id);
        if (!$items) {
            return 'Efecto omitido: el carrito está vacío.';
        }
        $pdo = $this->repo->pdo();
        $stock = new ProductoStockService($pdo);
        $vinculos = [];
        $totalVendidas = 0;
        $actorVenta = $solicitud['respondido_por'] !== null ? ['id' => (int) $solicitud['respondido_por']] : null;

        foreach ($items as $it) {
            $pid = (int) $it['producto_id'];
            $cant = (int) $it['cantidad'];
            $st = $pdo->prepare(
                "SELECT id FROM boleteria_boletas WHERE producto_id = ? AND estado = 'disponible' ORDER BY id ASC LIMIT ?"
            );
            $st->bindValue(1, $pid, PDO::PARAM_INT);
            $st->bindValue(2, $cant, PDO::PARAM_INT);
            $st->execute();
            $blist = $st->fetchAll();
            if (count($blist) < $cant) {
                return "Efecto pendiente: stock insuficiente para '{$it['producto_nombre']}' tras aprobación.";
            }
            foreach ($blist as $b) {
                $stock->vender((int) $b['id'], $cedula, 'credito', null, $actorVenta);
                $vinculos[] = ['boleta_id' => (int) $b['id'], 'producto_id' => $pid, 'item_id' => (int) $it['id']];
                $totalVendidas++;
            }
        }
        $this->repo->persistirVinculoBoleta($id, $vinculos);
        Logger::log('emitir_boletas', 'solicitudes', (string) ($solicitud['respondido_por'] ?? null), "Solicitud boleta #$id: $totalVendidas boleta(s) vendidas a $cedula", null, null, 'warning');
        return "$totalVendidas boleta(s) emitidas y marcadas vendidas al asociado $cedula.";
    }

    /**
     * Aprobar 'retiro_incentivos' → registra el/los egresos en movimientos_multi
     * reusando MovimientoService (incentivos y comisiones como tramos separados).
     */
    private function crearMovimientoIncentivos(array $solicitud): string
    {
        $id = (int) $solicitud['id'];
        $det = $this->repo->detalle('solicitudes_det_retiro_incentivos', $id);
        if (!$det) {
            return 'Efecto omitido: sin detalle de retiro de incentivos.';
        }
        $cedula = (string) $solicitud['cedula_solicitante'];
        $mv = new MovimientoService($this->repo->pdo());
        $hechos = [];
        $fecha = date('Y-m-d');
        $obs = 'Retiro incentivos — solicitud ' . $solicitud['numero_radicado'];

        if ((float) $det['val_incentivos'] > 0) {
            $mv->crear([
                'cedula' => $cedula, 'tipo' => 'retiro_incentivos',
                'valor' => (float) $det['val_incentivos'], 'fecha' => $fecha, 'observaciones' => $obs,
            ]);
            $hechos[] = 'incentivos';
        }
        if ((float) $det['val_comisiones'] > 0) {
            $mv->crear([
                'cedula' => $cedula, 'tipo' => 'retiro_comision',
                'valor' => (float) $det['val_comisiones'], 'fecha' => $fecha, 'observaciones' => $obs,
            ]);
            $hechos[] = 'comisiones';
        }
        if (!$hechos) {
            return 'Efecto omitido: sin valores a retirar.';
        }
        Logger::log('desembolsar', 'solicitudes', (string) ($solicitud['respondido_por'] ?? null), "Retiro de incentivos solicitud #$id ($cedula): " . implode(', ', $hechos), null, null, 'warning');
        return 'Movimiento(s) de retiro registrados (' . implode(', ', $hechos) . ') en movimientos_multi.';
    }

    /**
     * Aprobar 'actualizar_info' → propaga el cambio a sifone_asociados SOLO para los
     * campos que se editan localmente (celular, direccion, ciudad, email).
     * Datos que solo se editan en Sifone manual (nombre, fecha_nacimiento, etc.)
     * NO se propagan: se registran en observaciones para gestión manual.
     */
    private function aplicarActualizacion(array $solicitud): string
    {
        $det = $this->repo->detalle('solicitudes_det_actualizacion', (int) $solicitud['id']);
        if (!$det) {
            return 'Efecto omitido: sin detalle de actualización.';
        }
        $cedula = (string) $solicitud['cedula_solicitante'];

        // Mapa: campo del detalle → columna sifone_asociados.
        $mapa = [
            'email'     => 'mail',
            'celular'   => 'celula',
            'direccion' => 'direcc',
            'ciudad'    => 'ciudad',
        ];
        $set = [];
        $vals = [];
        $actualizado = [];
        foreach ($mapa as $detCol => $sifoneCol) {
            $v = trim((string) ($det[$detCol] ?? ''));
            if ($v !== '') {
                $set[] = "`$sifoneCol` = ?";
                $vals[] = $v;
                $actualizado[] = $detCol;
            }
        }
        if ($set) {
            $vals[] = $cedula;
            $this->repo->pdo()->prepare(
                'UPDATE sifone_asociados SET ' . implode(', ', $set) . ' WHERE cedula = ?'
            )->execute($vals);
        }
        // Nombre no-editable localmente: se documenta en el timeline.
        $nombreNuevo = trim((string) ($det['nombre'] ?? ''));
        if ($nombreNuevo !== '') {
            return 'Datos actualizados (' . implode(', ', $actualizado) . '). Nombre: solo editable en Sifone manual.';
        }
        return $actualizado ? 'Datos actualizados: ' . implode(', ', $actualizado) . '.' : 'Sin cambios aplicables.';
    }

    /**
     * Aprobar 'certificado' → genera el documento (declaracion_renta | estado_cuenta)
     * con TCPDF y lo registra como adjunto contexto=documento.
     */
    private function entregarCertificado(array $solicitud): string
    {
        $det = $this->repo->detalle('solicitudes_det_certificado', (int) $solicitud['id']);
        if (!$det) {
            return 'Efecto omitido: sin detalle de certificado.';
        }
        $cedula = (string) $solicitud['cedula_solicitante'];
        $especie = (string) $det['especie'];
        $anio = $det['anio'] !== null ? (int) $det['anio'] : (int) date('Y');

        $pdfRuta = $this->generarPDFCertificado((int) $solicitud['id'], $cedula, $especie, $anio, (string) $solicitud['numero_radicado']);

        $this->repo->insertarAdjunto((int) $solicitud['id'], [
            'contexto' => 'documento', 'tipo' => 'pdf', 'tipo_documento' => $especie,
            'nombre_original' => basename($pdfRuta), 'nombre_archivo' => basename($pdfRuta),
            'ruta_archivo' => $pdfRuta, 'tipo_mime' => 'application/pdf',
            'subido_por' => (string) ($solicitud['respondido_por'] ?? ''),
        ]);
        return "Certificado de $especie generado y adjuntado ($anio).";
    }

    /** Genera y devuelve la ruta del PDF del certificado (TCPDF). Relativa a la raíz. */
    private function generarPDFCertificado(int $solicitudId, string $cedula, string $especie, int $anio, string $radicado): string
    {
        require_once dirname(__DIR__, 2) . '/utils/vendor/tcpdf/tcpdf.php';

        $root = dirname(__DIR__, 2);
        $sub = 'solicitudes/certificados/' . date('Y/m');
        $clave = substr(md5($radicado . uniqid()), 0, 8);
        $base = Storage::nombreOpaco('cert', (string) $solicitudId, $clave, 'pdf');
        $rutaRel = $sub . '/' . $base;
        $tmpDir = sys_get_temp_dir();
        $rutaAbs = rtrim($tmpDir, '/') . '/' . $base;

        $asoc = $this->repo->pdo()->prepare('SELECT nombre, cedula FROM vw_asociados WHERE cedula = ?');
        $asoc->execute([$cedula]);
        $a = $asoc->fetch() ?: ['nombre' => $cedula, 'cedula' => $cedula];

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Multi App v2');
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'COOMULTIUNIÓN — CERTIFICADO', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Ln(4);
        $pdf->Cell(0, 8, "Cédula: {$a['nombre']} — {$a['cedula']}", 0, 1, 'L');

        if ($especie === 'declaracion_renta') {
            $pdf->Cell(0, 8, "Certificación de Ingresos y Retenciones — año gravable $anio", 0, 1, 'L');
            $pdf->Ln(4);
            $pdf->MultiCell(0, 6, "Se certifica que el asociado {$a['nombre']} identificado con cédula {$a['cedula']} "
                . "registra movimientos por el año gravable $anio. Certificado expedido bajo solicitud $radicado.", 0, 'J');
        } else {
            $pdf->Cell(0, 8, "Estado de cuenta — período $anio", 0, 1, 'L');
            $pdf->Ln(4);
            $pdf->Cell(0, 8, 'Créditos vigentes:', 0, 1, 'L');
            $st = $this->repo->pdo()->prepare(
                "SELECT numero_credito, tipo_prestamo, saldo_total, fecha_vencimiento FROM vw_creditos WHERE cedula = ? ORDER BY numero_credito"
            );
            $st->execute([$cedula]);
            foreach ($st->fetchAll() as $c) {
                $pdf->Cell(0, 6, "- {$c['numero_credito']} ({$c['tipo_prestamo']}) — saldo: $" . number_format((float) $c['saldo_total'], 0, ',', '.') . ' — vence ' . ($c['fecha_vencimiento'] ?? '—'), 0, 1, 'L');
            }
            $pdf->Ln(3);
            $pdf->Cell(0, 8, 'Saldos favor (incentivos / bolsillos):', 0, 1, 'L');
            $st = $this->repo->pdo()->prepare(
                "SELECT aportes_incentivos, bolsillos, comisiones FROM vw_productos WHERE cedula = ?"
            );
            $st->execute([$cedula]);
            if ($s = $st->fetch()) {
                $pdf->Cell(0, 6, 'Incentivos aportes: $' . number_format((float) $s['aportes_incentivos'], 0, ',', '.'), 0, 1, 'L');
                $pdf->Cell(0, 6, 'Bolsillos: $' . number_format((float) $s['bolsillos'], 0, ',', '.'), 0, 1, 'L');
                $pdf->Cell(0, 6, 'Comisiones netas: $' . number_format((float) $s['comisiones'], 0, ',', '.'), 0, 1, 'L');
            }
            $pdf->Ln(3);
            $mov = $this->repo->pdo()->prepare(
                "SELECT compro, fecham, detall, debito, credit, saldof FROM sifone_movimientos_tributarios
                 WHERE cedula = ? AND YEAR(fecham) = ? ORDER BY fecham DESC LIMIT 20"
            );
            $mov->execute([$cedula, $anio]);
            if ($mov->rowCount() > 0) {
                $pdf->Cell(0, 8, 'Últimos movimientos del año:', 0, 1, 'L');
                foreach ($mov->fetchAll() as $m) {
                    $debe = $m['debito'] ? '$' . number_format((float) $m['debito'], 0, ',', '.') : '-';
                    $haber = $m['credit'] ? '$' . number_format((float) $m['credit'], 0, ',', '.') : '-';
                    $pdf->Cell(0, 5, date('d/m/Y', strtotime($m['fecham'])) . ' — ' . $m['detall'] . " [Deb: $debe | Hab: $haber]", 0, 1, 'L');
                }
            }
        }
        $pdf->Output($rutaAbs, 'F');
        // Guardar en el storage del módulo solicitudes (R2 o Local), clave relativa a la raíz.
        require_once dirname(__DIR__) . '/Storage/Storage.php';
        Storage::for('solicitudes')->put(
            'solicitudes/certificados/' . date('Y/m') . '/' . $base,
            $rutaAbs,
            ['mime' => 'application/pdf']
        );
        @unlink($rutaAbs);
        return $rutaRel;
    }

    /**
     * Aprobar 'fondo' → registra la redención y el bloqueo en el historial único
     * (`fondo_bienestar_redenciones`, fuente de bloqueo/frecuencia de la validación),
     * fija `fecha_uso` y `bloqueado_hasta` en el detalle, y (si el convenio lo exige)
     * encola el email a la entidad (FondoMailService → cola; envía el worker SMTP real).
     */
    private function crearRedencionFondo(array $solicitud): string
    {
        $id = (int) $solicitud['id'];
        $det = $this->repo->detalle('solicitudes_det_fondo', $id);
        if (!$det || empty($det['convenio_id'])) {
            return 'Efecto omitido: sin detalle de fondo válido.';
        }
        $cedula    = (string) $solicitud['cedula_solicitante'];
        $convenioId = (int) $det['convenio_id'];

        $pdo = $this->repo->pdo();
        $st = $pdo->prepare(
            "SELECT id, nombre, bloqueo_meses, envia_email_entidad, email_entidad
             FROM fondo_bienestar_convenios WHERE id = ?"
        );
        $st->execute([$convenioId]);
        $convenio = $st->fetch();
        if (!$convenio) {
            return 'Efecto omitido: convenio de fondo no encontrado.';
        }

        $bloqueoMeses = $convenio['bloqueo_meses'] !== null && $convenio['bloqueo_meses'] !== ''
            ? (int) $convenio['bloqueo_meses']
            : 12;

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE solicitudes_det_fondo
                 SET fecha_uso = CURDATE(), bloqueado_hasta = DATE_ADD(CURDATE(), INTERVAL ? MONTH)
                 WHERE solicitud_id = ?"
            )->execute([$bloqueoMeses, $id]);

            $pdo->prepare(
                "INSERT INTO fondo_bienestar_redenciones
                   (cedula_asociado, convenio_id, solicitud_id, fecha_redencion, bloqueado_hasta)
                 VALUES (?,?,?,CURDATE(),DATE_ADD(CURDATE(), INTERVAL ? MONTH))"
            )->execute([$cedula, $convenioId, $id, $bloqueoMeses]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new DomainException('No se pudo registrar la redención del fondo: ' . $e->getMessage());
        }

        if (!empty($convenio['envia_email_entidad']) && !empty($convenio['email_entidad'])) {
            try {
                (new FondoMailService($pdo))->notificarEntidad(
                    (string) $convenio['email_entidad'],
                    $convenio,
                    $solicitud,
                    (string) ($solicitud['comentario_respuesta'] ?? ''),
                    $id
                );
            } catch (Throwable $e) {
                error_log('[fondo-efecto] notificarEntidad: ' . $e->getMessage());
            }
        }

        Logger::log('redimir', 'solicitudes', (string) ($solicitud['respondido_por'] ?? null), "Redención de fondo convenio #$convenioId para $cedula (solicitud #$id)", null, null, 'warning');
        return "Redención de fondo registrada y bloqueo hasta " . (new DateTime('+' . $bloqueoMeses . ' months'))->format('Y-m-d') . '.';
    }

    private function tablaExiste(string $tabla): bool
    {
        $st = $this->repo->pdo()->prepare(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $st->execute([$tabla]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Desembolsar 'retiro_aporte' → encola el correo de notificación al asociado
     * (EmailCola → cola email_cola; envía el worker SMTP real).
     */
    private function notificarDesembolsoRetiro(array $solicitud): string
    {
        $cedula  = (string) $solicitud['cedula_solicitante'];
        $det     = $this->repo->detalle('solicitudes_det_retiro_aporte', (int) $solicitud['id']);
        $monto   = ($det['monto_solicitado'] ?? null) !== null ? number_format((float) $det['monto_solicitado'], 0, ',', '.') : 'Total';
        $email   = EmailCola::emailAsociado($cedula);
        if (!$email) {
            return 'Notificación de desembolso omitida (sin email del asociado).';
        }
        $nombre  = (string) ($solicitud['asociado_nombre'] ?? '') ?: '';
        $asunto  = 'Retiro de aportes desembolsado';
        $cuerpo  = EmailTemplates::retiroDesembolsado(
            $nombre,
            (string) $solicitud['numero_radicado'],
            $cedula,
            $monto,
            (string) $solicitud['fecha_respuesta'] ?? ''
        );
        EmailCola::encolar('desembolso', $email, $asunto, $cuerpo, (int) $solicitud['id']);
        Logger::log('desembolsar', 'solicitudes', (string) ($solicitud['respondido_por'] ?? null), "Desembolso retiro aportes solicitud #{$solicitud['id']} ($cedula, $monto)", null, null, 'warning');
        return 'Notificación de desembolso encolada.';
    }
}