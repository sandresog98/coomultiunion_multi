<?php
declare(strict_types=1);

/**
 * Plantillas HTML de correos de `multi_app_v2`.
 *
 * Port de `multi_app/cx2/utils/email_templates.php` (cx_email_base_styles) a una
 * clase reutilizable e independiente del Mailer (puede usarla cualquier servicio,
 * p. ej. la inyección de correos en solicitudes/portal desde Consola A).
 * Todo input se escapa con htmlspecialchars antes de interpolar.
 */
final class EmailTemplates
{
    /** Hoja de estilos base (mismo look del portal legacy). */
    public static function baseStyles(): string
    {
        return <<<CSS
        body { background:#f5f7fb; font-family: Arial, Helvetica, sans-serif; margin:0; padding:24px; color:#111827; }
        .card { max-width:520px; margin:0 auto; background:#ffffff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.06); overflow:hidden; }
        .header { background:linear-gradient(135deg,#11458B,#1384D2); color:#fff; padding:24px; text-align:center; }
        .header.success { background:linear-gradient(135deg,#059669,#10b981); }
        .header.danger { background:linear-gradient(135deg,#dc2626,#ef4444); }
        .header.warning { background:linear-gradient(135deg,#d97706,#f59e0b); }
        .header h1 { margin:0; font-size:20px; letter-spacing:0.3px; }
        .content { padding:20px 24px 28px; }
        .greet { font-size:16px; margin:0 0 12px; }
        .p { margin:0 0 14px; color:#374151; }
        .code { font-size:28px; letter-spacing:4px; font-weight:700; background:#f3f4f6; padding:12px 16px; display:inline-block; border-radius:8px; color:#111827; }
        .detail-box { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin:16px 0; }
        .detail-box h3 { margin:0 0 12px; font-size:14px; color:#6b7280; text-transform:uppercase; letter-spacing:0.5px; }
        .detail-row { display:flex; padding:8px 0; border-bottom:1px solid #e5e7eb; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { font-weight:600; color:#374151; width:140px; flex-shrink:0; }
        .detail-value { color:#111827; }
        .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-warning { background:#fef3c7; color:#92400e; }
        .badge-success { background:#d1fae5; color:#065f46; }
        .badge-danger { background:#fee2e2; color:#991b1b; }
        .footer { font-size:12px; color:#6b7280; padding:16px 24px; border-top:1px solid #eef2f7; background:#fafbff; }
        CSS;
    }

    /** Envuelve un cuerpo en el card corporativo (base reutilizable). */
    public static function base(string $titulo, string $contenidoHtml, string $headerClass = ''): string
    {
        $styles = self::baseStyles();
        $cls    = $headerClass !== '' ? ' class="' . $headerClass . '"' : '';
        return <<<HTML
<!doctype html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>{$titulo}</title>
    <style>{$styles}</style>
  </head>
  <body>
    <div class="card">
      <div class="header{$cls}"><h1>{$titulo}</h1></div>
      <div class="content">{$contenidoHtml}</div>
      <div class="footer">No responder a este email. Enviado automáticamente por el sistema.</div>
    </div>
  </body>
</html>
HTML;
    }

    /** Fila del detalle-box (label:value). */
    public static function detailRow(string $label, $value): string
    {
        return '<div class="detail-row"><span class="detail-label">' . htmlspecialchars($label)
            . '</span><span class="detail-value">' . htmlspecialchars((string) $value) . '</span></div>';
    }

    /** Bloque detalle-box con un mapa etiqueta→valor. */
    public static function detailBox(string $titulo, array $datos): string
    {
        $h = '<div class="detail-box"><h3>' . htmlspecialchars($titulo) . '</h3>';
        foreach ($datos as $label => $value) {
            $h .= self::detailRow($label, $value);
        }
        return $h . '</div>';
    }

    /** Solicitud de Fondo de Bienestar aprobada → entidad del convenio. */
    public static function fondoEntidadAprobada(array $convenio, array $solicitud, ?string $comentarios): string
    {
        $convenioNombre = (string) ($convenio['nombre'] ?? '');
        $radicado       = (string) ($solicitud['numero_radicado'] ?? '');
        $cedula         = (string) ($solicitud['cedula_solicitante'] ?? '');
        $comentarios    = $comentarios !== null ? (string) $comentarios : '';

        $contenido = '<p class="greet">Notificación de aprobación</p>'
            . '<p class="p">Se ha aprobado una solicitud de Fondo de Bienestar. A continuación el detalle:</p>'
            . self::detailBox('Solicitud', [
                'Convenio'  => $convenioNombre,
                'Radicado'  => $radicado !== '' ? '#' . $radicado : '—',
                'Cédula'    => $cedula,
                'Asociado'  => (string) ($solicitud['asociado_nombre'] ?? ''),
                'Comentarios' => $comentarios !== '' ? $comentarios : '—',
            ])
            . '<p class="p">Cordial saludo,<br>Equipo Coomultiunion.</p>';

        return self::base('Solicitud de Fondo de Bienestar aprobada', $contenido, ' success');
    }

    /** Confirmación al asociado: solicitud recibida. */
    public static function solicitudCreada(string $nombre, string $categoria, int $solicitudId, array $datosResumen): string
    {
        $safeNombre   = htmlspecialchars($nombre ?: 'Asociado(a)');
        $safeCategoria = htmlspecialchars($categoria);
        $contenido = '<p class="greet">Hola, ' . $safeNombre . '</p>'
            . '<p class="p">Hemos recibido tu solicitud (<strong>' . $safeCategoria . '</strong>).'
            . ' Número de radicado: <strong>#' . $solicitudId . '</strong>.</p>'
            . self::detailBox('Resumen', $datosResumen)
            . '<p class="p">Te responderemos a la brevedad. Si tienes dudas, comunícate con nosotros.</p>';
        return self::base('Solicitud recibida', $contenido, ' success');
    }

    /** Notificación al asociado: solicitud respondida. */
    public static function solicitudRespondida(string $nombre, string $categoria, int $solicitudId, string $resultado, ?string $mensaje): string
    {
        $safeNombre  = htmlspecialchars($nombre ?: 'Asociado(a)');
        $safeCategoria = htmlspecialchars($categoria);
        $safeResultado = htmlspecialchars($resultado);
        $contenido = '<p class="greet">Hola, ' . $safeNombre . '</p>'
            . '<p class="p">Tu solicitud (<strong>' . $safeCategoria . '</strong>, radicado '
            . '<strong>#' . $solicitudId . '</strong>) ha sido respondida.</p>'
            . self::detailBox('Respuesta', [
                'Estado'  => $safeResultado,
                'Mensaje' => $mensaje !== null && $mensaje !== '' ? $mensaje : '—',
            ])
            . '<p class="p">Cordial saludo,<br>Equipo Coomultiunion.</p>';
        return self::base('Solicitud respondida', $contenido);
    }

    /** Aviso interno (staff) de nueva solicitud → responsable. */
    public static function solicitudNuevaAviso(string $categoria, int $solicitudId, string $cedula): string
    {
        $contenido = '<p class="p">Se ha registrado una nueva solicitud que requiere su atención.</p>'
            . self::detailBox('Solicitud', [
                'N.º radicado' => '#' . $solicitudId,
                'Categoría'    => $categoria,
                'Cédula'       => $cedula !== '' ? $cedula : '—',
            ])
            . '<p class="p">Ingrese al panel de gestión para revisarla y responderla.</p>';
        return self::base('Nueva solicitud — Portal de asociados', $contenido);
    }

    /** Retiro de aportes desembolsado → asociado. */
    public static function retiroDesembolsado(string $nombre, string $radicado, string $cedula, string $monto, string $fecha): string
    {
        $contenido = '<p class="greet">Hola, ' . htmlspecialchars($nombre ?: 'Asociado(a)') . '</p>'
            . '<p class="p">Su retiro de aportes ha sido <strong>desembolsado</strong>.</p>'
            . self::detailBox('Detalle', [
                'Radicado' => '#' . $radicado,
                'Cédula'   => $cedula,
                'Monto'    => $monto,
                'Fecha'    => $fecha !== '' ? $fecha : '—',
            ])
            . '<p class="p">Cordial saludo,<br>Equipo Coomultiunion.</p>';
        return self::base('Retiro de aportes desembolsado', $contenido, ' success');
    }

    /** Notificación genérica de un crédito → solicitante (correo_electronico). */
    public static function creditoEstado(string $nombre, string $numero, string $mensaje): string
    {
        $contenido = '<p class="greet">Hola, ' . htmlspecialchars($nombre ?: 'Solicitante') . '</p>'
            . '<p class="p">Ponemos al tanto del estado de su solicitud de crédito.</p>'
            . self::detailBox('Solicitud', [
                'N.º solicitud' => $numero,
                'Estado'        => $mensaje,
            ])
            . '<p class="p">Si tiene dudas, comuníquese con nosotros.</p>';
        return self::base('Solicitud de crédito — Actualización', $contenido);
    }

    /** Confirmación: contraseña de acceso creada → asociado. */
    public static function cuentaPasswordCreada(string $nombre, string $cedula): string
    {
        $contenido = '<p class="greet">Hola, ' . htmlspecialchars($nombre ?: 'Asociado(a)') . '</p>'
            . '<p class="p">Se ha creado exitosamente la <strong>contraseña de acceso</strong> a su '
            . 'portal de asociados.</p>'
            . self::detailBox('Cuenta', ['Cédula' => $cedula])
            . '<p class="p">Ya puede iniciar sesión con su cédula y la contraseña creada.</p>';
        return self::base('Contraseña creada — Portal de asociados', $contenido, ' success');
    }

    /** Confirmación: contraseña de acceso cambiada → asociado. */
    public static function cuentaPasswordCambiada(string $nombre, string $cedula): string
    {
        $contenido = '<p class="greet">Hola, ' . htmlspecialchars($nombre ?: 'Asociado(a)') . '</p>'
            . '<p class="p">Le confirmamos que su <strong>contraseña de acceso</strong> al portal de '
            . 'asociados ha sido cambiada.</p>'
            . self::detailBox('Cuenta', ['Cédula' => $cedula])
            . '<p class="p">Si no realizó este cambio, comuníquese con nosotros de inmediato.</p>';
        return self::base('Contraseña actualizada — Portal de asociados', $contenido);
    }

    /** Código de verificación para crear o restablecer la contraseña del portal. */
    public static function cuentaPasswordCodigo(string $nombre, string $cedula, string $codigo): string
    {
        $contenido = '<p class="greet">Hola, ' . htmlspecialchars($nombre ?: 'Asociado(a)') . '</p>'
            . '<p class="p">Recibimos una solicitud para <strong>crear o restablecer</strong> su contraseña del Portal de Asociados.</p>'
            . '<p class="p">Use el siguiente código de verificación (válido por 20 minutos):</p>'
            . '<p style="text-align:center;font-size:24px;letter-spacing:8px;font-weight:700;color:#11458B;padding:12px;background:#f2f5f9;border-radius:8px;">' . htmlspecialchars($codigo) . '</p>'
            . self::detailBox('Cuenta', ['Cédula' => $cedula])
            . '<p class="p">Si no solicitó este cambio, ignore este mensaje.</p>'
            . '<p class="p">Cordial saludo,<br>Equipo Coomultiunion.</p>';
        return self::base('Código de verificación · Portal de Asociados', $contenido);
    }
}