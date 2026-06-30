# Overview — Urgencias: triage + tablero operativo

## Objetivo

Llevar el módulo de **guardia/urgencias** de “ingreso + lista pendiente” a un circuito **hospitalario usable**: triage estructurado con prioridad, **tablero operativo** en tiempo casi real para el equipo, y **flujo médico optimizado para teléfono**, sin duplicar negocio fuera de la API v1.

## Principio de clientes

| Rol | Cliente principal | Uso |
|-----|-------------------|-----|
| **Médico de guardia** | App móvil médico (`mobile/personalsalud`) | Triage rápido, tomar paciente, abrir captura EMER, cerrar atención |
| **Staff** (admisión, enfermería, coordinación) | Web Yii + misma app móvil donde aplique | Tablero de cola, ingreso, reasignación, tiempos, derivación |
| **Paciente** | Fuera de alcance de este plan | Sin cambios de producto paciente en triage |

No contrastar “app vs web”: es **Bioenlace** con la misma API; la diferencia es **qué pantalla priorizamos** por rol.

## Resultado esperado (MVP del programa)

1. **Triage** registrado con escala configurable (por defecto Manchester), motivo de consulta, signos vitales mínimos y timestamp; prioridad numérica/etiqueta para ordenar cola.
2. **Tablero** por efector: pacientes en guardia con estado de circuito, prioridad, tiempo en espera, profesional asignado, acciones (asignar, llamar, derivar).
3. **Móvil médico**: cola ordenada por prioridad → detalle triage → “Atender” → captura clínica existente (`PARENT_GUARDIA`) → marcar atendido/finalizar según reglas actuales extendidas.
4. **Trazabilidad de tiempos** (door-to-triage, door-to-doctor) persistida para Fase 5.

## Fuera de alcance inicial

- Facturación y autorización de obra social en guardia.
- Pedidos de laboratorio/imagen “embebidos” solo en módulo guardia (reutilizar flujos clínicos ya existentes en captura).
- Mapa de camas / internación completa (solo **derivación** con enlace al flujo de internación ya existente).
- Asistente conversacional para triage (Fase posterior; UI JSON genérica si se necesita acceso desde chat).
- Paciente auto-triage o kiosk.
- Certificación normativa de escala (configuración institucional sí; auditoría externa no).

## Fases

Ver carpeta [phases/](./phases/). Orden recomendado: **0 → 1 → 2** en paralelo parcial con **3**; **4** y **5** al estabilizar MVP.

## Criterios de cierre del programa

- [ ] Checklist [02-urgencias.md](../../his-completo/02-urgencias.md): triage y tablero marcados como hechos.
- [ ] Documento `producto/urgencias-guardia.md` publicado.
- [ ] Carpeta `plans/urgencias-triage-tablero/` eliminada (regla de [plans/README.md](../README.md)).
