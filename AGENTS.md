# Bioenlace — instrucciones para agentes

Monorepo: `web/` (Yii2 / PHP ≥ 8.1 + API v1), `mobile/` (Flutter), `institucional/` (sitio estático).

## Dónde va cada cosa

| Carpeta | Qué es |
|---|---|
| `web/` | La aplicación: Yii2, API v1, `common/components` |
| `mobile/` | Las apps Flutter: `personalsalud/`, `paciente/`, `packages/shared/` (código compartido) |
| `institucional/` | Sitio estático público |
| `docs/` | Documentación del **producto y la empresa** (no de un cliente): producto, arquitectura, ADRs, negocio, QA, docs de mobile en `docs/mobile/` |
| `tools/` | Tooling **del repo** (no de un producto): generador de reglas de agente |
| `web/tools/` | **Gates** de la app: análisis con exit code y baseline. Ver su README |
| `web/scripts/` | **Generadores** que producen artefactos (favicons, wireflow) |
| `.github/hooks/` | Hooks del agente; el script vive junto a su JSON |

## Fuente única de reglas

Las reglas de ingeniería viven en **`.cursor/rules/*.mdc`**. Para agentes que no leen formato
Cursor (VS Code Copilot, CLI) se publican en **`.github/instructions/*.instructions.md`**,
que son **generados** — no se editan a mano:

```bash
php tools/sync-agent-rules.php          # regenerar tras tocar .cursor/rules
php tools/sync-agent-rules.php --check  # exit 1 si están desincronizados
```

Los `.instructions.md` usan `applyTo`, así que cada regla se adjunta sola cuando se editan
archivos de su zona. No hace falta leerlas todas.

## Mapa: qué regla aplica según la tarea

| Zona / tarea | Regla |
|---|---|
| Siempre (transversal) | `capas-y-metadata-sin-hardcode`, `dry-solid`, `source-of-truth-catalogos`, `fuente-unica-valores-derivados`, `configuracion-sin-fallbacks`, `runtime-datos-vs-metadata`, `metadata-yaml-uso`, `servicio-institucional-vs-pes-acto`, `bd-solo-migrations` |
| `web/**` | `arquitectura-yii2-bioenlace` |
| `web/common/components/**`, `web/common/models/**` | `common-components-organizacion` |
| `web/common/components/Domain/**` | `ddd-un-eje-por-nivel` |
| Vistas y controllers web | `web-vistas-yii-delgadas`, `web-frontend-camino-a-nativo`, `web-bootstrap-antes-que-css-custom`, `ui-nativas-embebibles-spa-shell` |
| `web/frontend/modules/api/v1/**` | `api-v1-acciones-permisos-y-rutas`, `api-v1-autenticacion-y-sesion` |
| Asistente / IA (intents, prompts, routing) | `asistente-intents-frontera-sin-solape`, `asistente-routing-sin-hardcode`, `asistente-prompts-sin-casos-particulares`, `asistente-prompts-solo-humano`, `asistente-lectura-generica` |
| `web/common/migrations/**` | `bd-solo-migrations`, `migrations-enum-columnas` |
| `docs/**` | `documentacion-estilo-y-enlaces`, `documentacion-estructura-dominios`, `documentacion-flows` |
| `mobile/**` | `mobile-ui-json-custom-widgets`, `mobile-app-architecture` |

Detalle de arquitectura: [docs/arquitectura/](./docs/arquitectura/) —
`common-components.md`, `arbol-espejo-dominios.md`, `contrato-api.md`.

## Invariantes (resumen; el detalle está en las reglas)

- **Tres capas** en `web/common/components/`: `Platform/` (motores), `Shared/` (infra técnica),
  `Domain/` (rubro). No existe `Domain/Platform` ni `Domain/Shared`.
- **`*/Application/` sin I/O de red.** Nada de `yii\httpclient`, `curl_*` ni
  `file_get_contents('http…')`. Un cliente de API externa es un ACL en
  `Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/<Sistema>ApiClient.php`.
