# Quirófano: campos legacy en `cirugia`

Las columnas `procedimiento_descripcion` y `observaciones` en la tabla `cirugia` pueden contener texto cargado antes de unificar el informe clínico en **`consultas`** con `parent_class` = clase de `Cirugia` y `parent_id` = id de cirugía.

- **Texto clínico nuevo**: solo vía historia clínica → `POST /api/v1/consulta/guardar` (no desde las vistas de agenda).
- **Lectura legacy**: la API `GET /api/v1/quirofano/cirugias/<id>` sigue devolviendo esos campos si existen; la UI de agenda ya no los edita.
- **Migración opcional**: job o script que cree `Consulta`/`ConsultaIa` a partir de filas históricas y, si aplica, deje de mostrar el texto solo en `cirugia`.
