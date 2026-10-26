#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Migrador legacy (multi_app_legacy) -> v2 (multi_app_v2).

Migra:
  usuarios · asociados_control · productos · asignaciones · movimientos · logs
  pagos/transacciones · cobranza · creditos (lineas, solicitudes, variables EAV,
  documentos + archivos) · convenios/boleteria · tienda (catalogo).

NO migra solicitudes (pqrs / tickets / cx_solicitudes / ticketera): en v2 se
crean desde cero.

Uso (Windows / Linux):
  python sql/migrar_v1_a_v2.py                      # todo (append; usar --reset en re-ejecuciones)
  python sql/migrar_v1_a_v2.py --reset              # limpia y migra todo
  python sql/migrar_v1_a_v2.py --only=creditos --reset
  python sql/migrar_v1_a_v2.py --files-src=C:/legacy/uploads
  python sql/migrar_v1_a_v2.py --dry-run

Variables de entorno (o editar los defaults):
  DB_SRC_HOST/PORT/NAME/USER/PASS   (legacy; default 127.0.0.1:3307/multi_app_legacy)
  DB_DST_HOST/PORT/NAME/USER/PASS   (v2;     default 127.0.0.1:3307/multi_app_v2)
  FILES_SRC                         raiz de uploads del legacy (para copiar archivos)
  UPLOAD_DIR                        raiz de uploads de v2 (default: <proyecto>/uploads)

