# Transporte HTTP único

## Decisión

Todo el I/O contra APIs externas del repo pasa por **un solo cliente base** en
`Shared/Infrastructure/Http/`. Ningún módulo arma su propio transporte: timeouts, reintentos,
idempotencia, parseo, mapeo de error, log y tokens viven una sola vez.

Cada proveedor conserva **sólo lo suyo**: su `baseUrl`, su autenticación, su payload y, si el
sistema externo tiene otra forma de error, su `extractProviderError()`.

## Dónde va cada cosa

| Qué | Dónde |
|-----|-------|
| Transporte, reintentos, formatos de body, excepción de proveedor, log | `Shared/Infrastructure/Http/` |
| Token OAuth `client_credentials` y token de cuenta de servicio de Google | `Shared/Infrastructure/Http/Auth/` |
| Cliente de un sistema externo (ACL) | `Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/<Sistema>ApiClient` |
| Reglas de negocio, pricing, autorización | `Application/` y `Domain/` del BC |

**Prohibido** en `Application/` y en `Platform/`: `yii\httpclient\Client`, `curl_*` y
`file_get_contents` contra una URL. Un cliente de API externa es un ACL, no un servicio.

## Contrato del cliente

- **Respuesta**: código, headers normalizados y cuerpo JSON; el cuerpo crudo queda disponible.
  Los headers importan: hay conectores que resuelven un identificador desde `Location`.
- **Error**: una excepción con proveedor, código de estado, causas del proveedor y cuerpo. El ACL
  decide si devuelve `null`/`false`, traduce el texto o propaga.
- **Idempotencia**: sólo se reintenta un método idempotente o una request con clave de
  idempotencia, que se repite en todos los intentos. Un `POST` sin clave nunca se repite.
- **Log**: una entrada por request fallida, con categoría configurable por el ACL. Nunca viaja
  `Authorization`, ni el cuerpo de la request, ni los parámetros de query (pueden llevar claves).
- **Formatos**: JSON (lo serializa el base para no perder el `Content-Type` declarado),
  form-encoded, cuerpo crudo y multipart en memoria.

## Alternativas descartadas

- **Un cliente por proveedor con su propio transporte.** Es lo que había: cuatro timeouts
  distintos, ningún reintento y tres copias del flujo OAuth que ya habían divergido.
- **Reintentar todo `POST`.** Riesgo de doble cobro o doble envío clínico; se exige clave.
- **Transformar un error del proveedor en éxito vacío.** El ACL decide, el base no.
- **Un framework HTTP propio.** El alcance es lo que los consumidores necesitan hoy.

## Consecuencias

- El flujo OAuth está implementado una vez; un token vencido se resuelve con `invalidate()` y un
  reintento, no con una política por conector.
- La observabilidad es uniforme: "errores HTTP por proveedor" se responde con un formato.
- **No hay lista de deuda**: el invariante `ArchitectureHttpUsageTest` pasa sin allowlist y el
  hook `http-guard` bloquea la edición que introduzca transporte ad-hoc. Un archivo nuevo que
  lo necesite debe crear un ACL o usar el base.
- La única deuda de transporte que queda es la de un ACL que ya usa `curl_*` **dentro** de
  `Infrastructure/External/` (SEIPA), donde el invariante lo permite por ubicación.

## Verificación

| Qué | Cómo |
|-----|------|
| Invariante de arquitectura | `ArchitectureHttpUsageTest` (sin allowlist) |
| Bloqueo en edición | hook `http-guard` (misma regla, aplicada antes de escribir) |
| Tests del base | `common/tests/unit/shared/infrastructure/http/`, con transporte falso y sin red |
