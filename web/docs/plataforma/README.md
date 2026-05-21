# Plataforma e ingeniería

Setup de proveedores cloud, convenciones de formularios dinámicos en API y documentación de planificación interna.

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Alcance del dominio |
| [design.md](./design.md) | Por qué centralizar aquí |

## Flujos

| Flujo | Archivo |
|-------|---------|
| Google Cloud (Vertex, Vision, STT) | [flows/google-cloud-setup.md](./flows/google-cloud-setup.md) |
| Anotaciones `@paramOption` en acciones API | [flows/action-parameter-annotations.md](./flows/action-parameter-annotations.md) |
| Plan de trabajo (convocatoria / presupuesto) | [flows/plan-de-trabajo.md](./flows/plan-de-trabajo.md) |

## Relacionado

- [costos](../costos/README.md) — estimación de uso IA
- [captura-clinica](../captura-clinica/README.md) — pipelines que consumen cloud
- Configuración runtime: `params.php`, componentes `IAManager` / integraciones
