# Organización de `common/components`

Este directorio contiene **código reutilizable** por web, API, consola y jobs. La idea es minimizar “carpetas sueltas” y agrupar por **responsabilidad**.

## Reglas rápidas

- **`Services/`**: lógica de negocio pesada y reutilizable (API, consola, jobs). Evitar HTTP/UI acá (ver reglas de arquitectura).
- **`Assistant/`**: todo el stack del asistente (intents UI + flows + RBAC + discovery). No dispersar clases del asistente fuera de este feature.
- **`Integrations/`**: clientes y adaptadores hacia sistemas externos.
- **`Ai/`**: proveedores/modelos/transporte/costos de IA (sin conocer HTTP).
- **`Text/`**, **`Terminology/`**, **`Logging/`**, **`Infra/`**: utilidades transversales por dominio técnico.

## Feature: Assistant

Todo lo relativo al asistente vive en:

- `Assistant/IntentEngine/`: entrypoint de clasificación y respuesta (UI match o flow).
- `Assistant/Catalog/`: catálogo de intents/UI sugeribles.
- `Assistant/SubIntentEngine/`: motor conversacional dentro de un intent (`schemas/*.yaml` como fuente de verdad).
- `Assistant/FlowManifest/`: compilación YAML → JSON `ui_type=flow` bajo `frontend/modules/api/v1/views/json/...`.
- `Assistant/UiActions/`: discovery + RBAC + enrichers (`client_open`, allowed routes, etc.).

Documentación específica: ver `Assistant/README.md`.

## Quirófano

El código de quirófano debe ir en `Services/Quirofano/` (servicios/validadores/policies relacionadas), para evitar una carpeta top-level suelta.

