# Índice de Prompts del Proyecto

Convención: **cada módulo guarda sus prompts en su propia carpeta `Prompts/`**, y el código los pide por clave
mediante `PromptRegistry` (`Platform/Ai/Prompt/PromptRegistry.php`). El mapa clave → archivo vive en
`web/common/config/prompts.php` (raíz de composición). Así Platform no conoce rutas ni contenido de Domain.

## Platform (independiente de dominio)

| Clave | Archivo | Propósito |
|---|---|---|
| `assistant.preprocess` | [Assistant/Prompts/preprocess.yaml](Assistant/Prompts/preprocess.yaml) | 1ª IA: etiquetado + routing_hint + historial |
| `assistant.guide` | [Assistant/Prompts/guide.yaml](Assistant/Prompts/guide.yaml) | 2ª IA: selección de intents del asistente |

## Domain (conocimiento clínico)

| Clave | Archivo | Propósito |
|---|---|---|
| `clinical.capture_extraction` | [Capture/Prompts/capture-extraction.yaml](../Domain/Clinical/Capture/Prompts/capture-extraction.yaml) | Extracción de datos de captura (humano). Esqueleto genérico: las categorías llegan como variables; incluye bloque de abreviaturas ambiguas |
| `veterinary.capture_extraction` | [Veterinary/Capture/Prompts/capture-extraction.yaml](../Domain/Veterinary/Capture/Prompts/capture-extraction.yaml) | Extracción veterinaria (v1 exploratoria; mismo contrato de salida). Se elige por ámbito de atención |
| `clinical.automatic_coding` | [Encounter/Prompts/automatic-coding.yaml](../Domain/Clinical/Encounter/Prompts/automatic-coding.yaml) | Codificación diagnóstica CIE-10 / SNOMED |
| `terminology.snomed_context` | [Terminology/Prompts/snomed-context.yaml](../Domain/Terminology/Prompts/snomed-context.yaml) | Término SNOMED CT más preciso por categoría |
| `clinical.care_pack` | [CareCohort/Prompts/care-pack.yaml](../Domain/Clinical/CareCohort/Prompts/care-pack.yaml) | Packs reutilizables de cohorte |
| `clinical.appointment_reason_summary` | [Encounter/Prompts/appointment-reason-summary.yaml](../Domain/Clinical/Encounter/Prompts/appointment-reason-summary.yaml) | Resumen breve de motivos para el médico |
| `clinical.appointment_reason_insights` | [Encounter/Prompts/appointment-reason-insights.yaml](../Domain/Clinical/Encounter/Prompts/appointment-reason-insights.yaml) | Orientación preliminar diagnósticos/prácticas |

## Agregar un prompt

1. **Platform** (agnóstico de dominio) → `Platform/<Módulo>/Prompts/`.
2. **Domain** (conocimiento clínico) → `Domain/<Contexto>/<Módulo>/Prompts/` (`Shared/` si lo usan varios módulos).
3. Registrar la clave en `web/common/config/prompts.php` y consumirla con `PromptRegistry::render()` / `::text()` / `::path()`.
4. Agregarlo a este índice (y al test `YamlPromptTest` si tiene placeholders).

No usar rutas hardcodeadas (`__DIR__ . '/Prompt/...'`) en el código.

## Headers estándar en cada prompt

- `# Tipo:` · `# Propósito:` · `# Consumidor:` · `# Placeholders:` (si aplica) · `version:`

Los YAML que no son prompts (schemas, catálogos, `ui-text`, `ai-cost-reference`, y la política de captura `Domain/Clinical/Capture/Policy/capture-policy.yaml`) no van en `Prompts/`.

## Escalabilidad por especialidad

- Un solo prompt base por vertical (humano / veterinario). Las especialidades no tienen prompt propio: sus categorías entran por `{categories_block}` y `{category_semantics}`.
- Una especialidad solo justifica un overlay o prompt aparte si sus reglas contradicen las del base, cambia el sujeto o el modelo de datos, o hay un riesgo clínico que exige instrucciones propias. Validarlo con un set de casos dorados por especialidad.
- Veterinaria no comparte el base: tiene su prompt y mantiene el mismo contrato de salida (`texto_procesado` + `datosExtraidos`).
- Pendiente: mover `category_hints` del prompt a la definición de cada categoría.
