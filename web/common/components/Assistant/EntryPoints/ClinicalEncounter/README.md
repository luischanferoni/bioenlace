# ClinicalEncounter

Entry point para **captura de consulta médica** (profesional en encounter).

- **No** usa `Chat/Preprocess` ni `user_goal`.
- Expone `analizar` / `guardar` delegando en `ConsultaProcesamientoService`.
- HTTP: `clinical/EncounterController` (`/api/v1/clinical/encounter/analizar`, `/api/v1/clinical/encounter/guardar`). Legacy `ConsultaController` responde 410.
