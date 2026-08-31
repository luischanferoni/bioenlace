# Metadata YAML — uso correcto

Cómo repartir responsabilidades entre **metadata declarativa** (YAML/JSON/params), **modelos Yii** y **servicios de dominio**. Complementa la regla de capas «0 hardcode» y el ADR de captura clínica.

Para **maestros/catálogos de lookup en runtime** (geo, recursos, hechos) vs composición YAML: [runtime-datos-y-metadata.md](./runtime-datos-y-metadata.md) y ADR [runtime-datos-vs-metadata.md](../decisions/runtime-datos-vs-metadata.md).

## Principio

YAML describe **qué hacer y con qué parámetros**. No decide por sí solo **si un dato clínico es válido** ni **si una acción sensible puede emitirse**.

Si falta un archivo YAML, el producto no debe «abrir» gates duros ni inventar integridad: deben seguir vigentes los defaults del dominio.

## Matriz rápida

| Pregunta | Respuesta típica |
|----------|------------------|
| ¿Cómo se arma el flow / pantalla / intent? | Metadata (YAML, UI JSON, catálogos) |
| ¿Qué campos puede devolver la IA? | Esquema NL (`requeridosPrompt` / prompt) en metadata o tipología |
| ¿Qué campos bloquean confirmación o emisión? | Contrato Yii (`*Input` / `rules` / `when`) o servicio de dominio |
| ¿Umbral, timeout, override de post-proceso? | Knob en YAML sobre policy PHP |
| ¿Opciones para que el profesional complete un faltante? | `buildIssues` en el contrato de dominio; el cliente no preselecciona |

## Capas (resumen)

1. **Orquestación** — enruta y ensambla; no enumera reglas de negocio por intent o pantalla.
2. **Motores genéricos** — interpretan manifiestos; agnósticos del dominio clínico.
3. **Metadata** — composición y knobs.
4. **Dominio** — integridad, autorización de recurso, gates hard, persistencia.

Detalle de capas: regla del proyecto «capas y metadata sin hardcode».

## Casos de referencia

### Captura clínica

- Tipologías (`IndicacionInput`, `MedicacionInput`, …): integridad y issues resolubles.
- Completeness: descubre el contrato por `modelo` del workflow; no hardcodea títulos de categoría.
- Post-proceso IA: defaults en `EncounterCaptureExtractionPostProcessPolicy`; `clinical-text-ia.yaml` solo overrides.
- Resolución de faltantes: el profesional elige opciones (`capture_review.issues` → `captura/aplicar-resoluciones`).

ADR: [captura-clinica-contratos-yii-vs-yaml.md](../decisions/captura-clinica-contratos-yii-vs-yaml.md). Narrativa de producto: [captura-clinica.md](../producto/captura-clinica.md).

### Agentes y receta

- Escenarios de bloqueo (p. ej. RDI) viven en el dominio / modelos de prescripción.
- YAML de agente autónomo: política operativa (umbrales, flags), no «si no hay archivo, pasar vacío».

### Asistente

- Intents, alias, scores NL y atajos: YAML + motores genéricos.
- No poner `intent_id` fijos en orquestadores ni prompts.
- **Prompts de canal** (`assistant/prompts/guide.yaml`, `preprocess.yaml`, …): reglas transversales; **no** casos particulares. Los huecos de datos se expresan en **loaders** (campo `null`/ausente en el volcado), no en listas globales de limitaciones. Regla Cursor: `asistente-prompts-sin-casos-particulares.mdc`.
- Lectura (“cuántos / listar / último X”): métrica DataAccess + YAML en `intents/read/` con params hidratados; pantallas que no caben van en `intents/read/flows/`. No reabrir `data-access.info|listar` como intents NL.

Ver [asistente-motores.md](./asistente-motores.md), [asistente-lectura-data-access.md](./asistente-lectura-data-access.md) y [rbac-catalogo-permisos.md](./rbac-catalogo-permisos.md).

## Anti-patrones

- Tratar `campos_requeridos` / listas de prompt como hard-required universal.
- Declarar `required_when` de integridad clínica solo en YAML.
- Parchear orquestadores con `if` por pantalla, intent o tipología.
- Autocompletar en silencio campos clínicos faltantes con la IA.
- Maestros de lookup en metadata YAML (van en BD + seed console).

## Checklist al agregar metadata

1. ¿Es composición o knob? Si sí, YAML está bien.
2. ¿Es lookup de hechos en request? → BD (+ cache); seed en console.
3. ¿Cambia «puede guardarse / emitirse»? Si sí, modelar en Yii o servicio y test de dominio.
4. ¿El motor ya interpreta el manifiesto? Extender el motor una vez; no if en el entrypoint.
5. ¿Si borrás el YAML, el gate hard sigue? Debe seguir.

## Referencias de código (anclas)

- `common/models/Clinical/Input/`
- `EncounterCaptureCompletenessValidator`
- `ClinicalCaptureIssueFactory` / `ClinicalCaptureResolutionApplier`
- `EncounterCaptureExtractionPostProcessPolicy`
- `PrescriptionRdiPreSubmitValidationService`
- `common/metadata/bioenlace/` — tipología y mapa: [`metadata/bioenlace/README.md`](../../common/metadata/bioenlace/README.md)

## Tipología rápida (metadata vs Yii vs BD)

| Tipo | Ejemplo | Dónde |
|------|---------|-------|
| flow / routing / copy | `assistant/intents`, `assistant/prompts` | Metadata; no sustituye `rules()` |
| knob | `agents/*.yaml`, overrides en `ai/` | Metadata; gates hard en dominio |
| manifest | `ui/home-panel-manifest.yaml` | Metadata |
| auth declarativa | `permission/` | Metadata → sync RBAC |
| maestros / lookup de hechos | geo, recursos provinciales | **BD** + seed console (no YAML runtime) |

La carpeta **no** es `common/config/`: ahí vive runtime de arranque (DB, components, secretos).
