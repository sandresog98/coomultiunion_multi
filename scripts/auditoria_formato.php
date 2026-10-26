<?php
declare(strict_types=1);

/**
 * Auditoría y reconciliación de formato de rutas de archivo (Apéndice A §2.3).
 *
 * Obligatorio: la ruta guardada en BD debe apuntar al archivo OPACO real
 *   <base>_<codigo>_<clave>.<ext>  (formato único §2.3, sin espacios ni nombre original del usuario).
 * Este script clasifica cada fila y (en modo aplica) renombra el físico + corrige la columna.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Uso (desde la raíz del proyecto):
 *   /opt/lampp/bin/php scripts/auditoria_formato.php               # AUDITORÍA (solo lectura)
 *   /opt/lampp/bin/php scripts/auditoria_formato.php --mapa        # + genera CSV de propuestas
 *   /opt/lampp/bin/php scripts/auditoria_formato.php --aplicar     # RENOMBRA físicos + UPDATE BD (idempotente)
 *   /opt/lampp/bin/php scripts/auditoria_formato.php --modulo=creditos   # restringir a un módulo
 *   /opt/lampp/bin/php scripts/auditoria_formato.php --r2          # además verifica objetos R2 (lento, usa red)
 *   /opt/lampp/bin/php scripts/auditoria_formato.php --csv="sql/dumps/ali_mapa.csv"
 *
 * Clases por fila:
 *   ALINEADA      : la ruta actual ya es el opaco §2.3 esperado y el físico existe.
 *   RECONCILIABLE : hay físico 1:1 (ya opaco o renombrable) y la columna se corrige a §2.3.
 *   NORMALIZABLE  : misma ruta ya opaca pero con formato distinto (solicitudes: prefijo uploads//abs).
 *   HUÉRFANA      : sin físico hoy (no se inventa ruta; se reporta). Incluye rutas vacías/NULL.
 *
 * Caso central de créditos: la fila guarda el NOMBRE ORIGINAL del usuario pero el físico
 * opaco esperado (cred_{solicitud_id}_{md5(id)[:8]}.{ext}) YA existe → RECONCILIABLE (solo UPDATE).
 *
 * Guardrails:
 *   - Nueva ruta SIEMPRE opaca → jamás el nombre original del usuario en BD.
 *   - Idempotente: repetir no duplica ni rompe.
 *   - Antes de --aplicar hacer backup (mariadb-dump) de todas las tablas afectadas.
 *   - R2: este script corrige el estado LOCAL + la columna; para refrescar R2 ejecutar
 *     después `scripts/migrar_r2.php` (idempotente). No borra objetos R2.
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Storage/Storage.php';

$NOW     = date('Ymd_His');
$root    = dirname(__DIR__);
$uploads = rtrim((string) (env('UPLOAD_DIR') ?: $root . '/uploads'), '/');
if ($uploads !== '' && $uploads[0] !== '/') { $uploads = $root . '/' . $uploads; }

$modoMapa    = in_array('--mapa', $argv, true);
$modoAplicar = in_array('--aplicar', $argv, true);
$useR2       = in_array('--r2', $argv, true);
$moduloF     = null;
$csvPath     = null;
foreach ($argv as $i => $a) {
    if (str_starts_with((string) $a, '--modulo=')) { $moduloF = substr((string) $a, strlen('--modulo=')); }
    if (str_starts_with((string) $a, '--csv='))   { $csvPath = substr((string) $a, strlen('--csv=')); }
}
if ($csvPath === null) { $csvPath = $root . '/sql/dumps/ali_' . $NOW . '_mapa.csv'; }

/**
 * @var array<string, array{tabla:string,col:string,id:string,codigo:string,base:string,
 *    dir:string,mod_storage:string,tipo:'rename'|'verify'|'normalize'}>
 */
$MODULOS = [
    'creditos'           => ['tabla'=>'credito_documentos',              'col'=>'ruta_archivo','id'=>'id','codigo'=>'solicitud_id','base'=>'cred','dir'=>'creditos',    'mod_storage'=>'creditos',  'tipo'=>'rename'],
    'boletas'            => ['tabla'=>'boleteria_boletas',               'col'=>'archivo_ruta','id'=>'id','codigo'=>'id',          'base'=>'boleta','dir'=>'boletas','mod_storage'=>'boletas','tipo'=>'rename'],
    'cobranza'           => ['tabla'=>'cobranza_indagaciones_adjuntos',  'col'=>'ruta_archivo','id'=>'id','codigo'=>'id',          'base'=>'adj','dir'=>'cobranza','mod_storage'=>'cobranza','tipo'=>'rename'],
    'pagos'              => ['tabla'=>'pagos_confirmacion',              'col'=>'ruta_comprobante','id'=>'id','codigo'=>'id','base'=>'pago','dir'=>'pagos','mod_storage'=>'pagos','tipo'=>'verify'],
    'solicitudes'        => ['tabla'=>'solicitudes_adjuntos',            'col'=>'ruta_archivo','id'=>'id','codigo'=>'id',          'base'=>'sol','dir'=>'solicitudes','mod_storage'=>'solicitudes','tipo'=>'normalize'],
    'convenios'          => ['tabla'=>'convenios',                       'col'=>'imagen','id'=>'id','codigo'=>'id','base'=>'convenio','dir'=>'convenios','mod_storage'=>'convenios','tipo'=>'rename'],
    'convenios_productos'=> ['tabla'=>'convenios_productos',             'col'=>'imagen','id'=>'id','codigo'=>'id','base'=>'convenio_producto','dir'=>'convenios','mod_storage'=>'convenios','tipo'=>'rename'],
    'tienda'             => ['tabla'=>'tienda_productos',                'col'=>'foto_path','id'=>'id','codigo'=>'id','base'=>'tienda_producto','dir'=>'tienda','mod_storage'=>'tienda','tipo'=>'rename'],
];

