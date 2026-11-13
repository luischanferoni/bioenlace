---
description: "Fallback silencioso prohibido — si falta un valor de configuración o negocio, fallar explícito; defaults sólo si están declarados en la fuente"
applyTo: ['**']
---

<!-- GENERADO por tools/sync-agent-rules.php — editar .cursor/rules/*.mdc -->

# Configuración sin fallbacks silenciosos

Complementa [`fuente-unica-valores-derivados.mdc`](fuente-unica-valores-derivados.mdc) y
[`source-of-truth-catalogos.mdc`](source-of-truth-catalogos.mdc).

## Regla

Un **fallback silencioso** es devolver un valor inventado cuando falta un dato requerido. Oculta
un error de configuración y hace que el sistema opere con una política que nadie declaró.

Si el valor es necesario para calcular, cobrar, autorizar o mostrar una decisión de negocio,
**su ausencia es un error**: se lanza una excepción con la clave faltante. Un `0`, `''`, `false`,
`[]`, `1` o `'default'` no es un estado válido para ese valor.

**Test para decidir:** si el valor faltante cambia el comportamiento de negocio y nadie lo
declaró, no hay default posible. Si el valor es opcional y su ausencia tiene un significado
explícito (p. ej. “sin tope”), el default se declara en la fuente y se documenta.

## Prohibido

- `?? 0`, `?? ''`, `?? false`, `?? true`, `?? []`, `?? 1` sobre claves de negocio en loaders,
  catálogos, metadata y servicios de dominio.
- `catch (\Throwable $e) { return 0; }` / `return null;` / `return [];` para tapar un fallo de
  configuración o de integración. Se propaga o se envuelve con contexto.
- `isset($x) ? $x : <literal>` y `$x ?: <literal>` cuando `$x` es obligatorio.
- Listas de roles, clases, addons o ids inline en servicios/controladores cuando existe un
  manifiesto (`roles.yaml`, `EncounterDefinition`, catálogo de precios).
- Defaults duplicados en el consumidor “porque la fuente podría faltar”.

## Permitido

- Default **declarado en la fuente** y leído por el loader (p. ej. `defaults.initial_attentions`,
  `requiere_horario_activo: true` en `roles.yaml`).
- Colecciones opcionales donde `[]` significa “no hay elementos” (no “no hay configuración”).
- Valores técnicos sin dependencia de negocio: timeouts, tamaños, códigos de proveedor.
- Migraciones ya aplicadas y fixtures de test.
- Un `null` explícito que el dominio interpreta como “sin valor” (p. ej. `max_pes` null = sin
  tope) cuando el contrato lo documenta.

## Checklist antes de escribir `??`

1. ¿La clave es obligatoria para una decisión de negocio? → lanzar excepción con la clave.
2. ¿Existe un default en la fuente? → leerlo del loader, no escribirlo en el consumidor.
3. ¿La ausencia es un estado válido? → documentarlo y nombrar el default.
4. ¿Es una lista cerrada? → leerla del manifiesto/catálogo; no repetirla.
5. ¿Un `catch` devuelve un valor? → revisar si está tapando un error de configuración.

## Anti-patrón real

`marginOnCostPercent()` devolvía `0` si faltaba `margin_on_cost_percent`: el precio quedaba igual
al COGS y se vendía sin margen, sin error visible. `taxAssumptions()` devolvía tasas `0` y el
margen después de impuestos se calculaba como si no hubiera impuestos. `referenceEncountersPerMonth()`
devolvía `500` para cualquier clase. Los tres hoy fallan explícito.

## Enforcement

- `SourceOfTruthDerivedValuesTest` y los tests de cada catálogo: el valor derivado se contrasta
  contra la fuente, no contra otro literal.
- Gate `web/tools/audit-derived-values.php`: contadores `catalog_numeric_fallback` (fallbacks
  numéricos en loaders), `inline_role_list` (listas de roles inline) y `config_catch_default`
  (`catch` que devuelve default en configuración/permisos), con baseline versionado y sumados a
  `.githooks/pre-commit`.
- `php tools/sync-agent-rules.php --check` mantiene esta regla publicada a todos los agentes.
