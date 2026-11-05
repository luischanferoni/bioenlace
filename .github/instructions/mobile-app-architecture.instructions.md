---
description: "Arquitectura de las apps Flutter (paciente y personalsalud) — API única, paquete shared y textos"
applyTo: ['mobile/**/*.dart']
---

<!-- GENERADO por tools/sync-agent-rules.php — editar .cursor/rules/*.mdc -->

# Apps móviles (Flutter) — arquitectura

Dos apps (`paciente/`, `personalsalud/`) sobre un paquete compartido (`packages/shared/`).
El backend es Yii2 en `web/`; `arquitectura-yii2-bioenlace` aplica solo al tocar el contrato.
`dry-solid` aplica acá igual que en el backend.

## Fronteras

- **La API v1 es la única fuente de negocio.** La app no reproduce reglas clínicas ni de
  facturación: las pide a `/api/v1/…`. Nada de lógica de negocio duplicada en Dart.
- **HTTP solo por `packages/shared/lib/http/api_client.dart`** (contratos en
  `api_contract.dart`, trazas en `bioenlace_http_trace.dart`). No crear `http.Client()`
  ad-hoc por pantalla: es el equivalente móvil de `Shared/Infrastructure/Http`.
- **Código compartido → `packages/shared/`** (`lib/{auth,clinical,ui_json,text,http,…}`),
  exportado desde `shared.dart`. En `personalsalud/` y `paciente/` vive solo el wiring:
  navegación, `X-App-Client`, tokens y tema.
- **`ui_json`**: el descriptor es **dato**, no código. Los `custom_widget` se implementan en
  Dart en el cliente compartido. Ver `mobile-ui-json-custom-widgets`.

## Textos

- **Sin literales de usuario** en `Text()`, `SnackBar`, `AlertDialog` ni mensajes de error:
  van a `packages/shared/lib/text/` (`shared_texts.dart`, `user_friendly_error.dart`) o a los
  `app_texts.dart` de cada app.
- Los mensajes técnicos (excepciones internas, `debugPrint`, logs) pueden quedar inline:
  el usuario no los ve.
- `composer audit:thin-clients` (desde `web/`) cuenta estos literales y el contador solo baja.

## No

- Descargar ni ejecutar HTML/JS/CSS del descriptor como sustituto de widgets nativos.
- Enumerar pantallas o intents conocidos en código: eso viene del `ui_json` y de la metadata.
