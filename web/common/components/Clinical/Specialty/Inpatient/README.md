# Internación (Inpatient)

Episodio FHIR: `EpisodeOfCare` + `CarePlan` category `inpatient` + `Encounter` class `IMP`.

## Servicios

| Clase | Rol |
|-------|-----|
| `InpatientClinicalContext` | Resuelve episode + care plan + encounter IMP abierto |
| `InpatientOrderService` | Medicación / prácticas / diagnósticos → `medication_request`, `service_request`, `clinical_condition` |
| `InpatientClinicalQuery` | Bundle de lectura (staff / API) |
| `InternacionClinicalBridge` (`Clinical/Legacy/`) | Puente Yii web desde formularios legacy |

## Ciclo de vida

- **Ingreso:** `CarePlanLifecycleService::onInternacionAdmission()` (desde `InternacionController` o `InpatientClinicalContext::ensure`).
- **Alta:** `CarePlanLifecycleService::completeOnDischarge()` (desde `SegNivelInternacionRepository::doExternacion`).

## API

- `GET /api/v1/clinical/episode-of-care/by-internacion/<internacionId>`
- `GET /api/v1/clinical/episode-of-care/<id>/clinical-bundle`

No escribir en `seg_nivel_internacion_medicamento` / `_practica` (tablas retiradas en migración FHIR).
