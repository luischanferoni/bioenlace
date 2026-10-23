# Plan: Clientes delgados y contrato de API

Programa de trabajo largo (varios días, varios PR). Temporal: al terminar se borra esta carpeta y lo vigente pasa a `producto/` y `decisions/`.

Lectura: [overview.md](./overview.md), [design.md](./design.md), fases en [phases/](./phases/).

## Seguimiento

| Fase | Estado |
|------|--------|
| 00 Línea base y guardas | en curso (el guard local falla: `domain_literal_messages` 764/701; baseline no elevado) |
| 01 Contrato de API | hecho (migración de respuestas estándar y consumidores; login legacy preservado como excepción externa) |
| 02 Terminología encounter | pendiente de alineación/revisión humana de prompts protegidos; identificadores Dart y rutas API en 0, nombres PHP restantes de teleconsulta documentados |
| 03 Metadata clínica humana | en curso (~65%: 3 de 7 áreas completas, 3 parciales y 1 pendiente; capacidades de encounter, metadata de odontología/oftalmología con orden explícito y validaciones condicionales, secciones genéricas para evolución de internación, signos vitales y agenda quirúrgica en paralelo a contratos legacy; Personal de Salud prefiere secciones genéricas en evolución de internación, signos vitales y agenda quirúrgica, y web en signos vitales y agenda quirúrgica; internación publica `sections` canónica y alias `secciones` temporal; acción de triage filtrada por capability RBAC; falta un dato de dominio confiable para informar el ámbito del profesional en sesión, migrar más consumidores, generalizar otros modelos y extender permisos por rol al resto de acciones) |
| 04 Captura y STT | en curso (audio+STT local y decisión server-side implementados; falta abstraer el sujeto genérico; validación en dispositivo real no bloquea el cierre) |
| 05 Móvil | en curso (inicio/configuración por capacidades; formularios, selector, chats y acciones async compartidos; clientes de chat/async preservan metadata de errores del contrato; faltan migrar otras pantallas, cliente HTTP/error uniforme y guardas de textos) |
| 06 Web | hecho (contrato `client_open`, renderer y vistas delgadas; auditoría dentro de topes: 0/226 HTML concatenado, 38/65 textos UI y 0/39 vistas con lógica; smoke tests HC/captura/tablero pendientes como validación no bloqueante) |
| 07 Veterinaria (estructura) | en curso (esqueleto backend/móvil materializado; pendiente confirmación manual del MVP de endpoints/catálogos y abstracción de sujeto de fase 04) |
| 08 Asistente, admin, jobs, docs | pendiente revisión humana de prompts reservados; copy de jobs verificado |
| 09 Cierre | pendiente |

## Reglas relacionadas

Capas y metadata sin hardcode, source of truth de catálogos, UI nativas embebibles, Camino A web, vistas Yii delgadas, UI JSON en móvil.

## Pendiente de decidir

Ver la sección «Decisiones sobre veterinaria y vocabulario» en [design.md](./design.md). Pendiente: diseño de qué se separa del modelo de agenda para efectores veterinarios, cuando se defina el MVP.
