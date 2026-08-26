# Runtime de datos vs metadata (cognitivo + escala)

## Contexto

Convivían en `metadata/` cosas distintas: composición del asistente (bien) y maestros/catálogos que el runtime consulta (geo, recursos, a veces códigos). Eso degrada dos objetivos a la vez:

1. **Runtime potente** — alto volumen, cache, índices, filtro por ámbito.
2. **Cognitivo** — saber dónde buscar sin sorpresas.

## Decisión

Desde el comienzo (no “cuando escale”):

| Qué | Dónde en runtime | Cómo se puebla |
|-----|------------------|----------------|
| Maestros y catálogos consultados en requests | **BD** (`geo_*`, tablas de dominio) + cache | Console seed / migración; opcional archivo solo como *input del seed* |
| Algoritmo, adapters, policy | **PHP dominio** | Código |
| Composición de producto (flows, knobs, copy, manifiestos UI, auth declarativa) | **Metadata YAML** | Git |

El runtime **no** lee YAML para listas de hechos (provincias, vecinos, recursos institucionales, sinónimos operativos masivos, etc.). YAML de metadata = lo que interpretan motores genéricos (`SubIntentEngine`, panel, prompts, knobs).

Si falta el YAML de composición, el producto puede degradar UX; si falta un maestro en BD, hay que seedar — no inventar defaults peligrosos en el orquestador.

## Alternativas descartadas

- **YAML en hot path “porque son pocas filas”:** a 100k usuarios el costo no es el parse; el costo es el mapa mental roto y no poder indexar/cachear igual que el resto.
- **Constantes PHP para vecinos/recursos “por país”:** cada país nuevo = PR en código; provincias en BD y vecinos en PHP rompe el prefijo cognitivo.
- **Dejar seeds en metadata:** one-shot / dumps no son composición de producto.

## Consecuencias

- Caso piloto: **geo multi-país** (ver [runtime-datos-y-metadata.md](../arquitectura/runtime-datos-y-metadata.md)).
- El mismo criterio se aplica al **resto de YAML del proyecto**: auditar catálogos en metadata → migrar a BD + seed console cuando sean datos de runtime.
- Integridad clínica / gates hard siguen en Yii/dominio ([captura-clinica-contratos-yii-vs-yaml.md](./captura-clinica-contratos-yii-vs-yaml.md)).
