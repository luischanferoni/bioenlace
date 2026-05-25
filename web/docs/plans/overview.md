# Overview — `plans/`

## Qué es

Carpeta de **trabajo en curso** para programas largos (semanas o meses, varias fases). Checklists, alcance por fase y notas de migración mientras el equipo construye.

## Qué no es

- Documentación estable del producto (eso es `producto/`).
- Destino de enlaces desde otros `.md` en `web/docs/`.
- Archivo histórico de planes terminados (al cerrar, **se borra** la carpeta).

## Ciclo de vida

1. Abrir `plans/<slug>/` y registrar la fila en [README.md](./README.md).
2. Ejecutar fases; actualizar checklists solo aquí dentro.
3. Al cerrar: volcar lo permanente a `producto/` o `decisions/`.
4. Eliminar `plans/<slug>/`.

## Actores

- Quien lidera el programa (mantiene README y `phases/` del plan).
- Desarrollo (PRs por fase cuando aplique).
