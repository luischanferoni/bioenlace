# Programa clínico FHIR — Diseño

## Por qué está estructurado así

### Greenfield en desarrollo (sin dual-write)

Las migraciones crean esquema nuevo y eliminan tablas clínicas legacy en entornos de desarrollo. No se mantiene sincronización bidireccional con `consultas_*`.

**Alternativa descartada:** convivencia indefinida MVC + API duplicando escritura.

**Alternativa descartada:** ETL masivo en la misma entrega que el esquema; quedó como sub-proyecto si producción lo requiere.

### Prefijo `/api/v1/clinical/...`

Recursos clínicos (encounter, care-plan, medication-request, …) viven bajo módulo clinical para permisos y plantillas JSON separadas de scheduling.

**Alternativa descartada:** mezclar todo bajo `/turnos` o controllers Yii por especialidad sin API.

### `personas` como Patient

No hay tabla `patient` paralela: `Person\Persona` es el sujeto.

### CarePlan con categorías cerradas

Las categorías (`chronic`, `inpatient`, `program`, …) gobiernan ciclo de vida al cerrar encounters. Documento de negocio: [CARE_PLAN_CATEGORIES.md](./CARE_PLAN_CATEGORIES.md).

## Decisiones cerradas (resumen)

La tabla completa está en [DECISIONS.md](./DECISIONS.md). Entradas relevantes:

| Tema | Decisión |
|------|----------|
| Retrocompatibilidad HTTP clínica | No |
| Canal principal | API v1 + clientes |
| Encounter | Tabla `encounter`, no `consultas` |
| Condition SQL | Tabla `clinical_condition` |

## Fases y PRs

Una **fase** (o subfase) por PR; no mezclar BD + Flutter + Yii web en el mismo cambio. Detalle de gobernanza: [phases/00-governance.md](./phases/00-governance.md).

## Relacionado

- [README.md](./README.md) — índice de fases
- [MIGRATION_STATUS.md](./MIGRATION_STATUS.md) — estado por recurso
