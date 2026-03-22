# Plan de pruebas manual (Internación + Chat + acciones)

Este checklist valida el flujo: **intents → acciones/botones → formularios → persistencia**.

La captura clínica en texto/audio para internación sigue el mismo criterio que el resto del sistema: **consulta** (`POST /api/v1/consulta/analizar` / `guardar` según el flujo de HC), no un endpoint de “entity intake” dedicado.

## Pre-requisitos

- Usuario autenticado con rol interno (médico/enfermería/adm).
- Acceso a módulos de internación en web.
- Endpoint API de chat disponible (según despliegue), p. ej. `POST /api/v1/messages/enviar` o el que use el cliente.

## 1) Prueba de “actions” en API chat

Enviar un JSON acorde al contrato del chat (ejemplo ilustrativo):

```json
{ "senderId": "test_user_1", "content": "dar el alta de la internación 123" }
```

Validar:

- `content` contiene texto.
- `router.metadata.intent` es `internacion_alta` (según matching).
- `router.actions` incluye una acción con `route` y `params` que identifiquen la internación.

## 2) Prueba de intent → botón (web)

En el UI que consume `router.actions`:

- Presionar el botón “Dar alta”.
- Verificar que abre `internacion/update?id=...`.

## 3) Formularios de internación (ingreso, alta, medicación, diagnóstico, prácticas)

- Completar campos en el formulario nativo y guardar.
- Verificar persistencia en base.
- La narrativa clínica o dictado van por **historia clínica / consulta**, no por pre-carga IA en estos formularios.

## 4) Test de matching de acciones (herramienta interna)

Abrir `/site/test-action-matching`:

- (Opcional) completar campo “Rol” para simular permisos RBAC.
- Probar JSON de criterios relacionado con internación y validar que aparecen acciones de `internacion/*`.
