# Clinical — gramática DDD Application / Domain / Infrastructure

## Objetivo

Unificar **todos** los módulos `Domain/Clinical/<Modulo>/` al esqueleto Vernon-like acordado (sin carpeta L1 `Service/`), con Flows/Authorization/Agents/Presentation bajo `Application/`, modelo rico en `Domain/Model/`, y ancla de AR Yii en `Infrastructure/Persistence/`.

ADR vivo: [`decisions/domain-folder-grammar.md`](../../decisions/domain-folder-grammar.md).

## Esqueleto canónico (exacto en cada módulo)

```text
Application/
  Authorization/     # *Access
  Flows/             # intents YAML
  Agents/            # *Agent / *AgentPolicy (plural)
  Presentation/      # *Presenter (no controllers Yii)
  …                  # *Service de caso de uso
Domain/
  Model/             # Aggregates (stubs → migración gradual)
  …                  # enums, *Catalog, policies
Infrastructure/
  External/…         # ACL
  Persistence/
    README.md        # → common/models/Clinical/
```

Borde HTTP/ui_json: `frontend/modules/api/v1/…` (fuera de Domain).  
Plugins BC: `Clinical/Assistant|Home|DataAccess/` solo en raíz Clinical.

## Fases

| Fase | Qué | Estado |
|------|-----|--------|
| 0 | Plan + ADR + README Domain/Clinical | hecho |
| 1 | Piloto **Encounter**: moves + namespaces + Model stubs | hecho (piloto) |
| 2 | `Agent/` → `Agents/` en resto Clinical + Scheduling + registry | hecho |
| 3 | Resto módulos Clinical (Emergency, Capture, …) | hecho |
| 4 | BCs chicos (opcional, misma gramática sin módulo) | hecho |
| 5 | Aggregates reales (mover reglas desde Application Services) | pendiente |
| Cierre | Borrar esta carpeta; dejar solo ADR + README | pendiente |

### Notas fase 2–3 (ejecutado)

- **Fase 2:** `Application/Agent/` → `Application/Agents/` en CareCohort, HistoryExchange, Inpatient, Laboratory, Prescription y Scheduling; `AgentPolicyRegistry` + consumers; test de forma acepta `Agents`.
- **Fase 3:** todos los módulos Clinical (salvo plugins `Assistant/`/`Home/`) quedan en tríada `Application/` · `Domain/` · `Infrastructure/` con ancla `Infrastructure/Persistence/README.md`. Sin `Service/`/`Dto/`/`Presentation/`/`Reminder/`/`Workflow/`/`SpeechToText/`/`Text/` en L1.
- **PedidoAtencion:** VO → `Domain/Model/`; ports/constantes → `Domain/`; adapters Snowstorm/InMemory → `Infrastructure/External/`; services → `Application/`.
- **Specialty:** áreas Odontology/Ophthalmology/Inpatient aplanadas a `Application/` + `Domain/`.
- **CareCohort:** `Assistant/CarePackUiActionCatalog` → `Application/` (plugins solo en raíz BC).
- Sin aliases de retrocompatibilidad de path.

### Notas fase 4 (ejecutado)

- **Content, Geo, Terminology:** tríada completa; Terminology parte `Snomed/` → Application + Domain + `Infrastructure/External/Snowstorm`.
- **Organization:** `Service/` → `Application/` (subáreas Billing/Efectores/…); `Presentation/` → `Application/Presentation/`; `SimulatedPaymentGateway` → `Infrastructure/External/Billing/`.
- **Person:** `Service/` → `Application/`; áreas **Representation** y **Ventanilla** con tríada interna; enums Representation → `Representation/Domain/`.
- **Scheduling:** `Service/` (~97) → `Application/`; `Presentation/` → `Application/Presentation/`; **Quirofano** elevado a área `Scheduling/Quirofano/{Application,Domain,Infrastructure}/`.
- **Integrations / Programs:** sin PHP en Domain — no-op.

## Fuera de alcance de este plan

- Mover controllers Yii bajo `components/Domain`
- Renombrar tablas / API pública ES
- Reescribir lógica de negocio salvo lo necesario por path/namespace

## Notas

- Sin retrocompat de paths: imports rotos se corrigen en el mismo PR de cada módulo.
- `Application/Legacy/` solo como staging interno del piloto Encounter (deja de ser L1); retirar cuando Capture deje de depender de `ConsultaProcesamientoService`.
