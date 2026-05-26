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
- [ ] `EncounterReporteBusqueda` odontología sin `consultas_odontologia_*`

## Paso 1 — Derivaciones

Tabla `consultas_derivaciones` → `service_request` con `category=referral` + metadatos de workflow (`note` JSON o columnas dedicadas).

**Callers:** `ReferenciasController`, `TurnosController`, API `TurnosController`, `TurnoPersistService`, `ConsultasConfiguracion` (parent derivación).

**Interim (solo si 150002 ya corrió sin paso 1):** `m260526_160001_recreate_consultas_derivaciones` — recrea tabla sin FK a `consultas`; retirar al cerrar paso 1.

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

- `ConsultaOdontologiaEstados::getCPOHastaEncounter` desde `procedure` / `procedure_odontology_ext`
- `Encounter::getOdontologia*` → FHIR

## Paso 6 — Internación auxiliar

- `SegNivelInternacionRepository` balance/régimen → `Observation` / `NutritionOrder` / `DeviceRequest` (definir por producto)
- `ConsultaSuministroMedicamento`, `seg_nivel_internacion_medicamento/practica`

## Paso 7 — Limpieza

- Eliminar: `ConsultaEvolucion`, `ConsultaObstetricia`, `ConsultaSintomas` (sin callers activos)
- Eliminar: `form/ConsultaDiagnosticosForm`, `AMBDiagnosticoForm`, `IMPDiagnosticoForm`
- Podar `ConsultaProcesamientoService` métodos `guardar*` legacy (post-`guardar()`)
- Imports muertos en controllers

## Paso 8 — Drop + verificación

Ver checklist en [MIGRATIONS.md](../MIGRATIONS.md).
