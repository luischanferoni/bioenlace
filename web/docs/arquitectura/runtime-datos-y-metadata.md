# Runtime de datos y metadata — mapa cognitivo

Complementa [metadata-yaml-uso.md](./metadata-yaml-uso.md) (integridad / gates) y el ADR [runtime-datos-vs-metadata.md](../decisions/runtime-datos-vs-metadata.md).

Objetivo doble, **desde el día uno**:

1. **Runtime potente** — BD + índices + cache; seeds fuera del hot path.
2. **Cognitivo** — un lugar obvio por tipo de cosa; sin maestros escondidos en YAML o constantes.

## Dónde buscar

| Buscás… | Mirás… |
|---------|--------|
| País / provincia / vecinos / recursos geo | Tablas `geo_*`, modelos y services Domain, seeds en `console` |
| “¿Puede guardarse / emitirse?” | `*Input`, AR, services de dominio |
| Flow del asistente, copy, manifests UI, auth motor | `Domain/<BC>/Application/Flows/`, `Platform/Assistant|Ui|Permission|Ai/…` |
| Knobs de negocio / agents | PHP `Domain/<BC>/Domain/*Catalog`, `Application/Agent/*AgentPolicy` |
| Cableado handler → PHP | `common/config/product-registries.php` |
| Config de arranque (DB, secretos) | `common/config/` (Yii) |

## Preguntas al clasificar un dato

1. ¿El request lo **consulta** como hecho (listar, lookup, join)? → **BD** (+ cache).
2. ¿Es **guion / umbral / texto / manifiesto** que un motor genérico interpreta? → **YAML metadata**.
3. ¿Es **cómo poblar** el maestro? → **console seed** (puede leer un archivo de import; el runtime no).
4. ¿Es **algoritmo** (ordenar por IP, mapear MPI → id)? → **PHP dominio**.

## Escala (alto volumen)

- Filtrar siempre por ámbito (`id_pais`, efector, etc.); no `find()->all()` global en hot path.
- Cache de lectura para maestros casi inmutables (TTL largo; invalidar en seed/admin).
- El medio de autoría del seed (PHP arrays, CSV, YAML de import) **no** define el runtime.

## Caso geo (qué haremos)

Plantilla para el resto del proyecto:

1. `geo_paises` + `geo_provincias.id_pais` + unique (país, código subdivision).
2. `geo_provincia_vecinos` en BD (no constante PHP).
3. Recursos institucionales en BD (`geo_recursos_*`; seed `clinical-seed/geo-multipais`).
4. `ProvinciaSuggestionService`: país (IP o query `iso2`/`id_pais`) → provincias de ese país → vecinos desde BD.
5. API sugerir: query opcional de país; cada fila con `id_pais` / `iso2`; incluye listado `paises`.
6. Contexto paciente: exporta país derivado de la provincia.
7. Seeds por país en console (`provincias-argentina`, `provincias-uruguay`, `geo-multipais`).
8. MPI/RENAPER: resolución de provincia acotada a AR (`id_pais`); extender adapter por país cuando haya fuente.

## Rollout al resto de metadata

Misma regla, archivo por archivo bajo la metadata **colocalizada**:

- **Se queda en YAML:** flow, routing, copy, knob de motor, manifest UI, auth declarativa (capabilities/policies como composición que synca a RBAC).
- **Sale a PHP Domain o BD (+ seed console):** knobs de negocio; cualquier “catalog” de hechos que el runtime parsearía con Yaml::parseFile para lookup.

Al tocar metadata: si la respuesta a “¿es lookup de hechos en request?” es sí → no agregar más datos ahí; migrar patrón geo.

Mapa: [`common/metadata/bioenlace/README.md`](../../common/metadata/bioenlace/README.md). ADR DDD: [ddd-bounded-contexts-capas-y-metadata.md](../decisions/ddd-bounded-contexts-capas-y-metadata.md).
