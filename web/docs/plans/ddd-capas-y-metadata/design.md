# Design — DDD capas y metadata

## Decisión de ubicación de YAML

Los YAML que **permanecen** viven **colocalizados** con el código del BC o de Platform, bajo la **capa DDD** que corresponde — no en un árbol paralelo anónimo `metadata/bioenlace/<bc>/archivo.yaml`.

Raíz de código (sin cambiar aún el nombre `components/Domain`):

```text
common/components/Domain/<BC>/
  Application/
    Flows/intents/{create,read,update,delete}/   # manifiestos SubIntentEngine
  Domain/                                        # PHP: Policy, Catalog, Enum (post-migración)
  Infrastructure/
    External/<Sistema>/                          # ACL (ex Integrations)
    Persistence/                                 # opcional; por defecto models/<BC>
  Presentation/                                  # YAML ui-text / copy de superficie del BC (si existe)
  Assistant/ | Home/ | DataAccess/               # adapters → motores Platform (PHP)

common/components/Platform/
  Assistant/
    Application/          # routing, schemas, catalogs IA, shortcuts (composición motor)
    Presentation/         # ui-text by-client, Ambiguous ui-text
    Channels/{Name}/      # prompt.yaml (+ ui-text de canal si aplica)
    Preprocess/           # prompt.yaml
  Ui/
    Presentation/         # home-panel, client-context, screen-params, offerings
  Core/
    Permission/           # domain-operation-policies, capabilities/  (auth del motor)
    Ai/                   # clinical-text-ia, ai-cost (o Platform/Ai/…)
```

`common/metadata/bioenlace/` deja de ser la fuente canónica al cerrar el plan. Durante la migración: **lectura dual** (path nuevo + fallback viejo) hasta borrar el viejo en el mismo PR de cada lote.

Intents transversales (DataAccess, queja): `Platform/Assistant/Application/Flows/intents/…` (equivalente actual `platform/intents`).

## Qué es YAML y qué no

| Sigue YAML | Capa / carpeta | Motivo |
|------------|----------------|--------|
| Flows / intents | `BC/Application/Flows/intents` | Motor SubIntentEngine |
| Routing NL, smart-catalog, intent-families, booking, thread-state | `Platform/Assistant/Application/` | Classifier / routers |
| Schemas NL, preprocess catalogs (texto IA) | `Platform/Assistant/Application/` | Motores IA |
| Prompts de canal / preprocess / AI | `Channels/`, `Preprocess/`, `Ai/` | Artefacto humano |
| ui-text | `Presentation/` | Copy UX |
| Manifests UI | `Platform/Ui/Presentation/` | UiScreen / panel |
| Auth composition (operation → policy ids) | `Platform/Core/Permission/` | Motor permission; implementaciones PHP en BC |

| Deja de ser YAML | Destino |
|------------------|---------|
| `platform/agents/*.yaml` (knobs) | `BC/Application/…Policy` o `…AgentPolicy` tipado + tests |
| `pedido-atencion` capacity_rules / coding knobs de negocio | `Clinical/Domain/…` |
| `acto_nl_aliases` y lookups similares | BD + seed (o catalog PHP temporal si el volumen es bajo y no es hot-path lookup masivo) |
| `turno-behavior-profile` | `Scheduling/Domain/BehaviorProfileContract` |
| `agenda-by-encounter-class`, pricing/atributos si son política | `Organization/Domain/…` |
| `ventanilla-sesion` knobs de negocio | `Person/Domain` o Application |
| `encounter_phase_*` (reglas) | `Clinical/Domain` o Application; titles/bodies → Presentation ui-text |
| YAML en `Domain/*/metadata/` hoy | Reclasificar con la misma tabla; no mantener tercer árbol |
| `integrations/fhir-healthcare-service-codes.yaml` | BD o `Scheduling/Infrastructure` catalog PHP según uso |
| Mapas id→id | Loader PHP (sin cambio de doctrina) |

