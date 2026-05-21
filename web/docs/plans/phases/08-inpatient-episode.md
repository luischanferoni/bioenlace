# Fase 8 — Internación (EpisodeOfCare)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 5](./05-care-plan-lifecycle.md), [Fase 6](./06-orders-medication-practice.md)  
**Estado:** hecho

## Objetivo

Unificar internación bajo **EpisodeOfCare + Encounter IMP + CarePlan inpatient**; eliminar isla `seg_nivel_internacion_*` para órdenes clínicas (escritura + ciclo de vida + API staff).

## Mapeo (alcance de esta fase)

| Legacy | Objetivo FHIR | Estado fase 8 |
|--------|---------------|---------------|
| `seg_nivel_internacion` (admisión/alta) | `episode_of_care` + lifecycle | hecho |
| `seg_nivel_internacion_medicamento` | `medication_request` | hecho (bridge; tabla drop fase 1) |
| `seg_nivel_internacion_practica` | `service_request` | hecho (bridge; tabla drop fase 1) |
| `seg_nivel_internacion_diagnostico` | `clinical_condition` | hecho (bridge; **drop tabla** → ver nota fase 1) |
| `consultas_regimen` en internación | `nutrition_order` | **no incluido** → fase 6 cuando exista `NutritionOrder` |
| Epicrisis / resumen de alta | `ClinicalImpression` / `DocumentReference` | **no incluido** → sin fase numerada en PROGRAM (producto) |
| Vista Yii `InternacionController::actionView` (lectura) | bundle FHIR / API | **no incluido** → [fase 12](./12-yii-web.md) |
| `seg_nivel_internacion_consumo` | — | otro dominio (facturación) |

## Integración alta

- [x] Ingreso: `CarePlanLifecycleService::onInternacionAdmission()` + `ensureInpatientEncounter()` (IMP en curso).
- [x] Alta: `CarePlanLifecycleService::completeOnDischarge()` desde `SegNivelInternacionRepository::doExternacion`.

## Yii web (escritura en esta fase)

- [x] `InternacionClinicalBridge` + `InpatientOrderService` desde `InternacionMedicamento|Practica|DiagnosticoController::actionCreate`.
- [x] Sin `save()` a `seg_nivel_internacion_medicamento` / `_practica` (tablas retiradas en migración fase 1).

## API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/clinical/episode-of-care/by-internacion/<internacionId>` | Resumen del episodio |
| GET | `/api/v1/clinical/episode-of-care/<id>/clinical-bundle` | Planes, órdenes y condiciones del episodio |

RBAC: `m260521_100006_api_clinical_episode_of_care_rbac`.

## Código

- `Clinical/Specialty/Inpatient/` — contexto, órdenes, query ([README](../../../common/components/Clinical/Specialty/Inpatient/README.md))
- `Clinical/Legacy/InternacionClinicalBridge.php`
- `frontend/modules/api/v1/controllers/clinical/EpisodeOfCareController.php`

## Definition of Done (cierre fase 8)

- [x] Admisión crea episodio + care plan inpatient + encounter IMP.
- [x] Alta cierra episodio, planes inpatient y encounter IMP.
- [x] Sin escrituras a tablas hijas `seg_nivel_internacion_medicamento` / `_practica`.
- [x] Diagnósticos de internación persisten en `clinical_condition` vía bridge (sin depender de tabla hijo para escritura nueva).
- [x] API staff para bundle clínico por episodio.

## Siguiente fase del programa

[Fase 9 — Asistente](./09-assistant.md)
