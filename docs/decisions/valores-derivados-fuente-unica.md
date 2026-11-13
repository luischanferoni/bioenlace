# Valores derivados: una sola fuente

## Decisión

Todo valor de negocio que **depende de otro** se calcula en runtime desde su fuente. No se copia
como literal, fallback, default de cliente ni tabla de documentación.

La progresión típica es: capacidad de referencia por profesional y mes →
presets de volumen → `max_pes` derivado → entitlement de la cuenta → copy del sitio → docs.
Cada eslabón se **deriva** del anterior; ninguno se vuelve a escribir.

**Regla publicada:** `.cursor/rules/fuente-unica-valores-derivados.mdc` (transversal).

## Dónde vive la fuente

| Fuente | Cuándo | Ejemplo |
|--------|--------|---------|
| **BD** (migración + seed de console) | Maestros y hechos consultados en runtime | geo, recursos, catálogos operativos |
| **YAML de metadata** | Composición, knobs y copy para IA o usuario final | intents, flows, ui-text |
| **PHP** | Catálogo declarativo, constantes de dominio y el loader | `*Catalog`, `*Metadata`, `*Policy` |

Todo consumidor lee a través del loader o del servicio. Si el valor no está en ninguna de las tres
fuentes, primero se agrega a la que corresponda; nunca se escribe directo en el consumidor.

## Casos resueltos

| Zona | Antes | Ahora |
|------|-------|-------|
| Presets de volumen | Literales 500/700/400 | `attention_volume_multipliers` × capacidad |
| Fallbacks de precio | `500`, `5`, `0` silenciosos | `InvalidConfigException` con la clave |
| Alta y cambio de plan | `200`, `max_pes => 5`, dictado apagado | `initialAttentionsForClass()` y `addonPlanValues()` |
| Entitlements | `in_array($class, ['EMER','IMP'])` | Matriz de addons del catálogo |
| App Personal de Salud | Plan `AMB: 200` inventado | `defaults` de `GET /licencia/planes` |
| Clases de atención | Lista repetida en config, cola FHIR y admin | `EncounterDefinition` y catálogo de precios |
| Docs | Tablas copiadas en varios documentos | Un documento canónico por dominio + enlaces |

## Jerarquía de documentos

Los documentos no son la fuente numérica. Cada dominio tiene **un documento canónico** que
muestra la tabla vigente y enlaza a la fuente; el resto enlaza al canónico. Las cifras de
escenario se marcan como ilustrativas.

| Dominio | Canónico | Fuente |
|---------|----------|--------|
| Precio de licencia | [matriz-argentina-modulos-precios.md](../negocio/modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) | `PricingPesByEncounterClassCatalog` |
| Composición del COGS | [resumen-costos-bioenlace.md](../negocio/costos/resumen-costos-bioenlace.md) | catálogo + [costos-api.md](../negocio/costos/costos-api.md) |
| Clases de atención | [agenda-por-encounter-class.md](../producto/agenda-por-encounter-class.md) | `EncounterDefinition` |

Convención general: [docs/README.md § Fuente única y jerarquía de documentos](../README.md#fuente-única-y-jerarquía-de-documentos).

## Alternativas descartadas

- **Copiar el valor con un comentario “actualizar si cambia”.** Se desfasa en el próximo cambio y
  nadie lo detecta.
- **Fallback al valor publicado.** Convierte un error de configuración en una cotización distinta
  según el canal.
- **Duplicar la tabla en cada doc de negocio.** Cada copia es una fuente más.

## Consecuencias

- Cambiar la capacidad de referencia mueve presets, `max_pes` y defaults sin tocar otro archivo.
- Un valor faltante falla explícito en vez de operar con una política que nadie declaró.
- El precio público no cambió: la derivación reproduce los valores publicados.

## Enforcement

- `SourceOfTruthDerivedValuesTest` y los tests de cada catálogo contrastan el derivado contra la
  fuente.
- `web/tools/audit-derived-values.php` + `derived-values-baseline.json`: contadores de literales
  derivados y listas inline; el tope sólo baja. Corre en `.githooks/pre-commit`.
- `php tools/sync-agent-rules.php --check` mantiene la regla publicada.

La extensión sobre **fallbacks silenciosos** (fallar explícito cuando falta configuración) tiene su
propia regla publicada y su programa de trabajo interno; no forma parte de esta decisión.
