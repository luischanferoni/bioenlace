# `Domain/Terminology/` — terminología clínica (SNOMED CT)

Namespace: `common\components\Domain\Terminology\…`  
BC **compacto** (layer-first; una capacidad). Ver [ddd-modulo-primero-vs-bc-compacto.md](../../../docs/decisions/ddd-modulo-primero-vs-bc-compacto.md).

## SNOMED

| Clase | Rol |
|-------|-----|
| `Infrastructure/External/Snowstorm/SnowstormClient` | Cliente HTTP Snowstorm |
| `Application/Service/SnomedIaCodingService` | Codificación semántica post-extracción IA |
| `Domain/Catalog/SnomedCategoryCatalog` | Categorías codificación (ECL vía metadata) |
| `Domain/Catalog/SnomedSearchProfileCatalog` | Perfiles autocomplete |
| `Application/Service/SnomedContextualPromptService` | Prompts embeddings SNOMED |
| `Domain/Catalog/SnomedTerminologyCatalog` | Vocabulario / knobs codificación |
| `Domain/Model/SnomedCodeSystem` | Code system canónico |

## Metadata producto

Un solo archivo: [`common/metadata/bioenlace/terminology/snomed-terminology.yaml`](../../metadata/bioenlace/terminology/snomed-terminology.yaml)

- **`ecl_definitions`**: strings ECL canónicos (sin duplicar en PHP ni en YAML)
- **`codification`**: categorías IA + `extraction_labels`
- **`search`**: perfiles autocomplete + `client_methods` → `SnowstormClient::get*`
- **`semantic_matching`**: umbral y límite para `SnomedIaCodingService`

Motor genérico: `Platform/Core/Product/SnomedTerminologyMetadata.php`.

## Modelos AR

Tablas SNOMED en `common/models/Terminology/Snomed/` (persistencia local de términos).

**No** usar `common/components/Terminology/` — eliminado; todo el código vive bajo `Domain/Terminology/`.
