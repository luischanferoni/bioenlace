# Design — Ejes, reshape de BCs y naming

## Regla de oro (recordatorio)

| Nivel | Eje | Ejemplo |
|-------|-----|---------|
| `Domain/<BC>/` | Bounded context | `Clinical/`, `Organization/` |
| `<BC>/<Modulo>/` | Capacidad de producto | `Capture/`, `Efector/`, `Agenda/` |
| `<Modulo>/` L1 | Capa DDD/CA | `Application/`, `Domain/`, `Infrastructure/` |
| `Application/*` | Rol CA **técnico** | `UseCase/`, `Service/`, `Presentation/`, … |
| `Domain/*` | Building block | `Model/`, `Policy/`, `Port/`, … |
| Clase | Lenguaje de negocio + **sufijo transversal** | `SaveCapture`, `CaptureDraftService` |

Plugins de BC (no módulos de capacidad clínica): `Assistant/`, `Home/`, `DataAccess/` en la raíz del BC — ya permitido en [clinical-modulos-capacidad.md](../../decisions/clinical-modulos-capacidad.md).

## Catálogo de sufijos transversales

Solo estos (detalle en [domain-folder-grammar.md](../../decisions/domain-folder-grammar.md) / rule `ddd-un-eje-por-nivel`):

`UseCase` (verb phrase) · `*Presenter` · `*Service` · `*Resolver` · `*Applier` · `*Access` · `*Agent`/`*AgentPolicy` · Aggregate/VO · `*Catalog` · `*Policy` · `*Repository`/`*Port`/`*Registry` · `*RowContract` · ACL `*Connector`/`*Mapper`

**No arquitectura:** `*Checkpoint`, `*Pipeline`, `*Normalizer`, `*Sanitizer`, `*PostProcessor`, `*Overrides`, `*Validator` (Application), `*Orchestrator`, `*Processor`, `*Formatter`, `*Helper`, `*Support`, `*Manager`.

`Dto/` como carpeta Application → mover a `Application/Service/` (si es wiring) o tipar en Domain / namespace sin carpeta de capacidad; preferir Value Objects en `Domain/Model/` cuando sean de negocio.

## Diagnóstico por BC (estado actual)

### Clinical (módulo-primero — incompleto)

Ya tiene módulos. Falta **aplanar Application** y naming:

| Módulo | Carpetas Application no-CA (ejemplos) |
|--------|----------------------------------------|
| Encounter | `Documentation/`, `AiContext/`, `EncounterJourney/`, `PatientSummary/`, `Dto/` |
| CarePlan | `Dto/`, `Reminder/` |
| Laboratory / Prescription / Inpatient | `Dto/` |
| Resto | Revisar `*Service` sueltos vs `UseCase/` |

**Acción:** dominio → nombre de clase; carpeta → rol CA (`Service/` / `UseCase/` / `Presentation/`). No crear `Encounter/Application/Documentation/` — sí `EncounterDocumentationService` (o UseCase) bajo `Service/` / `UseCase/`.

### Organization (layer-first + capacidades dentro de Application)

Hoy:

```text
Organization/
  Application/{Authorization,Billing,Efectores,Entitlement,Flows,Presentation,
               ProfesionalEfectorServicio,ProfesionalHorario,Seed,Servicios,SesionOperativa}
  Domain/Model/
  Infrastructure/
  Assistant/ | DataAccess/
```

**Problema:** el eje en `Application/*` es **capacidad** (Efectores, Servicios…), no rol CA.

**Reshape propuesto (módulo-primero):**

```text
Organization/
  Efector/          # oferta institucional del centro, billing ligado al efector si aplica
  Servicio/         # servicios del centro (no PES)
  Pes/              # ProfesionalEfectorServicio + horarios laborales del PES
  SesionOperativa/  # set-session / contexto operativo
  Entitlement/      # o bajo Efector si es inseparable
  Assistant/ | DataAccess/   # plugins BC
```

Cada módulo: `Application/{UseCase,Service,Presentation,Authorization,Flows}` + `Domain/` + `Infrastructure/`.

Nombres de módulo en español técnico del glosario (`Efector`, `Servicio`, `Pes`) — evitar “especialidad” como sinónimo de `id_servicio`.

### Scheduling (híbrido)

Hoy: Application layer-first + `Quirofano/` + `Home/` + `Application/BehaviorProfile/`.

**Reshape propuesto:**

```text
Scheduling/
  Agenda/           # turnos, oferta de slots, agents anti-no-show / adelantamiento
  Quirofano/        # ya existe — alinear capas internas
  BehaviorProfile/  # sacar de Application/; módulo de capacidad
  Home/ | Assistant/  # plugins
```

`BehaviorProfile` deja de ser carpeta bajo `Application/`.

### Person (híbrido)

Hoy: `Application/` raíz + `Representation/` + `Ventanilla/`.

**Reshape propuesto:**

```text
Person/
  Registry/         # alta/identidad, seed, flows de persona (ex Application raíz)
  Representation/   # ya módulo — alinear Application CA
  Ventanilla/       # ya módulo — alinear
  Assistant/ | DataAccess/
```

Si `Registry` es demasiado genérico, alternativa: `Identidad/` (producto admisión). Decidir en fase 04 con dueño de Person.

### BCs compactos (Geo, Content, Terminology, Programs)

Layer-first en la raíz **puede permanecer** mientras el BC no tenga >1 capacidad clara:

```text
Geo/
  Application/{UseCase,Service,Seed,…}
  Domain/Model/
  Infrastructure/Persistence/
```

**Condición:** no introducir carpetas de capacidad bajo `Application/`. Si aparece una segunda capacidad → promover a módulo-primero (misma regla que Clinical).

`Integrations/` sigue siendo solo README (ACL en `*/Infrastructure/External/`).

## Estrategia de migración

1. **Inventario** por BC: lista de carpetas Application no-CA + clases con sufijo fuera de catálogo.
2. **Matriz rename** (old path/FQCN → new) antes de mover archivos.
3. **Un PR por módulo** (o por fase BC); no mezclar reshape Organization con Encounter.
4. Moves + renames mecánicos primero; reorganizar lógica solo si el nombre nuevo implica otra capa (p. ej. Validator Application → Policy Domain).
5. Actualizar tests de forma / autoload / intents discovery paths.
6. No dejar facades legacy que reexporten el FQCN viejo.

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Intent discovery / `ProductMetadataPaths` | Verificar roots `Domain/<BC>/<Modulo>/Application/Flows` tras moves |
| Controllers API con FQCN hardcodeados | Grep + script de replace por fase |
| Dtos compartidos entre módulos | Dueño único; no `Shared/` dentro del BC |
| Renombrar módulo Organization rompe seeds/console | Fase 02 con checklist console + migraciones referencias |

## Decisión abierta (cerrar en fase 02)

¿`Billing` y `Entitlement` son módulos propios o viven bajo `Efector/`?  
Default del plan: **`Efector/`** absorbe billing/entitlement si el lenguaje es “del centro”; separar solo si el equipo de producto los trata como capacidades distintas.
