# Documentación Bioenlace

Documentación en **lenguaje natural**: cómo funciona cada área del producto de punta a punta, no el detalle de un endpoint o un archivo suelto.

## Dónde está cada cosa

| Carpeta | Para qué sirve |
|---------|----------------|
| [producto/](./producto/README.md) | Historias por área (turnos, guardia, internación, laboratorio, apps, clínica…) — cómo se conectan cron, API, IA y base de datos |
| [arquitectura/](./arquitectura/README.md) | Cómo está armado el asistente (IntentEngine, SubIntentEngine) — con diagramas, sin código |
| [his-completo/](./his-completo/README.md) | Mapa de madurez hacia un HIS hospitalario completo (qué hay / qué falta) |
| [costos/](./costos/README.md) | Estimación de costos de IA e infraestructura |
| [decisions/](./decisions/README.md) | Decisiones técnicas transversales cerradas |
| [modelo-de-negocio/](./modelo-de-negocio/README.md) | Casos comparativos: salud pública, sector privado, vías de monetización y [business plan](./modelo-de-negocio/business-plan/README.md) |

`plans/` es carpeta **temporal de trabajo** (programas grandes en construcción). No se enlaza desde el resto de esta documentación; al terminar un programa se borra su carpeta y lo estable queda en `producto/` o `decisions/`. Ver [plans/README.md](./plans/README.md) solo si estás ejecutando un plan activo.

## Convención de nombres

- Archivos en **minúsculas** y **kebab-case** (`turnos.md`, `asistente-motores.md`).
- Sin carpeta `flows/` por dominio: los flujos end-to-end viven en `producto/`.
- Sin fragmentos de código en la documentación de producto y arquitectura.

## Código como referencia

La implementación vive en el repositorio (`web/common/components/…`, API v1, clientes de Bioenlace). Esta documentación no sustituye leer el código cuando hace falta un detalle exacto.
