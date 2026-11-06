# Transporte residual y suite

## Objetivo

Dos deudas que quedaron abiertas al cerrar el plan de transporte HTTP, y que conviene
tratar juntas porque las dos se descubrieron midiendo lo mismo (el árbol y el suite):

1. **Quedan clientes HTTP armados a mano**, y el invariante no los ve porque sólo mira
   `common/components` y `common/models`. Los tres ACL de `common` están bien ubicados (por
   eso el test pasa) pero nadie los migró; los de `admin/` están fuera del alcance del test.
2. **El suite no está verde.** Al cerrar el plan anterior quedó en *1236 tests, 13 errores,
   73 fallas, 2 skipped*. Ninguna de esas fallas es de transporte, pero mientras no se
   arreglen no hay señal confiable para el resto del trabajo.

## Alcance

- Migrar a `Shared/Infrastructure/Http/` todo lo que todavía use `yii\httpclient\Client` o
  `curl_*`, y **ampliar el invariante** para que cubra `admin/` y `frontend/`.
- Dejar el suite verde o, cuando la falla sea una decisión de producto, dejar documentado
  qué se espera en lugar de "arreglar" el test.

**Fuera de alcance:** los catálogos de mensajes ([mensajes-respuesta](../mensajes-respuesta/README.md))
y las claves de caché ([cache-claves-ttl](../cache-claves-ttl/README.md)). Si una falla del
suite resulta ser un mensaje literal mal ubicado, se anota ahí, no se resuelve acá. Tampoco
es un plan de rediseño del admin: se migra el transporte, no las pantallas.

## Fases

| Fase | Qué | Riesgo | Estado |
|------|-----|--------|--------|
| [01](phases/01-acls-residuales.md) | Didit, Snowstorm y MPI sobre el base | Medio: identidad y terminología en uso | Pendiente |
| [02](phases/02-transporte-en-admin.md) | `FormController`, la vista de formularios, `SumarApi`; ampliar el invariante a `admin/` y `frontend/` | Medio: toca el admin y expone transporte legacy de terceros | Pendiente |
| [03](phases/03-andamio-del-suite.md) | Esquema de la BD de test, guard de carpetas, `final` mockeables y errores de contrato | Bajo | Pendiente |
| [04](phases/04-expectativas.md) | Prompts, catálogos y flows: lo que exige una decisión de producto | Medio: se toca producto, no sólo tests | Pendiente |

Son independientes entre sí. La 02 depende de que exista el base (ya existe) pero no de la 01.
La 03 habilita a la 04: mientras el andamio esté roto, no se distingue una falla real de un
error de armado.

## Inventario (medido 2026-11-06)

El detalle de la medición, con las causas de cada falla y los riesgos de fondo, está en
[overview.md](overview.md). Resumen:

### Transporte residual

| Archivo | Señal | Nota | Fase |
|---------|-------|------|------|
| `Person/FrontDesk/Infrastructure/External/Identity/Connector/DiditClient` | 4 requests + factory de `Client` | Verificación de identidad (KYC); cada respuesta ya se mapea a un resultado de dominio | 01 |
| `Terminology/Infrastructure/External/Snowstorm/SnowstormClient` | `curl_*` | Terminología SNOMED; hoy lanza excepción genérica ante no-2xx | 01 |
| `Person/Identity/Infrastructure/External/Mpi/MpiApiClient` | `curl_*` | SEIPA (empadronamiento, RENAPER, coberturas, domicilio); **desactiva la verificación TLS**, usa verbo configurable y detecta fallas del proveedor envueltas en HTTP 200 | 01 |
| `admin/controllers/FormController` | 30 requests + 8 factories de `Client` | API de formularios dinámicos (`params['hostFormsAPI']`, IP interna): alta de form, secciones, preguntas, reglas, instancias y conteos | 02 |
| `admin/views/form/form.php` | 1 request en la **vista** | Trae las opciones de un campo desde una URL externa mientras arma el formulario; si falla, imprime el aviso desde la vista | 02 |
| `admin/controllers/PersonasController::caller()` | `curl_*` | `SumarApi` sobre HTTP plano a una IP fija, con credenciales en el código; el único método que lo usa (`token()`) no se invoca desde ningún lado | 02 |

**Brecha del invariante:** `ArchitectureHttpUsageTest` escanea `@common/components` y
`@common/models` y nada más, y el hook `http-guard` sólo cubre `web/common/{components,models}`.
Por eso el admin entero pudo quedar afuera. La fase 02 cierra la brecha, no sólo los casos.

### Suite, por causa

| Causa | Fallas | Fase |
|-------|--------|------|
| Esquema de la BD de test atrás de las migraciones (`password_reset_token`, `domicilio_verificacion_inicio`) | 5 errores | 03 |
| Carpetas residuales `integrations/` en `models/`, `tests/unit/` y `controllers/` (guard de árbol espejo) | 3 | 03 |
| `Application/Catalog/` fuera de los roles de capa en 3 BC | 4 | 03 |
| Clases `final` mockeadas con PHPUnit | 1 error | 03 |
| Errores de contrato entre test y código (`EncounterCaptureReviewPresenter::build(false)`, propiedad inexistente en `DeclarativePlanExecutionResult`, propiedad desconocida de `Integrations`, `file_get_contents` a rutas que ya no existen, excepción de dominio no esperada) | 7 errores | 03 |
| DataAccess y catálogos declarativos | 7 | 04 |
| Prompts y ensamblado del asistente (guide, preprocess, semantics, state tags) | 14 | 04 |
| Catálogos QA del asistente y routing conversacional | 6 | 04 |
| Flows YAML y normalizadores clínicos | 12 | 04 |
| Seeds y catálogos geográficos / de precios | 3 | 04 |
| Resto (una o dos fallas por clase, sin familia clara) | 15 | 04 |

Los conteos son de una corrida concreta de `codecept run unit`; cambian con cualquier cambio
de producto. Cada fase vuelve a medir antes de empezar.

## Criterio de cierre

- `git grep` de `new Client(` y `curl_init(` sólo devuelve `Shared/Infrastructure/Http/` y los
  tests, en **todo** `web/`, no sólo en `common/`.
- `ArchitectureHttpUsageTest` escanea también `admin/` y `frontend/`, y sigue verde.
- `codecept run unit` en verde, o cada falla restante documentada como expectativa de
  producto con su motivo.
- Al cerrar: el criterio de "transporte en ACL" se suma a
  [transporte-http-unico.md](../../decisions/transporte-http-unico.md) y esta carpeta se borra.
