-- =============================================================================
-- Diagnóstico PES vs legacy (RRHH / rrhh_servicio) ANTES de retirar tablas/columnas
-- =============================================================================
-- Motor esperado: MySQL / MariaDB (como en Yii2 típico).
-- Uso sugerido:
--   mysql -u... -p nombre_bd < web/docs/sql/diagnostico_pes_antes_eliminar_legacy.sql
--   o pegar por secciones en el cliente SQL.
--   Seleccione la base en el cliente (p. ej. clic en el nombre) o use  USE `nombre_bd`;
--
-- Objetivo:
--   - Ver cuántas filas siguen SIN id_profesional_efector_servicio pero CON señal legacy.
--   - Revisar en el DDL si aún hay CONSTRAINT … FOREIGN KEY … hacia rrhh_efector / rrhh_servicio / rrhh_laboral
--     (sección 4: SHOW CREATE TABLE, sin consultar information_schema).
--     (No se asume tabla `rr_hh`: en varios esquemas solo existe id_rr_hh vía rrhh_efector.)
--   - Detectar huérfanos (PES en consumidor que no existe o está borrado).
--   - Comparar turnos: PES vs legacy_rrhh_servicio_id del PES (inconsistencias).
--
-- NO ejecuta DROP. Revise los resultados hasta que el negocio apruebe el retiro.
--
-- Base de datos activa obligatoria (tablas sin prefijo `esquema.`). Primera fila: compruebe _bd_sesion.
-- =============================================================================

SELECT DATABASE() AS _bd_sesion;

-- ---------------------------------------------------------------------------
-- 0) Resumen profesional_efector_servicio
-- ---------------------------------------------------------------------------
SELECT '0_pes_resumen' AS seccion,
       COUNT(*) AS total_filas,
       SUM(deleted_at IS NULL) AS activos,
       SUM(deleted_at IS NOT NULL) AS soft_deleted,
       SUM(legacy_rrhh_servicio_id IS NOT NULL AND legacy_rrhh_servicio_id <> 0) AS con_legacy_rrhh_servicio_id
FROM profesional_efector_servicio;

-- rrhh_servicio activos sin fila PES que los reclame como legacy (posible hueco de mapeo)
SELECT '0_rrhh_servicio_sin_pes_legacy' AS seccion,
       COUNT(*) AS filas_rrhh_servicio_activas_sin_pes
FROM rrhh_servicio rs
WHERE rs.deleted_at IS NULL
  AND NOT EXISTS (
        SELECT 1
        FROM profesional_efector_servicio pes
        WHERE pes.legacy_rrhh_servicio_id = rs.id
          AND pes.deleted_at IS NULL
      );

-- (Muestra opcional, limitada: primeros IDs sin mapeo)
-- SELECT rs.id, rs.id_rr_hh, rs.id_servicio
-- FROM rrhh_servicio rs
-- WHERE rs.deleted_at IS NULL
--   AND NOT EXISTS (
--         SELECT 1 FROM profesional_efector_servicio pes
--         WHERE pes.legacy_rrhh_servicio_id = rs.id AND pes.deleted_at IS NULL
--       )
-- LIMIT 50;

-- ---------------------------------------------------------------------------
-- 1) Consumidores: cobertura PES vs legacy (tablas tocadas por migraciones m260508)
--    Ajuste nombres de columna legacy si su esquema difiere.
-- ---------------------------------------------------------------------------
SELECT '1_turnos' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM((id_rrhh_servicio_asignado IS NOT NULL AND id_rrhh_servicio_asignado <> 0)
           OR (id_rr_hh IS NOT NULL AND id_rr_hh <> 0)) AS con_senal_legacy,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND ((id_rrhh_servicio_asignado IS NOT NULL AND id_rrhh_servicio_asignado <> 0)
                OR (id_rr_hh IS NOT NULL AND id_rr_hh <> 0))) AS sin_pes_pero_con_legacy
FROM turnos;

SELECT '1_consultas' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rr_hh IS NOT NULL AND id_rr_hh <> 0) AS con_id_rr_hh,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rr_hh IS NOT NULL AND id_rr_hh <> 0)) AS sin_pes_pero_con_rr_hh
FROM consultas;

SELECT '1_consultas_derivaciones' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rr_hh IS NOT NULL AND id_rr_hh <> 0) AS con_id_rr_hh,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rr_hh IS NOT NULL AND id_rr_hh <> 0)) AS sin_pes_pero_con_rr_hh
FROM consultas_derivaciones;

SELECT '1_documentos_externos' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rrhh_servicio IS NOT NULL AND id_rrhh_servicio <> 0) AS con_id_rrhh_servicio,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rrhh_servicio IS NOT NULL AND id_rrhh_servicio <> 0)) AS sin_pes_pero_con_legacy
FROM documentos_externos;

