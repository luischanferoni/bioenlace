# Norte DDD: modelo rico (Domain primero)

**Estado:** aceptado (norte de diseño).  
**Piloto de aplicación:** módulo `Clinical/Capture` (oleadas).  
**Relación con ADRs previos:** [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md), [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md) y [domain-folder-grammar.md](./domain-folder-grammar.md) siguen vigentes para **BC / módulo de capacidad / espejo de capas**. Este ADR **prioriza el contenido de las capas** sobre la gramática de carpetas: si hay conflicto entre “dónde quedó el archivo” y “dónde vive la regla”, gana este norte.

## Contexto

La migración a `Application/` · `Domain/` · `Infrastructure/` ordenó el árbol, pero en varios módulos (p. ej. Capture) el **Domain quedó delgado** y Application acumula policies, validators, loggers y orquestadores gordos. Eso es *service layer* con packaging DDD, no DDD serio.

Referencias vivas en el repo: aggregates en `Encounter/Domain/Model` y ports + modelo en `PedidoAtencion/Domain`. Piloto Application por capacidad: `Capture/Application/` (ver [Capture/README.md](../../common/components/Domain/Clinical/Capture/README.md)).

## Decisión

### 1. Capas por responsabilidad (no por tamaño de carpeta)

| Capa | Qué va | Qué no va |
|------|--------|-----------|
| **Domain** | Aggregates, VO, policies tipadas, catálogos de negocio, **ports** (interfaces que el dominio necesita) | Yii, HTTP, filesystem, cache, clientes externos, AR |
| **Application** | Casos de uso finos: cargar → mutar Domain → persistir → side effects; DTO/commands; Authorization de aplicación; presenters de forma API | Reglas de completitud, léxico clínico, invariantes de estado |
| **Infrastructure** | Adapters de ports (STT, terminología, repos sobre AR), logging a disco, cache | Semántica clínica |

ActiveRecord en `common/models/<BC>/` es **detalle de persistencia**. Application/Infrastructure reconstituyen aggregates; el Domain no conoce columnas ni `save()`.

### 2. Application fino (Clean Architecture: interactors)

Un **caso de uso** orquesta una intención. Preferir `Application/UseCase/*` (una clase = una intención) frente a un `*Service` multi-método que concentra pipeline + reglas + I/O.

Si un `*Service` mezcla etapas, reglas de Domain e I/O a varios módulos: partir use cases y bajar reglas a Domain. **No** reintroducir facades “legacy” que vuelvan a envolver todos los use cases.

### 3. Ports en Domain, adapters en Infrastructure

Ejemplo Capture: el dominio pide “texto a partir de audio/device”; no instancia `SpeechToTextManager`. El adapter vive bajo `Infrastructure/` y puede delegar a `Platform/Ai`.

### 4. Eje de subcarpetas (mismo criterio en Domain, Application e Infrastructure)

**Eje único:** lenguaje ubicuo / **capacidad de negocio** del módulo.  
**No** cajones técnicos por rol vago: `Support/`, `Helpers/`, `Shared/`, `Utils/`, `Common/`, `Pipeline/` (como orquestador genérico).

| Capa | Ejemplos válidos (lenguaje) | Ejemplos inválidos (cajón técnico) |
|------|-----------------------------|-------------------------------------|
| **Domain** | `Model/`, `Catalog/`, `Policy/`, `RowContract/`, `Port/` | `Support/`, `Shared/` |
| **Application** | `Checkpoint/`, `Extraction/`, `Definition/`, `RowContract/` | `Support/`, `Helpers/`, `Workflow/` genérico |
| **Infrastructure** | `Persistence/`, `SpeechToText/`, `Terminology/`, `Logging/` | PHP suelto sin tema |

**Excepciones de Clean Architecture / gramática Bioenlace** (no son “cajones helper”; son roles CA estables):

