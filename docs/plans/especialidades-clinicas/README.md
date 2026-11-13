# Plan — Especialidades clínicas

| Campo | Valor |
|-------|--------|
| Slug | `especialidades-clinicas` |
| Estado | En ejecución — fases 00, 01 y 02 cerradas; 03 en adelante pendientes |
| Dueño | Producto clínico / Backend |
| Relación | Extiende [captura-clinica.md](../../producto/captura-clinica.md) y [07-servicios-y-especialidades.md](../../his-completo/07-servicios-y-especialidades.md) |

## Contexto

La captura clínica ya interpreta texto libre y lo estructura por categorías, pero
sólo odontología y oftalmología tienen campos propios. Enfermería declara menos
signos vitales de los que el sistema sabe guardar, pediatría no tiene categoría, y
el resto de las especialidades opera con el workflow clínico estándar sin que la
plataforma lo explicite. Al mismo tiempo, la web institucional no comunica qué
interpreta el sistema en cada especialidad, y la historia clínica no ofrece
interfaces densas —el odontograma es el caso más visible—.

Este plan cierra las tres brechas: **qué se extrae** (catálogo y contratos),
**dónde se guarda** (FHIR con extensiones cuando hace falta consultar) y **cómo se
muestra** (interfaces comunes en web y Flutter, no UI JSON del asistente).

## Alcance

- Un catálogo único de especialidades con su estado real: captura propia, workflow
  estándar o pendiente.
- Extracción ampliada por especialidad, derivando todo lo que el modelo de datos ya
  soporta antes de crear tablas nuevas.
- Extensiones propias sólo donde el odontograma y los paneles lo exigen.
- Interfaces de historia clínica en web (Yii + JS) y en la app Personal de Salud.
- Sección pública de especialidades generada desde el catálogo, con gate de
  sincronización.

## Fuera de alcance

- Reescribir el motor de captura o el pipeline de IA.
- Prompts del asistente: se proponen en un archivo hermano, no se editan.
- Sustituir el workflow clínico estándar por workflows por especialidad.

## Índice

- [overview.md](overview.md) — preguntas que responde el plan y criterios de producto
- [design.md](design.md) — catálogo, escalera de derivación, persistencia e interfaces
- [phases/00-catalogo-especialidades.md](phases/00-catalogo-especialidades.md) — catálogo único y contratos de campos
- [phases/01-odontologia-extraccion.md](phases/01-odontologia-extraccion.md) — pieza, caras y momento en odontología
- [phases/02-odontologia-observacion.md](phases/02-odontologia-observacion.md) — estado dental en `observation` con extensión
- [phases/03-api-odontograma.md](phases/03-api-odontograma.md) — endpoint del odontograma
- [phases/04-web-odontograma.md](phases/04-web-odontograma.md) — odontograma en la historia web
- [phases/05-flutter-odontograma.md](phases/05-flutter-odontograma.md) — odontograma en la app

## Fases siguientes

Se ejecutan de a una y no requieren las anteriores completas, salvo donde se indica.

| Fase | Entrega | Depende de |
|------|---------|-----------|
| 06 | Oftalmología: pedir campos de práctica y receta de lentes | 00 |
| 07 | Oftalmología: grilla de receta en web y Flutter | 06 |
| 08 | Enfermería: completar signos vitales y prácticas | 00 |
| 09 | Pediatría: categoría propia con percentiles | 08 |
| 10 | Obstetricia: panel prenatal | 02 |
| 11 | Salud mental y psicología: sesiones y escalas | 02 |
| 12 | Nutrición y kinesiología: planes de tratamiento | 00 |
| 13 | Paneles de cardiología, neumonología, endocrinología y neurología | 02 |
| 14 | Traumatología: extensión por región y lado | 02 |
| 15 | Resto de especialidades: sólo catálogo y copy | 00 |
| 16 | Institucional: generador de la sección y gate de sincronización | 00, 15 |
| 17 | QA por especialidad y cierre del plan | todas |

## Dependencias

- Captura: `CaptureCategoryResolver`, `CompositeRowContractRegistry`, `EncounterDefinitionWorkflowCatalog`.
- Persistencia: `Encounter`, `Procedure` con `procedure_odontology_ext`, `Observation`, `Condition`, `CarePlan`.
- API: controladores `clinical/odontology` y `clinical/ophthalmology`, `ClinicalAccessTrait`.
- Historia web: `paciente/historia`, partials en `views/paciente/timeline/`.
- App: `patient_timeline_screen.dart` y los bloques de `screens/timeline/blocks/`.
- Gates: `web/tools/audit-derived-values.php`, `.githooks/pre-commit`, `tools/sync-agent-rules.php`.

## Criterio de cierre

Una especialidad está terminada cuando su catálogo declara los campos correctos, la
IA los devuelve, la captura los persiste en FHIR sin parsear notas, la historia
clínica los muestra en web y móvil, y la sección institucional los publica sin
transcribirlos a mano.
