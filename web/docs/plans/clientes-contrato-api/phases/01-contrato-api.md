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

- Último lote editado: `SesionOperativaController` y sus consumidores móvil (`ConfigService`) y web (wizard post-login). Se preserva el contacto administrativo bajo `meta.contact`; se agregó `mobile_role_unavailable` al catálogo. También se completó `FrontDeskSessionController` y la alineación de consumidores de Home y Catalogos al contrato.
- Estado de la guarda de la fase 00: `api_controller_literal_messages = 29` y `domain_literal_messages = 691`; el resto de los contadores está en su baseline. Próximo candidato acotado: `organization/ProfesionalHorariosController`; después continuar con los controllers restantes que todavía usan respuestas heredadas o mensajes literales.
- Validaciones de este lote: análisis Dart, hot reload, PHP lint, `node --check`, resolución del código nuevo del catálogo y `git diff --check` pasaron. La prueba Codeception `ApiContractTest` no se pudo ejecutar porque el PHP CLI no tiene la extensión cURL.
- Antes del cierre de fase: repetir la búsqueda de `success()`/`error()` y respuestas manuales en toda la API, migrar también sus consumidores y retirar los adaptadores heredados de `BaseController` cuando ya no tengan usos.
- Los cambios de código de esta tanda están actualmente sin commit; consultar `git status` antes de cambiar de equipo y transferirlos junto con esta documentación.

Pendiente: migrar los controllers restantes y reducir los contadores de la fase 00; después retirar `success()`/`error()` de BaseController y continuar con los demás dominios.