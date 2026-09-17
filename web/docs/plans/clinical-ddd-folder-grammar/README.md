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
| 2 | `Agent/` → `Agents/` en resto Clinical + Scheduling + registry | pendiente |
| 3 | Resto módulos Clinical (Emergency, Capture, …) | pendiente |
| 4 | BCs chicos (opcional, misma gramática sin módulo) | pendiente |
| 5 | Aggregates reales (mover reglas desde Application Services) | pendiente |
| Cierre | Borrar esta carpeta; dejar solo ADR + README | pendiente |

## Fuera de alcance de este plan

- Mover controllers Yii bajo `components/Domain`
- Renombrar tablas / API pública ES
- Reescribir lógica de negocio salvo lo necesario por path/namespace

## Notas

- Sin retrocompat de paths: imports rotos se corrigen en el mismo PR de cada módulo.
- `Application/Legacy/` solo como staging interno del piloto Encounter (deja de ser L1); retirar cuando Capture deje de depender de `ConsultaProcesamientoService`.
