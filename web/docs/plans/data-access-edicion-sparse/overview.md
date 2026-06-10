# Overview — Edición dispersa DataAccess

## Problema

Hoy `data-access.info` y `data-access.listar` cubren **lectura** staff con permisos por grupo de atributos. La edición sigue en flows YAML largos (p. ej. agenda) o formularios ad hoc.

## Propuesta

Tercer canal **`data-access.editar`**:

1. Cortar temprano si el usuario no tiene **ningún** aspecto con `write`.
2. Elegir **superficie** (ej. personal del efector).
3. Elegir **sujeto** (reutilizar métrica listar si hace falta desambiguar).
4. Elegir **aspectos** a editar (no wizard tipo CREATE).
5. Formulario parcial + **confirmación** + mutación vía Services de dominio.

## Tres `kind` de aspecto

| kind | Uso |
|------|-----|
| `scalar_group` | Campos en ui_json `fields` |
| `open_ui` | Widget / pantalla (`profesional-agenda.configurar-agenda`) |
| `nl_patch` | Atajo conversacional “cambiá X a Y” (fase posterior) |

## Fuera de alcance inicial

- PATCH SQL genérico sin Service de dominio
- Reemplazar todos los flows `crear-*` de alta
- API FHIR Write
