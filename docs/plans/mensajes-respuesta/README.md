# Mensajes de respuesta — una sola fuente

## Objetivo

Que el texto que ve el usuario **lo decida el backend**, y que el cliente sólo lo renderice.
Hoy el mismo mensaje existe dos veces: el backend lo emite y el cliente tiene su copia (o lo
inventa). Cuando cambia el del backend, el del dispositivo queda viejo; cuando el cliente
decide por su cuenta, la regla vive en el lugar equivocado.

## El contrato ya existe

`Platform/Core/Http/Contract/api-errors.yaml` — **136 códigos**, cada uno con `http_status`,
`intent`, `retryable` y `message` (con placeholders `{nombre}`). Su encabezado dice:

> Catálogo único de errores de la API v1. **Los clientes muestran `message` tal cual.**

Ese es el contrato. El plan no inventa nada: **lo hace cumplir**. `ApiErrorCatalog` y
`ApiResponder` ya existen y hoy los usan 9 archivos.

## El desvío, medido

| Dónde | Evidencia | Ejemplo |
|-------|-----------|---------|
| Controllers API | 28 literales en **16** controllers | `TurnosController:1974` → `'message' => 'Turno cancelado'` |
| Apps Flutter | **55** `_snack(` con texto propio; `apiMessageFromJson` usado en **5** lugares | `staff_signup_screen:255` → `_snack('Elegí el centro de salud al que pertenecés.')` |
| JS web legacy | 38 literales | toasts de `pacientes-listing` |

El caso de Flutter es el más elocuente: **el mecanismo para mostrar el `message` del backend
ya existe** (`user_friendly_error.dart`) y se usa en 5 de 60 lugares.

## Qué **no** es este plan

**Las etiquetas estáticas de UI** (títulos, botones, placeholders) no son el problema: son
i18n y diseño. El contador del auditor (`mobile_literal_ui_text`, `web_js_literal_ui_text`)
mezcla "mensaje de respuesta" con "etiqueta", así que **sobreestima el alcance**. La fase 01
separa el contador antes de tocar nada.

## Fases

| Fase | Qué | Estado |
|------|-----|--------|
| [01](phases/01-regla-y-medicion.md) | Regla de qué es un mensaje de respuesta + separar el contador | Pendiente |
| [02](phases/02-api-catalogo.md) | API: los 28 literales van al catálogo | Pendiente |
| [03](phases/03-flutter-render.md) | Flutter: renderizar `message`, no inventarlo | Pendiente |
| [04](phases/04-js-y-cierre.md) | JS web y cierre de baselines | Pendiente |

## Criterio de cierre

- `api_controller_literal_messages` = **0**.
- Ningún `_snack(` / toast nuevo con texto de respuesta literal: el texto viene del payload.
- Los contadores de literales de cliente reflejan **sólo etiquetas**, con un número declarado
  y en baja.
- Al cerrar: la regla queda en `decisions/` y esta carpeta se borra.

## Dependencias

Ninguna con `deduplicacion-http`. Son independientes: éste es contrato de mensajes, aquél es
transporte.
