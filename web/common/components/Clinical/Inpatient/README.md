# Clinical — Internación operativa (`Inpatient`)

Servicios de **gestión de internación** en el efector: mapa de camas, ingreso, cambio de cama, alta estructurada, epicrisis, indicadores.

- **`Service/`** — `Internacion*Service`, acceso por efector.

**No confundir** con `Clinical/Specialty/Inpatient/`, que resuelve el **contexto FHIR** (episode, encounter IMP, órdenes clínicas) para documentación y API clínica.

API: `InternacionController`, `InternacionEpicrisisPlantillaController` (API v1 clinical).
