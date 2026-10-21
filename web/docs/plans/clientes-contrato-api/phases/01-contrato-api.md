# Fase 01 — Contrato de API

## Objetivo

Un solo formato de respuesta y de error para toda la API v1, con mensajes definidos en el backend.

## Tareas

- Definir el contrato: éxito con datos y metadatos; error con `code`, `message`, `intent`, `retryable` y `field_errors` opcional.
- Un respondedor único en `common/components/Platform/Core` que usen todos los controllers de `frontend/modules/api/v1`.
- Catálogo YAML de códigos y mensajes bajo `common/metadata`, con un loader único. Sin lista duplicada en PHP.
- Excepción de dominio con código. Los servicios dejan de redactar texto; un manejador único la convierte en respuesta.
- Mapeo de errores de validación a `field_errors`.
- Contrato de estado visible: `code`, `label`, `intent`.
- Migrar los 68 mensajes de controllers y los 659 de servicios por dominio, empezando por Clinical/Capture y Encounter (piloto).
- Test de contrato por endpoint y test que verifique que todo código lanzado existe en el catálogo.
- Documentar el contrato en `web/docs/arquitectura/`.

## Criterio de salida

Todos los endpoints del piloto responden con el formato nuevo. Los contadores de la fase 0 bajan en controllers y servicios migrados. El resto de dominios queda con tope descendente hasta cerrar en la fase 9.

## Estado

En curso. Hecho: contrato, catálogo YAML con loader, excepción con código y `meta`, respondedor y manejador en BaseController, test de contrato (incluye códigos usados en el dominio), documento en arquitectura/contrato-api.md. Migrados: AudioController y todo Capture/Encounter con sus consumidores en móvil (`EncounterCaptureApi`, `ApiContractException`) y web (`encounter-capture-form.js`); y todos los controllers de `clinical/` que usaban `ClinicalAccessTrait` (resúmenes, journey, chat, motivos de consulta, receta electrónica, plan de tratamiento, episodio, internación, medicación, pedidos, diagnóstico, historia, laboratorio, odontología, oftalmología), con sus consumidores Dart (`unwrapApiContract` / `unwrapApiContractList`) y JS. También `EmergencyController`, `LicenciaController`, `QuirofanoController`, `CareProtocolController`, `InpatientEpicrisisTemplateController`, `PersonaController`, `RegistroController`, `ChatController` y `AuthController`; se migraron los filtros de autenticación y permisos para responder con el mismo contrato. Se actualizaron consumidores móviles de Emergency, Licencia, Registro, Chat y autenticación (login staff, revisión y biométrico, activación, sesión/refresh y JWT de desarrollo); la web no tenía consumidores directos de las rutas API de Auth. El bridge web interpreta errores del contrato. El sobre del asistente ahora viaja dentro de `data` para mantener su protocolo funcional dentro del contrato común. No se encontraron consumidores móviles o web directos para Quirofano, CareProtocol ni la administración API de plantillas de epicrisis. `requireEncounterAccess`, `requireCarePlanAccess`, `requireInternacionStaffAccess` y `clinicalError` ya fueron eliminados del trait. Las pantallas UI JSON (`ui_definition`) conservan su protocolo propio. Pendiente: otros controllers que aún usan `success()`/`error()` (incluido Pacientes), `EncounterCaptureContextService`, luego retirar `success()`/`error()` de BaseController y los demás dominios.