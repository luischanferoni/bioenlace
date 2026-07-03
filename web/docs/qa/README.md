# Guía de pruebas — Bioenlace

Documentación para **probar la app y la web**: qué hacer, qué deberías ver y qué marcar en el checklist.

## Por escenario clínico (recomendado para pruebas integrales)

| Carpeta | Contenido |
|---------|-----------|
| [escenarios/](./escenarios/README.md) | Guías paso a paso: ambulatorio, urgencia, seguimiento, internación |

Cada guía incluye guion de consulta, pasos del paciente, pasos del personal y cuándo esperar avisos en el celular.

## Por rol / audiencia

| Carpeta | Quién prueba | Rol RBAC | Superficie |
|---------|--------------|----------|------------|
| [paciente/](./paciente/README.md) | Usuario paciente | `paciente` | App móvil paciente |
| [app-personalsalud/](./app-personalsalud/README.md) | Cualquier personal con usuario del centro | Según rol | App móvil Personal de Salud |
| [medico/](./medico/README.md) | Médico / profesional clínico | `Medico` | Web y app Personal de Salud (captura, agenda propia) |
| [staff/](./staff/README.md) | Recepción, enfermería, coordinación | `Administrativo`, `enfermeria`, … | Web y app Personal de Salud (operación diaria) |
| [admin_efector/](./admin_efector/README.md) | Admin del centro | `AdminEfector` | Web frontend |

Referencia cruzada: [quien-puede-que.md](./quien-puede-que.md). Mapeo desde BD: [roles-desde-bd.md](./roles-desde-bd.md).

## Convenciones

- Título **Web — …** o **App — …**
- Pasos numerados (vos / el sistema)
- **Intent** cuando el flujo pasa por el asistente
- **Atajo** (no “menú”) para acciones visibles del chat
- En web staff: panel **Pacientes** + **Asistente**

Si algo pide credenciales o datos de prueba que no tenés, pedilos al responsable del entorno (staging).

Más contexto de producto (no orientado a QA): [docs/producto/](../producto/README.md).