| Carpeta bajo `Application/` | Rol |
|-----------------------------|-----|
| `UseCase/` | Interactors / entrypoints (una intención) |
| `Presentation/` | `*Presenter` / `*PresentationService` — forma de respuesta para API/UI JSON (no HTTP Yii) |
| `Authorization/`, `Flows/`, `Agents/` | Plugins de producto (RBAC app, asistente, agents) |

Agrupar por tema **no sustituye** bajar reglas a Domain.  
**Prohibido** inventar esas carpetas técnicas como L1 del módulo (hermanas de `Application/` / `Domain/` / `Infrastructure/`). Detalle de packaging: [domain-folder-grammar.md](./domain-folder-grammar.md).

### 5. Plugins de producto (Bioenlace)

`Application/Flows`, `Agents`, y a veces `Presentation` como adapter de pantallas del motor, más `Assistant/` / `Home/` en el BC, son **adapters al motor Platform**, no el núcleo DDD. Pueden coexistir en el módulo; no se usan para meter reglas de negocio.

`Presentation/` también aloja presenters de **forma API del propio módulo** (piloto Capture: `ClinicalCapturePresenter`). Sigue sin ser Domain.

### 6. Módulo Capture (límites)

- **Se mantiene el nombre** `Capture`: capacidad = intake clínico (texto/audio → extracción → issues → checkpoint).
- **No** se renombra a Documentation: mezclaría intake con lifecycle multi-módulo.
- Application implantada por capacidad: `UseCase/`, `Checkpoint/`, `Presentation/`, `Extraction/`, `RowContract/`, `Definition/` — sin facades legacy en raíz.
- Deuda explícita: orquestación gorda de persistencia hacia Encounter / CarePlan / Emergency / Specialty / Inpatient debe **acercarse a Encounter** (o use cases de Encounter) en oleadas posteriores; Capture no es fachada del HIS completo.

Guía operativa: `web/common/components/Domain/Clinical/Capture/README.md`.

### 7. Cómo construir un módulo o BC nuevo

1. Definir lenguaje y **aggregate raíz** (aunque empiece mínimo).
2. Poner invariantes y policies en `Domain/` (sin I/O).
3. Declarar **ports** que el Domain/Application necesiten.
4. Application: un entrypoint por caso de uso (`UseCase/` o `*Service` fino); subcarpetas solo por **capacidad** del lenguaje.
5. Infrastructure: adapters + ancla Persistence → `models/`.
6. Solo después: Flows/Agents/Presentation de producto si hace falta.
7. Tests de Domain sin bootstrap Yii cuando sea posible.

### 8. Checklist al tocar código existente

- ¿Esta regla sigue válida sin DB/HTTP? → Domain.
- ¿Es “llamar A luego B y persistir”? → Application (`UseCase/`).
- ¿Habla con disco, cache, API externa, AR? → Infrastructure (detrás de port si el Domain la necesita).
- ¿La subcarpeta nombra **capacidad del lenguaje** o un cajón técnico? → solo lenguaje, en la capa correcta.
- ¿Estoy creando `Support/` / `Helpers/` / facade “por compatibilidad”? → **no**; repartir en UseCase + capacidad + Presenter.

## Alternativas descartadas

- Tratar [domain-folder-grammar.md](./domain-folder-grammar.md) como norte suficiente (solo packaging).
- Renombrar Capture o partir ya en dos BCs sin mover reglas (cosmética).
- Domain model puro sin AR en un solo big-bang (arriesgado en Yii2; oleadas + reconstitución).
- Carpeta `Shared/` dentro de Clinical para policies “usadas por muchos”.
- `Application/Support` o `Application/Pipeline` como cajón de helpers transversales.
- Mantener facades legacy que re-envuelven todos los use cases.

## Consecuencias

- Capture piloto: Domain rico + Documentation en Encounter + use cases por etapa + Application por capacidad (oleadas).
- Tests de forma de carpetas siguen; no reemplazan revisión de capa.
- Documentación de arquitectura apunta aquí como norte de diseño; la gramática de carpetas lo operacionaliza.
