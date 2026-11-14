# Fase 16 — Sección institucional generada

| Campo | Valor |
|-------|--------|
| Estado | Cerrada |
| Depende de | 00 |
| Entrega | `#especialidades` en el sitio institucional generada desde el catálogo, con gate de sincronización en pre-commit y en la suite |

## Qué se hizo

### Un generador, no una lista escrita a mano

[`tools/generate-institutional-specialties.php`](../../../../tools/generate-institutional-specialties.php)
lee `ClinicalSpecialtyCatalog` —la misma fuente que usa la captura clínica— y reescribe
el bloque que vive entre `<!-- BEGIN:especialidades -->` y `<!-- END:especialidades -->`
en `institucional/index.html`, justo antes de «Precios por tipo de atención».

- **Modo escritura** (sin argumentos): regenera el bloque.
- **Modo `--check`**: no escribe; compara y sale con código 1 si el HTML publicado no es
  exactamente el que produciría el catálogo hoy.

Lo que está fuera de los marcadores se respeta tal cual: el generador no reescribe el
resto de la página.

### Qué publica cada tarjeta

- **Título y copy**: `label` y `public_copy` del catálogo.
- **Chips**: sólo los campos con `extracted => true`. Un valor derivado como el IMC no
  se publica como campo interpretado, porque el sistema no lo pide: lo calcula.
- **Especialidades `standard`**: los chips de sus secciones adicionales, más una línea
  con las categorías del registro clínico estándar. Esa frase se arma desde
  `standardSectionLabels()`, no desde una lista escrita en el generador.
- **Especialidades `planned`**: no se publican. La sección cierra con una nota que dice
  que el resto se configura.

El total que declara el subtítulo («…en N de ellas») se cuenta, no se escribe.

### Cómo se evita publicar sin regenerar

Tres capas, todas deterministas:

1. **`--check` en `.githooks/pre-commit`** (paso 5): el commit falla con el comando a
   correr.
2. **`InstitutionalSpecialtiesSectionTest`** en la suite: mismo `--check` más un
   invariante — cada chip publicado tiene que existir en `extractedFieldsFor()` de
   alguna especialidad. Si alguien edita el HTML a mano y agrega un campo inventado, el
   test falla aunque el `--check` estuviera en verde al commitear.
3. **Marcadores**: el generador no toca nada fuera del bloque, así que regenerar nunca
   pisa ediciones legítimas del resto de la página.

### Estilos

Las tarjetas usan las variables de `institucional/css/styles.css` (`--complement-pale`
para los chips, `--border-color` para el borde). Se subió el token `?v=` del CSS en
`index.html` y `precios.html`, porque sin eso el navegador sigue sirviendo la hoja
cacheada y la sección nueva aparece sin estilos.

### Un hueco que apareció al generar

Enfermería declaraba el contrato de signos vitales desde la fase 08, pero el catálogo no
declaraba esa sección: publicar la sección institucional mostraba «Enfermería» sin
ningún valor interpretado. Se agregó `vitalsSection()` como sección adicional de
enfermería (los diez signos vitales, validación «al menos uno»), y pediatría pasó a
usar el mismo helper con la validación antropométrica que ya tenía. Como es una sección
*adicional*, `EncounterNursingCare` sigue sin identificar a una especialidad: pediatría
y obstetricia la suman a su workflow sin reclamarla.

## Verificación

- `php tools/generate-institutional-specialties.php --check` → 0; con el HTML alterado a
  mano → 1 con el mensaje de qué correr.
- `InstitutionalSpecialtiesSectionTest` (4 tests): sincronización, que estén todas las
  especialidades publicables, que el bloque quede antes de `#precios`, y que ningún chip
  publicado sea un campo que nadie extrae.
- `ClinicalSpecialtyCatalogTest` incorpora el invariante de enfermería.
- Suite `unit clinical` sin fallas nuevas; gates `sync-agent-rules --check`,
  `audit-derived-values`, `ArchitectureHttpUsageTest` en verde.

## Lo que queda

Las especialidades `planned` siguen fuera de la sección hasta que tengan captura
declarada (fases 13–15). Cuando la tengan, aparecen solas al regenerar: no hay que
tocar el HTML.
