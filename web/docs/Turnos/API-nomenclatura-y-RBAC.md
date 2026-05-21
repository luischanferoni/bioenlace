# API v1 — Nomenclatura de turnos y RBAC

Documento de referencia para convenciones de nombres, URLs públicas y permisos (`ApiGhostAccessControl` / webvimark). La tabla **canónica** y actualizada vive en el docblock de `web/frontend/modules/api/v1/controllers/TurnosController.php`.

## Objetivo del “corte”

1. **Autogestión (como paciente):** el recurso afectado es siempre el del **usuario autenticado** (persona vía `id_user` o comprobación `turno.id_persona === sesión`). Ej.: listar mis turnos, cancelar mi turno, crear turno sin elegir otro `id_persona`.
2. **Operativo (para paciente / efector):** el cuerpo o el alcance administrativo define **otro** paciente o todo un día en el efector. Requiere permisos distintos; el rol paciente no debe tener rutas como `crear-para-paciente` o `cancelar-dia-efector`.
3. **Agenda profesional:** listado por RRHH/fecha en `GET /api/v1/profesional-agenda/dia` (`ProfesionalAgendaController`), no confundir con “mis turnos como paciente” (`GET …/turnos/listar-como-paciente`).

## Patrón de nombres

- **Acción Yii:** `action` + *VerboEnCamelCase* + *Ámbito* (ej. `actionCancelarComoPaciente`, `actionCrearParaPaciente`).
- **URL pública** puede ser más corta o REST-like (`…/cancelar`, `…/eventos`); no tiene que coincidir con el `action-id` interno.
- **Permiso en base de datos:** derivado del `action-id`: `/api/turnos/<action-id>` (sin prefijo `v1`).

## Dónde tocar qué

| Área | Archivo habitual |
|------|------------------|
| Reglas HTTP | `web/frontend/config/main.php` |
| Lógica de respuesta | `TurnosController.php`, `ProfesionalAgendaController.php` |
| Slots oferta paciente (agrupados día / mañana-tarde) | `TurnoSlotOfferService`, params `turnosPaciente` en `common/config/params.php` |
| Cálculo compartido agenda día | `TurnosController::agendaDiaResponse()` |
| Alta compartida | `TurnosController::persistTurnoCreacion()` |
| Chat / CTAs que chequean permiso | `common/components/Actions/ChatApiActionBuilder.php` (p. ej. crear turno autogestión) |

## Oferta de slots (paciente)

- **Paso 1 — días:** `GET|POST /api/v1/turnos/slots-dias-disponibles-como-paciente` — un bloque `list` con un ítem por fecha (`draft_field`: `fecha_turno`). Permiso: `/api/turnos/slots-dias-disponibles-como-paciente` (hereda roles de `slots-disponibles-como-paciente`).
- **Paso 2 — horarios:** `GET|POST /api/v1/turnos/slots-disponibles-como-paciente` — query/body **`fecha`** (`Y-m-d`, del paso 1) limita a ese día; sin `fecha`, comportamiento histórico (varios días hasta el límite de slots).
- **Permiso horarios:** `/api/turnos/slots-disponibles-como-paciente` (registrar y asignar al rol paciente u otros que correspondan).
- **Flujo asistente:** intent `turnos.crear-como-paciente` → subintents `select_dia` → `select_slot`.
- **Límites por defecto:** clave `turnosPaciente` en `common/config/params.php` (`slots_oferta_max`, `slots_busqueda_max_dias`, `franja_tarde_desde`, `slots_oferta_max_cliente`).
- **UI JSON horarios:** varios bloques `kind: list` (día + franja: `Hoy · por la mañana`, etc.). `id` = token `pes:…`, `label` = `HH:MM`. Plantilla: `scheduling/turnos/slots-disponibles-como-paciente.json`.
- **UI JSON días:** plantilla `scheduling/turnos/slots-dias-disponibles-como-paciente.json`; ítems `id`/`label` = fecha amigable (`Hoy`, `Mañana`, `lunes 15/05`).
- **`slot_id` canónico (4 partes):** `pes:{id_profesional_efector_servicio}|{fecha}|{hora}|{intervalo_minutos}` — ver [agenda-intervalo-y-reservas.md](./agenda-intervalo-y-reservas.md). Compat: 3 partes (intervalo desde versión vigente).

## Configurar agenda (staff / asistente)

| URL | Permiso `/api/...` |
|-----|-------------------|
| `GET\|POST …/profesional-agenda/configurar-agenda` | `profesional-agenda/configurar-agenda` |
| `POST …/profesional-agenda/preview-configurar-agenda` | `profesional-agenda/preview-configurar-agenda` |

Publicación de versión con `vigente_desde`, `intervalo_minutos` (15/20/30/45/60) y `confirmar_cambios=1` si el preview indica impacto.

## Conflictos de agenda (paciente)

| URL | Permiso |
|-----|---------|
| `GET\|POST …/turnos/elegir-conflicto-agenda-como-paciente` | `turnos/elegir-conflicto-agenda-como-paciente` |
| `GET\|POST …/turnos/elegir-resolucion-conflicto-agenda-como-paciente` | `turnos/elegir-resolucion-conflicto-agenda-como-paciente` |
| `POST …/turnos/resolver-conflicto-agenda-como-paciente` | `turnos/resolver-conflicto-agenda-como-paciente` |

Body resolución: `id` (turno), `eleccion` = `antes` \| `despues` \| `cancelar`. Listados paciente exponen `agenda_conflicto_pendiente` y `agenda_conflicto`. Filtro opcional `solo_agenda_conflicto=1` en listados con `alcance=pendientes`. Intent: `turnos.conflicto-agenda-flow` — ver [intents-turnos.md](./intents-turnos.md).

## Conflictos de agenda (staff)

| URL | Permiso |
|-----|---------|
| `GET\|POST …/profesional-agenda/elegir-conflicto-agenda` | `profesional-agenda/elegir-conflicto-agenda` |
| `GET\|POST …/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente` | `profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente` |
| `POST …/profesional-agenda/resolver-conflicto-agenda-para-paciente` | `profesional-agenda/resolver-conflicto-agenda-para-paciente` |

Requiere efector en sesión. Intent: `agenda.resolver-conflictos-staff-flow`.

## Migración de permisos

Al renombrar acciones, los permisos **cambian de string**. Hay que registrar las rutas nuevas en webvimark y reasignar roles; mantener temporalmente rutas viejas solo si hace falta convivencia en despliegue.

## Intents asistente (turnos / agenda)

Matriz completa intent ↔ API: [intents-turnos.md](./intents-turnos.md).

## Documentación relacionada

- `web/docs/Turnos/README.md` — índice del dominio turnos.
- `web/docs/Turnos/agenda-intervalo-y-reservas.md` — versiones, intervalo, solapamiento, conflictos.
- `web/docs/Turnos/politica-cancelacion-autogestion.md`, `cancelacion-masiva.md`, `reprogramacion-ui.md`, etc. — flujos puntuales.
