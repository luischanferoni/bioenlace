# Fase 03 — Derivados de IA (embeddings, STT, respuesta, captura)

## Objetivo

Que los cinco cachés de cómputo caro usen `CacheStore`, con TTL explícito según la
estabilidad del dato — no una constante copiada entre clases.

## Tamaño

**Medio (1 día).**

## Sitios y política propuesta

| Archivo | Dato | Estabilidad | TTL propuesto |
|---------|------|-------------|---------------|
| `SnomedCommonTermsCacheService` | embeddings de términos SNOMED | Muy alta (cambia con el modelo) | `TTL::EMBEDDING` largo + revisión por namespace |
| `EmbeddingsManager` | embeddings genéricos | Alta | `TTL::EMBEDDING` |
| `SpeechToTextManager` (2 claves) | transcripción de audio | Media (el mismo audio da el mismo texto) | `TTL::AUDIO_TRANSCRIPT` |
| `IAManager` (~689/869) | respuesta de IA | **Baja** | `TTL::IA_RESPONSE` (respeta `ia_cache_ttl` de params) |
| `CaptureAnalysisCache` | análisis de captura | Baja | `TTL::CAPTURE_ANALYSIS` |

La idea no es inventar números: es que **cada TTL tenga un nombre que explique por qué**.
Hoy convive un `315360000` (10 años) con un `CACHE_TTL` sin justificar.

## Tareas

- [ ] Reemplazar `md5(...)`/concatenación de claves por `CacheStore::remember()`.
- [ ] Definir `TTL::EMBEDDING` / `TTL::IA_RESPONSE` / `TTL::AUDIO_TRANSCRIPT` /
      `TTL::CAPTURE_ANALYSIS` con comentario de criterio.
- [ ] Invalidación por revisión en embeddings: `bumpRevision('ia.embeddings')` cuando cambie
      el modelo o el proveedor, en lugar de confiar en 10 años de TTL.
- [ ] Mantener el respeto a `ia_cache_desactivado` en `IAManager` (ya corregido: gatea también
      la deduplicación).
- [ ] Tests: hit/miss por cada sitio y que la revisión invalide.

## Aceptación

- Ninguna de las 5 clases calcula su clave a mano.
- Existe un solo lugar que decide `TTL::EMBEDDING` (antes eran dos valores distintos).
- Cambiar de modelo de embeddings tiene un camino de invalidación documentado.

## Riesgos

- **Invalidar embeddings es caro**: recalcular todo de golpe puede saturar al proveedor. La
  revisión debe poder aplicarse por namespace acotado (p. ej. sólo términos nuevos).
- `IAManager` es el archivo más delicado del repo en cuanto a IA: esta fase sólo cambia el
  almacenamiento, no la lógica de decisión.
