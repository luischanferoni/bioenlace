# Fase 0 — Gobernanza y convenciones

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Estado:** hecho (2026-05-20)

## Objetivo

Fijar reglas del programa para que `common/`, API y BD no vuelvan a fragmentarse. Sin código de dominio clínico aún (salvo READMEs y este tablero).

## Entregables

- [x] Convención de carpetas acordada y reflejada en `common/README.md` (nuevo o actualizado).
- [x] `docs/plans/MIGRATION_STATUS.md` con equipo asignado a mantenerlo.
- [x] Lista cerrada inicial de `CarePlan.category` — [CARE_PLAN_CATEGORIES.md](../CARE_PLAN_CATEGORIES.md).
- [x] Política de PR: **una fase o subfase por PR**; no mezclar BD + Yii web + Flutter en el mismo merge.
- [x] Decisión URL API: prefijo `/api/v1/clinical/...` (sin alias `/consulta/`) — [DECISIONS.md](../DECISIONS.md).

## Convenciones `common/`

| Tipo | Ubicación | Prohibido |
|------|-----------|-----------|
| ActiveRecord | `common/models/{Dominio}/` | AR sueltos en raíz `models/` para código nuevo |
| DTO / Enum | `common/components/{Dominio}/Dto`, `Enum` | DTOs en `models/` |
| Negocio | `common/components/{Dominio}/Service/` | Lógica en controllers API |
| Queries pesadas | `Repository/` o `Search/` en el dominio | Nuevos `busquedas/*` salvo migración temporal |
| UI JSON helpers | `common/components/Ui/` | `UiScreenService` suelto en raíz `components/` |

## Nomenclatura FHIR en código PHP

- Clases: `Encounter`, `CarePlan`, `MedicationRequest` (PascalCase igual que recurso).
- Tablas: `snake_case` plural (`encounter`, `care_plan`, `medication_request`).
- PK: `id` entero o UUID (decidir en fase 1; recomendado: **entero autoincrement** como hoy salvo integración externa).
- FK persona: `subject_persona_id` → `personas.id_persona`.
- FK turno: `appointment_id` → `turnos.id`.

## Fuera de alcance (recordatorio)

- Bundles / receta digital export.
- Migración de datos históricos producción (definir en fase 1 si hay script ETL o solo greenfield).

## Definition of Done

- Documentos en `docs/plans/` revisados por al menos un dev backend y un representante clínico/producto.
- `MIGRATION_STATUS.md` creado (hecho con el programa).
- Sin objeciones abiertas sobre fin de CarePlan en internación (alta = cierra plan salvo excepción documentada).

## Siguiente fase

[Fase 1 — Fundación BD](./01-foundation-db.md)
