# Design — Clinical módulos de capacidad

## Principio

```text
Domain/Clinical/<Modulo>/
  Application/     # Flows, Agent, use cases de orquestación
  Domain/          # Enum, Catalog, Policy del módulo (sin I/O)
  Infrastructure/  # External ACL del módulo (si aplica)
  Service/         # opcional mientras migra; preferir Application
```

Dependencias: módulos periféricos → núcleo. El núcleo **no** importa periferia.

```text
Emergency, Inpatient, Laboratory, Prescription, CareCohort, PedidoAtencion, Capture, HistoryExchange, Specialty
        │
        ▼
   Encounter  ◄── CarePlan (supporting; Encounter puede conocer CarePlan con cuidado)
```

## Módulos canónicos (lista inicial)

| Módulo | Dueño de (ejemplos) |
|--------|---------------------|
| `Encounter` | `EncounterStatus`, journey/motivos catalogs, lifecycle encounter, flows “atencion.*” genéricos |
| `CarePlan` | `CarePlan*`, activity kinds, reminders ligados a plan |
| `Emergency` | triage, circuito, tablero, flows `urgencias.*` |
| `Inpatient` | camas, ingreso/alta, flows `internacion.*` |
| `Laboratory` | ingest, link, flows `laboratorio.*`, ACL LIS |
| `Prescription` | RDI, flows `receta.*`, ACL repositorio |
| `CareCohort` | care packs, followup agents |
| `PedidoAtencion` | línea × acto, capacity |
| `Capture` | captura texto / workflow de documentación |
| `HistoryExchange` | cola export HC (o colgar bajo Infrastructure del BC más adelante) |
| `Specialty` | odontología / oftalmología (submódulos internos ok) |

Adapters al motor Platform: `Clinical/Assistant/`, `Home/`, `DataAccess/` — **no** son Shared de dominio; son plugins.

## Flows

```text
Clinical/<Modulo>/Application/Flows/intents/{create,read,update,delete}/[flows/]<intent>.yaml
```

Mapa piloto:

| Intent (prefijo) | Módulo |
|------------------|--------|
| `laboratorio.*` | Laboratory |
| `urgencias.*` | Emergency |
| `internacion.*` | Inpatient |
| `receta.*` | Prescription |
| `care-packs.*` / cohort | CareCohort |
| `atencion.*` | Encounter |
| `tratamiento.*` | CarePlan (o Encounter si es solo lectura de adherencia — decidir en fase) |

## Discovery

`ProductMetadataPaths::colocatedIntentRoots()` debe listar:

1. `Domain/<BC>/Application/Flows/intents` (legacy durante migración),
2. `Domain/<BC>/<Modulo>/Application/Flows/intents` (canónico),
3. `Platform/Assistant/Application/Flows/intents`.

`IntentSchemaPaths::domainFromPath` sigue devolviendo el **BC** (`clinical`), no el módulo.

## Anti-patrones

- `Clinical/Shared`, `Clinical/Common`, `Clinical/Enum` como eje.
- Flows solo bajo `Clinical/Application/Flows` al cerrar el plan.
- Módulo que importa otro periférico sin pasar por Encounter/CarePlan cuando el concepto es del núcleo.
