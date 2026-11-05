# Fase 04 — Platform IA, push y batch

## Objetivo

Que los 8 archivos de `Platform/` dejen de armar transporte. Es la fase con más superficie
y la que más gana: acá está `IAManager`, con 900 líneas y 4 headers `Bearer`.

## Tamaño estimado

**Grande (2 días).** Puede partirse en dos PR si se quiere: 04a IA (5 archivos) y
04b push (3 archivos).

## Archivos

| Archivo | Nota |
|---------|------|
| `Platform/Ai/IAManager` | El más grande. Conserva rate limiter y `AICostTracker`; el HTTP pasa al base |
| `Platform/Ai/HuggingFace/HuggingFaceBatchProcessor` | Batch |
| `Platform/Ai/Embeddings/EmbeddingsManager` | Orquesta + hace HTTP propio |
| `Platform/Ai/Embeddings/HuggingFaceEmbeddingsClient` | Base |
| `Platform/Ai/SpeechToText/HuggingFaceSttClient` | Base |
| `Platform/Ai/SpeechToText/GroqSttClient` | Hoy usa `curl_*` con timeout 120s |
| `Platform/Ai/Providers/Google/GoogleAuth` | `GoogleServiceAccountTokenProvider` |
| `Platform/Core/Service/Push/{FcmGoogleAuth,FcmHttpSender,PushNotificationSender}` | Token compartido + base |
| `Platform/Assistant/WhatsApp/WhatsAppCloudApiClient` | Conserva el reintento de número AR (`549…` → `54…`) |

## Tareas

- [ ] `GoogleServiceAccountTokenProvider` (JWT firmado, mismo contrato `token()/invalidate()`).
- [ ] Migrar `IAManager` conservando: validación de prompt, deduplicación exacta, cache Yii,
      rate limiter y registro de costos. **No** mover esa lógica al cliente.
- [ ] Migrar los clientes de IA que no tienen estado.
- [ ] Migrar push: un solo `FcmHttpSender` sobre el base; `PushNotificationSender` deja de
      construir su propio `Client`.
- [ ] Migrar WhatsApp conservando el fallback de número.
- [ ] Quitar los 8 archivos del baseline `adHoc`.
- [ ] Tests: al menos un caso de error por familia (IA, STT, push, WhatsApp).

## Aceptación

- `Platform/` no tiene `new Client(`, `curl_init(` ni `'Bearer '` armado a mano.
- `IAManager` mantiene su contrato público (`consultarIA`, etc.) sin cambios.
- El envío de push y WhatsApp devuelve `false` ante error, como hoy.

## Riesgos

- `IAManager` mezcla transporte, cache, costos y rate limiting. Es la tentación de
  "aprovechar" y refactorizar todo junto: **no**. Esta fase sólo cambia cómo se hace HTTP.
- Los timeouts no son uniformes (STT usa 120s). El base debe aceptarlos por constructor.
- `GroqSttClient` usa `curl_*` con opciones propias: verificar que la subida de audio
  (multipart) funcione con el base; si no, dejarlo fuera y anotarlo.

## Fuera de alcance

- Costos por IA, rate limiting y prompts: no se tocan.
