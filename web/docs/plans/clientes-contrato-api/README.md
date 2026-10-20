# Plan: Clientes delgados y contrato de API

Programa de trabajo largo (varios días, varios PR). Temporal: al terminar se borra esta carpeta y lo vigente pasa a `producto/` y `decisions/`.

Lectura: [overview.md](./overview.md), [design.md](./design.md), fases en [phases/](./phases/).

## Seguimiento

| Fase | Estado |
|------|--------|
| 00 Línea base y guardas | hecho (guarda local; sin CI en el repo) |
| 01 Contrato de API | en curso (base lista; piloto: audio) |
| 02 Terminología encounter | pendiente |
| 03 Metadata clínica humana | pendiente |
| 04 Captura y STT | pendiente |
| 05 Móvil | pendiente |
| 06 Web | pendiente |
| 07 Veterinaria (estructura) | pendiente |
| 08 Asistente, admin, jobs, docs | pendiente |
| 09 Cierre | pendiente |

## Reglas relacionadas

Capas y metadata sin hardcode, source of truth de catálogos, UI nativas embebibles, Camino A web, vistas Yii delgadas, UI JSON en móvil.

## Pendiente de decidir

Ver la sección «Decisiones sobre veterinaria y vocabulario» en [design.md](./design.md). Pendiente: diseño de qué se separa del modelo de agenda para efectores veterinarios, cuando se defina el MVP.
