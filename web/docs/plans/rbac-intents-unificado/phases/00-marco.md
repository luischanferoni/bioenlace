# Fase 0 — Marco y piloto

## Objetivo

Fijar alcance, convenciones de nombres y dominio piloto antes de tocar código. Salida: acuerdo de equipo + checklist en este documento marcado.

## Convenciones de nombres (propuesta)

| Elemento | Convención | Ejemplo |
|----------|------------|---------|
| Intent create staff | `{dominio}.{accion}-para-{sujeto}-flow` | `turnos.crear-para-paciente-flow` |
| Intent create paciente | `{dominio}.{accion}-como-paciente` | `turnos.crear-como-paciente` |
| Intent edit propio | `{entidad}.editar-propio` | `condicion-laboral.editar-propio` |
| Intent edit staff | `{entidad}.editar-staff` | `condicion-laboral.editar-staff` |
| Intent list/info efector | `{entidad}.listar-efector` / `{entidad}.info-efector` | `profesionales.listar-efector` |
| Variante campos | sufijo rol funcional | `condicion-laboral.editar-staff-enfermero` |
| Familia NL | `intent_family` en YAML | `condicion-laboral.edit` |

Validar en kickoff si se prefiere un solo prefijo de dominio (`organization.` vs nombre corto).

## Piloto obligatorio

**Condición laboral** (Organization / PES):

- Ya tiene endpoints UI JSON y políticas `condicion_laboral_own` / `condicion_laboral_staff`
- Ya tiene flows create relacionados (`licencia.cargar-*-flow`)
- Permite validar variante propio vs staff sin pasar por DataAccess genérico

**Segundo piloto recomendado** (fase 3): listado/conteo profesionales en efector (hoy en `ProfesionalEfectorServicio.yaml` → `info_list`).

## Fuera de alcance fase 0–1

- Migración masiva de todas las entidades `data-access-config`
- Cambio del modelo PES → rol dinámico
- Rediseño de menús `BioenlaceGhostNav` (se alinea cuando cada pantalla tenga intent explícito)

## Entregables

- [ ] Kickoff: decisiones R1–R8 aceptadas por el equipo (ver `README.md`)
- [ ] Tabla piloto: intents nuevos ↔ roles ↔ rutas ↔ `domain_operation`
- [ ] Lista de intents genéricos a deprecar (`data-access.*`) con fecha objetivo
- [ ] Estrategia migración `auth_item` atributos: quién ejecuta, en qué entorno

## Matriz de prueba piloto (borrador)

| Caso | Rol | Intent | Recurso | Resultado esperado |
|------|-----|--------|---------|-------------------|
| Editar propia CL | Médico | `editar-propio` | Su PES | 200 |
| Editar CL ajena | Admin efector | `editar-staff` | PES del efector sesión | 200 |
| Editar CL ajena sin intent staff | Médico solo propio | `editar-staff` | PES ajeno | 403 API |
| Editar CL ajena | Admin | `editar-staff` | PES otro efector | 403 dominio |
| Campo no en YAML | Cualquiera | intent válido | POST campo extra | Ignorado/rechazado servicio |

## Estado

Pendiente — plan documentado jun 2026; implementación no iniciada.
