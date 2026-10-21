# Contrato de API v1

Toda respuesta JSON estándar de `frontend/modules/api/v1` sigue una sola forma. Los clientes (móvil y web) la leen y la muestran; no redactan ni interpretan mensajes. Los endpoints que exponen un protocolo UI propio o contenido binario conservan ese formato específico.

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

Los servicios lanzan `ApiException` con un código del catálogo (y params o `field_errors`) en lugar de redactar texto. `BaseController::runAction` convierte `ApiException` y excepciones HTTP de Yii al contrato; los errores 4xx se mapean a códigos del catálogo y los 5xx se registran y responden con un código genérico, sin exponer el mensaje interno. Los métodos no permitidos usan `method_not_allowed_detail` (405).

## Estado visible

`ApiResponder::status` arma `code`, `label` e `intent`. El cliente pinta `label` con el estilo de `intent`; no mapea códigos a textos.

## Verificación

`common/tests/unit/platform/api/ApiContractTest.php` valida el catálogo, la forma de las respuestas y que todo código usado en los controllers exista en el catálogo.

## Migración

Los métodos `success()` y `error()` de `BaseController` quedan marcados como obsoletos hasta migrar todos los controllers (fase 9). Durante la transición, un controller puede activar `contractResponses` para adaptar esas llamadas al contrato después de migrar sus consumidores. En ese modo, los errores HTTP se convierten a códigos del catálogo y los errores 5xx no exponen el mensaje interno. Este adaptador es temporal: luego se reemplazarán las llamadas con `ok()` y `fail()`.

Ya migrados: `AudioController`, `EmergencyController`, `HomeController`, `CatalogosController`, `FrontDeskSessionController`, `SesionOperativaController`, `LicenciaController`, `QuirofanoController`, `CareProtocolController`, `InpatientEpicrisisTemplateController`, `PersonaController`, `RegistroController`, `ChatController`, `AuthController`, `AccionesController`, `PacientesController`, `ProfesionalAgendaController`, `TurnosController` y `LegalExportController`, además de los controllers clínicos del piloto. `LegalExportController` conserva PDF binario en la acción de descarga y usa el contrato en sus endpoints JSON. `ProfesionalAgendaController` usa `{data, meta}` en agenda diaria, listados y CRUD; la paginación se ubica en `meta.pagination`. El calendario web de ocupación, el catálogo de triage y las acciones de adelanto/preferencias de turnos también usan el contrato; el cliente paciente desempaqueta la aceptación de una oferta. `AccionesController` y sus consumidores SPA y móvil leen `data.actions` / `data.categories`; sus errores internos ya no se convierten en una respuesta 200 con forma de éxito. `EncounterCaptureContextService` usa excepciones tipadas del catálogo; los consumidores web preservan sus respuestas de UI y el bootstrap de captura mantiene su fallback de contexto operativo. Los filtros de autenticación y permisos también responden con el contrato común. En Auth, los errores de credenciales conservan un mensaje de autenticación distinto del error de sesión expirada. Los clientes móviles desempaquetan el contrato en login, activación, validación y renovación de sesión, en el panel de inicio, en el catálogo público de clases de consulta y en la configuración de sesión operativa; el wizard web de sesión también consume el contrato. El contacto administrativo de errores de configuración se conserva en `meta.contact`. La API de sesión de ventanilla no tiene clientes móviles ni UI web directa. Las rutas API de Auth no tenían consumidores web directos. La historia clínica comunica el código y los metadatos de las ventanas no disponibles dentro del error de contrato. El sobre de conversación del asistente se conserva como `data` dentro de la respuesta común. Las respuestas `ui_definition`, `ui_submit_result`, `agenda_config_preview` y las vistas `ui_json` conservan su protocolo propio; el login legacy y el webhook externo también mantienen su protocolo mientras no se migren sus consumidores.
