# Fase 03e — Tablas hijas legacy → FHIR

## Prerrequisitos (cumplidos)

- Tabla `consultas` eliminada (`m260520_100002`).
- AR `Consulta` / `ConsultaBusqueda` eliminados.
- Captura nueva vía `EncounterDocumentationService` → `clinical_condition`, `medication_request`, `service_request`, etc.
- Vista `view_encounter_diagnostico` dual (legacy o `clinical_condition`).

## Migración final de fase

`m260526_150002_clinical_fhir_drop_legacy_child_tables` — **solo tras pasos 0–7**.

---

## Paso 0 — Nomenclador y reportes (FHIR)

- [x] `DiagnosticoConsultasBusqueda` → `clinical_condition`
- [x] `ConsultaMotivosBusqueda` → `encounter.reason_text` (estadísticas)
- [x] `AlergiasBusqueda` → `allergy_intolerance`
- [x] `Encounter`: relaciones legacy marcadas `@deprecated`; getters FHIR preferidos
- [x] `_reporteFarmacia.php`: diagnósticos/meds vía `Condition` / `MedicationRequest`
- [x] `EncounterReporteBusqueda` odontología vía `procedure` + ext (sin `consultas_odontologia_*`)

## Paso 1 — Derivaciones

Tabla `consultas_derivaciones` → `service_request` con `category=referral` + columnas workflow (`target_*`, `referral_status`, …).

- [x] Migración `m260526_160002_service_request_referral_workflow`
- [x] AR shim `ConsultaDerivaciones` extends `ServiceRequest`
- [x] `ReferralRequestService` + integración turnos / captura IA

**Callers:** `ReferenciasController`, `TurnosController`, API `TurnosController`, `TurnoPersistService`, `ConsultasConfiguracion` (parent derivación).

## Paso 2 — Diagnósticos

- `DiagnosticoConsultaRepository::saveDiagnosticosPrevios` / `getDiagnosticos` → `Condition`
- `DiagnosticoPrevio` ya usa `view_encounter_diagnostico`
- Retirar AR `DiagnosticoConsulta` tras drop

## Paso 3 — Alergias

- AR `Clinical\AllergyIntolerance`
- `PacientesController`, `NomencladorController`, `LegalRecordExportDataCollector`
- Retirar `Alergias` AR tras drop

## Paso 4 — Motivos codificados

- Decisión: mantener solo `encounter.reason_text` + chat (`interaccion_motivos_consulta`) **o** `Condition` con rol `reason`
- Retirar `ConsultaMotivos` tras drop

## Paso 5 — Odontología

- [x] `ConsultaOdontologiaEstados::getCPOHastaEncounter` desde `clinical_condition` (`odontology_state:`) si no hay tabla legacy
- [x] `OdontologyEncounterService::persistToothStates` + captura IA
- [x] `EncounterReporteBusqueda::searchReporteOdontologia` → `procedure` + `procedure_odontology_ext`
- [ ] `Encounter::getOdontologia*` → relaciones FHIR (opcional; callers deprecados)

## Paso 6 — Internación auxiliar

- [x] `InpatientEncounterAuxService`: balance → `Observation` (`fluid-balance`), régimen → `NutritionOrder`, suministro → `MedicationAdministration`
- [x] `SegNivelInternacionRepository::getBalancesHidricos` / `getRegimenes` con fallback FHIR si no hay tablas legacy
- [x] Captura IA: `ConsultaBalanceHidrico`, `ConsultaRegimen`, `ConsultaSuministroMedicamento` en `EncounterDocumentationService`
- [x] AR `Clinical\NutritionOrder`, `Clinical\MedicationAdministration`
- [x] `InpatientClinicalQuery` expone `fluidBalances` y `nutritionOrders` en bundle
- [x] Medicación/prácticas de internación: ya vía `InternacionClinicalBridge` → `InpatientOrderService` (sin `seg_nivel_internacion_*` hijas)

## Paso 7 — Limpieza

- Eliminar: `ConsultaEvolucion`, `ConsultaObstetricia`, `ConsultaSintomas` (sin callers activos)
- Eliminar: `form/ConsultaDiagnosticosForm`, `AMBDiagnosticoForm`, `IMPDiagnosticoForm`
- Podar `ConsultaProcesamientoService` métodos `guardar*` legacy (post-`guardar()`)
- Imports muertos en controllers

## Paso 8 — Drop + verificación

- [x] Migración `m260526_150002` lista; idempotente en greenfield
- [x] `Encounter`: relaciones legacy con guard `legacyTableExists`
- [x] Ejecutar en BD: `php yii migrate` (ver [MIGRATIONS.md](../MIGRATIONS.md))
