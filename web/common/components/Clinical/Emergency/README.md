# Clinical — Guardia / urgencias (`Emergency`)

Servicios del **circuito operativo de guardia**: ingreso, triage, cola, atención, derivación, SLA e indicadores.

- **`Service/`** — `Guardia*Service`, acceso por efector, push, métricas.
- **`Enum/`** — `CircuitoEstado`, `CircuitoEventType`, `TriageScale`.

Modelos AR: `common/models/Emergency/`, `common/models/Guardia.php`.

API: `EmergencyGuardiaController` (`/api/v1/clinical/...`).

Documentación: [urgencias-triage-tablero](../../../../docs/plans/urgencias-triage-tablero/design.md).
