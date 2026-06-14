# `Domain/Terminology/` — terminología clínica (SNOMED CT)

Namespace: `common\components\Domain\Terminology\…`

## SNOMED

| Clase | Rol |
|-------|-----|
| `Snomed/SnowstormClient` | Cliente HTTP Snowstorm; búsqueda por perfil metadata |
| `Snomed/CodificadorSnomedIA` | Codificación semántica post-extracción IA |
| `Snomed/DeferredSnomedProcessor` | Jobs diferidos de codificación |
| `Snomed/SnomedCategoryCatalog` | Categorías codificación (ECL vía metadata) |
| `Snomed/SnomedSearchProfileCatalog` | Perfiles autocomplete |
| `Snomed/SnomedContextualPromptBuilder` | Prompts embeddings SNOMED |

## Metadata producto

Un solo archivo: [`common/metadata/bioenlace/terminology/snomed-terminology.yaml`](../../metadata/bioenlace/terminology/snomed-terminology.yaml)

- **`ecl_definitions`**: strings ECL canónicos (sin duplicar en PHP ni en YAML)
- **`codification`**: categorías IA + `extraction_labels`
- **`search`**: perfiles autocomplete + `client_methods` → `SnowstormClient::get*`
- **`semantic_matching`**: umbral y límite para `CodificadorSnomedIA`

Motor genérico: `Platform/Core/Product/SnomedTerminologyMetadata.php`.

## Modelos AR

Tablas SNOMED en `common/models/Terminology/Snomed/` (persistencia local de términos).

**No** usar `common/components/Terminology/` — eliminado; todo el código vive bajo `Domain/Terminology/`.
