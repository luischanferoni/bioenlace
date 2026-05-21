# Dominio transversal — Diseño

## PES como eje operativo

**ProfesionalEfectorServicio (PES)** identifica al profesional en un efector para un servicio concreto. Agenda, slots, turnos y muchas rutas API usan `id_profesional_efector_servicio`.

**Alternativa descartada:** seguir indefinidamente con pares `id_rrhh` + `id_servicio` sin registro PES unificado.

Detalle: [flows/PROFESIONAL_EFECTOR_SERVICIO.md](./flows/PROFESIONAL_EFECTOR_SERVICIO.md), estado: [flows/MIGRACION_PES_ESTADO.md](./flows/MIGRACION_PES_ESTADO.md).

## Relaciones en API

Respuestas JSON pueden incluir relaciones anidadas vía convención `expand` documentada en [flows/API_RELACIONES_EXPAND.md](./flows/API_RELACIONES_EXPAND.md).

## Modelos AR

Convenciones de nombres y dueño de relaciones: [flows/CONVENCIONES_RELACIONES_AR.md](./flows/CONVENCIONES_RELACIONES_AR.md).
