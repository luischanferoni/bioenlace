# `care_plan.category` — lista cerrada v1

## Objetivo

Definir códigos permitidos en `care_plan.category` y valores FHIR de `intent` / `status`.

## Actores

- Backend (`CarePlanService`, enums).
- Producto clínico al definir nuevas categorías.

## Anclas

| Área | `common/components/Clinical/Enum/CarePlanCategory.php` |

---

Códigos almacenados en `care_plan.category` (string, indexado). Ampliar solo con acuerdo clínico + actualización de este archivo.

| Código | Descripción |
|--------|-------------|
| `acute-ambulatory` | Indicaciones de consulta ambulatoria / episodio agudo |
| `chronic` | Tratamiento o medicación continua |
| `program` | Programa con finitud (kinesiología N sesiones, ortodoncia, psicología) |
| `inpatient` | Plan durante internación |
| `postoperative` | Postoperatorio |
| `preventive` | Prevención, controles |
| `palliative` | Cuidados paliativos |
| `odontology` | Plan odontológico multi-visita |
| `ophthalmology` | Seguimiento oftalmológico |
| `mental-health` | Salud mental / psicología |
| `rehabilitation` | Rehabilitación / kinesiología |
| `nutrition` | Plan nutricional |
| `other` | Residual (documentar en notas del plan) |

## `care_plan.intent` (FHIR)

| Valor | Uso |
|-------|-----|
| `proposal` | Borrador |
| `plan` | Plan establecido |
| `order` | Orden directa |

## `care_plan.status` (FHIR CarePlanStatus)

`draft`, `active`, `on-hold`, `revoked`, `completed`, `entered-in-error`, `unknown`