SELECT '1_guardia' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rrhh_asignado IS NOT NULL AND id_rrhh_asignado <> 0) AS con_id_rrhh_asignado,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rrhh_asignado IS NOT NULL AND id_rrhh_asignado <> 0)) AS sin_pes_pero_con_legacy
FROM guardia;

SELECT '1_atenciones_enfermeria' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM((id_rr_hh IS NOT NULL AND id_rr_hh <> 0)
           OR (id_rrhh_servicio IS NOT NULL AND id_rrhh_servicio <> 0)) AS con_senal_legacy,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND ((id_rr_hh IS NOT NULL AND id_rr_hh <> 0)
                OR (id_rrhh_servicio IS NOT NULL AND id_rrhh_servicio <> 0))) AS sin_pes_pero_con_legacy
FROM atenciones_enfermeria;

SELECT '1_consultas_suministro_medicamento' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rrhh IS NOT NULL AND id_rrhh <> 0) AS con_id_rrhh,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rrhh IS NOT NULL AND id_rrhh <> 0)) AS sin_pes_pero_con_legacy
FROM consultas_suministro_medicamento;

SELECT '1_seg_nivel_internacion' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rrhh IS NOT NULL AND id_rrhh <> 0) AS con_id_rrhh,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rrhh IS NOT NULL AND id_rrhh <> 0)) AS sin_pes_pero_con_legacy
FROM seg_nivel_internacion;

SELECT '1_encuesta_parches_mamarios' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rr_hh IS NOT NULL AND id_rr_hh <> 0) AS con_id_rr_hh,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rr_hh IS NOT NULL AND id_rr_hh <> 0)) AS sin_pes_pero_con_rr_hh
FROM encuesta_parches_mamarios;

SELECT '1_persona_programa' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rrhh_efector IS NOT NULL AND id_rrhh_efector <> 0) AS con_id_rrhh_efector,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rrhh_efector IS NOT NULL AND id_rrhh_efector <> 0)) AS sin_pes_pero_con_legacy
FROM persona_programa;

SELECT '1_persona_programa_diabetes' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rrhh_efector IS NOT NULL AND id_rrhh_efector <> 0) AS con_id_rrhh_efector,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rrhh_efector IS NOT NULL AND id_rrhh_efector <> 0)) AS sin_pes_pero_con_legacy
FROM persona_programa_diabetes;

SELECT '1_dispensa_programa_diabetes' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rrhh_efector IS NOT NULL AND id_rrhh_efector <> 0) AS con_id_rrhh_efector,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rrhh_efector IS NOT NULL AND id_rrhh_efector <> 0)) AS sin_pes_pero_con_legacy
FROM dispensa_programa_diabetes;

SELECT '1_sumar_autofacturacion' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rr_hh IS NOT NULL AND id_rr_hh <> 0) AS con_id_rr_hh,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rr_hh IS NOT NULL AND id_rr_hh <> 0)) AS sin_pes_pero_con_rr_hh
FROM sumar_autofacturacion;

SELECT '1_abreviaturas_rrhh' AS tabla,
       COUNT(*) AS total,
       SUM(id_profesional_efector_servicio IS NOT NULL AND id_profesional_efector_servicio <> 0) AS con_pes,
       SUM(id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0) AS sin_pes,
       SUM(id_rr_hh IS NOT NULL AND id_rr_hh <> 0) AS con_id_rr_hh,
       SUM((id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
           AND (id_rr_hh IS NOT NULL AND id_rr_hh <> 0)) AS sin_pes_pero_con_rr_hh
FROM abreviaturas_rrhh;

-- Tabla opcional (puede no existir en todos los entornos). Si falla, comentar este bloque.
SELECT '1_seg_nivel_internacion_practica' AS tabla,
       COUNT(*) AS total,
       SUM((id_profesional_efector_servicio_solicita IS NOT NULL AND id_profesional_efector_servicio_solicita <> 0)
           OR (id_profesional_efector_servicio_realiza IS NOT NULL AND id_profesional_efector_servicio_realiza <> 0)) AS filas_con_alguna_pes,
       SUM((id_profesional_efector_servicio_solicita IS NULL OR id_profesional_efector_servicio_solicita = 0)
           AND (id_rrhh_solicita IS NOT NULL AND id_rrhh_solicita <> 0)) AS sin_pes_sol_pero_rrhh,
       SUM((id_profesional_efector_servicio_realiza IS NULL OR id_profesional_efector_servicio_realiza = 0)
           AND (id_rrhh_realiza IS NOT NULL AND id_rrhh_realiza <> 0)) AS sin_pes_rea_pero_rrhh
FROM seg_nivel_internacion_practica;

-- ---------------------------------------------------------------------------
-- 2) Huérfanos: id_profesional_efector_servicio apunta a PES inexistente o borrado
-- ---------------------------------------------------------------------------
SELECT '2_turnos_pes_huérfano' AS chequeo, COUNT(*) AS cnt
FROM turnos t
LEFT JOIN profesional_efector_servicio pes
       ON pes.id = t.id_profesional_efector_servicio AND pes.deleted_at IS NULL
