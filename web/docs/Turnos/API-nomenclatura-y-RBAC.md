# API v1 — Nomenclatura de turnos y RBAC

Documento de referencia para convenciones de nombres, URLs públicas y permisos (`ApiGhostAccessControl` / webvimark). La tabla **canónica** y actualizada vive en el docblock de `web/frontend/modules/api/v1/controllers/TurnosController.php`.

## Objetivo del “corte”

1. **Autogestión (como paciente):** el recurso afectado es siempre el del **usuario autenticado** (persona vía `id_user` o comprobación `turno.id_persona === sesión`). Ej.: listar mis turnos, cancelar mi turno, crear turno sin elegir otro `id_persona`.
2. **Operativo (para paciente / efector):** el cuerpo o el alcance administrativo define **otro** paciente o todo un día en el efector. Requiere permisos distintos; el rol paciente no debe tener rutas como `crear-para-paciente` o `cancelar-dia-efector`.
3. **Agenda profesional:** listado por RRHH/fecha en `GET /api/v1/agenda/dia` (`AgendaController`), no confundir con “mis turnos como paciente” (`GET …/turnos/como-paciente`).

## Patrón de nombres

- **Acción Yii:** `action` + *VerboEnCamelCase* + *Ámbito* (ej. `actionCancelarComoPaciente`, `actionCrearParaPaciente`).
- **URL pública** puede ser más corta o REST-like (`…/cancelar`, `…/eventos`); no tiene que coincidir con el `action-id` interno.
- **Permiso en base de datos:** derivado del `action-id`: `/api/turnos/<action-id>` (sin prefijo `v1`).

## Dónde tocar qué

| Área | Archivo habitual |
|------|------------------|
| Reglas HTTP | `web/frontend/config/main.php` |
| Lógica de respuesta | `TurnosController.php`, `AgendaController.php` |
| Slots oferta paciente (agrupados día / mañana-tarde) | `TurnoSlotOfferService`, params `turnosPaciente` en `common/config/params.php` |
| Cálculo compartido agenda día | `TurnosController::agendaDiaResponse()` |
| Alta compartida | `TurnosController::persistTurnoCreacion()` |
| Chat / CTAs que chequean permiso | `common/components/Actions/ChatApiActionBuilder.php` (p. ej. crear turno autogestión) |

## Oferta de slots (paciente)

- **URL:** `GET|POST /api/v1/turnos/slots-disponibles-como-paciente`
- **Permiso webvimark:** `/api/turnos/slots-disponibles-como-paciente` (registrar y asignar al rol paciente u otros que correspondan).
- **Límites por defecto:** clave `turnosPaciente` en `common/config/params.php` (`slots_oferta_max`, `slots_busqueda_max_dias`, `franja_tarde_desde`, `slots_oferta_max_cliente`).
- **Ejemplo de respuesta:** `web/frontend/modules/api/v1/views/json/turnos/slots-disponibles-como-paciente.example.json`

## Migración de permisos

Al renombrar acciones, los permisos **cambian de string**. Hay que registrar las rutas nuevas en webvimark y reasignar roles; mantener temporalmente rutas viejas solo si hace falta convivencia en despliegue.

## Documentación relacionada

- `web/docs/Turnos/README.md` — índice del dominio turnos.
- `web/docs/Turnos/politica-cancelacion-autogestion.md`, `cancelacion-masiva.md`, `reprogramacion-ui.md`, etc. — flujos puntuales.
