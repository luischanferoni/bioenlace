# Valores derivados: una sola fuente

## Objetivo

Que ningún valor de negocio se escriba dos veces. Cuando un valor depende de otro —capacidad del
profesional, presets de volumen, `max_pes`, addons incluidos, copy del sitio— se **calcula desde
su fuente** y se lee con el loader; nunca se copia como literal, fallback o default en el
consumidor.

La regla ya está publicada: `.cursor/rules/fuente-unica-valores-derivados.mdc` (transversal) y su
publicación para agentes en `.github/instructions/fuente-unica-valores-derivados.instructions.md`.
Este plan corrige el código que hoy no la cumple y agrega el control que impide la recaída.

## Lo medido

| # | Zona | Archivo | Duplicación |
|---|------|---------|-------------|
| 1 | Catálogo de precios | `PricingPesByEncounterClassCatalog` | ~~`attention_volume_presets` repite la capacidad como literales~~ — **resuelto en fase 01**: ahora se deriva de `reference_encounters_per_professional_month` |
| 2 | Metadata de precios | `PricingPesByEncounterClassMetadata::referenceEncountersPerMonth` | ~~Fallback `500.0`~~ — **resuelto en fase 01**: falla explícito |
| 3 | Metadata de precios | `dictadoMinutes` / `dictadoCogsPerEncounter` | ~~Fallbacks `5` y `0.0005`~~ — **resuelto en fase 01**: leen `cogs_assumptions` sin fallback |
| 4 | Alta comercial | `SignUpInstitutionalEfector::normalizePlan` | Volumen `200` hardcodeado para el perfil legacy `CONSULTORIO` |
| 5 | Cambio de plan | `SwitchBillingMembership::normalizePlanOrCopyFromAccount` | Fallback `max_pes => 5` y, peor, `dictado_incluido => false` cuando el catálogo dice incluido en todas las clases |
| 6 | Entitlements | `BillingAccountService::upsertEntitlement` | Política inline: `dictado_incluido` sólo en EMER/IMP y `videollamada_permitida = 0` |
| 7 | App Personal de Salud | `staff_signup_screen.dart` | Fallback de plan `AMB: 200` + `videollamada_permitida: false` |
| 8 | Interoperabilidad | `params.php` (`clinicalHistoryExchange.encounter_classes`) y `EnqueueClinicalHistoryOutbound` | `['AMB','EMER','IMP']` repetido en config y como default, cuando la fuente es `EncounterDefinition::ENCOUNTER_CLASS` |
| 9 | Docs | `resumen-costos-bioenlace`, `matriz-argentina-modulos-precios`, `modelos-pricing-diferenciados`, `mapa-vias-ingreso-bioenlace`, `analisis-centro-datos-propio` | 9 menciones de 500/700/400 como texto |

Los tests de precios (`PricingPesByEncounterClassTest`, `InstitutionalSignupBillingTest`) fijan
montos derivados; está bien que lo hagan como expectativa del precio público, pero deben quedar
anclados a la capacidad del catálogo y no a otro literal paralelo.

## Por qué importa

1. **Contradicción de producto (el peor caso).** El fallback de `SwitchBillingMembership` dice que
   el dictado no está incluido, mientras catálogo, alta y sitio lo venden como incluido. Un cambio
   de plan puede degradar un entitlement sin que nadie lo note.
2. **Precio que no acompaña.** Si cambia la capacidad de referencia, los presets siguen mostrando
   los mismos volúmenes y el 1× deja de representar "jornada completa". El usuario cotiza sobre una
   escala que ya no significa lo que dice el comentario del catálogo.
3. **Deriva silenciosa en clientes.** Un default en Flutter o en el fallback de la API produce
   cotizaciones distintas según el canal, sin error visible.

## Qué **no** es este plan

- No se cambian precios, márgenes ni COGS: sólo de dónde se leen.
- No se toca el contrato de `GET /licencia/planes` ni `POST /licencia/cotizar`.
- Las migraciones aplicadas no se editan aunque contengan literales: son fotografía histórica.
- No se persigue cualquier número del repo: sólo valores **derivados de negocio** (capacidad,
  volumen, límites, política de addons, listas de clases).

## Fases

| Fase | Qué | Estado |
|------|-----|--------|
| 01 | Catálogo de precios: presets derivados y fallbacks eliminados | Implementada |
| 02 | Entitlements y políticas desde el catálogo | Pendiente |
| 03 | Clientes: defaults que vienen de la API | Pendiente |
| 04 | Clases de atención: una lista para config y cola FHIR | Pendiente |
| 05 | Gate de literales derivados + docs + cierre | Pendiente |

### Fase 01 — Catálogo de precios (implementada)

