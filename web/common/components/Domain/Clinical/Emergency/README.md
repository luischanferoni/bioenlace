# Clinical — Guardia / urgencias (`Emergency`)

Servicios del **circuito operativo de guardia**: ingreso, triage, cola, atención, derivación, SLA e indicadores.

- **`Application/Service/`** — `Emergency*Service` (board, triage, intake, discharge, queue, SLA, push, métricas).
- **`Domain/`** — `BoardState`, `BoardEventType`, `EmergencyDischargeDestination`, `TriageScale`.

Modelos AR: `common/models/Clinical/Emergency/` (`EmergencyEpisode`, `EmergencyTriage`, `EmergencyBoardEvent`). Tablas BD: `guardia*`.

API: `EmergencyController` — id público `emergency-guardia` (`/api/v1/clinical/emergency-guardia/...`).

Documentación: [urgencias-triage-tablero](../../../../docs/plans/urgencias-triage-tablero/design.md).
