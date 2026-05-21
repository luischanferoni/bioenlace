# Fase 8 — Internación (EpisodeOfCare)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 5](./05-care-plan-lifecycle.md), [Fase 6](./06-orders-medication-practice.md)  
**Estado:** hecho (Yii web + API staff; vista `InternacionController::actionView` sigue mezclando atenciones legacy)

## Objetivo

Unificar internación bajo **EpisodeOfCare + Encounter IMP + CarePlan inpatient**; eliminar isla `seg_nivel_internacion_*` para órdenes clínicas.

## Mapeo

| Hoy | Objetivo |
|-----|----------|
| `seg_nivel_internacion` | `episode_of_care` (+ FK a cama/efector según modelo actual) |
| `seg_nivel_internacion_medicamento` | `medication_request` (category inpatient) |
| `seg_nivel_internacion_practica` | `service_request` |
| `seg_nivel_internacion_diagnostico` | `clinical_condition` |
| `consultas_regimen` en internación | `nutrition_order` |
| Múltiples Encounter durante estancia | `episode_of_care` agrupa encounters |

## Integración alta

- [x] Ingreso: `CarePlanLifecycleService::onInternacionAdmission()` + `ensureInpatientEncounter()` (IMP en curso).
- [x] Alta: `CarePlanLifecycleService::completeOnDischarge()` desde `SegNivelInternacionRepository::doExternacion`.
- [ ] Epicrisis / resumen: `ClinicalImpression` o `DocumentReference` (fase posterior si hace falta).

## Yii web (escritura)

- [x] `InternacionClinicalBridge` + `InpatientOrderService` desde `InternacionMedicamento|Practica|DiagnosticoController::actionCreate`.
- [x] Sin `save()` a `seg_nivel_internacion_medicamento` / `_practica` (tablas retiradas en migración).

## API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/clinical/episode-of-care/by-internacion/<internacionId>` | Resumen del episodio |
| GET | `/api/v1/clinical/episode-of-care/<id>/clinical-bundle` | Planes, órdenes y condiciones del episodio |

RBAC: `m260521_100006_api_clinical_episode_of_care_rbac`.

## Código

- `Clinical/Specialty/Inpatient/` — contexto, órdenes, query ([README](../../../common/components/Clinical/Specialty/Inpatient/README.md))
- `Clinical/Legacy/InternacionClinicalBridge.php`

## Fuera de alcance

- Facturación consumos (`seg_nivel_internacion_consumo`) — otro dominio.
- Lectura Yii de medicación/prácticas en `actionView` desde FHIR (pendiente).

## Definition of Done

- [x] Admisión crea episodio + care plan inpatient + encounter IMP.
- [x] Alta cierra episodio, planes inpatient y encounter IMP.
- [x] Sin escrituras a tablas hijas `seg_nivel_internacion_medicamento` / `_practica`.
- [x] API staff para bundle clínico por episodio.

## Siguiente fase

[Fase 9 — Asistente](./09-assistant.md)
