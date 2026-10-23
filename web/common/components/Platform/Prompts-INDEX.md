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
| `clinical.word_correction` | [Shared/Prompts/medical-word-correction.yaml](../Domain/Clinical/Shared/Prompts/medical-word-correction.yaml) | Corrección ortográfica en contexto médico |
| `clinical.abbreviation_disambiguation` | [Shared/Prompts/abbreviation-disambiguation.yaml](../Domain/Clinical/Shared/Prompts/abbreviation-disambiguation.yaml) | Selección de expansión correcta de abreviaturas |
| `clinical.text_ia` | [Shared/Prompts/clinical-text-ia.yaml](../Domain/Clinical/Shared/Prompts/clinical-text-ia.yaml) | Extracción/heurísticas IA de texto clínico (vía `ClinicalTextIaMetadata`) |
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

Los YAML que no son prompts (schemas, catálogos, `ui-text`, `ai-cost-reference`) no van en `Prompts/`.
