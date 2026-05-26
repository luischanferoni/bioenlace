# Fase 04 — Turnos MVC residual, nomenclador, RBAC

**Estado:** cerrada (código + migración RBAC).

## Objetivos

1. Auditar y retirar vistas/actions turnos no usadas (`index2`, `espera2`, `show-calendar`).
2. Confirmar nomenclador 100 % FHIR (sin AR `Consulta*` en búsquedas).
3. Limpiar RBAC web: rutas `guardia/*`, `internacion-diagnostico/*`, `internacion-medicamento/*`, etc.

## Checklist

| Ítem | Estado |
|------|--------|
| `TurnosController::actionIndex` renderiza `index` (ex `index2`) | [x] |
| `TurnosController::actionEspera` restaurado (lista de espera) | [x] |
| Vistas `espera2`, `show-calendar` eliminadas | [x] |
| Migración `m260526_170001_web_retired_mvc_rbac` | [x] |
| `NomencladorController` sin imports legacy muertos | [x] |
| `ENCOUNTER_CLASS` en `EncounterDefinition`; alias en `ConsultasConfiguracion` | [x] |
| `Encounter`: relaciones `@deprecated` con guard si tabla legacy ausente | [x] |
| `FormController`, `MensajesController`, `EventsController` — auditar o diferir | [-] diferido |

## Migración RBAC

`m260526_170001` elimina rutas web:

- `guardia/*` (MVC retirado; tablero = `site/pacientes` + API EMER)
- `internacion-{diagnostico,medicamento,practica,atenciones-enfermeria}/*`
- `turnos/create`, `turnos/delete`, `turnos/no-se-presento` (API v1)
- `internacion-hcama/create|update` (410; flujo API cambio de cama)

Permisos huérfanos retirados: `front_crear_episodio_guardia`, `front_pacientes_guardia`, `front_libro_guardia`.

**Se mantiene:** `/frontend/turnos/espera`, `/frontend/turnos/index`, permiso `verListaEspera`.
