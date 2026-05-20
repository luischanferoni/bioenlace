# ClinicalEncounter

Entry point para **captura de consulta médica** (profesional en encounter).

- **No** usa `Chat/Preprocess` ni `user_goal`.
- Expone `analizar` / `guardar` delegando en `ConsultaProcesamientoService`.
- HTTP: `ConsultaController` (`/api/v1/consulta/analizar`, `/api/v1/consulta/guardar`).
