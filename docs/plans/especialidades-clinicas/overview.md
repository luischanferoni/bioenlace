# Especialidades clínicas — overview

## De qué se trata

Bioenlace atiende todas las especialidades clínicas. No hay una lista cerrada de
producto: qué especialidad opera un centro depende de su configuración de servicios.
Lo que sí es producto es **qué interpreta el sistema** en cada especialidad y **qué
interfaz** ofrece al profesional para registrarlo.

Este plan ordena ese trabajo en tres ejes.

## Eje 1 — Qué se extrae

Hoy la extracción pide menos de lo que el modelo de datos sabe guardar. Odontología
guarda pieza, superficies y momento del tratamiento, pero la IA sólo devolvía tipo y
código. Oftalmología guarda prácticas con ojo, prueba, estado e informe y recetas con
esfera, cilindro, eje y adición, pero dos de sus tres categorías no pedían nada.
Enfermería persiste nueve signos vitales y diez prácticas, pero declaraba cuatro
signos.

El plan hace visible esa brecha en un catálogo único y la cierra por especialidad.

## Eje 2 — Dónde se guarda

Toda la información clínica vive en el modelo FHIR del encounter. Se agrega una
extensión propia sólo cuando un dato debe consultarse o agregarse por campo —el
odontograma es el caso claro—. El resto se resuelve con observaciones tipadas.

La regla es no volver a guardar metadatos clínicos dentro del texto libre de una nota
para después parsearlos.

## Eje 3 — Cómo se muestra

Dentro del asistente hay UI JSON: manifiestos que los clientes renderizan de forma
genérica. **Fuera** del asistente hay interfaces comunes: vistas Yii con JS en la web
y widgets Flutter en la app. El odontograma, la grilla de receta de lentes y el panel
de signos vitales son interfaces comunes: se dibujan sobre la historia clínica, no
dentro del chat.

## Preguntas que responde

- ¿Qué especialidades cubre el sistema y cuáles tienen captura propia?
- ¿Qué campos interpreta la IA en cada especialidad y con qué unidad?
- ¿Dónde queda cada dato y cómo se consulta?
- ¿Qué interfaz ve el profesional y en qué plataforma?

## Actores

- **Profesional de salud**: dicta o escribe; revisa y confirma lo que la IA propone.
- **Producto clínico**: decide qué campos son obligatorios y qué se comunica al mercado.
- **Paciente**: se beneficia de un registro ordenado; no opera estas pantallas.

## Criterio de producto

Lo publicado en la web institucional y lo que muestra la historia clínica salen de la
misma fuente que usa la captura. Si el catálogo declara un campo, la IA lo pide y la
interfaz lo muestra. Si todavía no se pide, el catálogo lo marca como no extraído y no
se publica como capacidad.

## Relación con el resto

- [captura-clinica.md](../../producto/captura-clinica.md) — superficie de captura y pipeline.
- [catalogo-usos-ia.md](../../producto/catalogo-usos-ia.md) — qué hace la IA en cada paso.
- [07-servicios-y-especialidades.md](../../his-completo/07-servicios-y-especialidades.md) — servicios del centro y actos.
- [glosario-servicio-pes-acto.md](../../producto/glosario-servicio-pes-acto.md) — precisión de vocabulario.
