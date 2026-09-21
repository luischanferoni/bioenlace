# Plan — DDD/CA empaquetado transversal (todos los BCs)

| Campo | Valor |
|-------|--------|
| Slug | `ddd-empaquetado-transversal` |
| Estado | Pendiente de ejecución |
| Superficie | `common/components/Domain/**`, rules/ADRs de gramática, tests de forma |
| Piloto hecho | `Clinical/Capture` (UseCase / Presentation / Service + sufijos transversales) |

## Índice

- [overview.md](./overview.md) — por qué y alcance
- [design.md](./design.md) — ejes, reshape de BCs, catálogo de sufijos, anti-patrones
- [phases/01-clinical-restantes.md](./phases/01-clinical-restantes.md)
- [phases/02-organization-modulos.md](./phases/02-organization-modulos.md)
- [phases/03-scheduling-modulos.md](./phases/03-scheduling-modulos.md)
- [phases/04-person-modulos.md](./phases/04-person-modulos.md)
- [phases/05-bcs-compactos.md](./phases/05-bcs-compactos.md)
- [phases/06-cierre-docs-y-guardrails.md](./phases/06-cierre-docs-y-guardrails.md)

## Norte (no negociable)

1. **Un eje por nivel** — [ddd-norte-modelo-rico.md](../../decisions/ddd-norte-modelo-rico.md).
2. Bajo `Application/` solo roles CA: `UseCase/`, `Presentation/`, `Service/`, `Authorization/`, `Flows/`, `Agents/` (+ `Seed/` si aplica como plugin de arranque).
3. **Sufijos = solo técnicos transversales** (catálogo cerrado). Sin `Normalizer` / `Sanitizer` / `PostProcessor` / `Checkpoint` / `Validator` (Application) / carpetas de capacidad.
4. Donde el BC creció en **layer-first** o con **capacidades bajo `Application/`**, hay que **renombrar/repartir módulos** para alinear el mismo eje que Clinical.

## Al cerrar

Volcar decisiones a `decisions/` (actualizar gramática / clinical-modulos / eventual ADR “BC module-first”), actualizar `Domain/README.md` + READMEs de cada BC, borrar esta carpeta.
