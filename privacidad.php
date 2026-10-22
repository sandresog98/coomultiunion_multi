<?php
declare(strict_types=1);
/**
 * Política de Tratamiento de Datos Personales — página pública (Ley 1581 de 2012).
 * Sin auth ni BD. Enlazada desde el footer de todas las superficies.
 *
 * NOTA: el texto es un BORRADOR con marcadores ([RAZÓN SOCIAL], [NIT], …) que debe
 * validar el área jurídica / oficial de protección de datos de la cooperativa.
 */
$appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$LOGO = $appBase . '/assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Política de Tratamiento de Datos — Coomultiunion</title>
<link rel="icon" href="<?= $appBase ?>/assets/favicons/favicon.ico" sizes="any">
<!-- Tema: fija data-bs-theme ANTES de pintar (evita parpadeo). Por defecto: claro. -->
<script>(function(){try{var t=localStorage.getItem('multi-theme');document.documentElement.setAttribute('data-bs-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-bs-theme','light');}})();</script>
<link href="<?= $appBase ?>/assets/vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $appBase ?>/assets/vendor/fontawesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="<?= $appBase ?>/assets/css/fonts.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $appBase ?>/assets/css/front.css">
<link href="<?= $appBase ?>/assets/css/theme-dark.css" rel="stylesheet">
<style>
  .pv-header{ position:sticky; top:0; z-index:1030; display:flex; align-items:center; gap:12px;
    padding:10px 16px; color:#fff; background:linear-gradient(135deg,var(--brand-blue-dark),var(--brand-blue));
    box-shadow:0 2px 10px rgba(17,70,138,.25); }
  .pv-header img{ height:38px; }
  .pv-header b{ font-weight:700; }
  .pv-toggle{ margin-left:auto; display:inline-flex; align-items:center; gap:8px; border:0; cursor:pointer;
    background:rgba(255,255,255,.16); color:#fff; font:inherit; font-size:.85rem; font-weight:600;
    padding:8px 14px; border-radius:999px; }
  .pv-toggle:hover{ background:rgba(255,255,255,.28); }
  .pv-wrap{ max-width:920px; margin:0 auto; padding:26px 16px 60px; }
  .pv-card{ background:var(--front-card); border:1px solid var(--front-line); border-radius:var(--front-radius);
    box-shadow:var(--front-shadow); padding:28px; }
  .pv-title{ color:var(--brand-blue-dark); font-weight:800; }
  .pv-card h2{ font-size:1.05rem; font-weight:700; color:var(--brand-blue); margin:1.6rem 0 .5rem; }
  [data-bs-theme="dark"] .pv-title{ color:#8fc5ee; }
  [data-bs-theme="dark"] .pv-card h2{ color:#6fb3e8; }
  [data-bs-theme="dark"] .pv-foot a{ color:#6fb3e8; }
  .pv-card h2:first-of-type{ margin-top:0; }
  .pv-card p, .pv-card li{ font-size:.92rem; line-height:1.6; color:var(--front-ink); }
  .pv-card ul{ padding-left:1.2rem; }
  .pv-meta{ color:var(--front-muted); font-size:.82rem; }
  .pv-note{ background:rgba(252,211,41,.15); border:1px solid rgba(252,211,41,.5); border-radius:10px;
    padding:10px 14px; font-size:.85rem; margin-bottom:18px; }
  .pv-ph{ background:rgba(235,48,49,.12); border-radius:4px; padding:0 4px; font-weight:600; }
  .pv-foot{ text-align:center; color:var(--front-muted); font-size:.8rem; padding:22px 16px; }
  .pv-foot a{ color:var(--brand-blue); }
</style>
</head>
<body class="front-body-pattern">

<header class="pv-header">
  <img src="<?= $LOGO ?>" alt="Multi App">
  <b>Política de Tratamiento de Datos Personales</b>
  <button type="button" class="pv-toggle" data-theme-toggle aria-pressed="false" title="Cambiar tema">
    <i class="fas fa-moon" data-theme-icon></i><span data-theme-label>Tema oscuro</span>
  </button>
</header>

<main class="pv-wrap">
  <div class="pv-card">
    <h1 class="pv-title h4 mb-1">Política de Tratamiento de Datos Personales</h1>
    <p class="pv-meta mb-3">Última actualización: <span class="pv-ph">[FECHA]</span></p>

    <p class="pv-note"><i class="fas fa-triangle-exclamation"></i>
      <b>Borrador.</b> Los datos entre corchetes deben completarse y el texto debe ser
      validado por el área jurídica / el oficial de protección de datos de la cooperativa
      antes de su publicación.</p>

    <h2>1. Responsable del tratamiento</h2>
    <p>
      <span class="pv-ph">[RAZÓN SOCIAL]</span>, identificada con NIT <span class="pv-ph">[NIT]</span>,
      con domicilio en <span class="pv-ph">[DIRECCIÓN]</span>, en adelante «la Cooperativa», es la
      responsable del tratamiento de los datos personales que recolecta a través de sus canales
      presenciales, telefónicos y digitales (portal de asociados, tienda y convenios, simulador y
      documentación de la API).
    </p>
    <p>
      Datos de contacto del responsable y del oficial de protección de datos:<br>
      Correo: <span class="pv-ph">[CORREO OFICIAL DE DATOS]</span> · Teléfono: <span class="pv-ph">[TELÉFONO]</span> ·
      Dirección: <span class="pv-ph">[DIRECCIÓN]</span>.
    </p>

    <h2>2. Marco normativo</h2>
    <p>
      Esta política se expide en cumplimiento de la Ley 1581 de 2012, el Decreto 1377 de 2013
      (compilado en el Decreto 1074 de 2015) y las demás normas y guías que regulan la protección
      de datos personales en Colombia, así como de las instrucciones de la Superintendencia de
      Industria y Comercio (SIC).
    </p>

    <h2>3. Definiciones</h2>
    <ul>
      <li><b>Dato personal:</b> información que permite identificar o hacer identificable a una persona.</li>
      <li><b>Titular:</b> persona natural cuyos datos son tratados (asociados, solicitantes, clientes y usuarios).</li>
      <li><b>Tratamiento:</b> cualquier operación sobre datos personales (recolección, almacenamiento, uso, circulación, supresión).</li>
      <li><b>Autorización:</b> consentimiento previo, expreso e informado del titular para el tratamiento.</li>
      <li><b>Responsable:</b> quien decide sobre la base de datos y su tratamiento (la Cooperativa).</li>
      <li><b>Encargado:</b> quien trata datos por cuenta del responsable.</li>
    </ul>

    <h2>4. Principios</h2>
    <p>
      El tratamiento se rige por los principios de legalidad, finalidad, libertad, veracidad,
      transparencia, acceso y circulación restringida, seguridad y confidencialidad.
    </p>

    <h2>5. Finalidades del tratamiento</h2>
    <ul>
      <li>Gestionar la vinculación, los aportes, los créditos, los pagos y los servicios como asociado.</li>
      <li>Atender solicitudes, PQRS, trámites y peticiones de información.</li>
      <li>Cumplir obligaciones legales, contables, tributarias y de prevención de lavado de activos (SARLAFT).</li>
      <li>Consultar y reportar información en centrales de riesgo, cuando corresponda y con la autorización del titular.</li>
      <li>Enviar comunicaciones informativas, comerciales y de servicio (con posibilidad de oponerse).</li>
      <li>Operar la tienda y los convenios: gestionar compras, boletas, entregas y garantías.</li>
      <li>Seguridad de la información y auditoría del acceso a las plataformas.</li>
    </ul>

    <h2>6. Derechos del titular</h2>
    <p>El titular de los datos puede:</p>
    <ul>
      <li>Conocer, actualizar y rectificar sus datos personales.</li>
      <li>Solicitar prueba de la autorización otorgada.</li>
      <li>Ser informado sobre el uso dado a sus datos.</li>
      <li>Presentar quejas ante la Cooperativa y ante la SIC por infracciones.</li>
      <li>Revocar la autorización y/o solicitar la supresión del dato, cuando no exista un deber legal o contractual de permanencia.</li>
      <li>Acceder de forma gratuita a sus datos personales.</li>
    </ul>

    <h2>7. Canales y procedimiento para ejercer tus derechos</h2>
    <p>
      Las consultas y reclamos se pueden presentar por el correo <span class="pv-ph">[CORREO OFICIAL DE DATOS]</span>,
      en las oficinas de la Cooperativa (<span class="pv-ph">[DIRECCIÓN]</span>) o por el canal que se habilite para el efecto.
    </p>
    <ul>
      <li><b>Consultas:</b> se atienden dentro de los diez (10) días hábiles siguientes a su recepción; si no es posible, se informará y se extenderá hasta por cinco (5) días hábiles más.</li>
      <li><b>Reclamos:</b> se atienden dentro de los quince (15) días hábiles siguientes; si no es posible, se informará y se extenderá hasta por ocho (8) días hábiles más.</li>
      <li>La solicitud debe identificar al titular y describir los hechos o datos objeto de la petición.</li>
    </ul>

    <h2>8. Cookies y tecnologías de almacenamiento</h2>
    <p>
      Este sitio web <b>no utiliza cookies de publicidad, analítica ni seguimiento</b>. No existen
      cookies de terceros con fines de perfilamiento.
    </p>
    <ul>
      <li><b>Cookies de sesión (necesarias):</b> se usan únicamente cuando el usuario inicia sesión
        (personal interno y asociados). Sirven para mantener la sesión iniciada y proteger las
        operaciones contra fraude (token CSRF). Son <i>HttpOnly</i>, <i>SameSite=Lax</i> y
        <i>Secure</i> bajo HTTPS. Sin ellas no es posible autenticarse.</li>
      <li><b>Almacenamiento local (localStorage/sessionStorage):</b> se guardan preferencias
        funcionales, como el tema de visualización (claro/oscuro) y el contenido del carrito de
        convenios durante la sesión. No se usan para identificar o rastrear al usuario.</li>
    </ul>
    <p>
      Al no emplearse cookies o tecnologías no esenciales, no se presenta un aviso de consentimiento
      de cookies. Si en el futuro se incorporan herramientas de analítica o publicidad, se
      informará y se solicitará el consentimiento previo, permitiendo aceptar o rechazar las
      categorías no esenciales.
    </p>

    <h2>9. Transmisión y transferencia a terceros</h2>
    <p>
      La Cooperativa puede compartir datos con encargados y aliados necesarios para las finalidades
      descritas (centrales de riesgo, entidades financieras, proveedores de servicios tecnológicos y
      de convenios), bajo acuerdos de confidencialidad y medidas de seguridad. Los recursos web
      (tipografías, estilos y librerías) son servidos desde el propio dominio de la Cooperativa, sin
      cesión de la navegación a terceros.
    </p>

    <h2>10. Seguridad de la información</h2>
    <p>
      La Cooperativa adopta medidas técnicas, humanas y administrativas razonables para proteger los
      datos y evitar su adulteración, pérdida, consulta o uso no autorizado. Los archivos cargados en
      las plataformas se sirven mediante endpoints autenticados y con verificación de permisos.
    </p>

    <h2>11. Vigencia</h2>
    <p>
      Esta política rige a partir de su publicación (<span class="pv-ph">[FECHA]</span>) y hasta su
      actualización. Los cambios sustanciales se informarán por los canales de la Cooperativa. La
      base de datos se conservará mientras subsista la relación con el titular o exista un deber
      legal de conservación.
    </p>
  </div>
</main>

<footer class="pv-foot">
  © <?= date('Y') ?> <span class="pv-ph">[RAZÓN SOCIAL]</span> ·
  <a href="<?= $appBase ?>/"><i class="fas fa-house"></i> Inicio</a>
</footer>

<script src="<?= $appBase ?>/assets/js/theme.js"></script>
</body>
</html>
