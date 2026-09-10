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

## Estructura

El primer nivel es **dominio** o `platform` (misma posición). Los intents viven en el dominio que persiste el resultado (`IntentSchemaPaths` los descubre solos).

| Ruta | Tipo | Contenido |
|------|------|-----------|
| `<dominio>/intents/{create,read,update,delete}/` | flow | Flows por `intent_id` (métricas en `read/`; pantallas en `read/flows/`) |
| `platform/intents/` | flow | DataAccess y flujos transversales (p. ej. queja) |
| `platform/assistant/channels/{Name}/` | prompt / ui-text | Espejo de `Chat/Channels/{Name}/` |
| `platform/assistant/preprocess/prompt.yaml` | prompt | Preprocess IA |
| `platform/assistant/ui-text/by-client.yaml` | ui-text | Textos UX por perfil de cliente |
| `platform/assistant/routing/` | routing / knob | `intent-families`, `booking-offer`, `thread-state` |
| `platform/assistant/catalog/` | catalog | Vocabularios cerrados (`context-his-areas`, `preprocess-*`, `smart-catalog`) |
| `platform/assistant/assistant-shortcut-group-labels.yaml` | manifest | Etiquetas/orden de grupos de atajos |
| `platform/agents/` | knob | Política operativa por `agent_id` |
| `platform/permission/` | auth | `domain-operation-policies`, `capabilities/` |
| `platform/ui/` | manifest | home-panel, client-context, screen-params, paciente-contexto-offering |
| `platform/ai/` | prompt + knob / catalog | clinical-text-ia, ai-cost-reference |
| `terminology/` | catalog | SNOMED ECL, sinónimos de servicio institucional |
| `clinical/` | catalog + intents | p. ej. `pedido-atencion.yaml` |
| `organization/` | knob + intents | Agenda por encounter class, pricing PES, atributos efector |
| `scheduling/` | catalog + intents | `turno-behavior-profile.yaml` |
| `person/` | knob + intents | `ventanilla-sesion.yaml` |
| `integrations/` | *(revisar)* | Si es lookup runtime → BD; si es mapa de motor → OK |

> Geo multi-país: tablas `geo_paises`, `geo_provincias`, `geo_provincia_vecinos`, `geo_recursos_*`. Seeds: `php yii clinical-seed/geo-multipais`.

Contrato de pasos de intent: `common/components/Platform/Assistant/SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`.

Canal guide / trámite / menú: `ChatChannelPolicy` (PHP). Prompt guide: `platform/assistant/channels/Guide/prompt.yaml`.
Prompts de canal: reglas transversales; huecos de datos en loaders (`null`), sin registro global de limitaciones (regla `asistente-prompts-sin-casos-particulares.mdc`).
Booking CTA: `platform/assistant/routing/booking-offer.yaml`.
