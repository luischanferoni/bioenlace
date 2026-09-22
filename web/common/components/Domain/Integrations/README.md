# Integrations (residual)

No es un bounded context. Los ACL viven en el **módulo o BC dueño**:

| Antes (legado) | Ahora |
|----------------|-------|
| `Integrations/Identity` | `Person/FrontDesk/Infrastructure/External/Identity/` |
| `Integrations/Mpi` | `Person/Identity/Infrastructure/External/Mpi/` |
| `Integrations/Laboratory` | `Clinical/Laboratory/Infrastructure/External/` |
| `Integrations/Prescription` | `Clinical/Prescription/Infrastructure/External/` |
| `Integrations/ClinicalHistory` | `Clinical/HistoryExchange/Infrastructure/External/` |
| `Integrations/Scheduling` | `Scheduling/Agenda/Infrastructure/External/NisFhir/` |

Infra técnica transversal (HTTP base, log, migraciones): `components/Shared/Infrastructure/`.

```text
Domain/<BC>/<Modulo?>/Infrastructure/External/<Sistema>/
```
