# Decisiones (ADR)

Registro de decisiones **cerradas** que afectan a más de un módulo o que conviene no repetir en cada `design.md`.

| ID | Título | Ubicación |
|----|--------|-----------|
| Dominio clínico FHIR | Producto, modelo, API clínica, greenfield | [fhir-clinical.md](./fhir-clinical.md) |
| Protocolos de cuidado | PlanDefinition-lite en BD (Nación/Provincia); ABM superadmin | [care-protocols-plandefinition-lite.md](./care-protocols-plandefinition-lite.md) |
| Autorización solo por intents | RBAC assignable = intent_id; retiro grants atributo | [autorizacion-solo-por-intents.md](./autorizacion-solo-por-intents.md) |
| Capabilities UI nativa | RBAC assignable guardia/encounter/panel fuera de intents NL | [autorizacion-capabilities-ui-nativa.md](./autorizacion-capabilities-ui-nativa.md) |
| Captura clínica: Yii vs YAML | Integridad en `*Input` / servicios; YAML = prompts y knobs | [captura-clinica-contratos-yii-vs-yaml.md](./captura-clinica-contratos-yii-vs-yaml.md) |
| Runtime datos vs metadata | Maestros/catálogos en BD + seed console; YAML = composición | [runtime-datos-vs-metadata.md](./runtime-datos-vs-metadata.md) |
| Contexto HIS asistente | Preprocess `context_areas`; aspectos + loaders PHP; volcado 2ª IA | [asistente-contexto-his-areas-aspectos.md](./asistente-contexto-his-areas-aspectos.md) |
| Canal guide asistente | Fusión clinical + informational; 2ª IA unificada | [asistente-canal-guide.md](./asistente-canal-guide.md) |
| Catálogo inteligente asistente | 1ª IA etiqueta; PHP match + plan declarativo + log; síntesis; planificadora opcional | [asistente-catalogo-inteligente.md](./asistente-catalogo-inteligente.md) |
| Pedido servicio × acto | `servicios` = oferta del centro; actos SNOMED; glosario anti-confusión | [pedido-atencion-linea-acto.md](./pedido-atencion-linea-acto.md) |

Glosario producto (servicio / PES / acto): [producto/glosario-servicio-pes-acto.md](../producto/glosario-servicio-pes-acto.md).

Guía transversal (no ADR): [arquitectura/metadata-yaml-uso.md](../arquitectura/metadata-yaml-uso.md), [arquitectura/runtime-datos-y-metadata.md](../arquitectura/runtime-datos-y-metadata.md).

## Formato sugerido para nuevas entradas

Crear `NNNN-titulo-corto.md` en esta carpeta con:

1. Contexto  
2. Decisión  
3. Alternativas consideradas y por qué se descartaron  
4. Consecuencias  

No reemplazar entradas antiguas: añadir un archivo nuevo si la decisión cambia.
