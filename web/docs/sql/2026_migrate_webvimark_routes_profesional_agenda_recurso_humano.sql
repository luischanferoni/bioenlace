-- =============================================================================
-- Migración Webvimark (UserManagement / Yii2 RBAC): renombrar rutas API en auth_item
--   /api/agenda/*           -> /api/profesional-agenda/*
--   /api/rrhh/*             -> /api/recurso-humano/*
--   /api/agenda/*-agenda-flow (intents) -> /api/profesional-agenda/*-agenda-flow
--
-- Tablas típicas del módulo: auth_item, auth_item_child, auth_item_group, auth_rule
-- (+ auth_assignment: asignación usuario ↔ rol/permiso; referencia auth_item.name).
--
-- Qué toca este script:
--   - Solo `auth_item.name` (permisos tipo ruta, type = 3 en convención Yii).
--
-- Qué NO suele hacer falta tocar:
--   - auth_item_group: grupos lógicos (code, name); no son paths HTTP.
--   - auth_rule: reglas PHP (nombre + data); no son paths de API salvo casos muy raros.
--
-- Relaciones: en el esquema estándar webvimark, `auth_item_child` y `auth_assignment`
-- tienen FK a `auth_item(name)` con ON UPDATE CASCADE: al cambiar `auth_item.name`,
-- MySQL actualiza parent/child e item_name solos. Si tus FKs no tienen ON UPDATE CASCADE,
-- antes de ejecutar: o bien las agregás, o usás SET FOREIGN_KEY_CHECKS=0 y actualizás
-- manualmente auth_item_child y auth_assignment (no incluido aquí).
--
-- Límite varchar(64): verificá que ningún `name` nuevo supere la longitud de la columna
-- (p. ej. SHOW COLUMNS FROM auth_item LIKE 'name';). Ampliá la columna si hace falta.
--
-- ANTES: backup de auth_item, auth_item_child, auth_assignment.
-- DESPUÉS: caché app; usuarios re-login o refresh de permisos.
-- =============================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- Vista previa (opcional)
-- SELECT name, type, group_code FROM auth_item
--  WHERE name LIKE '/api/agenda/%' OR name LIKE '/api/rrhh/%'
--  ORDER BY name;

-- ---------------------------------------------------------------------------
-- Profesional agenda (antes /api/agenda/)
-- ---------------------------------------------------------------------------
UPDATE auth_item SET name = '/api/profesional-agenda/dia'                          WHERE name = '/api/agenda/dia';
UPDATE auth_item SET name = '/api/profesional-agenda/listar-para-recurso'           WHERE name = '/api/agenda/listar-para-recurso';
UPDATE auth_item SET name = '/api/profesional-agenda/listar'                        WHERE name = '/api/agenda/listar';
UPDATE auth_item SET name = '/api/profesional-agenda/crear-para-recurso'            WHERE name = '/api/agenda/crear-para-recurso';
UPDATE auth_item SET name = '/api/profesional-agenda/crear'                         WHERE name = '/api/agenda/crear';
UPDATE auth_item SET name = '/api/profesional-agenda/actualizar-para-recurso'       WHERE name = '/api/agenda/actualizar-para-recurso';
UPDATE auth_item SET name = '/api/profesional-agenda/actualizar'                    WHERE name = '/api/agenda/actualizar';
UPDATE auth_item SET name = '/api/profesional-agenda/eliminar-para-recurso'         WHERE name = '/api/agenda/eliminar-para-recurso';
UPDATE auth_item SET name = '/api/profesional-agenda/eliminar'                      WHERE name = '/api/agenda/eliminar';
UPDATE auth_item SET name = '/api/profesional-agenda/configurar-agenda'             WHERE name = '/api/agenda/configurar-agenda';
UPDATE auth_item SET name = '/api/profesional-agenda/crear-agenda-flow'             WHERE name = '/api/agenda/crear-agenda-flow';
UPDATE auth_item SET name = '/api/profesional-agenda/editar-agenda-flow'            WHERE name = '/api/agenda/editar-agenda-flow';

-- Wildcards y OPTIONS (permisos agrupados / CORS); si quedan viejos, el chequeo RBAC no coincide con rutas nuevas.
UPDATE auth_item SET name = '/api/profesional-agenda/*'     WHERE name = '/api/agenda/*';
UPDATE auth_item SET name = '/api/profesional-agenda/options' WHERE name = '/api/agenda/options';

-- ---------------------------------------------------------------------------
-- Recurso humano (antes /api/rrhh/)
-- ---------------------------------------------------------------------------
UPDATE auth_item SET name = '/api/recurso-humano/autocomplete'                           WHERE name = '/api/rrhh/autocomplete';
UPDATE auth_item SET name = '/api/recurso-humano/listar-mis-servicios-en-efector'       WHERE name = '/api/rrhh/listar-mis-servicios-en-efector';
UPDATE auth_item SET name = '/api/recurso-humano/listar-por-efector'                     WHERE name = '/api/rrhh/listar-por-efector';
UPDATE auth_item SET name = '/api/recurso-humano/listar-por-efector-acepta-turnos'     WHERE name = '/api/rrhh/listar-por-efector-acepta-turnos';
UPDATE auth_item SET name = '/api/recurso-humano/listar-servicios-en-efector'            WHERE name = '/api/rrhh/listar-servicios-en-efector';
UPDATE auth_item SET name = '/api/recurso-humano/listar-servicios-habilitados-efector' WHERE name = '/api/rrhh/listar-servicios-habilitados-efector';
UPDATE auth_item SET name = '/api/recurso-humano/condiciones-laborales-catalogo'        WHERE name = '/api/rrhh/condiciones-laborales-catalogo';
UPDATE auth_item SET name = '/api/recurso-humano/editar-condicion-laboral'             WHERE name = '/api/rrhh/editar-condicion-laboral';
UPDATE auth_item SET name = '/api/recurso-humano/crear-condicion-laboral'              WHERE name = '/api/rrhh/crear-condicion-laboral';
UPDATE auth_item SET name = '/api/recurso-humano/listar-por-efector-servicio-acepta-turnos' WHERE name = '/api/rrhh/listar-por-efector-servicio-acepta-turnos';
UPDATE auth_item SET name = '/api/recurso-humano/elegir'                               WHERE name = '/api/rrhh/elegir';

UPDATE auth_item SET name = '/api/recurso-humano/*'       WHERE name = '/api/rrhh/*';
UPDATE auth_item SET name = '/api/recurso-humano/options' WHERE name = '/api/rrhh/options';

COMMIT;

-- =============================================================================
-- Rutas sin barra inicial (si las registraron así en auth_item):
-- UPDATE auth_item SET name = CONCAT('/', TRIM(LEADING '/' FROM name))
--  WHERE name NOT LIKE '/%' AND (name LIKE 'api/agenda/%' OR name LIKE 'api/rrhh/%');
-- Luego repetir los UPDATE de arriba adaptando el WHERE (sin / inicial).
-- =============================================================================


-- #############################################################################
-- OPCIONAL: eliminar esquema RRHH/agenda “clásico” (sin retrocompatibilidad)
-- #############################################################################
--
-- Pensado para entornos donde **no** necesitás mantener el código Yii actual ni datos
-- enlazados: borra tablas núcleo `rrhh_*` / `agenda_rrhh` y la columna puente en PES.
--
-- Base de datos (hosting Bioenlace): `u257309594_bioenlace`
-- En local u otro entorno, reemplazá ese literal en las consultas 0–3 y en `USE` del DROP.
--
-- Antes de ejecutar: descubrir dependencias en MySQL.
--
-- 0) **Diagnóstico si todo lo demás da vacío**
--    - ¿Existen las tablas con esos nombres en esta base?
--
--   SELECT TABLE_SCHEMA, TABLE_NAME
--     FROM information_schema.TABLES
--    WHERE TABLE_SCHEMA = 'u257309594_bioenlace'
--      AND TABLE_NAME IN (
--          'agenda_rrhh', 'rrhh_servicio', 'rrhh_efector', 'rrhh_laboral'
--      )
--    ORDER BY TABLE_NAME;
--
--    Resultado esperado en esta base: **cuatro filas** (`agenda_rrhh`, `rrhh_efector`,
--    `rrhh_laboral`, `rrhh_servicio`). Confirma que el legado RRHH/agenda sigue en el servidor;
--    seguí con (1)–(3) por si hay FKs o columnas enlazadas, y recién después valorá el DROP.
--
--    (Para listarlas en **cualquier** schema del servidor, quitá `AND TABLE_SCHEMA = ...`.)
--
--    Si **0 filas**: o bien no están creadas en este entorno, o usan **otro nombre**
--    (prefijo, snake distinto). Buscá por patrón en la base que uses de verdad:
--
--   SELECT TABLE_NAME
--     FROM information_schema.TABLES
--    WHERE TABLE_SCHEMA = 'u257309594_bioenlace'
--      AND (
--            TABLE_NAME LIKE '%rrhh%' OR TABLE_NAME LIKE '%agenda%'
--          )
--    ORDER BY TABLE_NAME;
--
-- 1) FKs **entrantes** (quién referencia a estas tablas con CONSTRAINT InnoDB).
--    0 filas es habitual sin FOREIGN KEY en DDL; no prueba ausencia de uso lógico.
--
--   SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME
--     FROM information_schema.KEY_COLUMN_USAGE kcu
--    WHERE kcu.TABLE_SCHEMA = 'u257309594_bioenlace'
--      AND kcu.REFERENCED_TABLE_NAME IN (
--            'agenda_rrhh', 'rrhh_servicio', 'rrhh_efector', 'rrhh_laboral'
--      );
--
-- 2) FKs **salientes** (estas tablas referencian a otras):
--
--   SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME
--     FROM information_schema.KEY_COLUMN_USAGE kcu
--    WHERE kcu.TABLE_SCHEMA = 'u257309594_bioenlace'
--      AND kcu.TABLE_NAME IN (
--            'agenda_rrhh', 'rrhh_servicio', 'rrhh_efector', 'rrhh_laboral'
--      )
--      AND kcu.REFERENCED_TABLE_NAME IS NOT NULL;
--
--    Ejemplo en esta base: a veces solo `agenda_rrhh` tiene filas:
--      `id_efector` -> `efectores`, `id_tipo_dia` -> `tipo_dia`.
--    Son restricciones **salientes** (la agenda referencia catálogo/efector). Al ejecutar
--    `DROP TABLE agenda_rrhh` desaparecen esas FK junto con la tabla: **no** implica borrar
--    `efectores` ni `tipo_dia`. Las otras tres tablas (`rrhh_efector`, `rrhh_servicio`,
--    `rrhh_laboral`) a menudo **no** aparecen en (2) si nunca se declaró FK InnoDB en DDL.
--
-- 3) Columnas relacionadas (sin depender de FK). Incluye nombres típicos del repo
--    (`id_rr_hh`, `id_rrhh_servicio_asignado`, …) y **todas** las tablas del schema.
--    Nota: en MySQL, `_` dentro de `LIKE` es comodín; por eso usamos coincidencias
--    explícitas / `REGEXP` para `id_rr_hh`.
--
--   SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
--     FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = 'u257309594_bioenlace'
--      AND (
--            COLUMN_NAME IN (
--              'id_rr_hh', 'id_rrhh_servicio_asignado', 'id_agenda_rrhh'
--            )
--         OR COLUMN_NAME LIKE '%rrhh%'
--         OR COLUMN_NAME REGEXP 'rr[_]hh'
--         OR COLUMN_NAME LIKE '%agenda%'
--          )
--    ORDER BY TABLE_NAME, ORDINAL_POSITION;
--
-- Inventario real devuelto por (3) en `u257309594_bioenlace` (referencia; re-ejecutar tras cambios):
--   abreviaturas_rrhh.id_rr_hh
--   agenda_rrhh.id_agenda_rrhh, id_rr_hh, id_rrhh_servicio_asignado
--   atenciones_enfermeria.id_rr_hh, id_rrhh_servicio
--   consultas.id_rr_hh
--   consultas_derivaciones.id_rr_hh
--   consultas_suministro_medicamento.id_rrhh
--   dispensa_programa_diabetes.id_rrhh_efector
--   documentos_externos.id_rrhh_servicio
--   encuesta_parches_mamarios.id_rr_hh
--   guardia.id_rrhh_asignado, id_rr_hh
--   (si el cliente muestra `old.*`: tablas legacy en otro schema o backup; ver TABLE_SCHEMA)
--   persona_programa.id_rrhh_efector
--   persona_programa_diabetes.id_rrhh_efector
--   profesional_efector_servicio.legacy_rrhh_servicio_id
--   rrhh_efector.id_rr_hh, id_rr_hh_viejo
--   rrhh_laboral.id_rr_hh, id_rrhh_efector
--   rrhh_servicio.id_rr_hh
--   seg_nivel_internacion.id_rrhh
--   solicitud_rrhh.id_solicitante_rr_hh, id_destinatario_rr_hh
--   sumar_autofacturacion.id_rr_hh
--   totales_practicas.id_rr_hh
--   turnos.id_rr_hh, id_rrhh_servicio_asignado
--
-- Implicación: aunque (1) no liste FK InnoDB **hacia** `agenda_rrhh`/`rrhh_*`, muchas tablas
-- guardan IDs que **en la práctica** referencian RRHH, servicio-asignado o agenda. Un `DROP`
-- de las cuatro tablas núcleo deja esos valores huérfanos (o rompe la app) salvo que antes
-- migres a `profesional_efector_servicio` / nuevas FKs, o nullifiques columnas a propósito.
--
-- Si (1) lista tablas con FK real: o alterás antes, o FOREIGN_KEY_CHECKS=0 (abajo).
--
-- Orden DROP típico (hijos antes que padres lógicos):
--   agenda_rrhh -> rrhh_laboral -> rrhh_servicio -> rrhh_efector
--
-- Descomentá para aplicar:
--
-- USE u257309594_bioenlace;
-- SET FOREIGN_KEY_CHECKS = 0;
--
-- DROP TABLE IF EXISTS agenda_rrhh;
-- DROP TABLE IF EXISTS rrhh_laboral;
-- DROP TABLE IF EXISTS rrhh_servicio;
-- DROP TABLE IF EXISTS rrhh_efector;
--
-- ALTER TABLE profesional_efector_servicio
--   DROP INDEX ux_pes_legacy_rrhh_servicio_id;
-- ALTER TABLE profesional_efector_servicio
--   DROP COLUMN legacy_rrhh_servicio_id;
--
-- SET FOREIGN_KEY_CHECKS = 1;
--
-- Nota: el repo PHP actual **sí** referencia esas tablas y columnas; tras esto hay que
-- alinear modelos/servicios con solo `profesional_efector_servicio*` (o el esquema nuevo).
-- #############################################################################
