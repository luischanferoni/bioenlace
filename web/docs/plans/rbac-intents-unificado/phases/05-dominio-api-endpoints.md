# Fase 5 — Dominio, API y piloto condición laboral

## Objetivo

Implementar el **piloto de referencia** (condición laboral + resolución PES) con intents explícitos, dominio en persistencia y alineación de rutas API existentes.

## Intents piloto (nombres ilustrativos)

| intent_id | operation | rbac_route (existente o nueva) | domain_operation |
|-----------|-----------|-------------------------------|------------------|
| `condicion-laboral.editar-propio` | edit | `GET|POST …/editar-condicion-laboral` (variante sin listar terceros) o ruta dedicada | `ProfesionalEfectorServicio.condicion_laboral_own` |
| `condicion-laboral.editar-staff` | edit | `…/editar-condicion-laboral` + flujo con selección PES | `ProfesionalEfectorServicio.condicion_laboral_staff` |
| `condicion-laboral.editar-staff-enfermero` | edit | Misma ruta; YAML con menos `fields` | `ProfesionalEfectorServicio.condicion_laboral_staff` |

Ajustar nombres en implementación según convención acordada en fase 0.

## Tareas

### 5.1 YAML intents

- `subject_resolution` staff: reutilizar listado PES efector (`listar-por-efector-servicio` o handler existente)
- `fields` / `field_groups`: alinear con `crear-condicion-laboral.json` / `editar-condicion-laboral.json`
- `intent_family`: `condicion-laboral.edit`
- Enlazar flows create `licencia.cargar-*` si deben compartir familia o rutas

### 5.2 Controller / servicio

- `ProfesionalEfectorServicioController`: assert dominio vía `ProfesionalEfectorServicioDomainAuthorizationService` según intent/contexto del request (header flow o parámetro declarado)
- `submitCondicionLaboral`: whitelist campos según manifest del intent (no RBAC atributo)
- Listar PES: política `organization.pes_efector` / sesión

### 5.3 RBAC

- Sync intents piloto → rutas en `auth_item`
- Asignar a roles piloto en staging

### 5.4 Políticas dominio

- Revisar si `condicion_laboral_staff` / `_own` pueden simplificarse una vez el intent fija el contexto
- Mantener chequeo `id_pes` ∈ efector / propio

### 5.5 Tests

- API: matrix fase 0
- Unit: whitelist campos en submit

## Entregables

- [ ] Piloto condición laboral funcional con intents
- [ ] Documentación de mapeo grants legacy → intents piloto (input fase 6)
- [ ] Demo asistente + API directa

## Dependencias

- Fase 0 (matriz)
- Fase 1 mínima (contrato YAML)
- Fase 2 puede hacerse en paralelo para asignar roles en admin

## Estado

Pendiente.
