# Clinical — Internación operativa (`Inpatient`)

Servicios de **gestión de internación** en el efector: mapa de camas, ingreso, cambio de cama, epicrisis, indicadores. El alta clínica la indica el médico en el encounter.

- **`Application/Service/`** — `Inpatient*Service` (admission, bed map/status/transfer/suggestion, discharge structured, epicrisis templates, indicators).
- **`Application/Authorization/`** — `InpatientAccessService`, `InpatientEfectorAccess`, `ClinicalInpatientStaffAccessPolicy`.

**No confundir** con `Clinical/Specialty/` (odontología / oftalmología / aux), ni con el recurso FHIR Encounter IMP (ciclo de vida en `Encounter/`).

API: `InternacionController` (id público histórico), plantillas epicrisis. Tablas BD: `seg_nivel_internacion*`, `internacion_epicrisis_plantilla`.
