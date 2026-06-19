# Intents — mutaciones complejas (update)

Reprogramar turno, alta PES+agenda, etc. **No** duplicar en `data-access-config` como `edit` escalar los campos que muta el flow.

Permiso assignable por rol = permiso del intent (`permission` / `rbac_route`).

## Orden en disco

Prefijo numérico opcional en el nombre del archivo para ordenar en el explorador: `NN-<intent_id>.yaml` (p. ej. `02-condicion-laboral.editar-staff.yaml`). El `intent_id` dentro del YAML **no** cambia. `data-access.editar.yaml` queda sin prefijo (intent genérico de catálogo).

Orden actual:

| # | Archivo |
|---|---------|
| 01 | `01-condicion-laboral.editar-propio.yaml` |
| 02 | `02-condicion-laboral.editar-staff.yaml` |
| 03 | `03-profesional-identidad.editar-staff.yaml` |
| 04 | `04-profesional-agenda.configurar-propio.yaml` |
| 05 | `05-profesional-agenda.configurar-staff.yaml` |
| 06 | `06-profesional-agenda.resolver-conflictos-flow.yaml` |
| 07 | `07-servicio-teleconsulta.configurar-efector-flow.yaml` |
| 08 | `08-turnos.confirmar-asistencia-flow.yaml` |
| 09 | `09-turnos.modificar-como-paciente-flow.yaml` |
| 10 | `10-turnos.no-se-presento-flow.yaml` |
| 11 | `11-turnos.reubicar-como-paciente-flow.yaml` |
| 12 | `12-internacion.alta-estructurada-flow.yaml` |
| 13 | `13-internacion.cambio-cama-flow.yaml` |
| — | `data-access.editar.yaml` |
