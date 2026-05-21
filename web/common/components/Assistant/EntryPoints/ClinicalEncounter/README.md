# ClinicalEncounter

Entry point para **captura de consulta médica** (profesional en encounter).

- **No** usa `Chat/Preprocess` ni `user_goal`.
- Expone `analizar` / `guardar` delegando en `ConsultaProcesamientoService`.
- HTTP: `clinical/EncounterController` (`/api/v1/clinical/encounter/analizar`, `/api/v1/clinical/encounter/guardar`). Legacy `ConsultaController` responde 410.
- Persistencia: `EncounterDocumentationService` → `MedicationRequestService` / `ServiceRequestService` para órdenes extraídas por IA.
- `analizar()` delega temporalmente a `Clinical/Legacy/ConsultaProcesamientoService` (sin escribir tablas legacy).
