# Medicina clínica como hub de reserva (paciente)

## Modelo operativo

1. **Medicina clínica / generalistas** es la **única puerta** de autogestión del paciente en `atencion.necesito-atencion` (y listados con `reserva_modo=hub_paciente`).
2. Atienden **consultas no urgentes** (triage bandas B–D): síntomas nuevos, controles crónicos, trámites.
3. Actúan como **filtro y derivador**: el especialista interviene después, cuando el clínico lo indica.
4. **Especialistas** (oftalmología, dermatología, etc.):
   - No aparecen en la lista de servicios del flujo de atención del paciente.
   - Turno solo si existe **derivación vigente** (`ConsultaDerivaciones` en espera).
   - En ese caso la modalidad es **solo teleconsulta** (videollamada).

Urgencias (banda A / alarmas halt) siguen sin completar reserva en la app.

## Metadata

| Archivo | Uso |
|---------|-----|
| `reserva_triage_servicio_map_v1.yaml` | `acceso.hub_rol`, flags `autogestion_paciente`, `teleconsulta_solo_con_derivacion` por rol |
| `reserva_triage_catalog_v1.yaml` | Todos los nodos ambulatorios → `suggests_servicio_rol: medicina_clinica` |

## Servicios de dominio

| Servicio | Responsabilidad |
|----------|-----------------|
| `ReservaTriageServicioMapService` | Hub, roles, match servicio ↔ rol |
| `ReservaTriageServicioSugeridoService` | Filtra listado hub; valida reserva paciente |
| `TeleconsultaElegibilidadService` | Especialista + derivación → solo teleconsulta |
| `TurnoPersistService` | Rechaza especialista sin derivación; fuerza teleconsulta si derivación |

## Flujo asistente

`select_servicio` → `servicios.elegir-acepta-turnos?reserva_modo=hub_paciente&triage_*=…`

Solo servicios que coinciden con rol `medicina_clinica` y tienen turnos habilitados.

## Evolución

- Intent/flujo dedicado **“Tengo una derivación”** para reservar especialista con teleconsulta.
- UI staff: marcar profesionales como generalistas vs especialistas en PES (hoy se infiere por servicio).

Ver también: [triage-reserva-turno.md](./triage-reserva-turno.md), [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md).
