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

En curso. Hecho: contrato, catálogo YAML con loader, excepción con código y `meta`, respondedor y manejador en BaseController, test de contrato (incluye códigos usados en el dominio), documento en arquitectura/contrato-api.md. Migrados: AudioController y todo Capture/Encounter con sus consumidores en móvil (`EncounterCaptureApi`, `ApiContractException`) y web (`encounter-capture-form.js`); y todos los controllers de `clinical/` que usaban `ClinicalAccessTrait` (resúmenes, journey, chat, motivos de consulta, receta electrónica, plan de tratamiento, episodio, internación, medicación, pedidos, diagnóstico, historia, laboratorio, odontología, oftalmología), con sus consumidores Dart (`unwrapApiContract` / `unwrapApiContractList`) y JS. También `EmergencyController`, `HomeController`, `CatalogosController`, `FrontDeskSessionController`, `SesionOperativaController`, `LicenciaController`, `QuirofanoController`, `CareProtocolController`, `InpatientEpicrisisTemplateController`, `PersonaController`, `RegistroController`, `ChatController`, `AuthController` y `PacientesController`; se migraron los filtros de autenticación y permisos para responder con el mismo contrato. Se actualizaron consumidores móviles de Emergency, Licencia, Registro, Chat, autenticación (login staff, revisión y biométrico, activación, sesión/refresh y JWT de desarrollo), historia clínica (incluida la pantalla de cronología), panel de inicio, catálogo público de clases de consulta y configuración de sesión operativa; los consumidores web del panel y del wizard post-login leen el resultado desde `data`. Los errores de configuración preservan el contacto administrativo en `meta.contact`. La API de sesión de ventanilla no tiene clientes móviles ni UI web directa. Las ventanas de historia conservan su código y metadatos dentro del contrato. `EncounterCaptureContextService` ahora lanza `ApiException` tipadas del catálogo, manteniendo el mensaje en el AJAX web y el fallback del bootstrap de captura. La web no tenía consumidores directos de las rutas API de Auth. El bridge web interpreta errores del contrato. El sobre del asistente ahora viaja dentro de `data` para mantener su protocolo funcional dentro del contrato común. No se encontraron consumidores móviles o web directos para Quirofano, CareProtocol ni la administración API de plantillas de epicrisis. `requireEncounterAccess`, `requireCarePlanAccess`, `requireInternacionStaffAccess` y `clinicalError` ya fueron eliminados del trait. Las pantallas UI JSON (`ui_definition`) conservan su protocolo propio.

### Punto de continuidad (2026-10-21)

- Último lote editado: `organization/SolicitudProfesionalController`. Listado y creación responden `{data, meta}` (`data.solicitudes` / `data.id`); los errores de validación y permisos se mapean al catálogo. No se encontraron consumidores directos más allá de la configuración de rutas.
- Lote previo: `DeviceController` y `ClientDiagnosticController`. El registro de token push y la recepción de diagnósticos responden `{data, meta}` con errores tipados del catálogo. El consumidor móvil de diagnósticos ahora valida el contrato; los registradores push móviles no leían el cuerpo exitoso y solo comprobaban el estado HTTP.
- Lote previo: `NotificacionesController`. Los endpoints de bandeja, marcar leída e interacción push ahora usan `{data, meta}` y errores tipados del catálogo. Se actualizaron los consumidores web de alertas y los servicios móviles de notificaciones y recibos push para retirar la dependencia de `success` en la respuesta.
- Lote previo: `organization/ProfesionalEfectorServicioController`. Los endpoints de datos y preview ahora responden con `{data, meta}` y los errores HTTP/de dominio usan códigos del catálogo (`request_invalid`, `forbidden_detail`). Se adaptó el `processResults` del Select2 web para leer `data.results` y el widget de preview para mostrar `error.message`; el consumidor móvil ya leía el preview desde `data`. Los flujos `ui_definition` y `ui_submit_result` conservan su protocolo propio.
- También se migró `organization/ProfesionalHorariosController`: sus endpoints CRUD/listado usan el contrato y preservan conflictos en `meta.conflicts`. Las pantallas `ui_definition` permanecen intactas.
- No se pudo recalcular la guarda de fase 00: el PHP CLI es 7.4 y el script usa `str_starts_with()` (PHP 8); el baseline versionado tampoco está presente en `web/tools/`.
- Validaciones recientes: PHP lint del controlador de solicitudes, de Device/ClientDiagnostic y de los lotes anteriores, `node --check` de widgets web y `git diff --check` pasaron. Análisis Flutter de servicios modificados: sin errores nuevos; persiste un aviso preexistente de dependencia directa `shared_preferences` en `push_receipt_queue.dart`. En la última tanda Dart, hot restart en Chrome falló por una excepción del puente DevTools; hot reload posterior pasó y el runtime no reportó errores. `ApiContractTest` no se pudo ejecutar porque Codeception no tiene instalado el módulo Yii2.
- Próximo paso: continuar con los controllers restantes que aún usan respuestas heredadas o mensajes literales, manteniendo los protocolos UI propios y migrando sus consumidores directos.
- Antes del cierre de fase: repetir la búsqueda de `success()`/`error()` y respuestas manuales en toda la API, migrar también sus consumidores y retirar los adaptadores heredados de `BaseController` cuando ya no tengan usos.
- Los cambios de código de esta tanda están actualmente sin commit; consultar `git status` antes de cambiar de equipo y transferirlos junto con esta documentación. El archivo SQL sin seguimiento que ya existía antes de esta tanda no se modificó.

Pendiente: migrar los controllers restantes y reducir los contadores de la fase 00; después retirar `success()`/`error()` de BaseController y continuar con los demás dominios.