## Capas PHP (contenido tipado)

| Carpeta | Sufijos / contenido | Prohibido |
|---------|---------------------|-----------|
| `Application/` | `*Service` (use case), `*Agent`, `*Policy` de orquestación, `Flows/*.yaml` | Widget Yii, Connector HTTP, SQL de infra |
| `Domain/` | `*Policy`, `*Catalog`, `*Enum`, reglas sin I/O | `Yaml::parseFile` de producto, Guzzle, Widget |
| `Infrastructure/External/` | `*Connector`, `*Mapper`, `*Registry` de borde | Reglas clínicas “de verdad” |
| `Presentation/` | Presenters sin HTML Yii; YAML ui-text del BC | Menús legacy Widget (van a frontend widgets) |
| `Assistant/` `Home/` `DataAccess/` | Plugins tipados al motor | Casos de uso genéricos del BC |

`Service/` suelto como hermano de capacidades **queda prohibido**. Capacidades (`Emergency`, `PedidoAtencion`, …) pueden vivir bajo `Application/<Capacidad>/` o `Domain/<Capacidad>/` según el rol del código.

## Integrations

No es BC. Mapa de dueño (orientativo):

| Hoy | Dueño destino |
|-----|----------------|
| `Mpi/`, `Identity/` | `Person/Infrastructure/External/` |
| `Scheduling/` (FHIR appointment) | `Scheduling/Infrastructure/External/` |
| `Laboratory/` | `Clinical/Laboratory/Infrastructure/External/` o `Clinical/Infrastructure/External/Laboratory/` |
| `Prescription/` | `Clinical/Prescription/…/External/` |
| `ClinicalHistory/` | `Clinical/HistoryExchange/…/External/` |
| `Sisse/` (`NavSisse` Widget) | **Frontend** widgets / menú — fuera de Domain |
| `Service/IntegrationRetryAgent` | `Clinical/…/Application/` (HistoryExchange) |

## Mapa `agent_id` → BC (cerrado fase 00)

Fuente de código: `AgentBoundedContextMap`.

| agent_id | BC |
|----------|-----|
| `turno-antinoshow` | scheduling |
| `turno-advance-offer` | scheduling |
| `turno-resolucion-loop-close` | scheduling |
| `turno-resolucion-shortlist` | scheduling |
| `turno-resolucion-auto-reserva` | scheduling |
| `turno-resolucion-multicanal` | scheduling |
| `consulta-async-bandeja-prioridad` | scheduling |
| `reserva-triage-post-cupo-routing` | scheduling |
| `lab-encounter-link` | clinical |
| `post-lab-classification` | clinical |
| `prescription-rdi-pre-submit` | clinical |
| `care-followup-branching` | clinical |
| `post-discharge-followup` | clinical |
| `internacion-cama-sugerencia` | clinical |
| `integration-retry` | clinical |

## Descubrimiento técnico

- Extender `ProductMetadataPaths` y `IntentSchemaPaths` para raíces:
  - intents: `Domain/*/Application/Flows/intents` + `Platform/Assistant/Application/Flows/intents`
  - assistant: bajo `Platform/Assistant/…`
  - permission / ui / ai: paths nuevos
- Un solo método `baseDir` deja de apuntar solo a `metadata/bioenlace`.
- Tests: discovery encuentra el mismo conjunto de `intent_id` antes/después de cada lote.
- `productMetadataDir` en params: deprecar o redefinir como lista de raíces.

## Inventario YAML — permanece (reubicar)

### Por BC → `Application/Flows`

- Todo `metadata/bioenlace/{clinical,scheduling,person,organization}/intents/**`
- `platform/intents/**` → `Platform/Assistant/Application/Flows/intents/**`

### Platform Assistant → Application / Presentation / Channels

