# Fase 03 — info_content y CTA

## Objetivo

Contenido editorial como fuente de informational + vínculo a intents + RBAC de admin y de visibilidad.

## Checklist

- [ ] Migración: `intent_id` (string) o `intent_ids` (JSON) en `info_content_article`.
- [ ] Admin: CRUD con scopes; roles para editar solo efector/provincia; `intent_id` global = producto.
- [ ] Integridad: `intent_id` existe en catálogo (sync/admin check, análogo a permisos).
- [ ] Informational: resolve → inyectar body en prompt informational → respuesta + botones filtrados por RBAC.
- [ ] Visibilidad: si el artículo declara intent(s) y el usuario no puede ninguno → no servir ese artículo.
- [ ] Keywords / match más tolerante (`representar` ↔ `representacion`, sinónimos seed).
- [ ] Seed: ampliar `representacion`; artículo `pre_consulta` (concepto, no preguntas del pack).
- [ ] Sacar dump-only como única respuesta (o modo degenerado si IA informational falla: ¿error o dump? — decidir en implementación; preferir no inventar).
- [ ] Reducir/eliminar `intent_semantics` de oferta en YAML de intents; keywords bastan para operational; clinical CTA desde `booking-offer` metadata.

## Criterio de salida

«Explicame representación» / follow-up «sobrino» usan artículo + CTA `vincular-menor` / `designar-representante`, no Solicitar Atención ni dump sin contexto en clinical.
