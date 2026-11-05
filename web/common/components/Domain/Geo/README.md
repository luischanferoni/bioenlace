# Geo

Maestro geográfico multi-país: país, provincia, departamento, localidad, barrio, y los recursos institucionales provinciales con su tipo y alias.

Existe como dominio porque tiene **dueño propio**: nadie más persiste estas filas, y varios dominios las consumen (`Person` para el domicilio, `Organization` para ubicar el efector, `Clinical` para derivar a un recurso provincial). Antes estaba repartido entre `Domain/Person/Service/` y `Domain/Organization/Service/`, que es justo la dispersión que el árbol espejo elimina.

| Qué | Dónde |
|-----|-------|
| Modelos (AR) | `common/models/Geo/` |
| Lookup y sugerencia en request | `Application/Service/` |
| Carga del maestro | `Application/Seed/` (consola; el request no lee archivos) |

La separación runtime/seed la pide [`runtime-datos-vs-metadata.mdc`](../../../../../.cursor/rules/runtime-datos-vs-metadata.mdc): el maestro se consulta en **BD** con cache, y se puebla desde consola.

## Coordenadas → jerarquía (`GeoLocationResolver`)

El alta comercial manda una coordenada y recibe país/provincia/departamento/localidad. `GoogleGeocodingClient` traduce la coordenada y devuelve, por nivel, **todos** los nombres candidatos que reporta Google (en CABA la `locality` es "Buenos Aires" y el barrio viene en `sublocality_level_1`).

`GeoLocationResolver` resuelve cada nivel dentro del padre ya resuelto (siempre sabemos el país) comparando nombres **normalizados**: minúsculas, sin acentos, sin signos y sin los prefijos que agrega Google (`Provincia de`, `Partido de`, `Departamento de`, …), más alias explícitos (`Ciudad Autónoma de Buenos Aires` → `CABA`). Cuando el padre no se pudo resolver, el nivel sólo se acepta si el nombre es único (no se adivina entre homónimos).

Cuando hay match se guarda el nombre original de Google en `geo_provincias.google_nombre`, `geo_departamentos.google_nombre` y `geo_localidades.google_nombre` (migración `m261105_120000_geo_google_nombre`), así la próxima búsqueda es una igualdad directa y resiste cambios de nomenclatura. Las columnas son nullable y, si la migración no corrió, el resolver sigue funcionando con la comparación normalizada.

## Quién entra por servicio

Los flujos de otros dominios no leen el AR de geo directamente: piden por servicio. Ejemplo: el hidratador de recurso provincial del asistente vive en `Domain/Person/Assistant/` porque el sujeto es el paciente, y consume `Application/Service/ProvincialResourceLookupService`.
