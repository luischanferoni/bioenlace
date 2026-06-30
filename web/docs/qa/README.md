# Guía de pruebas — Bioenlace

Documentación para **probar la app y la web**: qué hacer, qué deberías ver y qué marcar en el checklist.

## Por rol / audiencia

| Carpeta | Quién prueba | Rol RBAC | Superficie |
|---------|--------------|----------|------------|
| [paciente/](./paciente/README.md) | Usuario paciente | `paciente` | App móvil |
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
