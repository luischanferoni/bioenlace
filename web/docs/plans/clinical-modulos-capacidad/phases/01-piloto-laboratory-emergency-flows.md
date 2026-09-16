# Fase 01 — Piloto Laboratory + Emergency (flows)

**Estado: hecha** (flows).

## Mapa aplicado

| Intent | Destino |
|--------|---------|
| `laboratorio.ver-resultados-como-paciente` | `Laboratory/Application/Flows/intents/read/flows/` |
| `urgencias.triage-paciente-guardia` | `Emergency/Application/Flows/intents/create/` |
| `urgencias.ver-tablero-guardia` | `Emergency/Application/Flows/intents/read/flows/` |
| `urgencias.egreso-estructurado-flow` | `Emergency/Application/Flows/intents/update/` |

## Siguiente

Fase 02: resto de flows Clinical (`internacion.*`, `receta.*`, `atencion.*`, `care-packs.*`, `tratamiento.*`).
