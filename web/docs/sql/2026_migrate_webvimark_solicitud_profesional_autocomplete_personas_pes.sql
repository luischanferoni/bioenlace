-- =============================================================================
-- Webvimark RBAC — rutas renombradas (PES / sin RRHH en paths de permiso)
--
-- Alineado con código:
--   - API: SolicitudProfesionalController (`/api/solicitud-profesional/*`)
--   - Web: actionIndexPersonasPes (`index-personas-pes`)
--   - Web: actionProfesionalesAutocomplete (`rrhh/profesionales-autocomplete`)
--
-- Tablas: auth_item, auth_item_child, auth_assignment (item_name)
--
-- ANTES: backup. DESPUÉS: vaciar caché app; usuarios pueden necesitar re-login.
--
-- Preferible ejecutar también la migración Yii:
--   php yii migrate --migrationPath=@common/migrations
--   m260513_000004_webvimark_routes_solicitud_profesional_y_autocomplete
-- =============================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- API solicitud entre profesionales
UPDATE auth_item SET name = REPLACE(name, '/api/solicitud-rrhh', '/api/solicitud-profesional')
 WHERE name LIKE '/api/solicitud-rrhh%';
UPDATE auth_item SET name = REPLACE(name, '/api/v1/solicitud-rrhh', '/api/v1/solicitud-profesional')
 WHERE name LIKE '/api/v1/solicitud-rrhh%';

UPDATE auth_item_child SET parent = REPLACE(parent, '/api/solicitud-rrhh', '/api/solicitud-profesional')
 WHERE parent LIKE '/api/solicitud-rrhh%';
UPDATE auth_item_child SET child = REPLACE(child, '/api/solicitud-rrhh', '/api/solicitud-profesional')
 WHERE child LIKE '/api/solicitud-rrhh%';
UPDATE auth_item_child SET parent = REPLACE(parent, '/api/v1/solicitud-rrhh', '/api/v1/solicitud-profesional')
 WHERE parent LIKE '/api/v1/solicitud-rrhh%';
UPDATE auth_item_child SET child = REPLACE(child, '/api/v1/solicitud-rrhh', '/api/v1/solicitud-profesional')
 WHERE child LIKE '/api/v1/solicitud-rrhh%';

UPDATE auth_assignment SET item_name = REPLACE(item_name, '/api/solicitud-rrhh', '/api/solicitud-profesional')
 WHERE item_name LIKE '/api/solicitud-rrhh%';
UPDATE auth_assignment SET item_name = REPLACE(item_name, '/api/v1/solicitud-rrhh', '/api/v1/solicitud-profesional')
 WHERE item_name LIKE '/api/v1/solicitud-rrhh%';

-- Personas: listado PES (acción antigua indexpersonarrhh eliminada del código)
UPDATE auth_item SET name = REPLACE(name, '/frontend/personas/indexpersonarrhh', '/frontend/personas/index-personas-pes')
 WHERE name LIKE '/frontend/personas/indexpersonarrhh%';
UPDATE auth_item SET name = REPLACE(name, '/personas/indexpersonarrhh', '/personas/index-personas-pes')
 WHERE name LIKE '/personas/indexpersonarrhh%';

UPDATE auth_item_child SET parent = REPLACE(parent, '/frontend/personas/indexpersonarrhh', '/frontend/personas/index-personas-pes')
 WHERE parent LIKE '/frontend/personas/indexpersonarrhh%';
UPDATE auth_item_child SET child = REPLACE(child, '/frontend/personas/indexpersonarrhh', '/frontend/personas/index-personas-pes')
 WHERE child LIKE '/frontend/personas/indexpersonarrhh%';
UPDATE auth_item_child SET parent = REPLACE(parent, '/personas/indexpersonarrhh', '/personas/index-personas-pes')
 WHERE parent LIKE '/personas/indexpersonarrhh%';
UPDATE auth_item_child SET child = REPLACE(child, '/personas/indexpersonarrhh', '/personas/index-personas-pes')
 WHERE child LIKE '/personas/indexpersonarrhh%';

UPDATE auth_assignment SET item_name = REPLACE(item_name, '/frontend/personas/indexpersonarrhh', '/frontend/personas/index-personas-pes')
 WHERE item_name LIKE '/frontend/personas/indexpersonarrhh%';
UPDATE auth_assignment SET item_name = REPLACE(item_name, '/personas/indexpersonarrhh', '/personas/index-personas-pes')
 WHERE item_name LIKE '/personas/indexpersonarrhh%';

-- Autocomplete PES (controller RrhhController)
UPDATE auth_item SET name = REPLACE(name, '/frontend/rrhh/rrhh-autocomplete', '/frontend/rrhh/profesionales-autocomplete')
 WHERE name LIKE '%/frontend/rrhh/rrhh-autocomplete%';
UPDATE auth_item SET name = REPLACE(name, '/rrhh/rrhh-autocomplete', '/rrhh/profesionales-autocomplete')
 WHERE name LIKE '%/rrhh/rrhh-autocomplete%' AND name NOT LIKE '%profesionales-autocomplete%';

UPDATE auth_item_child SET parent = REPLACE(parent, '/frontend/rrhh/rrhh-autocomplete', '/frontend/rrhh/profesionales-autocomplete')
 WHERE parent LIKE '%/frontend/rrhh/rrhh-autocomplete%';
UPDATE auth_item_child SET child = REPLACE(child, '/frontend/rrhh/rrhh-autocomplete', '/frontend/rrhh/profesionales-autocomplete')
 WHERE child LIKE '%/frontend/rrhh/rrhh-autocomplete%';
UPDATE auth_item_child SET parent = REPLACE(parent, '/rrhh/rrhh-autocomplete', '/rrhh/profesionales-autocomplete')
 WHERE parent LIKE '%/rrhh/rrhh-autocomplete%' AND parent NOT LIKE '%profesionales-autocomplete%';
UPDATE auth_item_child SET child = REPLACE(child, '/rrhh/rrhh-autocomplete', '/rrhh/profesionales-autocomplete')
 WHERE child LIKE '%/rrhh/rrhh-autocomplete%' AND child NOT LIKE '%profesionales-autocomplete%';

UPDATE auth_assignment SET item_name = REPLACE(item_name, '/frontend/rrhh/rrhh-autocomplete', '/frontend/rrhh/profesionales-autocomplete')
 WHERE item_name LIKE '%/frontend/rrhh/rrhh-autocomplete%';
UPDATE auth_assignment SET item_name = REPLACE(item_name, '/rrhh/rrhh-autocomplete', '/rrhh/profesionales-autocomplete')
 WHERE item_name LIKE '%/rrhh/rrhh-autocomplete%' AND item_name NOT LIKE '%profesionales-autocomplete%';

COMMIT;
