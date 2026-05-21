# Agenda versionada, intervalo de turnos y reservas

Documento de referencia para el modelo **greenfield** de grilla de turnos (2026): versiones de agenda con `vigente_desde`, intervalos fijos, ocupación por **solapamiento**, preview al configurar y cola de conflictos para pacientes.

Migración Yii: `common/migrations/m260516_000001_agenda_intervalo_versioning.php`.

## Resumen

| Antes | Ahora |
|-------|--------|
| `duracion_slot_minutos` libre en agenda | **`intervalo_minutos`** solo valores **15, 20, 30, 45, 60** |
| Agenda única por PES (tabla espejo) | **Versiones** en `profesional_efector_servicio_agenda_version` con `vigente_desde` |
| Ocupación = misma `hora` exacta | Ocupación por **rango** `[hora, hora_fin)` con solapamiento |
| `slot_id` de 3 partes | Canónico **4 partes**: `pes:{id}\|{fecha}\|{hora}\|{intervalo}` (3 partes sigue admitido) |
| Cambio de agenda sin aviso | **Preview obligatorio** + `confirmar_cambios=1` si hay impacto |

La tabla `profesional_efector_servicio_agenda` sigue siendo el **espejo** de la versión vigente (lecturas rápidas, teleconsulta, cupo). La fuente de verdad para la grilla en una fecha es la **versión vigente** (`vigente_desde` ≤ fecha del turno).

## Intervalo de turnos

- Constantes y validación: `common/components/Organization/Service/ProfesionalEfectorServicio/AgendaIntervaloMinutos.php`.
- Default: **15** minutos.
- **Máximo 2 cambios de intervalo por año calendario** por PES (cuenta versiones distintas en el mismo año).
- `duracion_slot_minutos` en agenda espejo se deja en `null` al publicar una versión; no usar para nueva lógica.

## Versiones de agenda

### Tabla `profesional_efector_servicio_agenda_version`

- Una fila por cambio publicado: horarios (`lunes_2` … `domingo_2`), `formas_atencion`, `cupo_pacientes`, `intervalo_minutos`, `vigente_desde`.
- Índice único: `(id_profesional_efector_servicio, vigente_desde)`.
- Versión vigente para una fecha: mayor `vigente_desde` ≤ fecha (`ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha`).

### Servicio

`ProfesionalEfectorServicioAgendaVersionService`:

- **`previewImpacto($idPes, $idEfector, $post)`** — simula alineación de turnos futuros `PENDIENTE` y lista conflictos con opciones antes/después en la nueva grilla.
- **`publicarVersion(...)`** — valida límite anual, exige confirmación si `requiere_confirmacion`, persiste versión, sincroniza agenda espejo y crea filas en `turno_agenda_conflicto`.
- **`resolverConflictoPaciente($idTurno, $idPersona, $eleccion)`** — `antes` \| `despues` \| `cancelar`.

### API configurar agenda (staff / flujo asistente)

| Método | Ruta | Uso |
|--------|------|-----|
| GET\|POST | `/api/v1/profesional-agenda/configurar-agenda` | Descriptor UI + submit (`ProfesionalEfectorServicioAgendaUiService::submitAgendaConfig` → `publicarVersion`) |
| POST | `/api/v1/profesional-agenda/preview-configurar-agenda` | Solo preview (`previewAgendaConfig`) |
| POST (body `preview=1`) | mismo `configurar-agenda` | Preview alternativo en el mismo endpoint |

Campos relevantes del formulario (`configurar-agenda.json`):

- `vigente_desde` (date, ≥ hoy)
- `intervalo_minutos` (15 / 20 / 30 / 45 / 60)
- `confirmar_cambios` (`0` \| `1`) — obligatorio en **1** si hay conflictos o cambio de intervalo
- Widget web `agenda_config_preview`: botón «Ver impacto» → `preview-configurar-agenda`

Permisos webvimark sugeridos (sin `v1`):

- `/api/profesional-agenda/configurar-agenda`
- `/api/profesional-agenda/preview-configurar-agenda`

## Generación de slots y oferta

- Motor: `AgendaSlotEngine` (usa `AgendaHorarioSlotsTrait`).
- Búsqueda de libres: `TurnoSlotFinder` resuelve versión vigente por día del slot.
- Oferta agrupada: `TurnoSlotOfferService` arma `slot_id` con cuarto segmento `|{intervalo}` cuando puede.

## Reserva de un turno (persistencia)

Al crear o reprogramar, `TurnoReservaSlotService::aplicarCamposReserva` (desde `TurnoPersistService::crear` y reprogramación paciente):

1. Toma `id_profesional_efector_servicio`, `fecha`, `hora` (tras expandir `slot_id` si aplica).
2. Valida que la hora esté en la **grilla** de la versión vigente.
3. Setea `intervalo_minutos_reserva`, `hora_fin`, `id_agenda_version`.
4. Rechaza si hay **solapamiento** (`TurnoSlotOccupancyService`).

### Columnas nuevas en `turnos`

| Columna | Descripción |
|---------|-------------|
| `id_agenda_version` | Versión usada al reservar |
| `intervalo_minutos_reserva` | Duración del bloque reservado |
| `hora_fin` | Fin exclusivo del intervalo (TIME) |

