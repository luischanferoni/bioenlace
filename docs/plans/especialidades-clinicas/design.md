# Especialidades clínicas — design

## Decisión 1 — Un catálogo es la fuente única

`ClinicalSpecialtyCatalog` declara cada especialidad con cuatro cosas:

| Dato | Contenido |
|------|-----------|
| `code` | identificador estable, alineado con las categorías de plan de tratamiento |
| `label` | nombre visible |
| `status` | `specific`, `standard` o `planned` |
| `sections` | sólo en `specific`: categorías de captura con sus campos |

Cada sección corresponde a una categoría del workflow del encuentro y declara sus
campos con etiqueta, unidad, formato, orden y la marca `extracted`.

`EncounterDefinitionSpecialtyRegistry` ya no guarda listas propias: resuelve qué
especialidades aplican a una definición de encuentro consultando el catálogo. Esa
era la duplicación que hacía que agregar un campo requiriera tocar dos lugares.

### Por qué un campo puede estar declarado y no extraído

Una interfaz puede necesitar un campo antes de que el prompt lo pida. La marca
`extracted` distingue «la IA lo devuelve» de «la UI lo muestra». El catálogo público
sólo publica los extraídos; la historia clínica puede anticipar el resto.

## Decisión 2 — La lista de campos vive en el contrato de fila

La clave de cada campo es exactamente la que la IA devuelve. Para que prompt y
catálogo no puedan desfasarse, esa lista vive una sola vez: en el contrato de fila
(`PROMPT_FIELDS`). El modelo de entrada del formulario y el catálogo la consumen.

Un test recorre las secciones del catálogo, consulta qué pide el modelo de captura y
falla si el catálogo omite un campo pedido o si marca como extraído algo que nadie
pide.

## Decisión 3 — Escalera de derivación

Antes de crear una tabla se elige el nivel más alto que sirva:

| Nivel | Dónde | Consultable por campo |
|-------|-------|------------------------|
| 1 | observación con cantidad y unidad | sí |
| 2 | observación con carga estructurada en JSON | por código, no por campo |
| 3 | metadatos dentro de la nota de una condición | no — evitar |
| 4 | extensión propia de un recurso | sí |
| 5 | texto libre | no |

El nivel 3 es el que se está abandonando: el odontograma se armaba parseando la nota
de la condición. El nivel 4 se reserva para odontograma, traumatología y los paneles
que necesitan agregación por campo.

### Alternativa descartada

Un modelo materializado del odontograma (una fila por paciente y pieza) se descartó
por ahora: duplica el estado y obliga a mantenerlo sincronizado con cada registro. La
lectura derivada desde la extensión es suficientemente rápida para la escala de una
historia clínica y conserva una sola fuente.

## Decisión 4 — El estado dental vive en `observation`

El estado de una pieza (caries, obturada, ausente) es una observación del encuentro,
no un diagnóstico. Se persiste como observación con categoría de odontología, código
del estado y display, más una extensión con pieza, superficies y momento.

Los diagnósticos siguen en condiciones. Las prácticas siguen en procedimientos con su
propia extensión. Cada recurso tiene un rol y no se mezclan.

### Migración de datos

Las filas de estado que hoy viven como nota de condición se copian a observaciones en
una migración idempotente. Cada observación migrada queda marcada con su origen para
que volver a correr la migración no duplique nada.

## Decisión 5 — Interfaces fuera del asistente

| Pieza | Web | Móvil |
|-------|-----|-------|
| Odontograma | partial de la historia + endpoint de especialidad | bloque de la timeline |
| Receta de lentes | grilla OD/OI | bloque de la timeline |
| Signos vitales | tabla del bloque del episodio | bloque existente |

Reglas aplicadas: nada de HTML armado en JS, el `kind` de una pantalla siempre resuelve
por loader, y los umbrales de validación se leen de su modelo, nunca se copian al
cliente.

## Decisión 6 — La página institucional se genera

La sección de especialidades del sitio público se escribe entre marcadores, a partir
del catálogo. Un verificador en modo comprobación falla si el HTML quedó desactualizado.

**Cómo se evita publicar sin regenerar**

| Capa | Qué hace | Se saltea con |
|------|----------|----------------|
| Hook de pre-commit | corre el verificador junto a los gates existentes | `--no-verify` |
| Test de suite | invoca el verificador; es el gate real | — |
| Publicación | corre el verificador antes de subir | — |

Como el repositorio no tiene integración continua, el test es la única barrera que no
se puede esquivar con una bandera local.

## Riesgos

- **Workflow por nombre de servicio.** La plantilla de captura todavía se elige por
  coincidencia de texto en el nombre del servicio. Reemplazarlo por el código de
  especialidad requiere datos cargados en los servicios existentes; se hará con una
  migración de datos y no dentro de este plan.
- **Prompts.** Los prompts del asistente no se editan desde un agente. Las propuestas
  se dejan en un archivo hermano y las aplica una persona.
- **Doble escritura.** Durante la migración del estado dental conviven condiciones
  históricas y observaciones nuevas. La lectura usa sólo observaciones.
