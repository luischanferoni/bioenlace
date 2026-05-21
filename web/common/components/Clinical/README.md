# Clinical (`common/components/Clinical`)

Dominio FHIR clínico (Encounter, CarePlan, órdenes).

## Estructura

| Carpeta | Contenido |
|---------|-----------|
| `Enum/` | Vocabularios (`CarePlanStatus`, `CarePlanCategory`, `EncounterStatus`, …) |
| `Support/` | Metadatos JSON (`CarePlanProgramMeta`) |
| `Service/` | Negocio sin HTTP (`CarePlanService`, `CarePlanLifecycleService`, `EncounterAccessService`, …) |
| `Workflow/` | Flujos compuestos (`EncounterDocumentationService`) |

Modelos AR: `common/models/Clinical/`.

## Uso

```php
use common\components\Clinical\Workflow\EncounterDocumentationService;

$result = (new EncounterDocumentationService())->guardar($body);
```

API: `clinical/EncounterController`, `clinical/CarePlanController` (ciclo de vida vía `CarePlanLifecycleService`).

```php
use common\components\Clinical\Service\CarePlanLifecycleService;

// Cierre encounter ambulatorio + completar planes agudos
$lifecycle = new CarePlanLifecycleService();
$encounters->close($encounter, ['continue_treatment' => true]);
```
