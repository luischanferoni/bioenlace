# Fase 03c — Paso 8: retiro shim `Consulta` (código)

## Objetivo

Reducir dependencias activas del AR `Consulta` y de joins a `consultas` antes de aplicar `m260520_100002_clinical_fhir_drop_legacy`.

## Cambios (hecho en código)

### Limpieza pipeline legacy

- `ConsultaProcesamientoService::guardar()`: eliminado bloque muerto (~280 líneas) que persistía en `consultas` tras el `return` temprano a `EncounterDocumentationService`.

### Constantes y permisos de configuración

- `ConsultasConfiguracion::validarPermisoAtencion()` y helpers: `Consulta::PARENT_*` / `ENCOUNTER_CLASS_*` → `Encounter::`.
- Corregido chequeo de encounter genérico: `PARENT_GENERICO_EMER` → `ENCOUNTER_CLASS_EMER`.

### Internación (repositorios)

- `SegNivelInternacionRepository::getBalancesHidricos()` / `getRegimenes()`: join `encounter` por `id_consulta` (columna legacy = encounter id) + filtro `parent_type = INTERNACION`.
- `DiagnosticoConsultaRepository`: internación IMP y contexto de consulta vía `Encounter::PARENT_CLASSES`; helper `resolveConsultaContext()` acepta `Encounter` o AR legacy.

### Turnos / pase previo

- `Encounter::findPasePrevioEncounter()` (nuevo).
- `Turno`: botón atender usa `Encounter::findPasePrevioEncounter()` (sin query a `consultas`).
- `Consulta::existeConsultaPasePrevio()` delega en Encounter (`@deprecated`).

### Deprecaciones

- AR `Consulta`: `@deprecated` a nivel clase.
- `ConsultaBusqueda`: `@deprecated` (sin callers en controllers).
- Imports huérfanos retirados: `main.php`, `InternacionController`.
- `DeferredSnomedProcessor`: `Encounter::findOne` en lugar de `Consulta::findOne`.

### Encounter

- `WORKFLOW_STEP_FINALIZED = 999` (alias legacy `Consulta::PASO_FINALIZADA`).

## Pendiente (Paso 8+ / drop BD)

| Bloqueador | Notas |
|------------|-------|
| AR `Consulta` + modelos hijos (`ConsultaMedicamentos`, …) | FK `exist` a `Consulta`; retirar tras drop o renombrar FK a `encounter` |
| `view_consulta_diagnostico` | Vista SQL; migrar a `condition` + `encounter` |
| `SegNivelInternacion::getConsultas()` | relación `hasMany(Consulta)` |
| Internación MVC (`Internacion*Controller`, `_view_*.php`) | Migración por pestaña a API/FHIR | **03d:** 410 clínico; captura → timeline IMP |
| `ConsultasConfiguracion` | Alias de `EncounterDefinition`; retirar cuando no queden imports |
| Aplicar migraciones staging | `m260520_100001`, `m260526_100002`, `m260520_100002` |

## Verificación sugerida

- [ ] Internación: balance hídrico y régimen listan filas de la internación activa.
- [ ] Turno con pase previo: botón «Atender» / ocultar según encounter existente.
- [ ] `php -l` en archivos tocados.
- [ ] Smoke captura clínica: `POST .../clinical/encounter/guardar` (sin writes a `consultas`).
