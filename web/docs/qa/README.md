# Guía de pruebas — Bioenlace

Documentación para **probar la app y la web**: qué hacer, qué deberías ver y qué marcar en el checklist.

## Cómo usar estos archivos

| Tipo | Archivo | Para qué sirve |
|------|---------|----------------|
| **Checklist** | [10-checklist-ejecutable.md](./10-checklist-ejecutable.md) | Casos numerados (TRN, CTX, TUR…) para marcar hecho / falló |
| **Flujos paso a paso** | `00`–`11` (excepto el checklist `10`) | Entender un módulo antes de probarlo |
| **Roles** | [quien-puede-que.md](./quien-puede-que.md) | Qué puede paciente vs personal |

**Convención en los flujos:** indicar **dónde** probar (web, app, asistente), **cómo** (menú, atajo o frase en el chat) y **resultado esperado** en pantalla.

No hace falta conocer bases de datos, APIs ni nombres internos del código. Si algo pide credenciales o datos de prueba que no tenés, pedilos al responsable del entorno (staging).

## Índice por módulo

| Archivo | De qué habla |
|---------|----------------|
| [00-transversal.md](./00-transversal.md) | Entrar, elegir efector, buscar pacientes |
| [01-captura-clinica.md](./01-captura-clinica.md) | Historia, consulta, guardar atención |
| [02-turnos-agenda.md](./02-turnos-agenda.md) | Turnos, lista de espera, derivaciones |
| [03-urgencias-guardia.md](./03-urgencias-guardia.md) | Guardia, triage, atender |
| [04-internacion.md](./04-internacion.md) | Mapa de camas, ingreso, alta |
| [05-laboratorio-receta-planes.md](./05-laboratorio-receta-planes.md) | Lab, recetas, tratamientos |
| [06-reportes-nomenclador.md](./06-reportes-nomenclador.md) | Nomencladores y planillas |
| [07-asistente.md](./07-asistente.md) | Chat y frases del asistente |
| [08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md) | Registro, sector público/privado, provincia |
| [09-admin-efectores-organizacion.md](./09-admin-efectores-organizacion.md) | Admin efectores y datos de prueba |
| [10-checklist-ejecutable.md](./10-checklist-ejecutable.md) | **Checklist maestro** |
| [11-notificaciones-automaticas.md](./11-notificaciones-automaticas.md) | Avisos automáticos (turnos, guardia, seguimiento) |
| [quien-puede-que.md](./quien-puede-que.md) | Paciente vs personal |

Más contexto de producto (no orientado a QA): [docs/producto/](../producto/README.md).
