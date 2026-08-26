# Canal informational_conversational

Ayuda de producto: artículo `info_content` + IA anclada a la fuente + botones CTA (RBAC).

- Prompt: `assistant/prompts/informational_conversational.yaml`
- Sin artículo → mensaje corto / menú / meta (no cae a clinical)
- Visibilidad: si el artículo declara `intent_ids` y el usuario no puede ninguno → no se sirve
