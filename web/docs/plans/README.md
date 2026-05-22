# Planes en ejecución

Solo **planes largos activos** (multi-fase, varios PR). Cuando un plan termina, se elimina su carpeta aquí; lo operativo queda en dominios (`Turnos/`, `asistente/`, `dominio/`, `decisions/`).

## Planes activos

| Plan | Carpeta | Estado |
|------|---------|--------|
| Laboratorio externo FHIR | [laboratorio-external-fhir/](./laboratorio-external-fhir/README.md) | en curso |

Para abrir un plan: crear `plans/<slug>/` según [design.md](./design.md) y registrar la fila anterior.

## Convenciones

- [overview.md](./overview.md)
- [design.md](./design.md)

## Fuera de `plans/`

| Necesidad | Dónde |
|-----------|--------|
| Decisiones cerradas | [decisions/](../decisions/README.md) |
| Flujos y contratos vigentes | [dominios en `web/docs/`](../README.md) |
| Código clínico | `common/components/Clinical/README.md` |
