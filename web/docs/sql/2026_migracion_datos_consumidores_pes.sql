-- =============================================================================
-- Migración de datos: consumidores hacia profesional_efector_servicio (PES)
-- =============================================================================
-- Orden sugerido:
--   1) Aplicar migración Yii `m260508_000001_profesional_efector_servicio_model` (PES + backfill desde rrhh_*).
--   2) Aplicar `m260508_000002_turnos_id_profesional_efector_servicio` (columna + backfill turnos).
--   3) Re-ejecutar bloques de verificación / UPDATE de este archivo solo si hace falta en un entorno ya parcialmente migrado.
--
-- Esquema de referencia producción: u257309594_bioenlace (reemplazar en USE si aplica).
-- =============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Turnos: asegurar id_profesional_efector_servicio desde legado (idempotente)
-- ---------------------------------------------------------------------------
-- UPDATE turnos t
-- INNER JOIN profesional_efector_servicio pes
--   ON pes.legacy_rrhh_servicio_id = t.id_rrhh_servicio_asignado
--  AND pes.deleted_at IS NULL
-- SET t.id_profesional_efector_servicio = pes.id
-- WHERE t.id_rrhh_servicio_asignado > 0
--   AND (t.id_profesional_efector_servicio IS NULL OR t.id_profesional_efector_servicio <> pes.id);

-- Verificación: turnos con RRHH asignado pero sin PES resuelto
-- SELECT t.id_turnos, t.id_rrhh_servicio_asignado, t.id_profesional_efector_servicio
--   FROM turnos t
--  WHERE t.id_rrhh_servicio_asignado > 0
--    AND t.id_profesional_efector_servicio IS NULL
--  LIMIT 200;

-- ---------------------------------------------------------------------------
-- Próximas tablas (inventario columnas RRHH en 2026_migrate_webvimark_...sql)
-- ---------------------------------------------------------------------------
-- Patrones de JOIN típicos (definir columna destino antes de DROP legado):
--
-- * id_rrhh_servicio_asignado o id_rrhh_servicio (numérico = rrhh_servicio.id):
--     JOIN profesional_efector_servicio pes ON pes.legacy_rrhh_servicio_id = <col> AND pes.deleted_at IS NULL
--
-- * id_rr_hh (rrhh_efector.id_rr_hh) + contexto servicio/efector si hace falta:
--     JOIN rrhh_servicio rs ON rs.id_rr_hh = t.id_rr_hh ...
--     JOIN profesional_efector_servicio pes ON pes.legacy_rrhh_servicio_id = rs.id
--   (o, sin tocar rrhh_servicio: persona+efector+servicio únicos en PES)
--
-- * id_rrhh_efector (si apunta a rrhh_efector.id u otro id): confirmar semántica en cada tabla.
-- =============================================================================
