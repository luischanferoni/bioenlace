# Medicina clínica como hub de reserva (paciente)

## Modelo operativo

1. **Medicina clínica / generalistas** (servicio institucional con autogestión) es la **única puerta** de autogestión del paciente en `atencion.necesito-atencion` para consulta no urgente (y listados con `reserva_modo=hub_paciente`). Ver [glosario-servicio-pes-acto.md](./glosario-servicio-pes-acto.md).
2. Atienden **consultas no urgentes** (triage bandas B–D): síntomas nuevos, controles crónicos, trámites.
3. Actúan como **filtro y derivador**: otras áreas del centro intervienen después, cuando el clínico lo indica (derivación).
4. **Otras áreas / servicios del centro** que no son hub (p. ej. oftalmología, dermatología — a menudo llamados “especialistas” en UI):
   - No aparecen en la lista de autogestión del flujo de atención del paciente.
   - Turno solo si existe **derivación vigente** (`ConsultaDerivaciones` en espera).
   - En ese caso la modalidad es **solo teleconsulta** (videollamada).
5. **Estudio o práctica** es otro camino raíz: acto SNOMED → oferta(s) del centro con capacidad ECL / `linea_acto` y agenda (no una fila “ECOGRAFIA” en `servicios`).

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
- UI staff: distinguir en PES quién opera como hub (generalista) vs otras áreas (hoy se infiere por servicio del centro).
- **Estudio o práctica** en Solicitar Atención: acto SNOMED → servicios institucionales vía `PedidoAtencionPacienteService`.

Ver también: [glosario-servicio-pes-acto.md](./glosario-servicio-pes-acto.md), [triage-reserva-turno.md](./triage-reserva-turno.md), [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md), [solicitar-atencion.md](./solicitar-atencion.md), [../decisions/pedido-atencion-linea-acto.md](../decisions/pedido-atencion-linea-acto.md).
