# Clinical — Internación operativa (`Inpatient`)

Servicios de **gestión de internación** en el efector: mapa de camas, ingreso, cambio de cama, epicrisis, indicadores. El alta clínica la indica el médico en el encounter.

- **`Application/Service/`** — `Inpatient*Service` (admission, bed map/status/transfer/suggestion, discharge structured, epicrisis templates, indicators).
- **`Application/Authorization/`** — `InpatientAccessService`, `InpatientEfectorAccess`, `ClinicalInpatientStaffAccessPolicy`.

Modelos AR: `InpatientStay`, `InpatientBedStay`, `InpatientAdmissionType`, `InpatientDischargeType`, … (tablas BD `seg_nivel_internacion*`).

API: `InpatientController` — id público `internacion` (`/api/v1/clinical/internacion/...`).  
Web: mismo id `/internacion/*` → vistas `@frontend/views/inpatient`.
