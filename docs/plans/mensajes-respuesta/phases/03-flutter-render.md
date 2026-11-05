# Fase 03 — Flutter: renderizar, no inventar

## Objetivo

Que las apps muestren el `message` que llega en el payload. Los textos locales quedan sólo
para dos casos: validación de UX previa al envío y fallback cuando no hay respuesta del
backend.

## Tamaño

**Grande (2–3 días).** 55 `_snack(` en dos apps, con 5 usos actuales del helper.

## Lo que ya existe

`mobile/packages/shared/lib/text/`:

- `user_friendly_error.dart` → `apiMessageFromJson(dynamic json)` extrae `message` del cuerpo
  de error típico de la API.
- `shared_texts.dart` → avisos de infraestructura (biometría no disponible, verificación de
  identidad, etc.).
- `app_texts.dart` en cada app (`personalsalud/lib/infra/`, `paciente/lib/utils/`) → textos
  propios de cada producto.

**La pieza que falta no es infraestructura, es adopción:** el helper se usa en 5 lugares de 60.

## Tareas

- [ ] Inventariar los 55 `_snack(` y clasificarlos: mensaje de respuesta / validación de UX /
      etiqueta.
- [ ] Envolver la llamada a la API en un punto único por app que ya aplique
      `apiMessageFromJson` (probablemente el cliente HTTP compartido), para que el mensaje
      llegue resuelto al `catch` de la pantalla.
- [ ] Migrar `commercial_signup_screen.dart` (14 + 27) y `staff_signup_screen.dart` (9 + 16):
      es el 60 % del total y es el flujo de alta, donde un mensaje desactualizado cuesta caro.
- [ ] Los mensajes de validación previa al envío se quedan, pero el **texto** va a
      `shared_texts` / `app_texts`, no inline.
- [ ] Fallback: si no hay `message` en el payload, mostrar uno de `shared_texts`.
- [ ] Bajar `mobile_literal_ui_text` y `mobile_literal_ui_text_multiline` al número de
      etiquetas declarado en la fase 01.

## Aceptación

- Ningún `_snack(` con texto literal de respuesta.
- Un cambio de texto en `api-errors.yaml` se ve en la app sin tocar Dart.
- Las etiquetas estáticas siguen donde están (no se migran).

## Riesgos

- **Romper el flujo de alta** es el mayor riesgo del plan: es el mismo flujo que toca
  Mercado Pago (fase 02 del plan de transporte). Coordinar el orden entre planes.
- `apiMessageFromJson` devuelve `null` si el JSON no tiene la forma esperada: el fallback
  tiene que estar antes de mostrar, no después.

## Fuera de alcance

- Traducciones / i18n de etiquetas.
- Rediseño de los mensajes (sólo se mueve la fuente).
