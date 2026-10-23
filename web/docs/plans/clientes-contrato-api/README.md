# Plan: Clientes delgados y contrato de API

Programa de trabajo largo (varios días, varios PR). Temporal: al terminar se borra esta carpeta y lo vigente pasa a `producto/` y `decisions/`.

Lectura: [overview.md](./overview.md), [design.md](./design.md), fases en [phases/](./phases/).

## Seguimiento

| Fase | Estado |
|------|--------|
| 00 Línea base y guardas | en curso (guard local en verde: `domain_literal_messages` 697/697 y `mobile_literal_ui_text` 90/90 tras externalizar mensajes; topes de web y vistas también bajaron; falta CI, que el repositorio no tiene) |
| 01 Contrato de API | hecho (migración de respuestas estándar y consumidores; login legacy preservado como excepción externa) |
| 02 Terminología encounter | pendiente de alineación/revisión humana de prompts protegidos; identificadores Dart y rutas API en 0, nombres PHP restantes de teleconsulta documentados |
| 03 Metadata clínica humana | hecho (metadata de episodios, triage por catálogo, secciones genéricas con fallback legacy en web y Personal de Salud, acción de triage por capability RBAC y `ambito_atencion` en la sesión, derivado de `Servicio.nombre = 'VETERINARIA'`; la UI de odontología/oftalmología sobre `specialtyMetadata` pasa a otro plan; `secciones` en internación es alias temporal) |
| 04 Captura y STT | en curso (audio+STT local y decisión server-side implementados; el sujeto genérico queda bloqueado por el diseño veterinario y por prompts reservados, ver la ficha; validación en dispositivo real no bloquea el cierre) |
| 05 Móvil | en curso (inicio/configuración por capacidades; formularios, selector, chats y acciones async compartidos; clientes de chat/async preservan metadata de errores del contrato; faltan migrar otras pantallas, cliente HTTP/error uniforme y guardas de textos) |
| 06 Web | hecho (contrato `client_open`, renderer y vistas delgadas; auditoría dentro de topes: 0/226 HTML concatenado, 38/65 textos UI y 0/39 vistas con lógica; smoke tests HC/captura/tablero pendientes como validación no bloqueante) |
| 07 Veterinaria (estructura) | en curso, exploratoria (~60% estructural: esqueleto backend/móvil y glosario materializados, propuesta de endpoints en borrador; bloqueada para comportamiento hasta definir clasificación canónica del servicio/PES, compatibilidad de agenda/efector, vínculo/permisos tutor-animal, catálogos y sujeto genérico de fase 04) |
| 08 Asistente, admin, jobs, docs | pendiente revisión humana de prompts reservados; copy de jobs verificado |
| 09 Cierre | pendiente |

## Reglas relacionadas

Capas y metadata sin hardcode, source of truth de catálogos, UI nativas embebibles, Camino A web, vistas Yii delgadas, UI JSON en móvil.

## Pendiente de decidir

Ver la sección «Decisiones sobre veterinaria y vocabulario» en [design.md](./design.md). Pendiente: diseño de qué se separa del modelo de agenda para efectores veterinarios, cuando se defina el MVP.
