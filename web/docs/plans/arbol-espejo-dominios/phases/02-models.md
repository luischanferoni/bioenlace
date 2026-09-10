# Fase 2 — Modelos por dominio

## Objetivo

Que `common/models/` tenga un solo eje: dominio (o `Platform`). Hoy hay 143 archivos planos y 15 carpetas de ejes mezclados.

## Cómo se decidió el dueño

Se midió, por modelo, desde qué carpeta se lo usa (`components/Domain/<D>/`, `Platform/`, `api/<dominio>/`, `admin`, `views`, otros modelos). Sobre esa evidencia se aplicó el criterio de [`design.md` §4](../design.md): `organization` = oferta y estructura del efector, `scheduling` = agenda y turnos, `person` = perfil de cualquier rol, `clinical` = salud del paciente.

La medición corrigió varias corazonadas: `Cirugia` se usa casi solo desde `Domain\Scheduling\Service\Quirofano` (no es clinical), y los `Consulta*` **no** son duplicados muertos de los modelos FHIR — `MedicationRequestService` persiste en `ConsultaMedicamentos`.

## Tareas

### 2.1 Clasificar

- [x] Medir dueño por evidencia de uso (143 modelos planos)
- [x] Repartir las carpetas que no son dominios:
  - `search`, `forms`, `file` → `Platform/` (junto a `LoginForm` / `ContactForm`)
  - `fullcalendar/Event` → `Scheduling/`
  - `rbac` → `Platform/Permission/`
  - `DataAccess` → `Platform/DataAccess/`; `Emergency` → `Clinical/Emergency/`
  - `busquedas` → cada búsqueda al dominio del modelo que busca (24 de 30)
- [x] `Integration/` → `Integrations/` (grafía única)
- [x] Los 28 planos restantes, con las decisiones tomadas (ver «Dominios nuevos» y «Cierre»)

### 2.2 Mover

- [x] Mover en tandas verificadas: `Scheduling` (9), `Clinical` (49), resto por dominio (60), carpetas (15), `busquedas` (24)
- [x] Actualizar `namespace` + `use` en todo el repo (≈900 archivos tocados)
- [x] Los `*Input` de captura clínica no se tocaron (ya estaban en `Clinical/Input/`)
- [ ] Renombrar `Consulta*` → `Encounter*`: **postergado**, ver «Decisión pendiente: nombres» abajo

### 2.3 Dependencias cruzadas

- [x] Inventariadas al mover: quedaron como `use` explícitos entre dominios (p. ej. `Clinical\SegNivelInternacion` → `Organization\Infraestructura*`, `Scheduling\Turno` → `Organization\Efector`)
- [x] No se introdujo ninguna lectura cruzada nueva

### 2.4 Tests

- [x] `tests/unit/models/LoginFormTest.php` → `tests/unit/platform/`; carpeta `models/` eliminada
- [ ] Suite completa verde (Codeception no corre en este entorno; falta CI)

## Verificación aplicada en cada tanda

1. **Sin usos sin import**: para cada clase movida, ningún archivo la usa como código (`X::`, `new X`, `extends X`, `instanceof X`) sin importarla y sin compartir namespace. Los comentarios se excluyen.
2. **Namespace = carpeta** en los ~230 archivos de `common/models`.
3. **Carga real** de todas las clases de `common/models` vía autoloader de Yii.
4. **Referencias globales**: las 2498 apariciones textuales de `common\models\…` en php/yaml/json/js/md resuelven a un archivo existente. Las 9 rotas que quedan ya estaban rotas en `HEAD` (`AbreviaturasSugeridas`, `BarriosSearch`, `Servicios_efector`, `persona_telefono`, `TurnoWaitlist*`, `GenericoAMB/EMER`).

## Decisión pendiente: nombres

`design.md` §10 preveía renombrar los `Consulta*` hijos del encounter a `Encounter*`. Al mirarlos de cerca, el destino no es obvio: `models/Clinical/` ya usa nombres FHIR (`MedicationRequest`, `Procedure`, `VisionPrescription`, `Condition`) y los `Consulta*` son la persistencia legacy **detrás** de esos servicios. Renombrar `ConsultaMedicamentos` → `EncounterMedicamentos` lo dejaría al lado de `MedicationRequest` sin aclarar cuál es cuál. Se decide aparte.

## Cierre: los 28 planos

| Familia | Destino | Por qué |
|---------|---------|---------|
| Geo (8 + 2 búsquedas) | **`Geo`** (dominio nuevo) | Maestro con dueño propio, consumido por varios dominios |
| Programas de salud / SUMAR (6 + `sumar/` + 2 búsquedas) | **`Programs`** (dominio nuevo) | Padrón, nomenclador y autofacturación con dueño común |
| Nomencladores de actos (5) | `Terminology` | El acto es «qué se indica o pide», separado de la oferta ([`servicio-institucional-vs-pes-acto`](../../../../.cursor/rules/servicio-institucional-vs-pes-acto.mdc)) |
| Sensibilidad del dato (4 + 1 búsqueda) | `Clinical` | Es sensibilidad del dato clínico; el mapeo SNOMED es medio, no fin |
| `Novedad` (+ búsqueda) | `Content` | Novedades institucionales, junto a `InfoContentArticle` |
| `Referencia` (+ búsqueda), `Mensajes`, `DocumentosExternos` | `Clinical` | Referencia/contrarreferencia, base de mensajes del encounter, documentos del paciente |
| `Setup` | `Platform` | No es AR: sin `tableName()` ni uso |

## Dominios nuevos

`Geo` y `Programs` se suman a `components/Domain/`, con README que documenta por qué existen y dónde está su límite.

`Geo` no nació vacío: ya había **8 servicios de geo dispersos** entre `Domain/Person/Service/` (lookup provincial, sugerencia de provincia, 5 seeds) y `Domain/Organization/Service/` (`GeografiaDepdropService`). Se movieron a `Domain/Geo/Service/` y `Domain/Geo/Service/Seed/`, que es exactamente la dispersión que el árbol espejo elimina. `PacienteRecursoProvincialFlowDraftHydrator` se queda en `Domain/Person/Assistant/`: el sujeto es el paciente y entra a geo por servicio.

`Programs` arranca solo con modelos. `EncounterSumarAutofacturacionContext` se queda en `Clinical` porque su sujeto es el encounter.

## Fuera de esta fase

- Cambiar nombres de tabla o migraciones.
- Refactor de dependencias cruzadas (solo se inventarían).

## Criterios de aceptación

- [x] `common/models/` sin archivos planos (143 → 0)
- [x] Toda carpeta de `models/` es un dominio de `components/Domain/`: `Clinical` 114, `Organization` 33, `Platform` 29, `Person` 26, `Scheduling` 23, `Terminology` 11, `Geo` 10, `Programs` 10, `Integrations` 5, `Content` 4
- [ ] Suite unitaria verde sin cambios de comportamiento (Codeception no corre en este entorno; falta CI + smoke QA)

## Estado final verificado

263 clases de `common/models` cargan por el autoloader. De las **4338** referencias textuales a `common\models\…` y `common\components\Domain\…` en php/yaml/json/js/md, las 8 rotas ya lo estaban en `HEAD`. Los 2 archivos que no parsean localmente (`Integrations/AsistenteWhatsapp*`) usan sintaxis PHP 8 y el CLI local es 7.4: preexistente y ajeno.

## PR sugerido

`refactor(models): un eje por dominio en common/models`
