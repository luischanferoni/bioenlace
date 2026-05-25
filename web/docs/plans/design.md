# Design — Planes activos

## Regla principal

**Solo planes en ejecución.** Al cerrar la construcción, eliminar `plans/<slug>/` por completo.

## Sin enlaces desde fuera

`producto/`, `arquitectura/`, `his-completo/`, `costos/`, `decisions/` y el [README global](../README.md) **no** referencian rutas bajo `plans/`. Si hace falta contar el recorrido del feature de punta a punta, eso va en `producto/<tema>.md` en lenguaje natural.

Los comentarios en código tampoco deberían apuntar a `web/docs/plans/…` como documentación duradera; como mucho, nota breve en el PR o en el plan mientras exista.

## Estructura mientras el plan está abierto

```text
plans/<slug>/
  README.md
  overview.md
  design.md
  phases/
```

Opcional durante la ejecución: estado de migraciones **solo** mientras el plan sigue abierto.

## Cierre

1. Narrativa y comportamiento → `web/docs/producto/<tema>.md`.
2. Decisiones transversales cerradas → `web/docs/decisions/`.
3. Borrar `plans/<slug>/` y quitar la fila de [README.md](./README.md).

## Alternativa descartada

`plans/completed/` o planes archivados: duplica lo que ya está en producto y enlaces rotos cuando se borra el plan.
