# Plan — Representación de paciente (FHIR)

| Campo | Valor |
|-------|--------|
| Slug | `representacion-paciente-fhir` |
| Estado | Fases 1–2 implementadas — Fase 3 pendiente |
| Objetivo | Vínculos padre/madre/tutor ↔ menor sin cuenta (A) y delegación paciente → representante (B), con actuación en turnos y trayectoria clínica |

## Decisiones de producto (cerradas)

| # | Tema | Decisión |
|---|------|----------|
| A1 | Quién opera por menor | Padre, madre y tutor legal (con documento) |
| A2 | Dos padres separados | Ambos pueden operar si están verificados |
| A3 | Mayoría de edad | El vínculo A **no** corta solo a los 18; revoca staff o el hijo cuando tenga cuenta |
| B4 | Aceptación del representante | **No** obligatoria; alcanza designación del paciente |
| B5 | Representantes simultáneos | **Varios** activos con mismos permisos |
| B6 | Quién puede ser representante | **Cualquier** persona con cuenta que el paciente elija |
| B7 | Permisos v1 | Turnos, motivos, pre-consulta, recetas/tratamientos, historia clínica |
| L8 | Orden judicial / custodia | Staff puede **bloquear** sin borrar |
| N9 | Notificación al paciente | Solo si el paciente lo activa en configuración |

Menor de edad: **nunca** inicia sesión. Staff puede revocar vínculos creados por pacientes.

## Índice

- [overview.md](./overview.md)
- [design.md](./design.md)
- [phases/01-dominio-fhir-y-catalogo.md](./phases/01-dominio-fhir-y-catalogo.md)
- [phases/02-regimen-a-tutela-staff.md](./phases/02-regimen-a-tutela-staff.md)
- [phases/03-regimen-b-delegacion.md](./phases/03-regimen-b-delegacion.md)
- [phases/04-autorizacion-transversal-api.md](./phases/04-autorizacion-transversal-api.md)
- [phases/05-clientes-movil-asistente.md](./phases/05-clientes-movil-asistente.md)

## Al cerrar el programa

Volcar narrativa estable a `producto/representacion-paciente.md` y borrar esta carpeta (`plans/README.md`).
