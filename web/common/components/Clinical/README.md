# Clinical (`common/components/Clinical`)

Dominio FHIR clínico (Encounter, CarePlan, órdenes).

## Estructura

| Carpeta | Contenido |
|---------|-----------|
| `Enum/` | Vocabularios (`CarePlanStatus`, `CarePlanCategory`, `EncounterStatus`, …) |
| `Support/` | Metadatos JSON (`CarePlanProgramMeta`) |
| `Service/` | Negocio sin HTTP (`CarePlanService`, `CarePlanLifecycleService`, `EncounterAccessService`, …) |
| `Workflow/` | Flujos compuestos (`EncounterDocumentationService`) |
| `Specialty/` | Odontología, oftalmología (Fase 7); ver [Specialty/README.md](Specialty/README.md) |
| `Laboratory/` | Ingesta pull LIS FHIR → `diagnostic_report` / `observation` |

Modelos AR: `common/models/Clinical/` (`Procedure`, `Observation`, `DiagnosticReport`, …).

Integraciones HTTP: `common/components/Integrations/Laboratory/`. Plan activo: `web/docs/plans/laboratorio-external-fhir/`.

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
