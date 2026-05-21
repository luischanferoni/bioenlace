# Intents del asistente — Turnos y agenda

## Objetivo

Índice de `intent_id` de turnos/agenda, pasos YAML y cierre RBAC alineado con rutas API v1.

## Actores

- Paciente (autogestión).
- Staff en efector (operativo y agenda).

## Secuencia (patrón general)

1. Usuario expresa necesidad en chat → motor de sub-intents resuelve `intent_id`.
2. Pasos `select_*` / `confirm` cargan datos vía `action_id` (misma ruta que API).
3. Paso final `api_call` persiste (crear, cancelar, reprogramar, etc.).

## Anclas

| Área | Ubicación |
|------|-----------|
| Schemas YAML | `SubIntentEngine/schemas/intents/` |
| Contrato pasos | `SUBINTENT_CONTRACT.md` en schemas |
| API turnos | `TurnosController` |
| Presentación slots | `TurnoSlotOfferUiPresenter` |

---

Fuente YAML: `web/common/components/Assistant/SubIntentEngine/schemas/intents/`.  
Contrato de pasos: [`SUBINTENT_CONTRACT.md`](../../../../common/components/Assistant/SubIntentEngine/schemas/SUBINTENT_CONTRACT.md).

Los `action_id` del catálogo UI coinciden con la ruta API (módulo + acción) sin prefijo `v1`. Los permisos webvimark usan `/api/<action-id>`.

## Paciente (autogestión)

| intent_id | RBAC (cierre) | Pasos principales |
|-----------|---------------|-------------------|
| `turnos.crear-como-paciente` | `/api/turnos/crear-como-paciente` | servicio → efector → profesional → día → horario → crear |
| `turnos.cancelar-como-paciente-flow` | `/api/turnos/cancelar-como-paciente` | elegir pendiente → motivo → cancelar |
| `turnos.modificar-como-paciente-flow` | `/api/turnos/reprogramar-como-paciente` | elegir pendiente → slots reprogramar → reprogramar |
| `turnos.conflicto-agenda-flow` | `/api/turnos/resolver-conflicto-agenda-como-paciente` | elegir conflicto → elección antes/despues/cancelar |
| `turnos.confirmar-asistencia-flow` | `/api/turnos/confirmar-asistencia-como-paciente` | elegir pendiente → confirmar |
| `turnos.consultar-politica-autogestion-flow` | `/api/turnos/politica-como-paciente` | consulta política por efector |

### Listados con conflicto de agenda

- `turnos.elegir-pendiente-como-paciente`: si el turno tiene conflicto pendiente, el ítem se muestra con prefijo `⚠`.
- Query opcional `solo_agenda_conflicto=1` en `listar-como-paciente` / elegir pendiente (alcance `pendientes`): solo turnos con fila en `turno_agenda_conflicto` pendiente.

## Staff / operativo (turnos)

| intent_id | RBAC (cierre) | UI principal |
|-----------|---------------|--------------|
| `turnos.crear-para-paciente-flow` | `/api/turnos/crear-para-paciente` | `turnos.crear-para-paciente` |
| `turnos.cancelar-para-paciente-flow` | `/api/turnos/cancelar-operativo` | `turnos.cancelar-operativo` |
| `turnos.no-se-presento-flow` | `/api/turnos/no-se-presento` | `turnos.no-se-presento` |
| `turnos.crear-sobreturno-flow` | `/api/turnos/crear-sobreturno` | `turnos.crear-sobreturno` |
| `turnos.consultar-ocupacion-dia-flow` | `/api/turnos/consultar-ocupacion-dia` | `turnos.consultar-ocupacion-dia` |
| `turnos.ver-agenda-dia-profesional-flow` | `/api/profesional-agenda/ver-agenda-dia` | `profesional-agenda.ver-agenda-dia` |

## Staff (agenda)

| intent_id | RBAC (cierre) | Notas |
|-----------|---------------|--------|
| `agenda.crear-profesional-flow` | `/api/profesional-agenda/crear-agenda-flow` | alta PES + configurar agenda si aplica |
| `agenda.editar-agenda-flow` | `/api/profesional-agenda/editar-agenda-flow` | editar horarios; paso `profesional-agenda.configurar-agenda` incluye preview de impacto |
| `agenda.resolver-conflictos-staff-flow` | `/api/profesional-agenda/resolver-conflicto-agenda-para-paciente` | conflictos del efector en nombre del paciente |

## API nuevas (soporte a intents)

### Paciente

| Método | URL | Permiso |
|--------|-----|---------|
| GET\|POST | `/api/v1/turnos/elegir-conflicto-agenda-como-paciente` | `turnos/elegir-conflicto-agenda-como-paciente` |
| GET\|POST | `/api/v1/turnos/elegir-resolucion-conflicto-agenda-como-paciente` | `turnos/elegir-resolucion-conflicto-agenda-como-paciente` |
| POST | `/api/v1/turnos/resolver-conflicto-agenda-como-paciente` | `turnos/resolver-conflicto-agenda-como-paciente` |

### Staff (conflictos y agenda día)

| Método | URL | Permiso |
|--------|-----|---------|
| GET\|POST | `/api/v1/profesional-agenda/elegir-conflicto-agenda` | `profesional-agenda/elegir-conflicto-agenda` |
| GET\|POST | `/api/v1/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente` | `profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente` |
| POST | `/api/v1/profesional-agenda/resolver-conflicto-agenda-para-paciente` | `profesional-agenda/resolver-conflicto-agenda-para-paciente` |
| GET\|POST | `/api/v1/profesional-agenda/ver-agenda-dia` | `profesional-agenda/ver-agenda-dia` |

Registrar permisos en webvimark y asignar roles tras desplegar.

## Cliente móvil (Flutter)

- Abrir `turnos.conflicto-agenda-flow` cuando `agenda_conflicto_pendiente === true` en un turno listado.
- Consumir `agenda_conflicto.opcion_antes` / `opcion_despues` como referencia; la elección formal va por las pantallas del flujo o POST directo de resolución.

## Documentación relacionada

- [Agenda versionada e intervalo](./agenda-intervalo-y-reservas.md)
- [API nomenclatura y RBAC](./API-nomenclatura-y-RBAC.md)
- [Reprogramación UI](./reprogramacion-ui.md)
