# Metadata del producto Bioenlace

Metadata **declarativa del rubro** (salud). Los motores genéricos la consumen vía
`common\components\Platform\Core\Product\ProductMetadataPaths`.

**No es config de Yii.** `common/config/main.php` / `params.php` = runtime (DB, components, secretos).
Esta carpeta = composición del producto (flows, knobs, prompts, ui-text, catálogos, manifiestos).
Cableado `handler_id →` PHP: `common/config/product-registries.php`.

Para otro vertical: copiar la carpeta, ajustar YAML y opcionalmente
`productMetadataDir` en `params-local.php`.

Guía de límites YAML vs Yii: [`web/docs/arquitectura/metadata-yaml-uso.md`](../../../docs/arquitectura/metadata-yaml-uso.md).  
Maestros vs metadata (runtime + cognitivo): [`web/docs/arquitectura/runtime-datos-y-metadata.md`](../../../docs/arquitectura/runtime-datos-y-metadata.md).

## Tipología de archivos

| Tipo | Qué es | Qué no es |
|------|--------|-----------|
| **flow** | Guion conversacional (`when`/`next`, pantallas, draft) | Integridad clínica ni gates hard |
| **knob** | Umbrales, flags, overrides sobre policy PHP | Fuente de verdad de «¿puede emitirse?» |
| **prompt** | `stable_prompt` / instrucciones a la IA | Texto que ve el usuario (eso es ui-text) |
| **ui-text** | Textos UX (mensaje, rótulos, variantes por cliente) | Predicados de dominio (van en PHP) |
| **catalog** | Vocabulario cerrado `id → texto` para IA o UX | Alias id→id, mapas legacy, listas de ids sin copy (van en el loader PHP) |
| **routing** | Familias NL, hints, booking CTA, thread tags | `if intent_id` en orquestadores |
| **manifest** | Composición de superficie (panel, client-context, screen-params) | RBAC HTTP (eso es `platform/permission/`) |
| **auth** | Capabilities, políticas de recurso (`domain-operation-policies`) | Autorización ad hoc en controllers; aliases legacy (retirados) |

**No** usar metadata para maestros/catálogos de lookup en request (provincias, vecinos, recursos institucionales, etc.): van en **BD** + seed **console**. Ver ADR runtime datos vs metadata.

> Seeds one-shot / dumps Georef no viven aquí. Geo: tablas `geo_*` + console por país.

## Plantilla de cabecera (YAML nuevos o al tocar)

```yaml
# Tipo: flow | knob | prompt | ui-text | routing | catalog | manifest | auth
# Propósito: una línea
# Consumidor: ClassName / ProductMetadataPaths::foo()
# No poner aquí: integridad clínica / gates hard / maestros de lookup (van en BD)
# Catalog: solo id → texto (IA o UX); alias/mapas técnicos en el loader PHP
```

## Estructura (migración DDD en curso)

ADR: [`ddd-bounded-contexts-capas-y-metadata.md`](../../../docs/decisions/ddd-bounded-contexts-capas-y-metadata.md).

**Ya colocalizado en código (fases 01–02):**

| Ruta canónica | Tipo |
|---------------|------|
| `components/Domain/<BC>/Application/Flows/intents/…` | flow (Clinical, Scheduling, Person, Organization) |
| `components/Platform/Assistant/Application/Flows/intents/…` | flow transversales (DataAccess, queja, …) |
| `components/Platform/Assistant/Application/{Catalog,Routing,Schemas,Preprocess}/` | catalog / routing / schemas / prompt preprocess |
| `components/Platform/Assistant/Channels/{Name}/` | prompt / ui-text de canal |
| `components/Platform/Assistant/Presentation/` | ui-text by-client |

| `components/Platform/Ui/Presentation/` | manifests UI (home-panel, client-context, …) |
| `components/Platform/Core/Permission/metadata/` | auth composition (policies + capabilities) |
| `components/Platform/Ai/Application/` | clinical-text-ia, ai-cost-reference |

**Aún bajo esta carpeta (legacy hasta fases siguientes):**

| Ruta | Tipo | Contenido |
|------|------|-----------|
| `platform/agents/` | knob | → PHP Application (fase 04) |
| `terminology/` | catalog | → Terminology Domain (fase 05) |
| `clinical/`, `organization/`, `scheduling/`, `person/` (YAML sueltos) | knob/catalog | → Domain PHP/BD (fase 05) |
| `integrations/` | *(revisar)* | Si es lookup → BD |

> Geo multi-país: tablas `geo_paises`, `geo_provincias`, `geo_provincia_vecinos`, `geo_recursos_*`. Seeds: `php yii clinical-seed/geo-multipais`.

Contrato de pasos de intent: `common/components/Platform/Assistant/SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`.

Canal guide / trámite / menú: `ChatChannelPolicy` (PHP). Prompt guide: `Platform/Assistant/Channels/Guide/prompt.yaml`.
Prompts de canal: reglas transversales; huecos de datos en loaders (`null`), sin registro global de limitaciones (regla `asistente-prompts-sin-casos-particulares.mdc`).
Booking CTA: `Platform/Assistant/Application/Routing/booking-offer.yaml`.
