# Arquitectura (visión de conjunto)

Explica piezas que atraviesan varios módulos del producto. Hoy el foco principal es el **asistente conversacional**.

| Documento | Contenido |
|-----------|-----------|
| [common-components.md](./common-components.md) | Organización de `web/common/components/` (leer antes de mover código) |
| [metadata-yaml-uso.md](./metadata-yaml-uso.md) | Qué va en YAML vs modelos Yii / dominio (integridad, gates, knobs) |
| [asistente-motores.md](./asistente-motores.md) | IntentEngine y SubIntentEngine: qué hace cada uno y cómo encadenan |
| [rbac-catalogo-permisos.md](./rbac-catalogo-permisos.md) | RBAC Yii, permisos por `intent_id`, catálogo admin, identidad sin webvimark |

## Relacionado

- Narrativa de producto del chat: [producto/asistente-y-chat.md](../producto/asistente-y-chat.md)
- IA, Vertex (encargado) y consentimiento: [producto/ia-datos-y-privacidad.md](../producto/ia-datos-y-privacidad.md)
- ADR captura: [decisions/captura-clinica-contratos-yii-vs-yaml.md](../decisions/captura-clinica-contratos-yii-vs-yaml.md)
- Código: `web/common/components/Platform/Assistant/`