- `platform/assistant/routing/*` → `Platform/Assistant/Application/Routing/`
- `platform/assistant/catalog/*` → `Platform/Assistant/Application/Catalog/`
- `platform/assistant/schemas/*` → `Platform/Assistant/Application/Schemas/`
- `platform/assistant/assistant-shortcut*.yaml` → `Platform/Assistant/Application/`
- `platform/assistant/ui-text/*` → `Platform/Assistant/Presentation/`
- `platform/assistant/channels/*/prompt.yaml` (+ ui-text canal) → `Platform/Assistant/Channels/{Name}/`
- `platform/assistant/preprocess/prompt.yaml` → `Platform/Assistant/Preprocess/`

### Platform Ui / Permission / Ai

- `platform/ui/*` → `Platform/Ui/Presentation/`
- `platform/permission/*` → `Platform/Core/Permission/` (o `…/Permission/metadata/`)
- `platform/ai/*` → `Platform/Ai/` (Presentation o Application según prompt vs knob de costo)

### Terminology (catalog motor + dominio)

- `terminology/snomed-terminology.yaml`: **evaluar en fase de migración** — ECL/catalog de coding es Domain `Terminology`; si el motor genérico solo carga path, puede vivir en `Terminology/Domain/` como YAML de catalog **solo si** es vocabulario id→ECL/texto para IA. Si es lookup runtime → BD (ADR runtime). Plan: empezar moviendo a `Terminology/Domain/catalog/` y decidir BD en sub-fase si el hot path lo exige.
- `servicio-synonyms.yaml`: Domain Organization o Terminology catalog PHP/YAML según consumidor.

## Inventario YAML — migrar fuera

| Archivo actual | Destino |
|----------------|---------|
| `platform/agents/*.yaml` (todos los knobs) | Policy PHP en BC dueño del `agent_id` |
| `clinical/pedido-atencion.yaml` | Partir: Domain PHP + aliases → BD/seed |
| `scheduling/turno-behavior-profile.yaml` | `Scheduling/Domain` PHP |
| `organization/agenda-by-encounter-class.yaml` | `Organization/Domain` PHP |
| `organization/pricing-pes-by-encounter-class.yaml` | Domain PHP |
| `organization/efector-atributos.yaml` | Domain PHP o BD si es maestro |
| `person/ventanilla-sesion.yaml` | Person Domain/Application PHP |
| `Domain/Clinical/metadata/encounter_phase_*.yaml` | Domain/App + Presentation copy |
| `Domain/Clinical/metadata/motivos_consulta_intake.yaml` | Reclasificar |
| `Domain/Scheduling/metadata/*.yaml` | Domain/App Scheduling |
| `Domain/Person/Representation/metadata/*` | Person Domain |
| `integrations/fhir-healthcare-service-codes.yaml` | BD o Infrastructure catalog |

Lista de agents a mapear a BC (orientativa): antinoshow / resolucion* / advance-offer / consulta-async* / reserva-triage → Scheduling; lab-* / post-lab / prescription-rdi / care-followup / post-discharge / internacion-cama → Clinical; integration-retry → Clinical HistoryExchange.

## Espejo y ProductDomainCatalog

- Primer nivel bajo `components/Domain/` = BC (Integrations deja de existir al final).
- `models/`, `controllers/<bc>/`, tests: sin cambio de id de BC.
- Actualizar `common-components.md`, `Domain/README.md`, regla Cursor `common-components-organizacion`, ADR nuevo al cierre.

## Alternativas descartadas

| Alternativa | Por qué no |
|-------------|------------|
| Solo `metadata/bioenlace/<bc>/<Layer>/` sin colocalizar | El usuario pidió YAML dentro del BC cuando sea posible |
| Layer-first global (`Application/` único del monorepo) | Pierde bounded contexts |
| Entities/Aggregates puros ahora | Costo absurdo con AR Yii |
| JSON en lugar de YAML para flows | Sin ganancia; se mantienen YAML |
| Agrupadores `Core/` `Support/` en el espejo | Rompen `capa/bc/entidad` |