Turnos legacy sin `hora_fin`: la ocupación infiere fin con `intervalo_minutos_reserva` o la versión vigente en esa fecha.

## Contrato `slot_id`

Formato **canónico** (recomendado en oferta y flujos conversacionales):

```text
pes:{id_profesional_efector_servicio}|{YYYY-MM-DD}|{HH:MM}|{intervalo_minutos}
```

Ejemplo: `pes:42|2026-06-10|09:30|30`

**Compatibilidad:** sigue aceptándose `pes:{id}|{fecha}|{hora}` (3 partes); el intervalo se toma de la versión vigente ese día.

Expansión en submit de UI/API: `UiScreenService::expandTurnosSlotIdParams` para acciones `crear-como-paciente` y `reprogramar-como-paciente`.

## Ocupación por solapamiento

- Servicio: `TurnoSlotOccupancyService`.
- Estados que bloquean: `Turno::ESTADOS_PARA_DESHABILITAR` (p. ej. `PENDIENTE`, `EN_ATENCION`, `ATENDIDO`).
- Dos rangos se solapan si `inicio_a < fin_b` y `inicio_b < fin_a`.
- Conflictos **pendientes** de reprogramación también bloquean franjas (`TurnoAgendaConflicto::existePendienteParaPesEnFranja`).

`Turno::estaOcupadoSlotPorProfesionalEfectorServicio` delega en este modelo (ya no es igualdad estricta de `hora`).

## Conflictos tras cambio de agenda

### Tabla `turno_agenda_conflicto`

- Turnos futuros `PENDIENTE` cuya `hora` no cae en la nueva grilla → fila `estado = pendiente`.
- `opcion_hora_antes` / `opcion_hora_despues`: vecinos en la grilla (`AgendaSlotEngine::vecinosEnGrilla`).
- Estados: `pendiente`, `resuelto_reprogramado`, `resuelto_cancelado`.

### Paciente

**Resolver conflicto**

```http
POST /api/v1/turnos/resolver-conflicto-agenda-como-paciente
Content-Type: application/x-www-form-urlencoded

id={id_turnos}&eleccion=antes|despues|cancelar
```

Permiso: `/api/turnos/resolver-conflicto-agenda-como-paciente`.

**Listados:** en `listar-como-paciente` (y derivados) cada turno puede incluir:

- `agenda_conflicto_pendiente`: bool
- `agenda_conflicto`: `{ id, id_turno, opcion_antes, opcion_despues }` si aplica
- Filtro `solo_agenda_conflicto=1` con `alcance=pendientes` (solo turnos con conflicto abierto)
- En `elegir-pendiente-como-paciente`, ítems con conflicto muestran prefijo `⚠` en el label

**Asistente (paciente):** intent `turnos.conflicto-agenda-flow` → `elegir-conflicto-agenda-como-paciente` → `elegir-resolucion-conflicto-agenda-como-paciente` → POST resolver.

### Staff

| Método | Ruta |
|--------|------|
| GET\|POST | `/api/v1/profesional-agenda/elegir-conflicto-agenda` |
| GET\|POST | `/api/v1/profesional-agenda/elegir-resolucion-conflicto-agenda-para-paciente` |
| POST | `/api/v1/profesional-agenda/resolver-conflicto-agenda-para-paciente` |

Intent: `agenda.resolver-conflictos-staff-flow`. Servicio: `TurnoAgendaConflictoService`.

## Código de referencia

| Pieza | Ubicación |
|-------|-----------|
| Migración | `common/migrations/m260516_000001_agenda_intervalo_versioning.php` |
| Modelos | `ProfesionalEfectorServicioAgendaVersion`, `TurnoAgendaConflicto` |
| UI agenda | `ProfesionalEfectorServicioAgendaUiService`, `configurar-agenda.json` |
| Slots / ocupación | `AgendaSlotEngine`, `TurnoSlotFinder`, `TurnoSlotOccupancyService`, `TurnoReservaSlotService` |
| API turnos | `TurnosController::actionResolverConflictoAgendaComoPaciente` |
| Preview JS | `frontend/web/js/widgets/ui-widget-agenda-config-preview.js` |

## Despliegue

1. Aplicar migración (`php yii migrate`). Si falló a medias, la migración es **idempotente** (recrea FK, alinea tipo de `id_turno` con `turnos.id_turnos`, convierte `turnos` a InnoDB si hace falta).
2. Registrar permisos RBAC nuevos y asignar roles.
3. Clientes móvil/web: consumir `slot_id` de 4 partes cuando venga en oferta; pantalla de conflicto usando `agenda_conflicto` + endpoint de resolución.

## Documentación relacionada

- [Intents turnos/agenda](./intents-turnos.md)
- [API nomenclatura y RBAC](./API-nomenclatura-y-RBAC.md)
- [Dominio PES y agenda](../dominio/PROFESIONAL_EFECTOR_SERVICIO.md)
- [Reprogramación](./reprogramacion-ui.md)
- [Contrato slots / PES](../dominio/MIGRACION_PES_ESTADO.md)