- `attention_volume_presets` dejó de existir en el catálogo. En su lugar hay
  `attention_volume_multipliers` (0,5 · 1 · 2 · 4 · 10 · 20 · 40 · 100 · 200) y
  `attention_volume_copy` (prefijo, unidad y hint por clase y multiplicador).
  `PricingPesByEncounterClassMetadata::attentionVolumePresets()` calcula los volúmenes desde
  `reference_encounters_per_professional_month` y arma el label con el volumen real.
- `defaults.initial_attentions` dejó de tener literales: el catálogo declara
  `initial_attentions_multiplier` (0,5) y `initialAttentionsByClass()` lo multiplica por la
  capacidad de cada clase.
- `referenceEncountersPerMonth()` ya no devuelve `500` cuando falta la capacidad: lanza
  `InvalidConfigException` con la clase que la necesita.
- `dictadoMinutes()` y `dictadoCogsPerEncounter()` leen `cogs_assumptions` sin fallback; si falta el
  supuesto, fallan explícito.
- El payload de `GET /licencia/planes` quedó **idéntico** (mismos volúmenes, labels y hints) porque
  la derivación reproduce los valores publicados.
- Tests nuevos en `PricingPesByEncounterClassTest`: presets derivados por clase, defaults
  derivados, y fallo explícito sin capacidad de referencia.

### Fase 02 — Entitlements y políticas

- `BillingAccountService::upsertEntitlement`: inicializar `dictado_incluido` y
  `videollamada_permitida` desde `addonState()`; el estado por defecto de una clase nueva sale del
  catálogo, no de un `in_array`.
- `SwitchBillingMembership::normalizePlanOrCopyFromAccount`: mismo criterio en el fallback y quitar
  `max_pes => 5`; si no hay contrato previo, derivar de `defaults.initial_attentions`.
- `SignUpInstitutionalEfector::normalizePlan`: mover el volumen legacy `200` a
  `defaults.initial_attentions` del catálogo o eliminarlo si el perfil ya no se acepta.
- Test: para cada clase vendible, el entitlement por defecto coincide con `addonState()`.

### Fase 03 — Clientes

- `staff_signup_screen.dart`: eliminar el plan de fallback con `200` y `videollamada_permitida:
  false`; si la API no respondió, mostrar error en vez de cotizar con un plan inventado.
- Revisar que el sitio institucional y la app no repitan presets ni estados de addon: ya consumen
  `GET /licencia/planes`; el objetivo es que siga así cuando se agregue un addon nuevo.

### Fase 04 — Clases de atención

- `clinicalHistoryExchange.encounter_classes` y el default de `EnqueueClinicalHistoryOutbound`
  deben salir de `EncounterDefinition::ENCOUNTER_CLASS` (o de las capacidades declaradas allí).
  Si el knob sigue existiendo, es un filtro sobre la lista fuente, no una segunda lista.

### Fase 05 — Gate y cierre

- **Test duro (sin baseline):** `SourceOfTruthDerivedValuesTest` en `web/common/tests/unit`, con
  los contrastes de las fases 01 y 02. Corre en la suite normal.
- **Gate con baseline:** `web/tools/audit-derived-values.php` + `derived-values-baseline.json`,
  modo `--check` con exit code, siguiendo el criterio de `web/tools/README.md` (el tope sólo baja).
  Detecta literales de la tabla de valores derivados del catálogo en PHP, Dart y JS fuera de la
  fuente, y fallbacks numéricos en archivos que leen el catálogo.
- Sumar el gate a `.githooks/pre-commit` junto a `ArchitectureHttpUsageTest`.
- Docs: reemplazar las tablas copiadas por enlaces a la fuente o marcarlas como ejemplo
  ilustrativo; el copy del sitio se queda porque es copy, no cálculo.

## Criterio de cierre

- Cambiar la capacidad de referencia en el catálogo mueve presets, `max_pes` derivado y defaults de
  entitlement sin tocar otro archivo.
- Ningún servicio inicializa política de addons con literales.
- Ningún cliente cotiza con un plan propio si la API falló.
- El gate está en pre-commit con baseline versionado y el test comparativo en verde.
- Al cerrar: lo vigente pasa a `decisions/` y esta carpeta se borra.

## Riesgos

- **Precio:** derivar los presets puede cambiar la escala si los literales actuales no son
  exactamente 0,5× y 1× de la capacidad. La fase 01 incluye una comparación previa contra los
  valores publicados antes de reemplazarlos.
- **Checkout persistido:** `prepareDraftForPersistedCheckout` mantiene perfiles legacy. La fase 02
  no debe romper checkouts ya guardados; el default nuevo aplica sólo a altas nuevas.
- **Gate ruidoso:** si el detector es demasiado amplio, el baseline se vuelve inmanejable. La fase
  05 arranca con la tabla de valores derivados explícita, no con heurísticas de cualquier número.
