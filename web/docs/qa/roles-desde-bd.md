# Roles RBAC (referencia desde BD)

[← Índice](./README.md)

Fuente: `u257309594_bioenlace.sql` (`auth_item` type=1, `auth_item_child`). Los nombres son los de la columna `name` en la BD.

## Carpetas QA ↔ rol

| Carpeta QA | Rol(es) RBAC | Superficie |
|------------|--------------|------------|
| [paciente/](./paciente/README.md) | `paciente` | App móvil |
| [medico/](./medico/README.md) | `Medico` | Web y app Personal de Salud (captura, guardia, internación clínica) |
| [staff/](./staff/README.md) | `Administrativo`, `enfermeria`, otros operativos | Web y app Personal de Salud (recepción, guardia, internación, reportes) |
| [admin_efector/](./admin_efector/README.md) | `AdminEfector` | Web frontend |

**No confundir:** `AdminEfector` administra **su efector** en la web clínica (`editarEfector` → `/efectores/update`, servicios, PES, usuarios). No es el usuario plataforma con acceso a `/admin` (`user.superadmin = 1`); ese ámbito queda fuera de esta guía QA.

## Roles type=1 relevantes (muestra)

| name | Uso QA |
|------|--------|
| `paciente` | Paciente app |
| `Medico` | Médico web |
| `Administrativo` | Staff recepción / coordinación |
| `enfermeria` | Staff enfermería |
| `AdminEfector` | Admin del efector en web frontend |
| `_x_efector_admin_bioenlace` | Rol plataforma (no es `AdminEfector` de centro) |
| `_x_efector_aditoria` | Auditoría (lectura) |

Otros roles en BD: `facturista`, `edicionPersonas`, `MedicoResidente`, etc. — ver dump.

## Permisos distintivos por rol (SQL)

### `AdminEfector`

Organización del centro en **su** efector:

- `editarEfector` → `/efectores/update`
- `crearServicioEfector`, `editarServicioEfector`, `front_listar_servicios_del_efector`
- `crearProfesionalEfectorServicio`, `front_listar_asignaciones_pes`, `configurar_agenda`
- `crearUsuarioEfector`, `assignRolesToUsers`, `borrarUserEfector`, `asignarEfector`
- `condicion-laboral.editar-staff`, licencias staff, infraestructura (`infraestructuraCama`, …)
- Intents asistente: `profesionales.listado-efector`, `turnos.indicadores-agenda-flow`, `licencia.cargar-para-profesional-flow`

### `Medico`

Captura clínica (`front_crear_consulta`, `front_editar_consulta`, …), guardia, internación clínica. Mucho solapamiento operativo con staff en turnos/guardia.

### `Administrativo` / `enfermeria`

Operación diaria: turnos (`borrarTurno`, `turnos.indicadores-agenda-flow`), guardia, internación, reportes planillas. **Sin** alta de servicios/PES/usuarios efector (eso es `AdminEfector`).
