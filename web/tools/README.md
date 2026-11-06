# `web/tools` — tooling de la aplicación web

Criterio de esta carpeta, para que el próximo archivo caiga solo:

| Carpeta | Qué va | Cómo se identifica |
|---|---|---|
| **`tools/` (raíz del repo)** | Tooling del repositorio, no de un producto: `.cursor/rules` → `.github/instructions`, hooks del agente | No importa nada de `web/` ni de `mobile/` |
| **`web/tools/`** (esta) | **Gates**: análisis que devuelven exit code y comparan contra un baseline | Lo corre un hook o CI; no escribe código del producto |
| **`web/scripts/`** | **Generadores**: producen artefactos (assets, fixtures) o imprimen datos para una tarea | Un humano lo corre a mano |

## Contenido

| Archivo | Qué hace |
|---|---|
| `audit-thin-clients.php` + `thin-clients-baseline.json` | Cuenta patrones que el programa elimina y compara contra un tope versionado (el tope solo baja). `composer audit:thin-clients` |
| `inventory_ar_relations.php` | Inventario de relaciones ActiveRecord. `--fail-on-legacy-getid` lo vuelve gate |

La deuda de transporte HTTP llegó a cero y su baseline se borró: el invariante
`ArchitectureHttpUsageTest` corre sin allowlist y el hook `http-guard` aplica la misma regla
al editar. Ver [transporte-http-unico.md](../../docs/decisions/transporte-http-unico.md).

Reglas de la casa:

- Un gate nuevo **necesita baseline versionado** y un modo `--check`/exit code.
- Los baselines son JSON commiteado (`web/.gitignore` los exceptúa del `*.json` global).
- Nada de tooling del repo acá: ver `tools/` en la raíz.