Idempotente por bloque con --reset. Para creditos, el reset borra SOLO las
solicitudes migradas (origen_tabla='credito_docs_solicitudes') y conserva las
nuevas creadas en v2.
"""
import argparse
import hashlib
import os
import re
import shutil
import sys
import urllib.parse

try:
    import mysql.connector
except ImportError:
    sys.exit("Falta mysql-connector-python. Instala: pip install mysql-connector-python")

PROJECT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

BLOCKS = ["usuarios", "asociados", "productos", "asignaciones", "movimientos",
          "logs", "pagos", "cobranza", "creditos", "convenios", "tienda"]

# Cobranza: etiquetas legacy -> codigo v2
COBRANZA_ESTADO = {
    "Sin respuesta": "sin_respuesta",
    "Informa de pago realizado": "informa_pago",
    "Comprometido a realizar el pago": "comprometido",
}
COBRANZA_TIPO = {
    "Email": "email", "Llamada": "llamada", "Mensaje de Texto": "sms", "Whatsapp": "whatsapp",
}


def slug(text, maxlen=30):
    if text is None:
        return None
    s = str(text).strip().lower()
    s = (s.replace("á", "a").replace("é", "e").replace("í", "i").replace("ó", "o")
         .replace("ú", "u").replace("ñ", "n"))
    s = re.sub(r"[^a-z0-9]+", "_", s).strip("_")
    return s[:maxlen]



CAMPOS_TIPADOS = {
    "monto_aprobado": "monto_aprobado",
    "linea_aprobada": "linea_credito_aprobada_id",
    "cuotas_aprobadas": "cuotas_aprobadas",
    "codeudor_aprobado": "codeudor_aprobado",
    "desea_codeudor": "desea_codeudor",
    "tipo_codeudor": "tipo_codeudor",
    "base_aportes": "base_aportes",
    "fianza": "fianza",
    "prestamos_recoger": "prestamos_recoger",
    "dto_papeleria": "dto_papeleria",
    "numero_sifone": "numero_credito_sifone",
    "fecha_desembolso": "fecha_desembolso",
    "valor_desembolso": "valor_real_desembolso",
    "desembolsado_a_pac": "desembolsado_pac",
}

# Categoria de boleteria (legacy) -> convenio_id v2. Mapeo por nombre (ids deterministas 70..81).
BOLETERIA_CONVENIO = {
    "COMBO 3 CINECOLOMBIA": 8, "COMBO 1 CINECOLOMBIA": 8, "CINECOLOMBIA 2D": 8,
    "COMBO 1 CINEMARK": 9, "CINEMARK 2D": 9, "COMBO 3 CINEMARK": 9,
    "MULTIPARQUE": 5, "HELADO POPSY": 26, "JAIME DUQUE": 1, "PISCILAGO": 11,
    "SALITRE MAGICO": 3, "COMBO KFC": 25,
}

# Alias Sifone (tipopr) por nombre de línea de crédito -> credito_lineas.sifone_alias.
# Los usa vw_creditos para resolver tasa/seguro. Antes vivía en la migración 014.
SIFONE_ALIAS = {
    "CONSUMO 1.7 ESPECIAL 2025": "consumo,consumo-,consumo-25,CONSUMO-24",
    "CREDIAGIL 1.9 2026": "CREDIAGIL-2026",
    "CONSUMO 1.8 ESPECIAL 2026": "CONSUMO ESPECIAL 1.8-2026",
    "BAJO CONSUMO 2.0 NUE AP 2026": "BAJO CONSUMO 2026",
    "LIBRANZA 1.5 2026": "LIBRANZA  1.5-2026",
    "CREDI-APORTES 2026": "CREDI-PORTES-2026",
    "CREDIPOLIZA 1.4 2026": "CREDIPOLIZA  1.4-2026",
    "TECNO-TELMOVIL 2026": "TECNO-TELMOVIL -2026",
    "EDUCACION 1.5 APORTE 2026": "EDUCACION  1.5-2026",
    "CONSUMO 1.7 ESPECIAL 2026": "CONSUMO-ESPECIAL-1.7-2026",
    "CRED-APORTES PLUS": "CREDIAPORTES PLUS-2026",
    "EMPRENDER 2.1 2026": "EMPRENDER-2.1-2026",
}


def env_cfg(prefix, default_db):
    return {
        "host": os.environ.get(f"{prefix}_HOST", "127.0.0.1"),
        "port": int(os.environ.get(f"{prefix}_PORT", "3307")),
        "db": os.environ.get(f"{prefix}_NAME", default_db),
        "user": os.environ.get(f"{prefix}_USER", "root"),
        "pass": os.environ.get(f"{prefix}_PASS", ""),
    }


def connect(cfg):
    return mysql.connector.connect(
        host=cfg["host"], port=cfg["port"], user=cfg["user"], password=cfg["pass"],
        database=cfg["db"], charset="utf8mb4", autocommit=False,
    )


def q(name):
    return "`" + name + "`"


def insert_many(cur, table, columns, rows, chunk=2000):
    if not rows:
        return 0
    sql = "INSERT INTO {} ({}) VALUES ({})".format(
        q(table), ",".join(q(c) for c in columns), ",".join(["%s"] * len(columns)))
    n = 0
    for i in range(0, len(rows), chunk):
        part = rows[i:i + chunk]
        cur.executemany(sql, part)
        n += len(part)
    return n


def fetch_dicts(cur):
    cols = [d[0] for d in cur.description]
    return [dict(zip(cols, r)) for r in cur.fetchall()]


def truncate(cur, table):
    cur.execute("TRUNCATE TABLE " + q(table))


# --------------------------------------------------------------------------- #
# Bloques
# --------------------------------------------------------------------------- #
def block_usuarios(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        truncate(dc, "usuarios")
    sc.execute("""SELECT id,usuario,password,nombre_completo,email,rol,estado_activo,
                         password_is_one_use,password_changed_at,fecha_creacion,fecha_actualizacion
                  FROM control_usuarios ORDER BY id""")
    rows = []
    valid = {"admin", "lider", "oficina", "tienda", "cobranza_juridica"}
    for r in fetch_dicts(sc):
        rol = r["rol"] if r["rol"] in valid else "oficina"
        rows.append((r["id"], r["usuario"], r["password"], r["nombre_completo"], r["email"],
                     rol, r["estado_activo"], r["password_is_one_use"], r["password_changed_at"],
                     r["fecha_creacion"], r["fecha_actualizacion"]))
    n = insert_many(dc, "usuarios",
                    ["id", "usuario", "password", "nombre_completo", "email", "rol", "estado_activo",
                     "password_is_one_use", "password_changed_at", "fecha_creacion", "fecha_actualizacion"], rows)
    dst.commit()
    return f"usuarios: {n}"


def block_asociados(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        truncate(dc, "asociados_control")
    sc.execute("""SELECT cedula,password_hash,password_set_at,reset_token,reset_token_expires_at,
                         estado_activo,fecha_actualizacion
                  FROM control_asociados""")
    rows = [(r["cedula"], r["password_hash"], r["password_set_at"], r["reset_token"],
             r["reset_token_expires_at"], r["estado_activo"], r["fecha_actualizacion"])
            for r in fetch_dicts(sc)]
    n = insert_many(dc, "asociados_control",
                    ["cedula", "password_hash", "password_set_at", "reset_token",
                     "reset_token_expires_at", "estado_activo", "fecha_actualizacion"], rows)
    dst.commit()
    return f"asociados_control: {n}"


def block_productos(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        truncate(dc, "productos")
    rows = []
    sc.execute("""SELECT id,nombre,descripcion,parametros,valor_minimo,valor_maximo,prioridad,
                         estado_activo,fecha_creacion,fecha_actualizacion
                  FROM control_productos ORDER BY id""")
    for r in fetch_dicts(sc):
        rows.append((r["id"], r["nombre"], r["descripcion"], "producto", r["parametros"], 0,
                     r["valor_minimo"] if r["valor_minimo"] is not None else 0,
                     r["valor_maximo"] if r["valor_maximo"] is not None else 0,
                     r["prioridad"] if r["prioridad"] is not None else 100,
                     r["estado_activo"], None, r["fecha_creacion"], None, r["fecha_actualizacion"]))
    n1 = insert_many(dc, "productos",
                     ["id", "nombre", "descripcion", "tipo", "parametros", "requiere_archivo",
                      "valor_minimo", "valor_maximo", "prioridad", "estado_activo", "creado_por",
                      "fecha_creacion", "actualizado_por", "fecha_actualizacion"], rows)
    # Clausulas: sin id explicito (se insertan despues de los productos -> id auto).
    clausula_map = {}
    sc.execute("""SELECT id,nombre,descripcion,parametros,requiere_archivo,estado_activo,
                         creado_por,fecha_creacion,actualizado_por,fecha_actualizacion
                  FROM control_clausulas ORDER BY id""")
    for r in fetch_dicts(sc):
        dc.execute("""INSERT INTO productos
                      (nombre,descripcion,tipo,parametros,requiere_archivo,valor_minimo,valor_maximo,
                       prioridad,estado_activo,creado_por,fecha_creacion,actualizado_por,fecha_actualizacion)
                      VALUES (%s,%s,'clausula',%s,%s,0,0,100,%s,%s,%s,%s,%s)""",
                   (r["nombre"], r["descripcion"], r["parametros"], r["requiere_archivo"],
                    r["estado_activo"], r["creado_por"], r["fecha_creacion"],
                    r["actualizado_por"], r["fecha_actualizacion"]))
        clausula_map[r["id"]] = dc.lastrowid
    dst.commit()
    args.state["clausula_map"] = clausula_map
    return f"productos: {n1} + {len(clausula_map)} clausulas"


def block_asignaciones(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        truncate(dc, "asociados_productos")
    clausula_map = args.state.get("clausula_map") or {}
    if not clausula_map:
        # resolver si no se corrio el bloque productos en esta pasada
        dc.execute("SELECT id FROM productos WHERE tipo='clausula' ORDER BY id LIMIT 1")
        row = dc.fetchone()
        cl = row[0] if row else 8
        clausula_map = {1: cl}

    rows = []
    sc.execute("""SELECT id,cedula,producto_id,dia_pago,estado_activo,fecha_creacion,fecha_actualizacion
                  FROM control_asignacion_asociado_producto ORDER BY id""")
    for r in fetch_dicts(sc):
        rows.append((r["id"], r["cedula"], r["producto_id"], 0, None,
                     r.get("dia_pago"), None, None,
                     r["estado_activo"], None, r["fecha_creacion"], r["fecha_actualizacion"]))
    n1 = insert_many(dc, "asociados_productos",
                     ["id", "cedula", "producto_id", "monto", "fecha_inicio", "dia_pago",
                      "meses_vigencia", "detalle", "estado_activo", "creado_por",
                      "fecha_creacion", "fecha_actualizacion"], rows)

    n2 = 0
    sc.execute("""SELECT id,asociado_cedula,clausula_id,monto_mensual,fecha_inicio,meses_vigencia,
                         parametros,estado_activo,creado_por,fecha_creacion,fecha_actualizacion
                  FROM control_asignacion_asociado_clausula ORDER BY id""")
    for r in fetch_dicts(sc):
        pid = clausula_map.get(r["clausula_id"], 8)
        # sin id -> auto (evita colision con los ids de producto)
        dc.execute("""INSERT INTO asociados_productos
                      (cedula,producto_id,monto,fecha_inicio,dia_pago,meses_vigencia,detalle,
                       estado_activo,creado_por,fecha_creacion,fecha_actualizacion)
                      VALUES (%s,%s,%s,%s,NULL,%s,%s,%s,%s,%s,%s)""",
                   (r["asociado_cedula"], pid, r["monto_mensual"], r["fecha_inicio"],
                    r["meses_vigencia"], r.get("parametros"), r["estado_activo"],
                    r.get("creado_por"), r["fecha_creacion"], r["fecha_actualizacion"]))
        n2 += 1
    dst.commit()
    return f"asociados_productos: {n1} producto + {n2} clausula"


def block_movimientos(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        truncate(dc, "movimientos_multi")
    rows = []
    sc.execute("""SELECT id,asociado_inicial_cedula,fecha_comision,valor_ganado,observaciones,
                         creado_por,fecha_creacion
                  FROM control_comisiones ORDER BY id""")
    for r in fetch_dicts(sc):
        rows.append((r["id"], r["asociado_inicial_cedula"], "comision", "ingreso",
                     r["valor_ganado"], r["fecha_comision"], r.get("observaciones"),
                     r.get("creado_por"), r["fecha_creacion"]))
    n1 = insert_many(dc, "movimientos_multi",
                     ["id", "cedula", "tipo", "clasificacion", "valor", "fecha", "detalle",
                      "creado_por", "fecha_creacion"], rows)

    n2 = 0
    sc.execute("""SELECT id,fecha_redencion,cedula_asociado,descripcion,total_redencion,
                         usuario_creacion,fecha_creacion
                  FROM redenciones_lista ORDER BY id""")
    for r in fetch_dicts(sc):
        # sin id -> auto (ids de comisiones ya ocupan 1..N)
        dc.execute("""INSERT INTO movimientos_multi
                      (cedula,tipo,clasificacion,valor,fecha,detalle,creado_por,fecha_creacion)
                      VALUES (%s,'redencion_comisiones','egreso',%s,%s,%s,%s,%s)""",
                   (r["cedula_asociado"], -float(r["total_redencion"] or 0), r["fecha_redencion"],
                    r.get("descripcion"), r.get("usuario_creacion"), r["fecha_creacion"]))
        n2 += 1
    dst.commit()
    return f"movimientos_multi: {n1} comisiones + {n2} redenciones"


def block_logs(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        truncate(dc, "logs")
    dc.execute("SELECT id FROM usuarios")
    users = {r[0] for r in dc.fetchall()}
    sc.execute("""SELECT id,id_usuario,timestamp,accion,modulo,detalle,ip_address,user_agent,
                         datos_anteriores,datos_nuevos,nivel
                  FROM control_logs ORDER BY id""")
    rows = []
    for r in fetch_dicts(sc):
        uid = r["id_usuario"] if (r["id_usuario"] in users) else None
        rows.append((r["id"], uid, r["timestamp"], r["accion"], r["modulo"], r.get("detalle"),
                     r.get("ip_address"), r.get("user_agent"), r.get("datos_anteriores"),
                     r.get("datos_nuevos"), r.get("nivel")))
    n = insert_many(dc, "logs",
                    ["id", "id_usuario", "timestamp", "accion", "modulo", "detalle", "ip_address",
                     "user_agent", "datos_anteriores", "datos_nuevos", "nivel"], rows, chunk=1000)
    dst.commit()
    return f"logs: {n}"


def block_pagos(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        for t in ("pagos_pse", "pagos_confiar", "pagos_confirmacion", "pagos_conciliacion",
                  "transacciones_detalle", "transacciones"):
            truncate(dc, t)

    sc.execute("""SELECT pse_id,cus,valor,banco_originador,estado,referencia_1,referencia_2,
                         referencia_3,fecha_hora_creada,fecha_hora_resolucion_de_la_transaccion
                  FROM banco_pse""")
    rows = [(r["pse_id"], r["cus"], r["valor"], r["banco_originador"], r["estado"],
             r["referencia_1"], r["referencia_2"], r["referencia_3"],
             r["fecha_hora_creada"], r["fecha_hora_resolucion_de_la_transaccion"]) for r in fetch_dicts(sc)]
    n_pse = insert_many(dc, "pagos_pse",
                        ["pse_id", "cus", "valor", "banco_originador", "estado", "referencia_1",
                         "referencia_2", "referencia_3", "fecha_creada", "fecha_resolucion"], rows)

    sc.execute("""SELECT confiar_id,fecha,descripcion,documento,oficina,valor_consignacion,
                         valor_retiro,saldo,tipo_transaccion FROM banco_confiar""")
    rows = [(r["confiar_id"], r["fecha"], r.get("descripcion"), r.get("documento"), r.get("oficina"),
             r.get("valor_consignacion"), r.get("valor_retiro"), r.get("saldo"),
             r.get("tipo_transaccion")) for r in fetch_dicts(sc)]
    n_conf = insert_many(dc, "pagos_confiar",
                         ["confiar_id", "fecha", "descripcion", "documento", "oficina",
                          "valor_consignacion", "valor_retiro", "saldo", "tipo_transaccion"], rows)

    dc.execute("SELECT id FROM usuarios")
    users = {r[0] for r in dc.fetchall()}
    sc.execute("""SELECT id,confiar_id,cedula,link_validacion,comentario,estado,motivo_no_valido,
                         no_valido_por,no_valido_fecha,fecha_validacion FROM banco_confirmacion_confiar""")
    rows = []
    for r in fetch_dicts(sc):
        rej = r.get("no_valido_por")
        rej = rej if rej in users else None
        rows.append((r["id"], r["confiar_id"], r.get("cedula"), r.get("comentario"), r.get("estado"),
                     None, r.get("motivo_no_valido"), rej, r.get("no_valido_fecha"),
                     r.get("fecha_validacion")))
    n_confirm = insert_many(dc, "pagos_confirmacion",
                            ["id", "confiar_id", "cedula", "comentario", "estado", "ruta_comprobante",
                             "motivo_rechazo", "rechazado_por", "fecha_rechazo", "fecha_creacion"], rows)

    sc.execute("""SELECT id,pse_id,confiar_id,tipo_asignacion,fecha_validacion
                  FROM banco_asignacion_pse""")
    rows = [(r["id"], r["pse_id"], r["confiar_id"], r.get("tipo_asignacion") or "manual",
             r.get("fecha_validacion")) for r in fetch_dicts(sc)]
    n_conc = insert_many(dc, "pagos_conciliacion",
                         ["id", "pse_id", "confiar_id", "tipo", "fecha_creacion"], rows)

    sc.execute("""SELECT id,cedula,origen_pago,pse_id,confiar_id,recibo_caja_sifone,
                         valor_pago_total,usuario_id,fecha_creacion FROM control_transaccion""")
    rows = []
    for r in fetch_dicts(sc):
        uid = r.get("usuario_id")
        uid = uid if uid in users else None
        rows.append((r["id"], r["cedula"], r.get("origen_pago"), r.get("pse_id"), r.get("confiar_id"),
                     r.get("recibo_caja_sifone"), r.get("valor_pago_total"), uid, r.get("fecha_creacion")))
    n_trx = insert_many(dc, "transacciones",
                        ["id", "cedula", "origen", "pse_id", "confiar_id", "recibo_caja",
                         "valor_total", "usuario_id", "fecha_creacion"], rows)

    sc.execute("""SELECT id,transaccion_id,tipo_rubro,referencia_credito,producto_id,descripcion,
                         valor_recomendado,valor_asignado,fecha_creacion
                  FROM control_transaccion_detalle""")
    rows = [(r["id"], r["transaccion_id"], r.get("tipo_rubro"), r.get("referencia_credito"),
             r.get("producto_id"), r.get("descripcion"), r.get("valor_recomendado"),
             r.get("valor_asignado"), r.get("fecha_creacion")) for r in fetch_dicts(sc)]
    n_det = insert_many(dc, "transacciones_detalle",
                        ["id", "transaccion_id", "tipo_rubro", "referencia_credito", "producto_id",
                         "descripcion", "valor_recomendado", "valor_asignado", "fecha_creacion"], rows)
    dst.commit()
    return f"pagos: pse {n_pse}, confiar {n_conf}, confirmacion {n_confirm}, conciliacion {n_conc}; transacciones {n_trx}+{n_det}"


def block_cobranza(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        for t in ("cobranza_indagaciones_adjuntos", "cobranza_indagaciones", "cobranza_remisiones_juridica",
                  "cobranza_detalle_mora_credito", "cobranza_detalle_mora", "cobranza_comunicaciones"):
            truncate(dc, t)

    sc.execute("""SELECT id,asociado_cedula,tipo_comunicacion,estado,comentario,fecha_compromiso,
                         monto_compromiso,fecha_creacion,tipo_origen FROM cobranza_comunicaciones""")
    rows = []
    for r in fetch_dicts(sc):
        tipo = COBRANZA_TIPO.get(r.get("tipo_comunicacion"), slug(r.get("tipo_comunicacion")))
        estado = COBRANZA_ESTADO.get(r.get("estado"), slug(r.get("estado")))
        rows.append((r["id"], r["asociado_cedula"], tipo, estado,
                     r.get("comentario"), r.get("fecha_compromiso"), r.get("monto_compromiso"),
                     r.get("fecha_creacion"), None, r.get("tipo_origen")))
    n1 = insert_many(dc, "cobranza_comunicaciones",
                     ["id", "asociado_cedula", "tipo_comunicacion", "estado", "comentario",
                      "fecha_compromiso", "monto_compromiso", "fecha", "creado_por", "tipo_origen"], rows)

    sc.execute("""SELECT id,comunicacion_id,asociado_cedula,aportes_monto,total_creditos,
                         creado_por,fecha FROM cobranza_detalle_mora""")
    rows = [(r["id"], r.get("comunicacion_id"), r["asociado_cedula"], r.get("aportes_monto"),
             r.get("total_creditos"), r.get("creado_por"), r.get("fecha")) for r in fetch_dicts(sc)]
    n2 = insert_many(dc, "cobranza_detalle_mora",
                     ["id", "comunicacion_id", "asociado_cedula", "aportes_monto", "total_creditos",
                      "creado_por", "fecha"], rows)

    sc.execute("""SELECT id,detalle_id,numero_credito,deuda_capital,deuda_mora,dias_mora,fecha_pago
                  FROM cobranza_detalle_mora_credito""")
    rows = [(r["id"], r["detalle_id"], r["numero_credito"], r.get("deuda_capital"),
             r.get("deuda_mora"), r.get("dias_mora"), r.get("fecha_pago")) for r in fetch_dicts(sc)]
    n3 = insert_many(dc, "cobranza_detalle_mora_credito",
                     ["id", "detalle_id", "numero_credito", "deuda_capital", "deuda_mora",
                      "dias_mora", "fecha_pago"], rows)

    sc.execute("""SELECT id,asociado_cedula,tipo_indagacion,fecha_creacion,tipo_origen
                  FROM cobranza_indagaciones""")
    rows = [(r["id"], r["asociado_cedula"], r.get("tipo_indagacion"), r.get("fecha_creacion"),
             None, r.get("tipo_origen")) for r in fetch_dicts(sc)]
    n4 = insert_many(dc, "cobranza_indagaciones",
                     ["id", "asociado_cedula", "tipo_indagacion", "fecha", "creado_por", "tipo_origen"], rows)

    sc.execute("""SELECT id,indagacion_id,nombre_archivo,ruta_archivo,tipo_mime,
                         `tamaño_archivo`,fecha_subida FROM cobranza_indagaciones_adjuntos""")
    rows = [(r["id"], r["indagacion_id"], r.get("nombre_archivo"), r.get("ruta_archivo"),
             r.get("tipo_mime"), r.get("tamaño_archivo"), r.get("fecha_subida")) for r in fetch_dicts(sc)]
    n5 = insert_many(dc, "cobranza_indagaciones_adjuntos",
                     ["id", "indagacion_id", "nombre_archivo", "ruta_archivo", "tipo_mime",
                      "tamaño_archivo", "fecha_subida"], rows)

    sc.execute("""SELECT id,asociado_cedula,remitido,fecha_remision,fecha_creacion
                  FROM cobranza_remisiones_juridica""")
    rows = [(r["id"], r["asociado_cedula"], r.get("remitido"), r.get("fecha_remision"),
             None, r.get("fecha_creacion")) for r in fetch_dicts(sc)]
    n6 = insert_many(dc, "cobranza_remisiones_juridica",
                     ["id", "asociado_cedula", "remitido", "fecha_remision", "creado_por",
                      "fecha_creacion"], rows)
    dst.commit()
    return f"cobranza: com {n1}, mora {n2}, mora_cred {n3}, indag {n4}, adj {n5}, remis {n6}"


def _credit_file(src_path_hint, files_src):
    """Devuelve (ruta_fisica|None, relativo_dentro_de_creditos_docs)."""
    if not src_path_hint:
        return None, None
    m = re.search(r"[?&]f=([^&]+)", src_path_hint)
    rel = urllib.parse.unquote(m.group(1)) if m else src_path_hint
    idx = rel.find("creditos_docs/")
    if idx >= 0:
        rest = rel[idx + len("creditos_docs/"):]
    else:
        rest = os.path.basename(rel)
    if not files_src:
        return None, rest
    cands = [os.path.join(files_src, "creditos_docs", rest),
             os.path.join(files_src, rest)]
    for c in cands:
        if os.path.isfile(c):
            return c, rest
    return None, rest


def block_creditos(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        dc.execute("""DELETE FROM credito_campos_valores WHERE solicitud_id IN
                      (SELECT id FROM credito_solicitudes WHERE origen_tabla='credito_docs_solicitudes')""")
        dc.execute("""DELETE FROM credito_documentos WHERE solicitud_id IN
                      (SELECT id FROM credito_solicitudes WHERE origen_tabla='credito_docs_solicitudes')""")
        dc.execute("DELETE FROM credito_solicitudes WHERE origen_tabla='credito_docs_solicitudes'")
        dc.execute("DELETE FROM credito_lineas")
        dst.commit()

    # 1) lineas
    sc.execute("""SELECT id,nombre_credito,limite_meses,tasa,seguro_vida,seguro_deudores,
                         estado_activo,mostrar_en_fabrica,creado_por,fecha_creacion,fecha_actualizacion
                  FROM control_tasas_creditos ORDER BY id""")
    rows = [(r["id"], r["nombre_credito"], r["limite_meses"], r["tasa"], r["seguro_vida"],
             r["seguro_deudores"], r["estado_activo"], r["mostrar_en_fabrica"], r.get("creado_por"),
             r["fecha_creacion"], r["fecha_actualizacion"],
             SIFONE_ALIAS.get((r["nombre_credito"] or "").strip())) for r in fetch_dicts(sc)]
    n_lineas = insert_many(dc, "credito_lineas",
                           ["id", "nombre_credito", "limite_meses", "tasa", "seguro_vida",
                            "seguro_deudores", "estado_activo", "mostrar_en_fabrica", "creado_por",
                            "fecha_creacion", "fecha_actualizacion", "sifone_alias"], rows)

    # 2) solicitudes
    sc.execute("""SELECT id,numero_solicitud,tipo_solicitante,nombre_solicitante,numero_identificacion,
                         numero_telefono,correo_electronico,monto_deseado,monto_aprobado,
                         numero_cuotas_deseadas,cuotas_aprobadas,estado,etapa_actual,numero_credito_sifone,
                         valor_real_desembolso,base_aportes,fianza,prestamos_recoger,dto_papeleria,
                         fecha_desembolso,desembolsado_pac,desea_codeudor,tipo_codeudor,codeudor_aprobado,
                         tasa_interes_id,linea_credito_aprobada_id,comentarios_rechazo,creado_por,
                         fecha_creacion,fecha_actualizacion,cedula_asociado,origen,
                         aceptado_por_asociado,fecha_aceptacion,ip_aceptacion,
                         acepta_tratamiento_datos,acepta_ofertas_comerciales
                  FROM credito_docs_solicitudes ORDER BY id""")
    sols = fetch_dicts(sc)
    rows = []
    migrated_ids = set()
    derived = {}
    skipped = 0
    for r in sols:
        cedula = (r.get("cedula_asociado") or r.get("numero_identificacion") or "").strip()
        if not cedula:
            skipped += 1
            continue
        migrated_ids.add(r["id"])
        # codeudor: el select v2 usa no_necesita / codeudor_dependiente / codeudor_pensionado
        tipo_cd = (r.get("tipo_codeudor") or "").strip()
        codeudor_ap = tipo_cd if tipo_cd in ("no_necesita", "codeudor_dependiente", "codeudor_pensionado") else None
        if codeudor_ap is None and r.get("codeudor_aprobado") is not None:
            codeudor_ap = "codeudor_dependiente" if int(r["codeudor_aprobado"]) == 1 else "no_necesita"
        tipo_cd_v2 = tipo_cd if tipo_cd in ("codeudor_dependiente", "codeudor_pensionado") else None
        derived[r["id"]] = {"codeudor_aprobado": codeudor_ap, "tipo_codeudor": tipo_cd_v2}
        rows.append((r["id"], r["numero_solicitud"], r.get("origen"), cedula,
                     r.get("tipo_solicitante"), r.get("nombre_solicitante"), r.get("numero_identificacion"),
                     r.get("numero_telefono"), r.get("correo_electronico"), r.get("monto_deseado"),
                     r.get("numero_cuotas_deseadas"), r.get("desea_codeudor"), tipo_cd_v2,
                     r.get("tasa_interes_id"), r.get("monto_aprobado"), r.get("cuotas_aprobadas"),
                     codeudor_ap, r.get("linea_credito_aprobada_id"), r.get("estado"),
                     r.get("etapa_actual"), r.get("comentarios_rechazo"), r.get("base_aportes"),
                     r.get("fianza"), r.get("prestamos_recoger"), r.get("dto_papeleria"),
                     r.get("desembolsado_pac"), r.get("numero_credito_sifone"),
                     r.get("valor_real_desembolso"), r.get("fecha_desembolso"),
                     r.get("aceptado_por_asociado"), r.get("fecha_aceptacion"), r.get("ip_aceptacion"),
                     "credito_docs_solicitudes", r["id"], None, r.get("creado_por"),
                     r.get("fecha_creacion"), r.get("fecha_actualizacion")))
    n_sol = insert_many(dc, "credito_solicitudes",
                        ["id", "numero_solicitud", "origen", "cedula_asociado", "tipo_solicitante",
                         "nombre_solicitante", "numero_identificacion", "numero_telefono",
                         "correo_electronico", "monto_deseado", "numero_cuotas_deseadas",
                         "desea_codeudor", "tipo_codeudor", "tasa_interes_id", "monto_aprobado",
                         "cuotas_aprobadas", "codeudor_aprobado", "linea_credito_aprobada_id",
                         "estado", "etapa_actual", "comentarios_rechazo", "base_aportes", "fianza",
                         "prestamos_recoger", "dto_papeleria", "desembolsado_pac",
                         "numero_credito_sifone", "valor_real_desembolso", "fecha_desembolso",
                         "aceptado_por_asociado", "fecha_aceptacion", "ip_aceptacion",
                         "origen_tabla", "origen_id", "datos_legacy", "creado_por",
                         "fecha_creacion", "fecha_actualizacion"], rows)

    # 3) variables EAV + aceptaciones: config resuelta por clave (NO por id fijo)
    dc.execute("SELECT id, campo FROM credito_config_campo WHERE estado_activo=1")
    campo_id = {r[1]: r[0] for r in dc.fetchall()}
    dc.execute("SELECT id, clave FROM credito_config_aceptacion WHERE estado_activo=1")
    acept_id = {r[1]: r[0] for r in dc.fetchall()}

    sc.execute("SELECT * FROM credito_docs_solicitudes ORDER BY id")
    sols_full = fetch_dicts(sc)
    eav_rows = []
    for r in sols_full:
        sid = r["id"]
        if sid not in migrated_ids:
            continue
        for campo, col in CAMPOS_TIPADOS.items():
            cid = campo_id.get(campo)
            if not cid:
                continue
            val = derived.get(sid, {}).get(campo, r.get(col))
            if val is None or val == "":
                continue
            if campo in ("desea_codeudor", "desembolsado_a_pac"):
                val = "1" if int(val) else "0"
            eav_rows.append((sid, cid, None, str(val)))
        for clave, src_col in (("acepta_tratamiento_datos", "acepta_tratamiento_datos"),
                               ("acepta_ofertas_comerciales", "acepta_ofertas_comerciales")):
            aid = acept_id.get(clave)
            if aid and int(r.get(src_col) or 0) == 1:
                eav_rows.append((sid, None, aid, "1"))
    n_eav = insert_many(dc, "credito_campos_valores",
                        ["solicitud_id", "config_campo_id", "config_aceptacion_id", "valor"], eav_rows)

    # 4) documentos + archivos con convencion opaca (cred_<id>_<clave>.<ext>)
    files_src = args.files_src
    upload_dir = args.upload_dir
    dest_dir = os.path.join(upload_dir, "creditos")
    os.makedirs(dest_dir, exist_ok=True)
    sc.execute("""SELECT id,solicitud_id,etapa,tipo_documento,nombre_archivo,ruta_archivo,
                         `tamaño_archivo`,tipo_mime,es_obligatorio,es_opcional,fecha_subida,subido_por
                  FROM credito_docs_documentos ORDER BY id""")
    docs = fetch_dicts(sc)
    doc_rows = []
    files_ok = files_missing = 0
    for r in docs:
        if r["solicitud_id"] not in migrated_ids:
            continue
        physical, rest = _credit_file(r.get("ruta_archivo"), files_src)
        base = os.path.basename(rest) if rest else (r.get("nombre_archivo") or "archivo")
        ext = (os.path.splitext(base)[1] or ".bin").lower()
        clave = hashlib.sha256(f"{r['solicitud_id']}:{base}".encode("utf-8")).hexdigest()[:12]
        nombre = f"cred_{r['solicitud_id']}_{clave}{ext}"
        if physical:
            try:
                shutil.copy2(physical, os.path.join(dest_dir, nombre))
                files_ok += 1
            except Exception:
                files_missing += 1
        else:
            files_missing += 1
        doc_rows.append((r["id"], r["solicitud_id"], r.get("etapa"), r.get("tipo_documento"),
                         r.get("nombre_archivo"), nombre, r.get("tamaño_archivo"), r.get("tipo_mime"),
                         r.get("es_obligatorio"), r.get("es_opcional"), r.get("subido_por"),
                         r.get("fecha_subida")))
    n_docs = insert_many(dc, "credito_documentos",
                         ["id", "solicitud_id", "etapa", "tipo_documento", "nombre_archivo",
                          "ruta_archivo", "tamaño_archivo", "tipo_mime", "es_obligatorio",
                          "es_opcional", "subido_por", "fecha_subida"], doc_rows)
    dst.commit()
    return (f"creditos: lineas {n_lineas}, solicitudes {n_sol} (omitidas {skipped}), EAV {n_eav}, "
            f"docs {n_docs} (archivos copiados {files_ok}, faltantes {files_missing})")


def block_convenios(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        for t in ("boleteria_eventos", "boleteria_boletas", "convenios_productos", "convenios"):
            truncate(dc, t)

    sc.execute("""SELECT id,nombre,descripcion,telefono,correo,link,anexo_nombre,imagen,activo,
                         creado_por,fecha_creacion,fecha_actualizacion FROM convenios""")
    rows = [(r["id"], r["nombre"], r.get("descripcion"), r.get("telefono"), r.get("correo"),
             r.get("link"), r.get("anexo_nombre"), r.get("imagen"), r.get("activo"),
             r.get("creado_por"), r.get("fecha_creacion"), r.get("fecha_actualizacion"))
            for r in fetch_dicts(sc)]
    n_conv = insert_many(dc, "convenios",
                         ["id", "nombre", "descripcion", "telefono", "correo", "link", "anexo_nombre",
                          "imagen", "activo", "creado_por", "fecha_creacion", "fecha_actualizacion"], rows)

    sc.execute("""SELECT id,convenio_id,nombre,descripcion,valor,valor_general,imagen,activo,
                         creado_por,fecha_creacion,fecha_actualizacion FROM convenios_productos""")
    rows = [(r["id"], r["convenio_id"], r["nombre"], r.get("descripcion"), r.get("valor"),
             r.get("valor_general"), r.get("imagen"), r.get("activo"), 0, None, None,
             r.get("creado_por"), r.get("fecha_creacion"), r.get("fecha_actualizacion"))
            for r in fetch_dicts(sc)]
    n_prod = insert_many(dc, "convenios_productos",
                         ["id", "convenio_id", "nombre", "descripcion", "valor", "valor_general",
                          "imagen", "activo", "maneja_stock", "precio_compra", "precio_venta",
                          "creado_por", "fecha_creacion", "fecha_actualizacion"], rows)

    # 12 productos-boleta desde boleteria_categoria (ids auto: 70..81)
    cat_map = {}
    sc.execute("""SELECT id,nombre,precio_compra,precio_venta,descripcion,estado,fecha_creacion,
                         fecha_actualizacion FROM boleteria_categoria ORDER BY id""")
    for r in fetch_dicts(sc):
        conv = BOLETERIA_CONVENIO.get((r["nombre"] or "").strip().upper())
        if not conv:
            continue
        dc.execute("""INSERT INTO convenios_productos
                      (convenio_id,nombre,descripcion,maneja_stock,precio_compra,precio_venta,
                       activo,creado_por,fecha_creacion,fecha_actualizacion)
                      VALUES (%s,%s,%s,1,%s,%s,%s,NULL,%s,%s)""",
                   (conv, r["nombre"], r.get("descripcion"), r.get("precio_compra"),
                    r.get("precio_venta"), 1 if str(r.get("estado") or "").lower() in ("activo", "1") else 0,
                    r.get("fecha_creacion"), r.get("fecha_actualizacion")))
        cat_map[r["id"]] = dc.lastrowid

    # boletas: categoria_id -> producto_id
    sc.execute("""SELECT id,categoria_id,serial,numero_factura,precio_compra_snapshot,
                         precio_venta_snapshot,archivo_ruta,fecha_vencimiento,estado,asociado_cedula,
                         metodo_venta,comprobante,fecha_creacion,fecha_vendida,fecha_contabilizacion,
                         fecha_actualizacion,creado_por,vendido_por,contabilizado_por
                  FROM boleteria_boletas""")
    rows = []
    for r in fetch_dicts(sc):
        pid = cat_map.get(r.get("categoria_id"))
        rows.append((r["id"], pid, r["serial"], r.get("numero_factura"), r.get("precio_compra_snapshot"),
                     r.get("precio_venta_snapshot"), r.get("archivo_ruta"), r.get("fecha_vencimiento"),
                     r.get("estado"), r.get("asociado_cedula"), r.get("metodo_venta"), r.get("comprobante"),
                     None, None, r.get("fecha_creacion"), r.get("fecha_vendida"),
                     r.get("fecha_contabilizacion"), r.get("fecha_actualizacion"), r.get("creado_por"),
                     r.get("vendido_por"), r.get("contabilizado_por")))
    n_bol = insert_many(dc, "boleteria_boletas",
                        ["id", "producto_id", "serial", "numero_factura", "precio_compra_snapshot",
                         "precio_venta_snapshot", "archivo_ruta", "fecha_vencimiento", "estado",
                         "asociado_cedula", "metodo_venta", "comprobante", "sifone_numero",
                         "transaccion_id", "fecha_creacion", "fecha_vendida",
                         "fecha_contabilizacion", "fecha_actualizacion", "creado_por",
                         "vendido_por", "contabilizado_por"], rows)

    sc.execute("""SELECT id,boleta_id,usuario_id,accion,estado_anterior,estado_nuevo,detalle,fecha
                  FROM boleteria_eventos""")
    rows = [(r["id"], r["boleta_id"], r.get("usuario_id"), r.get("accion"), r.get("estado_anterior"),
             r.get("estado_nuevo"), r.get("detalle"), r.get("fecha")) for r in fetch_dicts(sc)]
    n_ev = insert_many(dc, "boleteria_eventos",
                       ["id", "boleta_id", "usuario_id", "accion", "estado_anterior", "estado_nuevo",
                        "detalle", "fecha"], rows)
    dst.commit()
    return f"convenios: {n_conv}, productos {n_prod}+{len(cat_map)} boletas, boletas {n_bol}, eventos {n_ev}"


def block_tienda(src, dst, args):
    sc, dc = src.cursor(), dst.cursor()
    if args.reset:
        for t in ("tienda_promociones", "tienda_productos", "tienda_marcas", "tienda_categorias",
                  "tienda_clientes"):
            truncate(dc, t)

    sc.execute("SELECT id,nombre,estado_activo,fecha_creacion,fecha_actualizacion FROM tienda_categoria")
    rows = [(r["id"], r["nombre"], r.get("estado_activo"), None, r.get("fecha_creacion"),
             r.get("fecha_actualizacion")) for r in fetch_dicts(sc)]
    n_cat = insert_many(dc, "tienda_categorias",
                        ["id", "nombre", "estado_activo", "creado_por", "fecha_creacion",
                         "fecha_actualizacion"], rows)

    sc.execute("SELECT id,nombre,estado_activo,fecha_creacion,fecha_actualizacion FROM tienda_marca")
    rows = [(r["id"], r["nombre"], r.get("estado_activo"), None, r.get("fecha_creacion"),
             r.get("fecha_actualizacion")) for r in fetch_dicts(sc)]
    n_mar = insert_many(dc, "tienda_marcas",
                        ["id", "nombre", "estado_activo", "creado_por", "fecha_creacion",
                         "fecha_actualizacion"], rows)

    sc.execute("""SELECT id,categoria_id,marca_id,nombre,foto_url,descripcion,precio_compra_aprox,
                         precio_venta_aprox,estado_activo,agotado,fecha_creacion,fecha_actualizacion
                  FROM tienda_producto""")
    rows = []
    for r in fetch_dicts(sc):
        rows.append((r["id"], r["categoria_id"], r["marca_id"], r["nombre"], r.get("descripcion"),
                     None, r.get("precio_compra_aprox"), r.get("precio_venta_aprox"),
                     r.get("estado_activo"), r.get("agotado") or 0, None,
                     r.get("fecha_creacion"), r.get("fecha_actualizacion")))
    n_prod = insert_many(dc, "tienda_productos",
                         ["id", "categoria_id", "marca_id", "nombre", "descripcion", "foto_path",
                          "precio_compra_sugerido", "precio_venta_sugerido", "estado_activo",
                          "agotado", "creado_por", "fecha_creacion", "fecha_actualizacion"], rows)

    sc.execute("SELECT id,nombre,nit_cedula,telefono,email,fecha_creacion FROM tienda_clientes")
    rows = [(r["id"], "externo", None, r.get("nit_cedula"), r["nombre"], r.get("telefono"),
             r.get("email"), 1, r.get("fecha_creacion"), None) for r in fetch_dicts(sc)]
    n_cli = insert_many(dc, "tienda_clientes",
                        ["id", "tipo", "cedula", "nit_cedula", "nombre", "telefono", "email",
                         "estado_activo", "fecha_creacion", "fecha_actualizacion"], rows)

    sc.execute("""SELECT id,producto_id,precio_descuento,fecha_inicio,fecha_fin,estado_activo,
                         fecha_creacion,fecha_actualizacion FROM tienda_promocion""")
    rows = [(r["id"], r["producto_id"], r.get("precio_descuento"), r.get("fecha_inicio"),
             r.get("fecha_fin"), r.get("estado_activo"), None, r.get("fecha_creacion"),
             r.get("fecha_actualizacion")) for r in fetch_dicts(sc)]
    n_prom = insert_many(dc, "tienda_promociones",
                         ["id", "producto_id", "precio_descuento", "fecha_inicio", "fecha_fin",
                          "estado_activo", "creado_por", "fecha_creacion", "fecha_actualizacion"], rows)
    dst.commit()
    return f"tienda: cat {n_cat}, marcas {n_mar}, productos {n_prod}, clientes {n_cli}, promos {n_prom}"


HANDLERS = {
    "usuarios": block_usuarios,
    "asociados": block_asociados,
    "productos": block_productos,
    "asignaciones": block_asignaciones,
    "movimientos": block_movimientos,
    "logs": block_logs,
    "pagos": block_pagos,
    "cobranza": block_cobranza,
    "creditos": block_creditos,
    "convenios": block_convenios,
    "tienda": block_tienda,
}


def main():
    ap = argparse.ArgumentParser(description="Migrador legacy -> v2 (sin solicitudes)")
    ap.add_argument("--only", default="", help="bloques separados por coma: " + ",".join(BLOCKS))
    ap.add_argument("--reset", action="store_true", help="limpiar las tablas destino del bloque antes de insertar")
    ap.add_argument("--files-src", default=os.environ.get("FILES_SRC", ""),
                    help="raiz de uploads del legacy (contiene creditos_docs/, etc.)")
    ap.add_argument("--upload-dir", default=os.environ.get("UPLOAD_DIR") or os.path.join(PROJECT_DIR, "uploads"))
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()
    args.state = {}

    blocks = [b.strip() for b in args.only.split(",") if b.strip()] or list(BLOCKS)
    unknown = [b for b in blocks if b not in HANDLERS]
    if unknown:
        sys.exit(f"Bloque(s) desconocido(s): {', '.join(unknown)}")

    src = connect(env_cfg("DB_SRC", "multi_app_legacy"))
    dst = connect(env_cfg("DB_DST", "multi_app_v2"))
    print(f"Origen : {src.database}  | Destino: {dst.database}")
    print(f"Bloques: {', '.join(blocks)}  | reset={args.reset}  | files_src={args.files_src or '(no definido)'}")

    if args.dry_run:
        print("DRY-RUN: no se escriben datos.")
        return

    try:
        # 'productos' antes de 'asignaciones' (necesita el id de la clausula)
        for b in blocks:
            out = HANDLERS[b](src, dst, args)
            print(f"[OK] {out}")
        print("Migracion completada.")
    except Exception as e:
        dst.rollback()
        print(f"[ERROR] {e}")
        raise
    finally:
        src.close()
        dst.close()


if __name__ == "__main__":
    main()