WHERE t.id_profesional_efector_servicio IS NOT NULL
  AND t.id_profesional_efector_servicio <> 0
  AND pes.id IS NULL;

SELECT '2_consultas_pes_huérfano' AS chequeo, COUNT(*) AS cnt
FROM consultas c
LEFT JOIN profesional_efector_servicio pes
       ON pes.id = c.id_profesional_efector_servicio AND pes.deleted_at IS NULL
WHERE c.id_profesional_efector_servicio IS NOT NULL
  AND c.id_profesional_efector_servicio <> 0
  AND pes.id IS NULL;

-- ---------------------------------------------------------------------------
-- 3) Turnos: PES asignado pero distinto al que indicaría id_rrhh_servicio_asignado
--    (legacy_rrhh_servicio_id en PES vs columna en turno)
-- ---------------------------------------------------------------------------
SELECT '3_turnos_pes_vs_legacy_slot' AS chequeo, COUNT(*) AS cnt
FROM turnos t
INNER JOIN profesional_efector_servicio pes
        ON pes.id = t.id_profesional_efector_servicio AND pes.deleted_at IS NULL
WHERE t.id_rrhh_servicio_asignado IS NOT NULL
  AND t.id_rrhh_servicio_asignado <> 0
  AND pes.legacy_rrhh_servicio_id IS NOT NULL
  AND pes.legacy_rrhh_servicio_id <> t.id_rrhh_servicio_asignado;

-- ---------------------------------------------------------------------------
-- 4) DDL: buscar FOREIGN KEY hacia rrhh_efector / rrhh_servicio / rrhh_laboral
--    Sin SELECT a information_schema (útil si el hosting lo bloquea). En cada resultado busque en el
--    texto "CONSTRAINT" / "FOREIGN KEY" y "REFERENCES `rrhh_…`". Omita tablas que no existan en su esquema.
-- ---------------------------------------------------------------------------
SHOW CREATE TABLE profesional_efector_servicio;
SHOW CREATE TABLE turnos;
SHOW CREATE TABLE consultas;
SHOW CREATE TABLE consultas_derivaciones;
SHOW CREATE TABLE documentos_externos;
SHOW CREATE TABLE guardia;
SHOW CREATE TABLE atenciones_enfermeria;
SHOW CREATE TABLE consultas_suministro_medicamento;
SHOW CREATE TABLE seg_nivel_internacion;
SHOW CREATE TABLE encuesta_parches_mamarios;
SHOW CREATE TABLE persona_programa;
SHOW CREATE TABLE persona_programa_diabetes;
SHOW CREATE TABLE dispensa_programa_diabetes;
SHOW CREATE TABLE sumar_autofacturacion;
SHOW CREATE TABLE abreviaturas_rrhh;
SHOW CREATE TABLE rrhh_efector;
SHOW CREATE TABLE rrhh_servicio;
-- Si existe en su entorno (no todas las instalaciones tienen rrhh_laboral):
-- SHOW CREATE TABLE rrhh_laboral;
-- SHOW CREATE TABLE seg_nivel_internacion_practica;

-- ---------------------------------------------------------------------------
-- 5) Conteo de filas en tablas legacy (referencia para plan de retiro)
-- ---------------------------------------------------------------------------
-- Si en su esquema existe tabla `rr_hh` (p. ej. joins viejos en PHP), descomente:
-- SELECT '5_rr_hh' AS tabla, COUNT(*) AS cnt FROM rr_hh;
SELECT '5_rrhh_efector_distinct_id_rr_hh_activos' AS metrica,
       COUNT(DISTINCT id_rr_hh) AS cnt
FROM rrhh_efector
WHERE deleted_at IS NULL;
SELECT '5_rrhh_efector' AS tabla, COUNT(*) AS cnt FROM rrhh_efector;
SELECT '5_rrhh_servicio' AS tabla, COUNT(*) AS cnt FROM rrhh_servicio;

-- =============================================================================
-- Criterio práctico antes de eliminar columnas/tabla:
--   - sin_pes_pero_con_legacy = 0 en tablas críticas (o filas aceptadas como histórico).
--   - pes_huérfano = 0.
--   - En sección 4, ningún DDL con REFERENCES a rrhh_efector / rrhh_servicio / rrhh_laboral (o ya planificado).
--   - Acuerdo explícito sobre módulos que aún lean solo RRHH (p. ej. solicitudes por id_rr_hh).
-- =============================================================================
