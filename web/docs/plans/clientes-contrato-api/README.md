# Plan: Clientes delgados y contrato de API

Programa de trabajo largo (varios días, varios PR). Temporal: al terminar se borra esta carpeta y lo vigente pasa a `producto/` y `decisions/`.

Lectura: [overview.md](./overview.md), [design.md](./design.md), fases en [phases/](./phases/).

## Seguimiento

| Fase | Estado |
|------|--------|
| 00 Línea base y guardas | hecho (guarda local; sin CI en el repo) |
| 01 Contrato de API | hecho (migración de respuestas estándar y consumidores; login legacy preservado como excepción externa) |
| 02 Terminología encounter | en curso (glosario, razones/chat, identificación de captura y rutas MVC de documentación/captura) |
| 03 Metadata clínica humana | en curso (capacidades de encounter, secciones de odontología/oftalmología, niveles Manchester, presentación de episodios y etiquetas/filtros del timeline; documentación profesional ahora publica campos genéricos; faltan unificar signos vitales/evolución y completar acciones/roles) |
| 04 Captura y STT | en curso (Personal de Salud y chat de motivos envían audio+STT local; etapas y transcripciones con metadata contractual; queda validar en dispositivo real) |
| 05 Móvil | en curso (inicio y configuración por capacidades; wizard limita clases por efector usando metadata del backend) |
| 06 Web | en curso (consumo `client_open` verificado; unificado un mensaje UI duplicado; faltan Camino A, vistas y humo) |
| 07 Veterinaria (estructura) | pendiente |
| 08 Asistente, admin, jobs, docs | casi completo; pendiente revisión humana de prompts reservados |
| 09 Cierre | pendiente |

## Reglas relacionadas

Capas y metadata sin hardcode, source of truth de catálogos, UI nativas embebibles, Camino A web, vistas Yii delgadas, UI JSON en móvil.

## Pendiente de decidir

Ver la sección «Decisiones sobre veterinaria y vocabulario» en [design.md](./design.md). Pendiente: diseño de qué se separa del modelo de agenda para efectores veterinarios, cuando se defina el MVP.
