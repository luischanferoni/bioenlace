# Captura clínica: contratos Yii vs metadata YAML

## Contexto

La captura de encounter (audio/texto → extracción IA → revisión → persistencia) mezclaba tres roles en una sola lista plana `requeridosPrompt()` / `campos_requeridos`:

1. Campos que la IA puede extraer (esquema de prompt).
2. Campos que bloquean la confirmación (completitud).
3. Knobs de post-proceso y gates de agentes en YAML.

Eso produjo hard-stops incorrectos (p. ej. exigir «Plazo dias» en toda indicación, o Resultado/Codigo en toda práctica) y gates de emisión RDI que desaparecían si faltaba el YAML.

## Decisión

1. **Integridad de filas clínicas:** modelos de entrada Yii (`IndicacionInput`, `MedicacionInput`, `PracticaInput`, …) con `rules()` / `when`. Completeness y persistencia los consumen vía `Consulta*::completenessForExtractedRow` cuando el `modelo` del workflow es conocido.
2. **`requeridosPrompt()`:** solo nombres NL del esquema de extracción (incluye campos opcionales condicionales). No es la lista de hard-required.
3. **YAML de producto:** composición (intents, UI, ABAC, prompts, knobs). No es fuente de verdad de «¿puede emitirse / confirmarse?».
4. **Agentes / post-proceso:** defaults y semántica en servicios/policy de dominio (`PrescriptionRdiPreSubmitValidationService`, `EncounterCaptureExtractionPostProcessPolicy`); YAML solo umbrales/overrides. Si falta el YAML, los gates hard siguen activos.
5. **Terminología (SNOMED):** normaliza conceptos; no decide por sí sola obligatoriedad contextual (plazo, dosis).
6. **Issues resolubles (cliente):** si falta un campo, el `*Input` expone `buildIssues()` con opciones sugeridas **sin selección por defecto**. El profesional confirma vía `POST …/captura/aplicar-resoluciones` (`resolutions: { issue_id → value }`). Contrato resumido: `{ id, field, message, options[{value,label}], allow_custom }` (`ClinicalCaptureIssueFactory`).

## Alternativas descartadas

- **`field_schema` en YAML con `required_when`:** duplica Yii y viola «contrato en modelo».
- **Excepciones hardcodeadas en el validator por título de categoría:** deuda de capas.
- **SNOMED como árbitro de plazo / tipo de indicación:** el plazo es semántica del enunciado, no del conceptId.
- **Autocompletar silenciosamente con la IA:** la IA solo sugiere opciones; la selección es del profesional.

## Consecuencias

- Nuevas tipologías de captura: agregar `*Input` + `completenessForExtractedRow` (+ opcional `buildIssues` / `applyResolutionToRow`) en el `Consulta*`; el validator/applier lo descubren por nombre de modelo. No alargar listas planas ni if-chains.
- `clinical-text-ia.yaml` `post_process` / `clinical_lexicon`: overrides; defaults en `EncounterCaptureExtractionPostProcessPolicy`.
- Receta RDI: escenarios `rdi_issue` en `ElectronicPrescription` / Item; YAML `prescription-rdi-pre-submit` solo política operativa.
- Path legacy sin `modelo` en categorías: sigue interpretando `campos_requeridos` como lista plana (compat tests / datos viejos); issues genéricos con `allow_custom`.

## Referencias

- Guía transversal: [arquitectura/metadata-yaml-uso.md](../arquitectura/metadata-yaml-uso.md)
- `common/models/Clinical/Input/`
- `EncounterCaptureCompletenessValidator`
- `ClinicalCaptureIssueFactory` / `ClinicalCaptureResolutionApplier`
- `EncounterCaptureExtractionPostProcessPolicy`
- `PrescriptionRdiPreSubmitValidationService`
