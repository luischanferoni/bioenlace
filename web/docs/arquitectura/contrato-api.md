# Contrato de API v1

Toda respuesta de `frontend/modules/api/v1` sigue una sola forma. Los clientes (móvil y web) la leen y la muestran; no redactan ni interpretan mensajes.

## Éxito

El cuerpo tiene `data` (el resultado) y `meta` (objeto con datos auxiliares, vacío si no hay). Los controllers responden con `ok()` de `BaseController`.

## Error

El cuerpo tiene un único campo `error` con:

- `code`: identificador estable del catálogo.
- `message`: texto para el usuario, definido en el backend. El cliente lo muestra tal cual.
- `intent`: `error`, `warning` o `info`; define el estilo, no el texto.
- `retryable`: si tiene sentido ofrecer reintentar.
- `field_errors`: opcional, errores por campo en validaciones.

El código HTTP lo define el catálogo. Los controllers responden con `fail()` o `failValidation()`.

Un error puede traer además `meta` al nivel raíz (p. ej. `meta.capture` con el estado de la captura). Las entradas con `{detail}` y `default_detail` cubren textos dinámicos generados por el backend.

## Casos de uso

Los casos de uso devuelven arrays planos y fallan con `ApiException`. `BaseController::okFromUseCase()` mueve la clave `message` a `meta.message` y deja el resto en `data`.

## Catálogo

`common/components/Platform/Core/Http/Contract/api-errors.yaml` es la única fuente de textos de error, leída por `ApiErrorCatalog`. Cada entrada declara `http_status`, `intent`, `retryable` y `message`, con marcadores `{nombre}` opcionales. Un código inexistente responde `internal_error`.

## Excepción de dominio

Los servicios lanzan `ApiException` con un código del catálogo (y params o `field_errors`) en lugar de redactar texto. `BaseController::runAction` la convierte en la respuesta de error. Las excepciones no controladas se registran en el log y se responden con un código genérico, sin exponer el mensaje interno.

## Estado visible

`ApiResponder::status` arma `code`, `label` e `intent`. El cliente pinta `label` con el estilo de `intent`; no mapea códigos a textos.

## Verificación

`common/tests/unit/platform/api/ApiContractTest.php` valida el catálogo, la forma de las respuestas y que todo código usado en los controllers exista en el catálogo.

## Migración

Los métodos `success()` y `error()` de `BaseController` quedan marcados como obsoletos hasta migrar todos los controllers (fase 9). Durante la transición, un controller puede activar `contractResponses` para adaptar esas llamadas al contrato después de migrar sus consumidores. En ese modo, los errores HTTP se convierten a códigos del catálogo y los errores 5xx no exponen el mensaje interno. Este adaptador es temporal: luego se reemplazarán las llamadas con `ok()` y `fail()`.

Ya migrados: `AudioController`, `EmergencyController`, `LicenciaController`, `QuirofanoController`, `CareProtocolController`, `InpatientEpicrisisTemplateController`, `PersonaController`, `RegistroController` y `ChatController`, además de los controllers clínicos del piloto. El sobre de conversación del asistente se conserva como `data` dentro de la respuesta común.
