# Estado de migración FHIR / Clinical

Tablero vivo. Actualizar al cerrar tareas de cada fase.

**Leyenda:** `pendiente` | `en_curso` | `hecho` | `n/a`

## Recursos núcleo

| Recurso FHIR | Tabla objetivo | Model AR | Service | API | Estado |
|--------------|----------------|----------|---------|-----|--------|
| Patient | `personas` | `Person\Persona` | — | existente | pendiente (solo docblock) |
| Appointment | `turnos` | `Scheduling\Turno` | `Scheduling\*` | `TurnosController` | pendiente |
| Encounter | `encounter` | `Clinical\Encounter` | `EncounterLifecycleService` | `clinical/EncounterController` | hecho (AR+svc) |
| EpisodeOfCare | `episode_of_care` | `Clinical\EpisodeOfCare` | — | `clinical/EpisodeOfCareController` | hecho (AR) |
| CarePlan | `care_plan` | `Clinical\CarePlan` | `CarePlanService`, `CarePlanLifecycleService` | `clinical/CarePlanController` | hecho (Fase 5 lifecycle) |
| CarePlanActivity | `care_plan_activity` | `Clinical\CarePlanActivity` | (CarePlanService) | — | hecho (AR+svc) |
| Goal | `goal` | — | — | — | hecho (BD) |
| Condition | `clinical_condition` | `Clinical\Condition` | (EncounterDocumentationService) | `clinical/ConditionController` | hecho (AR+svc) |

## Órdenes y ejecución

| Recurso FHIR | Tabla objetivo | Reemplaza | Estado |
|--------------|----------------|-----------|--------|
| MedicationRequest | `medication_request` | `consultas_medicamentos` | hecho (AR+svc) |
| MedicationAdministration | `medication_administration` | `consultas_suministro_medicamento` | hecho (BD) |
| ServiceRequest | `service_request` | `consultas_practicas`, `consultas_derivaciones` | hecho (AR+svc) |
| Procedure | `procedure` | ejecución de prácticas / odonto | hecho (BD) |
| DeviceRequest | `device_request` | prótesis (odonto, ortopedia) | hecho (BD) |
| NutritionOrder | `nutrition_order` | `consultas_regimen` | hecho (BD) |
| VisionPrescription | `vision_prescription` | `consultas_receta_lentes` | hecho (BD) |
| Observation | `observation` | signos, balance, obstetricia parcial | hecho (BD) |
| ClinicalImpression | `clinical_impression` | `consultas_evolucion` | hecho (BD) |
| AllergyIntolerance | `allergy_intolerance` | `alergias` | hecho (BD; tabla `alergias` legacy sigue) |

## Configuración y UI

| Concepto | Hoy | Objetivo | Estado |
|----------|-----|----------|--------|
| Wizard por servicio | `consultas_configuracion` | `encounter_definition` | hecho (AR alias) |
| UI JSON templates | `views/json/turnos/...` | `views/json/scheduling/...` | pendiente |
| UI JSON clínica | — | `views/json/clinical/...` | pendiente |
| UiScreenService | `components/UiScreenService.php` | `components/Ui/UiScreenService.php` | pendiente |

## Legacy a eliminar

| Tabla / clase | Sustituto | Estado eliminación |
|---------------|-----------|-------------------|
| `consultas` | `encounter` | hecho (drop) |
| `diagnostico_consultas` | `clinical_condition` | hecho (drop) |
| `consultas_medicamentos` | `medication_request` | hecho (drop) |
| `consultas_practicas` | `service_request` | hecho (drop) |
| `consultas_derivaciones` | `service_request` | hecho (drop) |
| `consultas_regimen` | `nutrition_order` | hecho (drop) |
| `seg_nivel_internacion_medicamento/practica` | `episode_of_care` + órdenes | hecho (drop tablas hijas; madre `seg_nivel_internacion` se mantiene) |
| `common/components/Services/Consulta/` | `components/Clinical/` | hecho (Fase 3) |
| `ConsultaController` (API) | `clinical/EncounterController` | hecho (410 Gone en legacy) |

## Clientes

| Cliente | Feature | Estado |
|---------|---------|--------|
| Flutter paciente | Care plans activos en inicio | pendiente |
| Flutter paciente / médico | `id_consulta` → `encounter_id` en flujos | pendiente |
| Asistente | ClinicalEncounter → EncounterDocumentationService | hecho |
