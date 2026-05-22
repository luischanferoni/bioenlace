# Design — Planes activos

## Regla principal

**Solo planes en ejecución.** Al cerrar el plan, eliminar `plans/<slug>/` por completo. Decisiones que sigan vigentes → `web/docs/decisions/`. Comportamiento operativo → dominio correspondiente (`dominio/flows/`, `Turnos/`, etc.).

## Estructura mientras el plan está abierto

```text
plans/<slug>/
  README.md       ← índice, dueño, estado
  overview.md     ← alcance del programa
  design.md       ← decisiones propias del plan (si aún no están cerradas)
  phases/         ← una fase por archivo, checklist
```

Opcional durante la ejecución: `MIGRATION_STATUS.md` o tablero equivalente **solo** mientras el plan sigue abierto.

## Abrir un plan

1. Crear `plans/<slug>/` (`kebab-case`, ej. `laboratorio-external-fhir`).
2. Añadir fila en [README.md](./README.md).
3. Al cerrar: mover decisiones finales a `decisions/` si aplica, luego **borrar** `plans/<slug>/`.

## Alternativa descartada

Mantener `plans/completed/` o programas archivados — añade ruido; el repo y los dominios ya documentan lo construido.
