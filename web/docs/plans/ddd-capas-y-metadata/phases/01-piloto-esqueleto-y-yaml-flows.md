# Fase 01 — Esqueleto de capas en un BC piloto + Platform Assistant

**Estado: hecha** (Scheduling flows + Platform Assistant YAML colocalizados).

## Hecho

1. `metadata/bioenlace/scheduling/intents/**` → `Domain/Scheduling/Application/Flows/intents/**`
2. `metadata/bioenlace/platform/assistant/**` → `Platform/Assistant/{Application,Channels,Presentation}/…`
   - Catalog / Routing / Schemas / Preprocess prompt / shortcuts → `Application/`
   - ui-text → `Presentation/`
   - channel prompts → `Channels/{Name}/` (PHP de canales sigue en `Chat/Channels/`)
3. `ProductMetadataPaths` apunta a paths colocalizados del Assistant.
4. Reglas Cursor de prompts/routing actualizadas a los nuevos globs.

## Criterio de done

- Intents Scheduling descubiertos como colocalizados.
- smart-catalog, Guide prompt, preprocess prompt, hints cargan desde Platform/Assistant.

## Siguiente

[Fase 02](./02-reubicar-flows-todos-los-bc.md) — Clinical, Person, Organization, platform intents.