- **Transporte compartido.** Timeouts, retry, idempotencia, mapeo de error y log viven en
  `Shared/Infrastructure/Http/`. No reimplementar por proveedor: si aparece un tercer
  `try/catch` de transporte igual, se extrae.
- **DRY/SOLID.** Regla de tres para extraer, y al lugar correcto (tabla en `dry-solid`);
  una razón de cambio por clase; colaboradores por constructor, no `new` adentro del método.
- **Valores derivados.** Capacidad, presets, límites, políticas y textos que dependen de otro
  valor se **calculan desde la fuente** (catálogo/loader). No se copian como literales, ni como
  fallback que contradice la fuente, ni como default en un servicio o en un cliente.
  Detalle: `fuente-unica-valores-derivados`.
- **Base de datos solo por migraciones.** Esquema y datos persistentes → `web/common/migrations/`.
  Los `.sql` de la raíz son muestras de solo lectura; la conexión local se usa para inspeccionar
  estructura/datos y probar migraciones, no para `ALTER`/`INSERT`/`UPDATE` manuales.
- **API v1** es la única fuente de negocio: web Yii y móvil la consumen, no la duplican.
- **Prohibiciones por capa**: catálogo cerrado de 8 filas en
  `common-components-organizacion.mdc` (Application sin red, controllers sin persistencia,
  Domain sin I/O, motores sin casos hardcodeados, …). Cada fila indica su enforcement.

## Enforcement (determinista)

Las instrucciones guían; **no garantizan**. Lo que garantiza son las consecuencias:

```bash
git config core.hooksPath .githooks   # una vez por clon
```

- **`.githooks/pre-commit`** → `php -l` de los PHP en stage, `agents:check-rules` y
  `ArchitectureHttpUsageTest`. Escape puntual: `git commit --no-verify`.
- **`.github/hooks/http-guard.json`** (`preToolUse`) → deniega editar un archivo de
  `web/common/{components,models}` que introduce `yii\httpclient`, `curl_*` o
  `file_get_contents('http…')` fuera de un ACL o de `Shared/Infrastructure/Http/`.
  Es un hook, no una instrucción: actúa aunque el agente "se olvide" de la regla.
- **Un solo transporte HTTP.** Todo I/O contra APIs externas pasa por `Shared/Infrastructure/Http/`
  (cliente base, reintentos, tokens). Un cliente de proveedor es un ACL en
  `Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/`. `ArchitectureHttpUsageTest` lo
  verifica **sin allowlist** y el hook `.github/hooks/http-guard.json` lo bloquea al editar.
  Decisión: [transporte-http-unico.md](docs/decisions/transporte-http-unico.md).

## Build & test

```bash
cd web
composer install

cd common                                                  # la config vive en common/codeception.yml
../vendor/bin/codecept build                               # tras cambiar suites/actores
../vendor/bin/codecept run unit                            # suite completa
../vendor/bin/codecept run unit shared/infrastructure/http # base HTTP compartido
../vendor/bin/codecept run unit platform/core/ArchitectureHttpUsageTest.php  # invariante HTTP

cd .. && composer audit:thin-clients                       # ratchet de deuda
php tools/sync-agent-rules.php --check                     # reglas sincronizadas
```

El CLI tiene que ser **PHP ≥ 8.2** con `curl` y `mbstring`: el lock resuelve contra
`config.platform.php` = 8.3.30 y `web-auth/webauthn-lib` exige `>= 8.2`, así que con 8.1
el autoload aborta en `platform_check.php`. Si `php -v` en PATH no es ese binario, apuntá
al correcto (p. ej. `C:\laragon\bin\php\php-8.3.x\php.exe`).

La primera corrida necesita `common/config/test.php` (local, no versionado). Debe declarar
`id`, `basePath` y el componente `user` con `class` = `frontend\components\UserConfig`
(hereda `BaseUserConfig`, que aporta `getIdPersona`/`getIdEfector`): sin eso, todo test que
toque `Yii::$app->user` falla con "unknown method". Los tests de DB esperan la base de
`common/config/test-local.php`.
