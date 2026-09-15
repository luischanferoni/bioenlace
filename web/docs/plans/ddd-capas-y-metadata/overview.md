# Overview — DDD capas y metadata

## Problema

1. La carpeta `components/Domain/` mezcla bounded contexts reales (`Clinical`, `Scheduling`, …) con un cajón de borde (`Integrations`) y roles técnicos inconsistentes (`Service/` como dumping ground: widgets, agents, connectors).
2. La metadata en `common/metadata/bioenlace/` y en `Domain/*/metadata/` mezcla **composición de motores** (flows, routing, prompts) con **política de negocio** (capacity rules, antinoshow, agendas por encounter class) y **lookups** que deberían ser BD.
3. El patrón documentado (`Assistant` | `Home` | `DataAccess` | `Presentation`) es opcional y no tipado: no se puede navegar ni testear forma interior.

## Objetivo

- Cada primer nivel de negocio es un **bounded context (BC)** con capas DDD pragmáticas (compatible con Yii Active Record).
- YAML **solo** donde el consumidor es un motor genérico o es artefacto de copy/prompt; ubicado **dentro del BC o de Platform**, en carpetas de capa con nombres claros.
- Knobs / catálogos de dominio / aliases de lookup → **PHP Domain|Application** o **BD + seed**.
- `Integrations` deja de ser BC: pasa a `Infrastructure/External` del BC dueño (o carpeta infra explícita).
- Invariantes de forma (tests) para roles, sufijos y ubicación de YAML.

## Principios

| Principio | Detalle |
|-----------|---------|
| BC en carpeta | `Clinical`, `Scheduling`, `Person`, `Organization`, `Terminology`, `Geo`, `Programs`, `Content` |
| Capas dentro del BC | `Application` / `Domain` / `Infrastructure` / `Presentation` (+ adapters `Assistant`, `Home`, `DataAccess` hacia Platform) |
| YAML tipado por carpeta | Flow → `Application/Flows`; ui-text → `Presentation`; routing/prompt de motor → bajo `Platform/…` con capa explícita |
| AR = persistencia | `common/models/<BC>/` sigue siendo Infrastructure/Persistence del BC (no Entities puras en esta etapa) |
| Espejo | Controllers, models, metadata de BC y tests siguen el id de BC; Platform en paralelo |
| Sin teatro | No inventar `Entities/`/`Aggregates/` vacíos encima de Active Record |

## Alcance

- Reubicar YAML que **sí** permanecen (intents/flows, routing, prompts, ui-text, schemas, manifests UI, auth composition, catalogs IA del motor).
- Migrar YAML que **no** corresponden (agents knobs, capacity/agenda/behavior/phase policies, aliases lookup, integrations lookup).
- Reordenar PHP del BC hacia capas (piloto + oleadas); desarmar `Integrations`.
- Actualizar `ProductMetadataPaths`, `IntentSchemaPaths`, loaders y docs/reglas Cursor.
- Tests de forma de árbol interior.

## Fuera de alcance (esta construcción)

- Reescritura a domain model puro (Entities sin AR, repositorios en todos lados).
- Renombrar la carpeta raíz `components/Domain` → otro nombre (opcional al cierre si aún molesta).
- Cambiar URLs públicas ni RBAC paths por el solo hecho de mover archivos.
- Editar cuerpo de prompts (sigue regla: humano edita prompts a mano; el plan solo mueve paths / consumidores).

## Éxito

- Abrir un BC muestra `Application` / `Domain` / `Infrastructure` / `Presentation` con contenido tipado.
- `metadata/bioenlace` plano por tipo desaparece o queda solo como compat temporal hasta fin de plan.
- Ningún `*Agent` knob solo en YAML; ningún Widget bajo “Service” de Integrations.
- Test de forma falla si hay YAML de política de negocio en carpeta de flows o `Service/` con sufijo incorrecto.

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Rotura de descubrimiento de intents | Fase 0: paths duales / facade en `IntentSchemaPaths` |
| Diffs enormes | Fases por BC; un PR = una capa o un BC |
| Prompts | Mover archivo sin reescribir contenido; checklist humano |
| Doble fuente YAML+PHP | Migrar con test de paridad y borrar YAML en el mismo PR |