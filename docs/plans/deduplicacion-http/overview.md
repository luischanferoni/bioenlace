# Overview — inventario de duplicaciones

Medición sobre `web/` (sin `vendor/` ni `tests/`): 1360 archivos PHP escaneados. Los conteos
son de ocurrencias de texto: sirven para dimensionar, no como métrica exacta.

Para cada familia se aclara **qué se rompe**, porque no todo lo duplicado está "mal escrito":
el problema es que la repetición ya produjo comportamientos distintos.

## 1. Transporte HTTP — cada cliente inventa su timeout, y nadie reintenta

| Señal | Archivos | Ocurrencias |
|-------|----------|-------------|
| `new Client(` | 16 | 25 |
| `curl_init(` | 4 | 4 |
| `file_get_contents('http…')` | 1 | 1 |
| `'Bearer '` (header armado a mano) | 16 | 26 |
| `CURLOPT_TIMEOUT` | 4 | 4 |
| `http_build_query(` (query armada a mano) | 5 | 7 |

De los 23 archivos con transporte directo, **7 ya están bien ubicados** en
`Infrastructure/External/`: el problema ahí no es el lugar, es el transporte reinventado.

**Qué se rompe:** el timeout se define en 4 lugares con criterios distintos y la mayoría no
define ninguno (una API caída cuelga el request). No hay reintento en ningún cliente, aunque
Mercado Pago ya manda `X-Idempotency-Key` y podría reintentarse sin duplicar nada. Y ningún
cliente lee headers de respuesta, que el conector de HC nacional necesita (`Location`).

## 2. Token — tres copias del mismo OAuth, con tres comportamientos

`function getAccessToken` aparece en 10 archivos, con 4 familias. La que importa:

| Conector | Flujo | Cachea entre requests | Se recupera de un `401` |
|----------|-------|-----------------------|--------------------------|
| Sianlabs (LIS) | `client_credentials` | No (sólo mientras vive el objeto) | No |
| NIS FHIR (agenda) | `client_credentials` | No | No |
| HC nacional | `client_credentials` | Sí (`Yii::$app->cache`, TTL) | Sí (invalida y reintenta) |

**Qué se rompe:** no es que estén mal —los tres funcionan— es que **divergieron**. Dos piden
token de más en cada llamada y, cuando el token vence a mitad de la jornada, fallan con `401`
hasta que expira el proceso; el tercero, que sí lo resuelve, lo resolvió por su cuenta. Es
exactamente el tipo de arreglo que cada uno vuelve a inventar en el próximo conector.

Las otras familias (Google service account en `GoogleAuth`/`FcmGoogleAuth`, GCP ADC en
`GcsSimpleUploader`/`VertexBatchPredictionClient`, API key en `IAIManager`) van en la fase 04
con el mismo contrato `token()/invalidate()`.

## 3. Log de error HTTP — siete formas de contar lo mismo

`Yii::error([...])` estructurado en 7 archivos / 9 usos, todos con la misma forma conceptual
(`url`, `status`, `body`) y ninguno igual al otro: unos loguean `url`, otros `path`; unos
truncan el body, otros no; sólo Mercado Pago extrae `causes`; cada uno usa una categoría
distinta.

**Qué se rompe:** no se puede responder "¿cuántos errores HTTP tuvimos por proveedor esta
semana?" sin abrir siete formatos. Y no es un problema de estilo: es observabilidad perdida.
El cliente base lo emite de fábrica, con truncado uniforme y sin loguear `Authorization`.

## 4. Mensajes de respuesta — el cliente inventa lo que el backend ya decidió

Este no es "hay textos repetidos". Es que **el mensaje de respuesta se define en dos lados**:
el backend lo emite y el cliente tiene su propia copia (o directamente lo inventa).

El patrón se repite en tres lugares:

| Dónde | Evidencia | Ejemplo |
|-------|-----------|---------|
| Controllers API | 28 literales | `'message' => 'Turno cancelado'`, `'Asistencia confirmada'` |
| Apps Flutter | 25 + 45 (multilínea) | `_snack('Elegí el centro de salud al que pertenecés.')` en el alta |
| JS web legacy | 38 | toasts propios en `pacientes-listing` |

**Lo que ya existe:** `Platform/Core/Http/Contract/ApiErrorCatalog` + `api-errors.yaml` —
"la única fuente de textos de error de la API". El andamio está; falta que los controllers lo
usen y que los clientes dejen de tener su copia.

**Qué queda fuera de alcance:** las etiquetas estáticas de UI (títulos, botones). El contador
del auditor las mezcla con los mensajes de respuesta, y son problemas distintos (i18n/diseño
vs. contrato). Al abrir el plan hay que separarlas, porque el baseline actual suma todo.

Propuesta: **plan propio** ([mensajes-respuesta](../mensajes-respuesta/README.md)), con 3
frentes (controllers → catálogo, Flutter → renderizar lo que llega, JS → idem). Es el trabajo
más grande del inventario y no debe mezclarse con transporte.

## 5. Verificación de firmas — a evaluar

`hash_equals(` en 5 archivos / 9 usos: Mercado Pago, webhook de WhatsApp, Passkey (WebAuthn),
captcha de demo sandbox y `TurnosController`. No hay evidencia todavía de que compartan
formato: MP y WhatsApp sí parsean headers tipo `ts=…,v1=…`, Passkey es otro protocolo.

Ver [a-evaluar/firmas-webhook.md](../a-evaluar/firmas-webhook.md).

## 6. Descartadas

| Señal | Evidencia | Por qué se descarta |
|-------|-----------|---------------------|
| Claves de caché ad-hoc | `md5(` en 14 archivos / 19 usos | No hay dos iguales; antes que duplicación es falta de política de caché. Tiene [plan propio](../cache-claves-ttl/README.md) |
| Validación de URLs | `FILTER_VALIDATE_URL` en 1 archivo | No hay duplicación |

## 7. Precedentes que sirven de modelo

- **`MigrationEnumColumn`** (`Shared/Infrastructure/Migration`): 13 migraciones usan el mismo
  helper y ninguna rearma el DDL. Es la forma que buscamos para HTTP.
- **`RequestDeduplicator`** (cambio reciente): el match difuso se degradó a observabilidad en
  vez de devolver contenido. Mismo principio para el cliente base: no reintentar lo que no es
  idempotente, no "optimizar" silenciosamente una respuesta.

## Cómo medir el avance

```bash
cd web
C:\xampp8\php\php.exe tools/audit-thin-clients.php --report   # requiere PHP >= 8
vendor/bin/codecept run unit platform/core/ArchitectureHttpUsageTest.php
```

`web/tools/architecture-http-legacy.json` es la única fuente del avance: cada fase quita sus
archivos y el test falla si la lista menciona algo ya limpio.

## Por qué fases y no un solo PR

El riesgo no es técnico, es de dominio: **Billing mueve dinero**. Validar el diseño del base
contra Mercado Pago obliga a que soporte idempotencia, headers de respuesta y errores con
`cause[]` antes de tocar los otros 15 archivos. Si el diseño falla, se descubre en la fase 02
y no en la 04 con 8 archivos ya migrados.
