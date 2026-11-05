---
description: "DRY y SOLID aplicados a Bioenlace — cuándo extraer, dónde ubicar y qué no abstraer"
applyTo: ['**']
---

<!-- GENERADO por tools/sync-agent-rules.php — editar .cursor/rules/*.mdc -->

# DRY y SOLID — criterios operativos

No son principios decorativos: cada uno tiene una señal de alarma concreta en este repo.
Si una regla de acá choca con una decisión puntual, la excepción se justifica en el PR.

## DRY

- **Regla de tres.** La primera copia está bien; la segunda se tolera; la **tercera** se extrae.
- **Duplicación de conocimiento, no de texto.** Dos bloques iguales que cambian por motivos
  distintos no son DRY: son coincidencia. Abstraerlos acopla y empeora (*wrong abstraction*).
- **Una sola fuente por dato o decisión.** Precios, alícuotas, textos, catálogos, reglas →
  catálogo / param / YAML / BD, nunca el mismo valor en dos lugares
  (ver `source-of-truth-catalogos.mdc`).
- **Plumbing repetido → `Shared/Infrastructure/`.** HTTP, retry, idempotencia, log,
  migración, deduplicación: un base compartido; cada proveedor aporta solo su payload
  y su mapeo de error.
- **Señal fuerte:** si copiás un `try/catch` de transporte, falta un base, no un helper.
- **No** extraer a `*Helper` / `*Manager` / `*Support`: el catálogo de sufijos es cerrado
  (ver `ddd-un-eje-por-nivel.mdc`).

### Dónde extraer

| Se repite en… | Va a |
|---|---|
| 2+ BCs, sin semántica de negocio | `Shared/Infrastructure/<Tema>/` |
| 1 BC, es I/O contra un sistema externo | `Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/` |
| 1 BC, es regla o vocabulario | `Domain/<BC>/Domain/` (`*Catalog`, `*Policy`) |
| 2+ features de UI o descriptor JSON | `Platform/Ui/` |
| Motores agnósticos (IA, asistente) | `Platform/<Motor>/` |

## SOLID

- **S — una razón de cambio.** Si el nombre necesita un «y» (`CheckoutService` que además
  arma el precio, valida IVA, firma webhooks **y** hace HTTP), son tres clases:
  orquestador, pricing, cliente del proveedor.
- **O — extender sin editar.** Variantes nuevas → registro o catálogo, no un `if`/`switch`
  que crece. Prohibido enumerar casos de dominio en motores
  (ver `capas-y-metadata-sin-hardcode.mdc`).
- **L — sustituibilidad.** Hermanos bajo un mismo contrato (`*Connector`) se comportan
  igual ante error: mismo tipo de excepción, mismo criterio `null` vs `throw`.
- **I — contratos chicos.** El puerto expone lo que el consumidor necesita, no la API
  entera del proveedor.
- **D — depender de abstracciones.** Colaboradores por **constructor** y tipados
  (`__construct(?MercadoPagoApiClient $mp = null)`), no `new` de colaboradores adentro
  del método ni `Yii::$app->params` leído en cada llamada. Es lo que permite testear sin red.
  En código nuevo de `Domain/` y `Platform/`, no usar Service Locator (`Yii::$app->get(...)`).

## Enforcement

- `cd web && vendor/bin/codecept run unit platform/core/ArchitectureHttpUsageTest.php`
  bloquea clientes HTTP ad-hoc fuera de `Shared/Infrastructure/Http/` e `Infrastructure/External/`.
- `cd web && composer audit:thin-clients` — ratchet de deuda (el tope solo puede bajar).
