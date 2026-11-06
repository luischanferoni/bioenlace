# Overview — qué quedó afuera y por qué

Medición hecha al cerrar [transporte-http-unico](../../decisions/transporte-http-unico.md),
sobre `web/` completo (`common`, `frontend`, `admin`, `console`), sin `vendor`.

## 1. Transporte: por qué quedaron seis sitios

El plan anterior migró lo que el invariante podía ver. El invariante
(`ArchitectureHttpUsageTest`) recorre `@common/components` y `@common/models`, así que:

- **Dentro del alcance, pero bien ubicados.** `DiditClient`, `SnowstormClient` y `MpiApiClient`
  viven en `Infrastructure/External/`, que es un segmento permitido: el test los deja pasar y
  nadie los migró. No es un hueco del invariante, es trabajo pendiente.
- **Fuera del alcance.** `admin/` y `frontend/` nunca se escanearon. Ahí hay `yii\httpclient\Client`
  suelto en un controller de 30 requests, un request dentro de una **vista**, y `curl_*` en un
  controller con una IP fija y credenciales literales.

| Sitio | Señal | Volumen | Detalle |
|-------|-------|---------|---------|
| `FormController` | `new Client()` ×8, `->send()` ×30 | 30 requests | Alta y consulta de formularios dinámicos contra `params['hostFormsAPI']` (IP interna, puerto 9559): forms, secciones, preguntas, reglas, instancias, tipos, conteos |
| `views/form/form.php` | `new Client()` ×1 | 1 request | Arma opciones de un campo tipo *endpoint* consultando una URL externa durante el render; si falla, hace `echo` del aviso |
| `PersonasController::caller()` | `curl_init()` | 1 helper | `http://170.254.60.17/SumarApi/index.php/` con usuario y contraseña en el código; único consumidor `token()`, sin llamadores |
| `DiditClient` | `new Client()` ×2 (factory) | 4 requests | Verificación de identidad; cada respuesta se mapea a un resultado de dominio |
| `SnowstormClient` | `curl_init()` | 1 request | Terminología SNOMED; excepción genérica ante no-2xx |
| `MpiApiClient` | `curl_init()` | 1 request | SEIPA: empadronamiento, RENAPER, coberturas, domicilio; TLS sin verificar, verbo configurable, URL prod/test, fallas del proveedor dentro de un 200 |

## 2. Suite: qué falla y por qué

`codecept run unit` al cerrar el plan: **1236 tests, 4430 aserciones, 13 errores, 73 fallas,
2 skipped**. Todo el transporte nuevo está verde (32/96 en `shared`, y las 11 suites de ACL y
clientes migrados). Lo que falla es otra cosa, en cuatro familias.

### A. Andamio (errores de armado, no de producto)

| Causa | Errores | Ejemplo |
|-------|---------|---------|
| Esquema de la BD de test atrás de las migraciones | 3 | `password_reset_token` no existe |
| Campo requerido sin default en el modelo | 2 | `domicilio_verificacion_inicio` |
| `file_get_contents` a rutas que ya no existen | 2 | fixtures movidas |
| Tipo incorrecto en el llamador | 1 | `EncounterCaptureReviewPresenter::build(false)` |
| Propiedad inexistente / desconocida | 2 | `DeclarativePlanExecutionResult`, `Integrations` |
| Clase `final` mockeada | 1 | PHPUnit no puede mockear `final` |
| Excepción de dominio esperada distinto | 1 | `DomainOperationForbiddenException` |
| `InvalidArgumentException` de validación | 1 | "Elegí al menos un dato editable" |

Ninguna dice nada sobre el producto: dicen que el test está montado sobre supuestos que ya no
se cumplen.

### B. Guard de forma del árbol

| Causa | Fallas |
|-------|--------|
| Carpetas residuales `integrations/` en `models/`, `tests/unit/`, `controllers/` | 3 |
| `Application/Catalog/` en 3 BC no contemplado por el guard de capas | 4 |

### C. Expectativas de producto (necesitan decisión)

| Familia | Fallas | Clases representativas |
|---------|--------|------------------------|
| DataAccess y catálogos declarativos | 7 | `DataAccessEditDiscoveryTest` (5) |
| Prompts y ensamblado del asistente | 14 | `IntentSemanticsPromptFormatterTest`, `GuidePromptAssemblerTest`, `StateTagIndexTest`, `ChatPreprocessNormalizeV1Test` |
| Catálogos QA y routing conversacional | 6 | `SmartCatalogConversationalQaTest`, `PatientAssistantQaCatalogTest`, `IntentClassifierStaffAgendaEditTest` |
| Flows YAML y normalizadores clínicos | 12 | `EncounterFollowupFlowYamlTest`, `CareFollowupTouchpointsNormalizeTest`, `ReservaTriageServicioSugeridoTest` |
| Seeds y catálogos geográficos / de precios | 3 | `DddMigrationPhase0Test`, `AssistantMetadataPhase02Test` |
| Disperso | 15 | una o dos por clase |

## 3. Por qué las dos deudas van juntas

- La fase de transporte toca `admin/`, y el admin casi no tiene tests: la red de seguridad
  es el propio suite, que hoy no está verde. Sin suite verde no hay forma de saber si el
  cambio en `FormController` rompió el alta de formularios.
- Al revés: la familia "disperso" del suite incluye fallas que se destapan justamente al
  mover archivos (paths de fixtures, guard de carpetas).

Por eso el orden natural es 01 → 02 (transporte), 03 (andamio) y 04 (expectativas), y por eso
la 03 va antes que la 04: no se puede decidir si un test "quedó viejo" mientras el andamio
pueda ser la causa.

## 4. Lo que ya está resuelto y no se toca

- `Shared/Infrastructure/Http/`: cliente base, excepciones, reintentos, tokens OAuth y de
  service account, multipart. No se modifica en este plan salvo que un caso lo exija.
- Los 17 clientes migrados y sus tests.
- El invariante y el hook, más allá de ampliarles el alcance.

## 5. Riesgos de fondo

- **El invariante cubre poco.** Mientras escanee sólo `common/`, cualquier transporte nuevo
  en `admin/` o `frontend/` pasa inadvertido. La ampliación de alcance es parte del cierre,
  no un extra.
- **El admin es producción y no tiene suite.** Migrar `FormController` sin verificar a mano
  el alta de un formulario es apostar.
- **Ajustar tests para que pasen** en la fase 04 deja el suite verde y sin valor: cada
  aserción que se cambia necesita una razón que se pueda defender.
