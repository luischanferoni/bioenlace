# Domain/Integrations (retirado)

Los adapters externos ya no viven aquí. Van bajo el BC dueño:

| Antes | Ahora |
|-------|--------|
| `Integrations/Identity` | `Person/Infrastructure/External/Identity/` |
| `Integrations/Mpi` | `Person/Infrastructure/External/Mpi/` |
| `Integrations/Laboratory` | `Clinical/Infrastructure/External/Laboratory/` |
| `Integrations/Prescription` | `Clinical/Infrastructure/External/Prescription/` |
| `Integrations/ClinicalHistory` | `Clinical/Infrastructure/External/ClinicalHistory/` |
| `Integrations/Scheduling` | `Scheduling/Infrastructure/External/` |
| `Integrations/Sisse` (widgets) | `Platform/Ui/Widgets/Sisse/` |
| `Integrations/Service/IntegrationRetryAgent` | `Clinical/Application/Agent/` |

Forma canónica:

```text
Domain/<BC>/Infrastructure/External/<Sistema>/
  Contract/
  Connector/
  Mapper/
  Service/   # sync / reconcile de borde (opcional)
  Dto/
  Exception/
```

Plan: `web/docs/plans/ddd-capas-y-metadata/phases/06-capas-php-e-integrations.md`.
