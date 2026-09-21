# Norte DDD: modelo rico (Domain primero)

**Estado:** aceptado (norte de diseño).  
**Piloto de aplicación:** módulo `Clinical/Capture` (oleadas).  
**Relación con ADRs previos:** [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md), [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md) y [domain-folder-grammar.md](./domain-folder-grammar.md) siguen vigentes para **BC / módulo de capacidad / espejo de capas**. Este ADR **prioriza el contenido de las capas** sobre la gramática de carpetas: si hay conflicto entre “dónde quedó el archivo” y “dónde vive la regla”, gana este norte.

## Contexto

La migración a `Application/` · `Domain/` · `Infrastructure/` ordenó el árbol, pero en varios módulos (p. ej. Capture) el **Domain quedó delgado** y Application acumula policies, validators, loggers y orquestadores gordos. Eso es *service layer* con packaging DDD, no DDD serio.

Referencias vivas en el repo: aggregates en `Encounter/Domain/Model` y ports + modelo en `PedidoAtencion/Domain`.

## Decisión

### 1. Capas por responsabilidad (no por tamaño de carpeta)

| Capa | Qué va | Qué no va |
|------|--------|-----------|
| **Domain** | Aggregates, VO, policies tipadas, catálogos de negocio, **ports** (interfaces que el dominio necesita) | Yii, HTTP, filesystem, cache, clientes externos, AR |
| **Application** | Casos de uso finos: cargar → mutar Domain → persistir → side effects; DTO/commands; Authorization de aplicación | Reglas de completitud, léxico clínico, invariantes de estado |
| **Infrastructure** | Adapters de ports (STT, terminología, repos sobre AR), logging a disco, cache | Semántica clínica |

ActiveRecord en `common/models/<BC>/` es **detalle de persistencia**. Application/Infrastructure reconstituyen aggregates; el Domain no conoce columnas ni `save()`.

### 2. Application fino

Un caso de uso orquesta. Si un `*Service` concentra pipeline + reglas + I/O + side effects a varios módulos, es deuda: partir use cases y bajar reglas a Domain.

### 3. Ports en Domain, adapters en Infrastructure

Ejemplo Capture: el dominio pide “texto a partir de audio/device”; no instancia `SpeechToTextManager`. El adapter vive bajo `Infrastructure/` y puede delegar a `Platform/Ai`.

### 4. Subcarpetas temáticas

Permitidas **dentro** de la capa correcta (`Domain/Policy`, `Infrastructure/Logging`) cuando hay lenguaje ubicuo. **Prohibido** inventar cajones técnicos L1 del módulo (`Support/`, `Workflow/` hermano de `Application/`). Agrupar por tema **no sustituye** bajar reglas a Domain.

### 5. Plugins de producto (Bioenlace)

`Application/Flows`, `Agents`, `Presentation`, `Assistant/`, `Home/` son **adapters al motor Platform**, no el núcleo DDD. Pueden coexistir en el módulo; no se usan para meter reglas de negocio.

### 6. Módulo Capture (límites)

- **Se mantiene el nombre** `Capture`: capacidad = intake clínico (texto/audio → extracción → issues → checkpoint).
- **No** se renombra a Documentation: mezclaría intake con lifecycle multi-módulo.
- Deuda explícita: orquestación gorda de persistencia hacia Encounter / CarePlan / Emergency / Specialty / Inpatient debe **acercarse a Encounter** (o use cases de Encounter) en oleadas posteriores; Capture no es fachada del HIS completo.

Guía operativa del módulo: `web/common/components/Domain/Clinical/Capture/README.md`.

### 7. Cómo construir un módulo o BC nuevo

1. Definir lenguaje y **aggregate raíz** (aunque empiece mínimo).
2. Poner invariantes y policies en `Domain/` (sin I/O).
3. Declarar **ports** que el Domain/Application necesiten.
4. Application: un entrypoint por caso de uso, fino.
5. Infrastructure: adapters + ancla Persistence → `models/`.
6. Solo después: Flows/Agents/Presentation si el producto lo pide.
7. Tests de Domain sin bootstrap Yii cuando sea posible.

### 8. Checklist al tocar código existente

- ¿Esta regla sigue válida sin DB/HTTP? → Domain.
- ¿Es “llamar A luego B y persistir”? → Application.
- ¿Habla con disco, cache, API externa, AR? → Infrastructure (detrás de port si el Domain la necesita).
- ¿La subcarpeta es lenguaje o cajón técnico? → solo lenguaje, y en la capa correcta.

## Alternativas descartadas

- Tratar [domain-folder-grammar.md](./domain-folder-grammar.md) como norte suficiente (solo packaging).
- Renombrar Capture o partir ya en dos BCs sin mover reglas (cosmética).
- Domain model puro sin AR en un solo big-bang (arriesgado en Yii2; oleadas + reconstitución).
- Carpeta `Shared/` dentro de Clinical para policies “usadas por muchos”.

## Consecuencias

- Capture piloto: policies/ports (1); aggregate + repo (2); Documentation en Encounter + pipeline vía aggregate (3/3b).
- Tests de forma de carpetas siguen; no reemplazan revisión de capa.
- Documentación de arquitectura apunta aquí como norte de diseño.
