# Metadata YAML â€” uso correcto

CÃ³mo repartir responsabilidades entre **metadata declarativa** (YAML/JSON/params), **modelos Yii** y **servicios de dominio**. Complementa la regla de capas Â«0 hardcodeÂ» y el ADR de captura clÃ­nica.

Para **maestros/catÃ¡logos de lookup en runtime** (geo, recursos, hechos) vs composiciÃ³n YAML: [runtime-datos-y-metadata.md](./runtime-datos-y-metadata.md) y ADR [runtime-datos-vs-metadata.md](../decisions/runtime-datos-vs-metadata.md).

## Principio

YAML describe **quÃ© hacer y con quÃ© parÃ¡metros**. No decide por sÃ­ solo **si un dato clÃ­nico es vÃ¡lido** ni **si una acciÃ³n sensible puede emitirse**.

Si falta un archivo YAML, el producto no debe Â«abrirÂ» gates duros ni inventar integridad: deben seguir vigentes los defaults del dominio.

## Matriz rÃ¡pida

| Pregunta | Respuesta tÃ­pica |
|----------|------------------|
| Â¿CÃ³mo se arma el flow / pantalla / intent? | Metadata (YAML, UI JSON, catÃ¡logos) |
| Â¿QuÃ© campos puede devolver la IA? | Esquema NL (`requeridosPrompt` / prompt) en metadata o tipologÃ­a |
| Â¿QuÃ© campos bloquean confirmaciÃ³n o emisiÃ³n? | Contrato Yii (`*Input` / `rules` / `when`) o servicio de dominio |
| Â¿Umbral, timeout, override de post-proceso? | Knob en YAML sobre policy PHP |
| Â¿Opciones para que el profesional complete un faltante? | `buildIssues` en el contrato de dominio; el cliente no preselecciona |

## Capas (resumen)

1. **OrquestaciÃ³n** â€” enruta y ensambla; no enumera reglas de negocio por intent o pantalla.
2. **Motores genÃ©ricos** â€” interpretan manifiestos; agnÃ³sticos del dominio clÃ­nico.
3. **Metadata** â€” composiciÃ³n y knobs.
4. **Dominio** â€” integridad, autorizaciÃ³n de recurso, gates hard, persistencia.

Detalle de capas: regla del proyecto Â«capas y metadata sin hardcodeÂ».

## Casos de referencia

### Captura clÃ­nica

- TipologÃ­as (`IndicacionInput`, `MedicacionInput`, â€¦): integridad y issues resolubles.
- Completeness: descubre el contrato por `modelo` del workflow; no hardcodea tÃ­tulos de categorÃ­a.
- Post-proceso IA: defaults en `EncounterCaptureExtractionPostProcessPolicy`; `clinical-text-ia.yaml` solo overrides.
- ResoluciÃ³n de faltantes: el profesional elige opciones (`capture_review.issues` â†’ `captura/aplicar-resoluciones`).

ADR: [captura-clinica-contratos-yii-vs-yaml.md](../decisions/captura-clinica-contratos-yii-vs-yaml.md). Narrativa de producto: [captura-clinica.md](../producto/captura-clinica.md).

### Agentes y receta

- Escenarios de bloqueo (p. ej. RDI) viven en el dominio / modelos de prescripciÃ³n.
- YAML de agente autÃ³nomo: polÃ­tica operativa (umbrales, flags), no Â«si no hay archivo, pasar vacÃ­oÂ».

### Asistente

- Intents, alias, scores NL y atajos: YAML + motores genÃ©ricos.
- No poner `intent_id` fijos en orquestadores ni prompts.
- Lectura (â€œcuÃ¡ntos / listar / Ãºltimo Xâ€): mÃ©trica DataAccess + YAML en `intents/read/` con params hidratados; pantallas que no caben van en `intents/read/flows/`. No reabrir `data-access.info|listar` como intents NL.

Ver [asistente-motores.md](./asistente-motores.md), [asistente-lectura-data-access.md](./asistente-lectura-data-access.md) y [rbac-catalogo-permisos.md](./rbac-catalogo-permisos.md).

## Anti-patrones

- Tratar `campos_requeridos` / listas de prompt como hard-required universal.
- Declarar `required_when` de integridad clÃ­nica solo en YAML.
- Parchear orquestadores con `if` por pantalla, intent o tipologÃ­a.
- Autocompletar en silencio campos clÃ­nicos faltantes con la IA.

## Checklist al agregar metadata

1. Â¿Es composiciÃ³n o knob? Si sÃ­, YAML estÃ¡ bien.
2. Â¿Cambia Â«puede guardarse / emitirseÂ»? Si sÃ­, modelar en Yii o servicio y test de dominio.
3. Â¿El motor ya interpreta el manifiesto? Extender el motor una vez; no if en el entrypoint.
4. Â¿Si borrÃ¡s el YAML, el gate hard sigue? Debe seguir.

## Referencias de cÃ³digo (anclas)

- `common/models/Clinical/Input/`
- `EncounterCaptureCompletenessValidator`
- `ClinicalCaptureIssueFactory` / `ClinicalCaptureResolutionApplier`
- `EncounterCaptureExtractionPostProcessPolicy`
- `PrescriptionRdiPreSubmitValidationService`
- `common/metadata/bioenlace/` â€” tipologÃ­a y mapa de carpetas: [`metadata/bioenlace/README.md`](../../common/metadata/bioenlace/README.md)

## TipologÃ­a rÃ¡pida (metadata vs Yii)

| Tipo en metadata | Ejemplo | Yii / dominio |
|------------------|---------|---------------|
| flow / routing / copy | `assistant/intents`, `assistant/prompts` | No sustituye `rules()` |
| knob | `agents/*.yaml`, overrides en `ai/` | Policy PHP + gates hard |
| catalog | `person/recursos-provinciales.yaml`, `terminology/` | Maestro geo en BD; vecinos fijos en PHP |
| manifest / catalog | `ui/`, `terminology/` | No es RBAC HTTP |
| auth | `permission/` | Sync a `auth_item`; el 403 lo resuelve RBAC |

La carpeta **no** es `common/config/`: ahÃ­ vive runtime (DB, components, secretos).
