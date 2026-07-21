# Assistant (UI Intents + Flows)

Este feature agrupa el stack del **asistente**: descubrimiento de UIs, catálogo de intents, resolución de permisos, y ejecución de flujos conversacionales dentro de un intent.

## Componentes

- `IntentEngine/`: entrypoint para clasificar y devolver una acción UI (o arrancar un flow conversacional).
- `Catalog/`: catálogo de UIs sugeribles (hoy basado en YAML).
- `Chat/`: `asistente/enviar` — preprocess, routing, canales, sobre (`message` | `interactive` | `flow`).
- `WhatsApp/`: transporte Meta Cloud API (webhook → identidad → mismo orquestador → render texto/botones/listas).
- Entrypoints de dominio clínico: `Clinical/Assistant/` (`ClinicalEncounterEntry`, `AppointmentReasonEntry`).
- `Catalog/UiActionCatalogProviderRegistry.php`: resuelve providers declarados en `common/config/product-registries.php`.
- `Catalog/DataAccessUiActionCatalog.php`: acciones API genéricas staff (`/api/info`, `/api/listar`, `/api/editar`).
- `Service/AssistantDraftNormalizer.php`: `encounter_id`, `care_plan_id` en draft de flows.
- Documentación: [web/docs/arquitectura/asistente-motores.md](../../docs/arquitectura/asistente-motores.md), [web/docs/producto/asistente-y-chat.md](../../docs/producto/asistente-y-chat.md)
- `SubIntentEngine/`: motor conversacional *dentro* de un intent (YAML); evaluación de **`business_rules`** (`pre_flow`) vía `IntentBusinessRules`; **`draft_hydrator`** vía `FlowDraftHydratorService` (sin listar intents en `ChatOrchestrator`).
- `FlowManifest/`: construye `flow_manifest` **en runtime** a partir del YAML (sin artefactos `ui_type=flow` en `views/json`).
- `UiActions/`: discovery + RBAC + enriquecedores para construir `client_open` y resolver rutas permitidas.

## Fuentes de verdad

- **Conversación por intent**: `common/metadata/bioenlace/assistant/intents/*.yaml` — contrato: `SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`
- **Piezas reutilizables**: `common/metadata/bioenlace/assistant/globals/*.yaml`
- **Registries producto**: `common/config/product-registries.php` vía `Core/Product/ProductRegistryConfig.php`
- **Mini-UIs** (`ui_json` / wizard): `frontend/modules/api/v1/views/json/<entidad>/<accion>.json`

## Clasificación IA (señal semántica)

Los intents YAML pueden declarar `intent_semantics` (`summary`/`capabilities` + `goal/how/preconditions/constraints/outcome/keyphrases`) para mejorar:

- la clasificación por IA (cuando el texto no matchea keywords literales),
- la explicación (`match.ai.why`) y desambiguación (`kind=intent_remediation`, `rule_id=ai_disambiguation`), y
- la oferta conversacional (texto alineado al botón: solo prometer `capabilities` declaradas; ver `SUBINTENT_CONTRACT.md`).

## Entrypoints importantes

- API chat: `ChatController` → `asistente/enviar`
- WhatsApp: `WhatsAppWebhookController` → `whatsapp/webhook` (público; firma Meta)
- Motivos consulta: `MotivosConsultaController` → `Clinical/Assistant/AppointmentReasonEntry`
- Captura clínica: `clinical/EncounterController` → `Clinical/Assistant/ClinicalEncounterEntry`
 

## Comandos útiles

No hay comando de compilación de `ui_type=flow` hacia `views/json`. El servidor usa YAML en runtime.

## Intents clínicos / operativos (referencia)

| Intent | Área |
|--------|------|
| `urgencias.ver-tablero-guardia` | Guardia EMER |
| `urgencias.triage-paciente-guardia` | Triage Manchester (UI JSON) |
| `internacion.mapa-camas-flow` | Mapa de camas |
| `internacion.alta-estructurada-flow` | Alta estructurada |
| `turnos.indicadores-agenda-flow` | KPIs agenda staff |
| `tratamiento.adherencia-resumen-staff` | Adherencia care plans |
| `personas.vincular-menor-flow` | Tutela verificada (menor sin cuenta) |
| `personas.designar-representante-flow` | Delegación paciente → representante |

Documentación de producto: [internacion.md](../../docs/producto/internacion.md), [urgencias-guardia.md](../../docs/producto/urgencias-guardia.md), [representacion-paciente.md](../../docs/producto/representacion-paciente.md).

