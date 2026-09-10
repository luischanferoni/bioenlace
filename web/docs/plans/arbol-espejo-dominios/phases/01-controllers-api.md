# Fase 1 — Controllers API v1 por dominio

## Objetivo

Que los 28 controllers de dominio que hoy están planos queden bajo `controllers/<dominio>/`, como ya está `clinical/`, sin cambiar ninguna URL pública ni permiso. Los 13 transversales quedan en la raíz.

## Criterio

Lo que pertenece a un dominio **espeja**: pasa a `controllers/<dominio>/`. Lo transversal **no se disfraza de dominio**: queda en la raíz de `controllers/`.

Dominio asignado según los servicios que el controller consume (evidencia: sus `use`).

## Mapa de asignación

### Espejan (28 archivos)

| Dominio | Controllers | Evidencia |
|---------|-------------|-----------|
| `clinical` (7) | `AudioController`, `CarePacksController`, `EncounterChatController` (ex `ConsultaChatController`), `EncounterJourneyController`, `MediaController`, `MotivosConsultaController`, `PacientesController` | `Domain\Clinical\SpeechToText`, `CareCohort`, `Service\EncounterJourney`, `Service\SecureMediaService`, `metadata/motivos_consulta_intake.yaml`, HC staff |
| `scheduling` (6) | `ConsultaAsyncController`, `ConsultasSeguimientoController`, `ProfesionalAgendaController`, `QuirofanoController`, `TurnosController`, `TurnosPerfilController` | `Domain\Scheduling\Service\ConsultaAsync*`, `ConsultasSeguimientoFlowDraftHydrator`, quirófano por regla de dominio |
| `organization` (9) | `CatalogosController`, `EfectoresController`, `LicenciaController`, `ProfesionalEfectorServicioController`, `ProfesionalHorariosController`, `ServiciosController`, `ServicioTeleconsultaPoliticaController`, `SesionOperativaController`, `SolicitudProfesionalController` | `Domain\Organization\Service\*Depdrop*`, `SesionOperativa`, `Billing`, PES |
| `person` (5) | `PacienteContextoController`, `PersonaController`, `PersonRepresentationController`, `RegistroController`, `VentanillaSesionController` | `Domain\Person\Service\RegistroService`, `Person\Ventanilla\VentanillaSesionService`, representación |
| `integrations` (1) | `WhatsAppWebhookController` | Webhook Meta Cloud API |

`content` y `terminology` no tienen controllers planos.

### Quedan en la raíz (13 archivos)

| Controller | Motivo |
|------------|--------|
| `BaseController` | Base compartida de la capa |
| `AuthController`, `LoginController` | Autenticación (transversal) |
| `ChatController` | Asistente: motor de plataforma |
| `AccionesController` | Descubrimiento de acciones para clientes |
| `HomeController` | Panel unificado (`Platform\Ui\Home`) |
| `NotificacionesController` | `Platform\Core\Service\Notificaciones` |
| `QuejaPacienteController` | `Platform\Core\Service\QuejaPacienteService` |
| `DeviceController`, `ClientDiagnosticController` | Infra de apps móviles |
| `EditarController`, `InfoController`, `ListarController` | Transporte HTTP de `open_ui` para DataAccess (ver decisión 5) |

## Decisiones tomadas

1. **`ServiciosController` + `ServicioTeleconsultaPoliticaController` → `organization`.** Aceptado. Lo pide [`servicio-institucional-vs-pes-acto.mdc`](../../../../../.cursor/rules/servicio-institucional-vs-pes-acto.mdc): `servicios` es oferta del **efector**. Consecuencia: en Fase 3 se mueven también `views/json/scheduling/servicios/` y `views/json/scheduling/servicio-teleconsulta/` a `organization/`, y las entradas correspondientes del catálogo UI.
2. **`ConsultaChatController` → `clinical/EncounterChatController`.** El recurso es el encounter, así que se archiva con su familia (`EncounterController`, `EncounterStaffSummaryController`, `EncounterPatientSummaryController`). Sigue consumiendo servicios `ConsultaAsync*` de scheduling; `ConsultaAsyncController` (solicitud del flujo) se queda en `scheduling`. La URL pública mantiene el segmento `consulta-chat`.
3. **`AudioController` → `clinical`.** Aceptado. El motor `Platform\Ai\SpeechToText` no se mueve.
4. **`person` se queda con todo lo de perfil**, incluidos los signos vitales de `PersonaController`. Criterio registrado en `design.md`: `person` = perfil de cualquier rol (paciente, staff, profesional); lo específico de salud del paciente es `clinical` (por eso `PacientesController` → `clinical`).
5. **`EditarController` / `InfoController` / `ListarController` NO se borran.** Verificado: son el **transporte HTTP** del `open_ui` de los intents de lectura/edición (`rbac_route: /api/info`, `/api/listar`, `/api/editar`; ver [`asistente-lectura-data-access.md`](../../../arquitectura/asistente-lectura-data-access.md) y `UiActionCatalog`). Lo deprecado es reabrirlos como intents NL, no el endpoint. Quedan en la raíz y se corrige el docblock, que hoy induce a pensar que están muertos.

## Tareas

### 1.1 Congelar el mapa

- [x] Decisiones 1–5 resueltas
- [ ] Congelar la tabla de asignación antes de mover archivos

### 1.2 Mover y renombrar namespace

- [ ] Mover archivos a `controllers/<dominio>/`
- [ ] Actualizar `namespace` y todos los `use`
- [ ] `ClinicalAccessTrait` queda en `clinical/` (ya está)
- [ ] Renombrar `ConsultaChatController` → `EncounterChatController` (semántica `Consulta` = atención médica → `Encounter`; ver `design.md` §10)
- [ ] Corregir el docblock de `Editar` / `Info` / `Listar`: son transporte de `open_ui`, no endpoints muertos

### 1.3 urlManager

- [ ] Reapuntar cada regla al nuevo route interno (`'GET api/<version:\w+>/turnos/listar-como-paciente' => '<version>/scheduling/turnos/listar-como-paciente'`)
- [ ] **No** cambiar ningún patrón público
- [ ] Verificar que no queden reglas apuntando a routes inexistentes

### 1.4 Tests

- [ ] Mover los tests de API afectados a `tests/unit/<dominio>/`
- [ ] Test de humo: para una ruta por dominio, `ApiRoutePermissionResolver::checkedRoutesForAction()` sigue devolviendo la ruta `/api/<entidad>/<accion>` histórica

## Fuera de esta fase

- Poner el dominio en la URL pública (decisión aparte).
- Borrar `ENTITY_DOMAINS` (Fase 3, cuando `views/json` también esté parejo).
- Modelos (Fase 2).
- El barrido completo `Consulta` → `Encounter` (94 archivos; ver `design.md` §10). Acá solo se renombra el controller de chat.

## Criterios de aceptación

- [ ] En la raíz de `controllers/` solo quedan los 13 transversales de la tabla (ningún controller de dominio)
- [ ] Cero cambios en patrones de `urlManager` públicos
- [ ] Cero migraciones RBAC nuevas
- [ ] Smoke de asistente y de turnos paciente pasan sin cambios de fixtures

## PR sugerido

`refactor(api): controllers v1 agrupados por dominio (sin cambio de URL)`
