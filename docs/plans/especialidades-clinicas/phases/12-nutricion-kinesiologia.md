# Fase 12 — Nutrición y kinesiología

| Campo | Valor |
|-------|--------|
| Estado | Cerrada |
| Depende de | 00, 02 |
| Entrega | Panel genérico reutilizable + evaluación nutricional (IMC derivado) + sesión de kinesiología (escalas validadas) en captura, historia web y API |

## Qué se hizo

### Un panel, cuatro especialidades

Obstetricia (fase 10) y salud mental (fase 11) habían escrito dos servicios casi
idénticos: normalizar el payload con el contrato, escribir una observación por
parámetro y agruparlas por panel al leer. Nutrición y kinesiología hubieran sido la
tercera y cuarta copia, así que primero se extrajo la mecánica:

- `ClinicalPanelContract` — interfaz que ya cumplían los contratos de panel:
  `panelKind()`, `observationCategory()`, `promptFields()`, `storageFor()`,
  `normalize()`.
- `ClinicalPanelService` — persistir y leer un panel sin conocer la especialidad.
  Recibe `class-string<ClinicalPanelContract>`, no un caso.
- `ObstetricsEncounterService` y `MentalHealthEncounterService` ahora delegan en él
  y conservan sólo lo suyo: la severidad derivada de los instrumentos de salud
  mental y la edad gestacional del control prenatal.

La especialidad aporta **el contrato**; el servicio genérico aporta **la
persistencia**. Agregar un panel nuevo es declarar un contrato y una línea de
wiring, no otra copia del ciclo de escritura.

### Nutrición

`NutricionEvaluacionRowContract` pide peso, talla, circunferencia de cintura,
porcentaje de grasa, objetivo, plan alimentario y adherencia.

El **IMC no se pide a la IA**: se deriva de peso y talla en `normalize()`, con un
decimal. Un IMC que llegue en el payload se descarta y se recalcula, porque dos
fuentes del mismo valor se desfasan en el primer cambio. Por eso el campo está
declarado `extracted = false` en el catálogo y no aparece en `promptFields()`, que
es la lista que arma el prompt. Si falta peso o talla, el IMC simplemente no
existe; no hay default.

`NutritionEncounterService::imcSeriesForPerson()` devuelve la evolución del IMC
lista para graficar, sin parsear notas.

### Kinesiología

`KinesiologiaSesionRowContract` pide región, lado, rango de movilidad, fuerza
muscular, dolor EVA, ejercicio indicado, sesiones indicadas y evolución.

Acá el riesgo es el opuesto al de nutrición: la IA puede devolver valores fuera de
escala. La regla es **descartar, no recortar**: un EVA de 11 o una fuerza de 6 se
eliminan del registro, porque llevarlos al máximo falsearía el dato clínico. El
lado se valida contra `SIDES` (`izquierdo`, `derecho`, `bilateral`) y un valor
desconocido se descarta. Las escalas (`PAIN_MIN/MAX`, `STRENGTH_MIN/MAX`) son
constantes del contrato, no literales repetidos en el servicio.

`KinesiologyEncounterService::painSeriesForPerson()` publica la evolución del dolor.

### Interfaces

El view builder del panel prenatal se generalizó a `PanelValoresViewBuilder`
(paneles con fecha y filas etiqueta/valor/unidad) y el partial `_panel_prenatal.php`
a `_panel_valores.php`, que recibe `titulo` y `subtitulo`. La historia web muestra
ahora tres paneles con el mismo componente: control prenatal, evaluación
nutricional y kinesiología. La API publica `nutricion` y `kinesiologia` junto a
`control_prenatal` y `salud_mental`.

El orden de los valores dentro del panel sigue `promptFields()`, así que el IMC
—derivado, fuera de esa lista— queda al final del bloque.

## Verificación

- `NutricionEvaluacionTest` — IMC derivado, IMC del payload descartado, sin medidas
  no hay IMC, IMC no pedido al extractor, almacenamiento de todos los campos, una
  sola medida alcanza para considerar completa la evaluación, ida y vuelta contra
  base con rollback.
- `KinesiologiaSesionTest` — escalas declaradas, valores en rango se conservan,
  fuera de rango se descartan sin recortar, negativos descartados, lado inválido
  descartado, ida y vuelta contra base con rollback.
- `PanelValoresViewBuilderTest` — visibilidad, unidades, paneles vacíos omitidos,
  valores en blanco descartados, panel sin fecha.
- Suite `unit clinical` sin fallas nuevas; gates `sync-agent-rules --check`,
  `audit-derived-values` y `ArchitectureHttpUsageTest` en verde.

## Lo que queda para las fases siguientes

- Los paneles de cardiología, neumonología, endocrinología y neurología (fase 13)
  hoy son sólo un contrato nuevo sobre `ClinicalPanelService`.
- La app Flutter todavía no dibuja estos paneles; la historia móvil los muestra
  como parte del resumen.
