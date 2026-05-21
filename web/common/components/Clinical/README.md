# Clinical (`common/components/Clinical`)

Dominio FHIR clínico (Encounter, CarePlan, órdenes).

## Estructura

| Carpeta | Contenido |
|---------|-----------|
| `Enum/` | Vocabularios (`CarePlanStatus`, `EncounterStatus`, …) |
| `Service/` | Negocio sin HTTP (`CarePlanService`, `EncounterAccessService`, …) |
| `Workflow/` | Flujos compuestos (`EncounterDocumentationService`) |

Modelos AR: `common/models/Clinical/`.

## Uso

```php
use common\components\Clinical\Workflow\EncounterDocumentationService;

$result = (new EncounterDocumentationService())->guardar($body);
```

API actual (`ConsultaController`) delega vía `ClinicalEncounterEntry`.
