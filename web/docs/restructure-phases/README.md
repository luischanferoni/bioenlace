# Reestructuración de documentación — fases

Plan para migrar todo `web/docs/` al esquema por dominio: `README` + `overview` + `design` + `flows/` (+ `decisions/` global).

**Estado:** [STATUS.md](./STATUS.md)

| Fase | Alcance |
|------|---------|
| [01-dedupe-y-rutas](./phases/01-dedupe-y-rutas.md) | Eliminar duplicados; unificar enlaces `turnos/` |
| [02-producto-paciente](./phases/02-producto-paciente.md) | App paciente, registro, capacidades |
| [03-captura-clinica](./phases/03-captura-clinica.md) | Audio/texto, encounter, timeline IA |
| [04-plataforma](./phases/04-plataforma.md) | Cloud, anotaciones API, plan de trabajo |
| [05-legacy-his](./phases/05-legacy-his.md) | HIS completo, quirófano legacy (índice archive) |
| [06-flujos-turnos](./phases/06-flujos-turnos.md) | Normalizar cabeceras en `turnos/flows/*` |
| [07-enlaces-repo](./phases/07-enlaces-repo.md) | Grep y corrección de rutas en código y docs |
| [08-flujos-plantilla-completa](./phases/08-flujos-plantilla-completa.md) | Plantilla en todos los `flows/` Turnos + asistente |
| [09-plataforma-sin-codigo](./phases/09-plataforma-sin-codigo.md) | Anotaciones API sin bloques de código |
| [10-legacy-his-fisico](./phases/10-legacy-his-fisico.md) | `HIS completo/` → `legacy/his-completo/` |
