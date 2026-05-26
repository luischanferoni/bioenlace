# Conversación y acciones en Bioenlace

## De qué se trata

Pacientes y staff **hacen cosas en lenguaje natural** dentro de Bioenlace: pedir un turno, ver laboratorio, abrir un formulario, seguir un asistente paso a paso sin memorizar menús. Es la misma plataforma que las pantallas de inicio, listas y formularios: no es un producto aparte.

Por detrás hay dos motores (clasificar intención y guiar pasos); la explicación con diagramas está en [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md).

## Qué ve el usuario

- Mensajes claros del sistema conversacional.
- Pantallas embebidas (listas, formularios, confirmaciones) en el mismo hilo o en la pantalla que corresponda.
- Atajos de acciones frecuentes según rol (categorías “turnos”, “laboratorio”, “mis atenciones”…).

## Cómo funciona de punta a punta

```mermaid
flowchart TB
  U[Usuario]
  IF[Interfaz Bioenlace]
  ENV[Sobre del mensaje]
  DOM[Servicios de dominio vía API]
  U --> IF
  IF --> ENV
  ENV -->|clasificar y avanzar flujo| IF
  IF --> DOM
  DOM --> IF
  IF --> U
```

1. El usuario escribe o elige una acción sugerida.
2. El sistema decide **qué intent** corresponde y si tiene **permiso**.
3. Si el flujo es conversacional, avanza por **pasos** (YAML): elegir ítem, confirmar, enviar formulario.
4. Cada paso que necesita datos llama a la **API de negocio** (turnos, clínica, laboratorio); la capa conversacional no guarda la verdad clínica, solo el **borrador** del wizard.

## Otros usos del mismo stack

- Conversación operativa general.
- Motivos de consulta antes del turno.
- Captura clínica (texto o audio del encuentro) con entrypoint dedicado.

Comparten ideas de borrador y permisos; no siempre pasan por el mismo clasificador de intents.

## Intents de dominio (referencia mayo 2026)

Ejemplos de flujos conversacionales con UI JSON (YAML en `SubIntentEngine/schemas/intents/`):

| Dominio | Intent | Uso |
|---------|--------|-----|
| Urgencias | `urgencias.ver-tablero-guardia` | Navegar al tablero EMER |
| Urgencias | `urgencias.triage-paciente-guardia` | Lista sin triage → formulario Manchester |
| Internación | `internacion.mapa-camas-flow` | Mapa de camas del efector |
| Internación | `internacion.alta-estructurada-flow` | Alta con epicrisis y plantilla |
| Agenda | `turnos.indicadores-agenda-flow` | KPIs no-show y lead time (staff) |
| Planes | `tratamiento.adherencia-resumen-staff` | Dashboard adherencia por efector |

Detalle operativo por área: [urgencias-guardia.md](./urgencias-guardia.md), [internacion.md](./internacion.md), [turnos.md](./turnos.md), [planes-de-tratamiento.md](./planes-de-tratamiento.md).

## Costos

Uso de IA en clasificación y respuestas: ver [costos/](../costos/README.md).
