# Overview — inventario de caché

Medición sobre `web/common/components`, `web/common/models`, `web/frontend/modules/api`,
`web/console` y `web/frontend/controllers` (1375 archivos PHP, sin `vendor` ni `tests`).

## Los 17 archivos

| Archivo | Qué cachea | TTL |
|---------|-----------|-----|
| `Clinical/Capture/Infrastructure/Persistence/CaptureAnalysisCache` | análisis de captura | `self::TTL_SECONDS` |
| `Clinical/HistoryExchange/…/HttpNationalClinicalHistoryConnector` | token OAuth | `max(60, ttl-60)` |
| `Organization/Efector/Application/Service/SignupAbuseGuard` | contador de intentos | `3600` |
| `Terminology/Application/Service/SnomedCommonTermsCacheService` | embeddings de términos | **`315360000` (10 años)** |
| `Platform/Ai/Embeddings/EmbeddingsManager` | embeddings | `self::CACHE_TTL` |
| `Platform/Ai/IAManager` | token + respuesta IA | `expiresIn` / `ia_cache_ttl` |
| `Platform/Ai/SpeechToText/SpeechToTextManager` | transcripción (2 claves) | `self::CACHE_TTL` |
| `Platform/Ai/Providers/Google/GoogleAuth` | token SA | `expiresIn` |
| `Platform/Assistant/Catalog/AssistantShortcutGroupRegistry` | snapshot | `self::CACHE_TTL` + `delete` |
| `Platform/Assistant/Catalog/YamlIntentCatalogService` | catálogo YAML | **`300` hardcodeado** |
| `Platform/Assistant/UiActions/ActionDiscoveryService` | acciones (2 claves) | `self::CACHE_DURATION` |
| `Platform/Assistant/UiActions/ActionMappingService` | acciones por usuario | `CACHE_DURATION` + `delete` por usuario |
| `Platform/Assistant/UiActions/AllowedRoutesResolver` | rutas (2 claves) | `CACHE_DURATION` |
| `Platform/Core/Auth/DemoSandboxCaptchaService` | captcha | inline + `delete` |
| `Platform/Core/Permission/BioenlaceRbacRevision` | contador de revisión | **`0` = sin expiración** |
| `Platform/Core/Permission/RbacFreeRouteChecker` | rutas libres | `3600` |
| `Platform/Core/Service/Push/FcmGoogleAuth` | token SA | `max(60, expiresIn)` |

## Cómo se agrupan

### A. Caché de credencial (4)

Token OAuth / service account de HC nacional, `IAManager`, `GoogleAuth`, `FcmGoogleAuth`.
Mismo problema, cuatro cálculos de TTL. **Duplicación real**: la resuelve el
`AccessTokenProvider` del plan de transporte.

### B. Caché de cómputo caro (5)

Embeddings (`EmbeddingsManager`, `SnomedCommonTermsCacheService`), transcripción
(`SpeechToTextManager`), respuesta IA (`IAManager`), análisis de captura
(`CaptureAnalysisCache`). Acá el problema no es la duplicación —son datos distintos— sino que
**el TTL no tiene criterio**: 10 años para SNOMED junto a `CACHE_TTL` sin justificar en los
otros. Un embedding de un término es estable; una respuesta de IA no. Deberían tener políticas
distintas y explícitas.

### C. Caché de resolución (5)

`ActionDiscoveryService`, `ActionMappingService`, `AllowedRoutesResolver`,
`AssistantShortcutGroupRegistry`, `YamlIntentCatalogService`. Todos cachean el resultado de
leer metadata/RBAC. Dos se invalidan con `delete`, uno con TTL de 300s sin invalidación, y
ninguno consulta la revisión de RBAC.

### D. Estado efímero (3)

`SignupAbuseGuard` (contador), `DemoSandboxCaptchaService`, `BioenlaceRbacRevision`. Son
cachés de estado, no de cómputo: el TTL es semántico y no debería compartir constante con los
anteriores.

## Diseño propuesto: `Shared/Infrastructure/Cache/CacheStore`

```php
final class CacheStore
{
    public function remember(string $namespace, string $key, int $ttl, callable $producer);
    public function forget(string $namespace, string $key): void;
    public function revision(string $namespace): int;      // invalidación masiva
    public function bumpRevision(string $namespace): void;
    public function isAvailable(): bool;                    // cache puede ser null en consola
}
```

Decisiones:

- **Clave:** `bioenlace:<namespace>:<rev>:<key>`. El namespace es el tema (`ia.embeddings`,
  `auth.token`, `rbac.routes`), no el nombre de la clase.
- **`remember()` null-safe:** guarda también resultados vacíos con un sentinel interno, así
  desaparece el `'__empty__'` de `AllowedRoutesResolver`.
- **TTL obligatorio y con nombre**: `TTL::TOKEN`, `TTL::EMBEDDING`, `TTL::CATALOG`. Nada de
  números sueltos.
- **Revisión por namespace**: generaliza lo que hoy sólo hace `BioenlaceRbacRevision`.
  Invalidar es `bumpRevision('rbac.routes')`, no recorrer claves.
- **Nunca cachea `null` como "no existe"** sin distinguirlo: `remember()` devuelve lo que
  produjo el callable y guarda el vacío con marca.

## Riesgos

- **`Yii::$app->cache` puede ser `null`** (consola, tests). Todo el helper debe degradar a
  "sin caché" y no romper.
- Cambiar claves **invalida el caché actual** al desplegar. En tokens eso es benigno; en el
  catálogo YAML y en RBAC implica un pico de recálculo. Se puede mitigar aceptando un
  `legacyKey` opcional durante la migración.
- `SnomedCommonTermsCacheService` con 10 años: si algún día cambia el modelo de embeddings,
  hay que invalidar a mano. La revisión por namespace lo resuelve.
