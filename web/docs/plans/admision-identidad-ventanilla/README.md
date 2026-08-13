# Plan — Admisión: identidad alineada a la app y ventanilla

| Campo | Valor |
|-------|--------|
| Slug | `admision-identidad-ventanilla` |
| Estado | En ejecución |
| Superficie | Ingreso guardia (web + app Personal de Salud), `RegistroStaffPacienteService`, rol `Administrativo` |

## Índice

- [overview.md](./overview.md)
- [design.md](./design.md)
- [phases/01-ingreso-dni-sin-alta-libre.md](./phases/01-ingreso-dni-sin-alta-libre.md)
- [phases/02-didit-ventanilla.md](./phases/02-didit-ventanilla.md)
- [phases/03-identidad-pendiente-nn.md](./phases/03-identidad-pendiente-nn.md)
- [phases/04-sesion-ventanilla.md](./phases/04-sesion-ventanilla.md)

## Al cerrar

Volcar a `producto/registro-paciente.md`, `producto/urgencias-guardia.md` (y turnos/representación si aplica la sesión de ventanilla) y borrar esta carpeta.
