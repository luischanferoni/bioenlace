# Norte DDD: modelo rico (Domain primero)

**Estado:** aceptado (norte de diseño).  
**Piloto:** módulo `Clinical/Capture`.  
**Relación:** [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md), [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md), [domain-folder-grammar.md](./domain-folder-grammar.md).  
Si packaging y responsabilidad chocan, **gana este norte**.

## Contexto

La tríada `Application/` · `Domain/` · `Infrastructure/` ordenó el árbol, pero mezclar **roles CA** (`UseCase/`, `Presentation/`) con **capacidades de lenguaje** (`Checkpoint/`, `Extraction/`) **como hermanos** introduce dos ejes al mismo nivel. DDD y Clean Architecture no empaquetan así: eligen **un eje primario por nivel**.

## Decisión

### 1. Capas por responsabilidad

| Capa | Qué va | Qué no va |
|------|--------|-----------|
| **Domain** | Aggregates, VO, policies, catálogos, **ports** | Yii, HTTP, filesystem, cache, AR |
| **Application** | Casos de uso, presenters, wiring de aplicación | Reglas de completitud / invariantes de Domain |
| **Infrastructure** | Adapters de ports, logging, cache | Semántica clínica |

### 2. Un eje por nivel (obligatorio)

| Nivel | Eje único | Ejemplo |
|-------|-----------|---------|
| Bajo `Domain/` (producto) | Bounded context | `Clinical/`, `Scheduling/` |
| Bajo `Clinical/` | Módulo de capacidad | `Capture/`, `Encounter/` |
| Bajo el módulo | Capa DDD/CA | `Application/`, `Domain/`, `Infrastructure/` |
| Bajo `Application/` | **Capacidad / lenguaje ubicuo** | `Checkpoint/`, `Extraction/`, `Definition/`, `RowContract/` |
| Bajo una capacidad Application | **Rol CA** (si hace falta subdividir) | `UseCase/`, `Presentation/` |
| Bajo `Domain/` (del módulo) | **Lenguaje / tipo de building block** | `Model/`, `Catalog/`, `Policy/`, `Port/`, `RowContract/` |
| Bajo `Infrastructure/` | **Adapter / tecnología** | `Persistence/`, `SpeechToText/`, `Logging/` |

**Prohibido:** dos ejes como hermanos. Ejemplos inválidos:

- `Application/UseCase/` al lado de `Application/Checkpoint/`
- `Application/Presentation/` al lado de `Application/Extraction/`
- `Application/Support/`, `Helpers/`, `Shared/`, `Pipeline/` genérico
- Facades legacy que re-envuelven todos los use cases

**Permitido:** anidar el segundo eje **debajo** del primero:

```text
Application/
  Checkpoint/                 ← capacidad (eje de este nivel)
    UseCase/                  ← rol CA (eje del nivel inferior)
    Presentation/
    ClinicalCaptureCheckpoint.php
  Extraction/
    UseCase/
    …
```

### 3. Application fino (interactors)

Una clase = una intención, bajo `Application/<Capacidad>/UseCase/`.  
Colaboradores de esa capacidad (lookup, audio, wiring) viven en la misma capacidad, no en un cajón técnico transversal.

### 4. Ports en Domain, adapters en Infrastructure

Sin cambios: Domain declara ports; Infrastructure adapta (STT, AR, terminología).

### 5. Plugins de producto (Bioenlace)

`Flows/`, `Agents/`, `Authorization/` son adapters al motor Platform. Van:

- como **capacidades/plugins con nombre de producto** bajo `Application/` cuando el módulo los necesita, **o**
- en la raíz del BC (`Assistant/`, `Home/`) según gramática,

sin mezclarlos como “eje rol” enfrentado a capacidades del language del módulo. Si un módulo tiene a la vez `Checkpoint/` y `Flows/`, ambos son **áreas con nombre propio** del producto/módulo, no `UseCase/` genérico hermano de `Checkpoint/`.

`Presentation/` de forma API del módulo: **dentro** de la capacidad dueña (piloto: `Checkpoint/Presentation/`).

### 6. Capture (límites)

- Capacidad = intake (texto/audio → extracción → issues → checkpoint).
- No es fachada del HIS; persistir nota → Encounter Documentation.
- Forma Application: ver [Capture/README.md](../../common/components/Domain/Clinical/Capture/README.md).

### 7. Módulo nuevo — checklist

1. Aggregate + policies en `Domain/` (sin I/O).
2. Ports en `Domain/Port/`.
3. `Application/<Capacidad>/…` solo por lenguaje; use cases anidados.
4. Infrastructure = adapters.
5. ¿Esta carpeta hermana responde la **misma pregunta**? Si no → mal eje.

### 8. Checklist al tocar código

- ¿Regla sin DB/HTTP? → Domain.
- ¿Orquestación de una intención? → `Application/<Capacidad>/UseCase/`.
- ¿I/O? → Infrastructure.
- ¿Estoy creando un hermano con otro criterio (rol vs dominio vs tech)? → **parar**; anidar o renombrar.

## Alternativas descartadas

- Empaquetar Application solo por rol CA con capacidades como nombres de clase (válido en CA puro, pero pierde localidad del language en Capture).
- Híbrido hermanos `UseCase/` + `Checkpoint/` (dos ejes).
- `Support/` / facades legacy.
- `Shared/` dentro de Clinical.

## Consecuencias

- Capture piloto implementa **capacidad → (UseCase|Presentation)**.
- Gramática de carpetas operacionaliza este norte: [domain-folder-grammar.md](./domain-folder-grammar.md).
