# Fase 04 — Turnos MVC residual, nomenclador, RBAC

**Inicio:** tras 03e-8 (drop tablas hijas + smoke).

## Objetivos

1. Auditar y retirar vistas/actions turnos no usadas (`index2`, `espera2`, `show-calendar`).
2. Confirmar nomenclador 100 % FHIR (sin AR `Consulta*` en búsquedas).
3. Limpiar RBAC web: rutas `guardia/*`, `internacion-diagnostico/*`, `internacion-medicamento/*`, etc.

## Checklist

| Ítem | Estado |
|------|--------|
| `TurnosController::actionIndex` vs `index2` — una sola entrada | [ ] |
| Rutas `urlManager` sin paths muertos a vistas eliminadas | [ ] |
| `auth_item` / permisos web alineados con controllers existentes | [ ] |
| `NomencladorController` sin dependencias legacy | [ ] |
| `ConsultasConfiguracion` alias documentado; callers usan `EncounterDefinition` donde aplique | [ ] |
| `FormController`, `MensajesController`, `EventsController` — auditar o diferir | [ ] |

## Verificación

- [ ] Staff: agenda turnos + derivaciones + referencias
- [ ] Sin 404 en menú por permisos huérfanos
