# Plan: Clientes delgados y contrato de API

Programa de trabajo largo (varios días, varios PR). Temporal: al terminar se borra esta carpeta y lo vigente pasa a `producto/` y `decisions/`.

Lectura: [overview.md](./overview.md), [design.md](./design.md), fases en [phases/](./phases/).

## Seguimiento

| Fase | Estado |
|------|--------|
| 00 Línea base y guardas | en curso (el guard local falla: `domain_literal_messages` 764/701; baseline no elevado) |
| 01 Contrato de API | hecho (migración de respuestas estándar y consumidores; login legacy preservado como excepción externa) |
| 02 Terminología encounter | pendiente de alineación/revisión humana de prompts protegidos; identificadores Dart y rutas API en 0, nombres PHP restantes de teleconsulta documentados |
| 03 Metadata clínica humana | en curso (capacidades de encounter, secciones de odontología/oftalmología, niveles Manchester, presentación de episodios y etiquetas/filtros del timeline; documentación profesional y metadata de signos vitales consumida como fallback por web/móvil; faltan unificar modelos clínicos y resolver acciones por rol) |
| 04 Captura y STT | en curso (audio+STT local y decisión server-side implementados; falta abstraer el sujeto genérico; validación en dispositivo real no bloquea el cierre) |
| 05 Móvil | en curso (inicio y configuración por capacidades; wizard limita clases por efector usando metadata del backend) |
| 06 Web | hecho (contrato `client_open`, renderer y vistas delgadas; auditoría dentro de topes: 0/226 HTML concatenado, 38/65 textos UI y 0/39 vistas con lógica; smoke tests HC/captura/tablero pendientes como validación no bloqueante) |
| 07 Veterinaria (estructura) | en curso (esqueleto backend/móvil materializado; pendiente confirmación manual del MVP de endpoints/catálogos y abstracción de sujeto de fase 04) |
| 08 Asistente, admin, jobs, docs | pendiente revisión humana de prompts reservados; copy de jobs verificado |
| 09 Cierre | pendiente |

## Reglas relacionadas

Capas y metadata sin hardcode, source of truth de catálogos, UI nativas embebibles, Camino A web, vistas Yii delgadas, UI JSON en móvil.

## Pendiente de decidir

Ver la sección «Decisiones sobre veterinaria y vocabulario» en [design.md](./design.md). Pendiente: diseño de qué se separa del modelo de agenda para efectores veterinarios, cuando se defina el MVP.
