# Decisiones cerradas — dominio clínico FHIR

Fecha de registro: 2026-05-20.

## Producto y alcance

| Tema | Decisión |
|------|----------|
| Retrocompatibilidad | **No.** Sin dual-write ni alias HTTP legacy. |
| Interoperabilidad export | **Fuera** de este programa (bundles receta, IPS). |
| Canal principal | **API v1** + clientes; Yii web clínico retirado para captura clínica nueva. |
| Datos existentes | **Greenfield** en dev: migración crea esquema nuevo y elimina tablas clínicas legacy. ETL producción = sub-proyecto si aplica. |

## Modelo

| Tema | Decisión |
|------|----------|
| Patient | Tabla **`personas`**, clase `Person\Persona`. |
| Appointment | Tabla **`turnos`**, clase `Scheduling\Turno`; columnas FHIR Appointment opcionales. |
| Encounter | Tabla **`encounter`**, reemplaza **`consultas`**. |
| Condition | Tabla **`clinical_condition`**; recurso FHIR **Condition**. |
| CarePlan fin (internación) | **Alta** cierra plan `inpatient` salvo plan de continuidad explícito. |
| CarePlan crónico / programa | No termina al cerrar un Encounter; `completed` / `revoked` manual o por reglas de programa. |
| PK numéricas | `id` autoincrement en tablas nuevas. |

## API

| Tema | Decisión |
|------|----------|
| Prefijo rutas clínicas | **`/api/v1/clinical/...`** |
| Ejemplos | `clinical/encounter/guardar`, `clinical/care-plans/active` |

## Repositorio

| Tema | Decisión |
|------|----------|
| PRs | Cambios acotados por dominio; no mezclar BD + Flutter + Yii web en un solo PR. |

## Referencias

- [planes-de-tratamiento.md](../producto/planes-de-tratamiento.md)
- Código: `common/components/Clinical/`, `common/models/Clinical/`
