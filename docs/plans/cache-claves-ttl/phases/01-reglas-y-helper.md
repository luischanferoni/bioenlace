# Fase 01 — Reglas y helper `CacheStore`

## Objetivo

Existir `Shared/Infrastructure/Cache/CacheStore` con la política única, y dejar escritas las
reglas: namespace de claves, TTL con nombre, invalidación por revisión.

## Tamaño

**Medio (1 día).** Código nuevo con tests; no migra consumidores.

## Tareas

- [ ] `CacheStore` en `Shared/Infrastructure/Cache/` con `remember`, `forget`, `revision`,
      `bumpRevision`, `isAvailable`.
- [ ] `TTL` como catálogo de constantes con nombre y comentario (`TOKEN`, `EMBEDDING`,
      `CATALOG`, `RESOLUTION`, `EPHEMERAL`).
- [ ] Sentinel interno para resultados vacíos (reemplaza el `'__empty__'` manual).
- [ ] Degradación limpia si `Yii::$app->cache` es `null`.
- [ ] Tests: hit, miss, vacío cacheado, invalidación por revisión, cache nulo, TTL respetado.
- [ ] Regla en `.cursor/rules/` (`cache-store.mdc`): prohibido `md5(...)` como clave y números
      sueltos como TTL en código nuevo.

## Aceptación

- `CacheStore` pasa sus tests sin red ni BD.
- La regla está publicada a `.github/instructions` (`php tools/sync-agent-rules.php`).
- Ningún consumidor migrado todavía: el árbol queda igual de verde.

## Riesgos

- **Sobre-abstraer**: no agregar `tags`, `stampede protection` ni `locks` hasta que un caso
  concreto lo pida.
- La revisión por namespace debe leer/escribir con el mismo mecanismo que
  `BioenlaceRbacRevision` para no tener dos contadores.