if ($moduloF !== null) {
    $MODULOS = array_intersect_key($MODULOS, [$moduloF => true]);
    if (!$MODULOS) { fwrite(STDERR, "Módulo desconocido: $moduloF\n"); exit(1); }
}

echo "═ AUDITORÍA DE FORMATO DE ARCHIVOS (reglas §2.3) ═\n";
echo "Raíz uploads: $uploads\n";
echo "Modo: " . ($modoAplicar ? 'APLICAR (renombra físicos + UPDATE)'
    : ($modoMapa ? 'MAPEO (CSV, no aplica)' : 'AUDITORÍA (solo lectura)')) . "\n";
echo "R2: " . ($useR2 ? 'verifica objetos' : 'solo LOCAL') . "\n";
if ($modoAplicar) { echo "⚠️  Verifica que ya hiciste backup (mariadb-dump) de las tablas afectadas.\n"; }
echo "\n";

$db = db();
$tot = ['total'=>0,'ALINEADA'=>0,'RECONCILIABLE'=>0,'NORMALIZABLE'=>0,'HUÉRFANA'=>0];
$csvRows = [];

foreach ($MODULOS as $mod => $cfg) {
    $tabla=$cfg['tabla']; $col=$cfg['col']; $cid=$cfg['id']; $ccod=$cfg['codigo'];
    $base=$cfg['base']; $fisDir=$uploads.'/'.$cfg['dir']; $modStorage=$cfg['mod_storage'];
    $tipo=$cfg['tipo'];
    $st=['total'=>0,'ALINEADA'=>0,'RECONCILIABLE'=>0,'NORMALIZABLE'=>0,'HUÉRFANA'=>0,'fisico'=>0];
    $storage=null; if ($useR2) { try { $storage=Storage::for($modStorage); } catch (Throwable $e) {} }
    $sql = "SELECT `$cid` AS cid, `$ccod` AS ccod, `$col` AS cur FROM `$tabla`";
    $rows = $db->query($sql)->fetchAll();
    echo "── Módulo: $mod  (tabla $tabla · col $col)\n";

    foreach ($rows as $r) {
        $st['total']++; $tot['total']++;
        $id=(string)$r['cid']; $codigo=(string)$r['ccod'];
        $cur=trim((string)$r['cur']);
        $clave=substr(md5($id),0,8);
        $clasif='HUÉRFANA'; $objetivo=''; $deltaDb=null; $ren=null;

        if ($cur==='') {
            // sin ruta → huérfana (no inventar)
        } elseif ($tipo==='normalize') {
            // Solicitudes: prefijo absoluto/uploads → clave relativa.
            $rel=$cur;
            $posA=strpos($rel,'/uploads/');
            if ($posA!==false) $rel=substr($rel,$posA+strlen('/uploads/'));
            elseif (str_starts_with($rel,'uploads/')) $rel=substr($rel,strlen('uploads/'));
            elseif (str_starts_with($rel,'/')) $rel=ltrim($rel,'/');
            $existe=is_file($uploads.'/'.ltrim($rel,'/'));
            if ($existe) {
                $st['fisico']++;
                if ($rel!==$cur) { $clasif='NORMALIZABLE'; $objetivo=$rel; $deltaDb=[$col,$rel]; }
                else { $clasif='ALINEADA'; $objetivo=$rel; }
            } else { $clasif='HUÉRFANA'; }
        } elseif ($tipo==='verify') {
            // Pagos: ya opaco; solo verificar físico (plano o subcarpeta).
            $existe=is_file($fisDir.'/'.ltrim($cur,'/'));
            $enR2=false;
            if (!$existe && $storage) { try { $enR2=$storage->exists($modStorage.'/'.ltrim($cur,'/')); } catch (Throwable $e) {} }
            if ($existe||$enR2) { $clasif='ALINEADA'; $objetivo=$cur; $st['fisico']++; } else { $clasif='HUÉRFANA'; }
        } elseif ($tipo==='rename') {
            $ext=strtolower(pathinfo($cur, PATHINFO_EXTENSION));
            $ext=($ext!==''&&preg_match('/^[a-z0-9]{1,10}$/i',$ext))?$ext:'bin';
            // objetivo §2.3
            $target = $base.'_'.$codigo.'_'.$clave.'.'.$ext;
            $objetivo=$target;

            // Créditos legacy: ruta = nombre original. El físico opaco esperado PUEDE ya existir.
            if (str_starts_with($cur,$base.'_')) {
                // ruta ya opaca → si el físico del objetivo existe, alineado; si el físico del ACTUAL existe, igual.
                $existeTarget=is_file($fisDir.'/'.$target);
                $existeCur=is_file($fisDir.'/'.ltrim($cur,'/'));
                if ($existeTarget || ($existeCur && $target===$cur)) {
                    $clasif='ALINEADA'; $st['fisico']++;
                    if ($existeCur && $target!==$cur) { $deltaDb=[$col,$target]; } // cur!=target raro
                } else {
                    // físico NO existe bajo el opaco objetivo
                    if ($existeCur) {
                        // existe bajo current (legacy raro con ruta opaca distinta) → renombrar
                        $clasif='RECONCILIABLE'; $st['fisico']++;
                        $ren=[$fisDir.'/'.ltrim($cur,'/'), $fisDir.'/'.$target, $cur, $target]; $deltaDb=[$col,$target];
                    } else {
                        $clasif='HUÉRFANA';
                    }
                }
            } else {
                // ruta = nombre original (problema central de créditos) u otro no-opaco.
                $existeTarget=is_file($fisDir.'/'.$target);
                $existeCur=is_file($fisDir.'/'.ltrim($cur,'/'));
                if ($existeTarget) {
                    // El físico opaco (que debe apuntarse) YA existe → solo UPDATE (sin renombrar).
                    $clasif='RECONCILIABLE'; $st['fisico']++; $deltaDb=[$col,$target];
                } elseif ($existeCur) {
                    // existe bajo el nombre actual (legacy no-opaco) → renombrar a §2.3 + update.
                    $clasif='RECONCILIABLE'; $st['fisico']++;
                    $ren=[$fisDir.'/'.ltrim($cur,'/'), $fisDir.'/'.$target, $cur, $target]; $deltaDb=[$col,$target];
                } else {
                    $clasif='HUÉRFANA';
                }
            }
        }

        $st[$clasif]++; $tot[$clasif]++;
        if (($modoMapa||$modoAplicar) && $clasif!=='ALINEADA') {
            $csvRows[]=[$mod,$tabla,$id,$codigo,$cur,$objetivo,$clasif,$deltaDb?$deltaDb[1]:''];
        }
        if ($modoAplicar && $deltaDb!==null) {
            if ($ren!==null) {
                [$fa,$ta,$fr,$tr]=$ren;
                if (is_file($ta) && $ta!==$fa) { echo "  [colisión] $tr existe; omite $fr\n"; $st['HUÉRFANA']++; continue; }
                if (!@rename($fa,$ta)) { echo "  [fail-ren] $fr → $tr : ".(error_get_last()['message']??'?')."\n"; continue; }
                echo "  [ren] $fr → $tr\n";
            }
            $stmt=$db->prepare("UPDATE `$tabla` SET `{$deltaDb[0]}` = ? WHERE `$cid` = ?");
            $stmt->execute([$deltaDb[1], $id]);
            echo "  [update] $tabla #$id : $col = {$deltaDb[1]}\n";
        }
    }

    printf("  → %s: total %d · ALINEADAS %d · RECONCILIABLES %d · NORMALIZABLES %d · HUÉRFANAS %d (físico e/d %d)\n",
        $mod,$st['total'],$st['ALINEADA'],$st['RECONCILIABLE'],$st['NORMALIZABLE'],$st['HUÉRFANA'],$st['fisico']);
    echo "\n";
}

if ($modoMapa) {
    if (!is_dir(dirname($csvPath))) { @mkdir(dirname($csvPath),0777,true); }
    $fp=fopen($csvPath,'w');
    fputcsv($fp,['modulo','tabla','id','codigo','ruta_actual','ruta_objetivo','clasificacion','ruta_final']);
    foreach ($csvRows as $row) { fputcsv($fp,$row); }
    fclose($fp);
    echo "Mapa de propuestas → $csvPath\n\n";
}

echo "═ TOTAL GLOBAL ═\n";
printf("total %d · ALINEADAS %d · RECONCILIABLES %d · NORMALIZABLES %d · HUÉRFANAS %d\n",
    $tot['total'],$tot['ALINEADA'],$tot['RECONCILIABLE'],$tot['NORMALIZABLE'],$tot['HUÉRFANA']);
echo $modoAplicar ? "\nAplicación terminada. Ejecuta scripts/migrar_r2.php para refrescar R2.\n"
                  : "\n(Solo lectura; usa --mapa para CSV o --aplicar para aplicar.)\n";