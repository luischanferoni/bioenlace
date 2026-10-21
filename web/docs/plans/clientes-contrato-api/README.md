# Plan: Clientes delgados y contrato de API

Programa de trabajo largo (varios días, varios PR). Temporal: al terminar se borra esta carpeta y lo vigente pasa a `producto/` y `decisions/`.

Lectura: [overview.md](./overview.md), [design.md](./design.md), fases en [phases/](./phases/).

## Seguimiento

| Fase | Estado |
|------|--------|
| 00 Línea base y guardas | hecho (guarda local; sin CI en el repo) |
| 01 Contrato de API | hecho (migración de respuestas estándar y consumidores; login legacy preservado como excepción externa) |
| 02 Terminología encounter | en curso (glosario, resumen staff, razones/chat y contratos de identificación de captura) |
| 03 Metadata clínica humana | en curso (catálogo de clases encounter expone capacidades y Personal de Salud las consume; falta el resto de metadata clínica) |
| 04 Captura y STT | pendiente |
| 05 Móvil | en curso (inicio y configuración por capacidades; wizard limita clases por efector usando metadata del backend) |
| 06 Web | pendiente |
| 07 Veterinaria (estructura) | pendiente |
| 08 Asistente, admin, jobs, docs | parcial; pendiente de retomar tras las fases anteriores |
| 09 Cierre | pendiente |

## Reglas relacionadas

Capas y metadata sin hardcode, source of truth de catálogos, UI nativas embebibles, Camino A web, vistas Yii delgadas, UI JSON en móvil.

## Pendiente de decidir

Ver la sección «Decisiones sobre veterinaria y vocabulario» en [design.md](./design.md). Pendiente: diseño de qué se separa del modelo de agenda para efectores veterinarios, cuando se defina el MVP.
