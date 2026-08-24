# Triage al reservar turno (paciente)

## De qué se trata

Antes de elegir **servicio y horario**, el paciente recorre un **árbol fijo** en lenguaje simple: motivo, alarmas, zona y evolución. No es diagnóstico. Alimenta el turno y la preparación del encuentro.

La puerta de producto es [Solicitar Atención](./solicitar-atencion.md). Este documento cubre **alarmas, bandas y persistencia**. Motivos ricos (chat/intake) después de reservar: [recorrido-pre-post-consulta.md](./recorrido-pre-post-consulta.md).

## Principios

1. **Seguridad primero:** alarma en **banda A** → no se reserva en la app; derivación a urgencia / 107.
2. **Catálogo declarativo:** nodos en metadata de scheduling (`reserva_triage_catalog_v1.yaml`). El flujo conversacional es `atencion.necesito-atencion`.
3. **IA después:** texto libre opcional en confirmación; el lote de motivos pre-consulta es el canal rico de IA.

*«Sacar turno»* sin motivo clínico usa `turnos.crear-como-paciente` (sin este árbol).

## Recorrido

| Paso | Qué hace |
|------|----------|
| Motivo raíz | Malestar nuevo, Control/Seguimiento, Urgencia (y estudio/práctica en Solicitar Atención). Urgencia va directo a 107/guardia (sin elegir tipo). |
| Hub control | Solo Control/Seguimiento — [consultas-seguimiento.md](./consultas-seguimiento.md) |
| Alarmas | Si banda A en malestar → pantalla 107 / guardia, sin cupo |
| Zona, detalle, evolución | Según el malestar; ocho sistemas corporales (cabeza, pecho, digestión, musculoesquelético, piel, ojos/boca, genitourinario, general) |
| Servicio del centro | Oferta institucional; en ambulatorio suele ser medicina clínica — [medicina-clinica-hub-reserva.md](./medicina-clinica-hub-reserva.md) |
| Modalidad | Solo si el servicio y el triage permiten remoto — [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md) |
| Centro → profesional → horario | Igual que la reserva de agenda |

## Bandas

| Banda | Significado |
|-------|-------------|
| A | Alarma actual → no reservar en app |
| B | Prioridad alta / evaluar presencial pronto |
| C | Ambulatorio programable habitual |
| D | Control / trámite / baja urgencia |

El turno guarda el código de hoja, la banda máxima y la trayectoria del catálogo. No se persiste una reserva en banda A.

## Relación con teleconsulta

La política del **servicio del centro**, los nodos del catálogo y el switch de agenda del profesional (`acepta_consultas_online`) deciden si aparece videollamada. Detalle: [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md). Si no hay cupo tras el triage, un agente puede sugerir canal alternativo — [agentes-autonomos.md](./agentes-autonomos.md) (A05).

Ver también: [solicitar-atencion.md](./solicitar-atencion.md), [turnos.md](./turnos.md).
