# Fase 05 — Geo, `Persona` y cierre del baseline

## Objetivo

Dos cosas distintas que conviene entender por separado:

1. **Sacar las 2 últimas fuentes de transporte** del repo (Geo y `Persona`).
2. **Apagar la red de seguridad**: vaciar el baseline para que el invariante pase sin allowlist.

## Tamaño estimado

**Medio (1 día).** No es "medio día" porque la parte B no es mover código: es arreglar un
método que hoy está roto y recién después moverlo.

---

## Parte A — Geo (mecánico)

`Domain/Geo/Application/Service/GoogleGeocodingClient` hace el HTTP dentro de `Application/`.
Moverlo a `Domain/Geo/Infrastructure/External/GoogleGeocoding/GoogleGeocodingApiClient` y
usar el base.

**Contrato que hay que preservar** (lo consume el resolver de direcciones):

- `isConfigured()` sigue leyendo la API key de params.
- Ante error de red o `status != OK` devuelve `null` y loguea `warning` en `geo.geocoding`.
- `ZERO_RESULTS` no genera warning (es un resultado válido, sólo que vacío).

Tests: respuesta OK, `ZERO_RESULTS` sin warning, HTTP 500 → `null` + warning, timeout → `null`.

---

## Parte B — `Persona` (acá está el trabajo real)

`Persona::existe_en_puco()` en `common/models/Person/Persona.php:692`, llamada desde
`admin/controllers/PersonasController.php:788` (acción AJAX del alta de persona, "PUCO").

Hace un `GET` a mano a `esalud.msaludsgo.gov.ar/seipa/web/api/coberturas?dni=…&sexo=1`, sin
headers, sin timeout y sin manejo de error.

### Por qué no es sólo "mover un curl"

Tres problemas, en orden de gravedad:

1. **En PHP ≥ 8 el caso normal explota.** El método devuelve `false` cuando la persona no
   está en PUCO, y el controller hace `count($persona_puco)`. En PHP 8, `count(false)` lanza
   `TypeError` (verificado: en 7.4 era un warning y devolvía 1). Lo mismo si la API está
   caída: `json_decode(false)` es `null` y `count(null)` también lanza. O sea: la consulta
   PUCO del alta **falla con error fatal** tanto en el caso "no tiene obra social" como con
   el servicio provincial caído.
2. **Sin timeout.** `curl_setopt_array` no define `CURLOPT_TIMEOUT`: una API provincial lenta
   cuelga la petición del operador sin límite.
3. **El documento se concatena crudo en la URL** (`'?dni=' . $documento`), sin `rawurlencode`.

### Qué hacer

- Crear `Domain/Person/<Modulo>/Infrastructure/External/Puco/PucoApiClient` sobre el base.
- Contrato explícito, que es lo que hoy falta:
  - `lookup(string $documento): ?array` → `null` = no está en PUCO, array = primer registro.
  - Error del proveedor → excepción; el controller decide qué mostrar.
- El modelo deja de saber de HTTP; el controller usa `count()` sólo sobre arrays.
- Tests: con obra social, vacío (`null`), API caída (excepción capturada), documento con
  caracteres no numéricos (que quede `rawurlencode`ado).

### Riesgo de comportamiento

Cambia el tipo de retorno (`false` → `null`). Es una acción de admin, no del alta pública,
pero conviene que quien opera el alta confirme que el mensaje "Tiene Obra Social …" sigue
apareciendo igual antes de cerrar la fase.

---

## Parte C — Cierre

- [ ] Vaciar `adHoc` y `applicationHttp` en `web/tools/architecture-http-legacy.json`.
- [ ] Borrar `LEGACY_AD_HOC` / `LEGACY_APPLICATION_HTTP` del test y el modo "solo puede bajar".
- [ ] Actualizar `Shared/Infrastructure/README.md` y `docs/arquitectura/common-components.md`.
- [ ] Revisar el hook `.github/hooks/http-guard.php`: ya no tiene rutas de deuda que exceptuar.
- [ ] `composer audit:thin-clients` sin contadores nuevos.
- [ ] Cerrar el plan: volcar la decisión de diseño a `docs/decisions/` y borrar esta carpeta
      (regla de `plans/`).

## Aceptación

- `ArchitectureHttpUsageTest` pasa **sin ninguna excepción**.
- `git grep "new Client("` sólo devuelve `Shared/Infrastructure/Http` y los tests.
- La consulta PUCO funciona en los tres casos (con obra social, sin obra social, API caída).

## Riesgos

- **Al vaciar el baseline, el test deja de tener margen**: cualquier archivo nuevo que use
  transporte directo rompe el build. Es el objetivo, pero hay que avisarle al equipo el mismo
  día que se mergea, no después.
- `Persona` es un ActiveRecord usado en toda la app: el método es `static` y sólo lo usa el
  admin, así que el riesgo de extraerlo es bajo — pero hay que verificarlo, no asumirlo.
