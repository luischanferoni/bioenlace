# Design — Plataforma

## Separación de dominio de negocio

Setup cloud y anotaciones de parámetros afectan **varios** módulos; concentrarlos evita duplicar pasos de GCP en `costos/`, `captura-clinica/` o `asistente/`.

**Alternativa descartada:** un único `README.md` en raíz de `docs/` — crece sin índice ni overview.

## Formularios dinámicos vía anotaciones

Las acciones API documentan `@paramOption` para que el generador de UI JSON conozca fuentes (`efectores`, `servicios`, etc.) sin hardcode por pantalla.

Ver [flows/action-parameter-annotations.md](./flows/action-parameter-annotations.md).

## Plan de trabajo en `flows/`

Contenido de convocatoria (presupuesto, cronograma) se conserva como referencia archivada, no como fuente de verdad del estado del repo — el estado técnico está en [plans/MIGRATION_STATUS.md](../plans/MIGRATION_STATUS.md).
