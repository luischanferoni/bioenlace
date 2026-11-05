# Fase 02 — API: los literales van al catálogo

## Objetivo

Que ningún controller de la API invente un mensaje: todos los textos de respuesta salen de
`api-errors.yaml` vía `ApiErrorCatalog` / `ApiResponder`.

## Tamaño

**Medio (1–2 días).** 28 mensajes en 16 controllers; el trabajo es mecánico pero hay que
preservar el texto exacto la primera vez.

## Tareas

- [ ] Listar los 28 literales con su contexto (archivo, línea, cuándo se devuelve).
- [ ] Para cada uno, decidir: **ya existe un código en el catálogo** (usarlo) o **falta un
      código** (agregarlo al YAML).
- [ ] Migrar `scheduling/TurnosController` (4), el de mayor volumen.
- [ ] Migrar los 5 controllers clínicos (12 mensajes).
- [ ] Migrar el resto (12).
- [ ] Bajar `api_controller_literal_messages` a 0 en el baseline.
- [ ] Test: una respuesta de cada controller migrado devuelve un `code` del catálogo.

## Regla de migración

1. **Primero el texto idéntico**: el mismo string, ahora leído del catálogo. Nada de
   "aprovechar" para reescribir. Si el texto cambia, es otro PR con otra revisión.
2. Si el mensaje depende de datos (`"Se canceló el turno de {paciente}"`), usar el mecanismo
   de placeholders del YAML, no concatenar en PHP.

## Aceptación

- `api_controller_literal_messages` = 0.
- El JSON de error de cualquier controller migrado tiene `code`, `message`, `intent` y
  `retryable` (el contrato de `ApiResponder`).
- Los textos visibles al usuario son **idénticos** a los de antes.

## Riesgos

- Un `message` que hoy se usa para *debug* (no para el usuario) no debe ir al catálogo:
  revisar caso por caso.
- Algunos controllers devuelven `['success' => false, 'message' => …]` sin status HTTP de
  error. Unificar eso cambia la semántica de la respuesta: dejarlo como está y sólo mover el
  texto, o tratarlo como decisión aparte.

## Fuera de alcance

- Cambiar códigos HTTP existentes.
- Unificar la redacción de los 136 códigos del catálogo.
