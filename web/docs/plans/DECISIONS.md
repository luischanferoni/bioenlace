# Decisiones cerradas — programa Clinical / FHIR

Fecha de registro: 2026-05-20 (Fase 0).

## Producto y alcance

| Tema | Decisión |
|------|----------|
| Retrocompatibilidad | **No.** Sin dual-write ni alias HTTP legacy. |
| Interoperabilidad export | **Fuera** de este programa (bundles receta, IPS). |
| Canal principal | **API v1** + clientes; Yii web clínico en fase 12 u obsoleto. |
| Datos existentes | **Greenfield** en dev: migración crea esquema nuevo y elimina tablas clínicas legacy. ETL producción = sub-proyecto si aplica. |

## Modelo

| Tema | Decisión |
|------|----------|
| Patient | Tabla **`personas`**, clase `Person\Persona`. |
| Appointment | Tabla **`turnos`**, clase `Scheduling\Turno`; columnas FHIR Appointment en fase 1 (opcional). |
| Encounter | Tabla **`encounter`**, reemplaza **`consultas`**. |
| Condition | Tabla **`clinical_condition`** (evita palabra reservada SQL); recurso FHIR **Condition**. |
| CarePlan fin (internación) | **Alta** cierra plan `inpatient` salvo que se cree explícitamente plan de continuidad. |
| CarePlan crónico / programa | No termina al cerrar un Encounter; `completed` / `revoked` manual o por reglas de programa. |
| PK numéricas | `id` autoincrement en tablas nuevas (como hoy). |

## API

| Tema | Decisión |
|------|----------|
| Prefijo rutas clínicas | **`/api/v1/clinical/...`** |
| Ejemplos | `clinical/encounter/guardar`, `clinical/care-plans/active` |

## Repositorio

| Tema | Decisión |
|------|----------|
| PRs | Una **fase** (o subfase) por PR; no mezclar BD + Flutter + Yii web. |
| Documentación viva | [MIGRATION_STATUS.md](./MIGRATION_STATUS.md) |

## Referencias

- [CARE_PLAN_CATEGORIES.md](./CARE_PLAN_CATEGORIES.md)
- [PROGRAM.md](./PROGRAM.md)
