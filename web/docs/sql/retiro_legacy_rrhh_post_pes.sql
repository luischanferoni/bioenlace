-- =============================================================================
-- Retiro de columnas y tablas legacy RRHH (post PES)
-- =============================================================================
-- Motor: MySQL / MariaDB (InnoDB).
--
-- ANTES DE EJECUTAR (obligatorio):
--   1) Backup completo (mysqldump) o snapshot de la BD.
--   2) Código desplegado que YA NO use estas columnas ni las tablas rrhh_* / agenda_rrhh.
--      Hoy en el repo aún hay referencias a `rrhh_servicio` (p. ej. joins en Consulta, Turno, Servicio).
--      Si corre esto antes de limpiar el código, la aplicación romperá.
--   3) Ejecutar `diagnostico_pes_antes_eliminar_legacy.sql` en producción: todos los
--      `sin_pes_pero_con_*` y huérfanos deben ser 0 donde aplique.
--   4) Revisar otras tablas no listadas (p. ej. `solicitud_rrhh` guarda id_rr_hh; no hace falta
--      borrar esa columna para DROP TABLE, pero el negocio puede seguir necesitando el número
--      como dato histórico).
--
-- ORDEN: mantenga las fases. Cada ALTER es auto-commit en MySQL.
-- Si alguna columna no existe en su esquema, comente solo esa línea o ese ALTER.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- FASE 0 — Comprobaciones (debe poder leer resultados; todo en 0 filas o cnt=0)
-- ---------------------------------------------------------------------------
SELECT '0_PRE_turnos_sin_pes_con_legacy' AS chequeo, COUNT(*) AS cnt
FROM turnos
WHERE (id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
  AND (
        (id_rrhh_servicio_asignado IS NOT NULL AND id_rrhh_servicio_asignado <> 0)
        OR (id_rr_hh IS NOT NULL AND id_rr_hh <> 0)
      );

SELECT '0_PRE_consultas_sin_pes_con_rr_hh' AS chequeo, COUNT(*) AS cnt
FROM consultas
WHERE (id_profesional_efector_servicio IS NULL OR id_profesional_efector_servicio = 0)
  AND (id_rr_hh IS NOT NULL AND id_rr_hh <> 0);

SELECT '0_PRE_rrhh_servicio_huerfano_en_pes' AS chequeo, COUNT(*) AS cnt
FROM profesional_efector_servicio pes
LEFT JOIN rrhh_servicio rs ON rs.id = pes.legacy_rrhh_servicio_id AND rs.deleted_at IS NULL
WHERE pes.deleted_at IS NULL
  AND pes.legacy_rrhh_servicio_id IS NOT NULL
  AND pes.legacy_rrhh_servicio_id <> 0
  AND rs.id IS NULL;

-- Si algún cnt > 0: NO continúe hasta corregir datos o código.

-- ---------------------------------------------------------------------------
-- FASE 1 — Quitar columnas legacy en consumidores (ajuste comentarios si falta tabla)
-- ---------------------------------------------------------------------------

ALTER TABLE `abreviaturas_rrhh` DROP COLUMN `id_rr_hh`;

ALTER TABLE `atenciones_enfermeria`
  DROP COLUMN `id_rr_hh`,
  DROP COLUMN `id_rrhh_servicio`;

ALTER TABLE `consultas` DROP COLUMN `id_rr_hh`;

ALTER TABLE `consultas_derivaciones` DROP COLUMN `id_rr_hh`;

ALTER TABLE `consultas_suministro_medicamento` DROP COLUMN `id_rrhh`;

ALTER TABLE `dispensa_programa_diabetes` DROP COLUMN `id_rrhh_efector`;

ALTER TABLE `documentos_externos` DROP COLUMN `id_rrhh_servicio`;

ALTER TABLE `encuesta_parches_mamarios` DROP COLUMN `id_rr_hh`;

ALTER TABLE `guardia`
  DROP COLUMN `id_rrhh_asignado`,
  DROP COLUMN `id_rr_hh`;

ALTER TABLE `persona_programa` DROP COLUMN `id_rrhh_efector`;

ALTER TABLE `persona_programa_diabetes` DROP COLUMN `id_rrhh_efector`;

ALTER TABLE `seg_nivel_internacion` DROP COLUMN `id_rrhh`;

-- Si la tabla no existe, comente el bloque siguiente entero.
ALTER TABLE `seg_nivel_internacion_practica`
  DROP COLUMN `id_rrhh_solicita`,
  DROP COLUMN `id_rrhh_realiza`;

ALTER TABLE `sumar_autofacturacion` DROP COLUMN `id_rr_hh`;

ALTER TABLE `turnos`
  DROP COLUMN `id_rr_hh`,
  DROP COLUMN `id_rrhh_servicio_asignado`;

-- ---------------------------------------------------------------------------
-- FASE 2 — PES: quitar puente legacy a rrhh_servicio.id
-- ---------------------------------------------------------------------------

ALTER TABLE `profesional_efector_servicio` DROP COLUMN `legacy_rrhh_servicio_id`;

-- ---------------------------------------------------------------------------
-- FASE 3 — Tablas legacy (orden: dependientes de agenda/servicio primero)
-- ---------------------------------------------------------------------------
-- Descomente y ejecute solo cuando el código no use más estos nombres de tabla.

-- SET FOREIGN_KEY_CHECKS = 0;
-- DROP TABLE IF EXISTS `agenda_rrhh`;
-- DROP TABLE IF EXISTS `rrhh_servicio`;
-- DROP TABLE IF EXISTS `rrhh_efector`;
-- DROP TABLE IF EXISTS `rrhh_laboral`;
-- SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Post-check sugerido: volver a correr secciones 0–3 de diagnostico_pes (ajustando
-- el script si ya no existen columnas). Eliminar de modelos Yii rules/attributes
-- que referencien columnas borradas y relaciones a RrhhServicio / RrhhEfector.
-- =============================================================================
