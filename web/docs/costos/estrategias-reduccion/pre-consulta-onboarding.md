# Conversación con el paciente y onboarding

Costes de referencia en [costos-api.md](../costos-api.md) (§1 conversación, §3 onboarding).

## Conversación con el paciente (chat asistente)

- Un solo canal; el desvío es por **`user_goal`** tras preprocess (operational, conversational, informational, unclear). Ver [matriz-casos-uso.md](./matriz-casos-uso.md).
- Respuestas predefinidas y flujos guiados en tramos operativos; IA conversacional cuando el paciente cuenta síntomas o charla sin pedir una acción del sistema.
- Context caching por contexto Vertex — [costos-api §1](../costos-api.md#1-conversación-con-el-paciente); no el 80 % global.

## Onboarding

- FAQ y árboles primero; IA en preguntas libres (**hasta 60 %** orientativo).

No incluidos en columnas de impuestos-argentina salvo lo ya modelado en costos-api §1 y §3.
