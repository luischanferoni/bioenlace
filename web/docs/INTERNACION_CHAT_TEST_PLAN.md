# Plan de pruebas manual (Internación + Chat + EntityIntake)

Este checklist valida el flujo completo:
**intents → acciones/botones → formularios → prefill por texto → persistencia**.

## Pre-requisitos

- Usuario autenticado con rol interno (médico/enfermería/adm).
- Acceso a módulos de internación en web.
- Endpoint API disponible:
  - `POST /api/v1/chat/recibir`
  - `POST /api/v1/entity-intake/analyze`

## 1) Prueba de “actions” en API chat

Enviar a `POST /api/v1/chat/recibir` un JSON:

```json
{ "senderId": "test_user_1", "content": "dar el alta de la internación 123" }
```

Validar:
- `content` contiene texto.
- `router.metadata.intent` es `internacion_alta` (según matching).
- `router.actions` incluye una acción con `route` y `params.id = 123`.

## 2) Prueba de intent → botón (web)

En el UI que consume `router.actions`:
- Presionar el botón “Dar alta”.
- Verificar que abre `internacion/update?id=...`.

## 3) Prueba de EntityIntake en formulario (Ingreso)

En `internacion/create`:
- Escribir texto en “Carga por texto (asistente)”.
- Presionar “Analizar y pre-cargar”.
- Verificar que se completan campos como:
  - `fecha_inicio`, `hora_inicio`
  - `id_tipo_ingreso`
  - radios `ingresa_en`, `ingresa_con`
  - `situacion_al_ingresar`
- Si el mensaje incluye derivación/acompañante, verificar que el prefill refleja valores (aunque luego se ajusten manualmente).
- Guardar y confirmar persistencia.

## 4) Prueba de EntityIntake en Alta

En `internacion/update`:
- Escribir texto de alta.
- “Analizar y pre-cargar”.
- Verificar prefill en:
  - `fecha_fin`, `hora_fin`
  - `id_tipo_alta`
  - `observaciones_alta`, `condiciones_derivacion`
- Guardar y confirmar.

## 5) Medicación / Diagnóstico / Prácticas

- Medicación: `internacion-medicamento/create?id={id_internacion}`
  - “Analizar y pre-cargar”
  - Verificar que se completan campos simples (cantidad/dosis_diaria/indicacion).
  - Confirmar que el usuario puede seleccionar manualmente el “Concepto” (Select2).
- Diagnóstico y Prácticas:
  - Verificar que el asistente devuelve el texto detectado para ayudar a seleccionar el concepto.

## 6) Test de matching de acciones (herramienta interna)

Abrir `/site/test-action-matching`:
- (Opcional) completar campo “Rol” para simular permisos RBAC.
- Probar JSON de criterios relacionado con internación y validar que aparecen acciones de `internacion/*`.

