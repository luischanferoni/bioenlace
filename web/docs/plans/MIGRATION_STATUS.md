# Estado de migración FHIR / Clinical

Tablero vivo. Actualizar al cerrar tareas de cada fase.

**Leyenda:** `pendiente` | `en_curso` | `hecho` | `n/a`

**Última revisión:** 2026-05-20 — fase 8 internación (EpisodeOfCare + bridge Yii + API).

## Recursos núcleo

| Recurso FHIR | Tabla objetivo | Model AR | Service | API | Estado |
|--------------|----------------|----------|---------|-----|--------|
| Patient | `personas` | `Person\Persona` (+ alias) | `Person/Service/*` | existente | hecho |
| Appointment | `turnos` | `Scheduling\Turno` (+ alias) | `Scheduling/Service/*` | `TurnosController` | hecho |
| Encounter | `encounter` | `Clinical\Encounter` | `EncounterLifecycleService` | `clinical/EncounterController` | hecho |
| EpisodeOfCare | `episode_of_care` | `Clinical\EpisodeOfCare` | `EpisodeOfCareService`, `InpatientClinicalQuery` | `clinical/episode-of-care/*` | hecho |
| CarePlan | `care_plan` | `Clinical\CarePlan` | `CarePlanService`, `CarePlanLifecycleService`, `CarePlanPresentationService` | `clinical/CarePlanController` | hecho |
| CarePlanActivity | `care_plan_activity` | `Clinical\CarePlanActivity` | (CarePlanService) | — | hecho |
| Goal | `goal` | — | — | — | hecho (BD) |
| Condition | `clinical_condition` | `Clinical\Condition` | (EncounterDocumentationService) | `clinical/ConditionController` | hecho |

## Órdenes y ejecución

| Recurso FHIR | Tabla objetivo | Service | API | Estado |
|--------------|----------------|---------|-----|--------|
| MedicationRequest | `medication_request` | `MedicationRequestService` | `GET/POST …/encounter/<id>/medication-requests` | hecho |
| ServiceRequest | `service_request` | `ServiceRequestService` | `GET/POST …/encounter/<id>/service-requests` | hecho |
| MedicationAdministration | `medication_administration` | — | — | pendiente (BD) |
| Procedure | `procedure` | `Clinical\Procedure` | `OdontologyEncounterService` | `GET …/odontology` | hecho (piloto odonto) |
| Procedure ext odonto | `procedure_odontology_ext` | `ProcedureOdontologyExt` | (odonto svc) | — | hecho |
| DeviceRequest | `device_request` | — | — | pendiente (BD; prótesis odonto) |
| NutritionOrder | `nutrition_order` | — | — | pendiente (BD) |
| VisionPrescription | `vision_prescription` | `Clinical\VisionPrescription` | `OphthalmologyEncounterService` | `GET …/ophthalmology` | hecho (piloto) |
| Observation | `observation` | `Clinical\Observation` | `OphthalmologyEncounterService` | `GET …/ophthalmology` | hecho (piloto oftalmo) |
| ClinicalImpression | `clinical_impression` | — | — | pendiente (BD) |
| AllergyIntolerance | `allergy_intolerance` | — | — | pendiente (BD; tabla `alergias` legacy) |

Persistencia vía `EncounterDocumentationService::guardar` (IA) delega en `MedicationRequestService` / `ServiceRequestService`. Escritores a tablas `consultas_*` retirados en migración (drop).

## Configuración y UI

| Concepto | Hoy | Objetivo | Estado |
|----------|-----|----------|--------|
| Wizard por servicio | `encounter_definition` (+ alias `ConsultasConfiguracion`) | `encounter_definition` | hecho |
| UI JSON turnos | `views/json/turnos/…` | `views/json/scheduling/…` | pendiente (fase 11) |
| UI JSON clínica | `views/json/clinical/…` | `UiScreenService` + dominios `scheduling/`/`clinical/` | hecho (fase 11) |
| UiScreenService | `components/Ui/UiScreenService.php` | `components/Ui/` | hecho |

## Legacy a eliminar

| Tabla / clase | Sustituto | Estado eliminación |
|---------------|-----------|-------------------|
| `consultas` (tabla) | `encounter` | hecho (drop) |
| `consultas_medicamentos` (tabla) | `medication_request` | hecho (drop) |
| `seg_nivel_internacion_medicamento` / `_practica` | `medication_request` / `service_request` | hecho (drop; escritura vía `InternacionClinicalBridge`) |
| `seg_nivel_internacion_diagnostico` | `clinical_condition` | hecho escritura fase 8; drop tabla pendiente fase 1 (omisión migración) |
| `Consulta.php` (AR) | `Clinical\Encounter` | pendiente (Yii web + referencias) |
| `common/components/Services/` | dominios `Scheduling/`, `Clinical/`, … | hecho (eliminada) |
| `ConsultaProcesamientoService` (AR legacy) | `Clinical/Legacy/` + `EncounterDocumentationService` | en_curso (solo `analizar()` delega legacy) |
| `ConsultaController` (API) | `clinical/EncounterController` | hecho (410 Gone) |

## Clientes

| Cliente | Feature | Estado |
|---------|---------|--------|
| Flutter paciente | Care plans activos en inicio (`CarePlanService` + card) | hecho |
| Flutter paciente | `encounter_id` en turnos y chat (alias `id_consulta`) | hecho |
| Asistente | `ClinicalEncounter` → `EncounterDocumentationService` | hecho |
| Asistente | Draft `encounter_id` / `care_plan_id`, `ClinicalUiActionCatalog`, canales síntomas | hecho (fase 9) |
| Asistente | Intent «ver mi tratamiento» + UI JSON | pendiente (fase 11) |

## Fases del programa (resumen)

| Fase | Estado doc | Notas |
|------|------------|-------|
| 0–2, 4–5 | hecho | Núcleo BD + API + lifecycle |
| 3 | **hecho** | Dominios + modelos `Person/`, `Scheduling/`, `Terminology/Snomed/` |
| 6 | **hecho** | Medication/Service request services + API; E2E vía `encounter/guardar` |
| 7 | **hecho (pilotos)** | Odonto + oftalmo; psico/obstetricia/enfermería pendientes |
| 8 | **hecho** | EpisodeOfCare + IMP + bridge medicación/práctica/diagnóstico; API bundle |
| 9 | **hecho** | Draft clínico, catálogo `/api/clinical/*`, AppointmentReason por encounter_id |
| 10 | **hecho** | Home paciente + API active con resumen |
| 11 | **hecho** | Dominios views/json + pilotos care-plan / encounter UI |
| 12 | pendiente | Yii web clínico |
