# Deduplicación de transporte HTTP

## Objetivo

Que **una sola** pieza del repo hable HTTP contra APIs externas: timeouts, reintentos,
idempotencia, parseo JSON, mapeo de error y logging viven en `Shared/Infrastructure/Http/`.
Cada proveedor conserva únicamente su payload, su autenticación y su mapeo de error.

Hoy hay **23 archivos** que construyen transporte a mano (25 `new Client(`, 4 `curl_init(`)
y **10 archivos** con su propio `getAccessToken`. Consecuencia: el timeout se configura en
4 lugares distintos, el header `Bearer` se arma 26 veces y no existe política de reintento.

## Alcance

- Cliente HTTP base + errores + reintentos + token OAuth compartido.
- Migración de los **16 archivos** registrados en `web/tools/architecture-http-legacy.json`.
- Bajar ese baseline a **0** y borrar la lista de deuda.

**Fuera de alcance:** claves de caché, literales de mensajes y verificación de firmas.
Son otras familias de duplicación y van en planes propios (ver [overview.md](overview.md)).

## Fases

| Fase | Qué | Riesgo | Estado |
|------|-----|--------|--------|
| [01](phases/01-base-http.md) | Base HTTP en `Shared/Infrastructure/Http` | Bajo: código nuevo, sin consumidores | Pendiente |
| [02](phases/02-billing-mercadopago.md) | Billing / Mercado Pago (dinero) | **Alto**: pagos y webhooks | Pendiente |
| [03](phases/03-oauth-client-credentials.md) | OAuth `client_credentials` ×3 (LIS, agenda FHIR, HC nacional) | Medio | Pendiente |
| [04](phases/04-platform-ai-push.md) | Platform IA + push + batch (8 archivos) | Medio: más superficie | Pendiente |
| [05](phases/05-geo-persona-cierre.md) | Geo, `Persona` y cierre del baseline | Bajo | Pendiente |

Cada fase es un PR independiente y deja el árbol verde. La única dependencia es la 01,
que habilita a las demás.

## Criterio de cierre

- `web/tools/architecture-http-legacy.json` con `adHoc` y `applicationHttp` vacíos.
- `ArchitectureHttpUsageTest` pasa sin allowlist.
- No queda `new Client(` ni `curl_init(` fuera de `Shared/Infrastructure/Http/` e
  `Infrastructure/External/`.
- Al cerrar: borrar esta carpeta y dejar la decisión de diseño en `decisions/`.

## Documentos

- [overview.md](overview.md) — inventario medido de duplicaciones y decisiones de alcance.
- [design.md](design.md) — contrato del cliente base y mapeo archivo por archivo.

## Reglas de la casa que aplican

- `common-components-organizacion`: `*/Application/` sin I/O de red; el ACL va en
  `Infrastructure/External/<Sistema>/`.
- `dry-solid`: regla de tres; transporte compartido; colaboradores por constructor.
