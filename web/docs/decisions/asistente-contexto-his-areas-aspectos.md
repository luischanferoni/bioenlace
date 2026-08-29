# Contexto HIS del asistente: áreas + aspectos

## Contexto

La 2ª IA (conversacional clinical e informational) necesita datos del HIS (turno próximo, reglas del centro, agenda) sin pegar la historia clínica completa ni nombres de tablas (`EfectorTurnosConfig`) en prompts. El preprocess ya clasifica canal y extracciones; faltaba un mecanismo escalable para decidir **qué** volcar y **cómo** cargarlo desde PHP.

Alternativas descartadas en diseño: manifests YAML de bloques por pregunta, predicados regex en orquestadores (`if preg_match tarde`), catálogo de aspectos en preprocess.

## Decisión

Dos niveles de vocabulario HIS, separados por responsabilidad:

| Nivel | Código | Preprocess | PHP post-preprocess |
|-------|--------|------------|---------------------|
| **Área HIS** | `AssistantContextHISArea` | Catálogo en prompt + `context_areas` en JSON | Entrada al resolver de aspectos |
| **Aspecto** | `AssistantContextHISAreaAspect` | No visible | Loaders → claves JSON en volcado 2ª IA |

Cadena en request:

1. `ChatPreprocessService` valida `context_areas` contra enum de áreas.
2. `AssistantContextAnchorResolver` fija sujeto, cita referencia, `site_id`, PES.
3. `AssistantContextAreaAspectResolver` mapea áreas + extracciones → aspectos (tabla PHP, no YAML).
4. `AssistantContextAspectLoaderRegistry` ejecuta loaders registrados en `product-registries.php`.
5. `AssistantContextAssemblyService` forma bloque `--- context:his ---` para la 2ª IA.

Saludo o meta sin datos → `context_areas: []` → sin loaders.

MVP: área `appointments` con cuatro aspectos implementados. Otras áreas declaradas en enum hasta tener loaders.

## Alternativas descartadas

- **Preprocess elige aspectos:** duplica lógica de negocio en el modelo; frágil ante nuevos aspectos.
- **YAML de bloques por pregunta:** deuda de mantenimiento; viola regla «0 hardcode» en orquestadores.
- **Volcado directo de AR en orquestador:** sin capa de aspecto/ancla; difícil truncar y auditar.
- **HC completa en 2ª IA:** costo, privacidad y confusión con lecturas autorizadas (DataAccess / intents).

## Consecuencias

- Nuevos datos HIS para el asistente: definir aspecto en enum, loader en dominio, registro en `assistantContextAspectLoaders`; opcional ampliar mapa área→aspectos en `AssistantContextAreaAspectResolver`.
- Prompts `clinical.yaml` / `informational.yaml`: bloque `context:his` con reglas **transversales** (usar solo datos no nulos del volcado).
- **Descartado:** registro central `limitations[]` por cada dato que el sistema aún no expone — escala mal; el loader ya señala ausencia con `null`/campo omitido.
- Debug QA: `asistente_context_debug` adjunta `context_applied` al envelope público.
- Parámetros de cap: `asistente_context_max_aspects`, `asistente_context_max_chars`, `asistente_context_history_limit`.

Documentación: [producto/asistente-y-chat.md](../producto/asistente-y-chat.md), [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md), QA [asistente-consultas.md](../qa/paciente/asistente-consultas.md).
